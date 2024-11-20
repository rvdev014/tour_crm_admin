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
        Schema::disableForeignKeyConstraints();

        Schema::create('tours', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('company_id');
            $table->foreign('company_id')->references('id')->on('companies');
            $table->string('group_number');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('arrival')->nullable();
            $table->string('departure')->nullable();
            $table->string('rooming')->nullable();
            $table->integer('pax');
            $table->integer('status')->nullable();
            $table->bigInteger('country_id');

            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tours');
    }
};
