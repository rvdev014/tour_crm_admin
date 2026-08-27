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
        Schema::create('destinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('destinations')->cascadeOnDelete()
                ->comment('null = country-level destination, set = place inside that country');
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete()
                ->comment('Optional link to the operational City so tours auto-match by itinerary');
            $table->string('slug')->unique();
            $table->string('title_ru');
            $table->string('title_en')->nullable();
            $table->text('short_description_ru')->nullable();
            $table->text('short_description_en')->nullable();
            $table->text('description_ru')->nullable();
            $table->text('description_en')->nullable();
            $table->string('photo')->nullable();
            $table->jsonb('photos')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamps();

            $table->index(['parent_id', 'order']);
        });

        Schema::create('destination_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destination_id')->constrained()->cascadeOnDelete();
            $table->string('title_ru');
            $table->string('title_en')->nullable();
            $table->string('anchor')->nullable()->comment('Slug used for the in-page jump link');
            $table->text('content_ru')->nullable();
            $table->text('content_en')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        Schema::create('destination_web_tour', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destination_id')->constrained()->cascadeOnDelete();
            $table->foreignId('web_tour_id')->constrained()->cascadeOnDelete();

            $table->unique(['destination_id', 'web_tour_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('destination_web_tour');
        Schema::dropIfExists('destination_sections');
        Schema::dropIfExists('destinations');
    }
};
