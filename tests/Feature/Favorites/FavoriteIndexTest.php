<?php

namespace Tests\Feature\Favorites;

// テスト用のユーザーを作成するモデルを読み込む
use App\Models\User;
// テスト用のテーブル準備と、テスト後のデータ変更の取り消しを行う
use Illuminate\Foundation\Testing\RefreshDatabase;
// データベースの接続先を確認する機能を読み込む
use Illuminate\Support\Facades\DB;
// Laravelのテスト用基本クラスを読み込む
use Tests\TestCase;

// お気に入り一覧画面の動作を確認する
class FavoriteIndexTest extends TestCase
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

    // ログインユーザーのお気に入りだけが表示されることを確認する
    public function testUserSeesOnlyTheirFavoriteBooks(): void
    {
        // テストではViteの読み込みを省略し、画面のHTMLを確認する
        $this->withoutVite();

        // 一覧を見るユーザーと、別のユーザーを用意する
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        // 自分がお気に入りにする書籍を用意する
        $favoriteBook = $otherUser->books()->create([
            'title' => '自分のお気に入りの書籍',
            'author' => 'お気に入り著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // 別のユーザーだけがお気に入りにする書籍を用意する
        $otherBook = $otherUser->books()->create([
            'title' => '他のユーザーだけのお気に入り書籍',
            'author' => '別の著者',
            'isbn' => '9781234567804',
            'published_date' => '2020-01-01',
        ]);

        // テスト用DBに、それぞれのお気に入りを用意する
        $user->favoriteBooks()->attach($favoriteBook->id);
        $otherUser->favoriteBooks()->attach($otherBook->id);

        // 一覧を見るユーザーでログインした状態にして、画面を開く
        $response = $this->actingAs($user)->get(route('favorites.index'));

        // 正常に表示されることを確認する
        $response->assertOk();

        // お気に入り一覧用のBladeが使われていることを確認する
        $response->assertViewIs('favorites.index');

        // 自分のお気に入り書籍の情報が表示されることを確認する
        $response->assertSee($favoriteBook->title);
        $response->assertSee($favoriteBook->author);
        $response->assertSee($favoriteBook->isbn);

        // 書籍詳細画面へのリンクが表示されることを確認する
        $response->assertSee(route('books.show', $favoriteBook));

        // 別のユーザーだけのお気に入り書籍が表示されないことを確認する
        $response->assertDontSee($otherBook->title);
    }

    // 自分のお気に入りが0件なら、案内文が表示されることを確認する
    public function testUserWithNoFavoritesSeesEmptyMessage(): void
    {
        // テストではViteの読み込みを省略する
        $this->withoutVite();

        // 一覧を見るユーザーと、別のユーザーを用意する
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        // 別のユーザーがお気に入りにする書籍を用意する
        $book = $otherUser->books()->create([
            'title' => '別のユーザーのお気に入り書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // 別のユーザーにだけ、お気に入りを用意する
        $otherUser->favoriteBooks()->attach($book->id);

        // お気に入りが0件のユーザーで、一覧画面を開く
        $response = $this->actingAs($user)->get(route('favorites.index'));

        // 正常に表示されることを確認する
        $response->assertOk();

        // お気に入り一覧用のBladeが使われていることを確認する
        $response->assertViewIs('favorites.index');

        // 0件の場合の案内文と、書籍一覧への案内が表示されることを確認する
        $response->assertSee('お気に入りに登録された書籍はありません。');
        $response->assertSee('書籍一覧を見る');

        // 別のユーザーのお気に入り書籍が表示されないことを確認する
        $response->assertDontSee($book->title);
    }

    // お気に入り一覧が1ページ10件で表示されることを確認する
    public function testFavoriteIndexDisplaysTenBooksPerPage(): void
    {
        // テストではViteの読み込みを省略する
        $this->withoutVite();

        // 一覧を見るユーザーを用意する
        $user = User::factory()->create();

        // 書籍とお気に入りを11件ずつ用意する
        foreach (range(1, 11) as $number) {
            $book = $user->books()->create([
                'title' => "ページ送り確認用の書籍{$number}",
                'author' => 'テスト著者',
                'isbn' => sprintf('978123456%04d', $number),
                'published_date' => '2020-01-01',
            ]);

            $user->favoriteBooks()->attach($book->id);
        }

        // このユーザーでログインした状態にする
        $this->actingAs($user);

        // 1ページ目を開く
        $firstResponse = $this->get(route('favorites.index'));
        $firstResponse->assertOk();

        // Bladeに渡された、1ページ目の一覧を取得する
        $firstPage = $firstResponse->viewData('books');

        // 表示件数・1ページの件数設定・全体件数を確認する
        $this->assertCount(10, $firstPage->items());
        $this->assertSame(10, $firstPage->perPage());
        $this->assertSame(11, $firstPage->total());

        // page=2を指定して、2ページ目を開く
        $secondResponse = $this->get(route('favorites.index', ['page' => 2]));
        $secondResponse->assertOk();

        // Bladeに渡された、2ページ目の一覧を取得する
        $secondPage = $secondResponse->viewData('books');

        // 残り1件が表示され、現在のページ番号が2であることを確認する
        $this->assertCount(1, $secondPage->items());
        $this->assertSame(2, $secondPage->currentPage());
        $this->assertSame(11, $secondPage->total());
    }

    // 未ログインでは、お気に入り一覧を開けないことを確認する
    public function testGuestCannotViewFavoriteIndex(): void
    {
        // 未ログインの状態で、お気に入り一覧を開く
        $response = $this->get(route('favorites.index'));

        // ログイン画面へ移動することを確認する
        $response->assertRedirect('/login');

        // 未ログイン状態であることを確認する
        $this->assertGuest();
    }
}
