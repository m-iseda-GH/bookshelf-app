<?php

namespace Tests\Feature\Auth;

// ログイン確認に使うユーザーを作成する
use App\Models\User;
// テスト用のテーブル準備と、テスト後のデータ変更の取り消しを行う
use Illuminate\Foundation\Testing\RefreshDatabase;
// データベースの接続先を確認する機能を読み込む
use Illuminate\Support\Facades\DB;
// パスワードをハッシュ化する機能を読み込む
use Illuminate\Support\Facades\Hash;
// Laravelのテスト用基本クラスを読み込む
use Tests\TestCase;

// ログイン機能の動作を確認する
class LoginTest extends TestCase
{
    use RefreshDatabase;

    // データベースの初期化前に、接続先がテスト用であることを確認する
    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame(
            'testing',
            DB::connection()->getDatabaseName(),
            '接続先がtestingではないため、テストを停止しました。'
        );
    }

    // メールアドレスとパスワードが未入力なら、ログインできないことを確認する
    public function testLoginRequiresEmailAndPassword(): void
    {
        // ログイン画面から、すべて未入力の状態で送信する
        $response = $this->from('/login')->post('/login', []);

        // ログイン画面へ戻ることを確認する
        $response->assertRedirect('/login');

        // 各項目の日本語エラーメッセージを確認する
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください。',
            'password' => 'パスワードを入力してください。',
        ]);

        // 未ログイン状態のままであることを確認する
        $this->assertGuest();
    }

    // 正しいメールアドレスとパスワードでログインできることを確認する
    public function testUserCanLoginWithCorrectCredentials(): void
    {
        // テスト用DBに、ログイン確認用のユーザーを作成する
        $user = User::create([
            'name' => 'ログインテストユーザー',
            'email' => 'login-test@example.com',
            'password' => Hash::make('password123'),
        ]);

        // 作成したユーザーの認証情報でログインを試みる
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        // 入力チェックや認証のエラーがないことを確認する
        $response->assertSessionHasNoErrors();

        // ログイン後の移動先がトップページであることを確認する
        $response->assertRedirect('/');

        // 作成したユーザーでログイン状態になっていることを確認する
        $this->assertAuthenticatedAs($user);
    }

    // パスワードが間違っている場合は、ログインできないことを確認する
    public function testLoginRejectsIncorrectPassword(): void
    {
        // 正しいパスワードを設定したユーザーを用意する
        $user = User::create([
            'name' => 'ログインテストユーザー',
            'email' => 'wrong-password@example.com',
            'password' => Hash::make('password123'),
        ]);

        // 登録済みメールアドレスと、間違ったパスワードで送信する
        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password123',
        ]);

        // ログイン画面へ戻ることを確認する
        $response->assertRedirect('/login');

        // 認証失敗時の日本語メッセージを確認する
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスまたはパスワードが正しくありません。',
        ]);

        // 未ログイン状態のままであることを確認する
        $this->assertGuest();
    }

    // 未登録のメールアドレスでは、ログインできないことを確認する
    public function testLoginRejectsUnregisteredEmail(): void
    {
        // ユーザーを作成せず、未登録のメールアドレスで送信する
        $response = $this->from('/login')->post('/login', [
            'email' => 'not-registered@example.com',
            'password' => 'password123',
        ]);

        // ログイン画面へ戻ることを確認する
        $response->assertRedirect('/login');

        // 認証失敗時の日本語メッセージを確認する
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスまたはパスワードが正しくありません。',
        ]);

        // ログイン操作でユーザーが新規作成されていないことを確認する
        $this->assertDatabaseCount('users', 0);

        // 未ログイン状態のままであることを確認する
        $this->assertGuest();
    }

    // ログイン済みで認証画面を開くと、トップページへ移動することを確認する
    public function testAuthenticatedUserIsRedirectedFromAuthenticationPages(): void
    {
        // ログイン状態にするためのテスト用ユーザーを登録する
        $user = User::create([
            'name' => 'ログイン済みユーザー',
            'email' => 'authenticated-user@example.com',
            'password' => Hash::make('password123'),
        ]);

        // このユーザーでログインした状態にする
        $this->actingAs($user);

        // ログイン画面を開くと、トップページへ移動することを確認する
        $this->get('/login')->assertRedirect('/');

        // 会員登録画面を開くと、トップページへ移動することを確認する
        $this->get('/register')->assertRedirect('/');
    }

    // ログアウトすると、ログイン状態が解除されることを確認する
    public function testUserCanLogout(): void
    {
        // ログアウトの確認に使うテスト用ユーザーを登録する
        $user = User::create([
            'name' => 'ログアウトテストユーザー',
            'email' => 'logout-test@example.com',
            'password' => Hash::make('password123'),
        ]);

        // このユーザーでログインした状態にする
        $this->actingAs($user);

        // ログアウトのリクエストを送信する
        $response = $this->post('/logout');

        // ログアウト後にログイン画面へ移動することを確認する
        $response->assertRedirect('/login');

        // 未ログイン状態になったことを確認する
        $this->assertGuest();
    }

    // 基本編で使わない認証機能のURLが無効であることを確認する
    public function testAdditionalAuthenticationRoutesAreUnavailable(): void
    {
        // パスワード再設定メールの送信先が存在しないことを確認する
        $this->post('/forgot-password')->assertNotFound();

        // パスワード再設定の送信先が存在しないことを確認する
        $this->post('/reset-password')->assertNotFound();

        // プロフィール変更の送信先が存在しないことを確認する
        $this->put('/user/profile-information')->assertNotFound();

        // パスワード変更の送信先が存在しないことを確認する
        $this->put('/user/password')->assertNotFound();

        // 二段階認証を有効化する送信先が存在しないことを確認する
        $this->post('/user/two-factor-authentication')->assertNotFound();
    }
}
