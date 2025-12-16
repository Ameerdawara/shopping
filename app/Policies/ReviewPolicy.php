<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    public function viewAny(User $user)
    {
        return true; // أي مستخدم مسجل يمكنه مشاهدة التقييمات
    }

    public function view(User $user, Review $review)
    {
        return true; // كل التقييمات يمكن مشاهدتها
    }

    public function create(User $user)
    {
        return true; // أي مستخدم مسجل يمكنه إنشاء تقييم
    }

    public function update(User $user, Review $review)
    {
        return $user->is_admin || $user->id === $review->user_id;
    }

    public function delete(User $user, Review $review)
    {
        return $user->is_admin || $user->id === $review->user_id;
    }
}
