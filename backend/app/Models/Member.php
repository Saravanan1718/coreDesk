<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Member extends Model
{
    use HasFactory;

    protected $fillable = [
        'ulid',
        'gym_id',
        'full_name',
        'date_of_birth',
        'gender',
        'phone',
        'emergency_contact_name',
        'emergency_contact_phone',
        'photo_url',
        'registration_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth'     => 'date',
            'registration_date' => 'date',
        ];
    }

    // ── Boot ────────────────────────────────────────────────────────────────

    protected static function booted(): void
    {
        // Auto-assign a ULID on creation so callers never need to set it manually
        static::creating(function (Member $member): void {
            if (empty($member->ulid)) {
                $member->ulid = (string) Str::ulid();
            }
            if (empty($member->registration_date)) {
                $member->registration_date = now()->toDateString();
            }
        });
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Scope to active members only.
     * Inactive members must be excluded from search results, counts,
     * and membership assignment (Requirements 3.7, 3.8).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to a specific gym — used everywhere until the full
     * multi-tenancy global scope is implemented.
     */
    public function scopeForGym(Builder $query, int $gymId): Builder
    {
        return $query->where('gym_id', $gymId);
    }

    /**
     * FULLTEXT search on full_name and phone.
     * Falls back to LIKE when the query is too short for FULLTEXT (< 3 chars).
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $safe = mb_strlen($term) >= 3;

        if ($safe) {
            return $query->whereRaw(
                'MATCH(full_name, phone) AGAINST(? IN BOOLEAN MODE)',
                ['+' . $term . '*']
            );
        }

        // Short queries: use LIKE so single-character searches still work
        return $query->where(function (Builder $q) use ($term): void {
            $q->where('full_name', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%");
        });
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function deactivate(): void
    {
        $this->update(['status' => 'inactive']);
    }
}
