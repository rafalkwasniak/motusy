<?php

use App\Support\AccountToken;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Token konta — jeden na konto, wspólny dla wszystkich urządzeń właściciela
     * (kontrakt telemetrii §2).
     *
     * Trzymany otwartym tekstem, bo ma być widoczny w panelu przez cały czas.
     * Skrót (jak w Sanctum) pozwoliłby pokazać go wyłącznie raz, w chwili
     * wydania, a to nie pasuje do sposobu, w jaki się go używa: przepisuje
     * się go do pudełka przy każdej ponownej konfiguracji WiFi.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('api_token', 14)->nullable()->unique()->after('email');
        });

        // Konta założone przed tą zmianą też mają dostać token.
        DB::table('users')->whereNull('api_token')->orderBy('id')->each(function ($user) {
            DB::table('users')->where('id', $user->id)->update([
                'api_token' => AccountToken::generate(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['api_token']);
            $table->dropColumn('api_token');
        });
    }
};
