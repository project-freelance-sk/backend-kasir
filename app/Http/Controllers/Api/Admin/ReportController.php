<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transactions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function sales(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = ($request->date('from') ?? now()->startOfMonth())->startOfDay();
        $to = ($request->date('to') ?? now())->endOfDay();

        $baseQuery = Transactions::query()
            ->where('transactions.payment_status', 'paid')
            ->whereBetween('transactions.created_at', [$from, $to]);

        $summary = (clone $baseQuery)
            ->selectRaw('COUNT(*) as total_transactions, COALESCE(SUM(transactions.total), 0) as total_revenue')
            ->first();

        $daily = (clone $baseQuery)
            ->selectRaw('DATE(transactions.created_at) as date, COUNT(*) as transactions, COALESCE(SUM(transactions.total), 0) as revenue')
            ->groupBy(DB::raw('DATE(transactions.created_at)'))
            ->orderBy('date')
            ->get()
            ->map(fn($row) => [
                'date' => $row->date,
                'transactions' => (int) $row->transactions,
                'revenue' => (float) $row->revenue,
            ]);

        $byPaymentMethod = (clone $baseQuery)
            ->selectRaw('transactions.payment_method, COUNT(*) as transactions, COALESCE(SUM(transactions.total), 0) as revenue')
            ->groupBy('transactions.payment_method')
            ->get()
            ->map(fn($row) => [
                'payment_method' => $row->payment_method,
                'transactions' => (int) $row->transactions,
                'revenue' => (float) $row->revenue,
            ]);

        $byCashierRows = (clone $baseQuery)
            ->join('users', 'users.id', '=', 'transactions.cashier_id')
            ->selectRaw('
                users.id as cashier_id,
                users.name as cashier_name,
                COUNT(transactions.id) as transactions,
                COALESCE(SUM(transactions.total), 0) as revenue,
                COALESCE(SUM(CASE WHEN transactions.payment_method = \'cash\' THEN transactions.total ELSE 0 END), 0) as cash_revenue,
                COALESCE(SUM(CASE WHEN transactions.payment_method = \'qris\' THEN transactions.total ELSE 0 END), 0) as qris_revenue
            ')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('revenue')
            ->get();

        $itemsByCashier = DB::table('transaction_details')
            ->join('transactions', 'transactions.id', '=', 'transaction_details.transaction_id')
            ->join('users', 'users.id', '=', 'transactions.cashier_id')
            ->where('transactions.payment_status', 'paid')
            ->whereBetween('transactions.created_at', [$from, $to])
            ->groupBy('users.id')
            ->selectRaw('users.id as cashier_id, COALESCE(SUM(transaction_details.qty), 0) as items_sold')
            ->pluck('items_sold', 'cashier_id');

        $byCashier = $byCashierRows->map(fn($row) => [
            'cashier_name' => $row->cashier_name,
            'transactions' => (int) $row->transactions,
            'revenue' => (float) $row->revenue,
            'items_sold' => (int) ($itemsByCashier[$row->cashier_id] ?? 0),
            'cash_revenue' => (float) $row->cash_revenue,
            'qris_revenue' => (float) $row->qris_revenue,
            'card_revenue' => 0,
        ]);

        $topProducts = DB::table('transaction_details')
            ->join(
                'transactions',
                'transactions.id',
                '=',
                'transaction_details.transaction_id'
            )
            ->where('transactions.payment_status', 'paid')
            ->whereBetween('transactions.created_at', [$from, $to])
            ->selectRaw('
                transaction_details.product_name,
                SUM(transaction_details.qty) as qty_sold,
                SUM(transaction_details.subtotal) as revenue
            ')
            ->groupBy('transaction_details.product_name')
            ->orderByDesc('qty_sold')
            ->limit(10)
            ->get()
            ->map(fn($row) => [
                'product_name' => $row->product_name,
                'qty_sold' => (int) $row->qty_sold,
                'revenue' => (float) $row->revenue,
            ]);

        $voidedCount = Transactions::query()
            ->where('transactions.payment_status', 'voided')
            ->whereBetween('transactions.created_at', [$from, $to])
            ->count();

        return response()->json([
            'data' => [
                'period' => [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                ],

                'summary' => [
                    'total_transactions' => (int) $summary->total_transactions,
                    'total_revenue' => (float) $summary->total_revenue,
                    'average_transaction' => $summary->total_transactions > 0
                        ? round(
                            (float) $summary->total_revenue /
                                (int) $summary->total_transactions,
                            2
                        )
                        : 0,
                    'voided_transactions' => $voidedCount,
                ],

                'daily' => $daily,
                'by_payment_method' => $byPaymentMethod,
                'by_cashier' => $byCashier,
                'top_products' => $topProducts,
            ],
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'payment_status' => ['nullable', 'in:pending,paid,voided'],
            'payment_method' => ['nullable', 'in:cash,qris'],
            'invoice_number' => ['nullable', 'string', 'max:50'],
            'cashier_id' => ['nullable', 'uuid', 'exists:users,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $from = ($request->date('from') ?? now()->startOfMonth())->startOfDay();
        $to = ($request->date('to') ?? now())->endOfDay();
        $paymentStatus = $validated['payment_status'] ?? 'paid';

        $transactions = Transactions::query()
            ->with([
                'cashier:id,name,email',
                'voidedBy:id,name,email',
                'details.product',
                'details.detailToppings.topping',
            ])
            ->whereBetween('transactions.created_at', [$from, $to])
            ->where('transactions.payment_status', $paymentStatus)
            ->when(
                $request->filled('payment_method'),
                fn($q) => $q->where(
                    'transactions.payment_method',
                    $validated['payment_method']
                )
            )
            ->when(
                $request->filled('invoice_number'),
                fn($q) => $q->where(
                    'transactions.invoice_number',
                    'like',
                    '%' . $validated['invoice_number'] . '%'
                )
            )
            ->when(
                $request->filled('cashier_id'),
                fn($q) => $q->where(
                    'transactions.cashier_id',
                    $validated['cashier_id']
                )
            )
            ->latest('transactions.created_at')
            ->paginate($request->integer('per_page', 20));

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
}
