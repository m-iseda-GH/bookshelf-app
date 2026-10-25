<?php

namespace Tests\Feature\Books;

// 削除対象の書籍を用意するためのモデルを読み込む
use App\Models\Book;
// 書籍に関連付けるジャンルを用意するためのモデルを読み込む
use App\Models\Genre;
// 書籍の登録者を用意するためのモデルを読み込む
use App\Models\User;
// テスト用のテーブル準備と、テスト後のデータ変更の取り消しを行う
use Illuminate\Foundation\Testing\RefreshDatabase;
// データベースの接続先を確認する機能を読み込む
use Illuminate\Support\Facades\DB;
// Laravelのテスト用基本クラスを読み込む
use Tests\TestCase;

// 書籍削除機能の動作を確認する
class BookDestroyTest extends TestCase
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

    // 登録者本人が書籍を削除できることを確認する
    public function testOwnerCanDeleteBook(): void
    {
        // 書籍の登録者を用意する
        $user = User::factory()->create();

        // 書籍に関連付けるジャンルを用意する
        $genre = Genre::create([
            'name' => '削除確認用ジャンル',
        ]);

        // 削除対象の書籍を用意する
        $book = Book::create([
            'user_id' => $user->id,
            'title' => '削除対象の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // ジャンルを関連付ける
        $book->genres()->attach($genre->id);

        // 登録者本人でログインし、書籍削除のリクエストを送る
        $response = $this->actingAs($user)
            ->delete(route('books.destroy', $book));

        // 削除後に書籍一覧へ移動することを確認する
        $response->assertRedirect('/books');

        // 削除完了のメッセージが用意されていることを確認する
        $response->assertSessionHas('success', '書籍を削除しました。');

        // 対象の書籍が削除されていることを確認する
        $this->assertDatabaseMissing('books', [
            'id' => $book->id,
        ]);

        // 対象書籍とジャンルの関連付けが削除されていることを確認する
        $this->assertDatabaseMissing('book_genre', [
            'book_id' => $book->id,
        ]);

        // 登録者のユーザーデータ自体は残っていることを確認する
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
        ]);

        // ジャンルのデータ自体は残っていることを確認する
        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
            'name' => $genre->name,
        ]);
    }

    // 対象書籍の関連データだけが連動して削除されることを確認する
    public function testDeletingBookRemovesOnlyItsRelatedData(): void
    {
        // 書籍の登録者・レビュー投稿者・いいねを付けるユーザーを用意する
        $owner = User::factory()->create();
        $reviewer = User::factory()->create();
        $liker = User::factory()->create();

        // 両方の書籍で使用するジャンルを用意する
        $genre = Genre::create([
            'name' => '連動削除確認用ジャンル',
        ]);

        // 削除対象の書籍を用意する
        $book = $owner->books()->create([
            'title' => '削除対象の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // 削除せずに残す書籍を用意する
        $otherBook = $owner->books()->create([
            'title' => '残す書籍',
            'author' => '別の著者',
            'isbn' => '9781234567804',
            'published_date' => '2020-01-01',
        ]);

        // 両方の書籍にジャンルを関連付ける
        $book->genres()->attach($genre->id);
        $otherBook->genres()->attach($genre->id);

        // 削除対象の書籍にレビューを用意する
        $review = $book->reviews()->create([
            'user_id' => $reviewer->id,
            'rating' => 4,
            'comment' => '削除対象のレビュー',
        ]);

        // 残す書籍にもレビューを用意する
        $otherReview = $otherBook->reviews()->create([
            'user_id' => $reviewer->id,
            'rating' => 5,
            'comment' => '残すレビュー',
        ]);

        // 両方の書籍をお気に入り登録する
        $reviewer->favoriteBooks()->attach([$book->id, $otherBook->id]);

        // 両方のレビューにいいねを付ける
        $liker->likedReviews()->attach([$review->id, $otherReview->id]);

        // 登録者本人でログインし、対象の書籍だけを削除する
        $response = $this->actingAs($owner)
            ->delete(route('books.destroy', $book));

        // 削除後に書籍一覧へ移動することを確認する
        $response->assertRedirect('/books');

        // 対象の書籍が削除されていることを確認する
        $this->assertDatabaseMissing('books', [
            'id' => $book->id,
        ]);

        // 別の書籍は残っていることを確認する
        $this->assertDatabaseHas('books', [
            'id' => $otherBook->id,
            'title' => '残す書籍',
        ]);

        // 対象書籍のジャンルとの関連付けが削除されていることを確認する
        $this->assertDatabaseMissing('book_genre', [
            'book_id' => $book->id,
        ]);

        // 別の書籍とジャンルの関連付けは残っていることを確認する
        $this->assertDatabaseHas('book_genre', [
            'book_id' => $otherBook->id,
            'genre_id' => $genre->id,
        ]);

        // 対象書籍のレビューが削除されていることを確認する
        $this->assertDatabaseMissing('reviews', [
            'id' => $review->id,
        ]);

        // 別の書籍のレビューは残っていることを確認する
        $this->assertDatabaseHas('reviews', [
            'id' => $otherReview->id,
            'book_id' => $otherBook->id,
        ]);

        // 対象書籍のお気に入り登録が削除されていることを確認する
        $this->assertDatabaseMissing('favorites', [
            'book_id' => $book->id,
        ]);

        // 別の書籍のお気に入り登録は残っていることを確認する
        $this->assertDatabaseHas('favorites', [
            'user_id' => $reviewer->id,
            'book_id' => $otherBook->id,
        ]);

        // 削除されたレビューのいいねも削除されていることを確認する
        $this->assertDatabaseMissing('review_likes', [
            'review_id' => $review->id,
        ]);

        // 別のレビューのいいねは残っていることを確認する
        $this->assertDatabaseHas('review_likes', [
            'user_id' => $liker->id,
            'review_id' => $otherReview->id,
        ]);

        // ユーザーデータは3件とも残っていることを確認する
        $this->assertDatabaseCount('users', 3);

        // ジャンルのデータ自体も残っていることを確認する
        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
            'name' => $genre->name,
        ]);
    }

    // 他人の書籍を削除できず、関連付けも残ることを確認する
    public function testUserCannotDeleteBookOwnedByAnotherUser(): void
    {
        // 書籍の登録者を用意する
        $owner = User::factory()->create();

        // 削除を試みる、別のユーザーを用意する
        $otherUser = User::factory()->create();

        // 書籍に関連付けるジャンルを用意する
        $genre = Genre::create([
            'name' => '削除権限確認用ジャンル',
        ]);

        // 登録者に所有される書籍を用意する
        $book = Book::create([
            'user_id' => $owner->id,
            'title' => '他のユーザーの書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // ジャンルを関連付ける
        $book->genres()->attach($genre->id);

        // 別のユーザーでログインし、書籍の削除を試みる
        $response = $this->actingAs($otherUser)
            ->delete(route('books.destroy', $book));

        // 操作権限がないことを示す403が返ることを確認する
        $response->assertForbidden();

        // 書籍が元の登録者・タイトルのまま残っていることを確認する
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $owner->id,
            'title' => '他のユーザーの書籍',
        ]);

        // ジャンルとの関連付けも残っていることを確認する
        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $genre->id,
        ]);
    }

    // 未ログインの場合は、書籍を削除できないことを確認する
    public function testGuestCannotDeleteBook(): void
    {
        // 書籍の登録者を用意する
        $owner = User::factory()->create();

        // 書籍に関連付けるジャンルを用意する
        $genre = Genre::create([
            'name' => '未ログイン削除確認用ジャンル',
        ]);

        // 削除を試みる対象の書籍を用意する
        $book = Book::create([
            'user_id' => $owner->id,
            'title' => '未ログイン確認用の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // ジャンルを関連付ける
        $book->genres()->attach($genre->id);

        // ログインせずに、書籍の削除を試みる
        $response = $this->delete(route('books.destroy', $book));

        // ログイン画面へ移動することを確認する
        $response->assertRedirect('/login');

        // 未ログイン状態のままであることを確認する
        $this->assertGuest();

        // 書籍が元の登録者・タイトルのまま残っていることを確認する
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $owner->id,
            'title' => '未ログイン確認用の書籍',
        ]);

        // ジャンルとの関連付けも残っていることを確認する
        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $genre->id,
        ]);
    }
}
