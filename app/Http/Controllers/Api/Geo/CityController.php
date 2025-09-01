<?php

namespace App\Http\Controllers\Api\Geo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Geo\CityRequest;
use App\Models\Master\Geo\City;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class CityController extends Controller
{
    /**
     * Display a listing of cities
     */
    public function index(Request $request): JsonResponse
    {
        // Validate per_page parameter
        $perPage = $request->input('per_page', 15);
        $perPage = in_array($perPage, [5, 10, 15, 25, 50, 100]) ? $perPage : 15;

        $cities = QueryBuilder::for(City::class)
            ->allowedFilters([
                AllowedFilter::exact('province_id'),
                AllowedFilter::partial('code'),
                AllowedFilter::partial('name'),
                AllowedFilter::callback('province_name', function ($query, $value) {
                    $query->whereHas('province', function ($q) use ($value) {
                        $q->where('name', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::callback('country_id', function ($query, $value) {
                    $query->whereHas('province', function ($q) use ($value) {
                        $q->where('country_id', $value);
                    });
                }),
            ])
            ->allowedSorts([
                'code',
                'name',
                'created_at',
                'updated_at',
                AllowedSort::field('province_name', 'province.name'),
                AllowedSort::field('country_name', 'province.country.name'),
            ])
            ->defaultSort('name')
            ->with(['province.country', 'districts'])
            ->paginate($perPage)
            ->appends($request->query());

        return response()->json($cities);
    }

    /**
     * Store a newly created city
     *
     * @bodyParam province_id string required The ID of the province. Example: 01HXYZ123456789ABCDEF
     * @bodyParam code string required City code (max 10 characters). Example: LA
     * @bodyParam name string required City name (max 255 characters). Example: Los Angeles
     *
     * @response 201 {
     *   "id": "01HXYZ123456789ABCDEF",
     *   "province_id": "01HXYZ123456789ABCDEF",
     *   "code": "LA",
     *   "name": "Los Angeles",
     *   "created_at": "2024-01-01T00:00:00.000000Z",
     *   "updated_at": "2024-01-01T00:00:00.000000Z"
     * }
     * @response 422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "province_id": ["The province field is required."],
     *     "code": ["The city code field is required."],
     *     "name": ["The city name field is required."]
     *   }
     * }
     */
    public function store(CityRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $systemUserId = User::where('email', 'system@geo.local')->first()?->id ?? '01HXYZ123456789ABCDEF';
        $validated['created_by'] = $systemUserId;
        $validated['updated_by'] = $systemUserId;

        $city = City::create($validated);

        return response()->json($city, 201);
    }

    /**
     * Display the specified city
     */
    public function show(City $city): JsonResponse
    {
        $city->load(['province.country', 'districts']);

        return response()->json($city);
    }

    /**
     * Update the specified city
     *
     * @urlParam city string required The ID of the city. Example: 01HXYZ123456789ABCDEF
     *
     * @bodyParam province_id string The ID of the province. Example: 01HXYZ123456789ABCDEF
     * @bodyParam code string City code (max 10 characters). Example: LA
     * @bodyParam name string City name (max 255 characters). Example: Los Angeles
     *
     * @response 200 {
     *   "id": "01HXYZ123456789ABCDEF",
     *   "province_id": "01HXYZ123456789ABCDEF",
     *   "code": "LA",
     *   "name": "Los Angeles",
     *   "created_at": "2024-01-01T00:00:00.000000Z",
     *   "updated_at": "2024-01-01T00:00:00.000000Z"
     * }
     * @response 404 {
     *   "message": "No query results for model [App\\Models\\Master\\Geo\\City]."
     * }
     * @response 422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "code": ["The city code has already been taken."]
     *   }
     * }
     */
    public function update(CityRequest $request, City $city): JsonResponse
    {
        $city->update($request->validated());

        return response()->json($city);
    }

    /**
     * Remove the specified city
     */
    public function destroy(City $city): JsonResponse
    {
        if (! $city->canDeleted()) {
            return response()->json([
                'message' => 'Cannot delete city. It has associated districts.',
            ], 422);
        }

        $city->delete();

        return response()->json(['message' => 'City deleted successfully']);
    }

    /**
     * Get cities for dropdown/select
     */
    public function list(Request $request): JsonResponse
    {
        $cities = QueryBuilder::for(City::class)
            ->allowedFilters([
                AllowedFilter::exact('province_id'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('code'),
            ])
            ->allowedSorts(['name', 'code'])
            ->defaultSort('name')
            ->select(['id', 'province_id', 'code', 'name'])
            ->with(['province.country:id,name,code'])
            ->limit($request->input('limit', 100))
            ->get();

        return response()->json($cities);
    }

    /**
     * Get cities by province
     */
    public function byProvince(Request $request, string $provinceId): JsonResponse
    {
        $cities = City::where('province_id', $provinceId)
            ->orderBy('name')
            ->select(['id', 'code', 'name'])
            ->get();

        return response()->json($cities);
    }
}
