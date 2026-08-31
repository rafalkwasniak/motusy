<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Przejazdy przysyłane przez urządzenie.
     *
     * Kształt wprost z docs/api-telemetria.md §7. Trzy rzeczy są tam
     * postawione twardo i nie wolno ich poluzować:
     *
     *  - unikalne (device_id, seq) — klucz idempotencji; powtórna wysyłka
     *    tego samego przejazdu ma trafić w istniejący wiersz, nie zrobić duplikatu,
     *  - recorded_at nullable — urządzenie nie ma zegara czasu rzeczywistego,
     *  - speed_kmh nullable — brak pomiaru to nie zero; bez GPS-a przychodzi
     *    null i tak też ma być pokazane („---", nie „0").
     */
    public function up(): void
    {
        Schema::create('rides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('device_id', 12)->index();
            $table->unsignedInteger('seq');

            $table->unsignedInteger('duration_s');
            $table->unsignedInteger('recorded_at')->nullable();

            $table->decimal('lean_left_deg', 4, 1);
            $table->decimal('lean_right_deg', 4, 1);
            $table->decimal('accel_g', 4, 2);
            $table->decimal('brake_g', 4, 2);
            $table->decimal('speed_kmh', 5, 1)->nullable();

            $table->string('fw', 16);
            $table->boolean('calibrated');

            $table->timestamps();

            // Kasowanie wyłącznie miękkie — inaczej skasowany przejazd
            // wróciłby przy następnej wysyłce z urządzenia.
            $table->softDeletes();

            $table->unique(['device_id', 'seq']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rides');
    }
};
