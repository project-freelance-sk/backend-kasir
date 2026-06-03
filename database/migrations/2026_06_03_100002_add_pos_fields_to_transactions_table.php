<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->unique()->after('invoice_number');
            $table->string('order_type', 20)->nullable()->after('payment_method');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('subtotal');
            $table->decimal('tax_amount', 12, 2)->default(0)->after('discount_amount');
            $table->decimal('service_charge_amount', 12, 2)->default(0)->after('tax_amount');
            $table->timestamp('voided_at')->nullable()->after('payment_status');
            $table->foreignUuid('voided_by')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable()->after('voided_by');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE transactions MODIFY COLUMN payment_status ENUM('pending', 'paid', 'voided') NOT NULL DEFAULT 'paid'");
        }
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['voided_by']);
            $table->dropColumn([
                'idempotency_key',
                'order_type',
                'discount_amount',
                'tax_amount',
                'service_charge_amount',
                'voided_at',
                'voided_by',
                'void_reason',
            ]);
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE transactions MODIFY COLUMN payment_status ENUM('pending', 'paid') NOT NULL DEFAULT 'paid'");
        }
    }
};
