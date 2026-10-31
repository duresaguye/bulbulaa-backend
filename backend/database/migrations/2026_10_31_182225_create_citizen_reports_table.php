<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Messages, complaints, safety concerns and suggestions sent from the public website.
     * No IP address is stored, so anonymous reports stay anonymous.
     */
    public function up(): void
    {
        Schema::create('citizen_reports', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->string('category', 20)->index();
            $table->text('details');
            $table->string('location')->nullable();
            $table->boolean('is_anonymous')->default(true);
            $table->string('reporter_name')->nullable();
            $table->string('reporter_contact')->nullable();
            $table->json('photos')->nullable();
            $table->string('locale', 5)->nullable();
            $table->string('status', 20)->default('new')->index();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->text('staff_note')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citizen_reports');
    }
};
