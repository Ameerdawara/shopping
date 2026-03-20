<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AddImageController extends Controller
{

public function uploadQR(Request $request)
{

    $request->validate([
        'shamcash_qr' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        'usdt_qr'     => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    ]);

    $data = [];
    $disk = Storage::disk('public');

    
    $filesToUpload = [
        'shamcash_qr' => 'shamcash.jpg',
        'usdt_qr'     => 'usdt.jpg',
    ];

    foreach ($filesToUpload as $inputName => $fileName) {
        if ($request->hasFile($inputName)) {
            $path = "qr/{$fileName}";

            // حذف القديم إذا كان موجوداً
            if ($disk->exists($path)) {
                $disk->delete($path);
            }

            // تخزين الملف الجديد
            // ملاحظة: storeAs هنا تتعامل مع المجلد داخل القرص المختار مباشرة
            $request->file($inputName)->storeAs('qr', $fileName, 'public');

            // تجهيز الرابط للعرض
            $data[$inputName] = asset("storage/{$path}");
        }
    }

    // 3. الرد
    return response()->json([
        'status'  => true,
        'message' => 'تم رفع وتحديث الصور بنجاح',
        'urls'    => $data
    ]);
}
}
