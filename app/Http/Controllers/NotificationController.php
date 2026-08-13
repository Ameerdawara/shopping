<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * GET /notifications
     * يرجع إشعارات المستخدم الحالي مع عدد غير المقروء
     */
    public function index(Request $request)
    {
        $notifications = Notification::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $notifications->getCollection()->transform(fn ($n) => $this->formatNotification($n));

        return response()->json([
            'data'         => $notifications->items(),
            'unread_count' => Notification::where('user_id', Auth::id())->where('is_read', false)->count(),
            'meta'         => [
                'current_page' => $notifications->currentPage(),
                'last_page'    => $notifications->lastPage(),
                'total'        => $notifications->total(),
            ],
        ]);
    }

    /**
     * GET /notifications/unread-count
     * مسار خفيف لتحديث الشارة (badge) دون تحميل القائمة كاملة
     */
    public function unreadCount()
    {
        return response()->json([
            'unread_count' => Notification::where('user_id', Auth::id())->where('is_read', false)->count(),
        ]);
    }

    public function show(Notification $notification)
    {
        $this->authorizeOwner($notification);
        return response()->json($this->formatNotification($notification));
    }

    /**
     * PUT /notifications/{notification}/read
     */
    public function markRead(Notification $notification)
    {
        $this->authorizeOwner($notification);
        $notification->update(['is_read' => true]);

        return response()->json([
            'message' => 'تم التعليم كمقروء',
            'data'    => $this->formatNotification($notification->fresh()),
        ]);
    }

    /**
     * POST /notifications/mark-all-read
     */
    public function markAllRead()
    {
        Notification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'تم تعليم كل الإشعارات كمقروءة']);
    }

    public function destroy(Notification $notification)
    {
        $this->authorizeOwner($notification);
        $notification->delete();

        return response()->json(['message' => 'تم حذف الإشعار']);
    }

    private function authorizeOwner(Notification $notification): void
    {
        if ($notification->user_id !== Auth::id()) {
            abort(403, 'غير مصرح لك بالوصول لهذا الإشعار');
        }
    }

    /**
     * نخزن الإشعار في عمود content كـ JSON (title/message/type/order_id/link)
     * حتى لا نحتاج تعديل قاعدة البيانات، ونفكّه هنا لعرضه بشكل منظم للفرونت.
     */
    private function formatNotification(Notification $n): array
    {
        $decoded = null;
        if (is_string($n->content)) {
            $decoded = json_decode($n->content, true);
        } elseif (is_array($n->content)) {
            $decoded = $n->content;
        }

        return [
            'id'         => $n->id,
            'is_read'    => (bool) $n->is_read,
            'title'      => $decoded['title'] ?? 'إشعار',
            'message'    => $decoded['message'] ?? (is_string($n->content) ? $n->content : ''),
            'type'       => $decoded['type'] ?? 'general',
            'order_id'   => $decoded['order_id'] ?? null,
            'link'       => $decoded['link'] ?? null,
            'created_at' => $n->created_at,
        ];
    }

    /* =========================================================
     |  دوال ساكنة (Helpers) لاستدعائها من أي Controller آخر
     |  مثال: NotificationController::notifyAdmins(...)
     * ========================================================= */

    public static function notifyUser(
        int $userId,
        string $title,
        string $message,
        string $type = 'general',
        ?int $orderId = null,
        ?string $link = null
    ): Notification {
        return Notification::create([
            'user_id' => $userId,
            'is_read' => false,
            'content' => json_encode([
                'title'    => $title,
                'message'  => $message,
                'type'     => $type,
                'order_id' => $orderId,
                'link'     => $link,
            ], JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * إشعار كل حسابات الأدمن دفعة واحدة (يُستخدم عند إنشاء طلب جديد).
     * ملاحظة: يفترض وجود عمود `role` في جدول users بقيمة 'admin'.
     * إن كان اسم العمود مختلفاً عندك عدّل السطر أدناه فقط.
     */
    public static function notifyAdmins(
        string $title,
        string $message,
        string $type = 'order',
        ?int $orderId = null,
        ?string $link = null
    ): void {
        $adminIds = User::where('role', 'admin')->pluck('id');

        foreach ($adminIds as $adminId) {
            static::notifyUser($adminId, $title, $message, $type, $orderId, $link);
        }
    }
}
