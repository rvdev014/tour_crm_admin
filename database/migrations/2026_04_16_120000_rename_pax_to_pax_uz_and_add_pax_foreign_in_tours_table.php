<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->renameColumn('pax', 'pax_uz');
        });

        Schema::table('tours', function (Blueprint $table) {
            $table->integer('pax_foreign')->nullable()->after('pax_uz');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn('pax_foreign');
        });

        Schema::table('tours', function (Blueprint $table) {
            $table->renameColumn('pax_uz', 'pax');
        });
    }
};
