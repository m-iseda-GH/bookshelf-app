<?php

namespace Tests\Feature\Reviews;

// テスト用のレビューを扱うモデルを読み込む
use App\Models\Review;
// テスト用のユーザーを作成するモデルを読み込む
use App\Models\User;
// テスト用のテーブル準備と、テスト後のデータ変更の取り消しを行う
use Illuminate\Foundation\Testing\RefreshDatabase;
// データベースの接続先を確認する機能を読み込む
use Illuminate\Support\Facades\DB;
// Laravelのテスト用基本クラスを読み込む
use Tests\TestCase;

// レビューへのいいねの追加・解除を確認する
class ReviewLikeTest extends TestCase
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

    // いいねを追加でき、画面に人数が表示されることを確認する
    public function testUserCanLikeReview(): void
    {
        // テストではViteの読み込みを省略する
        $this->withoutVite();

        // レビュー投稿者と、いいねするユーザーを用意する
        $reviewer = User::factory()->create();
        $user = User::factory()->create();

        // このファイル末尾の処理を使い、対象のレビューを用意する
        $review = $this->createReview($reviewer);

        // ログインした状態で、いいねをリクエストする
        $response = $this->actingAs($user)
            ->post(route('reviews.like', $review));

        // 対象の書籍詳細画面へ戻ることを確認する
        $response->assertRedirect(route('books.show', $review->book_id));

        // 完了メッセージを確認する
        $response->assertSessionHas('success', 'いいねしました。');

        // ログインユーザーと対象レビューの組み合わせで保存されることを確認する
        $this->assertDatabaseCount('review_likes', 1);
        $this->assertDatabaseHas('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);

        // 詳細画面に、いいね済みの状態と人数が表示されることを確認する
        $this->get(route('books.show', $review->book_id))
            ->assertOk()
            ->assertSee('いいね済み (1)');
    }

    // 登録済みのいいねを解除できることを確認する
    public function testUserCanUnlikeReview(): void
    {
        // テストではViteの読み込みを省略する
        $this->withoutVite();

        // 投稿者と、いいねするユーザーを用意する
        $reviewer = User::factory()->create();
        $user = User::factory()->create();
        $review = $this->createReview($reviewer);

        // このユーザーが、すでにいいねしている状態を用意する
        $review->likedByUsers()->attach($user->id);

        // ログインした状態で、もう一度いいねをリクエストする
        $response = $this->actingAs($user)
            ->post(route('reviews.like', $review));

        // 対象の書籍詳細画面へ戻ることを確認する
        $response->assertRedirect(route('books.show', $review->book_id));

        // 解除完了のメッセージを確認する
        $response->assertSessionHas('success', 'いいねを解除しました。');

        // いいねが解除されたことを確認する
        $this->assertDatabaseCount('review_likes', 0);

        // 解除してもレビュー自体は残ることを確認する
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'rating' => 4,
            'comment' => 'いいねの確認用レビューです。',
        ]);

        // 画面の人数が0人になり、いいね済みの表示がなくなることを確認する
        $this->get(route('books.show', $review->book_id))
            ->assertOk()
            ->assertSee('いいね (0)')
            ->assertDontSee('いいね済み');
    }

    // 自分が追加・解除しても、他のユーザーのいいねが残ることを確認する
    public function testTogglingLikePreservesOtherUsersLikes(): void
    {
        // 投稿者、操作するユーザー、別のユーザーを用意する
        $reviewer = User::factory()->create();
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $review = $this->createReview($reviewer);

        // 別のユーザーが、すでにいいねしている状態を用意する
        $review->likedByUsers()->attach($otherUser->id);

        // 操作するユーザーでログインした状態にする
        $this->actingAs($user);

        // 自分のいいねを追加する
        $this->post(route('reviews.like', $review))
            ->assertRedirect(route('books.show', $review->book_id));

        // 2人分のいいねが保存されていることを確認する
        $this->assertDatabaseCount('review_likes', 2);
        $this->assertDatabaseHas('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);
        $this->assertDatabaseHas('review_likes', [
            'user_id' => $otherUser->id,
            'review_id' => $review->id,
        ]);

        // もう一度送信し、自分のいいねを解除する
        $this->post(route('reviews.like', $review))
            ->assertRedirect(route('books.show', $review->book_id));

        // 自分のいいねだけがなくなり、別のユーザーの分は残ることを確認する
        $this->assertDatabaseCount('review_likes', 1);
        $this->assertDatabaseMissing('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);
        $this->assertDatabaseHas('review_likes', [
            'user_id' => $otherUser->id,
            'review_id' => $review->id,
        ]);
    }

    // 未ログインでは、いいねの追加・解除ができないことを確認する
    public function testGuestCannotToggleReviewLikes(): void
    {
        // 投稿者と、いいねするユーザーを用意する
        $reviewer = User::factory()->create();
        $user = User::factory()->create();
        $review = $this->createReview($reviewer);

        // 未ログインの状態で、追加を試みる
        $this->post(route('reviews.like', $review))
            ->assertRedirect('/login');

        // 追加されず、未ログインのままであることを確認する
        $this->assertDatabaseCount('review_likes', 0);
        $this->assertGuest();

        // テスト用DBに、登録済みのいいねを用意する
        $review->likedByUsers()->attach($user->id);

        // 未ログインの状態で、解除を試みる
        $this->post(route('reviews.like', $review))
            ->assertRedirect('/login');

        // 元のいいねが残り、未ログインのままであることを確認する
        $this->assertDatabaseCount('review_likes', 1);
        $this->assertDatabaseHas('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);
        $this->assertGuest();
    }

    // 送信されたIDではなく、ログインユーザーとURLのレビューが使われることを確認する
    public function testLikeUsesAuthenticatedUserAndReviewFromUrl(): void
    {
        // 投稿者と、操作するユーザーを用意する
        $reviewer = User::factory()->create();
        $user = User::factory()->create();

        // 本来の対象と、送信内容に含める別のレビューを用意する
        $review = $this->createReview($reviewer);
        $otherReview = $this->createReview($reviewer, '9781234567804');

        // 本人・対象レビューとは異なるIDを含めて送信する
        $response = $this->actingAs($user)
            ->post(route('reviews.like', $review), [
                'user_id' => $reviewer->id,
                'review_id' => $otherReview->id,
            ]);

        // URLで指定したレビューの書籍詳細画面へ戻ることを確認する
        $response->assertRedirect(route('books.show', $review->book_id));

        // ログインユーザーと、URLのレビューで1件だけ保存されることを確認する
        $this->assertDatabaseCount('review_likes', 1);
        $this->assertDatabaseHas('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);
    }

    // 存在しないレビューへの操作では、404になることを確認する
    public function testMissingReviewReturnsNotFound(): void
    {
        // 投稿者と、操作するユーザーを用意する
        $reviewer = User::factory()->create();
        $user = User::factory()->create();
        $review = $this->createReview($reviewer);

        // IDを控えてから削除し、存在しないレビューの状態を用意する
        $reviewId = $review->id;
        $review->delete();

        // 削除済みのIDでいいねをリクエストする
        $this->actingAs($user)
            ->post(route('reviews.like', $reviewId))
            ->assertNotFound();

        // いいねが追加されていないことを確認する
        $this->assertDatabaseCount('review_likes', 0);
    }

    // 各テストで使う書籍とレビューを作成する共通の準備処理
    private function createReview(
        User $reviewer,
        string $isbn = '9781234567897'
    ): Review {
        // 投稿者が登録した書籍を1冊用意する
        $book = $reviewer->books()->create([
            'title' => 'いいね確認用の書籍',
            'author' => 'テスト著者',
            'isbn' => $isbn,
            'published_date' => '2020-01-01',
        ]);

        // 用意した書籍にレビューを作成し、呼び出したテストへ返す
        return $book->reviews()->create([
            'user_id' => $reviewer->id,
            'rating' => 4,
            'comment' => 'いいねの確認用レビューです。',
        ]);
    }
}
