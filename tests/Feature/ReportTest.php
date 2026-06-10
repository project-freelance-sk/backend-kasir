<?php

namespace Tests\Feature;

use App\Models\Categories;
use App\Models\Product;
use App\Models\ShopSetting;
use App\Models\TransactionDetails;
use App\Models\Transactions;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Categories::query()->create(['name' => 'Coffee', 'is_active' => true]);

        $this->product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Latte',
            'price' => 25000,
            'is_active' => true,
        ]);

        ShopSetting::query()->create([
            'shop_name' => 'Test Cafe',
            'tax_percent' => 0,
            'service_charge_percent' => 0,
        ]);

        $this->cashier = User::query()->create([
            'name' => 'Kasir 1',
            'email' => 'kasir1@test.com',
            'password' => Hash::make('password'),
            'role' => 'cashier',
            'is_active' => true,
        ]);

        User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_admin_report_transactions_default_to_paid_only(): void
    {
        $paid = $this->createTransaction('paid', 50000);
        $this->createTransaction('voided', 30000);

        $admin = User::query()->where('email', 'admin@test.com')->firstOrFail();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/reports/transactions?from='.now()->toDateString().'&to='.now()->toDateString())
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($paid->id));
        $this->assertCount(1, $ids);
    }

    public function test_cashier_transactions_with_date_range_default_to_paid_only(): void
    {
        $paid = $this->createTransaction('paid', 50000);
        $this->createTransaction('voided', 30000);

        $response = $this->actingAs($this->cashier, 'sanctum')
            ->getJson('/api/transactions?from='.now()->toDateString().'&to='.now()->toDateString())
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($paid->id));
        $this->assertCount(1, $ids);
    }

    public function test_by_cashier_matches_paid_transactions_for_cashier(): void
    {
        $this->createTransaction('paid', 50000, qty: 2);
        $this->createTransaction('voided', 30000);

        $admin = User::query()->where('email', 'admin@test.com')->firstOrFail();
        $from = now()->toDateString();
        $to = now()->toDateString();

        $sales = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/reports/sales?from={$from}&to={$to}")
            ->assertOk()
            ->json('data.by_cashier');

        $cashierRow = collect($sales)->firstWhere('cashier_name', 'Kasir 1');

        $this->assertNotNull($cashierRow);
        $this->assertSame(1, $cashierRow['transactions']);
        $this->assertEquals(50000, $cashierRow['revenue']);
        $this->assertSame(2, $cashierRow['items_sold']);
        $this->assertEquals(50000, $cashierRow['cash_revenue']);
    }

    public function test_by_cashier_lists_all_cashiers_on_same_day(): void
    {
        $cashierTwo = User::query()->create([
            'name' => 'Kasir 2',
            'email' => 'kasir2@test.com',
            'password' => Hash::make('password'),
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $cashierThree = User::query()->create([
            'name' => 'Kasir 3',
            'email' => 'kasir3@test.com',
            'password' => Hash::make('password'),
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $this->createTransaction('paid', 50000, cashier: $this->cashier);
        $this->createTransaction('paid', 30000, cashier: $cashierTwo);
        $this->createTransaction('paid', 20000, cashier: $cashierThree);

        $admin = User::query()->where('email', 'admin@test.com')->firstOrFail();
        $from = now()->toDateString();
        $to = now()->toDateString();

        $sales = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/reports/sales?from={$from}&to={$to}")
            ->assertOk()
            ->json('data.by_cashier');

        $this->assertCount(3, $sales);
        $this->assertEqualsCanonicalizing(
            ['Kasir 1', 'Kasir 2', 'Kasir 3'],
            collect($sales)->pluck('cashier_name')->all()
        );
    }

    private function createTransaction(
        string $status,
        float $total,
        int $qty = 1,
        ?User $cashier = null,
    ): Transactions
    {
        $transaction = Transactions::query()->create([
            'cashier_id' => ($cashier ?? $this->cashier)->id,
            'invoice_number' => 'INV-'.uniqid(),
            'payment_method' => 'cash',
            'subtotal' => $total,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'service_charge_amount' => 0,
            'total' => $total,
            'paid_amount' => $total,
            'change_amount' => 0,
            'payment_status' => $status,
        ]);

        TransactionDetails::query()->create([
            'transaction_id' => $transaction->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'qty' => $qty,
            'price' => $total / $qty,
            'subtotal' => $total,
        ]);

        return $transaction;
    }
}
