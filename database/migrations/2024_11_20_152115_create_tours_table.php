<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('tours', function(Blueprint $table) {
            $table->id();
            $table->string('group_number');
            $table->bigInteger('company_id');
            $table->foreign('company_id')->references('id')->on('companies');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('pax');
            $table->bigInteger('country_id');
            $table->foreign('country_id')->references('id')->on('countries');
            $table->bigInteger('city_id');
            $table->foreign('city_id')->references('id')->on('cities');
            $table->integer('created_by');
            $table->foreign('created_by')->references('id')->on('users');


            $table->integer('status')->nullable();
            $table->string('arrival')->nullable();
            $table->string('departure')->nullable();
            $table->string('rooming')->nullable();

            $table->integer('type');

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
