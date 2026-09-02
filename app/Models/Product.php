<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Product extends Model
{ use SoftDeletes;
    protected $fillable =
    [
        'name',
        'description',
        'price',
        'currency',      // العملة التي تم إدخال السعر بها: USD أو SYP
        'buyCount',
        'category',
        'category_id',
        'brand'
    ];
    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }
    public function offer()
    {
        return $this->hasOne(Offer::class);
    }public function category()
{
    return $this->belongsTo(Category::class);
}

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }
    // App\Models\Product.php

    public function sizes()
    {
        return $this->hasMany(ProductSize::class);
    }
    protected $appends = ['image_url', 'price_syp'];

    public function getImageUrlAttribute()
    {
        if ($this->images->count()) {
            return asset('storage/' . $this->images->first()->image);
        }
        return null;
    }

    /**
     * السعر بالليرة السورية دائماً، جاهز للعرض للزبون بغض النظر عن
     * العملة التي أدخلها الأدمن أصلاً (USD أو SYP).
     * - إذا كان المنتج مسعّراً بالدولار: يتم التحويل تلقائياً حسب آخر سعر صرف.
     * - إذا كان مسعّراً بالليرة أصلاً: تُعاد القيمة كما هي.
     */
    public function getPriceSypAttribute()
    {
        if ($this->currency === 'USD') {
            return \App\Models\ExchangeRate::convert((float) $this->price);
        }

        return (float) $this->price;
    }
   protected static function booted()
{
    static::deleting(function ($product) {
        // نحذف العرض والصور فعلياً فقط عند الحذف النهائي (Force Delete)
        // عند الـ Soft Delete (وهو ما يحصل عادةً من destroy()) نُبقي الصور والعرض
        // كما هي، حتى تبقى بيانات المنتج كاملة (بما فيها الصورة) ظاهرة في سجل الطلبات القديمة.
        if ($product->isForceDeleting()) {
            $product->offer()?->delete();
            $product->images()->delete();
        }
    });
}

    public function activeOffer()
    {
        return $this->hasOne(Offer::class)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }
}
