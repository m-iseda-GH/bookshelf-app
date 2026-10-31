<?php

namespace Tests\Feature\Favorites;

// テスト用のユーザーを作成するモデルを読み込む
use App\Models\User;
// テスト用のテーブル準備と、テスト後のデータ変更の取り消しを行う
use Illuminate\Foundation\Testing\RefreshDatabase;
// データベースの接続先を確認する機能を読み込む
use Illuminate\Support\Facades\DB;
// Laravelのテスト用基本クラスを読み込む
use Tests\TestCase;

// お気に入りの追加・解除を確認する
class FavoriteToggleTest extends TestCase
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

    // ログインユーザーが書籍をお気に入りへ追加できることを確認する
    public function testUserCanAddBookToFavorites(): void
    {
        // 書籍の登録者と、お気に入り登録するユーザーを別々に用意する
        $owner = User::factory()->create();
        $user = User::factory()->create();

        // お気に入り登録の対象となる書籍を用意する
        $book = $owner->books()->create([
            'title' => 'お気に入り登録テスト用の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // ログインした状態で、書籍詳細画面から登録をリクエストする
        $response = $this->actingAs($user)
            ->from(route('books.show', $book))
            ->post(route('favorites.toggle', $book));

        // 操作した書籍の詳細画面へ戻ることを確認する
        $response->assertRedirect(route('books.show', $book));

        // 登録完了のメッセージを確認する
        $response->assertSessionHas('success', 'お気に入りに追加しました。');

        // お気に入りが1件保存されたことを確認する
        $this->assertDatabaseCount('favorites', 1);

        // ログインユーザーと対象書籍の組み合わせで保存されたことを確認する
        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    // 登録済みのお気に入りを解除できることを確認する
    public function testUserCanRemoveBookFromFavorites(): void
    {
        // 書籍の登録者と、お気に入りを解除するユーザーを別々に用意する
        $owner = User::factory()->create();
        $user = User::factory()->create();

        // お気に入り解除の対象となる書籍を用意する
        $book = $owner->books()->create([
            'title' => 'お気に入り解除テスト用の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // このユーザーがお気に入り登録済みの状態を用意する
        $user->favoriteBooks()->attach($book->id);

        // ログインした状態で、書籍詳細画面から解除をリクエストする
        $response = $this->actingAs($user)
            ->from(route('books.show', $book))
            ->post(route('favorites.toggle', $book));

        // 操作した書籍の詳細画面へ戻ることを確認する
        $response->assertRedirect(route('books.show', $book));

        // 解除完了のメッセージを確認する
        $response->assertSessionHas('success', 'お気に入りを解除しました。');

        // 対象のお気に入りが削除されたことを確認する
        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        // お気に入りが0件になったことを確認する
        $this->assertDatabaseCount('favorites', 0);

        // お気に入りを解除しても、書籍は残ることを確認する
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => $book->title,
        ]);
    }

    // 追加・解除しても、別のユーザーのお気に入りが変わらないことを確認する
    public function testTogglingDoesNotChangeOtherUsersFavorites(): void
    {
        // 操作するユーザーと、別のユーザーを用意する
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        // 両方のユーザーがお気に入りにする書籍を用意する
        $book = $otherUser->books()->create([
            'title' => 'ユーザーごとのお気に入り確認用の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // 別のユーザーが、すでに登録している状態を用意する
        $otherUser->favoriteBooks()->attach($book->id);

        // 操作するユーザーでログインした状態にする
        $this->actingAs($user);

        // 自分のお気に入りに追加する
        $addResponse = $this->from(route('books.show', $book))
            ->post(route('favorites.toggle', $book));

        // 書籍詳細画面へ戻ることを確認する
        $addResponse->assertRedirect(route('books.show', $book));

        // 自分と別のユーザーの、合計2件が登録されていることを確認する
        $this->assertDatabaseCount('favorites', 2);
        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
        $this->assertDatabaseHas('favorites', [
            'user_id' => $otherUser->id,
            'book_id' => $book->id,
        ]);

        // もう一度送信して、自分のお気に入りを解除する
        $removeResponse = $this->from(route('books.show', $book))
            ->post(route('favorites.toggle', $book));

        // 書籍詳細画面へ戻ることを確認する
        $removeResponse->assertRedirect(route('books.show', $book));

        // 自分の登録がなくなり、別のユーザーの1件だけが残ることを確認する
        $this->assertDatabaseCount('favorites', 1);
        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
        $this->assertDatabaseHas('favorites', [
            'user_id' => $otherUser->id,
            'book_id' => $book->id,
        ]);
    }

    // 未ログインでは、お気に入りの追加・解除ができないことを確認する
    public function testGuestCannotToggleFavorites(): void
    {
        // 書籍の登録者を用意する
        $user = User::factory()->create();

        // 操作対象の書籍を用意する
        $book = $user->books()->create([
            'title' => '未ログイン操作確認用の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // 未ログインの状態で、お気に入りへの追加を試みる
        $addResponse = $this->from(route('books.show', $book))
            ->post(route('favorites.toggle', $book));

        // ログイン画面へ移動し、未ログインのままであることを確認する
        $addResponse->assertRedirect('/login');
        $this->assertGuest();

        // お気に入りが追加されていないことを確認する
        $this->assertDatabaseCount('favorites', 0);

        // テスト用DBに、登録済みのお気に入りを用意する
        $user->favoriteBooks()->attach($book->id);

        // 未ログインの状態で、お気に入りの解除を試みる
        $removeResponse = $this->from(route('books.show', $book))
            ->post(route('favorites.toggle', $book));

        // ログイン画面へ移動し、未ログインのままであることを確認する
        $removeResponse->assertRedirect('/login');
        $this->assertGuest();

        // 解除されず、元の1件が残っていることを確認する
        $this->assertDatabaseCount('favorites', 1);
        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    // 登録するユーザーと書籍が、ログイン状態とURLに基づいて決まることを確認する
    public function testFavoriteUsesAuthenticatedUserAndBookFromUrl(): void
    {
        // 書籍の登録者と、お気に入り登録するユーザーを別々に用意する
        $owner = User::factory()->create();
        $user = User::factory()->create();

        // URLで指定する、本来の対象書籍を用意する
        $book = $owner->books()->create([
            'title' => '本来のお気に入り対象書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // 送信内容に含める、別の書籍を用意する
        $otherBook = $owner->books()->create([
            'title' => '送信内容で指定する別の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567804',
            'published_date' => '2020-01-01',
        ]);

        // ログイン中の本人・URLの書籍とは異なるIDを含めて送信する
        $response = $this->actingAs($user)
            ->from(route('books.show', $book))
            ->post(route('favorites.toggle', $book), [
                'user_id' => $owner->id,
                'book_id' => $otherBook->id,
            ]);

        // URLで指定した書籍の詳細画面へ戻ることを確認する
        $response->assertRedirect(route('books.show', $book));

        // お気に入りが1件だけ保存されたことを確認する
        $this->assertDatabaseCount('favorites', 1);

        // ログインユーザーと、URLの書籍で登録されたことを確認する
        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    // 最終ページの最後の1件を解除すると、一覧の先頭へ戻ることを確認する
    public function testRemovingLastFavoriteOnFinalPageRedirectsToFirstPage(): void
    {
        // テストではViteの読み込みを省略する
        $this->withoutVite();

        // 操作するユーザーを用意する
        $user = User::factory()->create();

        // 2ページに分かれるように、お気に入りを11件用意する
        foreach (range(1, 11) as $number) {
            $book = $user->books()->create([
                'title' => "解除後のページ確認用の書籍（{$number}）",
                'author' => 'テスト著者',
                'isbn' => sprintf('978123456%04d', $number),
                'published_date' => '2020-01-01',
            ]);

            $user->favoriteBooks()->attach($book->id);
        }

        // このユーザーでログインした状態にする
        $this->actingAs($user);

        // 2ページ目を開き、表示された1冊を取得する
        $lastPageUrl = route('favorites.index', ['page' => 2]);
        $pageResponse = $this->get($lastPageUrl);
        $pageResponse->assertOk();

        $lastPage = $pageResponse->viewData('books');
        $this->assertCount(1, $lastPage->items());
        $bookToRemove = $lastPage->items()[0];

        // 2ページ目から、その書籍のお気に入り解除をリクエストする
        $response = $this->from($lastPageUrl)
            ->post(route('favorites.toggle', $bookToRemove));

        // ページ番号の指定がない、一覧の先頭へ戻ることを確認する
        $response->assertRedirect(route('favorites.index'));

        // 対象のお気に入りが解除されたことを確認する
        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'book_id' => $bookToRemove->id,
        ]);

        // 書籍そのものは残っていることを確認する
        $this->assertDatabaseHas('books', [
            'id' => $bookToRemove->id,
        ]);

        // 戻り先の先頭ページを開く
        $firstPageResponse = $this->get(route('favorites.index'));
        $firstPageResponse->assertOk();

        // 0件の案内文と、解除した書籍が表示されないことを確認する
        $firstPageResponse->assertDontSee('お気に入りに登録された書籍はありません。');
        $firstPageResponse->assertDontSee($bookToRemove->title);

        // 残り10件が、1ページ目に表示されることを確認する
        $firstPage = $firstPageResponse->viewData('books');
        $this->assertSame(1, $firstPage->currentPage());
        $this->assertSame(10, $firstPage->total());
        $this->assertCount(10, $firstPage->items());
    }
}
