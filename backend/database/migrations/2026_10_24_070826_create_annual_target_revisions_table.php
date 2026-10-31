<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annual_target_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annual_target_id')->constrained()->cascadeOnDelete();
            $table->foreignId('revised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('previous_value', 15, 2);
            $table->decimal('new_value', 15, 2);
            $table->text('reason');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annual_target_revisions');
    }
};
