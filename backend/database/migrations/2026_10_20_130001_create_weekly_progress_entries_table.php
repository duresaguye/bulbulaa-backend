<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_progress_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekly_progress_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monthly_plan_id')->constrained()->restrictOnDelete();
            $table->decimal('actual_value', 15, 2);
            $table->text('progress_summary')->nullable();
            $table->timestamps();

            $table->unique(['weekly_progress_report_id', 'monthly_plan_id'], 'weekly_progress_entries_report_plan_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_progress_entries');
    }
};
