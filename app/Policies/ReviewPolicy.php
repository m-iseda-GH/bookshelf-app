<?php

namespace App\Policies;

// 操作対象のレビューを扱うモデルを読み込む
use App\Models\Review;
// 操作するユーザーを扱うモデルを読み込む
use App\Models\User;

// レビューの操作を許可する条件を定義する
class ReviewPolicy
{
    // 投稿者本人に、編集画面の表示と更新を許可する
    public function update(User $user, Review $review): bool
    {
        // 操作するユーザーのIDと、レビュー投稿者のIDが一致するかを確認する
        return $user->id === $review->user_id;
    }

    // 投稿者本人に、レビューの削除を許可する
    public function delete(User $user, Review $review): bool
    {
        // 操作するユーザーのIDと、レビュー投稿者のIDが一致するかを確認する
        return $user->id === $review->user_id;
    }
}
