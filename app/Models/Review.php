<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
// 対象書籍との関連付けを表す型を読み込む
use Illuminate\Database\Eloquent\Relations\BelongsTo;
// 多対多の関連付けを表す型を読み込む
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// reviewsテーブルのレビューデータを扱う
class Review extends Model
{
    // ダミーデータ作成用のFactoryを利用可能にする
    use HasFactory;

    // まとめて登録・更新できる列を指定する
    protected $fillable = [
        'user_id', // 投稿者のユーザーID
        'book_id', // 対象の書籍ID
        'rating',  // 評価
        'comment', // レビュー本文
    ];

    // レビューと対象の書籍の関連付けを定義する
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    // レビュー投稿したユーザーの関連付けを定義する
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // このレビューにいいねしたユーザーとの関連付けを定義する
    public function likedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'review_likes');
    }
}
