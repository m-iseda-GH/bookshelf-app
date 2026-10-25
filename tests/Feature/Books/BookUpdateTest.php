<?php

namespace Tests\Feature\Books;

// 更新対象の書籍を用意するためのモデルを読み込む
use App\Models\Book;
// 更新前後のジャンルを用意するためのモデルを読み込む
use App\Models\Genre;
// 書籍の登録者を用意するためのモデルを読み込む
use App\Models\User;
// テスト用のテーブル準備と、テスト後のデータ変更の取り消しを行う
use Illuminate\Foundation\Testing\RefreshDatabase;
// データベースの接続先を確認する機能を読み込む
use Illuminate\Support\Facades\DB;
// Laravelのテスト用基本クラスを読み込む
use Tests\TestCase;

// 書籍更新機能の動作を確認する
class BookUpdateTest extends TestCase
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

    // 登録者本人が、同じISBNのまま書籍情報とジャンルを更新できることを確認する
    public function testOwnerCanUpdateBookKeepingSameIsbn(): void
    {
        // 書籍の登録者を用意する
        $user = User::factory()->create();

        // 更新時に選択を解除するジャンルを用意する
        $removedGenre = Genre::create([
            'name' => '変更前だけのジャンル',
        ]);

        // 更新後も選択を維持するジャンルを用意する
        $retainedGenre = Genre::create([
            'name' => '継続するジャンル',
        ]);

        // 更新時に新しく選択するジャンルを用意する
        $addedGenre = Genre::create([
            'name' => '追加するジャンル',
        ]);

        // 更新前の書籍を用意する
        $book = Book::create([
            'user_id' => $user->id,
            'title' => '変更前の書籍',
            'author' => '変更前の著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
            'description' => '変更前の書籍説明です。',
            'image_url' => 'https://example.com/before.jpg',
        ]);

        // 更新前は、解除予定と継続予定の2ジャンルを関連付ける
        $book->genres()->attach([
            $removedGenre->id,
            $retainedGenre->id,
        ]);

        // 登録者本人でログインし、ISBNを変更せずに更新する
        $response = $this->actingAs($user)
            ->put(route('books.update', $book), [
                'title' => '変更後の書籍',
                'author' => '変更後の著者',
                'isbn' => $book->isbn,
                'published_date' => '2021-02-03',
                'description' => '変更後の書籍説明です。',
                'image_url' => 'https://example.com/after.jpg',
                'genres' => [$retainedGenre->id, $addedGenre->id],
            ]);

        // 自分自身のISBNが重複扱いにならず、入力チェックを通過することを確認する
        $response->assertSessionHasNoErrors();

        // 更新後に、対象の書籍詳細へ移動することを確認する
        $response->assertRedirect(route('books.show', $book));

        // 更新完了のメッセージが用意されていることを確認する
        $response->assertSessionHas('success', '書籍を更新しました。');

        // 新しい書籍が追加されず、1件のままであることを確認する
        $this->assertDatabaseCount('books', 1);

        // 同じ書籍ID・登録者のまま、入力内容に更新されていることを確認する
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $user->id,
            'title' => '変更後の書籍',
            'author' => '変更後の著者',
            'isbn' => '9781234567897',
            'published_date' => '2021-02-03',
            'description' => '変更後の書籍説明です。',
            'image_url' => 'https://example.com/after.jpg',
        ]);

        // 更新後も、ジャンルとの関連付けが2件であることを確認する
        $this->assertDatabaseCount('book_genre', 2);

        // チェックを外したジャンルの関連付けが解除されていることを確認する
        $this->assertDatabaseMissing('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $removedGenre->id,
        ]);

        // 継続して選択したジャンルの関連付けが残っていることを確認する
        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $retainedGenre->id,
        ]);

        // 新しく選択したジャンルが関連付けられていることを確認する
        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $addedGenre->id,
        ]);
    }

    // 他人の書籍を更新できず、保存済みの内容も変わらないことを確認する
    public function testUserCannotUpdateBookOwnedByAnotherUser(): void
    {
        // 書籍の登録者を用意する
        $owner = User::factory()->create();

        // 更新を試みる、別のユーザーを用意する
        $otherUser = User::factory()->create();

        // 現在関連付けられているジャンルを用意する
        $originalGenre = Genre::create([
            'name' => '変更前のジャンル',
        ]);

        // 変更先として指定するジャンルを用意する
        $newGenre = Genre::create([
            'name' => '変更先のジャンル',
        ]);

        // 登録者に所有される書籍を用意する
        $book = Book::create([
            'user_id' => $owner->id,
            'title' => '他のユーザーの書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // 変更前のジャンルを関連付ける
        $book->genres()->attach($originalGenre->id);

        // 別のユーザーでログインし、タイトルとジャンルの変更を試みる
        $response = $this->actingAs($otherUser)
            ->put(route('books.update', $book), [
                'title' => '変更しようとした書籍',
                'author' => $book->author,
                'isbn' => $book->isbn,
                'published_date' => '2020-01-01',
                'genres' => [$newGenre->id],
            ]);

        // 操作権限がないことを示す403が返ることを確認する
        $response->assertForbidden();

        // 書籍の件数が変わっていないことを確認する
        $this->assertDatabaseCount('books', 1);

        // 登録者とタイトルが変更されていないことを確認する
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $owner->id,
            'title' => '他のユーザーの書籍',
        ]);

        // ジャンルとの関連付けが1件のままであることを確認する
        $this->assertDatabaseCount('book_genre', 1);

        // 元のジャンルとの関連付けが残っていることを確認する
        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $originalGenre->id,
        ]);

        // 変更先のジャンルが関連付けられていないことを確認する
        $this->assertDatabaseMissing('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $newGenre->id,
        ]);
    }

    // 未ログインの場合は、書籍を更新できないことを確認する
    public function testGuestCannotUpdateBook(): void
    {
        // 書籍の登録者を用意する
        $owner = User::factory()->create();

        // 書籍に関連付けるジャンルを用意する
        $genre = Genre::create([
            'name' => '未ログイン更新確認用ジャンル',
        ]);

        // 更新前の書籍を用意する
        $book = Book::create([
            'user_id' => $owner->id,
            'title' => '変更前の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // ジャンルを関連付ける
        $book->genres()->attach($genre->id);

        // ログインせずに、タイトルの変更を試みる
        $response = $this->put(route('books.update', $book), [
            'title' => '未ログインで変更しようとした書籍',
            'author' => $book->author,
            'isbn' => $book->isbn,
            'published_date' => '2020-01-01',
            'genres' => [$genre->id],
        ]);

        // ログイン画面へ移動することを確認する
        $response->assertRedirect('/login');

        // 未ログイン状態のままであることを確認する
        $this->assertGuest();

        // 書籍の件数が変わっていないことを確認する
        $this->assertDatabaseCount('books', 1);

        // 登録者とタイトルが変更されていないことを確認する
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $owner->id,
            'title' => '変更前の書籍',
        ]);

        // 元のジャンルとの関連付けが1件残っていることを確認する
        $this->assertDatabaseCount('book_genre', 1);

        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $genre->id,
        ]);
    }

    // 別の書籍で使われているISBNには変更できないことを確認する
    public function testBookUpdateRejectsDuplicateIsbn(): void
    {
        // 書籍の登録者を用意する
        $user = User::factory()->create();

        // 書籍に関連付けるジャンルを用意する
        $genre = Genre::create([
            'name' => '更新時のISBN重複確認用ジャンル',
        ]);

        // 更新対象の書籍を用意する
        $book = Book::create([
            'user_id' => $user->id,
            'title' => '更新対象の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // 別のISBNを持つ、登録済みの書籍を用意する
        $anotherBook = Book::create([
            'user_id' => $user->id,
            'title' => '登録済みの別の書籍',
            'author' => '別の著者',
            'isbn' => '9781234567804',
            'published_date' => '2020-01-01',
        ]);

        // 両方の書籍にジャンルを関連付ける
        $book->genres()->attach($genre->id);
        $anotherBook->genres()->attach($genre->id);

        // 別の書籍と同じISBNに変更しようとする
        $response = $this->actingAs($user)
            ->from(route('books.edit', $book))
            ->put(route('books.update', $book), [
                'title' => '変更しようとした書籍',
                'author' => $book->author,
                'isbn' => $anotherBook->isbn,
                'published_date' => '2021-02-03',
                'genres' => [$genre->id],
            ]);

        // 対象の書籍編集画面へ戻ることを確認する
        $response->assertRedirect(route('books.edit', $book));

        // ISBN重複の日本語エラーメッセージを確認する
        $response->assertSessionHasErrors([
            'isbn' => 'このISBNはすでに登録されています。',
        ]);

        // 書籍の件数が変わっていないことを確認する
        $this->assertDatabaseCount('books', 2);

        // 更新対象の書籍が、変更前の内容のままであることを確認する
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $user->id,
            'title' => '更新対象の書籍',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // ISBNの重複先となった書籍も変更されていないことを確認する
        $this->assertDatabaseHas('books', [
            'id' => $anotherBook->id,
            'title' => '登録済みの別の書籍',
            'isbn' => '9781234567804',
        ]);

        // ジャンルとの関連付けの件数が変わっていないことを確認する
        $this->assertDatabaseCount('book_genre', 2);
    }

    // 未登録のISBNへの変更と、任意項目を空欄にする更新ができることを確認する
    public function testOwnerCanChangeIsbnAndClearOptionalFields(): void
    {
        // 書籍の登録者を用意する
        $user = User::factory()->create();

        // 書籍に関連付けるジャンルを用意する
        $genre = Genre::create([
            'name' => '任意項目の更新確認用ジャンル',
        ]);

        // 説明と画像URLが登録されている書籍を用意する
        $book = Book::create([
            'user_id' => $user->id,
            'title' => '変更前の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
            'description' => '変更前の書籍説明です。',
            'image_url' => 'https://example.com/before.jpg',
        ]);

        // ジャンルを関連付ける
        $book->genres()->attach($genre->id);

        // ISBNを変更し、説明と画像URLを空欄にして送信する
        $response = $this->actingAs($user)
            ->put(route('books.update', $book), [
                'title' => '任意項目を空欄にした書籍',
                'author' => $book->author,
                'isbn' => '9781234567804',
                'published_date' => '2020-01-01',
                'description' => '',
                'image_url' => '',
                'genres' => [$genre->id],
            ]);

        // 入力チェックのエラーがないことを確認する
        $response->assertSessionHasNoErrors();

        // 更新後に、対象の書籍詳細へ移動することを確認する
        $response->assertRedirect(route('books.show', $book));

        // 書籍が追加されず、1件のままであることを確認する
        $this->assertDatabaseCount('books', 1);

        // ISBNが変更され、任意項目が未設定の状態になっていることを確認する
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $user->id,
            'title' => '任意項目を空欄にした書籍',
            'isbn' => '9781234567804',
            'description' => null,
            'image_url' => null,
        ]);
    }

    // 送信内容に別のユーザーIDが含まれていても、登録者が変わらないことを確認する
    public function testBookUpdateIgnoresSubmittedUserId(): void
    {
        // 書籍の登録者を用意する
        $user = User::factory()->create();

        // 送信内容に含める、別のユーザーを用意する
        $otherUser = User::factory()->create();

        // 書籍に関連付けるジャンルを用意する
        $genre = Genre::create([
            'name' => '更新時の登録者確認用ジャンル',
        ]);

        // 更新前の書籍を用意する
        $book = Book::create([
            'user_id' => $user->id,
            'title' => '変更前の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // ジャンルを関連付ける
        $book->genres()->attach($genre->id);

        // 登録者本人でログインし、別のユーザーIDを含めて更新する
        $response = $this->actingAs($user)
            ->put(route('books.update', $book), [
                'user_id' => $otherUser->id,
                'title' => '変更後の書籍',
                'author' => $book->author,
                'isbn' => $book->isbn,
                'published_date' => '2020-01-01',
                'genres' => [$genre->id],
            ]);

        // 入力チェックのエラーがないことを確認する
        $response->assertSessionHasNoErrors();

        // 更新後に、対象の書籍詳細へ移動することを確認する
        $response->assertRedirect(route('books.show', $book));

        // 書籍が追加されず、1件のままであることを確認する
        $this->assertDatabaseCount('books', 1);

        // タイトルが更新され、登録者は元のユーザーのままであることを確認する
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $user->id,
            'title' => '変更後の書籍',
        ]);
    }
}
