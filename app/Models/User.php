<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
// 複数の登録書籍との関連付けを表す型を読み込む
use Illuminate\Database\Eloquent\Relations\HasMany;
// 中間テーブルを使う関連付けの型を読み込む
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
