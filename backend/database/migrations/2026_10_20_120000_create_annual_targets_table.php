<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annual_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('measurement_unit', 64);
            $table->decimal('target_value', 15, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['department_id', 'fiscal_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annual_targets');
    }
};
