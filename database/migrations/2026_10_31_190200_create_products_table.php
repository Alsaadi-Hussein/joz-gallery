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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            // A category that still has products cannot be deleted.
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('slug')->unique();
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->unsignedInteger('price');
            // Ignored once the product has variants: stock then lives on each variant.
            $table->unsignedInteger('stock')->default(0);
            $table->string('sku')->nullable()->unique();
            $table->string('material_ar')->nullable();
            $table->string('material_en')->nullable();
            $table->string('color_ar')->nullable();
            $table->string('color_en')->nullable();
            $table->unsignedSmallInteger('width_cm')->nullable();
            $table->unsignedSmallInteger('depth_cm')->nullable();
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_new')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 300)->nullable();
            $table->timestamps();

            $table->index(['category_id', 'is_active']);
            $table->index(['is_active', 'is_new']);
            $table->index(['is_active', 'is_featured']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('color_ar')->nullable();
            $table->string('color_en')->nullable();
            $table->string('size')->nullable();
            $table->integer('price_delta')->default(0);
            $table->unsignedInteger('stock')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
    }
};
