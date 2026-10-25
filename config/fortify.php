<?php

// Fortifyで有効にする追加機能を指定するクラスを読み込む
use Laravel\Fortify\Features;

return [

    // ブラウザーのログイン状態を管理する「web」認証を使う
    'guard' => 'web',

    // パスワード再設定で使う設定名を指定する（機能の有効化とは別）
    'passwords' => 'users',

    // ログイン時にユーザーを識別する項目としてメールアドレスを使う
    'username' => 'email',

    // Fortifyがメールアドレスとして扱う入力項目名を指定する
    'email' => 'email',

    // Fortifyが扱うユーザー識別用の入力値を小文字に統一する
    'lowercase_usernames' => true,

    // ログイン・会員登録後の標準の移動先をトップページにする
    'home' => '/',

    // ログアウト後の移動先をログイン画面にする
    'redirects' => [
        'logout' => '/login',
    ],

    // 認証用URLの先頭に追加する共通の文字列は指定しない
    'prefix' => '',

    // 認証用ルートを特定のドメインに限定しない
    'domain' => null,

    // セッション管理やCSRF対策など、ブラウザー向けの処理を適用する
    'middleware' => ['web'],

    // ServiceProviderで定義した試行回数制限の名前を指定する
    'limiters' => [
        'login' => 'login',
        'two-factor' => 'two-factor',
    ],

    // ログイン画面・登録画面を表示するルートを有効にする
    'views' => true,

    // 追加機能はユーザー登録のみ有効にする
    'features' => [
        // ユーザー登録機能を有効にする
        Features::registration(),
    ],
];
