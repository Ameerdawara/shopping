<?php

use App\Models\Ad;
use App\Models\Category;
use App\Models\ProductImage;
use App\Services\ImageOptimizer;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| ضغط الصور القديمة الموجودة مسبقاً (منتجات / تصنيفات / إعلانات)
|--------------------------------------------------------------------------
| php artisan images:optimize            ← معاينة فقط (لا يغيّر شيئاً)
| php artisan images:optimize --apply    ← تنفيذ فعلي (يحوّل لـ WebP ويحدّث قاعدة البيانات)
| الخيار --keep-original يُبقي الملفات القديمة بدل حذفها (للأمان).
*/
Artisan::command(
    'images:optimize {--apply : تنفيذ فعلي، وبدونه تكون معاينة فقط} {--keep-original : عدم حذف الملفات القديمة} {--quality=80 : جودة WebP}',
    function () {
        $apply   = (bool) $this->option('apply');
        $keepOld = (bool) $this->option('keep-original');
        $quality = max(1, min(100, (int) $this->option('quality')));

        $targets = [
            'product_images' => [ProductImage::class, 1280],
            'categories'     => [Category::class, 800],
            'ads'            => [Ad::class, 1600],
        ];

        $this->info($apply ? 'وضع التنفيذ الفعلي' : 'وضع المعاينة فقط (أضف --apply للتنفيذ)');

        $stats     = ['optimized' => 0, 'skipped' => 0, 'errors' => 0, 'saved' => 0];
        $converted = []; // مسار قديم => مسار جديد (للصفوف التي تشير لنفس الملف)

        foreach ($targets as $label => [$model, $maxSide]) {
            $this->line('');
            $this->info("== {$label} ==");

            $model::query()
                ->whereNotNull('image')
                ->where('image', '!=', '')
                ->orderBy('id')
                ->chunkById(50, function ($rows) use ($model, $maxSide, $quality, $apply, $keepOld, &$stats, &$converted) {
                    foreach ($rows as $row) {
                        $rel = ImageOptimizer::normalizePath($row->image);

                        if (! $rel) {
                            $stats['skipped']++;
                            continue;
                        }

                        // صف آخر يشير لنفس الملف الذي حوّلناه قبل قليل
                        if (isset($converted[$rel])) {
                            if ($apply) {
                                $model::query()->whereKey($row->getKey())->update(['image' => $converted[$rel]]);
                            }
                            continue;
                        }

                        $res = ImageOptimizer::reoptimize($rel, $maxSide, $quality, $apply);

                        if ($res['status'] === 'optimized') {
                            $saved = $res['old_size'] - $res['new_size'];
                            $stats['optimized']++;
                            $stats['saved'] += $saved;

                            $this->line(sprintf(
                                '  ✔ #%s %s  %s KB → %s KB',
                                $row->getKey(),
                                $rel,
                                number_format($res['old_size'] / 1024, 0),
                                number_format($res['new_size'] / 1024, 0)
                            ));

                            $converted[$rel] = $res['new_path'];

                            if ($apply && $res['new_path'] !== $rel) {
                                $model::query()->whereKey($row->getKey())->update(['image' => $res['new_path']]);

                                if (! $keepOld) {
                                    ImageOptimizer::delete($rel);
                                }
                            }
                        } elseif ($res['status'] === 'error' || $res['status'] === 'invalid') {
                            $stats['errors']++;
                            $this->warn("  ✖ #{$row->getKey()} {$rel}: " . ($res['reason'] ?? ''));
                        } else {
                            $stats['skipped']++;
                        }
                    }
                });
        }

        $this->line('');
        $this->info(sprintf(
            'تم ضغط %d صورة، تخطي %d، أخطاء %d. %s: %s MB',
            $stats['optimized'],
            $stats['skipped'],
            $stats['errors'],
            $apply ? 'المساحة الموفّرة' : 'المساحة التي سيتم توفيرها',
            number_format($stats['saved'] / 1048576, 1)
        ));
    }
)->purpose('Compress existing product/category/ad images to WebP and update the database');
