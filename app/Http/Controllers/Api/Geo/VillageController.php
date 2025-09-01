<?php

namespace App\Http\Controllers\Api\Geo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Geo\VillageRequest;
use App\Models\Master\Geo\Village;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class VillageController extends Controller
{
    /**
     * Display a listing of villages
     */
    public function index(Request $request): JsonResponse
    {
        // Validate per_page parameter
        $perPage = $request->input('per_page', 15);
        $perPage = in_array($perPage, [5, 10, 15, 25, 50, 100]) ? $perPage : 15;

        $villages = QueryBuilder::for(Village::class)
            ->allowedFilters([
                AllowedFilter::exact('district_id'),
                AllowedFilter::partial('code'),
                AllowedFilter::partial('name'),
                AllowedFilter::callback('district_name', function ($query, $value) {
                    $query->whereHas('districts', function ($q) use ($value) {
                        $q->where('name', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::callback('city_id', function ($query, $value) {
                    $query->whereHas('districts', function ($q) use ($value) {
                        $q->where('city_id', $value);
                    });
                }),
                AllowedFilter::callback('province_id', function ($query, $value) {
                    $query->whereHas('district.city', function ($q) use ($value) {
                        $q->where('province_id', $value);
                    });
                }),
                AllowedFilter::callback('country_id', function ($query, $value) {
                    $query->whereHas('district.city.province', function ($q) use ($value) {
                        $q->where('country_id', $value);
                    });
                }),
            ])
            ->allowedSorts([
                'code',
                'name',
                'created_at',
                'updated_at',
                AllowedSort::field('district_name', 'district.name'),
                AllowedSort::field('city_name', 'district.city.name'),
                AllowedSort::field('province_name', 'district.city.province.name'),
                AllowedSort::field('country_name', 'district.city.province.country.name'),
            ])
            ->defaultSort('name')
            ->with(['district.city.province.country'])
            ->paginate($perPage)
            ->appends($request->query());

        return response()->json($villages);
    }

    /**
     * Store a newly created village
     *
     * @bodyParam district_id string required The ID of the district. Example: 01HXYZ123456789ABCDEF
     * @bodyParam code string required Village code (max 10 characters). Example: WEST
     * @bodyParam name string required Village name (max 255 characters). Example: West Hollywood
     *
     * @response 201 {
     *   "id": "01HXYZ123456789ABCDEF",
     *   "district_id": "01HXYZ123456789ABCDEF",
     *   "code": "WEST",
     *   "name": "West Hollywood",
     *   "created_at": "2024-01-01T00:00:00.000000Z",
     *   "updated_at": "2024-01-01T00:00:00.000000Z"
     * }
     * @response 422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "district_id": ["The district field is required."],
     *     "code": ["The village code field is required."],
     *     "name": ["The village name field is required."]
     *   }
     * }
     */
    public function store(VillageRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $systemUserId = User::where('email', 'system@geo.local')->first()?->id ?? '01HXYZ123456789ABCDEF';
        $validated['created_by'] = $systemUserId;
        $validated['updated_by'] = $systemUserId;

        $village = Village::create($validated);

        return response()->json($village, 201);
    }

    /**
     * Display the specified village
     */
    public function show(Village $village): JsonResponse
    {
        $village->load(['district.city.province.country']);

        return response()->json($village);
    }

    /**
     * Update the specified village
     *
     * @urlParam village string required The ID of the village. Example: 01HXYZ123456789ABCDEF
     *
     * @bodyParam district_id string The ID of the district. Example: 01HXYZ123456789ABCDEF
     * @bodyParam code string Village code (max 10 characters). Example: WEST
     * @bodyParam name string Village name (max 255 characters). Example: West Hollywood
     *
     * @response 200 {
     *   "id": "01HXYZ123456789ABCDEF",
     *   "district_id": "01HXYZ123456789ABCDEF",
     *   "code": "WEST",
     *   "name": "West Hollywood",
     *   "created_at": "2024-01-01T00:00:00.000000Z",
     *   "updated_at": "2024-01-01T00:00:00.000000Z"
     * }
     * @response 404 {
     *   "message": "No query results for model [App\\Models\\Master\\Geo\\Village]."
     * }
     * @response 422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "code": ["The village code has already been taken."]
     *   }
     * }
     */
    public function update(VillageRequest $request, Village $village): JsonResponse
    {
        $village->update($request->validated());

        return response()->json($village);
    }

    /**
     * Remove the specified village
     */
    public function destroy(Village $village): JsonResponse
    {
        if (! $village->canDeleted()) {
            return response()->json([
                'message' => 'Cannot delete village.',
            ], 422);
        }

        $village->delete();

        return response()->json(['message' => 'Village deleted successfully']);
    }

    /**
     * Get villages for dropdown/select
     */
    public function list(Request $request): JsonResponse
    {
        $villages = QueryBuilder::for(Village::class)
            ->allowedFilters([
                AllowedFilter::exact('district_id'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('code'),
            ])
            ->allowedSorts(['name', 'code'])
            ->defaultSort('name')
            ->select(['id', 'district_id', 'code', 'name'])
            ->with(['district:id,name'])
            ->limit($request->input('limit', 100))
            ->get();

        return response()->json($villages);
    }

    /**
     * Get villages by district
     */
    public function byDistrict(Request $request, string $districtId): JsonResponse
    {
        $villages = Village::where('district_id', $districtId)
            ->orderBy('name')
            ->select(['id', 'code', 'name'])
            ->get();

        return response()->json($villages);
    }
}
