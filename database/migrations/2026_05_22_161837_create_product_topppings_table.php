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
        Schema::create('product_toppings', function (Blueprint $table) {
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('topping_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['product_id', 'topping_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_topppings');
    }
};
