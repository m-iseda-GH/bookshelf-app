<?php

namespace Tests\Feature\Genres;

// テスト用の書籍を扱うモデルを読み込む
use App\Models\Book;
// テスト用のジャンルを扱うモデルを読み込む
use App\Models\Genre;
// テスト用のユーザーを作成するモデルを読み込む
use App\Models\User;
// テスト用のテーブル準備と、テスト後のデータ変更の取り消しを行う
use Illuminate\Foundation\Testing\RefreshDatabase;
// データベースの接続先を確認する機能を読み込む
use Illuminate\Support\Facades\DB;
// Laravelのテスト用基本クラスを読み込む
use Tests\TestCase;

// ジャンルの表示・登録・更新・削除を確認する
class GenreTest extends TestCase
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

    // ジャンル一覧に、それぞれの書籍数が表示されることを確認する
    public function testUserCanViewGenreIndexWithBookCounts(): void
    {
        $this->withoutVite();

        // 書籍があるジャンルと、書籍がないジャンルを用意する
        $user = User::factory()->create();
        $genre = Genre::create(['name' => '文学']);
        $emptyGenre = Genre::create(['name' => 'その他']);

        // 書籍を1冊用意し、文学だけに関連付ける
        $book = $this->createBook($user);
        $book->genres()->attach($genre->id);

        // ログイン状態でジャンル一覧を開く
        $response = $this->actingAs($user)->get(route('genres.index'));

        $response->assertOk();
        $response->assertViewIs('genres.index');
        $response->assertSee($genre->name);
        $response->assertSee($emptyGenre->name);
        $response->assertSee('1冊');
        $response->assertSee('0冊');

        // Bladeに渡された一覧にも、正しい書籍数が入っていることを確認する
        $genres = $response->viewData('genres');

        $this->assertCount(2, $genres);
        $this->assertEquals(1, $genres->firstWhere('id', $genre->id)->books_count);
        $this->assertEquals(0, $genres->firstWhere('id', $emptyGenre->id)->books_count);
    }

    // ジャンルが0件なら、案内文が表示されることを確認する
    public function testEmptyGenreIndexDisplaysMessage(): void
    {
        $this->withoutVite();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('genres.index'))
            ->assertOk()
            ->assertViewIs('genres.index')
            ->assertSee('ジャンルが登録されていません。');
    }

    // 登録画面と、現在の名前が入った編集画面を表示できることを確認する
    public function testUserCanViewGenreCreateAndEditForms(): void
    {
        $this->withoutVite();

        $user = User::factory()->create();
        $genre = Genre::create(['name' => '旅行']);

        $this->actingAs($user);

        // 登録画面を確認する
        $this->get(route('genres.create'))
            ->assertOk()
            ->assertViewIs('genres.create')
            ->assertSee('ジャンル登録');

        // 編集画面に、保存済みのジャンル名が表示されることを確認する
        $this->get(route('genres.edit', $genre))
            ->assertOk()
            ->assertViewIs('genres.edit')
            ->assertSee('value="旅行"', false);
    }

    // 詳細画面に、対象ジャンルの書籍だけが10件ずつ表示されることを確認する
    public function testGenreDetailsShowOnlyRelatedBooksWithPagination(): void
    {
        $this->withoutVite();

        $user = User::factory()->create();
        $genre = Genre::create(['name' => '対象ジャンル']);
        $otherGenre = Genre::create(['name' => '別のジャンル']);

        // 対象ジャンルに、11冊の書籍を関連付ける
        foreach (range(1, 11) as $number) {
            $book = $this->createBook($user, sprintf('978123456%04d', $number));
            $book->genres()->attach([$genre->id, $otherGenre->id]);
        }

        // 対象ジャンルには属さない書籍も用意する
        $unrelatedBook = $this->createBook($user);
        $unrelatedBook->genres()->attach($otherGenre->id);

        $this->actingAs($user);

        // 1ページ目を確認する
        $firstResponse = $this->get(route('genres.show', $genre));

        $firstResponse->assertOk();
        $firstResponse->assertViewIs('genres.show');
        $firstResponse->assertSee($genre->name);
        $firstResponse->assertSee($otherGenre->name);
        $firstResponse->assertDontSee($unrelatedBook->title);

        $firstPage = $firstResponse->viewData('books');

        $this->assertCount(10, $firstPage->items());
        $this->assertSame(10, $firstPage->perPage());
        $this->assertSame(11, $firstPage->total());

        // 2ページ目に、残り1冊が表示されることを確認する
        $secondResponse = $this->get(route('genres.show', [
            'genre' => $genre,
            'page' => 2,
        ]));

        $secondResponse->assertOk();

        $secondPage = $secondResponse->viewData('books');

        $this->assertCount(1, $secondPage->items());
        $this->assertSame(2, $secondPage->currentPage());
        $this->assertSame(11, $secondPage->total());
    }

    // 書籍がないジャンルの詳細画面に、案内文が表示されることを確認する
    public function testEmptyGenreDetailsDisplaysMessage(): void
    {
        $this->withoutVite();

        $user = User::factory()->create();
        $genre = Genre::create(['name' => '書籍なしのジャンル']);

        $this->actingAs($user)
            ->get(route('genres.show', $genre))
            ->assertOk()
            ->assertViewIs('genres.show')
            ->assertSee('このジャンルの書籍はまだ登録されていません。');
    }

    // 正しい入力でジャンルを登録できることを確認する
    public function testUserCanStoreGenre(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('genres.store'), [
            'name' => '新しいジャンル',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('genres.index'));
        $response->assertSessionHas('success', 'ジャンルを登録しました。');

        $this->assertDatabaseCount('genres', 1);
        $this->assertDatabaseHas('genres', [
            'name' => '新しいジャンル',
        ]);
    }

    // 同じ名前のままでも更新でき、名前を変えても書籍との関連が残ることを確認する
    public function testUserCanUpdateGenreKeepingBookAssociations(): void
    {
        $user = User::factory()->create();
        $genre = Genre::create(['name' => '更新前のジャンル']);
        $book = $this->createBook($user);

        $book->genres()->attach($genre->id);

        $this->actingAs($user);

        // 自分自身の名前は、重複として扱われないことを確認する
        $sameNameResponse = $this->put(route('genres.update', $genre), [
            'name' => '更新前のジャンル',
        ]);

        $sameNameResponse->assertSessionHasNoErrors();
        $sameNameResponse->assertRedirect(route('genres.index'));

        // 別の名前に更新する
        $response = $this->put(route('genres.update', $genre), [
            'name' => '更新後のジャンル',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('genres.index'));
        $response->assertSessionHas('success', 'ジャンルを更新しました。');

        // 同じジャンルの名前だけが更新されたことを確認する
        $this->assertDatabaseCount('genres', 1);
        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
            'name' => '更新後のジャンル',
        ]);

        // 書籍との関連付けが残っていることを確認する
        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $genre->id,
        ]);
    }

    // 登録・更新の両方で、不正なジャンル名が拒否されることを確認する
    public function testGenreValidationRejectsInvalidNamesOnStoreAndUpdate(): void
    {
        $user = User::factory()->create();
        $existingGenre = Genre::create(['name' => '登録済みジャンル']);
        $genre = Genre::create(['name' => '更新対象ジャンル']);

        // 未入力・重複・文字数超過・文字列以外の入力を用意する
        $cases = [
            [
                'name' => '',
                'message' => 'ジャンル名を入力してください。',
            ],
            [
                'name' => $existingGenre->name,
                'message' => 'このジャンル名はすでに登録されています。',
            ],
            [
                'name' => str_repeat('あ', 256),
                'message' => 'ジャンル名は255文字以内で入力してください。',
            ],
            [
                'name' => ['入力値'],
                'message' => 'ジャンル名の入力内容を確認してください。',
            ],
        ];

        $this->actingAs($user);

        foreach ($cases as $case) {
            // 登録時に、日本語のエラーメッセージが返ることを確認する
            $storeResponse = $this->from(route('genres.create'))
                ->post(route('genres.store'), [
                    'name' => $case['name'],
                ]);

            $storeResponse->assertRedirect(route('genres.create'));
            $storeResponse->assertSessionHasErrors([
                'name' => $case['message'],
            ]);

            // 更新時にも、同じ入力条件が適用されることを確認する
            $updateResponse = $this->from(route('genres.edit', $genre))
                ->put(route('genres.update', $genre), [
                    'name' => $case['name'],
                ]);

            $updateResponse->assertRedirect(route('genres.edit', $genre));
            $updateResponse->assertSessionHasErrors([
                'name' => $case['message'],
            ]);
        }

        // 追加登録も、既存ジャンルの名前の変更も行われていないことを確認する
        $this->assertDatabaseCount('genres', 2);
        $this->assertSame('更新対象ジャンル', $genre->fresh()->name);
        $this->assertSame('登録済みジャンル', $existingGenre->fresh()->name);
    }

    // 書籍がないジャンルだけを削除できることを確認する
    public function testUserCanDeleteUnusedGenre(): void
    {
        $user = User::factory()->create();
        $genre = Genre::create(['name' => '削除対象ジャンル']);
        $otherGenre = Genre::create(['name' => '残すジャンル']);

        // 別のジャンルには、書籍を関連付けておく
        $book = $this->createBook($user);
        $book->genres()->attach($otherGenre->id);

        $response = $this->actingAs($user)
            ->delete(route('genres.destroy', $genre));

        $response->assertRedirect(route('genres.index'));
        $response->assertSessionHas('success', 'ジャンルを削除しました。');

        $this->assertDatabaseMissing('genres', [
            'id' => $genre->id,
        ]);

        // 別のジャンル・書籍・関連付けは残っていることを確認する
        $this->assertDatabaseHas('genres', [
            'id' => $otherGenre->id,
            'name' => '残すジャンル',
        ]);
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
        ]);
        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $otherGenre->id,
        ]);
    }

    // 書籍があるジャンルは削除できないことを確認する
    public function testUserCannotDeleteGenreWithBooks(): void
    {
        $this->withoutVite();

        // 書籍の登録者と、ジャンルを操作するユーザーを別々に用意する
        $owner = User::factory()->create();
        $user = User::factory()->create();
        $genre = Genre::create(['name' => '書籍があるジャンル']);
        $book = $this->createBook($owner);

        $book->genres()->attach($genre->id);

        $response = $this->actingAs($user)
            ->delete(route('genres.destroy', $genre));

        $response->assertRedirect(route('genres.index'));
        $response->assertSessionHas(
            'error',
            '書籍が登録されているジャンルは削除できません。'
        );

        // ジャンル・書籍・関連付けが残っていることを確認する
        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
        ]);
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
        ]);
        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $genre->id,
        ]);

        // 一覧画面に、削除できない理由が表示されることを確認する
        $this->get(route('genres.index'))
            ->assertOk()
            ->assertSee('書籍が登録されているジャンルは削除できません。');
    }

    // 未ログインでは、ジャンルのすべての操作ができないことを確認する
    public function testGuestCannotAccessGenreActions(): void
    {
        $genre = Genre::create(['name' => '変更しないジャンル']);

        // 一覧・詳細・登録画面・編集画面へのアクセスを確認する
        $this->get(route('genres.index'))->assertRedirect('/login');
        $this->get(route('genres.show', $genre))->assertRedirect('/login');
        $this->get(route('genres.create'))->assertRedirect('/login');
        $this->get(route('genres.edit', $genre))->assertRedirect('/login');

        // 登録・更新・削除のリクエストも拒否されることを確認する
        $this->post(route('genres.store'), [
            'name' => '追加しないジャンル',
        ])->assertRedirect('/login');

        $this->put(route('genres.update', $genre), [
            'name' => '更新しないジャンル',
        ])->assertRedirect('/login');

        $this->delete(route('genres.destroy', $genre))
            ->assertRedirect('/login');

        $this->assertGuest();

        // 登録済みの1件が、元の名前のまま残っていることを確認する
        $this->assertDatabaseCount('genres', 1);
        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
            'name' => '変更しないジャンル',
        ]);
    }

    // 存在しないジャンルへの操作では、404になることを確認する
    public function testMissingGenreReturnsNotFound(): void
    {
        $user = User::factory()->create();
        $genre = Genre::create(['name' => '削除済みジャンル']);

        // IDを控えてから削除する
        $genreId = $genre->id;
        $genre->delete();

        $this->actingAs($user);

        $this->get(route('genres.show', $genreId))->assertNotFound();
        $this->get(route('genres.edit', $genreId))->assertNotFound();

        $this->put(route('genres.update', $genreId), [
            'name' => '更新できないジャンル',
        ])->assertNotFound();

        $this->delete(route('genres.destroy', $genreId))
            ->assertNotFound();

        $this->assertDatabaseCount('genres', 0);
    }

    // 各テストで使う書籍を作成する共通の準備処理
    private function createBook(
        User $owner,
        string $isbn = '9781234567897'
    ): Book {
        return $owner->books()->create([
            'title' => "ジャンル確認用書籍（{$isbn}）",
            'author' => 'テスト著者',
            'isbn' => $isbn,
            'published_date' => '2020-01-01',
        ]);
    }
}
