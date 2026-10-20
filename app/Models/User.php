<?php

namespace App\Models;

// Factory（テスト用データを作る仕組み）を利用する機能を読み込む
use Illuminate\Database\Eloquent\Factories\HasFactory;
// 中間テーブルを使うお気に入り・いいねの関連付けを表す型を読み込む
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
// ユーザーが登録した複数の書籍・レビューとの関連付けを表す型を読み込む
use Illuminate\Database\Eloquent\Relations\HasMany;
// 認証用のUserクラスをAuthenticatableという別名で読み込む
use Illuminate\Foundation\Auth\User as Authenticatable;
// ユーザーへの通知を扱う機能を読み込む
use Illuminate\Notifications\Notifiable;
// SanctumのAPIトークンを扱う機能を読み込む
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    // ユーザーと登録した書籍一覧の関連付けを定義する
    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    // ユーザーと投稿したレビュー一覧の関連付けを定義する
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    // favoritesを介してユーザーとお気に入り書籍を関連付ける
    public function favoriteBooks(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'favorites');
    }

    // ユーザーがいいねしたレビューとの関連付けを定義する
    public function likedReviews(): BelongsToMany
    {
        return $this->belongsToMany(Review::class, 'review_likes');
    }
}
