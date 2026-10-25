<?php

namespace Tests\Feature\Books;

// 詳細画面に表示する書籍を作成する
use App\Models\Book;
// 書籍に関連付けるジャンルを作成する
use App\Models\Genre;
// 書籍に投稿されたレビューを作成する
use App\Models\Review;
// 書籍の登録者・レビュー投稿者・いいねする人を作成する
use App\Models\User;
// テスト用のテーブル準備と、データ変更の取り消しを行う
use Illuminate\Foundation\Testing\RefreshDatabase;
// データベースの接続先を確認する
use Illuminate\Support\Facades\DB;
// Laravelのテスト用基本クラスを読み込む
use Tests\TestCase;

// 書籍詳細画面の動作を確認する
class BookShowTest extends TestCase
{
    use RefreshDatabase;

    // テーブルの初期化前に、接続先がテスト用であることを確認する
    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame(
            'testing',
            DB::connection()->getDatabaseName(),
            '接続先がtestingではないため、テストを停止しました。'
        );
    }

    // 未ログインでも書籍情報・レビュー・いいね数を閲覧できることを確認する
    public function testGuestCanViewBookDetailsWithReviewsAndLikes(): void
    {
        // CSS・JavaScriptのビルド状況に依存せず、画面の処理を確認する
        $this->withoutVite();

        // 書籍の登録者を用意する
        $owner = User::factory()->create();

        // レビュー投稿者を用意する
        $reviewer = User::factory()->create([
            'name' => 'レビュー投稿者',
        ]);

        // レビューにいいねするユーザーを用意する
        $liker = User::factory()->create();

        // 書籍に関連付けるジャンルを用意する
        $genre = Genre::create([
            'name' => 'テストジャンル',
        ]);

        // 詳細画面に表示する書籍を用意する
        $book = Book::create([
            'user_id' => $owner->id,
            'title' => '詳細表示テストの書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
            'description' => '詳細画面のテスト用説明です。',
            'image_url' => 'https://example.com/book-cover.jpg',
        ]);

        // 書籍とジャンルを関連付ける
        $book->genres()->attach($genre->id);

        // この書籍へのレビューを1件用意する
        $review = Review::create([
            'user_id' => $reviewer->id,
            'book_id' => $book->id,
            'rating' => 4,
            'comment' => '詳細画面のテスト用レビューです。',
        ]);

        // ユーザー側の関連付けを使い、レビューにいいねを1件登録する
        $liker->likedReviews()->attach($review->id);

        // 用意した書籍の詳細画面を、未ログインの状態で開く
        $response = $this->get('/books/'.$book->id);

        // 正常に画面を表示できることを確認する
        $response->assertOk();

        // 書籍詳細のBladeが使われていることを確認する
        $response->assertViewIs('books.show');

        // 書籍の基本情報が含まれることを確認する
        $response->assertSee('詳細表示テストの書籍');
        $response->assertSee('テスト著者');
        $response->assertSee('9781234567897');
        $response->assertSee('2020-01-01');

        // 説明・画像の参照先URL・ジャンルが含まれることを確認する
        $response->assertSee('詳細画面のテスト用説明です。');
        $response->assertSee('https://example.com/book-cover.jpg');
        $response->assertSee('テストジャンル');

        // レビュー投稿者・本文・評価が含まれることを確認する
        $response->assertSee('レビュー投稿者');
        $response->assertSee('詳細画面のテスト用レビューです。');
        $response->assertSee('★★★★☆');

        // 登録したいいねが1件として表示されることを確認する
        $response->assertSee('いいね (1)');

        // 未ログイン状態のままであることを確認する
        $this->assertGuest();
    }

    // ログイン中でも詳細画面と各フォームを表示できることを確認する
    public function testAuthenticatedUserCanViewBookDetails(): void
    {
        // CSS・JavaScriptのビルド状況に依存せず、画面の処理を確認する
        $this->withoutVite();

        // ログインするユーザーを用意する
        $user = User::factory()->create();

        // 別のユーザーをレビュー投稿者として用意する
        $reviewer = User::factory()->create();

        // 詳細画面に表示する書籍を用意する
        $book = Book::create([
            'user_id' => $user->id,
            'title' => 'ログイン中の表示確認用書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // いいねボタンの表示を確認するため、レビューを1件用意する
        $review = Review::create([
            'user_id' => $reviewer->id,
            'book_id' => $book->id,
            'rating' => 3,
            'comment' => 'ログイン中の表示確認用レビューです。',
        ]);

        // 指定したユーザーでログインした状態にして、詳細画面を開く
        $response = $this->actingAs($user)
            ->get('/books/'.$book->id);

        // 正常に詳細画面を表示できることを確認する
        $response->assertOk();
        $response->assertViewIs('books.show');

        // ログイン中の利用者向けにレビュー投稿フォームが表示されることを確認する
        $response->assertSee('レビューを投稿');

        // お気に入り登録・解除の送信先URLが含まれることを確認する
        $response->assertSee(route('favorites.toggle', $book));

        // レビュー投稿の送信先URLが含まれることを確認する
        $response->assertSee(route('reviews.store', $book));

        // いいね登録・解除の送信先URLが含まれることを確認する
        $response->assertSee(route('reviews.like', $review));

        // 指定したユーザーでログイン状態になっていることを確認する
        $this->assertAuthenticatedAs($user);
    }

    // 存在しない書籍の詳細では404を返すことを確認する
    public function testMissingBookReturnsNotFound(): void
    {
        // CSS・JavaScriptのビルド状況に依存せず、応答を確認する
        $this->withoutVite();

        // 書籍を登録していないテスト用DBで、存在しないIDへアクセスする
        $response = $this->get('/books/999999');

        // 対象が見つからないことを示す404が返ることを確認する
        $response->assertNotFound();
    }
}
