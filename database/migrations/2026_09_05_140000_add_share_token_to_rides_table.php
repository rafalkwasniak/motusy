<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Token publicznego podglądu przejazdu.
     *
     * Sam adres jest tu poświadczeniem: kto zna token, ten widzi kartę
     * przejazdu bez logowania. Stąd 128 bitów losowości zamiast czytelnego
     * `XFRS-34ST-YTS8` z tokena konta — tamten człowiek przepisuje z ekranu
     * do pudełka, a ten wkleja się z schowka i ma być niezgadywalny.
     *
     * Kolumna jest `nullable` wyłącznie na czas tej migracji: wiersze
     * dostają token w pętli niżej, a nowe biorą go w `Ride::booted()`.
     */
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->char('share_token', 32)->nullable()->unique()->after('seq');
        });

        // Przejazdy zapisane przed tą migracją też mają dać się wysłać
        // linkiem — inaczej udostępnianie działałoby dopiero od następnej
        // przesyłki z urządzenia. Bez `withTrashed`, bo `DB::table` i tak
        // nie zna miękkiego kasowania, a token ma mieć każdy wiersz.
        DB::table('rides')->whereNull('share_token')->orderBy('id')->each(
            fn (object $ride) => DB::table('rides')
                ->where('id', $ride->id)
                ->update(['share_token' => bin2hex(random_bytes(16))])
        );
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            // Indeks jawnie przed kolumną: MySQL nie pozwala zdjąć kolumny,
            // na której wisi unikat.
            $table->dropUnique(['share_token']);
            $table->dropColumn('share_token');
        });
    }
};
