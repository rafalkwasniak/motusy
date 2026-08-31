<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rozdzielenie tożsamości publicznej od prywatnej.
     *
     * `nickname` to nazwa pokazywana w portalu i kiedyś na froncie — imię
     * i nazwisko nie mają tam wyciekać. `name` zostaje jako dane prywatne,
     * uzupełniane dobrowolnie w profilu, więc musi stać się nullowalne:
     * rejestracja już o nie nie pyta.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nickname', 30)->nullable()->unique()->after('id');
            $table->string('name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['nickname']);
            $table->dropColumn('nickname');
            $table->string('name')->nullable(false)->change();
        });
    }
};
