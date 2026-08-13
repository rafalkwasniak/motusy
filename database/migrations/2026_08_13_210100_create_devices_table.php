<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('device_id');
            $table->string('platform');
            $table->string('app_version')->nullable();

            // Unused until Firebase lands, but present from the start: adding it later
            // would mean a migration on a live table plus collecting the tokens again
            // from every installed device. FCM rotates these, so it must be updatable.
            $table->string('push_token')->nullable();

            // Ties the device to the access token it signed in with, so a single
            // device can be signed out from a list and push can be addressed to a
            // device rather than to an account.
            $table->foreignId('personal_access_token_id')
                ->nullable()
                ->constrained('personal_access_tokens')
                ->nullOnDelete();

            $table->boolean('active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            // Scoped to the account, not global: the same phone may serve two accounts.
            $table->unique(['user_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
