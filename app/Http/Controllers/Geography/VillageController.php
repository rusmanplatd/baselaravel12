<?php

namespace App\Http\Controllers\Geography;

use App\Http\Controllers\Controller;
use App\Models\Master\Geo\District;
use App\Models\Master\Geo\Village;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VillageController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:geo_village:read')->only(['index', 'show']);
        $this->middleware('permission:geo_village:write')->only(['create', 'store', 'edit', 'update']);
        $this->middleware('permission:geo_village:delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        return Inertia::render('Geography/VillagesApi');
    }

    public function show(Village $village)
    {
        $village->load(['district.city.province.country']);

        return Inertia::render('Geography/VillageShow', [
            'village' => $village,
        ]);
    }

    public function create()
    {
        $districts = District::with(['city.province.country'])
            ->select(['id', 'name', 'code', 'city_id'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Geography/VillageCreate', [
            'districts' => $districts,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'district_id' => 'required|exists:ref_geo_district,id',
            'code' => 'required|string|max:10',
            'name' => 'required|string|max:255',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();

        Village::create($validated);

        return redirect()
            ->route('geography.villages')
            ->with('success', 'Village created successfully.');
    }

    public function edit(Village $village)
    {
        $districts = District::with(['city.province.country'])
            ->select(['id', 'name', 'code', 'city_id'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Geography/VillageEdit', [
            'village' => $village,
            'districts' => $districts,
        ]);
    }

    public function update(Request $request, Village $village)
    {
        $validated = $request->validate([
            'district_id' => 'required|exists:ref_geo_district,id',
            'code' => 'required|string|max:10',
            'name' => 'required|string|max:255',
        ]);

        $validated['updated_by'] = auth()->id();

        $village->update($validated);

        return redirect()
            ->route('geography.villages')
            ->with('success', 'Village updated successfully.');
    }

    public function destroy(Village $village)
    {
        if (! $village->canDeleted()) {
            return redirect()
                ->route('geography.villages')
                ->with('error', 'Cannot delete village. There are dependencies.');
        }

        $village->delete();

        return redirect()
            ->route('geography.villages')
            ->with('success', 'Village deleted successfully.');
    }
}
