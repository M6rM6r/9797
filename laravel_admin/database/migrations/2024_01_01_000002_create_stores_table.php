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
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('logo_url');
            $table->text('description');
            $table->string('affiliate_base_url');
            $table->decimal('cashback_percent', 5, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->string('firebase_document_id')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['is_active']);
            $table->index(['name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
