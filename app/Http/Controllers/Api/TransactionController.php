<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Toppings;
use App\Models\Transactions;
use App\Models\User;
use App\Rules\ExistsModel;
use App\Services\TransactionService;
use App\Models\ShopSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    public function __construct(private TransactionService $transactionService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'invoice_number' => ['nullable', 'string', 'max:50'],
            'payment_method' => ['nullable', 'in:cash,qris'],
            'payment_status' => ['nullable', 'in:pending,paid,voided'],
            'cashier_id' => ['nullable', 'uuid', new ExistsModel(User::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Transactions::query()
            ->with($this->transactionService->defaultRelations())
            ->latest();

        if ($request->user()->role === 'cashier') {
            $query->where('cashier_id', $request->user()->id);
        } elseif ($request->filled('cashier_id')) {
            $query->where('cashier_id', $validated['cashier_id']);
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->date('from')->startOfDay());
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->date('to')->endOfDay());
        }

        if ($request->filled('invoice_number')) {
            $query->where('invoice_number', 'like', '%' . $validated['invoice_number'] . '%');
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $validated['payment_method']);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $validated['payment_status']);
        }

        $transactions = $query->paginate($request->integer('per_page', 15));

        return response()->json([
            'data' => $transactions->items(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'uuid',
                Rule::exists('products', 'id'),
            ],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:999'],
            'items.*.size' => ['nullable', 'in:small,large'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
            'items.*.topping_ids' => ['nullable', 'array'],
            'items.*.topping_ids.*' => [
                'uuid',
                Rule::exists('toppings', 'id')->where('is_active', true),
            ],
            'payment_method' => ['required', 'in:cash,qris'],
            'paid_amount' => [
                Rule::requiredIf(fn() => $request->input('payment_method') === 'cash'),
                'nullable',
                'numeric',
                'min:0',
            ],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'service_charge_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'order_type' => ['nullable', 'in:dine_in,takeaway'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);

        // Validasi topping harus milik produk yang bersangkutan
        $this->validateItemToppings($validated['items']);

        $payment = array_filter([
            'payment_method' => $validated['payment_method'],
            'paid_amount' => $validated['paid_amount'] ?? null,
            'discount_amount' => $validated['discount_amount'] ?? null,
            'tax_percent' => $validated['tax_percent'] ?? null,
            'service_charge_percent' => $validated['service_charge_percent'] ?? null,
            'order_type' => $validated['order_type'] ?? 'dine_in',
            'idempotency_key' => $validated['idempotency_key'] ?? null,
        ], fn($v) => $v !== null);

        $transaction = $this->transactionService->create(
            $request->user(),
            $validated['items'],
            $payment,
        );

        return response()->json([
            'message' => 'Transaksi berhasil dibuat.',
            'data' => $transaction,
        ], 201);
    }

    public function show(Request $request, Transactions $transaction): JsonResponse
    {
        $this->authorizeTransactionAccess($request, $transaction);

        $transaction->load($this->transactionService->defaultRelations());

        return response()->json(['data' => $transaction]);
    }

    public function receipt(Request $request, Transactions $transaction): JsonResponse
    {
        $this->authorizeTransactionAccess($request, $transaction);

        $transaction->load($this->transactionService->defaultRelations());
        $settings = ShopSetting::current();

        return response()->json([
            'data' => [
                'shop' => [
                    'name' => $settings->shop_name,
                    'address' => $settings->address,
                    'footer' => $settings->receipt_footer,
                ],
                'transaction' => $transaction,
                'lines' => $transaction->details->map(fn($detail) => [
                    'product_name' => $detail->product_name,
                    'size' => $detail->size ?? null,
                    'qty' => $detail->qty,
                    'price' => $detail->price,
                    'subtotal' => $detail->subtotal,
                    'notes' => $detail->notes,
                    'toppings' => $detail->detailToppings->map(fn($dt) => [
                        'name' => $dt->topping?->name,
                        'price' => $dt->price,
                    ]),
                ]),
            ],
        ]);
    }

    public function void(Request $request, Transactions $transaction): JsonResponse
    {
        $this->authorizeTransactionAccess($request, $transaction);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if ($transaction->payment_status === 'voided') {
            return response()->json([
                'message' => 'Transaksi sudah dibatalkan sebelumnya.',
            ], 422);
        }

        $transaction = $this->transactionService->void(
            $request->user(),
            $transaction,
            $validated['reason'],
        );

        return response()->json([
            'message' => 'Transaksi berhasil dibatalkan.',
            'data' => $transaction,
        ]);
    }

    private function authorizeTransactionAccess(Request $request, Transactions $transaction): void
    {
        if ($request->user()->role === 'cashier' && $transaction->cashier_id !== $request->user()->id) {
            abort(403, 'Forbidden.');
        }
    }

    /**
     * Pastikan topping_ids yang dikirim benar-benar terhubung ke produk masing-masing.
     */
    private function validateItemToppings(array $items): void
    {
        $productIds = collect($items)->pluck('product_id')->unique();

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->with('toppings:id')
            ->get()
            ->keyBy('id');

        foreach ($items as $index => $item) {
            $toppingIds = $item['topping_ids'] ?? [];
            if (empty($toppingIds)) {
                continue;
            }

            $allowedToppingIds = $products[$item['product_id']]
                ->toppings
                ->pluck('id')
                ->all();

            $invalid = array_diff($toppingIds, $allowedToppingIds);
            if (! empty($invalid)) {
                abort(422, "Topping pada item #{$index} tidak valid untuk produk tersebut.");
            }
        }
    }
}
