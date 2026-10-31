<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_progress_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('ethiopian_fiscal_year');
            $table->unsignedTinyInteger('ethiopian_month');
            $table->unsignedTinyInteger('week_number');
            $table->text('achievements');
            $table->text('challenges')->nullable();
            $table->text('next_steps')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(['department_id', 'ethiopian_fiscal_year', 'ethiopian_month', 'week_number'], 'weekly_reports_department_period_unique');
            $table->index(['ethiopian_fiscal_year', 'ethiopian_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_progress_reports');
    }
};
