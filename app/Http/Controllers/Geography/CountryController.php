<?php

namespace App\Http\Controllers\Geography;

use App\Http\Controllers\Controller;
use App\Models\Master\Geo\Country;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CountryController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:geo_country:read')->only(['index', 'show']);
        $this->middleware('permission:geo_country:write')->only(['create', 'store', 'edit', 'update']);
        $this->middleware('permission:geo_country:delete')->only(['destroy']);
    }

    public function index(Request $request)
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
            ->withCount(['provinces'])
            ->paginate($request->input('per_page', 15))
            ->appends($request->query());

        return Inertia::render('Geography/Countries', [
            'countries' => $countries,
            'filters' => $request->only([
                'filter.code',
                'filter.name', 
                'filter.iso_code',
                'filter.phone_code',
                'sort'
            ]),
        ]);
    }

    public function show(Country $country)
    {
        $country->load(['provinces']);

        return Inertia::render('Geography/CountryShow', [
            'country' => $country,
        ]);
    }

    public function create()
    {
        return Inertia::render('Geography/CountryCreate');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:10|unique:ref_geo_country,code',
            'name' => 'required|string|max:255',
            'iso_code' => 'nullable|string|max:3',
            'phone_code' => 'nullable|string|max:10',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();

        $country = Country::create($validated);
        
        activity()
            ->performedOn($country)
            ->causedBy(auth()->user())
            ->log('Created country ' . $country->name);

        return redirect()
            ->route('geography.countries')
            ->with('success', 'Country created successfully.');
    }

    public function edit(Country $country)
    {
        return Inertia::render('Geography/CountryEdit', [
            'country' => $country,
        ]);
    }

    public function update(Request $request, Country $country)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:10|unique:ref_geo_country,code,'.$country->id,
            'name' => 'required|string|max:255',
            'iso_code' => 'nullable|string|max:3',
            'phone_code' => 'nullable|string|max:10',
        ]);

        $validated['updated_by'] = auth()->id();

        $country->update($validated);
        
        activity()
            ->performedOn($country)
            ->causedBy(auth()->user())
            ->log('Updated country ' . $country->name);

        return redirect()
            ->route('geography.countries')
            ->with('success', 'Country updated successfully.');
    }

    public function destroy(Country $country)
    {
        if (!$country->canDeleted()) {
            return redirect()
                ->route('geography.countries')
                ->with('error', 'Cannot delete country. It has associated provinces.');
        }

        activity()
            ->performedOn($country)
            ->causedBy(auth()->user())
            ->log('Deleted country ' . $country->name);

        $country->delete();

        return redirect()
            ->route('geography.countries')
            ->with('success', 'Country deleted successfully.');
    }
}