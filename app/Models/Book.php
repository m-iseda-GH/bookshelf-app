<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
// 登録者との関連付けを表す型を読み込む
use Illuminate\Database\Eloquent\Relations\BelongsTo;
// 複数のレビューとの関連付けを表す型を読み込む
use Illuminate\Database\Eloquent\Relations\HasMany;
// 中間テーブルを使う関連付けの型を読み込む
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// booksテーブルの書籍データを扱う
class Book extends Model
{
    // Factory（ダミーデータを作る仕組み）を利用可能にする
    use HasFactory;
    // まとめて登録・更新できる列を指定する
    protected $fillable = [
        'user_id',        // 登録者のユーザーID
        'title',          // 書名
        'author',         // 著者名
        'isbn',           // ISBN
        'published_date', // 出版日
        'description',    // 書籍の説明
        'image_url',      // 画像URL
    ];
    // 出版日をPHP側で日付として扱う
    protected $casts = [
        'published_date' => 'date',
    ];
    // 書籍と登録したユーザーの関連付けを定義する
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    // 書籍と投稿されたレビュー一覧の関連付けを定義する
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
    // book_genreを介して書籍とジャンルを関連付ける
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class);
    }
    // favoritesを介して書籍とお気に入り登録したユーザーを関連付ける
    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites');
    }
}
