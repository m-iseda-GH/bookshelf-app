<?php

namespace App\Providers;

// 権限判定の対象となる書籍モデルを読み込む
use App\Models\Book;
// 書籍の操作を許可する条件を定義したPolicyを読み込む
use App\Policies\BookPolicy;
// 認証・権限に関する設定を登録する基本クラスを読み込む
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

// 認証・権限判定に関する設定を登録する
class AuthServiceProvider extends ServiceProvider
{
    /**
     * モデルと、権限判定を担当するPolicyの対応関係。
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // Bookモデルの操作権限をBookPolicyで判定する
        Book::class => BookPolicy::class,
    ];

    // Laravel起動時に行う追加処理を定義する
    public function boot(): void
    {
        // 現在は追加の処理なし
    }
}
