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
        Schema::create('transaction_details', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('transaction_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignUuid('product_id')
                ->constrained()
                ->restrictOnDelete();

            $table->integer('qty');

            $table->decimal('price', 12, 2);
            $table->decimal('subtotal', 12, 2);

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_details');
    }
};
