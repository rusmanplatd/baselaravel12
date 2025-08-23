<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreOrganizationMembershipRequest;
use App\Http\Requests\Api\UpdateOrganizationMembershipRequest;
use App\Http\Requests\Api\TerminateOrganizationMembershipRequest;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationPosition;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationMembershipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = OrganizationMembership::with([
            'user',
            'organization',
            'organizationUnit',
            'organizationPosition',
        ]);

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        if ($request->has('organization_unit_id')) {
            $query->where('organization_unit_id', $request->organization_unit_id);
        }

        if ($request->has('organization_position_id')) {
            $query->where('organization_position_id', $request->organization_position_id);
        }

        if ($request->has('membership_type')) {
            $query->where('membership_type', $request->membership_type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('active_only') && $request->active_only) {
            $query->active();
        }

        if ($request->has('board_only') && $request->board_only) {
            $query->board();
        }

        if ($request->has('executive_only') && $request->executive_only) {
            $query->executive();
        }

        if ($request->has('management_only') && $request->management_only) {
            $query->management();
        }

        $memberships = $query->orderBy('start_date', 'desc')->get();

        return response()->json($memberships);
    }

    public function store(StoreOrganizationMembershipRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($request->organization_position_id) {
            $position = OrganizationPosition::find($request->organization_position_id);
            if (! $position->hasAvailableSlots()) {
                return response()->json([
                    'message' => 'No available slots for this position',
                ], 400);
            }

            $existingMembership = OrganizationMembership::where('user_id', $request->user_id)
                ->where('organization_id', $request->organization_id)
                ->where('organization_position_id', $request->organization_position_id)
                ->active()
                ->first();

            if ($existingMembership) {
                return response()->json([
                    'message' => 'User already has an active membership in this position',
                ], 400);
            }
        }

        $membership = OrganizationMembership::create($validated);
        $membership->load(['user', 'organization', 'organizationUnit', 'organizationPosition']);

        return response()->json($membership, 201);
    }

    public function show(OrganizationMembership $organizationMembership): JsonResponse
    {
        $organizationMembership->load([
            'user',
            'organization',
            'organizationUnit',
            'organizationPosition',
        ]);

        return response()->json($organizationMembership);
    }

    public function update(UpdateOrganizationMembershipRequest $request, OrganizationMembership $organizationMembership): JsonResponse
    {
        $validated = $request->validated();

        if ($request->organization_position_id && $request->organization_position_id != $organizationMembership->organization_position_id) {
            $position = OrganizationPosition::find($request->organization_position_id);
            if (! $position->hasAvailableSlots()) {
                return response()->json([
                    'message' => 'No available slots for this position',
                ], 400);
            }

            $existingMembership = OrganizationMembership::where('user_id', $organizationMembership->user_id)
                ->where('organization_id', $organizationMembership->organization_id)
                ->where('organization_position_id', $request->organization_position_id)
                ->where('id', '!=', $organizationMembership->id)
                ->active()
                ->first();

            if ($existingMembership) {
                return response()->json([
                    'message' => 'User already has an active membership in this position',
                ], 400);
            }
        }

        $organizationMembership->update($validated);
        $organizationMembership->load(['user', 'organization', 'organizationUnit', 'organizationPosition']);

        return response()->json($organizationMembership);
    }

    public function destroy(OrganizationMembership $organizationMembership): JsonResponse
    {
        $organizationMembership->delete();

        return response()->json(['message' => 'Organization membership deleted successfully']);
    }

    public function activate(OrganizationMembership $organizationMembership): JsonResponse
    {
        if ($organizationMembership->organization_position_id) {
            $position = $organizationMembership->organizationPosition;
            if (! $position->hasAvailableSlots() && $organizationMembership->status !== 'active') {
                return response()->json([
                    'message' => 'No available slots for this position',
                ], 400);
            }
        }

        $organizationMembership->activate();
        $organizationMembership->load(['user', 'organization', 'organizationUnit', 'organizationPosition']);

        return response()->json($organizationMembership);
    }

    public function deactivate(OrganizationMembership $organizationMembership): JsonResponse
    {
        $organizationMembership->deactivate();
        $organizationMembership->load(['user', 'organization', 'organizationUnit', 'organizationPosition']);

        return response()->json($organizationMembership);
    }

    public function terminate(TerminateOrganizationMembershipRequest $request, OrganizationMembership $organizationMembership): JsonResponse
    {
        $validated = $request->validated();

        $endDate = $validated['end_date'] ? Carbon::parse($validated['end_date']) : null;
        $organizationMembership->terminate($endDate);
        $organizationMembership->load(['user', 'organization', 'organizationUnit', 'organizationPosition']);

        return response()->json($organizationMembership);
    }

    public function getUserMemberships(Request $request, User $user): JsonResponse
    {
        $query = $user->organizationMemberships()
            ->with(['organization', 'organizationUnit', 'organizationPosition']);

        if ($request->has('active_only') && $request->active_only) {
            $query->active();
        }

        if ($request->has('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        $memberships = $query->orderBy('start_date', 'desc')->get();

        return response()->json($memberships);
    }

    public function getOrganizationMemberships(Request $request, Organization $organization): JsonResponse
    {
        $query = $organization->memberships()
            ->with(['user', 'organizationUnit', 'organizationPosition']);

        if ($request->has('active_only') && $request->active_only) {
            $query->active();
        }

        if ($request->has('board_only') && $request->board_only) {
            $query->board();
        }

        if ($request->has('executive_only') && $request->executive_only) {
            $query->executive();
        }

        if ($request->has('management_only') && $request->management_only) {
            $query->management();
        }

        $memberships = $query->orderBy('start_date', 'desc')->get();

        return response()->json($memberships);
    }

    public function getBoardMembers(Request $request): JsonResponse
    {
        $query = OrganizationMembership::board()
            ->with(['user', 'organization', 'organizationUnit', 'organizationPosition']);

        if ($request->has('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        if ($request->has('active_only') && $request->active_only) {
            $query->active();
        }

        $boardMembers = $query->orderBy('start_date', 'desc')->get();

        return response()->json($boardMembers);
    }

    public function getExecutives(Request $request): JsonResponse
    {
        $query = OrganizationMembership::executive()
            ->with(['user', 'organization', 'organizationUnit', 'organizationPosition']);

        if ($request->has('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        if ($request->has('active_only') && $request->active_only) {
            $query->active();
        }

        $executives = $query->orderBy('start_date', 'desc')->get();

        return response()->json($executives);
    }
}
