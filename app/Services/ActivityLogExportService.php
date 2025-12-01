<?php

namespace App\Services;

use App\Exports\ActivityLogExport;
use App\Models\Activity;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use League\Csv\Writer;
use Maatwebsite\Excel\Facades\Excel;
use SplTempFileObject;

class ActivityLogExportService
{
    protected array $defaultColumns = [
        'id',
        'log_name',
        'description',
        'causer_type',
        'causer_id',
        'causer_name',
        'subject_type',
        'subject_id',
        'subject_name',
        'organization_id',
        'organization_name',
        'properties',
        'created_at',
        'updated_at',
    ];

    /**
     * Export all activities that the user has permission to view
     */
    public function exportAll(User $user, string $format = 'csv', array $columns = []): array
    {
        $query = $this->buildBaseQuery($user);
        $activities = $query->with(['causer', 'subject', 'organization'])->get();

        return $this->export($activities, $format, $columns);
    }

    /**
     * Export activities with filters applied
     */
    public function exportFiltered(User $user, array $filters, string $format = 'csv', array $columns = []): array
    {
        $query = $this->buildBaseQuery($user);
        $query = $this->applyFilters($query, $filters, $user);
        $activities = $query->with(['causer', 'subject', 'organization'])->get();

        return $this->export($activities, $format, $columns);
    }

    /**
     * Build base query based on user permissions
     */
    protected function buildBaseQuery(User $user)
    {
        $canViewAll = $user->can('audit_log:admin');
        $canViewOrganization = $user->can('audit_log:read');

        if ($canViewAll) {
            // Super admins can see all activities
            return Activity::query();
        } elseif ($canViewOrganization) {
            // Organization admins can see activities within their organizations
            $organizationIds = $user->activeOrganizations()->pluck('organizations.id');

            return Activity::whereIn('organization_id', $organizationIds)
                ->orWhere('causer_id', $user->id);
        } else {
            // Regular users can only see their own activities
            return Activity::forUser($user->id);
        }
    }

