<?php

namespace App\Policies;

// 操作対象の書籍を扱うモデルを読み込む
use App\Models\Book;
// 操作するユーザーを扱うモデルを読み込む
use App\Models\User;

// 書籍の操作を許可する条件を定義する
class BookPolicy
{
    // 書籍の登録者本人に、編集画面の表示と更新を許可する
    public function update(User $user, Book $book): bool
    {
        // 操作するユーザーのIDと、書籍の登録者IDが一致するかを確認する
        return $user->id === $book->user_id;
    }

    // 書籍の登録者本人に、削除を許可する
    public function delete(User $user, Book $book): bool
    {
        // 操作するユーザーのIDと、書籍の登録者IDが一致するかを確認する
        return $user->id === $book->user_id;
    }
}
