<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('short_name', 50)->nullable();
            $table->string('motto')->nullable();
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('footer_text')->nullable();
            $table->string('copyright_holder')->nullable();
            $table->string('support_email')->nullable();
            $table->string('support_phone', 50)->nullable();
            $table->string('whatsapp', 50)->nullable();
            $table->string('address')->nullable();
            $table->string('website')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('x_url')->nullable();
            $table->string('youtube_url')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('brand_primary', 7)->nullable();
            $table->string('brand_secondary', 7)->nullable();
            $table->string('brand_accent', 7)->nullable();
            $table->string('brand_background', 7)->nullable();
            $table->string('brand_text', 7)->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->string('seo_keywords')->nullable();
            $table->string('og_image_path')->nullable();
            $table->boolean('noindex_dashboard')->default(true);
            $table->string('maintenance_banner')->nullable();
            $table->string('terms_url')->nullable();
            $table->string('privacy_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
