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
        Schema::table('hotels', function (Blueprint $table) {
            $table->string('company_name')->nullable();
            $table->string('address')->nullable();
            $table->float('rate')->nullable();
            $table->string('phone')->nullable();
            $table->text('comment')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn('company_name');
            $table->dropColumn('address');
            $table->dropColumn('rate');
            $table->dropColumn('phone');
            $table->dropColumn('comment');
        });
    }
};
