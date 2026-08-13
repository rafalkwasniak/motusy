<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ble_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // 128 bits hex encoded. Fixed width because every token has the same size
            // and the column is looked up on every resolution.
            $table->char('token', 32)->unique();

            $table->boolean('active')->default(true);

            // Null while the token is the one being broadcast. Set when it is retired,
            // marking how long it can still be resolved for late offline reports.
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            // Fetching the token to broadcast, and pruning retired ones.
            $table->index(['user_id', 'active']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ble_identities');
    }
};
