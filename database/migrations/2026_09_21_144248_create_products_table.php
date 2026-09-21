<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('category')->nullable()->index();
            $table->string('subcategory')->nullable();
            $table->string('brand')->nullable();
            $table->string('style_period')->nullable()->index();
            $table->string('metal_type')->nullable();
            $table->decimal('weight_grams', 10, 3)->nullable();
            $table->string('measurements')->nullable();
            $table->text('condition_notes')->nullable();
            $table->text('internal_description')->nullable();
            $table->text('customer_description')->nullable();
            $table->text('seo_description')->nullable();
            $table->text('marketplace_description')->nullable();
            $table->text('social_description')->nullable();
            $table->enum('status', ['draft', 'pending_review', 'approved', 'listed', 'sold', 'archived'])->default('draft')->index();
            // Field names a human replaced after a suggestion — Phase 2's
            // correction logging reads this from day one (Module 5).
            $table->json('manually_overridden_fields')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_for_review_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
