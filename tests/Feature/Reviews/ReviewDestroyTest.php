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

// レビュー削除機能の動作を確認する
class ReviewDestroyTest extends TestCase
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

    // 投稿者本人がレビューを削除できることを確認する
    public function testReviewerCanDeleteReview(): void
    {
        // 書籍の登録者と、レビューの投稿者を別々に用意する
        $owner = User::factory()->create();
        $reviewer = User::factory()->create();

        // レビューの対象となる書籍を用意する
        $book = $owner->books()->create([
            'title' => 'レビュー削除テスト用の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // 削除対象のレビューを用意する
        $review = $book->reviews()->create([
            'user_id' => $reviewer->id,
            'rating' => 4,
            'comment' => '削除対象のレビューです。',
        ]);

        // 投稿者本人でログインした状態にして、削除をリクエストする
        $response = $this->actingAs($reviewer)->delete(route('reviews.destroy', $review));

        // 対象の書籍詳細画面へ移動することを確認する
        $response->assertRedirect(route('books.show', $book));

        // 削除完了のメッセージを確認する
        $response->assertSessionHas('success', 'レビューを削除しました。');

        // 対象のレビューが削除されたことを確認する
        $this->assertDatabaseMissing('reviews', [
            'id' => $review->id,
        ]);

        // レビューの対象だった書籍が残っていることを確認する
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => $book->title,
        ]);

        // 書籍の登録者と、レビューの投稿者が残っていることを確認する
        $this->assertDatabaseHas('users', [
            'id' => $owner->id,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $reviewer->id,
        ]);
    }

    // レビュー削除時に、そのレビューのいいねだけが削除されることを確認する
    public function testDeletingReviewRemovesOnlyItsLikes(): void
    {
        // 書籍の登録者、2人の投稿者、いいねするユーザーを用意する
        $owner = User::factory()->create();
        $reviewer = User::factory()->create();
        $otherReviewer = User::factory()->create();
        $liker = User::factory()->create();

        // レビューの対象となる書籍を用意する
        $book = $owner->books()->create([
            'title' => 'いいねの連動削除確認用の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // 削除対象のレビューを用意する
        $review = $book->reviews()->create([
            'user_id' => $reviewer->id,
            'rating' => 4,
            'comment' => '削除対象のレビューです。',
        ]);

        // 同じ書籍に、残すレビューも用意する
        $otherReview = $book->reviews()->create([
            'user_id' => $otherReviewer->id,
            'rating' => 3,
            'comment' => '残すレビューです。',
        ]);

        // 両方のレビューに、同じユーザーのいいねを用意する
        $liker->likedReviews()->attach([
            $review->id,
            $otherReview->id,
        ]);

        // 投稿者本人で、対象のレビューを削除する
        $response = $this->actingAs($reviewer)->delete(route('reviews.destroy', $review));

        // 対象の書籍詳細画面へ移動することを確認する
        $response->assertRedirect(route('books.show', $book));

        // 対象のレビューが削除されたことを確認する
        $this->assertDatabaseMissing('reviews', [
            'id' => $review->id,
        ]);

        // 削除したレビューのいいねが残っていないことを確認する
        $this->assertDatabaseMissing('review_likes', [
            'review_id' => $review->id,
        ]);

        // 別のレビューが変わらず残っていることを確認する
        $this->assertDatabaseHas('reviews', [
            'id' => $otherReview->id,
            'user_id' => $otherReviewer->id,
            'book_id' => $book->id,
            'rating' => 3,
            'comment' => '残すレビューです。',
        ]);

        // 別のレビューに付いたいいねが残っていることを確認する
        $this->assertDatabaseHas('review_likes', [
            'user_id' => $liker->id,
            'review_id' => $otherReview->id,
        ]);

        // 対象の書籍と4人のユーザーが残っていることを確認する
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
        ]);
        $this->assertDatabaseCount('users', 4);
    }

    // 書籍の登録者でも、他人のレビューは削除できないことを確認する
    public function testUserCannotDeleteReviewPostedByAnotherUser(): void
    {
        // 書籍の登録者と、レビューの投稿者を別々に用意する
        $owner = User::factory()->create();
        $reviewer = User::factory()->create();

        // 権限確認用の書籍を用意する
        $book = $owner->books()->create([
            'title' => 'レビュー削除の権限確認用の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // 別のユーザーが投稿したレビューを用意する
        $review = $book->reviews()->create([
            'user_id' => $reviewer->id,
            'rating' => 4,
            'comment' => '削除を許可しないレビューです。',
        ]);

        // 書籍の登録者が、このレビューにいいねした状態を用意する
        $owner->likedReviews()->attach($review->id);

        // 書籍の登録者で、他のユーザーのレビュー削除を試みる
        $response = $this->actingAs($owner)->delete(route('reviews.destroy', $review));

        // 投稿者本人ではないため、操作が拒否されることを確認する
        $response->assertForbidden();

        // レビューが元の内容で残っていることを確認する
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'user_id' => $reviewer->id,
            'book_id' => $book->id,
            'rating' => 4,
            'comment' => '削除を許可しないレビューです。',
        ]);

        // レビューに付いたいいねも残っていることを確認する
        $this->assertDatabaseHas('review_likes', [
            'user_id' => $owner->id,
            'review_id' => $review->id,
        ]);
    }

    // 未ログインでは、レビューを削除できないことを確認する
    public function testGuestCannotDeleteReview(): void
    {
        // 書籍の登録者と、レビューの投稿者を別々に用意する
        $owner = User::factory()->create();
        $reviewer = User::factory()->create();

        // レビューの対象となる書籍を用意する
        $book = $owner->books()->create([
            'title' => '未ログイン削除確認用の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // 削除対象となるレビューを用意する
        $review = $book->reviews()->create([
            'user_id' => $reviewer->id,
            'rating' => 4,
            'comment' => '未ログイン削除確認用のレビューです。',
        ]);

        // 書籍の登録者が、このレビューにいいねした状態を用意する
        $owner->likedReviews()->attach($review->id);

        // 未ログインの状態で、レビューの削除を試みる
        $response = $this->delete(route('reviews.destroy', $review));

        // ログイン画面へ移動することを確認する
        $response->assertRedirect('/login');

        // 未ログイン状態であることを確認する
        $this->assertGuest();

        // レビューが元の内容で残っていることを確認する
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'user_id' => $reviewer->id,
            'book_id' => $book->id,
            'rating' => 4,
            'comment' => '未ログイン削除確認用のレビューです。',
        ]);

        // レビューに付いたいいねも残っていることを確認する
        $this->assertDatabaseHas('review_likes', [
            'user_id' => $owner->id,
            'review_id' => $review->id,
        ]);
    }
}
