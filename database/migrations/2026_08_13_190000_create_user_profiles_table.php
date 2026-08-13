<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('nickname');
            $table->string('gender');

            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('avatar')->nullable();
            $table->text('bio')->nullable();
            $table->string('phone')->nullable();

            // Per-field visibility. The card shape never changes; hidden values are
            // returned as null so the client never has to branch on missing keys.
            $table->boolean('phone_visible')->default(false);
            $table->boolean('email_visible')->default(false);
            $table->boolean('first_name_visible')->default(false);
            $table->boolean('last_name_visible')->default(false);

            $table->timestamps();

            $table->index('nickname');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
