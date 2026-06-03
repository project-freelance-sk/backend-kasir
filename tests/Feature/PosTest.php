<?php

namespace Tests\Feature;

use App\Models\Categories;
use App\Models\Product;
use App\Models\ShopSetting;
use App\Models\Transactions;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PosTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private User $otherCashier;

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
            'tax_percent' => 10,
            'service_charge_percent' => 0,
        ]);

        $this->cashier = User::query()->create([
            'name' => 'Kasir 1',
            'email' => 'kasir1@test.com',
            'password' => Hash::make('password'),
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $this->otherCashier = User::query()->create([
            'name' => 'Kasir 2',
            'email' => 'kasir2@test.com',
            'password' => Hash::make('password'),
            'role' => 'cashier',
            'is_active' => true,
        ]);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::query()->create([
            'name' => 'Inactive',
            'email' => 'inactive@test.com',
            'password' => Hash::make('password'),
            'role' => 'cashier',
            'is_active' => false,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'inactive@test.com',
            'password' => 'password',
        ])->assertStatus(422);
    }

    public function test_checkout_snapshots_product_name_and_calculates_tax(): void
    {
        $response = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/transactions', [
                'items' => [
                    ['product_id' => $this->product->id, 'qty' => 2],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.details.0.product_name', 'Latte')
            ->assertJsonPath('data.subtotal', '50000.00')
            ->assertJsonPath('data.tax_amount', '5000.00')
            ->assertJsonPath('data.total', '55000.00');
    }

    public function test_cashier_cannot_view_other_cashier_transaction(): void
    {
        $transaction = Transactions::query()->create([
            'cashier_id' => $this->otherCashier->id,
            'invoice_number' => 'INV-TEST-0001',
            'payment_method' => 'cash',
            'subtotal' => 10000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'service_charge_amount' => 0,
            'total' => 10000,
            'paid_amount' => 10000,
            'change_amount' => 0,
            'payment_status' => 'paid',
        ]);

        $this->actingAs($this->cashier, 'sanctum')
            ->getJson("/api/transactions/{$transaction->id}")
            ->assertForbidden();
    }

    public function test_void_transaction(): void
    {
        $transaction = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/transactions', [
                'items' => [
                    ['product_id' => $this->product->id, 'qty' => 1],
                ],
                'tax_percent' => 0,
            ])
            ->json('data');

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/transactions/{$transaction['id']}/void", [
                'reason' => 'Salah input',
            ])
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'voided');
    }

    public function test_idempotency_key_returns_same_transaction(): void
    {
        $payload = [
            'idempotency_key' => 'order-123',
            'items' => [
                ['product_id' => $this->product->id, 'qty' => 1],
            ],
            'tax_percent' => 0,
        ];

        $first = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/transactions', $payload)
            ->assertCreated()
            ->json('data.id');

        $second = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/transactions', $payload)
            ->assertCreated()
            ->json('data.id');

        $this->assertSame($first, $second);
    }

    public function test_inactive_category_blocks_checkout(): void
    {
        Categories::query()->whereKey($this->product->category_id)->update(['is_active' => false]);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/transactions', [
                'items' => [
                    ['product_id' => $this->product->id, 'qty' => 1],
                ],
                'tax_percent' => 0,
            ])
            ->assertStatus(422);
    }
}
