<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ślady tras — docs/api-slad-trasy.md §5 i docs/api-slad-implementacja-laravel.md §1.
     *
     * Surowa treść ląduje na prywatnym dysku, nie w kolumnie: kilkadziesiąt
     * kilobajtów w `longText` obciążałoby każde listowanie przejazdów, nawet
     * gdy nikt nie otwiera śladu. Plik zostaje źródłem prawdy, z którego da
     * się wszystko przeliczyć od nowa, gdy parser się poprawi.
     *
     * Statystyki liczymy raz, przy przyjęciu — i tak parsujemy cały plik,
     * żeby móc odpowiedzieć 422 na uszkodzony (kontrakt §2).
     */
    public function up(): void
    {
        Schema::create('ride_tracks', function (Blueprint $table) {
            $table->id();

            // Kasowanie konta ma zabrać ślady ze sobą, tak jak przejazdy —
            // bez tego „usuń konto" w ustawieniach wywala się na kluczu obcym.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Klucz idempotencji, ten sam co przy przejazdach (kontrakt §2).
            $table->string('device_id', 12);
            $table->unsignedInteger('seq');

            // NULLABLE CELOWO: ślad bywa szybszy niż wynik przejazdu, bo to
            // dwa niezależne żądania i o kolejności decyduje moment złapania
            // sieci. Odesłanie 404 zakleszczyłoby wysyłkę (kontrakt §2).
            $table->foreignId('ride_id')->nullable()->constrained()->nullOnDelete();

            $table->string('path');
            $table->unsignedInteger('bytes');
            $table->string('format', 8);
            $table->string('fw', 16);

            // Szerokość korytarza z nagłówka `eps`. Szesnaście bitów, a nie
            // osiem: przyszłe firmware może zapisywać rzadziej, a odbicie
            // takiego pliku kodem 422 skasowałoby go z urządzenia na zawsze.
            $table->unsignedSmallInteger('corridor_m');

            // Statystyki policzone przy przyjęciu.
            $table->unsignedInteger('point_count');
            $table->unsignedInteger('segment_count');
            $table->unsignedInteger('distance_m');

            // Unix UTC, jak `recorded_at` w przejazdach: urządzenie nie zna
            // swojej strefy, a null znaczy „czasu nie było", nie „rok 1970".
            $table->unsignedInteger('started_at')->nullable();
            $table->unsignedInteger('ended_at')->nullable();

            $table->decimal('min_lat', 9, 6)->nullable();
            $table->decimal('max_lat', 9, 6)->nullable();
            $table->decimal('min_lon', 9, 6)->nullable();
            $table->decimal('max_lon', 9, 6)->nullable();
            $table->smallInteger('max_lean_deg')->nullable();

            $table->timestamps();

            // Miękko, tak samo jak przejazdy (kontrakt §5) — inaczej ślad
            // skasowany w panelu wracałby przy następnej wysyłce.
            $table->softDeletes();

            $table->unique(['device_id', 'seq']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_tracks');
    }
};
