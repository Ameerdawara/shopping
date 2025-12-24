<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'price'       => $this->price,
            'buyCount'    => $this->buyCount,
            'category'    => $this->category,
            'brand'       => $this->brand,

            // 👇 الصورة (الأهم)
            'image_url' => $this->images->first()
                ? asset('storage/' . $this->images->first()->image)
                : null,

            // (اختياري) كل الصور
            'images' => $this->images->map(fn ($img) => [
                'id'    => $img->id,
                'color' => $img->color,
                'url'   => asset('storage/' . $img->image),
            ]),

            'sizes' => $this->sizes,
            'created_at' => $this->created_at,
        ];
    }
}
