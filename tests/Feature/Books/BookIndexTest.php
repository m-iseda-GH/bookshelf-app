<?php

namespace Tests\Feature\Books;

// 一覧に表示する書籍を作成する
use App\Models\Book;
// 書籍に関連付けるジャンルを作成する
use App\Models\Genre;
// 書籍の登録者を作成する
use App\Models\User;
// テスト用のテーブル準備と、データ変更の取り消しを行う
use Illuminate\Foundation\Testing\RefreshDatabase;
// データベースの接続先を確認する
use Illuminate\Support\Facades\DB;
// Laravelのテスト用基本クラスを読み込む
use Tests\TestCase;

// 書籍一覧の動作を確認する
class BookIndexTest extends TestCase
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

    // 未ログインでも、書籍一覧を閲覧できることを確認する
    public function testGuestCanViewBookIndex(): void
    {
        // CSS・JavaScriptのビルド状況に依存せず、画面の処理を確認する
        $this->withoutVite();

        // 書籍の登録者を用意する。ログイン状態にはしない
        $user = User::factory()->create();

        // 書籍に関連付けるジャンルを用意する
        $genre = Genre::create([
            'name' => 'テストジャンル',
        ]);

        // 一覧に表示する書籍をテスト用DBに登録する
        $book = Book::create([
            'user_id' => $user->id,
            'title' => '一覧表示テストの書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // book_genreに書籍とジャンルの組み合わせを登録する
        $book->genres()->attach($genre->id);

        // 未ログインの状態で書籍一覧へアクセスする
        $response = $this->get('/books');

        // 正常な応答であるHTTPステータス200を確認する
        $response->assertOk();

        // 書籍一覧のBladeが使われていることを確認する
        $response->assertViewIs('books.index');

        // 書名・著者名・ジャンル名が画面に含まれることを確認する
        $response->assertSee('一覧表示テストの書籍');
        $response->assertSee('テスト著者');
        $response->assertSee('テストジャンル');

        // トップページでも書籍一覧が表示されることを確認する
        $this->get('/')
            ->assertOk()
            ->assertViewIs('books.index');

        // 未ログイン状態のままであることを確認する
        $this->assertGuest();
    }

    // 登録日時が新しい書籍から表示されることを確認する
    public function testBookIndexDisplaysBooksByNewestRegistrationDate(): void
    {
        // CSS・JavaScriptのビルド状況に依存せず、画面の処理を確認する
        $this->withoutVite();

        // 書籍の登録者をテスト用DBに用意する
        $user = User::factory()->create();

        // 登録日時が新しい書籍を先に作成する
        $newerBook = Book::create([
            'user_id' => $user->id,
            'title' => '登録日時が新しい書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // 登録日時をテスト用の値に変更して保存する
        $newerBook->created_at = '2026-01-02 12:00:00';
        $newerBook->save();

        // 登録日時が古い書籍を後から作成する
        $olderBook = Book::create([
            'user_id' => $user->id,
            'title' => '登録日時が古い書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567804',
            'published_date' => '2020-01-01',
        ]);

        // 先に作成した書籍より古い登録日時に変更して保存する
        $olderBook->created_at = '2026-01-01 12:00:00';
        $olderBook->save();

        // 書籍一覧を開く
        $response = $this->get('/books');

        // 正常に画面を表示できることを確認する
        $response->assertOk();

        // 登録日時が新しい書籍、古い書籍の順に表示されることを確認する
        $response->assertSeeInOrder([
            $newerBook->title,
            $olderBook->title,
        ]);
    }

    // 書籍一覧が1ページ10件に分かれることを確認する
    public function testBookIndexDisplaysTenBooksPerPage(): void
    {
        // CSS・JavaScriptのビルド状況に依存せず、画面の処理を確認する
        $this->withoutVite();

        // 書籍の登録者をテスト用DBに用意する
        $user = User::factory()->create();

        // ページ送りが発生するよう、書籍を11件用意する
        for ($number = 1; $number <= 11; $number++) {
            Book::create([
                'user_id' => $user->id,
                'title' => 'ページ送りテストの書籍'.$number,
                'author' => 'テスト著者',
                // 書籍ごとに異なる13桁のテスト用ISBNを作る
                'isbn' => sprintf('97812345678%02d', $number),
                'published_date' => '2020-01-01',
            ]);
        }

        // 書籍一覧の1ページ目を開く
        $firstResponse = $this->get('/books');

        // 正常に画面を表示できることを確認する
        $firstResponse->assertOk();

        // 画面に渡されたbooksを取得する
        $firstPageBooks = $firstResponse->viewData('books');

        // 1ページ目に含まれる書籍が10件であることを確認する
        $this->assertCount(10, $firstPageBooks);

        // ページ分割前の書籍総数が11件であることを確認する
        $this->assertSame(11, $firstPageBooks->total());

        // URLのpageに2を指定し、2ページ目を開く
        $secondResponse = $this->get('/books?page=2');

        // 正常に画面を表示できることを確認する
        $secondResponse->assertOk();

        // 2ページ目の画面に渡されたbooksを取得する
        $secondPageBooks = $secondResponse->viewData('books');

        // 2ページ目に含まれる書籍が残りの1件であることを確認する
        $this->assertCount(1, $secondPageBooks);

        // 現在のページ番号が2であることを確認する
        $this->assertSame(2, $secondPageBooks->currentPage());

        // 未ログイン状態で閲覧できていることを確認する
        $this->assertGuest();
    }
}
