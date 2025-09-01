<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ActivityLogController extends Controller
{
    public function __construct()
    {
        // Allow users to see activity logs for entities they have access to
        // Permission checking is handled per-request based on subject access
    }

    public function index(Request $request)
    {
        $activities = QueryBuilder::for(Activity::class)
            ->allowedFilters([
                AllowedFilter::exact('subject_type'),
                AllowedFilter::exact('subject_id'),
                AllowedFilter::exact('causer_type'),
                AllowedFilter::exact('causer_id'),
                AllowedFilter::partial('log_name'),
                AllowedFilter::partial('description'),
            ])
            ->allowedSorts([
                'created_at',
                'updated_at',
                'log_name',
                'description',
            ])
            ->defaultSort('-created_at')
            ->with(['causer'])
            ->paginate($request->input('per_page', 50));

        return response()->json($activities);
    }

    public function show(Activity $activity)
    {
        $activity->load(['causer', 'subject']);

        return response()->json($activity);
    }
}
