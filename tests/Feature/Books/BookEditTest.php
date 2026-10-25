<?php

namespace Tests\Feature\Books;

// 編集対象の書籍を用意するためのモデルを読み込む
use App\Models\Book;
// ジャンルの選択肢を用意するためのモデルを読み込む
use App\Models\Genre;
// 書籍の登録者を用意するためのモデルを読み込む
use App\Models\User;
// テスト用のテーブル準備と、テスト後のデータ変更の取り消しを行う
use Illuminate\Foundation\Testing\RefreshDatabase;
// データベースの接続先を確認する機能を読み込む
use Illuminate\Support\Facades\DB;
// Laravelのテスト用基本クラスを読み込む
use Tests\TestCase;

// 書籍編集画面の表示と権限を確認する
class BookEditTest extends TestCase
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

    // 登録者本人が、保存済みの内容を引き継いだ編集画面を開けることを確認する
    public function testOwnerCanViewBookEditFormWithExistingValues(): void
    {
        // テスト中はCSS・JavaScriptのビルドファイルへの依存をなくす
        $this->withoutVite();

        // 書籍の登録者を用意する
        $user = User::factory()->create();

        // 書籍に関連付けるジャンルを用意する
        $selectedGenre = Genre::create([
            'name' => '選択済みジャンル',
        ]);

        // 選択肢には表示するが、書籍には関連付けないジャンルを用意する
        $unselectedGenre = Genre::create([
            'name' => '未選択ジャンル',
        ]);

        // 編集対象の書籍を用意する
        $book = Book::create([
            'user_id' => $user->id,
            'title' => '編集前の書籍',
            'author' => '編集前の著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
            'description' => '編集前の書籍説明です。',
            'image_url' => 'https://example.com/book-cover.jpg',
        ]);

        // 選択済みジャンルを、書籍に関連付ける
        $book->genres()->attach($selectedGenre->id);

        // 登録者本人でログインし、編集画面を開く
        $response = $this->actingAs($user)
            ->get(route('books.edit', $book));

        // 正常に表示され、編集用のBladeが使われていることを確認する
        $response->assertOk();
        $response->assertViewIs('books.edit');

        // 保存済みの各項目が画面に含まれていることを確認する
        $response->assertSee($book->title);
        $response->assertSee($book->author);
        $response->assertSee($book->isbn);
        $response->assertSee($book->description);
        $response->assertSee($book->image_url);

        // 出版日が、日付入力欄で使える年月日の形式になっていることを確認する
        $response->assertSee('value="2020-01-01"', false);

        // 関連付けの有無にかかわらず、両方のジャンルが選択肢に表示されることを確認する
        $response->assertSee($selectedGenre->name);
        $response->assertSee($unselectedGenre->name);

        // 画面に渡された書籍データを取得する
        $viewBook = $response->viewData('book');

        // URLで指定した書籍が編集対象になっていることを確認する
        $this->assertSame($book->id, $viewBook->id);

        // 書籍に関連付けたジャンルが、画面に渡されていることを確認する
        $this->assertTrue($viewBook->genres->contains($selectedGenre->id));

        // 関連付けていないジャンルが、選択済みデータに含まれないことを確認する
        $this->assertFalse($viewBook->genres->contains($unselectedGenre->id));
    }

    // 他人が登録した書籍の編集画面は開けないことを確認する
    public function testUserCannotEditBookOwnedByAnotherUser(): void
    {
        // テスト中はCSS・JavaScriptのビルドファイルへの依存をなくす
        $this->withoutVite();

        // 書籍の登録者を用意する
        $owner = User::factory()->create();

        // 編集を試みる、別のユーザーを用意する
        $otherUser = User::factory()->create();

        // 登録者に所有される書籍を用意する
        $book = Book::create([
            'user_id' => $owner->id,
            'title' => '他のユーザーの書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // 別のユーザーでログインし、編集画面を開こうとする
        $response = $this->actingAs($otherUser)
            ->get(route('books.edit', $book));

        // 操作権限がないことを示す403が返ることを確認する
        $response->assertForbidden();
    }

    // 未ログインの場合は、書籍の編集画面を開けないことを確認する
    public function testGuestCannotViewBookEditForm(): void
    {
        // 書籍の登録者を用意する
        $owner = User::factory()->create();

        // 編集対象の書籍を用意する
        $book = Book::create([
            'user_id' => $owner->id,
            'title' => '未ログイン確認用の書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
        ]);

        // ログインせずに、編集画面を開こうとする
        $response = $this->get(route('books.edit', $book));

        // ログイン画面へ移動することを確認する
        $response->assertRedirect('/login');

        // 未ログイン状態のままであることを確認する
        $this->assertGuest();
    }
}
