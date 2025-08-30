<?php

namespace App\Http\Controllers\Api\Geo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Geo\DistrictRequest;
use App\Models\User;
use App\Models\Master\Geo\District;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class DistrictController extends Controller
{
    /**
     * Display a listing of districts
     */
    public function index(Request $request): JsonResponse
    {
        $districts = QueryBuilder::for(District::class)
            ->allowedFilters([
                AllowedFilter::exact('city_id'),
                AllowedFilter::partial('code'),
                AllowedFilter::partial('name'),
                AllowedFilter::callback('city_name', function ($query, $value) {
                    $query->whereHas('city', function ($q) use ($value) {
                        $q->where('name', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::callback('province_id', function ($query, $value) {
                    $query->whereHas('city', function ($q) use ($value) {
                        $q->where('province_id', $value);
                    });
                }),
                AllowedFilter::callback('country_id', function ($query, $value) {
                    $query->whereHas('city.province', function ($q) use ($value) {
                        $q->where('country_id', $value);
                    });
                }),
            ])
            ->allowedSorts([
                'code',
                'name',
                'created_at',
                'updated_at',
                AllowedSort::field('city_name', 'city.name'),
                AllowedSort::field('province_name', 'city.province.name'),
                AllowedSort::field('country_name', 'city.province.country.name'),
            ])
            ->defaultSort('name')
            ->with(['city.province.country'])
            ->paginate($request->input('per_page', 15))
            ->appends($request->query());

        return response()->json($districts);
    }

    /**
     * Store a newly created district
     *
     * @bodyParam city_id string required The ID of the city. Example: 01HXYZ123456789ABCDEF
     * @bodyParam code string required District code (max 10 characters). Example: HOLLY
     * @bodyParam name string required District name (max 255 characters). Example: Hollywood
     *
     * @response 201 {
     *   "id": "01HXYZ123456789ABCDEF",
     *   "city_id": "01HXYZ123456789ABCDEF",
     *   "code": "HOLLY",
     *   "name": "Hollywood",
     *   "created_at": "2024-01-01T00:00:00.000000Z",
     *   "updated_at": "2024-01-01T00:00:00.000000Z"
     * }
     *
     * @response 422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "city_id": ["The city field is required."],
     *     "code": ["The district code field is required."],
     *     "name": ["The district name field is required."]
     *   }
     * }
     */
    public function store(DistrictRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        $systemUserId = User::where('email', 'system@geo.local')->first()?->id ?? '01HXYZ123456789ABCDEF';
        $validated['created_by'] = $systemUserId;
        $validated['updated_by'] = $systemUserId;

        $district = District::create($validated);

        return response()->json($district, 201);
    }

    /**
     * Display the specified district
     */
    public function show(District $district): JsonResponse
    {
        $district->load(['city.province.country']);

        return response()->json($district);
    }

    /**
     * Update the specified district
     *
     * @urlParam district string required The ID of the district. Example: 01HXYZ123456789ABCDEF
     * @bodyParam city_id string The ID of the city. Example: 01HXYZ123456789ABCDEF
     * @bodyParam code string District code (max 10 characters). Example: HOLLY
     * @bodyParam name string District name (max 255 characters). Example: Hollywood
     *
     * @response 200 {
     *   "id": "01HXYZ123456789ABCDEF",
     *   "city_id": "01HXYZ123456789ABCDEF",
     *   "code": "HOLLY",
     *   "name": "Hollywood",
     *   "created_at": "2024-01-01T00:00:00.000000Z",
     *   "updated_at": "2024-01-01T00:00:00.000000Z"
     * }
     *
     * @response 404 {
     *   "message": "No query results for model [App\\Models\\Master\\Geo\\District]."
     * }
     *
     * @response 422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "code": ["The district code has already been taken."]
     *   }
     * }
     */
    public function update(DistrictRequest $request, District $district): JsonResponse
    {
        $district->update($request->validated());

        return response()->json($district);
    }

    /**
     * Remove the specified district
     */
    public function destroy(District $district): JsonResponse
    {
        if (!$district->canDeleted()) {
            return response()->json([
                'message' => 'Cannot delete district. It has associated villages.'
            ], 422);
        }

        $district->delete();

        return response()->json(['message' => 'District deleted successfully']);
    }

    /**
     * Get districts for dropdown/select
     */
    public function list(Request $request): JsonResponse
    {
        $districts = QueryBuilder::for(District::class)
            ->allowedFilters([
                AllowedFilter::exact('city_id'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('code'),
            ])
            ->allowedSorts(['name', 'code'])
            ->defaultSort('name')
            ->select(['id', 'city_id', 'code', 'name'])
            ->with(['city:id,name'])
            ->limit($request->input('limit', 100))
            ->get();

        return response()->json($districts);
    }

    /**
     * Get districts by city
     */
    public function byCity(Request $request, string $cityId): JsonResponse
    {
        $districts = District::where('city_id', $cityId)
            ->orderBy('name')
            ->select(['id', 'code', 'name'])
            ->get();

        return response()->json($districts);
    }
}
