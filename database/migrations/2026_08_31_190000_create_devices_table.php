<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Egzemplarz urządzenia w koncie użytkownika.
     *
     * Kontrakt telemetrii nie przewiduje osobnego kroku rejestracji urządzenia
     * — nieznane pudełko przypisuje się do właściciela tokena przy pierwszej
     * udanej wysyłce. Ta tabela istnieje po to, żeby dało się je nazwać i żeby
     * dało się odróżnić kilka pudełek w jednym koncie.
     */
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Identyfikator układu z eFuse MAC: 12 znaków hex, publiczny,
            // stały przez całe życie urządzenia. Jedno pudełko = jedno konto.
            $table->string('device_id', 12)->unique();

            // Nazwa nadawana w panelu. Puste = pokazujemy device_id.
            $table->string('name')->nullable();

            // Rodzaj urządzenia. Dziś jest tylko Moto Box, ale zgodnie
            // z założeniem projektu odróżniamy egzemplarz od typu.
            $table->string('type', 32)->default('motobox');

            // Ostatnie znane dane z przesyłki — wyłącznie do diagnostyki.
            $table->string('fw', 16)->nullable();
            $table->boolean('calibrated')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
