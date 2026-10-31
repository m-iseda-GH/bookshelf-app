<?php

namespace Tests\Feature\Reviews;

// テスト用のユーザーを作成するモデルを読み込む
use App\Models\User;
// テスト用のテーブル準備と、テスト後のデータ変更の取り消しを行う
use Illuminate\Foundation\Testing\RefreshDatabase;
// データベースの接続先を確認する機能を読み込む
use Illuminate\Support\Facades\DB;
// Laravelのテスト用基本クラスを読み込む
use Tests\TestCase;

// レビュー更新機能の動作を確認する
class ReviewUpdateTest extends TestCase
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

    // 投稿者本人が、評価とコメントを更新できることを確認する
    public function testReviewerCanUpdateReview(): void
    {
        // 書籍の登録者と、レビューの投稿者を別々に用意する
        $owner = User::factory()->create();
        $reviewer = User::factory()->create();

        // レビューの対象となる書籍を用意する
        $book = $owner->books()->create([
            'title' => 'レビュー更新テスト用の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // 更新前のレビューを用意する
        $review = $book->reviews()->create([
            'user_id' => $reviewer->id,
            'rating' => 2,
            'comment' => '更新前のレビューです。',
        ]);

        // 投稿者本人でログインした状態にして、更新内容を送信する
        $response = $this->actingAs($reviewer)->put(route('reviews.update', $review), [
            'rating' => 5,
            'comment' => '更新後のレビューです。',
        ]);

        // 入力チェックのエラーがないことを確認する
        $response->assertSessionHasNoErrors();

        // 対象の書籍詳細画面へ移動することを確認する
        $response->assertRedirect(route('books.show', $book));

        // 更新完了のメッセージを確認する
        $response->assertSessionHas('success', 'レビューを更新しました。');

        // レビューが1件のままであることを確認する
        $this->assertDatabaseCount('reviews', 1);

        // 同じレビューの評価とコメントが更新されたことを確認する
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'user_id' => $reviewer->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => '更新後のレビューです。',
        ]);
    }

    // 書籍の登録者でも、他人のレビューは更新できないことを確認する
    public function testUserCannotUpdateReviewPostedByAnotherUser(): void
    {
        // 書籍の登録者と、レビューの投稿者を別々に用意する
        $owner = User::factory()->create();
        $reviewer = User::factory()->create();

        // 権限確認用の書籍を用意する
        $book = $owner->books()->create([
            'title' => 'レビュー更新の権限確認用の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // 別のユーザーが投稿したレビューを用意する
        $review = $book->reviews()->create([
            'user_id' => $reviewer->id,
            'rating' => 2,
            'comment' => '元のレビューです。',
        ]);

        // 書籍の登録者で、他のユーザーのレビュー更新を試みる
        $response = $this->actingAs($owner)->put(route('reviews.update', $review), [
            'rating' => 5,
            'comment' => '別のユーザーが送信した更新内容です。',
        ]);

        // 投稿者本人ではないため、操作が拒否されることを確認する
        $response->assertForbidden();

        // レビューが1件のままであることを確認する
        $this->assertDatabaseCount('reviews', 1);

        // 投稿者・対象書籍・評価・コメントが変わっていないことを確認する
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'user_id' => $reviewer->id,
            'book_id' => $book->id,
            'rating' => 2,
            'comment' => '元のレビューです。',
        ]);
    }

    // 未ログインでは、レビューを更新できないことを確認する
    public function testGuestCannotUpdateReview(): void
    {
        // レビューの投稿者を用意する
        $user = User::factory()->create();

        // レビューの対象となる書籍を用意する
        $book = $user->books()->create([
            'title' => '未ログイン更新確認用の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // 更新前のレビューを用意する
        $review = $book->reviews()->create([
            'user_id' => $user->id,
            'rating' => 2,
            'comment' => '未ログイン更新確認用のレビューです。',
        ]);

        // 未ログインの状態で、更新内容を送信する
        $response = $this->put(route('reviews.update', $review), [
            'rating' => 5,
            'comment' => '未ログイン状態からの更新内容です。',
        ]);

        // ログイン画面へ移動することを確認する
        $response->assertRedirect('/login');

        // 未ログイン状態であることを確認する
        $this->assertGuest();

        // レビューが1件のままであることを確認する
        $this->assertDatabaseCount('reviews', 1);

        // 保存済みの内容が変わっていないことを確認する
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 2,
            'comment' => '未ログイン更新確認用のレビューです。',
        ]);
    }

    // 更新時も、評価とコメントの入力が必須であることを確認する
    public function testReviewUpdateRequiresRatingAndComment(): void
    {
        // レビューの投稿者を用意する
        $user = User::factory()->create();

        // レビューの対象となる書籍を用意する
        $book = $user->books()->create([
            'title' => '更新時の必須項目確認用の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // 更新前のレビューを用意する
        $review = $book->reviews()->create([
            'user_id' => $user->id,
            'rating' => 2,
            'comment' => '更新前のレビューです。',
        ]);

        // 投稿者本人で、評価とコメントを未入力のまま送信する
        $response = $this->actingAs($user)
            ->from(route('reviews.edit', $review))
            ->put(route('reviews.update', $review), []);

        // 入力元のレビュー編集画面へ戻ることを確認する
        $response->assertRedirect(route('reviews.edit', $review));

        // 必須項目の日本語エラーメッセージを確認する
        $response->assertSessionHasErrors([
            'rating' => '評価を選択してください。',
            'comment' => 'コメントを入力してください。',
        ]);

        // レビューが1件のままであることを確認する
        $this->assertDatabaseCount('reviews', 1);

        // 保存済みの内容が変わっていないことを確認する
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 2,
            'comment' => '更新前のレビューです。',
        ]);
    }

    // 入力条件を満たさない評価では、レビューが更新されないことを確認する
    public function testReviewUpdateRejectsInvalidRatings(): void
    {
        // レビューの投稿者を用意する
        $user = User::factory()->create();

        // レビューの対象となる書籍を用意する
        $book = $user->books()->create([
            'title' => '更新時の評価確認用の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // 更新前のレビューを用意する
        $review = $book->reviews()->create([
            'user_id' => $user->id,
            'rating' => 2,
            'comment' => '更新前のレビューです。',
        ]);

        // 投稿者本人でログインした状態にする
        $this->actingAs($user);

        // 範囲外・小数・数字以外の評価を、1つずつ送信する
        foreach (['0', '6', '4.5', 'abc'] as $rating) {
            $response = $this->from(route('reviews.edit', $review))
                ->put(route('reviews.update', $review), [
                    'rating' => $rating,
                    'comment' => '更新後のレビューです。',
                ]);

            // 入力元のレビュー編集画面へ戻ることを確認する
            $response->assertRedirect(route('reviews.edit', $review));

            // 評価の日本語エラーメッセージを確認する
            $response->assertSessionHasErrors([
                'rating' => '評価は1〜5から選択してください。',
            ]);

            // レビューが1件のままであることを確認する
            $this->assertDatabaseCount('reviews', 1);

            // 評価とコメントが更新前のままであることを確認する
            $this->assertDatabaseHas('reviews', [
                'id' => $review->id,
                'user_id' => $user->id,
                'book_id' => $book->id,
                'rating' => 2,
                'comment' => '更新前のレビューです。',
            ]);
        }
    }

    // 送信内容に別のIDが含まれていても、投稿者と対象書籍が変わらないことを確認する
    public function testReviewUpdateIgnoresSubmittedUserAndBookIds(): void
    {
        // 書籍の登録者と、レビューの投稿者を別々に用意する
        $owner = User::factory()->create();
        $reviewer = User::factory()->create();

        // 元のレビューの対象となる書籍を用意する
        $book = $owner->books()->create([
            'title' => '元のレビューの対象書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // 送信内容に含める、別の書籍を用意する
        $otherBook = $owner->books()->create([
            'title' => '別の対象書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567804',
            'published_date' => '2020-01-01',
        ]);

        // 更新前のレビューを用意する
        $review = $book->reviews()->create([
            'user_id' => $reviewer->id,
            'rating' => 2,
            'comment' => '更新前のレビューです。',
        ]);

        // 投稿者本人で、別のユーザーID・書籍IDを含めて更新内容を送信する
        $response = $this->actingAs($reviewer)->put(route('reviews.update', $review), [
            'user_id' => $owner->id,
            'book_id' => $otherBook->id,
            'rating' => 5,
            'comment' => 'ID確認用の更新後レビューです。',
        ]);

        // 入力チェックのエラーがないことを確認する
        $response->assertSessionHasNoErrors();

        // 元の対象書籍の詳細画面へ移動することを確認する
        $response->assertRedirect(route('books.show', $book));

        // レビューが1件のままであることを確認する
        $this->assertDatabaseCount('reviews', 1);

        // 投稿者・対象書籍を保ち、評価とコメントが更新されたことを確認する
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'user_id' => $reviewer->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => 'ID確認用の更新後レビューです。',
        ]);
    }
}
