<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->constrained();
            $table->enum('channel', ['pos', 'web'])->index();
            $table->unsignedBigInteger('subtotal_cents')->default(0);
            $table->unsignedBigInteger('tax_cents')->default(0);
            $table->unsignedBigInteger('discount_total_cents')->default(0);
            $table->unsignedBigInteger('total_cents')->default(0);
            // Tax is US state-based: the rate applied is recorded on the order
            // so a later rate change never rewrites history.
            $table->decimal('tax_rate', 6, 4)->default(0);
            $table->string('tax_state', 2)->nullable();
            $table->enum('status', ['pending', 'paid', 'fulfilled', 'refunded', 'cancelled', 'partially_refunded'])->default('pending')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['location_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
