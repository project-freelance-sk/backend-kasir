<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShopSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopSettingController extends Controller
{
    /** Admin: full settings */
    public function show(): JsonResponse
    {
        return response()->json(['data' => ShopSetting::current()]);
    }

    /** Kasir: hanya field yang dibutuhkan POS/struk */
    public function pos(): JsonResponse
    {
        $settings = ShopSetting::current();

        return response()->json([
            'data' => [
                'shop_name' => $settings->shop_name,
                'address' => $settings->address,
                'receipt_footer' => $settings->receipt_footer,
                'tax_percent' => (float) $settings->tax_percent,
                'service_charge_percent' => (float) $settings->service_charge_percent,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shop_name' => ['sometimes', 'required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'receipt_footer' => ['nullable', 'string', 'max:500'],
            'tax_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'service_charge_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ]);

        $settings = ShopSetting::current();
        $settings->update($data);

        return response()->json([
            'message' => 'Pengaturan toko berhasil diperbarui.',
            'data' => $settings->fresh(),
        ]);
    }
}
