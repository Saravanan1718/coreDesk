<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gym extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'status',
        'subscription_tier',
        'suspended_at',
    ];

    protected function casts(): array
    {
        return [
            'suspended_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }
}
