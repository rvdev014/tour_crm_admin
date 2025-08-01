<?php

use App\Enums\TourStatus;
use App\Enums\WebTourStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('web_tour_requests', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');

            $table->bigInteger('web_tour_id')->unsigned();
            $table->foreign('web_tour_id')->references('id')->on('web_tours')->onDelete('restrict');

            $table->string('phone')->nullable();
            $table->string('citizenship')->nullable();
            $table->text('comment')->nullable();
            $table->integer('travellers_count')->nullable();
            $table->integer('tour_type')->nullable();
            $table->date('start_date');
            $table->integer('status')->default(WebTourStatus::New->value);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tour_requests');
    }
};
