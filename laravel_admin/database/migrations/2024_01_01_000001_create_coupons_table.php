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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('store_id');
            $table->integer('discount_percent');
            $table->text('description');
            $table->timestamp('expires_at');
            $table->integer('usage_count')->default(0);
            $table->string('category');
            $table->boolean('is_verified')->default(false);
            $table->string('affiliate_link');
            $table->boolean('is_active')->default(true);
            $table->boolean('app_only')->default(false);
            $table->string('firebase_document_id')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['is_active', 'expires_at']);
            $table->index(['is_active', 'category', 'usage_count']);
            $table->index(['is_active', 'store_id', 'usage_count']);
            $table->index(['code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
