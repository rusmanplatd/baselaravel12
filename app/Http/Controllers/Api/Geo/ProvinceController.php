<?php

namespace App\Http\Controllers\Api\Geo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Geo\ProvinceRequest;
use App\Models\Master\Geo\Province;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class ProvinceController extends Controller
{
    /**
     * Display a listing of provinces
     *
     * @queryParam filter[country_id] string Filter provinces by country ID. Example: 01HXYZ123456789ABCDEF
     * @queryParam filter[code] string Filter provinces by code (partial match). Example: CA
     * @queryParam filter[name] string Filter provinces by name (partial match). Example: California
     * @queryParam filter[country_name] string Filter provinces by country name (partial match). Example: United
     * @queryParam sort string Sort provinces by field. Example: name
     * @queryParam per_page integer Number of items per page. Example: 15
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": "01HXYZ123456789ABCDEF",
     *       "country_id": "01HXYZ123456789ABCDEF",
     *       "code": "CA",
     *       "name": "California",
     *       "created_at": "2024-01-01T00:00:00.000000Z",
     *       "updated_at": "2024-01-01T00:00:00.000000Z",
     *       "country": {
     *         "id": "01HXYZ123456789ABCDEF",
     *         "code": "US",
     *         "name": "United States"
     *       },
     *       "cities": []
     *     }
     *   ],
     *   "links": {},
     *   "meta": {}
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $provinces = QueryBuilder::for(Province::class)
            ->allowedFilters([
                AllowedFilter::exact('country_id'),
                AllowedFilter::partial('code'),
                AllowedFilter::partial('name'),
                AllowedFilter::callback('country_name', function ($query, $value) {
                    $query->whereHas('country', function ($q) use ($value) {
                        $q->where('name', 'like', "%{$value}%");
                    });
                }),
            ])
            ->allowedSorts([
                'code',
                'name',
                'created_at',
                'updated_at',
                AllowedSort::field('country_name', 'country.name'),
            ])
            ->defaultSort('name')
            ->with(['country', 'cities'])
            ->paginate($request->input('per_page', 15))
            ->appends($request->query());

        return response()->json($provinces);
    }

    /**
     * Store a newly created province
     *
     * @bodyParam country_id string required The ID of the country. Example: 01HXYZ123456789ABCDEF
     * @bodyParam code string required Province code (max 10 characters). Example: CA
     * @bodyParam name string required Province name (max 255 characters). Example: California
     *
     * @response 201 {
     *   "id": "01HXYZ123456789ABCDEF",
     *   "country_id": "01HXYZ123456789ABCDEF",
     *   "code": "CA",
     *   "name": "California",
     *   "created_at": "2024-01-01T00:00:00.000000Z",
     *   "updated_at": "2024-01-01T00:00:00.000000Z"
     * }
     * @response 422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "country_id": ["The country field is required."],
     *     "code": ["The province code field is required."],
     *     "name": ["The province name field is required."]
     *   }
     * }
     */
    public function store(ProvinceRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $systemUserId = User::where('email', 'system@geo.local')->first()?->id ?? '01HXYZ123456789ABCDEF';
        $validated['created_by'] = $systemUserId;
        $validated['updated_by'] = $systemUserId;

        $province = Province::create($validated);

        return response()->json($province, 201);
    }

    /**
     * Display the specified province
     *
     * @urlParam province string required The ID of the province. Example: 01HXYZ123456789ABCDEF
     *
     * @response 200 {
     *   "id": "01HXYZ123456789ABCDEF",
     *   "country_id": "01HXYZ123456789ABCDEF",
     *   "code": "CA",
     *   "name": "California",
     *   "created_at": "2024-01-01T00:00:00.000000Z",
     *   "updated_at": "2024-01-01T00:00:00.000000Z",
     *   "country": {
     *     "id": "01HXYZ123456789ABCDEF",
     *     "code": "US",
     *     "name": "United States"
     *   },
     *   "cities": []
     * }
     * @response 404 {
     *   "message": "No query results for model [App\\Models\\Master\\Geo\\Province]."
     * }
     */
    public function show(Province $province): JsonResponse
    {
        $province->load(['country', 'cities']);

        return response()->json($province);
    }

    /**
     * Update the specified province
     *
     * @urlParam province string required The ID of the province. Example: 01HXYZ123456789ABCDEF
     *
     * @bodyParam country_id string The ID of the country. Example: 01HXYZ123456789ABCDEF
     * @bodyParam code string Province code (max 10 characters). Example: CA
     * @bodyParam name string Province name (max 255 characters). Example: California
     *
     * @response 200 {
     *   "id": "01HXYZ123456789ABCDEF",
     *   "country_id": "01HXYZ123456789ABCDEF",
     *   "code": "CA",
     *   "name": "California",
     *   "created_at": "2024-01-01T00:00:00.000000Z",
     *   "updated_at": "2024-01-01T00:00:00.000000Z"
     * }
     * @response 404 {
     *   "message": "No query results for model [App\\Models\\Master\\Geo\\Province]."
     * }
     * @response 422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "code": ["The province code has already been taken."]
     *   }
     * }
     */
    public function update(ProvinceRequest $request, Province $province): JsonResponse
    {
        $province->update($request->validated());

        return response()->json($province);
    }

    /**
     * Remove the specified province
     *
     * @urlParam province string required The ID of the province. Example: 01HXYZ123456789ABCDEF
     *
     * @response 200 {
     *   "message": "Province deleted successfully"
     * }
     * @response 404 {
     *   "message": "No query results for model [App\\Models\\Master\\Geo\\Province]."
     * }
     * @response 422 {
     *   "message": "Cannot delete province. It has associated cities."
     * }
     */
    public function destroy(Province $province): JsonResponse
    {
        if (! $province->canDeleted()) {
            return response()->json([
                'message' => 'Cannot delete province. It has associated cities.',
            ], 422);
        }

        $province->delete();

        return response()->json(['message' => 'Province deleted successfully']);
    }

    /**
     * Get provinces for dropdown/select
     *
     * @queryParam filter[country_id] string Filter provinces by country ID. Example: 01HXYZ123456789ABCDEF
     * @queryParam filter[name] string Filter provinces by name (partial match). Example: California
     * @queryParam filter[code] string Filter provinces by code (partial match). Example: CA
     * @queryParam sort string Sort provinces by field. Example: name
     * @queryParam limit integer Maximum number of results. Example: 100
     *
     * @response 200 [
     *   {
     *     "id": "01HXYZ123456789ABCDEF",
     *     "country_id": "01HXYZ123456789ABCDEF",
     *     "code": "CA",
     *     "name": "California",
     *     "country": {
     *       "id": "01HXYZ123456789ABCDEF",
     *       "name": "United States"
     *     }
     *   }
     * ]
     */
    public function list(Request $request): JsonResponse
    {
        $provinces = QueryBuilder::for(Province::class)
            ->allowedFilters([
                AllowedFilter::exact('country_id'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('code'),
            ])
            ->allowedSorts(['name', 'code'])
            ->defaultSort('name')
            ->select(['id', 'country_id', 'code', 'name'])
            ->with(['country:id,name'])
            ->limit($request->input('limit', 100))
            ->get();

        return response()->json($provinces);
    }

    /**
     * Get provinces by country
     *
     * @urlParam countryId string required The ID of the country. Example: 01HXYZ123456789ABCDEF
     *
     * @response 200 [
     *   {
     *     "id": "01HXYZ123456789ABCDEF",
     *     "code": "CA",
     *     "name": "California"
     *   }
     * ]
     * @response 404 {
     *   "message": "Country not found."
     * }
     */
    public function byCountry(Request $request, string $countryId): JsonResponse
    {
        $provinces = Province::where('country_id', $countryId)
            ->orderBy('name')
            ->select(['id', 'code', 'name'])
            ->get();

        return response()->json($provinces);
    }
}
