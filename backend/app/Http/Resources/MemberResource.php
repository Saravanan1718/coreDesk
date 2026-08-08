<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes every Member API response consistently.
 *
 * Dates are always returned as ISO-8601 strings (Y-m-d) so the
 * Vue client never needs to deal with Carbon serialisation quirks.
 */
class MemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'ulid'                    => $this->ulid,
            'gym_id'                  => $this->gym_id,
            'full_name'               => $this->full_name,
            'date_of_birth'           => $this->date_of_birth?->toDateString(),
            'gender'                  => $this->gender,
            'phone'                   => $this->phone,
            'emergency_contact_name'  => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'photo_url'               => $this->photo_url,
            'registration_date'       => $this->registration_date?->toDateString(),
            'status'                  => $this->status,
            'created_at'              => $this->created_at?->toIso8601String(),
            'updated_at'              => $this->updated_at?->toIso8601String(),
        ];
    }
}
