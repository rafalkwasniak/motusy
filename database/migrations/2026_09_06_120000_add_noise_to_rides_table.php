<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pomiar hałasu — pięć pól, które urządzenie dosyła od 6 września 2026
     * (docs/api-halas-implementacja-laravel.md §1).
     *
     * Zmiana jest czysto addytywna: stare firmware tych pól nie wysyła
     * i ma dalej działać, więc dwie pierwsze kolumny **muszą** być nullable.
     * Istniejące wiersze dostaną w nich null i to jest stan poprawny —
     * te przejazdy odbyły się przed mikrofonem. Nie wypełniać ich zerami:
     * cicha jazda i martwy mikrofon to dwie różne rzeczy (§5.1), dokładnie
     * jak przy `speed_kmh` i braku GPS-a.
     *
     * Liczniki przychodzą zawsze, także przy `max_noise_db = null` — to one
     * odróżniają ciszę od awarii (§6) — więc tam default 0 jest na miejscu.
     */
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            // Sufit skali przetwornika leży na 126,4 dB(A), ale szerokość
            // bierzemy z zapasem — tak samo jak przy `speed_kmh`.
            $table->decimal('max_noise_db', 5, 1)->nullable()->after('speed_kmh');

            // Prędkość w chwili ustanowienia rekordu hałasu. Bywa pusta
            // niezależnie od `max_noise_db`: mikrofon działa bez GPS-a.
            $table->unsignedSmallInteger('noise_at_speed_kmh')->nullable()->after('max_noise_db');

            $table->unsignedInteger('noise_clipped')->default(0)->after('noise_at_speed_kmh');
            $table->unsignedInteger('noise_dropped')->default(0)->after('noise_clipped');

            // Znacznik serii pomiarowej (§5.2). Szkic wdrożenia proponował
            // `tinyint`, czyli sufit 255. Bierzemy `smallint` z tego samego
            // powodu, dla którego `corridor_m` w śladzie jest szerszy od
            // szkicu: wartość niemieszcząca się w kolumnie znaczy 422 na całą
            // przesyłkę, a odbita przesyłka wraca z urządzenia w kółko —
            // zakleszczenie pudełka w terenie jest gorsze niż dwa bajty.
            $table->unsignedSmallInteger('noise_cal')->default(0)->after('noise_dropped');
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropColumn([
                'max_noise_db',
                'noise_at_speed_kmh',
                'noise_clipped',
                'noise_dropped',
                'noise_cal',
            ]);
        });
    }
};
