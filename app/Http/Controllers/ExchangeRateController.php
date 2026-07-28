<?php
// app/Http/Controllers/ExchangeRateController.php

namespace App\Http\Controllers;

use App\Models\ExchangeRate;
use Illuminate\Http\Request;

class ExchangeRateController extends Controller
{
    /**
     * عرض سعر الصرف الحالي (Public)
     */
    public function show()
    {
        $latest = ExchangeRate::latest('id')->first();

        return response()->json([
            'rate'       => ExchangeRate::current(),
            'updated_at' => $latest?->updated_at,
        ]);
    }

    /**
     * تحديث سعر الصرف (Admin فقط - محمي بـ middleware admin في الراوت)
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'rate' => 'required|numeric|min:0.0001',
        ]);

        $exchangeRate = ExchangeRate::create([
            'rate'       => $data['rate'],
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'تم تحديث سعر الصرف بنجاح',
            'data'    => $exchangeRate,
        ], 201);
    }

    /**
     * سجل تاريخي لأسعار الصرف (Admin - اختياري)
     */
    public function history()
    {
        return response()->json(
            ExchangeRate::latest('id')->paginate(20)
        );
    }
}
