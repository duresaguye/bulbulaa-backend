<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('annual_targets', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('annual_targets')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->after('department_id')->constrained('users')->nullOnDelete();
            $table->string('aggregation', 16)->default('sum')->after('measurement_unit');
            $table->decimal('original_target_value', 15, 2)->nullable()->after('target_value');
        });

        DB::table('annual_targets')->update(['original_target_value' => DB::raw('target_value')]);
    }

    public function down(): void
    {
        Schema::table('annual_targets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropConstrainedForeignId('assigned_by');
            $table->dropColumn(['aggregation', 'original_target_value']);
        });
    }
};
