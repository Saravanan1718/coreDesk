<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();

            // Opaque external identifier — used in QR payloads and API URLs
            $table->string('ulid', 26)->unique();

            // Tenant isolation — every row belongs to exactly one gym
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();

            // Core member data (Requirement 3.3)
            $table->string('full_name', 100);
            $table->date('date_of_birth');
            $table->string('gender', 20);                          // male | female | other
            $table->string('phone', 15);
            $table->string('emergency_contact_name', 100);
            $table->string('emergency_contact_phone', 15);
            $table->string('photo_url')->nullable();
            $table->date('registration_date');

            // Soft-delete via status flag (Requirement 3.7 — mark inactive, never hard-delete)
            $table->string('status', 20)->default('active');       // active | inactive

            $table->timestamps();

            // ── Indexes ──────────────────────────────────────────────────────
            // Tenant isolation: all member queries are scoped to gym_id first
            $table->index('gym_id');

            // Phone uniqueness check within a gym (Requirement 3.9)
            $table->index(['gym_id', 'phone']);

            // Status filtering (active member lists, counts)
            $table->index(['gym_id', 'status']);
        });

        // FULLTEXT index for name + phone search (Requirement 3.6)
        // Must be added after table creation — Blueprint::fullText() adds it inline
        // but InnoDB FULLTEXT requires the table to exist first on older MySQL versions.
        DB::statement('ALTER TABLE members ADD FULLTEXT INDEX idx_members_search (full_name, phone)');
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
