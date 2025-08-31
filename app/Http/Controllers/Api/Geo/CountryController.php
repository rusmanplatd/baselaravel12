<?php

namespace App\Http\Controllers\Api\Geo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Geo\CountryRequest;
use App\Models\Master\Geo\Country;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CountryController extends Controller
{
    /**
     * Display a listing of countries
     *
     * @group Geographic Data
     *
     * @groupName Geographic Data
     *
     * @queryParam filter[code] string Filter countries by code (partial match). Example: US
     * @queryParam filter[name] string Filter countries by name (partial match). Example: United
     * @queryParam filter[iso_code] string Filter countries by ISO code (partial match). Example: US
     * @queryParam filter[phone_code] string Filter countries by phone code (partial match). Example: +1
     * @queryParam sort string Sort countries by field. Example: name
     * @queryParam per_page integer Number of items per page. Example: 15
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": "01HXYZ123456789ABCDEF",
     *       "code": "US",
     *       "name": "United States",
     *       "iso_code": "USA",
     *       "phone_code": "+1",
     *       "created_at": "2024-01-01T00:00:00.000000Z",
     *       "updated_at": "2024-01-01T00:00:00.000000Z",
     *       "provinces": []
     *     }
     *   ],
     *   "links": {},
     *   "meta": {}
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $countries = QueryBuilder::for(Country::class)
            ->allowedFilters([
                AllowedFilter::partial('code'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('iso_code'),
                AllowedFilter::partial('phone_code'),
            ])
            ->allowedSorts([
                'code',
                'name',
                'iso_code',
                'phone_code',
                'created_at',
                'updated_at',
            ])
            ->defaultSort('name')
            ->with(['provinces'])
            ->paginate($request->input('per_page', 15))
            ->appends($request->query());

        return response()->json($countries);
    }

    /**
     * Store a newly created country
     *
     * @bodyParam code string required Country code (max 10 characters). Example: US
     * @bodyParam name string required Country name (max 255 characters). Example: United States
     * @bodyParam iso_code string Country ISO code (max 3 characters). Example: USA
     * @bodyParam phone_code string Country phone code (max 10 characters). Example: +1
     *
     * @response 201 {
     *   "id": "01HXYZ123456789ABCDEF",
     *   "code": "US",
     *   "name": "United States",
     *   "iso_code": "USA",
     *   "phone_code": "+1",
     *   "created_at": "2024-01-01T00:00:00.000000Z",
     *   "updated_at": "2024-01-01T00:00:00.000000Z"
     * }
     * @response 422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "code": ["The country code field is required."],
     *     "name": ["The country name field is required."]
     *   }
     * }
     */
    public function store(CountryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // For public API, we'll use a default system user ID
        // In a real application, you might want to create a system user or handle this differently
        $systemUserId = User::where('email', 'system@geo.local')->first()?->id ?? '01HXYZ123456789ABCDEF';
        $validated['created_by'] = $systemUserId;
        $validated['updated_by'] = $systemUserId;

        $country = Country::create($validated);

        return response()->json($country, 201);
    }

    /**
     * Display the specified country
     *
     * @urlParam country string required The ID of the country. Example: 01HXYZ123456789ABCDEF
     *
     * @response 200 {
     *   "id": "01HXYZ123456789ABCDEF",
     *   "code": "US",
     *   "name": "United States",
     *   "iso_code": "USA",
     *   "phone_code": "+1",
     *   "created_at": "2024-01-01T00:00:00.000000Z",
     *   "updated_at": "2024-01-01T00:00:00.000000Z",
     *   "provinces": []
     * }
     * @response 404 {
     *   "message": "No query results for model [App\\Models\\Master\\Geo\\Country]."
     * }
     */
    public function show(Country $country): JsonResponse
    {
        $country->load(['provinces']);

        return response()->json($country);
    }

    /**
     * Update the specified country
     *
     * @urlParam country string required The ID of the country. Example: 01HXYZ123456789ABCDEF
     *
     * @bodyParam code string Country code (max 10 characters). Example: US
     * @bodyParam name string Country name (max 255 characters). Example: United States
     * @bodyParam iso_code string Country ISO code (max 3 characters). Example: USA
     * @bodyParam phone_code string Country phone code (max 10 characters). Example: +1
     *
     * @response 200 {
     *   "id": "01HXYZ123456789ABCDEF",
     *   "code": "US",
     *   "name": "United States",
     *   "iso_code": "USA",
     *   "phone_code": "+1",
     *   "created_at": "2024-01-01T00:00:00.000000Z",
     *   "updated_at": "2024-01-01T00:00:00.000000Z"
     * }
     * @response 404 {
     *   "message": "No query results for model [App\\Models\\Master\\Geo\\Country]."
     * }
     * @response 422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "code": ["The country code has already been taken."]
     *   }
     * }
     */
    public function update(CountryRequest $request, Country $country): JsonResponse
    {
        $country->update($request->validated());

        return response()->json($country);
    }

    /**
     * Remove the specified country
     *
     * @urlParam country string required The ID of the country. Example: 01HXYZ123456789ABCDEF
     *
     * @response 200 {
     *   "message": "Country deleted successfully"
     * }
     * @response 404 {
     *   "message": "No query results for model [App\\Models\\Master\\Geo\\Country]."
     * }
     * @response 422 {
     *   "message": "Cannot delete country. It has associated provinces."
     * }
     */
    public function destroy(Country $country): JsonResponse
    {
        if (! $country->canDeleted()) {
            return response()->json([
                'message' => 'Cannot delete country. It has associated provinces.',
            ], 422);
        }

        $country->delete();

        return response()->json(['message' => 'Country deleted successfully']);
    }

    /**
     * Get countries for dropdown/select
     *
     * @queryParam filter[name] string Filter countries by name (partial match). Example: United
     * @queryParam filter[code] string Filter countries by code (partial match). Example: US
     * @queryParam sort string Sort countries by field. Example: name
     * @queryParam limit integer Maximum number of results. Example: 100
     *
     * @response 200 [
     *   {
     *     "id": "01HXYZ123456789ABCDEF",
     *     "code": "US",
     *     "name": "United States",
     *     "iso_code": "USA",
     *     "phone_code": "+1"
     *   }
     * ]
     */
    public function list(Request $request): JsonResponse
    {
        $countries = QueryBuilder::for(Country::class)
            ->allowedFilters([
                AllowedFilter::partial('name'),
                AllowedFilter::partial('code'),
            ])
            ->allowedSorts(['name', 'code'])
            ->defaultSort('name')
            ->select(['id', 'code', 'name', 'iso_code', 'phone_code'])
            ->limit($request->input('limit', 100))
            ->get();

        return response()->json($countries);
    }
}
