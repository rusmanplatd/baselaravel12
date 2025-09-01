<?php

namespace App\Http\Controllers\Geography;

use App\Http\Controllers\Controller;
use App\Models\Master\Geo\Country;
use App\Models\Master\Geo\Province;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProvinceController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:geo_province:read')->only(['index', 'show']);
        $this->middleware('permission:geo_province:write')->only(['create', 'store', 'edit', 'update']);
        $this->middleware('permission:geo_province:delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $provinces = QueryBuilder::for(Province::class)
            ->allowedFilters([
                AllowedFilter::partial('code'),
                AllowedFilter::partial('name'),
                AllowedFilter::exact('country_id'),
            ])
            ->allowedSorts([
                'code',
                'name',
                'created_at',
                'updated_at',
            ])
            ->defaultSort('name')
            ->with(['country'])
            ->withCount(['cities'])
            ->paginate($request->input('per_page', 15))
            ->appends($request->query());

        $countries = Country::select(['id', 'name', 'code'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Geography/Provinces', [
            'provinces' => $provinces,
            'countries' => $countries,
            'filters' => $request->only([
                'filter.code',
                'filter.name',
                'filter.country_id',
                'sort'
            ]),
        ]);
    }

    public function show(Province $province)
    {
        $province->load(['country', 'cities']);

        return Inertia::render('Geography/ProvinceShow', [
            'province' => $province,
        ]);
    }

    public function create()
    {
        $countries = Country::select(['id', 'name', 'code'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Geography/ProvinceCreate', [
            'countries' => $countries,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:ref_geo_country,id',
            'code' => 'required|string|max:10',
            'name' => 'required|string|max:255',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();

        Province::create($validated);

        return redirect()
            ->route('geography.provinces')
            ->with('success', 'Province created successfully.');
    }

    public function edit(Province $province)
    {
        $countries = Country::select(['id', 'name', 'code'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Geography/ProvinceEdit', [
            'province' => $province,
            'countries' => $countries,
        ]);
    }

    public function update(Request $request, Province $province)
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:ref_geo_country,id',
            'code' => 'required|string|max:10',
            'name' => 'required|string|max:255',
        ]);

        $validated['updated_by'] = auth()->id();

        $province->update($validated);

        return redirect()
            ->route('geography.provinces')
            ->with('success', 'Province updated successfully.');
    }

    public function destroy(Province $province)
    {
        if (!$province->canDeleted()) {
            return redirect()
                ->route('geography.provinces')
                ->with('error', 'Cannot delete province. It has associated cities.');
        }

        $province->delete();

        return redirect()
            ->route('geography.provinces')
            ->with('success', 'Province deleted successfully.');
    }
}