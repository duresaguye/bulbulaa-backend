<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('ethiopian_fiscal_year');
            $table->unsignedTinyInteger('ethiopian_month');
            $table->string('status', 16)->default('draft')->index();
            $table->text('summary');
            $table->text('missed_target_reasons')->nullable();
            $table->text('corrective_actions')->nullable();
            $table->json('evidence_paths')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['department_id', 'ethiopian_fiscal_year', 'ethiopian_month'], 'monthly_reports_department_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_reports');
    }
};
