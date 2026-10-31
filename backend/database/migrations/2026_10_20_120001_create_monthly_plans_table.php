<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annual_target_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('ethiopian_month');
            $table->decimal('planned_value', 15, 2);
            $table->text('planned_activities')->nullable();
            $table->timestamps();

            $table->unique(['annual_target_id', 'ethiopian_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_plans');
    }
};
