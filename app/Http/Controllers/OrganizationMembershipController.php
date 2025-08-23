<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrganizationMembership\StoreOrganizationMembershipRequest;
use App\Http\Requests\OrganizationMembership\UpdateOrganizationMembershipRequest;
use App\Http\Requests\OrganizationMembership\TerminateOrganizationMembershipRequest;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationPosition;
use App\Models\OrganizationUnit;
use App\Models\User;
use App\Services\ActivityLogService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrganizationMembershipController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:membership.view')->only(['index', 'show', 'boardMembers', 'executives']);
        $this->middleware('permission:membership.create')->only(['create', 'store']);
        $this->middleware('permission:membership.edit')->only(['edit', 'update']);
        $this->middleware('permission:membership.delete')->only(['destroy']);
        $this->middleware('permission:membership.activate')->only(['activate']);
        $this->middleware('permission:membership.deactivate')->only(['deactivate']);
        $this->middleware('permission:membership.terminate')->only(['terminate']);
    }

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

    public function store(StoreOrganizationMembershipRequest $request)
    {
        $validated = $request->validated();

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

        $membership = OrganizationMembership::create($validated);
        $membership->load(['user', 'organization', 'organizationPosition']);

        // Log membership creation
        ActivityLogService::logOrganization('membership_created', 'Organization membership created for '.$membership->user->name, [
            'user_name' => $membership->user->name,
            'user_email' => $membership->user->email,
            'organization_name' => $membership->organization->name,
            'membership_type' => $membership->membership_type,
            'position_title' => $membership->organizationPosition?->title,
        ], $membership);

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

    public function update(UpdateOrganizationMembershipRequest $request, OrganizationMembership $organizationMembership)
    {
        $validated = $request->validated();

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

        $oldData = $organizationMembership->toArray();
        $organizationMembership->update($validated);
        $organizationMembership->load(['user', 'organization', 'organizationPosition']);

        // Log membership update
        ActivityLogService::logOrganization('membership_updated', 'Organization membership updated for '.$organizationMembership->user->name, [
            'user_name' => $organizationMembership->user->name,
            'organization_name' => $organizationMembership->organization->name,
            'changes' => array_diff_assoc($validated, $oldData),
        ], $organizationMembership);

        return redirect()->route('organization-memberships.index')
            ->with('success', 'Organization membership updated successfully.');
    }

    public function destroy(OrganizationMembership $organizationMembership)
    {
        // Log membership deletion before deleting
        $organizationMembership->load(['user', 'organization']);
        ActivityLogService::logOrganization('membership_deleted', 'Organization membership deleted for '.$organizationMembership->user->name, [
            'user_name' => $organizationMembership->user->name,
            'user_email' => $organizationMembership->user->email,
            'organization_name' => $organizationMembership->organization->name,
            'membership_type' => $organizationMembership->membership_type,
        ], $organizationMembership);

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
        $organizationMembership->load(['user', 'organization']);

        // Log membership activation
        ActivityLogService::logOrganization('membership_activated', 'Organization membership activated for '.$organizationMembership->user->name, [
            'user_name' => $organizationMembership->user->name,
            'organization_name' => $organizationMembership->organization->name,
        ], $organizationMembership);

        return redirect()->back()
            ->with('success', 'Membership activated successfully.');
    }

    public function deactivate(OrganizationMembership $organizationMembership)
    {
        $organizationMembership->load(['user', 'organization']);

        // Log membership deactivation
        ActivityLogService::logOrganization('membership_deactivated', 'Organization membership deactivated for '.$organizationMembership->user->name, [
            'user_name' => $organizationMembership->user->name,
            'organization_name' => $organizationMembership->organization->name,
        ], $organizationMembership);

        $organizationMembership->deactivate();

        return redirect()->back()
            ->with('success', 'Membership deactivated successfully.');
    }

    public function terminate(TerminateOrganizationMembershipRequest $request, OrganizationMembership $organizationMembership)
    {
        $validated = $request->validated();

        $endDate = $validated['end_date'] ? Carbon::parse($validated['end_date']) : null;
        $organizationMembership->load(['user', 'organization']);

        // Log membership termination
        ActivityLogService::logOrganization('membership_terminated', 'Organization membership terminated for '.$organizationMembership->user->name, [
            'user_name' => $organizationMembership->user->name,
            'organization_name' => $organizationMembership->organization->name,
            'end_date' => $endDate?->toDateString(),
        ], $organizationMembership);

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
