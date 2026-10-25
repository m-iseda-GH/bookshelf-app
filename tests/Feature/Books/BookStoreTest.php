<?php

namespace Tests\Feature\Books;

// 保存された書籍を取得するためのモデルを読み込む
use App\Models\Book;
// 選択するジャンルを用意するためのモデルを読み込む
use App\Models\Genre;
// ログインするユーザーを用意するためのモデルを読み込む
use App\Models\User;
// テスト用のテーブル準備と、テスト後のデータ変更の取り消しを行う
use Illuminate\Foundation\Testing\RefreshDatabase;
// データベースの接続先を確認する機能を読み込む
use Illuminate\Support\Facades\DB;
// Laravelのテスト用基本クラスを読み込む
use Tests\TestCase;

// 書籍登録機能の動作を確認する
class BookStoreTest extends TestCase
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

    // ログイン中のユーザーが、ジャンルを複数選んで書籍を登録できることを確認する
    public function testAuthenticatedUserCanStoreBookWithGenres(): void
    {
        // 書籍の登録者となるテスト用ユーザーを作成する
        $user = User::factory()->create();

        // 登録時に選択するジャンルを2件用意する
        $firstGenre = Genre::create([
            'name' => '登録テストジャンル1',
        ]);

        $secondGenre = Genre::create([
            'name' => '登録テストジャンル2',
        ]);

        // ログイン状態で、書籍登録フォームに相当するリクエストを送る
        $response = $this->actingAs($user)->post('/books', [
            'title' => '登録テストの書籍',
            'author' => '登録テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
            'description' => '登録テスト用の書籍説明です。',
            'image_url' => 'https://example.com/book-cover.jpg',
            'genres' => [$firstGenre->id, $secondGenre->id],
        ]);

        // 入力チェックのエラーがないことを確認する
        $response->assertSessionHasNoErrors();

        // 登録後に書籍一覧へ移動することを確認する
        $response->assertRedirect('/books');

        // 登録完了のメッセージが用意されていることを確認する
        $response->assertSessionHas('success', '書籍を登録しました。');

        // 書籍が1件だけ登録されていることを確認する
        $this->assertDatabaseCount('books', 1);

        // 入力内容と、ログイン中のユーザーIDが正しく保存されていることを確認する
        $this->assertDatabaseHas('books', [
            'user_id' => $user->id,
            'title' => '登録テストの書籍',
            'author' => '登録テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
            'description' => '登録テスト用の書籍説明です。',
            'image_url' => 'https://example.com/book-cover.jpg',
        ]);

        // 中間テーブルを確認するため、登録された書籍を取得する
        $book = Book::where('isbn', '9781234567897')->firstOrFail();

        // 書籍とジャンルの組み合わせが2件保存されていることを確認する
        $this->assertDatabaseCount('book_genre', 2);

        // 1つ目のジャンルが、登録した書籍に関連付けられていることを確認する
        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $firstGenre->id,
        ]);

        // 2つ目のジャンルも、同じ書籍に関連付けられていることを確認する
        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $secondGenre->id,
        ]);
    }

    // 必須項目が未入力の場合は、書籍が登録されないことを確認する
    public function testBookRegistrationRequiresRequiredFields(): void
    {
        // 書籍登録を行うテスト用ユーザーを作成する
        $user = User::factory()->create();

        // ログイン状態で、すべて未入力の登録リクエストを送る
        $response = $this->actingAs($user)
            ->from('/books/create')
            ->post('/books', []);

        // 入力元の書籍登録画面へ戻ることを確認する
        $response->assertRedirect('/books/create');

        // 必須項目ごとの日本語エラーメッセージを確認する
        $response->assertSessionHasErrors([
            'title' => 'タイトルを入力してください。',
            'author' => '著者名を入力してください。',
            'isbn' => 'ISBNを入力してください。',
            'published_date' => '出版日を入力してください。',
            'genres' => 'ジャンルを1つ以上選択してください。',
        ]);

        // 書籍が登録されていないことを確認する
        $this->assertDatabaseCount('books', 0);

        // 書籍とジャンルの関連付けも保存されていないことを確認する
        $this->assertDatabaseCount('book_genre', 0);
    }

    // 未ログインの場合は、書籍登録画面の表示と書籍の保存ができないことを確認する
    public function testGuestCannotCreateOrStoreBooks(): void
    {
        // 登録リクエストで指定するジャンルを用意する
        $genre = Genre::create([
            'name' => '未ログイン確認用ジャンル',
        ]);

        // 登録画面を開こうとすると、ログイン画面へ移動することを確認する
        $this->get('/books/create')->assertRedirect('/login');

        // 未ログインのまま、必要な入力項目をそろえて登録を試みる
        $response = $this->post('/books', [
            'title' => '未ログイン登録テストの書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
            'genres' => [$genre->id],
        ]);

        // 登録処理に進まず、ログイン画面へ移動することを確認する
        $response->assertRedirect('/login');

        // 書籍が登録されていないことを確認する
        $this->assertDatabaseCount('books', 0);

        // 書籍とジャンルの関連付けも保存されていないことを確認する
        $this->assertDatabaseCount('book_genre', 0);

        // 未ログイン状態のままであることを確認する
        $this->assertGuest();
    }

    // ISBNが半角数字13桁でない場合は、登録されないことを確認する
    public function testBookRegistrationRejectsInvalidIsbnFormats(): void
    {
        // 書籍登録を行うユーザーを用意する
        $user = User::factory()->create();

        // 選択するジャンルを用意する
        $genre = Genre::create([
            'name' => 'ISBN確認用ジャンル',
        ]);

        // 条件を満たさないISBNを用意する
        $invalidIsbns = [
            '978123456789',   // 12桁
            '97812345678970', // 14桁
            '978123456789X',  // 13文字だが英字を含む
        ];

        // 各ISBNで登録を試し、入力チェックで拒否されることを確認する
        foreach ($invalidIsbns as $isbn) {
            $response = $this->actingAs($user)
                ->from('/books/create')
                ->post('/books', [
                    'title' => 'ISBN確認用の書籍',
                    'author' => 'テスト著者',
                    'isbn' => $isbn,
                    'published_date' => '2020-01-01',
                    'genres' => [$genre->id],
                ]);

            // 登録画面へ戻ることを確認する
            $response->assertRedirect('/books/create');

            // ISBNの日本語エラーメッセージを確認する
            $response->assertSessionHasErrors([
                'isbn' => 'ISBNは半角数字13桁で入力してください。',
            ]);

            // 書籍が登録されていないことを確認する
            $this->assertDatabaseCount('books', 0);

            // ジャンルとの関連付けも保存されていないことを確認する
            $this->assertDatabaseCount('book_genre', 0);
        }
    }

    // 登録済みのISBNでは、新しい書籍を登録できないことを確認する
    public function testBookRegistrationRejectsDuplicateIsbn(): void
    {
        // 書籍登録を行うユーザーを用意する
        $user = User::factory()->create();

        // 書籍に関連付けるジャンルを用意する
        $genre = Genre::create([
            'name' => 'ISBN重複確認用ジャンル',
        ]);

        // すでに登録されている書籍を1件用意する
        $existingBook = $user->books()->create([
            'title' => '登録済みの書籍',
            'author' => '登録済みの著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // 登録済みの書籍にジャンルを関連付ける
        $existingBook->genres()->attach($genre->id);

        // 同じISBNを使って、別の書籍の登録を試みる
        $response = $this->actingAs($user)
            ->from('/books/create')
            ->post('/books', [
                'title' => '追加登録しようとした書籍',
                'author' => '別の著者',
                'isbn' => $existingBook->isbn,
                'published_date' => '2021-01-01',
                'genres' => [$genre->id],
            ]);

        // 登録画面へ戻ることを確認する
        $response->assertRedirect('/books/create');

        // ISBN重複の日本語エラーメッセージを確認する
        $response->assertSessionHasErrors([
            'isbn' => 'このISBNはすでに登録されています。',
        ]);

        // 最初に用意した1件から、書籍が増えていないことを確認する
        $this->assertDatabaseCount('books', 1);

        // ジャンルとの関連付けも増えていないことを確認する
        $this->assertDatabaseCount('book_genre', 1);

        // 登録済みの書籍のタイトルが変更されていないことを確認する
        $this->assertSame('登録済みの書籍', $existingBook->fresh()->title);
    }

    // 出版日が有効な日付でない場合は、登録されないことを確認する
    public function testBookRegistrationRejectsInvalidPublishedDates(): void
    {
        // 書籍登録を行うユーザーを用意する
        $user = User::factory()->create();

        // 選択するジャンルを用意する
        $genre = Genre::create([
            'name' => '出版日確認用ジャンル',
        ]);

        // 日付の条件を満たさない値を用意する
        $invalidDates = [
            '2020-02-30', // 形式は日付だが、実際には存在しない日
            'not-a-date', // 日付として扱えない文字列
        ];

        // 各出版日で登録を試し、入力チェックで拒否されることを確認する
        foreach ($invalidDates as $publishedDate) {
            $response = $this->actingAs($user)
                ->from('/books/create')
                ->post('/books', [
                    'title' => '出版日確認用の書籍',
                    'author' => 'テスト著者',
                    'isbn' => '9781234567897',
                    'published_date' => $publishedDate,
                    'genres' => [$genre->id],
                ]);

            // 登録画面へ戻ることを確認する
            $response->assertRedirect('/books/create');

            // 出版日の日本語エラーメッセージを確認する
            $response->assertSessionHasErrors([
                'published_date' => '出版日の入力内容を確認してください。',
            ]);

            // 書籍が登録されていないことを確認する
            $this->assertDatabaseCount('books', 0);

            // ジャンルとの関連付けも保存されていないことを確認する
            $this->assertDatabaseCount('book_genre', 0);
        }
    }

    // 存在しないジャンルが指定された場合は、登録されないことを確認する
    public function testBookRegistrationRejectsNonexistentGenres(): void
    {
        // 書籍登録を行うユーザーを用意する
        $user = User::factory()->create();

        // ジャンルを作成せず、存在しないIDを指定して登録を試みる
        $response = $this->actingAs($user)
            ->from('/books/create')
            ->post('/books', [
                'title' => 'ジャンル確認用の書籍',
                'author' => 'テスト著者',
                'isbn' => '9781234567897',
                'published_date' => '2020-01-01',
                'genres' => [999999],
            ]);

        // 登録画面へ戻ることを確認する
        $response->assertRedirect('/books/create');

        // 選択したジャンルに対する日本語エラーメッセージを確認する
        $response->assertSessionHasErrors([
            'genres.0' => 'ジャンルの選択内容を確認してください。',
        ]);

        // 書籍が登録されていないことを確認する
        $this->assertDatabaseCount('books', 0);

        // ジャンルとの関連付けも保存されていないことを確認する
        $this->assertDatabaseCount('book_genre', 0);
    }

    // 画像URLとして使えない値が入力された場合は、登録されないことを確認する
    public function testBookRegistrationRejectsInvalidImageUrl(): void
    {
        // 書籍登録を行うユーザーを用意する
        $user = User::factory()->create();

        // 選択するジャンルを用意する
        $genre = Genre::create([
            'name' => '画像URL確認用ジャンル',
        ]);

        // URLではない文字列を画像URLに指定して登録を試みる
        $response = $this->actingAs($user)
            ->from('/books/create')
            ->post('/books', [
                'title' => '画像URL確認用の書籍',
                'author' => 'テスト著者',
                'isbn' => '9781234567897',
                'published_date' => '2020-01-01',
                'image_url' => 'not-a-url',
                'genres' => [$genre->id],
            ]);

        // 登録画面へ戻ることを確認する
        $response->assertRedirect('/books/create');

        // 画像URLの日本語エラーメッセージを確認する
        $response->assertSessionHasErrors([
            'image_url' => '画像URLの入力内容を確認してください。',
        ]);

        // 書籍が登録されていないことを確認する
        $this->assertDatabaseCount('books', 0);

        // ジャンルとの関連付けも保存されていないことを確認する
        $this->assertDatabaseCount('book_genre', 0);
    }

    // 説明と画像URLが空欄でも、書籍を登録できることを確認する
    public function testBookRegistrationAllowsBlankOptionalFields(): void
    {
        // 書籍登録を行うユーザーを用意する
        $user = User::factory()->create();

        // 選択するジャンルを用意する
        $genre = Genre::create([
            'name' => '任意項目確認用ジャンル',
        ]);

        // 必須項目を入力し、任意項目は空欄のまま送信する
        $response = $this->actingAs($user)->post('/books', [
            'title' => '任意項目が空欄の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
            'description' => '',
            'image_url' => '',
            'genres' => [$genre->id],
        ]);

        // 入力チェックのエラーがないことを確認する
        $response->assertSessionHasNoErrors();

        // 登録後に書籍一覧へ移動することを確認する
        $response->assertRedirect('/books');

        // 書籍が1件登録されていることを確認する
        $this->assertDatabaseCount('books', 1);

        // 任意項目が未設定の状態で保存されていることを確認する
        $this->assertDatabaseHas('books', [
            'user_id' => $user->id,
            'isbn' => '9781234567897',
            'description' => null,
            'image_url' => null,
        ]);

        // 登録された書籍を取得する
        $book = Book::where('isbn', '9781234567897')->firstOrFail();

        // 選択したジャンルが1件だけ関連付けられていることを確認する
        $this->assertDatabaseCount('book_genre', 1);

        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $genre->id,
        ]);
    }

    // 送信内容に別のユーザーIDが含まれていても、ログイン中のユーザーを登録者にする
    public function testBookRegistrationUsesAuthenticatedUserAsOwner(): void
    {
        // 実際にログインするユーザーを用意する
        $user = User::factory()->create();

        // 送信内容に含める、別のユーザーを用意する
        $otherUser = User::factory()->create();

        // 選択するジャンルを用意する
        $genre = Genre::create([
            'name' => '登録者確認用ジャンル',
        ]);

        // ログイン中とは別のユーザーIDを含めて、書籍登録を行う
        $response = $this->actingAs($user)->post('/books', [
            'user_id' => $otherUser->id,
            'title' => '登録者確認用の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
            'genres' => [$genre->id],
        ]);

        // 入力チェックのエラーがないことを確認する
        $response->assertSessionHasNoErrors();

        // 登録後に書籍一覧へ移動することを確認する
        $response->assertRedirect('/books');

        // 書籍が1件登録されていることを確認する
        $this->assertDatabaseCount('books', 1);

        // ログイン中のユーザーが登録者として保存されていることを確認する
        $this->assertDatabaseHas('books', [
            'isbn' => '9781234567897',
            'user_id' => $user->id,
        ]);

        // 送信内容に含めた別のユーザーが、登録者になっていないことを確認する
        $this->assertDatabaseMissing('books', [
            'isbn' => '9781234567897',
            'user_id' => $otherUser->id,
        ]);
    }
}
