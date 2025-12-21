<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Http\Requests\StoreAdRequest;
use App\Http\Requests\UpdateAdRequest;
use Illuminate\Http\Request;

class AdController extends Controller
{


    public function store(StoreAdRequest $request)
    {
        $this->authorize('create', Ad::class);

        $ad = Ad::create($request->validated());

        return response()->json([
            'message' => 'تم إنشاء الإعلان بنجاح',
            'ad' => $ad
        ]);;
    }

    public function update(UpdateAdRequest $request, Ad $ad)
    {
        $this->authorize('update', $ad);

        $ad->update($request->validated());

        return response()->json([
            'message' => 'تم تعديل الإعلان بنجاح',
            'ad' => $ad
        ]);
    }


    public function destroy(Ad $ad)
    {
        $this->authorize('delete', $ad);

        $ad->delete();

        return response()->json([
            'message' => 'تم حذف الإعلان بنجاح'
        ]);
    }
}
