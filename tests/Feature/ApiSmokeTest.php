<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ApiSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_pos_flow_for_admin_and_cashier(): void
    {
        $this->seed();

        $cashier = User::query()->where('email', 'kasir@cafe.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@cafe.test')->firstOrFail();
        $product = Product::query()->firstOrFail();

        $this->actingAs($cashier, 'sanctum')
            ->getJson('/api/menu')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Coffee');

        $transaction = $this->actingAs($cashier, 'sanctum')
            ->postJson('/api/transactions', [
                'items' => [
                    ['product_id' => $product->id, 'qty' => 1, 'topping_ids' => []],
                ],
                'payment_method' => 'cash',
                'paid_amount' => 50000,
                'tax_percent' => 0,
            ])
            ->assertCreated()
            ->json('data');

        $this->actingAs($cashier, 'sanctum')
            ->getJson("/api/transactions/{$transaction['id']}/receipt")
            ->assertOk()
            ->assertJsonPath('data.shop.name', 'Kasir Cafe');

        Auth::forgetGuards();
        $this->flushHeaders();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/reports/sales')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'summary',
                    'by_payment_method',
                    'by_cashier',
                    'top_products',
                ],
            ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/settings')
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_token_can_access_admin_reports(): void
    {
        $this->seed();

        $token = $this->postJson('/api/auth/login', [
            'email' => 'admin@cafe.test',
            'password' => 'password',
        ])->assertOk()->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/reports/sales')
            ->assertOk();
    }

    public function test_cashier_cannot_access_admin_routes(): void
    {
        $this->seed();

        $cashier = User::query()->where('email', 'kasir@cafe.test')->firstOrFail();

        $this->actingAs($cashier, 'sanctum')
            ->getJson('/api/admin/products')
            ->assertForbidden();
    }
}
