<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_settings', function (Blueprint $table) {
            $table->id();
            $table->string('shop_name')->default('Coffee Shop');
            $table->text('address')->nullable();
            $table->text('receipt_footer')->nullable();
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('service_charge_percent', 5, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_settings');
    }
};
