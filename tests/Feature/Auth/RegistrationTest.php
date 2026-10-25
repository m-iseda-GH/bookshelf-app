<?php

namespace Tests\Feature\Auth;

// 登録されたユーザーをデータベースから取得する
use App\Models\User;
// テスト用のテーブル準備と、テスト後のデータ変更の取り消しを行う
use Illuminate\Foundation\Testing\RefreshDatabase;
// データベースの接続先を確認する機能を読み込む
use Illuminate\Support\Facades\DB;
// 保存されたパスワードを照合する機能を読み込む
use Illuminate\Support\Facades\Hash;
// Laravelのテスト用基本クラスを読み込む
use Tests\TestCase;

// 会員登録機能の動作を確認する
class RegistrationTest extends TestCase
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

    // 正しい入力でユーザーが登録され、ログイン状態になることを確認する
    public function testUserCanRegister(): void
    {
        // 登録フォームの送信に相当するリクエストを送る
        $response = $this->post('/register', [
            'name' => '登録テストユーザー',
            'email' => 'registration-test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // 入力チェックのエラーがないことを確認する
        $response->assertSessionHasNoErrors();

        // 登録後の移動先がトップページであることを確認する
        $response->assertRedirect('/');

        // 登録されたユーザーを取得する。見つからなければテストは失敗する
        $user = User::where('email', 'registration-test@example.com')->firstOrFail();

        // 名前が正しく保存されていることを確認する
        $this->assertSame('登録テストユーザー', $user->name);

        // 保存されたハッシュ値が、入力したパスワードと一致することを確認する
        $this->assertTrue(Hash::check('password123', $user->password));

        // 登録したユーザーでログイン状態になっていることを確認する
        $this->assertAuthenticatedAs($user);
    }

    // 必須項目が未入力なら、登録されないことを確認する
    public function testRegistrationRequiresNameEmailAndPassword(): void
    {
        // 登録画面から、すべて未入力の状態で送信する
        $response = $this->from('/register')->post('/register', []);

        // 入力元の登録画面へ戻ることを確認する
        $response->assertRedirect('/register');

        // 各項目の日本語エラーメッセージを確認する
        $response->assertSessionHasErrors([
            'name' => 'お名前を入力してください。',
            'email' => 'メールアドレスを入力してください。',
            'password' => 'パスワードを入力してください。',
        ]);

        // ユーザーが1件も登録されていないことを確認する
        $this->assertDatabaseCount('users', 0);

        // 未ログイン状態のままであることを確認する
        $this->assertGuest();
    }

    // パスワードが短すぎる場合は、登録されないことを確認する
    public function testRegistrationRejectsShortPasswords(): void
    {
        // パスワードと確認用に、同じ3文字の値を入力して送信する
        $response = $this->from('/register')->post('/register', [
            'name' => '登録テストユーザー',
            'email' => 'short-password@example.com',
            'password' => 'abc',
            'password_confirmation' => 'abc',
        ]);

        // 登録画面へ戻ることを確認する
        $response->assertRedirect('/register');

        // 文字数不足の日本語メッセージを確認する
        $response->assertSessionHasErrors([
            'password' => 'パスワードは8文字以上で入力してください。',
        ]);

        // ユーザーが登録されていないことを確認する
        $this->assertDatabaseCount('users', 0);

        // 未ログイン状態のままであることを確認する
        $this->assertGuest();
    }

    // 確認用パスワードが一致しない場合は、登録されないことを確認する
    public function testRegistrationRejectsMismatchedPasswords(): void
    {
        // パスワードと確認用に、異なる値を入力して送信する
        $response = $this->from('/register')->post('/register', [
            'name' => '登録テストユーザー',
            'email' => 'password-mismatch@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password456',
        ]);

        // 登録画面へ戻ることを確認する
        $response->assertRedirect('/register');

        // パスワード不一致の日本語メッセージを確認する
        $response->assertSessionHasErrors([
            'password' => '確認用パスワードが一致しません。',
        ]);

        // ユーザーが登録されていないことを確認する
        $this->assertDatabaseCount('users', 0);

        // 未ログイン状態のままであることを確認する
        $this->assertGuest();
    }

    // メールアドレスの形式が不正な場合は、登録されないことを確認する
    public function testRegistrationRejectsInvalidEmail(): void
    {
        // メールアドレスの形式を満たさない値を入力して送信する
        $response = $this->from('/register')->post('/register', [
            'name' => '登録テストユーザー',
            'email' => 'test-mail',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // 登録画面へ戻ることを確認する
        $response->assertRedirect('/register');

        // メール形式の日本語エラーメッセージを確認する
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを正しい形式で入力してください。',
        ]);

        // ユーザーが登録されていないことを確認する
        $this->assertDatabaseCount('users', 0);

        // 未ログイン状態のままであることを確認する
        $this->assertGuest();
    }

    // 登録済みのメールアドレスでは、追加登録できないことを確認する
    public function testRegistrationRejectsDuplicateEmail(): void
    {
        // テスト用DBに、登録済みユーザーを1件用意する
        $existingUser = User::create([
            'name' => '登録済みユーザー',
            'email' => 'existing-user@example.com',
            'password' => Hash::make('existing-password123'),
        ]);

        // 用意したユーザーと同じメールアドレスで登録を試みる
        $response = $this->from('/register')->post('/register', [
            'name' => '登録テストユーザー',
            'email' => $existingUser->email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // 登録画面へ戻ることを確認する
        $response->assertRedirect('/register');

        // メールアドレス重複の日本語メッセージを確認する
        $response->assertSessionHasErrors([
            'email' => 'このメールアドレスはすでに登録されています。',
        ]);

        // 最初に用意した1件から、ユーザーが増えていないことを確認する
        $this->assertDatabaseCount('users', 1);

        // 保存済みデータを再取得し、既存の名前が変わっていないことを確認する
        $this->assertSame('登録済みユーザー', $existingUser->fresh()->name);

        // 未ログイン状態のままであることを確認する
        $this->assertGuest();
    }
}
