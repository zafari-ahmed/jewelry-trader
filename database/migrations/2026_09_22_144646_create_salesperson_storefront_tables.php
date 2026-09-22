<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — Salesperson Storefronts. Tables only, gated by
 * features.salesperson_storefront.enabled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salesperson_storefronts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subdomain')->unique();
            $table->json('branding_config')->nullable();
            $table->enum('status', ['draft', 'active', 'suspended'])->default('draft');
            $table->timestamps();
        });

        Schema::create('storefront_inventory_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salesperson_storefront_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected', 'withdrawn'])->default('pending');
            $table->timestamps();

            // Named explicitly: the generated name exceeds MySQL's 64-character
            // identifier limit and fails after the table is created.
            $table->unique(['salesperson_storefront_id', 'product_id'], 'storefront_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_inventory_requests');
        Schema::dropIfExists('salesperson_storefronts');
    }
};
