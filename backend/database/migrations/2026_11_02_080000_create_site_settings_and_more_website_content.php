<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Website text staff edit in the admin: site settings (mayor, contact, home page, city facts, investment park,
     * about gallery), investment sectors, about sections, and the public office directory.
     */
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->json('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('investment_sectors', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20)->default('other');
            $table->json('title');
            $table->json('description')->nullable();
            $table->json('items')->nullable();
            $table->string('image')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false)->index();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('about_sections', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->json('body')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false)->index();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('is_active');
            $table->json('public_name')->nullable()->after('is_public');
            $table->json('public_description')->nullable()->after('public_name');
            $table->json('public_location')->nullable()->after('public_description');
            $table->string('public_phone', 40)->nullable()->after('public_location');
            $table->string('public_email')->nullable()->after('public_phone');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn(['is_public', 'public_name', 'public_description', 'public_location', 'public_phone', 'public_email']);
        });
        Schema::dropIfExists('about_sections');
        Schema::dropIfExists('investment_sectors');
        Schema::dropIfExists('site_settings');
    }
};
