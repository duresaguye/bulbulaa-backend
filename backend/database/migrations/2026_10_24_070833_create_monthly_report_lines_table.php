<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_report_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monthly_plan_id')->constrained()->restrictOnDelete();
            $table->decimal('actual_value', 15, 2);
            $table->text('variance_note')->nullable();
            $table->timestamps();

            $table->unique(['monthly_report_id', 'monthly_plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_report_lines');
    }
};
