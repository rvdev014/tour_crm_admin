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
        Schema::create('hotel_rules', function (Blueprint $table) {
            $table->id();
            
            // Связь с таблицей hotels.
            // constrained() предполагает, что таблица называется 'hotels'.
            // cascadeOnDelete() удалит правила, если удален сам отель.
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            
            // Тип правила: 'early_check_in', 'late_check_out'
            $table->string('rule_type');
            
            // Время начала и конца действия правила (H:i:s)
            $table->time('start_time');
            $table->time('end_time');
            
            // Тип влияния на цену: 'percentage', 'fixed', 'hourly'
            $table->string('price_impact_type');
            
            // Значение влияния. Используем decimal для точности.
            // 10 цифр всего, 2 после запятой (например: 100.00, 50.50)
            $table->decimal('impact_value', 10, 2);
            
            // Входит ли питание/услуги в эту стоимость (информационное поле)
            $table->boolean('is_inclusive')->default(false);
            $table->timestamps();
            $table->index(['hotel_id', 'rule_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotel_rules');
    }
};
