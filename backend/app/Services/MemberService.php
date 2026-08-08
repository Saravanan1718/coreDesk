<?php

namespace App\Services;

use App\Models\Gym;
use App\Models\Member;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use League\Csv\Writer;
use SplTempFileObject;

class MemberService
{
    /**
     * Create a new member for the given gym.
     *
     * Returns an array with two keys:
     *   - 'member'    => Member|null   — the created model, or null on duplicate stop
     *   - 'duplicate' => bool          — true when an unconfirmed duplicate phone was found
     *
     * Duplicate-phone flow (Requirement 3.9):
     *   1. First call with a duplicate phone → returns ['duplicate' => true, 'member' => null]
     *   2. Caller shows confirmation dialog to the user.
     *   3. Second call with confirm_duplicate=true → creates the member regardless.
     */
    public function create(Gym $gym, array $data, ?UploadedFile $photo = null): array
    {
        // Duplicate phone check (same gym only)
        if (! ($data['confirm_duplicate'] ?? false)) {
            $exists = Member::forGym($gym->id)
                ->where('phone', $data['phone'])
                ->exists();

            if ($exists) {
                return ['duplicate' => true, 'member' => null];
            }
        }

        $photoUrl = null;
        if ($photo) {
            $photoUrl = $this->storePhoto($photo, $gym->id);
        }

        $member = Member::create([
            'gym_id'                  => $gym->id,
            'full_name'               => $data['full_name'],
            'date_of_birth'           => $data['date_of_birth'],
            'gender'                  => $data['gender'],
            'phone'                   => $data['phone'],
            'emergency_contact_name'  => $data['emergency_contact_name'],
            'emergency_contact_phone' => $data['emergency_contact_phone'],
            'photo_url'               => $photoUrl,
            'registration_date'       => $data['registration_date'] ?? now()->toDateString(),
            'status'                  => 'active',
        ]);

        return ['duplicate' => false, 'member' => $member];
    }

    /**
     * Update allowed fields on an existing member.
     * Only supplied fields are changed (PATCH semantics).
     */
    public function update(Member $member, array $data, ?UploadedFile $photo = null): Member
    {
        $fillable = [
            'full_name',
            'date_of_birth',
            'gender',
            'phone',
            'emergency_contact_name',
            'emergency_contact_phone',
            'registration_date',
        ];

        $updates = array_intersect_key($data, array_flip($fillable));

        if ($photo) {
            // Delete old photo from storage if present
            if ($member->photo_url) {
                $this->deletePhoto($member->photo_url);
            }
            $updates['photo_url'] = $this->storePhoto($photo, $member->gym_id);
        }

        $member->update($updates);

        return $member->fresh();
    }

    /**
     * Soft-delete a member by setting status to inactive (Requirement 3.7).
     * Data is never permanently removed.
     */
    public function deactivate(Member $member): Member
    {
        $member->deactivate();
        return $member->fresh();
    }

    /**
     * Paginated, searchable list of ACTIVE members for a gym.
     * Inactive members are never returned here (Requirement 3.8).
     */
    public function list(Gym $gym, ?string $search, int $perPage = 50): LengthAwarePaginator
    {
        $query = Member::forGym($gym->id)->active();

        if ($search !== null && $search !== '') {
            $query->search($search);
        }

        return $query
            ->orderBy('full_name')
            ->paginate(min($perPage, 200)); // cap at 200 per Req 12.4
    }

    /**
     * Retrieve a single member scoped to the gym (active or inactive).
     * Returns null if not found in this gym.
     */
    public function find(Gym $gym, int $id): ?Member
    {
        return Member::forGym($gym->id)->find($id);
    }

    /**
     * Stream a CSV export of ALL members (active + inactive) for a gym.
     * Returns the raw CSV string (Requirement 3.11).
     *
     * Uses league/csv for RFC-4180-compliant output.
     */
    public function exportCsv(Gym $gym): string
    {
        $members = Member::forGym($gym->id)
            ->orderBy('id')
            ->get();

        $csv = Writer::createFromFileObject(new SplTempFileObject());

        // Header row matches every stored field
        $csv->insertOne([
            'id',
            'ulid',
            'full_name',
            'date_of_birth',
            'gender',
            'phone',
            'emergency_contact_name',
            'emergency_contact_phone',
            'photo_url',
            'registration_date',
            'status',
            'created_at',
            'updated_at',
        ]);

        foreach ($members as $member) {
            $csv->insertOne([
                $member->id,
                $member->ulid,
                $member->full_name,
                $member->date_of_birth?->toDateString(),
                $member->gender,
                $member->phone,
                $member->emergency_contact_name,
                $member->emergency_contact_phone,
                $member->photo_url ?? '',
                $member->registration_date?->toDateString(),
                $member->status,
                $member->created_at?->toIso8601String(),
                $member->updated_at?->toIso8601String(),
            ]);
        }

        return $csv->toString();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function storePhoto(UploadedFile $photo, int $gymId): string
    {
        // Store under members/{gym_id}/{filename} — works with local or S3 driver
        $path = $photo->store("members/{$gymId}", 'public');
        return Storage::disk('public')->url($path);
    }

    private function deletePhoto(string $url): void
    {
        // Extract the storage-relative path from the public URL
        $relativePath = str_replace(Storage::disk('public')->url(''), '', $url);
        Storage::disk('public')->delete($relativePath);
    }
}
