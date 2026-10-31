<?php

namespace Tests\Feature;

// 確認用のジャンルを作成するモデルを読み込む
use App\Models\Genre;
// ログイン状態にするユーザーを作成するモデルを読み込む
use App\Models\User;
// テスト用のテーブル準備と、テスト後のデータ変更の取り消しを行う
use Illuminate\Foundation\Testing\RefreshDatabase;
// データベースの接続先を確認する機能を読み込む
use Illuminate\Support\Facades\DB;
// Laravelのテスト用基本クラスを読み込む
use Tests\TestCase;

// 基本機能の入力画面が正常に表示されることを確認する
class ScreenAccessTest extends TestCase
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

    // ゲストがログイン画面と会員登録画面を表示できることを確認する
    public function testGuestsCanViewLoginAndRegistrationForms(): void
    {
        $this->withoutVite();

        // ログイン画面と、メールアドレス・パスワードの入力欄を確認する
        $this->get(route('login'))
            ->assertOk()
            ->assertViewIs('auth.login')
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false);

        // 会員登録画面と、名前・メールアドレス・確認用パスワードの入力欄を確認する
        $this->get(route('register'))
            ->assertOk()
            ->assertViewIs('auth.register')
            ->assertSee('name="name"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="password_confirmation"', false);

        $this->assertGuest();
    }

    // ログインユーザーが、ジャンルを選べる書籍登録画面を表示できることを確認する
    public function testAuthenticatedUserCanViewBookCreateFormWithGenres(): void
    {
        $this->withoutVite();

        $user = User::factory()->create();
        $genre = Genre::create(['name' => '小説']);
        $otherGenre = Genre::create(['name' => '歴史']);

        $response = $this->actingAs($user)->get(route('books.create'));

        $response->assertOk();
        $response->assertViewIs('books.create');
        $response->assertSee('小説');
        $response->assertSee('歴史');

        // ジャンル選択欄と、書籍の保存先が含まれることを確認する
        $response->assertSee('name="genres[]"', false);
        $response->assertSee(route('books.store'));

        // 登録済みのジャンルが、画面へ渡されていることを確認する
        $this->assertEqualsCanonicalizing(
            [$genre->id, $otherGenre->id],
            $response->viewData('genres')->modelKeys()
        );
    }
}
