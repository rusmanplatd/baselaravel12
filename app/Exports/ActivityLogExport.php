<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ActivityLogExport implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStyles, WithTitle
{
    protected Collection $data;

    protected array $columns;

    protected array $headers;

    public function __construct(Collection $data, array $columns, array $headers)
    {
        $this->data = $data;
        $this->columns = $columns;
        $this->headers = $headers;
    }

    /**
     * @return Collection
     */
    public function collection()
    {
        return $this->data->map(function ($activity) {
            $row = [];
            foreach ($this->columns as $column) {
                $row[] = $activity[$column] ?? '';
            }

            return $row;
        });
    }

    public function headings(): array
    {
        return $this->headers;
    }

    /**
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as bold with background color
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => [
                        'argb' => 'FFE5E7EB', // Light gray background
                    ],
                ],
                'borders' => [
                    'bottom' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    ],
                ],
            ],
            // Add borders to all cells
            'A1:'.$sheet->getHighestColumn().$sheet->getHighestRow() => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['argb' => 'FFD1D5DB'],
                    ],
                ],
            ],
        ];
    }

    public function columnFormats(): array
    {
        $formats = [];

        // Apply date formatting to date columns
        foreach ($this->columns as $index => $column) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);

            if (in_array($column, ['created_at', 'updated_at'])) {
                $formats[$columnLetter] = NumberFormat::FORMAT_DATE_DATETIME;
            } elseif ($column === 'properties') {
                $formats[$columnLetter] = NumberFormat::FORMAT_TEXT;
            }
        }

        return $formats;
    }

    public function title(): string
    {
        return 'Activity Log Export';
    }
}
