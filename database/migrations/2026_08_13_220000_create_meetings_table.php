<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per encounter, shared by both riders.
        //
        // The earlier design kept a row per side and only counted an encounter once
        // both phones reported it. That collapsed against BLE reality: iOS advertising
        // in the background is invisible to Android, so in every iOS-Android pair only
        // one direction ever detects anything and no such meeting would ever confirm.
        //
        // A single row propagated to both sides costs the guarantee that a token cannot
        // be turned into an identity without the other side agreeing. Accepted
        // knowingly: reading a token now requires connecting over GATT, so it takes
        // physical proximity, and a fabricated meeting lands in the victim's own
        // history where it can be seen rather than collected silently.
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();

            // Normalised so the pair is unordered: the lower id always goes first.
            // Without it every cooldown and dedup lookup would have to be written twice.
            $table->foreignId('user_a_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_b_id')->constrained('users')->cascadeOnDelete();

            // The moment the encounter happened, taken from the report that created it.
            // Cooldown is measured against this, never against the time of arrival:
            // reports held offline arrive in a burst and would otherwise each look like
            // a separate encounter.
            $table->timestamp('detected_at');

            // The detecting phone's position, not a midpoint. Both riders were within
            // BLE range of it, which is as precise as the place of a meeting gets.
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);

            $table->timestamps();

            // Cooldown lookup: has this pair met around this time?
            $table->index(['user_a_id', 'user_b_id', 'detected_at']);

            // History listing for whichever side is asking, newest first.
            $table->index(['user_b_id', 'detected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
