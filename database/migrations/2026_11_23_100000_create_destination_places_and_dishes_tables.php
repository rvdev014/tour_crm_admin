<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('destination_places', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destination_id')->constrained('destinations')->cascadeOnDelete();
            $table->string('category')->default('other');
            $table->string('title_ru')->nullable();
            $table->string('title_en')->nullable();
            $table->text('description_ru')->nullable();
            $table->text('description_en')->nullable();
            $table->string('photo')->nullable();
            $table->foreignId('link_destination_id')->nullable()->constrained('destinations')->nullOnDelete();
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index(['destination_id', 'category', 'order']);
        });

        Schema::create('destination_dishes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destination_id')->constrained('destinations')->cascadeOnDelete();
            $table->string('name_ru')->nullable();
            $table->string('name_en')->nullable();
            $table->text('description_ru')->nullable();
            $table->text('description_en')->nullable();
            $table->string('photo')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index(['destination_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('destination_dishes');
        Schema::dropIfExists('destination_places');
    }
};
