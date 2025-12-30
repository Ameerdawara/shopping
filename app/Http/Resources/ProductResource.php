<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
{
    $price = $this->price;
    $finalPrice = $price;
    $discountPercentage = null;

    if ($this->activeOffer) {
        if ($this->activeOffer->discount_percentage) {
            $discountPercentage = $this->activeOffer->discount_percentage;
            $finalPrice = round($price * (1 - $discountPercentage / 100));
        } elseif ($this->activeOffer->discount_price) {
            $finalPrice = $this->activeOffer->discount_price;
        }
    }

    return [
        'id' => $this->id,
        'name' => $this->name,
        'description' => $this->description,
        'price' => $price,
        'final_price' => $finalPrice,
        'discount_percentage' => $discountPercentage,
        'buyCount' => $this->buyCount,
        'category' => $this->category,
        'brand' => $this->brand,

        'image_url' => $this->images->first()
            ? asset('storage/' . $this->images->first()->image)
            : null,

        'images' => $this->images->map(fn ($img) => [
            'id' => $img->id,
            'color' => $img->color,
            'url' => asset('storage/' . $img->image),
        ]),

        'sizes' => $this->sizes,
        'created_at' => $this->created_at,
    ];
}
}
