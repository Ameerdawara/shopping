<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Http\Requests\StoreAdRequest;
use App\Http\Requests\UpdateAdRequest;
use Illuminate\Http\Request;
use App\Services\ImageOptimizer;

class AdController extends Controller
{
    public function index()
    {
        $ads = Ad::where('active', true)->latest()->get();

        return response()->json([
            'ads' => $ads
        ]);
    }



    public function store(StoreAdRequest $request)
    {
        $this->authorize('create', Ad::class);

        $data = [];

        $data['title'] = $request->title;
        $data['description'] = $request->description;

        if ($request->hasFile('image')) {
            $data['image'] = ImageOptimizer::store($request->file('image'), 'ads', 1600, 80);
        }

        $ad = Ad::create($data);

        return response()->json([
            'message' => 'تم إنشاء الإعلان بنجاح',
            'ad' => $ad
        ]);
    }


    public function update(UpdateAdRequest $request, Ad $ad)
    {
        $this->authorize('update', $ad);

        $data = $request->validated();
        unset($data['image']);

        $oldImage = null;

        if ($request->hasFile('image')) {
            $oldImage = $ad->image;
            $data['image'] = ImageOptimizer::store($request->file('image'), 'ads', 1600, 80);
        }

        $ad->update($data);

        if ($oldImage) {
            ImageOptimizer::delete($oldImage);
        }

        return response()->json([
            'message' => 'تم تعديل الإعلان بنجاح',
            'ad' => $ad
        ]);
    }


    public function destroy(Ad $ad)
    {
        $this->authorize('delete', $ad);

        $oldImage = $ad->image;

        $ad->delete();

        ImageOptimizer::delete($oldImage);

        return response()->json([
            'message' => 'تم حذف الإعلان بنجاح'
        ]);
    }
}
