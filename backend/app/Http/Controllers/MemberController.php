<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Http\Resources\MemberResource;
use App\Models\Gym;
use App\Models\Member;
use App\Services\MemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberController extends Controller
{
    public function __construct(private readonly MemberService $memberService)
    {
    }

    /**
     * GET /api/gyms/{gymId}/members
     *
     * Returns a paginated list of active members, with optional search.
     * Inactive members are never included (Requirement 3.8).
     */
    public function index(Request $request, int $gymId): ResourceCollection
    {
        $gym = $this->resolveGym($gymId);

        $search  = $request->query('search');
        $perPage = (int) $request->query('per_page', 50);

        $members = $this->memberService->list($gym, $search, $perPage);

        return MemberResource::collection($members);
    }

    /**
     * POST /api/gyms/{gymId}/members
     *
     * Creates a new member. Handles the duplicate-phone warning flow:
     *   - First request without confirm_duplicate → 409 with warning payload
     *   - Re-request with confirm_duplicate=true  → 201 Created
     */
    public function store(StoreMemberRequest $request, int $gymId): JsonResponse
    {
        $gym    = $this->resolveGym($gymId);
        $result = $this->memberService->create(
            $gym,
            $request->validated(),
            $request->file('photo')
        );

        if ($result['duplicate']) {
            return response()->json([
                'error' => [
                    'code'    => 'DUPLICATE_PHONE',
                    'message' => 'A member with this phone number is already registered in this gym.',
                    'fields'  => [['field' => 'phone', 'issue' => 'already registered to another member']],
                ],
            ], 409);
        }

        return response()->json(new MemberResource($result['member']), 201);
    }

    /**
     * GET /api/gyms/{gymId}/members/{id}
     *
     * Returns a single member (active or inactive) within this gym.
     */
    public function show(int $gymId, int $id): JsonResponse
    {
        $gym    = $this->resolveGym($gymId);
        $member = $this->memberService->find($gym, $id);

        if (! $member) {
            return $this->memberNotFound($id);
        }

        return response()->json(new MemberResource($member));
    }

    /**
     * PATCH /api/gyms/{gymId}/members/{id}
     *
     * Partial update — only provided fields are changed.
     */
    public function update(UpdateMemberRequest $request, int $gymId, int $id): JsonResponse
    {
        $gym    = $this->resolveGym($gymId);
        $member = $this->memberService->find($gym, $id);

        if (! $member) {
            return $this->memberNotFound($id);
        }

        $member = $this->memberService->update(
            $member,
            $request->validated(),
            $request->file('photo')
        );

        return response()->json(new MemberResource($member));
    }

    /**
     * POST /api/gyms/{gymId}/members/{id}/deactivate
     *
     * Soft-deletes a member (sets status to inactive, Requirement 3.7).
     */
    public function deactivate(int $gymId, int $id): JsonResponse
    {
        $gym    = $this->resolveGym($gymId);
        $member = $this->memberService->find($gym, $id);

        if (! $member) {
            return $this->memberNotFound($id);
        }

        if (! $member->isActive()) {
            return response()->json([
                'error' => [
                    'code'    => 'ALREADY_INACTIVE',
                    'message' => 'This member is already inactive.',
                ],
            ], 422);
        }

        $member = $this->memberService->deactivate($member);

        return response()->json(new MemberResource($member));
    }

    /**
     * GET /api/gyms/{gymId}/members/export
     *
     * Streams a CSV of ALL members (active + inactive) in this gym (Requirement 3.11).
     * Must be registered BEFORE the {id} route to avoid route collision.
     */
    public function export(int $gymId): StreamedResponse
    {
        $gym      = $this->resolveGym($gymId);
        $csvData  = $this->memberService->exportCsv($gym);
        $filename = 'members_gym_' . $gymId . '_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(
            function () use ($csvData): void {
                echo $csvData;
            },
            $filename,
            [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Resolve the gym or abort with 404.
     * In owner mode (no auth) we look up the gym by ID directly.
     * When auth is added, this will validate gym_id against the authenticated user.
     */
    private function resolveGym(int $gymId): Gym
    {
        return Gym::findOrFail($gymId);
    }

    private function memberNotFound(int $id): JsonResponse
    {
        return response()->json([
            'error' => [
                'code'    => 'MEMBER_NOT_FOUND',
                'message' => "Member with id '{$id}' does not exist in this gym.",
            ],
        ], 404);
    }
}
