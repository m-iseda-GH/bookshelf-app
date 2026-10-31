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

// レビュー編集画面の動作を確認する
class ReviewEditTest extends TestCase
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

    // 投稿者本人に、既存の入力値が入った編集画面が表示されることを確認する
    public function testReviewerCanViewEditFormWithExistingValues(): void
    {
        // テストではViteの読み込みを省略し、画面のHTMLを確認する
        $this->withoutVite();

        // 書籍の登録者と、レビューの投稿者を別々に用意する
        $owner = User::factory()->create();
        $reviewer = User::factory()->create();

        // レビューの対象となる書籍を用意する
        $book = $owner->books()->create([
            'title' => 'レビュー編集テスト用の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // 編集対象のレビューを用意する
        $review = $book->reviews()->create([
            'user_id' => $reviewer->id,
            'rating' => 4,
            'comment' => '編集前のレビューです。',
        ]);

        // 投稿者本人でログインした状態にして、編集画面を開く
        $response = $this->actingAs($reviewer)->get(route('reviews.edit', $review));

        // 正常に表示されることを確認する
        $response->assertOk();

        // レビュー編集用のBladeが使われていることを確認する
        $response->assertViewIs('reviews.edit');

        // 対象の書籍名と、保存済みのコメントが表示されることを確認する
        $response->assertSee($book->title);
        $response->assertSee($review->comment);

        // HTMLをそのまま照合し、評価4が選択済みであることを確認する
        $response->assertSee('name="rating" value="4" class="sr-only peer" checked', false);

        // 更新フォームの送信先が、このレビューの更新URLであることを確認する
        $response->assertSee(route('reviews.update', $review));
    }

    // 書籍の登録者でも、他人のレビュー編集画面は開けないことを確認する
    public function testUserCannotEditReviewPostedByAnotherUser(): void
    {
        // テストではViteの読み込みを省略する
        $this->withoutVite();

        // 書籍の登録者と、レビューの投稿者を別々に用意する
        $owner = User::factory()->create();
        $reviewer = User::factory()->create();

        // 権限確認用の書籍を用意する
        $book = $owner->books()->create([
            'title' => 'レビュー編集の権限確認用の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // 別のユーザーが投稿したレビューを用意する
        $review = $book->reviews()->create([
            'user_id' => $reviewer->id,
            'rating' => 4,
            'comment' => '別のユーザーが投稿したレビューです。',
        ]);

        // 書籍の登録者で、他のユーザーのレビュー編集画面を開く
        $response = $this->actingAs($owner)->get(route('reviews.edit', $review));

        // 投稿者本人ではないため、操作が拒否されることを確認する
        $response->assertForbidden();
    }

    // 未ログインでは、レビュー編集画面を開けないことを確認する
    public function testGuestCannotViewReviewEditForm(): void
    {
        // レビューの投稿者を用意する
        $user = User::factory()->create();

        // レビューの対象となる書籍を用意する
        $book = $user->books()->create([
            'title' => '未ログイン編集確認用の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // 編集対象のレビューを用意する
        $review = $book->reviews()->create([
            'user_id' => $user->id,
            'rating' => 4,
            'comment' => '未ログイン編集確認用のレビューです。',
        ]);

        // 未ログインの状態で、編集画面を開く
        $response = $this->get(route('reviews.edit', $review));

        // ログイン画面へ移動することを確認する
        $response->assertRedirect('/login');

        // 未ログイン状態であることを確認する
        $this->assertGuest();
    }
}
