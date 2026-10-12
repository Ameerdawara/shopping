<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * ضغط الصور تلقائياً عند الرفع (GD فقط، بدون أي حزمة إضافية).
 *
 * - يصغّر الصورة بحيث لا يتجاوز أطول ضلع $maxSide بكسل (ولا يكبّر الصور الصغيرة).
 * - يحوّلها إلى WebP (أو PNG لصور الـ QR، أو JPEG إذا كان WebP غير مدعوم في السيرفر).
 * - يصحّح اتجاه صور الموبايل (EXIF) ويحذف بيانات EXIF/GPS.
 * - في حال فشل الضغط لأي سبب يحفظ الصورة الأصلية كما هي، فلا يفشل الرفع أبداً بسبب الضغط.
 */
class ImageOptimizer
{
    public const DISK = 'public';

    /** أقصى عدد بكسلات مسموح فك ترميزه (حماية من استهلاك الذاكرة) */
    private const MAX_PIXELS = 50000000;

    /**
     * يضغط الملف المرفوع ويحفظه على القرص public ويرجع المسار النسبي (مثل products/xxxx.webp).
     *
     * @param string $format webp | png | jpeg
     */
    public static function store(
        UploadedFile $file,
        string $dir,
        int $maxSide = 1280,
        int $quality = 80,
        string $format = 'webp'
    ): string {
        try {
            $out  = self::encode($file->getRealPath(), $maxSide, $quality, $format);
            $path = trim($dir, '/') . '/' . Str::uuid() . '.' . $out['ext'];

            if (Storage::disk(self::DISK)->put($path, $out['data'])) {
                return $path;
            }
        } catch (Throwable $e) {
            Log::warning('ImageOptimizer: تم حفظ الصورة الأصلية بدون ضغط. السبب: ' . $e->getMessage());
        }

        $stored = $file->store($dir, self::DISK);

        if (! $stored) {
            throw new RuntimeException('تعذر حفظ الصورة على السيرفر');
        }

        return $stored;
    }

    /**
     * يفك ترميز الصورة ويصغّرها ويعيد ترميزها.
     *
     * @return array{data:string, ext:string, mime:string, width:int, height:int}
     */
    public static function encode(
        string $sourcePath,
        int $maxSide = 1280,
        int $quality = 80,
        string $format = 'webp'
    ): array {
        if (! function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('إضافة GD غير مفعّلة في PHP');
        }

        self::raiseMemoryLimit();

        $info = @getimagesize($sourcePath);

        if (! $info) {
            throw new RuntimeException('الملف ليس صورة صالحة');
        }

        [$width, $height, $type] = $info;

        if ($width < 1 || $height < 1 || ($width * $height) > self::MAX_PIXELS) {
            throw new RuntimeException('أبعاد الصورة غير مدعومة');
        }

        $src = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG  => @imagecreatefrompng($sourcePath),
            IMAGETYPE_GIF  => @imagecreatefromgif($sourcePath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            IMAGETYPE_BMP  => function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($sourcePath) : false,
            default        => false,
        };

        if (! $src) {
            throw new RuntimeException('تعذر قراءة الصورة (صيغة غير مدعومة)');
        }

        // تصحيح اتجاه الصور القادمة من الموبايل
        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif        = @exif_read_data($sourcePath);
            $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
            $angle       = match ($orientation) {
                3       => 180,
                6       => -90,
                8       => 90,
                default => 0,
            };

            if ($angle !== 0) {
                $rotated = @imagerotate($src, $angle, 0);
                if ($rotated) {
                    $src = $rotated;
                }
            }
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);

        $maxSide = max(1, $maxSide);
        $longest = max($srcW, $srcH);

        if ($longest > $maxSide) {
            $ratio = $maxSide / $longest;
            $newW  = max(1, (int) round($srcW * $ratio));
            $newH  = max(1, (int) round($srcH * $ratio));
        } else {
            $newW = $srcW;
            $newH = $srcH;
        }

        $format = strtolower($format);

        if (! in_array($format, ['webp', 'png', 'jpeg'], true)) {
            $format = 'webp';
        }

        if ($format === 'webp' && ! function_exists('imagewebp')) {
            $format = 'jpeg';
        }

        $dst = imagecreatetruecolor($newW, $newH);

        if ($format === 'jpeg') {
            // JPEG لا يدعم الشفافية: نملأ الخلفية بالأبيض
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        } else {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);

        $quality = max(1, min(100, $quality));

        ob_start();
        try {
            $ok = match ($format) {
                'png'   => imagepng($dst, null, 9),
                'jpeg'  => (function () use ($dst, $quality) {
                    imageinterlace($dst, true);
                    return imagejpeg($dst, null, $quality);
                })(),
                default => imagewebp($dst, null, $quality),
            };
        } finally {
            $data = ob_get_clean();
        }

        unset($src, $dst);

        if (! $ok || ! is_string($data) || $data === '') {
            throw new RuntimeException('فشل ترميز الصورة بعد الضغط');
        }

