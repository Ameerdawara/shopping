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
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('cards')->cascadeOnDelete();
<<<<<<< HEAD
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
=======
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
>>>>>>> 6607c001d65afe7c67930856e07375152a8b0d5c
            $table->integer("quantity")->default(1);
            $table->float("unit_price");
            $table->string('color')->nullable();
            $table->string('size')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
