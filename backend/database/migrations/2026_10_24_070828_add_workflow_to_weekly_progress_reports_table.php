<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_progress_reports', function (Blueprint $table) {
            $table->string('status', 16)->default('draft')->after('week_number')->index();
            $table->foreignId('prepared_by')->nullable()->after('department_id')->constrained('users')->nullOnDelete();
            $table->json('evidence_paths')->nullable()->after('next_steps');
        });

        Schema::table('weekly_progress_reports', function (Blueprint $table) {
            $table->unsignedBigInteger('submitted_by')->nullable()->change();
            $table->timestamp('submitted_at')->nullable()->change();
        });

        /** Every report recorded before the review workflow existed was submitted when it was created. */
        DB::table('weekly_progress_reports')->update([
            'status' => 'submitted',
            'prepared_by' => DB::raw('submitted_by'),
        ]);
    }

    public function down(): void
    {
        Schema::table('weekly_progress_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prepared_by');
            $table->dropColumn(['status', 'evidence_paths']);
        });
    }
};
