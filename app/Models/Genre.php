<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
// 中間テーブルを使う関連付けの型を読み込む
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// genresテーブルのジャンルデータを扱う
class Genre extends Model
{
    // ダミーデータ作成用のFactoryを利用可能にする
    use HasFactory;

    // ジャンル名をまとめて登録・更新可能にする
    protected $fillable = [
        'name', // ジャンル名
    ];

    // book_genreを介してジャンルと書籍を関連付ける
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class);
    }
}
