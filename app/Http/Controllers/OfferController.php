<?php

namespace App\Http\Controllers;
use app\Models\Offer;
use Illuminate\Http\Request;

class OfferController extends Controller
{    
    
    //  عرض جميع العروض
    public function index()
    {
        return response()->json(
            Offer::with('product')->get()
        );
    }
    // عرض عرض واحد
    public function show($id)
    {
        $offer = Offer::with('product')->findOrFail($id);
        return response()->json($offer);
    }

    //create offer 
     public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'discount_price' => 'nullable|numeric|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'boolean',
        ]);
        $offer = Offer::create($data);

        return response()->json($offer, 201);
    }
  

    //  تحديث العرض
    public function update(Request $request, $id)
    {
        $offer = Offer::findOrFail($id);

        $data = $request->validate([
            'product_id' => 'sometimes|exists:products,id',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'discount_price' => 'nullable|numeric|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'boolean',
        ]);

        $offer->update($data);

        return response()->json($offer);
    }

    
    //  حذف العرض
    public function destroy($id)
    {
        Offer::findOrFail($id)->delete();

        return response()->json([
            'message' => 'تم حذف العرض بنجاح'
        ]);
    }
}


    

