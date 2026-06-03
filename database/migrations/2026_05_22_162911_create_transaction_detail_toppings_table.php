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
        Schema::create('transaction_detail_toppings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('transaction_detail_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignUuid('topping_id')
                ->constrained()
                ->restrictOnDelete();

            $table->decimal('price', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_detail_toppings');
    }
};
