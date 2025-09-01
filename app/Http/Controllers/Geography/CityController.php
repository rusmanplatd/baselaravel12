<?php

namespace App\Http\Controllers\Geography;

use App\Http\Controllers\Controller;
use App\Models\Master\Geo\City;
use App\Models\Master\Geo\Province;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CityController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:geo_city:read')->only(['index', 'show']);
        $this->middleware('permission:geo_city:write')->only(['create', 'store', 'edit', 'update']);
        $this->middleware('permission:geo_city:delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $cities = QueryBuilder::for(City::class)
            ->allowedFilters([
                AllowedFilter::partial('code'),
                AllowedFilter::partial('name'),
                AllowedFilter::exact('province_id'),
            ])
            ->allowedSorts([
                'code',
                'name',
                'created_at',
                'updated_at',
            ])
            ->defaultSort('name')
            ->with(['province.country'])
            ->withCount(['district'])
            ->paginate($request->input('per_page', 15))
            ->appends($request->query());

        $provinces = Province::with(['country'])
            ->select(['id', 'name', 'code', 'country_id'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Geography/Cities', [
            'cities' => $cities,
            'provinces' => $provinces,
            'filters' => $request->only([
                'filter.code',
                'filter.name',
                'filter.province_id',
                'sort'
            ]),
        ]);
    }

    public function show(City $city)
    {
        $city->load(['province.country', 'district']);

        return Inertia::render('Geography/CityShow', [
            'city' => $city,
        ]);
    }

    public function create()
    {
        $provinces = Province::with(['country'])
            ->select(['id', 'name', 'code', 'country_id'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Geography/CityCreate', [
            'provinces' => $provinces,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'province_id' => 'required|exists:ref_geo_province,id',
            'code' => 'required|string|max:10',
            'name' => 'required|string|max:255',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();

        City::create($validated);

        return redirect()
            ->route('geography.cities')
            ->with('success', 'City created successfully.');
    }

    public function edit(City $city)
    {
        $provinces = Province::with(['country'])
            ->select(['id', 'name', 'code', 'country_id'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Geography/CityEdit', [
            'city' => $city,
            'provinces' => $provinces,
        ]);
    }

    public function update(Request $request, City $city)
    {
        $validated = $request->validate([
            'province_id' => 'required|exists:ref_geo_province,id',
            'code' => 'required|string|max:10',
            'name' => 'required|string|max:255',
        ]);

        $validated['updated_by'] = auth()->id();

        $city->update($validated);

        return redirect()
            ->route('geography.cities')
            ->with('success', 'City updated successfully.');
    }

    public function destroy(City $city)
    {
        if (!$city->canDeleted()) {
            return redirect()
                ->route('geography.cities')
                ->with('error', 'Cannot delete city. It has associated districts.');
        }

        $city->delete();

        return redirect()
            ->route('geography.cities')
            ->with('success', 'City deleted successfully.');
    }
}