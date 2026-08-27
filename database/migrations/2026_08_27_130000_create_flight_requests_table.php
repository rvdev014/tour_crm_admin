<?php

use App\Enums\WebTourStatus;
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
        Schema::create('flight_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from');
            $table->string('to');
            $table->date('departure_date');
            $table->date('return_date')->nullable()->comment('One-way if empty');
            $table->integer('passengers_count')->default(1);
            $table->string('cabin_class')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('comment')->nullable();
            $table->string('status')->default(WebTourStatus::New->value);
            $table->foreignId('status_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flight_requests');
    }
};
