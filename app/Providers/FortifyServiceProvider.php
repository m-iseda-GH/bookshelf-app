<?php

namespace App\Providers;

// ユーザー登録の処理を読み込む
use App\Actions\Fortify\CreateNewUser;
// パスワード再設定の処理を読み込む
use App\Actions\Fortify\ResetUserPassword;
// パスワード変更の処理を読み込む
use App\Actions\Fortify\UpdateUserPassword;
// ユーザー情報変更の処理を読み込む
use App\Actions\Fortify\UpdateUserProfileInformation;
// 日本語メッセージを定義した、アプリ側のLoginRequestを読み込む
use App\Http\Requests\LoginRequest;
// 一定時間内の試行回数を制限するクラスを読み込む
use Illuminate\Cache\RateLimiting\Limit;
// 送信された入力内容や接続元情報を取得するクラスを読み込む
use Illuminate\Http\Request;
// 試行回数の制限を登録する機能を読み込む
use Illuminate\Support\Facades\RateLimiter;
// Laravel起動時に設定を準備するための基本クラスを読み込む
use Illuminate\Support\ServiceProvider;
// 文字列を変換する機能を読み込む
use Illuminate\Support\Str;
// 二段階認証が必要な場合の画面遷移を扱う処理を読み込む
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
// Fortifyの認証処理や画面を設定する機能を読み込む
use Laravel\Fortify\Fortify;
// Fortify標準のLoginRequestを、区別できる別名で読み込む
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

// Fortifyで利用する認証処理・画面・試行回数制限を設定する
class FortifyServiceProvider extends ServiceProvider
{
    // Laravelの起動時に、使用するクラスの対応関係を登録する
    public function register(): void
    {
        // Fortify標準のLoginRequestが必要なときに、アプリ側のクラスを使う
        $this->app->bind(FortifyLoginRequest::class, LoginRequest::class);
    }

    // Laravelの起動時に、Fortifyの設定を行う
    public function boot(): void
    {
        // ログイン画面として、用意済みのBladeファイルを指定する
        Fortify::loginView(function () {
            return view('auth.login');
        });

        // ユーザー登録画面として、用意済みのBladeファイルを指定する
        Fortify::registerView(function () {
            return view('auth.register');
        });

        // ユーザー登録時に呼び出す処理を指定する
        Fortify::createUsersUsing(CreateNewUser::class);

        // 以下は機能を有効にした場合に使う処理の指定であり、有効化自体は行わない
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        // ログインの試行回数制限を「login」という名前で登録する
        RateLimiter::for('login', function (Request $request) {
            // メールアドレスを小文字化・文字変換し、接続元IPと組み合わせる
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            // 同じメールアドレスとIPの組み合わせで、1分間に5回まで試行できる
            return Limit::perMinute(5)->by($throttleKey);
        });

        // 二段階認証用の制限を登録する。これだけでは二段階認証は有効にならない
        RateLimiter::for('two-factor', function (Request $request) {
            // 認証途中のユーザーIDごとに、1分間に5回まで試行できる
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