        return [
            'data'   => $data,
            'ext'    => $format === 'jpeg' ? 'jpg' : $format,
            'mime'   => $format === 'jpeg' ? 'image/jpeg' : 'image/' . $format,
            'width'  => $newW,
            'height' => $newH,
        ];
    }

    /**
     * يحوّل قيمة محفوظة في قاعدة البيانات (مسار نسبي أو رابط كامل يحتوي /storage/)
     * إلى مسار نسبي على القرص public، أو null إذا لم يكن ملفاً محلياً.
     */
    public static function normalizePath(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $pos = strpos($value, '/storage/');

        if ($pos !== false) {
            $value = substr($value, $pos + strlen('/storage/'));
        }

        $value = ltrim($value, '/');

        if (str_starts_with($value, 'storage/')) {
            $value = substr($value, strlen('storage/'));
        }

        if ($value === '' || str_contains($value, '..') || preg_match('#^[a-z][a-z0-9+.-]*://#i', $value)) {
            return null;
        }

        return $value;
    }

    /**
     * يحذف ملف صورة من القرص public (آمن: لا يرمي استثناءات).
     *
     * @param array<int,string> $protect مسارات نسبية لا يجوز حذفها
     */
    public static function delete(?string $value, array $protect = []): bool
    {
        $path = self::normalizePath($value);

        if (! $path || in_array($path, $protect, true)) {
            return false;
        }

        try {
            $disk = Storage::disk(self::DISK);

            return $disk->exists($path) ? (bool) $disk->delete($path) : false;
        } catch (Throwable $e) {
            Log::warning('ImageOptimizer: تعذر حذف الملف ' . $path . ' - ' . $e->getMessage());

            return false;
        }
    }

    /**
     * يعيد ضغط صورة موجودة مسبقاً على القرص (للصور القديمة).
     * إن اختلفت الصيغة (مثلاً jpg → webp) يُكتب الملف باسم جديد ويرجع المسار الجديد في new_path،
     * ويقوم المستدعي بتحديث قاعدة البيانات ثم حذف الملف القديم.
     *
     * @return array{status:string, reason?:string, old_size?:int, new_size?:int, new_path?:string}
     */
    public static function reoptimize(
        string $relPath,
        int $maxSide,
        int $quality = 80,
        bool $apply = true,
        int $skipBelowBytes = 358400
    ): array {
        $disk = Storage::disk(self::DISK);

        if (! $disk->exists($relPath)) {
            return ['status' => 'missing', 'reason' => 'الملف غير موجود على القرص'];
        }

        $abs = $disk->path($relPath);

        clearstatcache(true, $abs);

        $oldSize = (int) @filesize($abs);
        $info    = @getimagesize($abs);

        if (! $info) {
            return ['status' => 'invalid', 'reason' => 'ليس ملف صورة'];
        }

        if ($info[2] === IMAGETYPE_GIF) {
            return ['status' => 'skipped', 'reason' => 'GIF (قد يكون متحركاً)'];
        }

        $ext = strtolower(pathinfo($relPath, PATHINFO_EXTENSION));

        if ($ext === 'webp' && $oldSize <= $skipBelowBytes && max($info[0], $info[1]) <= $maxSide) {
            return ['status' => 'skipped', 'reason' => 'مضغوطة مسبقاً'];
        }

        try {
            $out = self::encode($abs, $maxSide, $quality, 'webp');
        } catch (Throwable $e) {
            return ['status' => 'error', 'reason' => $e->getMessage()];
        }

        $newSize = strlen($out['data']);

        if ($newSize >= $oldSize) {
            return ['status' => 'skipped', 'reason' => 'لا يوجد توفير في الحجم'];
        }

        $newPath = $relPath;

        if ($ext !== $out['ext']) {
            $base    = preg_replace('/\.[^.\/]*$/', '', $relPath);
            $newPath = $base . '.' . $out['ext'];

            if ($disk->exists($newPath)) {
                $newPath = $base . '-' . Str::lower(Str::random(6)) . '.' . $out['ext'];
            }
        }

        if ($apply && ! $disk->put($newPath, $out['data'])) {
            return ['status' => 'error', 'reason' => 'تعذر كتابة الملف الجديد'];
        }

        return [
            'status'   => 'optimized',
            'old_size' => $oldSize,
            'new_size' => $newSize,
            'new_path' => $newPath,
        ];
    }

    private static function raiseMemoryLimit(): void
    {
        $limit = ini_get('memory_limit');

        if ($limit === false || $limit === '' || $limit === '-1') {
            return;
        }

        $bytes = self::toBytes($limit);

        if ($bytes > 0 && $bytes < 512 * 1024 * 1024) {
            @ini_set('memory_limit', '512M');
        }
    }

    private static function toBytes(string $value): int
    {
        $value = trim($value);
        $unit  = strtolower(substr($value, -1));
        $num   = (int) $value;

        return match ($unit) {
            'g'     => $num * 1024 * 1024 * 1024,
            'm'     => $num * 1024 * 1024,
            'k'     => $num * 1024,
            default => $num,
        };
    }
}
