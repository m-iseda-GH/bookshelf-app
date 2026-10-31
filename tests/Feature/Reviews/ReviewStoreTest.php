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

// レビュー投稿機能の動作を確認する
class ReviewStoreTest extends TestCase
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

    // ログイン中のユーザーがレビューを投稿できることを確認する
    public function testAuthenticatedUserCanStoreReview(): void
    {
        // 書籍の登録者と、レビューの投稿者を別々に用意する
        $owner = User::factory()->create();
        $reviewer = User::factory()->create();

        // レビューの投稿先となる書籍をテスト用DBに登録する
        $book = $owner->books()->create([
            'title' => 'レビュー投稿テスト用の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // 投稿者でログインした状態にして、レビューを送信する
        $response = $this->actingAs($reviewer)->post(route('reviews.store', $book), [
            'rating' => 4,
            'comment' => '読みやすく、参考になりました。',
        ]);

        // 入力チェックのエラーがないことを確認する
        $response->assertSessionHasNoErrors();

        // 投稿先の書籍詳細画面へ移動することを確認する
        $response->assertRedirect(route('books.show', $book));

        // 投稿完了のメッセージを確認する
        $response->assertSessionHas('success', 'レビューを投稿しました。');

        // レビューが1件保存されたことを確認する
        $this->assertDatabaseCount('reviews', 1);

        // 投稿者・投稿先・評価・コメントが正しく保存されたことを確認する
        $this->assertDatabaseHas('reviews', [
            'user_id' => $reviewer->id,
            'book_id' => $book->id,
            'rating' => 4,
            'comment' => '読みやすく、参考になりました。',
        ]);
    }

    // 評価とコメントが未入力なら、レビューが保存されないことを確認する
    public function testReviewRequiresRatingAndComment(): void
    {
        // 投稿するユーザーを用意する
        $user = User::factory()->create();

        // 投稿先となる書籍をテスト用DBに登録する
        $book = $user->books()->create([
            'title' => 'レビュー入力チェック用の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // ログインした状態で、評価とコメントを未入力のまま送信する
        $response = $this->actingAs($user)
            ->from(route('books.show', $book))
            ->post(route('reviews.store', $book), []);

        // 入力元の書籍詳細画面へ戻ることを確認する
        $response->assertRedirect(route('books.show', $book));

        // 必須項目の日本語エラーメッセージを確認する
        $response->assertSessionHasErrors([
            'rating' => '評価を選択してください。',
            'comment' => 'コメントを入力してください。',
        ]);

        // レビューが保存されていないことを確認する
        $this->assertDatabaseCount('reviews', 0);
    }

    // 範囲外・小数・数字以外の評価では、レビューが保存されないことを確認する
    public function testReviewRejectsInvalidRatings(): void
    {
        // 投稿するユーザーを用意する
        $user = User::factory()->create();

        // 投稿先となる書籍をテスト用DBに登録する
        $book = $user->books()->create([
            'title' => '評価チェック用の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // このユーザーでログインした状態にする
        $this->actingAs($user);

        // 入力条件を満たさない評価を、1つずつ送信する
        foreach (['0', '6', '4.5', 'abc'] as $rating) {
            $response = $this->from(route('books.show', $book))
                ->post(route('reviews.store', $book), [
                    'rating' => $rating,
                    'comment' => '評価の入力チェックを確認します。',
                ]);

            // 入力元の書籍詳細画面へ戻ることを確認する
            $response->assertRedirect(route('books.show', $book));

            // 評価の日本語エラーメッセージを確認する
            $response->assertSessionHasErrors([
                'rating' => '評価は1〜5から選択してください。',
            ]);

            // レビューが保存されていないことを確認する
            $this->assertDatabaseCount('reviews', 0);
        }
    }

    // 評価の最小値1と最大値5で、レビューを投稿できることを確認する
    public function testReviewAcceptsMinimumAndMaximumRatings(): void
    {
        // 書籍の登録者を用意する
        $owner = User::factory()->create();

        // 投稿先となる書籍をテスト用DBに登録する
        $book = $owner->books()->create([
            'title' => '評価の範囲確認用の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // フォームから送信される値に合わせ、文字列の1と5で確認する
        foreach (['1', '5'] as $rating) {
            // 同じ書籍への投稿は1人1件なので、評価ごとに別の投稿者を用意する
            $reviewer = User::factory()->create();

            // 投稿者でログインした状態にして、レビューを送信する
            $response = $this->actingAs($reviewer)->post(route('reviews.store', $book), [
                'rating' => $rating,
                'comment' => '評価の範囲を確認するレビューです。',
            ]);

            // 入力チェックのエラーがないことを確認する
            $response->assertSessionHasNoErrors();

            // 投稿先の書籍詳細画面へ移動することを確認する
            $response->assertRedirect(route('books.show', $book));

            // 送信した評価が、投稿者・書籍と正しく関連付けて保存されたことを確認する
            $this->assertDatabaseHas('reviews', [
                'user_id' => $reviewer->id,
                'book_id' => $book->id,
                'rating' => $rating,
                'comment' => '評価の範囲を確認するレビューです。',
            ]);
        }

        // 評価1と評価5のレビューが、合計2件保存されたことを確認する
        $this->assertDatabaseCount('reviews', 2);
    }

    // 同じユーザーが同じ書籍へ、レビューを重複投稿できないことを確認する
    public function testReviewRejectsDuplicateSubmission(): void
    {
        // 投稿するユーザーを用意する
        $user = User::factory()->create();

        // 投稿先となる書籍をテスト用DBに登録する
        $book = $user->books()->create([
            'title' => '重複投稿チェック用の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // このユーザーが投稿済みのレビューを用意する
        $review = $book->reviews()->create([
            'user_id' => $user->id,
            'rating' => 3,
            'comment' => '最初に投稿したレビューです。',
        ]);

        // 同じユーザーで、同じ書籍への再投稿を試みる
        $response = $this->actingAs($user)
            ->from(route('books.show', $book))
            ->post(route('reviews.store', $book), [
                'rating' => 5,
                'comment' => '再投稿したレビューです。',
            ]);

        // 入力元の書籍詳細画面へ戻ることを確認する
        $response->assertRedirect(route('books.show', $book));

        // 重複投稿の日本語エラーメッセージを確認する
        $response->assertSessionHasErrors([
            'comment' => 'この書籍にはすでにレビューを投稿しています。',
        ]);

        // レビューが最初の1件から増えていないことを確認する
        $this->assertDatabaseCount('reviews', 1);

        // 最初のレビューの評価とコメントが変わっていないことを確認する
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 3,
            'comment' => '最初に投稿したレビューです。',
        ]);
    }

    // 同じユーザーが、別々の書籍へレビューを投稿できることを確認する
    public function testUserCanReviewDifferentBooks(): void
    {
        // 投稿するユーザーを用意する
        $user = User::factory()->create();

        // 最初の投稿先となる書籍を用意する
        $firstBook = $user->books()->create([
            'title' => '最初の投稿先の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // 別の投稿先となる書籍を用意する
        $secondBook = $user->books()->create([
            'title' => '別の投稿先の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567804',
            'published_date' => '2020-01-01',
        ]);

        // 最初の書籍には、すでに投稿している状態を用意する
        $firstReview = $firstBook->reviews()->create([
            'user_id' => $user->id,
            'rating' => 3,
            'comment' => '最初の書籍へのレビューです。',
        ]);

        // 同じユーザーで、別の書籍へレビューを投稿する
        $response = $this->actingAs($user)->post(route('reviews.store', $secondBook), [
            'rating' => 5,
            'comment' => '別の書籍へのレビューです。',
        ]);

        // 入力チェックや重複投稿のエラーがないことを確認する
        $response->assertSessionHasNoErrors();

        // 今回投稿した書籍の詳細画面へ移動することを確認する
        $response->assertRedirect(route('books.show', $secondBook));

        // レビューが合計2件になったことを確認する
        $this->assertDatabaseCount('reviews', 2);

        // 最初のレビューが変わらず残っていることを確認する
        $this->assertDatabaseHas('reviews', [
            'id' => $firstReview->id,
            'user_id' => $user->id,
            'book_id' => $firstBook->id,
            'rating' => 3,
            'comment' => '最初の書籍へのレビューです。',
        ]);

        // 別の書籍へのレビューも保存されたことを確認する
        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'book_id' => $secondBook->id,
            'rating' => 5,
            'comment' => '別の書籍へのレビューです。',
        ]);
    }

    // 未ログインの利用者はレビューを投稿できないことを確認する
    public function testGuestCannotStoreReview(): void
    {
        // 書籍の登録者を用意する
        $owner = User::factory()->create();

        // 投稿先となる書籍をテスト用DBに登録する
        $book = $owner->books()->create([
            'title' => '未ログイン投稿チェック用の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // 未ログインの状態で、レビューの投稿リクエストを送る
        $response = $this->post(route('reviews.store', $book), [
            'rating' => 4,
            'comment' => '未ログイン状態からの投稿です。',
        ]);

        // ログイン画面へ移動することを確認する
        $response->assertRedirect('/login');

        // 未ログイン状態であることを確認する
        $this->assertGuest();

        // レビューが保存されていないことを確認する
        $this->assertDatabaseCount('reviews', 0);
    }

    // 投稿者と投稿先が、ログイン状態とURLに基づいて決まることを確認する
    public function testReviewUsesAuthenticatedUserAndBookFromUrl(): void
    {
        // 書籍の登録者と、レビューの投稿者を用意する
        $owner = User::factory()->create();
        $reviewer = User::factory()->create();

        // URLで指定する、本来の投稿先の書籍を用意する
        $book = $owner->books()->create([
            'title' => '本来の投稿先の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // 送信内容に含める、別の書籍を用意する
        $otherBook = $owner->books()->create([
            'title' => '別の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567804',
            'published_date' => '2020-01-01',
        ]);

        // ログイン中の本人・URLの書籍とは異なるIDを含めて送信する
        $response = $this->actingAs($reviewer)->post(route('reviews.store', $book), [
            'user_id' => $owner->id,
            'book_id' => $otherBook->id,
            'rating' => 4,
            'comment' => '投稿者と投稿先の確認用レビューです。',
        ]);

        // 入力チェックのエラーがないことを確認する
        $response->assertSessionHasNoErrors();

        // URLで指定した書籍の詳細画面へ移動することを確認する
        $response->assertRedirect(route('books.show', $book));

        // レビューが1件だけ保存されたことを確認する
        $this->assertDatabaseCount('reviews', 1);

        // ログイン中の本人と、URLの書籍に関連付けて保存されたことを確認する
        $this->assertDatabaseHas('reviews', [
            'user_id' => $reviewer->id,
            'book_id' => $book->id,
            'rating' => 4,
            'comment' => '投稿者と投稿先の確認用レビューです。',
        ]);
    }
}
