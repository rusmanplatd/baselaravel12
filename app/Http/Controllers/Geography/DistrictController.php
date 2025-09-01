<?php

namespace App\Http\Controllers\Geography;

use App\Http\Controllers\Controller;
use App\Models\Master\Geo\City;
use App\Models\Master\Geo\District;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DistrictController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:geo_district:read')->only(['index', 'show']);
        $this->middleware('permission:geo_district:write')->only(['create', 'store', 'edit', 'update']);
        $this->middleware('permission:geo_district:delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        return Inertia::render('Geography/DistrictsApi');
    }

    public function show(District $district)
    {
        $district->load(['city.province.country', 'villages']);

        return Inertia::render('Geography/DistrictShow', [
            'districts' => $district,
        ]);
    }

    public function create()
    {
        $cities = City::with(['province.country'])
            ->select(['id', 'name', 'code', 'province_id'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Geography/DistrictCreate', [
            'cities' => $cities,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'city_id' => 'required|exists:ref_geo_city,id',
            'code' => 'required|string|max:10',
            'name' => 'required|string|max:255',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();

        District::create($validated);

        return redirect()
            ->route('geography.districts')
            ->with('success', 'District created successfully.');
    }

    public function edit(District $district)
    {
        $cities = City::with(['province.country'])
            ->select(['id', 'name', 'code', 'province_id'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Geography/DistrictEdit', [
            'districts' => $district,
            'cities' => $cities,
        ]);
    }

    public function update(Request $request, District $district)
    {
        $validated = $request->validate([
            'city_id' => 'required|exists:ref_geo_city,id',
            'code' => 'required|string|max:10',
            'name' => 'required|string|max:255',
        ]);

        $validated['updated_by'] = auth()->id();

        $district->update($validated);

        return redirect()
            ->route('geography.districts')
            ->with('success', 'District updated successfully.');
    }

    public function destroy(District $district)
    {
        if (! $district->canDeleted()) {
            return redirect()
                ->route('geography.districts')
                ->with('error', 'Cannot delete district. It has associated villages.');
        }

        $district->delete();

        return redirect()
            ->route('geography.districts')
            ->with('success', 'District deleted successfully.');
    }
}
