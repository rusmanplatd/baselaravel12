<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationPosition;
use App\Models\OrganizationUnit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrganizationMembershipController extends Controller
{
    public function index(Request $request)
    {
        $query = OrganizationMembership::query()
            ->with(['user', 'organization', 'organizationUnit', 'organizationPosition']);

        if ($request->filled('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        if ($request->filled('membership_type')) {
            $query->where('membership_type', $request->membership_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $memberships = $query->orderBy('start_date', 'desc')->paginate(10);

        $organizations = Organization::orderBy('name')->get(['id', 'name']);
        $users = User::orderBy('name')->get(['id', 'name', 'email']);
        $organizationUnits = OrganizationUnit::with('organization')->orderBy('name')->get(['id', 'name', 'organization_id']);
        $organizationPositions = OrganizationPosition::with('organizationUnit.organization')->orderBy('title')->get(['id', 'title', 'organization_unit_id']);

        return Inertia::render('OrganizationMemberships/Index', [
            'memberships' => $memberships,
            'organizations' => $organizations,
            'users' => $users,
            'organizationUnits' => $organizationUnits,
            'organizationPositions' => $organizationPositions,
            'filters' => $request->only(['organization_id', 'membership_type', 'status']),
        ]);
    }

    public function create()
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email']);
        $organizations = Organization::orderBy('name')->get(['id', 'name']);
        $organizationUnits = OrganizationUnit::with('organization')->orderBy('name')->get(['id', 'name', 'organization_id']);
        $organizationPositions = OrganizationPosition::with('organizationUnit.organization')->orderBy('title')->get(['id', 'title', 'organization_unit_id']);

        return Inertia::render('OrganizationMemberships/Create', [
            'users' => $users,
            'organizations' => $organizations,
            'organizationUnits' => $organizationUnits,
            'organizationPositions' => $organizationPositions,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'organization_id' => 'required|exists:organizations,id',
            'organization_unit_id' => 'nullable|exists:organization_units,id',
            'organization_position_id' => 'nullable|exists:organization_positions,id',
            'membership_type' => 'required|in:employee,board_member,consultant,contractor,intern',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'status' => 'required|in:active,inactive,terminated',
            'additional_roles' => 'nullable|array',
        ]);

        if ($request->organization_position_id) {
            $position = OrganizationPosition::find($request->organization_position_id);
            if (! $position->hasAvailableSlots()) {
                return redirect()->back()
                    ->withErrors(['organization_position_id' => 'No available slots for this position'])
                    ->withInput();
            }

            $existingMembership = OrganizationMembership::where('user_id', $request->user_id)
                ->where('organization_id', $request->organization_id)
                ->where('organization_position_id', $request->organization_position_id)
                ->active()
                ->first();

            if ($existingMembership) {
                return redirect()->back()
                    ->withErrors(['organization_position_id' => 'User already has an active membership in this position'])
                    ->withInput();
            }
        }

        OrganizationMembership::create($validated);

        return redirect()->route('organization-memberships.index')
            ->with('success', 'Organization membership created successfully.');
    }

    public function show(OrganizationMembership $organizationMembership)
    {
        $organizationMembership->load([
            'user',
            'organization',
            'organizationUnit',
            'organizationPosition',
        ]);

        return Inertia::render('OrganizationMemberships/Show', [
            'membership' => $organizationMembership,
        ]);
    }

    public function edit(OrganizationMembership $organizationMembership)
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email']);
        $organizations = Organization::orderBy('name')->get(['id', 'name']);
        $organizationUnits = OrganizationUnit::with('organization')->orderBy('name')->get(['id', 'name', 'organization_id']);
        $organizationPositions = OrganizationPosition::with('organizationUnit.organization')->orderBy('title')->get(['id', 'title', 'organization_unit_id']);

        return Inertia::render('OrganizationMemberships/Edit', [
            'membership' => $organizationMembership,
            'users' => $users,
            'organizations' => $organizations,
            'organizationUnits' => $organizationUnits,
            'organizationPositions' => $organizationPositions,
        ]);
    }

    public function update(Request $request, OrganizationMembership $organizationMembership)
    {
        $validated = $request->validate([
            'organization_unit_id' => 'nullable|exists:organization_units,id',
            'organization_position_id' => 'nullable|exists:organization_positions,id',
            'membership_type' => 'required|in:employee,board_member,consultant,contractor,intern',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'status' => 'required|in:active,inactive,terminated',
            'additional_roles' => 'nullable|array',
        ]);

        if ($request->organization_position_id && $request->organization_position_id != $organizationMembership->organization_position_id) {
            $position = OrganizationPosition::find($request->organization_position_id);
            if (! $position->hasAvailableSlots()) {
                return redirect()->back()
                    ->withErrors(['organization_position_id' => 'No available slots for this position'])
                    ->withInput();
            }

            $existingMembership = OrganizationMembership::where('user_id', $organizationMembership->user_id)
                ->where('organization_id', $organizationMembership->organization_id)
                ->where('organization_position_id', $request->organization_position_id)
                ->where('id', '!=', $organizationMembership->id)
                ->active()
                ->first();

            if ($existingMembership) {
                return redirect()->back()
                    ->withErrors(['organization_position_id' => 'User already has an active membership in this position'])
                    ->withInput();
            }
        }

        $organizationMembership->update($validated);

        return redirect()->route('organization-memberships.index')
            ->with('success', 'Organization membership updated successfully.');
    }

    public function destroy(OrganizationMembership $organizationMembership)
    {
        $organizationMembership->delete();

        return redirect()->route('organization-memberships.index')
            ->with('success', 'Organization membership deleted successfully.');
    }

    public function activate(OrganizationMembership $organizationMembership)
    {
        if ($organizationMembership->organization_position_id) {
            $position = $organizationMembership->organizationPosition;
            if (! $position->hasAvailableSlots() && $organizationMembership->status !== 'active') {
                return redirect()->back()
                    ->withErrors(['membership' => 'No available slots for this position']);
            }
        }

        $organizationMembership->activate();

        return redirect()->back()
            ->with('success', 'Membership activated successfully.');
    }

    public function deactivate(OrganizationMembership $organizationMembership)
    {
        $organizationMembership->deactivate();

        return redirect()->back()
            ->with('success', 'Membership deactivated successfully.');
    }

    public function terminate(Request $request, OrganizationMembership $organizationMembership)
    {
        $validated = $request->validate([
            'end_date' => 'nullable|date',
        ]);

        $endDate = $validated['end_date'] ? Carbon::parse($validated['end_date']) : null;
        $organizationMembership->terminate($endDate);

        return redirect()->back()
            ->with('success', 'Membership terminated successfully.');
    }

    public function boardMembers()
    {
        $boardMembers = OrganizationMembership::board()
            ->with(['user', 'organization', 'organizationUnit', 'organizationPosition'])
            ->active()
            ->orderBy('start_date', 'desc')
            ->get();

        return Inertia::render('OrganizationMemberships/BoardMembers', [
            'boardMembers' => $boardMembers,
        ]);
    }

    public function executives()
    {
        $executives = OrganizationMembership::executive()
            ->with(['user', 'organization', 'organizationUnit', 'organizationPosition'])
            ->active()
            ->orderBy('start_date', 'desc')
            ->get();

        return Inertia::render('OrganizationMemberships/Executives', [
            'executives' => $executives,
        ]);
    }
}
