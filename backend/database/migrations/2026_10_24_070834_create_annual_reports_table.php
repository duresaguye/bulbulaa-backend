<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annual_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('ethiopian_fiscal_year');
            $table->string('status', 16)->default('draft')->index();
            $table->text('key_accomplishments');
            $table->text('missed_targets')->nullable();
            $table->text('corrective_actions')->nullable();
            $table->text('lessons_learned')->nullable();
            $table->json('evidence_paths')->nullable();
            $table->json('results_snapshot')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['department_id', 'ethiopian_fiscal_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annual_reports');
    }
};
