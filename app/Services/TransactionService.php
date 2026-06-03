<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ShopSetting;
use App\Models\Toppings;
use App\Models\TransactionDetailToppings;
use App\Models\TransactionDetails;
use App\Models\Transactions;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionService
{
    /**
     * @param  array<int, array{product_id: string, qty: int, notes?: string|null, topping_ids?: string[]}>  $items
     * @param  array{
     *     payment_method?: string,
     *     paid_amount?: float|int|null,
     *     discount_amount?: float|int|null,
     *     tax_percent?: float|int|null,
     *     service_charge_percent?: float|int|null,
     *     order_type?: string|null,
     *     idempotency_key?: string|null,
     * }|null  $payment
     */
    public function create(User $cashier, array $items, ?array $payment = null): Transactions
    {
        if (empty($items)) {
            throw ValidationException::withMessages([
                'items' => ['Keranjang tidak boleh kosong.'],
            ]);
        }

        $payment ??= [];

        if (! empty($payment['idempotency_key'])) {
            $existing = Transactions::query()
                ->where('idempotency_key', $payment['idempotency_key'])
                ->with($this->defaultRelations())
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($cashier, $items, $payment) {
            $subtotal = 0;
            $lineItems = [];

            foreach ($items as $index => $item) {
                $product = Product::query()
                    ->whereHas('category', fn($q) => $q->where('is_active', true))
                    ->with(['toppings', 'category'])
                    ->find($item['product_id']);

                if (! $product) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => ['Produk tidak ditemukan atau tidak aktif.'],
                    ]);
                }

                $qty = (int) $item['qty'];
                if ($qty < 1) {
                    throw ValidationException::withMessages([
                        "items.{$index}.qty" => ['Jumlah minimal 1.'],
                    ]);
                }

                $toppingIds = $item['topping_ids'] ?? [];
                $availableIds = $product->toppings->pluck('id')->all();

                $selectedToppings = collect();
                if (! empty($toppingIds)) {
                    $selectedToppings = Toppings::query()
                        ->where('is_active', true)
                        ->whereIn('id', $toppingIds)
                        ->get();

                    foreach ($toppingIds as $ti => $toppingId) {
                        if (! in_array($toppingId, $availableIds, true)) {
                            throw ValidationException::withMessages([
                                "items.{$index}.topping_ids.{$ti}" => [
                                    "Topping tidak tersedia untuk produk {$product->name}.",
                                ],
                            ]);
                        }
                    }

                    if ($selectedToppings->count() !== count(array_unique($toppingIds))) {
                        throw ValidationException::withMessages([
                            "items.{$index}.topping_ids" => ['Satu atau lebih topping tidak valid.'],
                        ]);
                    }
                }

                $toppingTotal = (float) $selectedToppings->sum('price');
                $unitPrice = (float) $product->getRawOriginal('price') + $toppingTotal;
                $lineSubtotal = $unitPrice * $qty;
                $subtotal += $lineSubtotal;

                $lineItems[] = [
                    'product' => $product,
                    'product_name' => $product->name,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'line_subtotal' => $lineSubtotal,
                    'notes' => $item['notes'] ?? null,
                    'toppings' => $selectedToppings,
                ];
            }

            $totals = $this->calculateTotals($subtotal, $payment);

            $paymentMethod = $payment['payment_method'] ?? 'cash';
            $paidAmount = isset($payment['paid_amount'])
                ? (float) $payment['paid_amount']
                : $totals['total'];

            if ($paymentMethod === 'cash' && $paidAmount < $totals['total']) {
                throw ValidationException::withMessages([
                    'paid_amount' => ['Jumlah pembayaran tunai kurang dari total belanja.'],
                ]);
            }

            $transaction = Transactions::query()->create([
                'cashier_id' => $cashier->id,
                'invoice_number' => $this->generateInvoiceNumber(),
                'idempotency_key' => $payment['idempotency_key'] ?? null,
                'payment_method' => $paymentMethod,
                'order_type' => $payment['order_type'] ?? null,
                'subtotal' => $subtotal,
                'discount_amount' => $totals['discount_amount'],
                'tax_amount' => $totals['tax_amount'],
                'service_charge_amount' => $totals['service_charge_amount'],
                'total' => $totals['total'],
                'paid_amount' => $paidAmount,
                'change_amount' => max(0, $paidAmount - $totals['total']),
                'payment_status' => 'paid',
            ]);

            foreach ($lineItems as $line) {
                $detail = TransactionDetails::query()->create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $line['product']->id,
                    'product_name' => $line['product_name'],
                    'qty' => $line['qty'],
                    'price' => $line['unit_price'],
                    'subtotal' => $line['line_subtotal'],
                    'notes' => $line['notes'],
                ]);

                foreach ($line['toppings'] as $topping) {
                    TransactionDetailToppings::query()->create([
                        'transaction_detail_id' => $detail->id,
                        'topping_id' => $topping->id,
                        'price' => $topping->price,
                    ]);
                }
            }

            return $transaction->load($this->defaultRelations());
        });
    }

    public function void(User $user, Transactions $transaction, string $reason): Transactions
    {
        if ($transaction->payment_status === 'voided') {
            throw ValidationException::withMessages([
                'transaction' => ['Transaksi sudah dibatalkan.'],
            ]);
        }

        if ($transaction->payment_status !== 'paid') {
            throw ValidationException::withMessages([
                'transaction' => ['Hanya transaksi berstatus paid yang dapat dibatalkan.'],
            ]);
        }

        if ($user->role === 'cashier' && $transaction->cashier_id !== $user->id) {
            throw ValidationException::withMessages([
                'transaction' => ['Anda tidak dapat membatalkan transaksi kasir lain.'],
            ]);
        }

        $transaction->update([
            'payment_status' => 'voided',
            'voided_at' => now(),
            'voided_by' => $user->id,
            'void_reason' => $reason,
        ]);

        return $transaction->fresh()->load($this->defaultRelations());
    }

    /**
     * @return array<int, string>
     */
    public function defaultRelations(): array
    {
        return [
            'cashier:id,name,email',
            'voidedBy:id,name,email',
            'details.product',
            'details.detailToppings.topping',
        ];
    }

    /**
     * @param  array<string, mixed>  $payment
     * @return array{discount_amount: float, tax_amount: float, service_charge_amount: float, total: float}
     */
    private function calculateTotals(float $subtotal, array $payment): array
    {
        $settings = ShopSetting::current();
        $discount = max(0, (float) ($payment['discount_amount'] ?? 0));

        if ($discount > $subtotal) {
            throw ValidationException::withMessages([
                'discount_amount' => ['Diskon tidak boleh melebihi subtotal.'],
            ]);
        }

        $taxable = $subtotal - $discount;
        $taxPercent = (float) ($payment['tax_percent'] ?? $settings->tax_percent);
        $servicePercent = (float) ($payment['service_charge_percent'] ?? $settings->service_charge_percent);

        $taxAmount = round($taxable * $taxPercent / 100, 2);
        $serviceCharge = round($taxable * $servicePercent / 100, 2);
        $total = round($taxable + $taxAmount + $serviceCharge, 2);

        return [
            'discount_amount' => $discount,
            'tax_amount' => $taxAmount,
            'service_charge_amount' => $serviceCharge,
            'total' => $total,
        ];
    }

    private function generateInvoiceNumber(): string
    {
        $prefix = 'INV-' . now()->format('Ymd');
        $last = Transactions::query()
            ->where('invoice_number', 'like', "{$prefix}-%")
            ->orderByDesc('invoice_number')
            ->lockForUpdate()
            ->value('invoice_number');

        $sequence = 1;
        if ($last) {
            $sequence = (int) substr($last, -4) + 1;
        }

        return sprintf('%s-%04d', $prefix, $sequence);
    }
}