    /**
     * Apply filters to the query
     */
    protected function applyFilters($query, array $filters, User $user)
    {
        $canViewAll = $user->can('audit_log:admin');
        $canViewOrganization = $user->can('audit_log:read');

        if (! empty($filters['resource']) && $filters['resource'] !== 'all') {
            $query->where('log_name', $filters['resource']);
        }

        if (! empty($filters['organization_id']) && $filters['organization_id'] !== 'all') {
            // Only allow if user has permission to view this organization
            if ($canViewAll || ($canViewOrganization && $user->activeOrganizations()->where('organizations.id', $filters['organization_id'])->exists())) {
                $query->forOrganization($filters['organization_id']);
            }
        }

        if (! empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date'].' 23:59:59');
        }

        if (! empty($filters['causer_id']) && $filters['causer_id'] !== 'all') {
            // Only allow if user has permission to view other users' activities
            if ($canViewAll || $canViewOrganization) {
                $query->where('causer_id', $filters['causer_id']);
            }
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('properties->search_terms', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['event'])) {
            $query->where('event', $filters['event']);
        }

        if (! empty($filters['subject_type'])) {
            $query->where('subject_type', $filters['subject_type']);
        }

        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Export activities in the specified format
     */
    protected function export(Collection $activities, string $format, array $columns): array
    {
        $columns = empty($columns) ? $this->defaultColumns : $columns;
        $data = $this->transformActivities($activities, $columns);

        switch (strtolower($format)) {
            case 'json':
                return $this->exportAsJson($data, $activities->count());
            case 'excel':
                return $this->exportAsExcel($data, $columns, $activities->count());
            case 'pdf':
                return $this->exportAsPdf($data, $columns, $activities->count());
            case 'csv':
            default:
                return $this->exportAsCsv($data, $columns, $activities->count());
        }
    }

    /**
     * Transform activities into exportable data
     */
    protected function transformActivities(Collection $activities, array $columns): array
    {
        return $activities->map(function (Activity $activity) use ($columns) {
            $data = [];

            foreach ($columns as $column) {
                switch ($column) {
                    case 'id':
                        $data['id'] = $activity->id;
                        break;
                    case 'log_name':
                        $data['log_name'] = $activity->log_name;
                        break;
                    case 'description':
                        $data['description'] = $activity->description;
                        break;
                    case 'event':
                        $data['event'] = $activity->event;
                        break;
                    case 'causer_type':
                        $data['causer_type'] = $activity->causer_type;
                        break;
                    case 'causer_id':
                        $data['causer_id'] = $activity->causer_id;
                        break;
                    case 'causer_name':
                        $data['causer_name'] = $activity->causer?->name ?? 'System';
                        break;
                    case 'causer_email':
                        $data['causer_email'] = $activity->causer?->email ?? '';
                        break;
                    case 'subject_type':
                        $data['subject_type'] = $activity->subject_type;
                        break;
                    case 'subject_id':
                        $data['subject_id'] = $activity->subject_id;
                        break;
                    case 'subject_name':
                        $data['subject_name'] = $this->getSubjectName($activity);
                        break;
                    case 'organization_id':
                        $data['organization_id'] = $activity->organization_id;
                        break;
                    case 'organization_name':
                        $data['organization_name'] = $activity->organization?->name ?? '';
                        break;
                    case 'organization_code':
                        $data['organization_code'] = $activity->organization?->organization_code ?? '';
                        break;
                    case 'properties':
                        $data['properties'] = json_encode($activity->properties);
                        break;
                    case 'batch_uuid':
                        $data['batch_uuid'] = $activity->batch_uuid;
                        break;
                    case 'created_at':
                        $data['created_at'] = $activity->created_at->format('Y-m-d H:i:s');
                        break;
                    case 'updated_at':
                        $data['updated_at'] = $activity->updated_at->format('Y-m-d H:i:s');
                        break;
                    default:
                        // Handle custom properties
                        if (str_starts_with($column, 'properties.')) {
                            $propertyKey = str_replace('properties.', '', $column);
                            $data[$column] = data_get($activity->properties, $propertyKey, '');
                        }
                        break;
                }
            }

            return $data;
        })->toArray();
    }

    /**
     * Get human-readable subject name
     */
    protected function getSubjectName(Activity $activity): string
    {
        if (! $activity->subject) {
            return '';
        }

        // Try common name attributes
        foreach (['name', 'title', 'identifier', 'email'] as $attribute) {
            if (isset($activity->subject->$attribute)) {
                return $activity->subject->$attribute;
            }
        }

        return $activity->subject_type.' #'.$activity->subject_id;
    }

    /**
     * Export data as CSV
     */
    protected function exportAsCsv(array $data, array $columns, int $totalCount): array
    {
        $csv = Writer::createFromFileObject(new SplTempFileObject);

        // Set UTF-8 BOM for Excel compatibility
        $csv->setOutputBOM(Writer::BOM_UTF8);

        // Add header row
        $csv->insertOne($this->getColumnHeaders($columns));

        // Add data rows
        foreach ($data as $row) {
            $csv->insertOne(array_values($row));
        }

        $filename = $this->generateFilename('csv');

        return [
            'content' => $csv->toString(),
            'filename' => $filename,
            'mime_type' => 'text/csv',
            'size' => strlen($csv->toString()),
            'total_records' => $totalCount,
            'export_format' => 'csv',
        ];
    }

    /**
     * Export data as JSON
     */
    protected function exportAsJson(array $data, int $totalCount): array
    {
        $jsonData = [
            'export_info' => [
                'timestamp' => now()->toISOString(),
                'total_records' => $totalCount,
                'format' => 'json',
                'version' => '1.0',
            ],
            'data' => $data,
        ];

        $content = json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $filename = $this->generateFilename('json');

        return [
            'content' => $content,
            'filename' => $filename,
            'mime_type' => 'application/json',
            'size' => strlen($content),
            'total_records' => $totalCount,
            'export_format' => 'json',
        ];
    }

    /**
     * Generate column headers for CSV
     */
    protected function getColumnHeaders(array $columns): array
    {
        return array_map(function ($column) {
            return match ($column) {
                'id' => 'ID',
                'log_name' => 'Resource',
                'description' => 'Description',
                'event' => 'Event',
                'causer_type' => 'User Type',
                'causer_id' => 'User ID',
                'causer_name' => 'User Name',
                'causer_email' => 'User Email',
                'subject_type' => 'Subject Type',
                'subject_id' => 'Subject ID',
                'subject_name' => 'Subject Name',
                'organization_id' => 'Organization ID',
                'organization_name' => 'Organization Name',
                'organization_code' => 'Organization Code',
                'properties' => 'Properties',
                'batch_uuid' => 'Batch UUID',
                'created_at' => 'Created At',
                'updated_at' => 'Updated At',
                default => ucfirst(str_replace(['_', '.'], ' ', $column)),
            };
        }, $columns);
    }

    /**
     * Generate filename for export
     */
    protected function generateFilename(string $extension): string
    {
        return 'activity_log_export_'.now()->format('Y-m-d_H-i-s').'.'.$extension;
    }

    /**
     * Get available columns for export
     */
    public function getAvailableColumns(): array
    {
        return [
            'basic' => [
                'id' => 'ID',
                'log_name' => 'Resource',
                'description' => 'Description',
                'event' => 'Event',
                'created_at' => 'Created At',
            ],
            'user_info' => [
                'causer_type' => 'User Type',
                'causer_id' => 'User ID',
                'causer_name' => 'User Name',
                'causer_email' => 'User Email',
            ],
            'subject_info' => [
                'subject_type' => 'Subject Type',
                'subject_id' => 'Subject ID',
                'subject_name' => 'Subject Name',
            ],
            'organization_info' => [
                'organization_id' => 'Organization ID',
                'organization_name' => 'Organization Name',
                'organization_code' => 'Organization Code',
            ],
            'metadata' => [
                'properties' => 'Properties (JSON)',
                'batch_uuid' => 'Batch UUID',
                'updated_at' => 'Updated At',
            ],
        ];
    }

    /**
     * Validate export request
     */
    public function validateExportRequest(User $user, array $filters = []): array
    {
        $errors = [];

        // Check if user has export permissions
        if (! $user->can('audit_log:admin')) {
            $errors[] = 'You do not have permission to export activity logs.';
        }

        // Validate date range
        if (! empty($filters['from_date']) && ! empty($filters['to_date'])) {
            $fromDate = \Carbon\Carbon::parse($filters['from_date']);
            $toDate = \Carbon\Carbon::parse($filters['to_date']);

            if ($fromDate->gt($toDate)) {
                $errors[] = 'From date cannot be later than to date.';
            }

            // Limit export range to prevent performance issues
            if ($fromDate->diffInDays($toDate) > 365) {
                $errors[] = 'Export date range cannot exceed 365 days.';
            }
        }

        // Check record count limit
        $query = $this->buildBaseQuery($user);
        $query = $this->applyFilters($query, $filters, $user);
        $count = $query->count();

        if ($count > 50000) {
            $errors[] = "Too many records to export ($count). Please add filters to reduce the number of records below 50,000.";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'estimated_records' => $count,
        ];
    }

    /**
     * Export data as Excel
     */
    protected function exportAsExcel(array $data, array $columns, int $totalCount): array
    {
        $headers = $this->getColumnHeaders($columns);
        $filename = $this->generateFilename('xlsx');

        $export = new ActivityLogExport(collect($data), $columns, $headers);
        $content = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);

        return [
            'content' => $content,
            'filename' => $filename,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'size' => strlen($content),
            'total_records' => $totalCount,
            'export_format' => 'excel',
        ];
    }

    /**
     * Export data as PDF
     */
    protected function exportAsPdf(array $data, array $columns, int $totalCount): array
    {
        $headers = $this->getColumnHeaders($columns);
        $filename = $this->generateFilename('pdf');

        // Prepare data for PDF view
        $exportData = [
            'title' => 'Activity Log Export',
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'total_records' => $totalCount,
            'headers' => $headers,
            'data' => $data,
            'columns' => $columns,
        ];

        $pdf = Pdf::loadView('exports.activity-log-pdf', $exportData)
            ->setPaper('a4', 'landscape')
            ->setOptions([
                'defaultFont' => 'sans-serif',
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
            ]);

        $content = $pdf->output();

        return [
            'content' => $content,
            'filename' => $filename,
            'mime_type' => 'application/pdf',
            'size' => strlen($content),
            'total_records' => $totalCount,
            'export_format' => 'pdf',
        ];
    }
}
