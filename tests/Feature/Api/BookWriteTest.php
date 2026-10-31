<?php

namespace Tests\Feature\Api;

// テスト用の書籍を扱うモデルを読み込む
use App\Models\Book;
// テスト用のジャンルを扱うモデルを読み込む
use App\Models\Genre;
// テスト用のレビューを扱うモデルを読み込む
use App\Models\Review;
// テスト用のユーザーを作成するモデルを読み込む
use App\Models\User;
// テスト用のテーブル準備と、テスト後のデータ変更の取り消しを行う
use Illuminate\Foundation\Testing\RefreshDatabase;
// データベースの接続先を確認する機能を読み込む
use Illuminate\Support\Facades\DB;
// APIの応答を確認するテスト用クラスを読み込む
use Illuminate\Testing\TestResponse;
// Laravelのテスト用基本クラスを読み込む
use Tests\TestCase;

// 公開APIの書籍登録・更新・削除を確認する
class BookWriteTest extends TestCase
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

    // ゲストが、指定した登録者とジャンルで書籍を登録できることを確認する
    public function testGuestCanStoreBookWithGenres(): void
    {
        $owner = User::factory()->create();
        $genre = Genre::create(['name' => '小説']);
        $otherGenre = Genre::create(['name' => '歴史']);

        $payload = $this->validPayload($owner, $genre, [
            'genres' => [$genre->id, $otherGenre->id],
        ]);

        $response = $this->postJson(route('api.v1.books.store'), $payload);

        // 登録成功の201と、登録したデータが返ることを確認する
        $response->assertStatus(201);
        $response->assertJsonPath('data.user_id', $owner->id);
        $response->assertJsonPath('data.title', $payload['title']);
        $response->assertJsonPath('data.published_date', '2020-01-01');
        $response->assertJsonCount(2, 'data.genres');
        $response->assertJsonPath('data.reviews_count', 0);
        $response->assertJsonPath('data.reviews_avg_rating', null);

        $bookId = $response->json('data.id');

        $this->assertIsInt($bookId);

        // 書籍本体が保存されたことを確認する
        $this->assertDatabaseCount('books', 1);
        $this->assertDatabaseHas('books', [
            'id' => $bookId,
            'user_id' => $owner->id,
            'title' => $payload['title'],
            'author' => $payload['author'],
            'isbn' => $payload['isbn'],
            'published_date' => $payload['published_date'],
            'description' => $payload['description'],
            'image_url' => $payload['image_url'],
        ]);

        // 選択した2つのジャンルが関連付けられたことを確認する
        $this->assertDatabaseCount('book_genre', 2);

        foreach ([$genre->id, $otherGenre->id] as $genreId) {
            $this->assertDatabaseHas('book_genre', [
                'book_id' => $bookId,
                'genre_id' => $genreId,
            ]);
        }

        $this->assertEqualsCanonicalizing(
            [$genre->id, $otherGenre->id],
            array_column($response->json('data.genres'), 'id')
        );

        $this->assertGuest();
    }

    // 説明と画像URLを送信しなくても、登録できることを確認する
    public function testBookStoreAllowsOmittedOptionalFields(): void
    {
        $owner = User::factory()->create();
        $genre = Genre::create(['name' => '小説']);

        $payload = $this->validPayload($owner, $genre);

        // 任意項目を送信内容から取り除く
        unset($payload['description'], $payload['image_url']);

        $response = $this->postJson(route('api.v1.books.store'), $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('data.description', null);
        $response->assertJsonPath('data.image_url', null);

        $this->assertDatabaseHas('books', [
            'id' => $response->json('data.id'),
            'description' => null,
            'image_url' => null,
        ]);
    }

    // 同じISBNのまま更新でき、ジャンルと対象書籍が正しく扱われることを確認する
    public function testGuestCanUpdateBookKeepingSameIsbnAndReplacingGenres(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $oldGenre = Genre::create(['name' => '変更前ジャンル']);
        $newGenre = Genre::create(['name' => '変更後ジャンル']);

        $book = $this->createBook($owner, $oldGenre);
        $otherBook = $this->createBook($otherOwner, $oldGenre, '9781234567804');

        $reviewer = User::factory()->create();
        $review = $this->createReview($book, $reviewer);

        $payload = $this->validPayload($owner, $newGenre, [
            'title' => 'API更新後の書籍',
            'author' => 'API更新後の著者',
            'isbn' => $book->isbn,
            'published_date' => '2021-02-03',
            // URLとは異なるIDを含めても、更新対象が変わらないことを確認する
            'id' => $otherBook->id,
            'book_id' => $otherBook->id,
        ]);

        $response = $this->putJson(route('api.v1.books.update', $book), $payload);

        $response->assertOk();
        $response->assertJsonPath('data.id', $book->id);
        $response->assertJsonPath('data.title', 'API更新後の書籍');
        $response->assertJsonPath('data.published_date', '2021-02-03');
        $response->assertJsonCount(1, 'data.genres');
        $response->assertJsonPath('data.genres.0.id', $newGenre->id);

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $owner->id,
            'title' => 'API更新後の書籍',
            'author' => 'API更新後の著者',
            'isbn' => $book->isbn,
            'published_date' => '2021-02-03',
        ]);

        // 対象書籍のジャンルだけが置き換わることを確認する
        $this->assertDatabaseMissing('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $oldGenre->id,
        ]);
        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $newGenre->id,
        ]);

        // 書籍を更新しても、既存レビューは残ることを確認する
        $response->assertJsonPath('data.reviews_count', 1);
        $response->assertJsonPath('data.reviews.0.id', $review->id);

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'book_id' => $book->id,
        ]);

        // 別の書籍と、そのジャンルは変更されていないことを確認する
        $this->assertDatabaseHas('books', [
            'id' => $otherBook->id,
            'user_id' => $otherOwner->id,
            'title' => $otherBook->title,
            'isbn' => $otherBook->isbn,
        ]);
        $this->assertDatabaseHas('book_genre', [
            'book_id' => $otherBook->id,
            'genre_id' => $oldGenre->id,
        ]);

        $this->assertGuest();
    }

    // ISBN・登録者IDの変更と、任意項目の解除ができることを確認する
    public function testBookUpdateCanChangeIsbnAndClearOptionalFields(): void
    {
        $owner = User::factory()->create();
        $newOwner = User::factory()->create();
        $genre = Genre::create(['name' => '小説']);
        $book = $this->createBook($owner, $genre);

        $payload = $this->validPayload($newOwner, $genre, [
            'isbn' => '9781234567804',
            'description' => '',
            'image_url' => null,
        ]);

        $response = $this->putJson(route('api.v1.books.update', $book), $payload);

        $response->assertOk();
        $response->assertJsonPath('data.user_id', $newOwner->id);
        $response->assertJsonPath('data.isbn', '9781234567804');
        $response->assertJsonPath('data.description', null);
        $response->assertJsonPath('data.image_url', null);

        // 今回のAPIでは、指定された有効な登録者IDも更新内容として反映する
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $newOwner->id,
            'isbn' => '9781234567804',
            'description' => null,
            'image_url' => null,
        ]);
    }

    // 登録・更新の両方で、必須項目が確認されることを確認する
    public function testBookStoreAndUpdateRequireRequiredFields(): void
    {
        $owner = User::factory()->create();
        $genre = Genre::create(['name' => '小説']);
        $book = $this->createBook($owner, $genre);

        $messages = [
            'user_id' => '登録者を指定してください。',
            'title' => 'タイトルを入力してください。',
            'author' => '著者名を入力してください。',
            'isbn' => 'ISBNを入力してください。',
            'published_date' => '出版日を入力してください。',
            'genres' => 'ジャンルを1つ以上選択してください。',
        ];

        $this->assertInputErrors(
            $this->postJson(route('api.v1.books.store'), []),
            $messages
        );

        $this->assertInputErrors(
            $this->putJson(route('api.v1.books.update', $book), []),
            $messages
        );

        // 新規登録も、既存書籍の変更も行われていないことを確認する
        $this->assertDatabaseCount('books', 1);
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => $book->title,
            'isbn' => $book->isbn,
        ]);
    }

    // 登録・更新の両方で、不正な入力が拒否されることを確認する
    public function testBookStoreAndUpdateRejectInvalidFields(): void
    {
        $owner = User::factory()->create();
        $genre = Genre::create(['name' => '小説']);
        $book = $this->createBook($owner, $genre);

        $cases = [
            [
                'changes' => ['user_id' => 'abc'],
                'field' => 'user_id',
                'message' => '登録者の指定内容を確認してください。',
            ],
            [
                'changes' => ['user_id' => 999999],
                'field' => 'user_id',
                'message' => '指定された登録者は登録されていません。',
            ],
            [
                'changes' => ['isbn' => '123'],
                'field' => 'isbn',
                'message' => 'ISBNは半角数字13桁で入力してください。',
            ],
            [
                'changes' => ['published_date' => '2025-02-30'],
                'field' => 'published_date',
                'message' => '出版日の入力内容を確認してください。',
            ],
            [
                'changes' => ['genres' => []],
                'field' => 'genres',
                'message' => 'ジャンルを1つ以上選択してください。',
            ],
            [
                'changes' => ['genres' => [999999]],
                'field' => 'genres.0',
                'message' => 'ジャンルの選択内容を確認してください。',
            ],
            [
                'changes' => ['image_url' => 'not-a-url'],
                'field' => 'image_url',
                'message' => '画像URLの入力内容を確認してください。',
            ],
            [
                'changes' => ['description' => str_repeat('あ', 501)],
                'field' => 'description',
                'message' => '説明は500文字以内で入力してください。',
            ],
        ];

        foreach ($cases as $case) {
            // 新規登録時にも重複しない、未使用のISBNを基本値にする
            $payload = $this->validPayload($owner, $genre, [
                'isbn' => '9781234567804',
            ]);
            $payload = array_merge($payload, $case['changes']);

            $messages = [$case['field'] => $case['message']];

            $this->assertInputErrors(
                $this->postJson(route('api.v1.books.store'), $payload),
                $messages
            );

            $this->assertInputErrors(
                $this->putJson(route('api.v1.books.update', $book), $payload),
                $messages
            );
        }

        // 入力エラーの間、書籍とジャンルの関連付けが変わっていないことを確認する
        $this->assertDatabaseCount('books', 1);
        $this->assertDatabaseCount('book_genre', 1);
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $owner->id,
            'title' => $book->title,
            'author' => $book->author,
            'isbn' => $book->isbn,
            'description' => $book->description,
            'image_url' => $book->image_url,
        ]);
        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $genre->id,
        ]);
    }

    // 登録・更新の両方で、他の書籍とISBNが重複しないことを確認する
    public function testBookStoreAndUpdateRejectDuplicateIsbn(): void
    {
        $owner = User::factory()->create();
        $genre = Genre::create(['name' => '小説']);
        $book = $this->createBook($owner, $genre);
        $otherBook = $this->createBook($owner, $genre, '9781234567804');

        $messages = [
            'isbn' => 'このISBNはすでに登録されています。',
        ];

        // 登録済みISBNで、新規登録を試みる
        $this->assertInputErrors(
            $this->postJson(
                route('api.v1.books.store'),
                $this->validPayload($owner, $genre, ['isbn' => $book->isbn])
            ),
            $messages
        );

        // 別の書籍が使っているISBNへ、更新を試みる
        $this->assertInputErrors(
            $this->putJson(
                route('api.v1.books.update', $book),
                $this->validPayload($owner, $genre, ['isbn' => $otherBook->isbn])
            ),
            $messages
        );

        $this->assertDatabaseCount('books', 2);
        $this->assertSame('9781234567897', $book->fresh()->isbn);
        $this->assertSame('9781234567804', $otherBook->fresh()->isbn);
    }

    // 書籍削除時に、その書籍の関連データだけが削除されることを確認する
    public function testGuestCanDeleteBookWithOnlyItsRelatedData(): void
    {
        $owner = User::factory()->create();
        $reviewer = User::factory()->create();
        $liker = User::factory()->create();

        $genre = Genre::create(['name' => '対象ジャンル']);
        $otherGenre = Genre::create(['name' => '残す書籍のジャンル']);

        $book = $this->createBook($owner, $genre);
        $otherBook = $this->createBook($owner, $otherGenre, '9781234567804');

        // 削除対象の書籍には、2つのジャンルを関連付ける
        $book->genres()->attach($otherGenre->id);

        $review = $this->createReview($book, $reviewer);
        $otherReview = $this->createReview($otherBook, $reviewer);

        // 両方の書籍・レビューに、お気に入りといいねを用意する
        $liker->favoriteBooks()->attach([$book->id, $otherBook->id]);
        $liker->likedReviews()->attach([$review->id, $otherReview->id]);

        // 本文なしの204が返ることを確認する
        $this->deleteJson(route('api.v1.books.destroy', $book))
            ->assertNoContent();

        // 対象書籍と、その関連データが削除されたことを確認する
        $this->assertDatabaseMissing('books', ['id' => $book->id]);
        $this->assertDatabaseMissing('reviews', ['book_id' => $book->id]);
        $this->assertDatabaseMissing('book_genre', ['book_id' => $book->id]);
        $this->assertDatabaseMissing('favorites', ['book_id' => $book->id]);
        $this->assertDatabaseMissing('review_likes', ['review_id' => $review->id]);

        // 別の書籍と、その関連データは残っていることを確認する
        $this->assertDatabaseHas('books', ['id' => $otherBook->id]);
        $this->assertDatabaseHas('reviews', [
            'id' => $otherReview->id,
            'book_id' => $otherBook->id,
        ]);
        $this->assertDatabaseHas('book_genre', [
            'book_id' => $otherBook->id,
            'genre_id' => $otherGenre->id,
        ]);
        $this->assertDatabaseHas('favorites', [
            'user_id' => $liker->id,
            'book_id' => $otherBook->id,
        ]);
        $this->assertDatabaseHas('review_likes', [
            'user_id' => $liker->id,
            'review_id' => $otherReview->id,
        ]);

        // ジャンル本体とユーザーは削除されていないことを確認する
        $this->assertDatabaseCount('genres', 2);
        $this->assertDatabaseCount('users', 3);
        $this->assertGuest();
    }

    // 存在しない書籍の更新・削除は、404になることを確認する
    public function testMissingBookReturnsNotFoundOnUpdateAndDelete(): void
    {
        $owner = User::factory()->create();
        $genre = Genre::create(['name' => '小説']);
        $book = $this->createBook($owner, $genre);

        $bookId = $book->id;
        $book->delete();

        $this->putJson(
            route('api.v1.books.update', $bookId),
            $this->validPayload($owner, $genre)
        )->assertNotFound();

        $this->deleteJson(route('api.v1.books.destroy', $bookId))
            ->assertNotFound();

        $this->assertDatabaseCount('books', 0);
    }

    // 基本となる正しい送信内容を用意し、必要な項目だけ差し替える
    private function validPayload(
        User $owner,
        Genre $genre,
        array $overrides = []
    ): array {
        return array_merge([
            'user_id' => $owner->id,
            'title' => 'API登録用の書籍',
            'author' => 'API著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
            'description' => 'API確認用の説明です。',
            'image_url' => 'https://example.com/cover.jpg',
            'genres' => [$genre->id],
        ], $overrides);
    }

    // 更新・削除の対象となる、ジャンル付きの書籍を用意する
    private function createBook(
        User $owner,
        Genre $genre,
        string $isbn = '9781234567897'
    ): Book {
        $book = $owner->books()->create([
            'title' => 'API変更前の書籍',
            'author' => '変更前の著者',
            'isbn' => $isbn,
            'published_date' => '2020-01-01',
            'description' => '変更前の説明です。',
            'image_url' => 'https://example.com/original.jpg',
        ]);

        $book->genres()->attach($genre->id);

        return $book;
    }

    // 対象書籍に、確認用のレビューを作成する
    private function createReview(Book $book, User $reviewer): Review
    {
        return $book->reviews()->create([
            'user_id' => $reviewer->id,
            'rating' => 4,
            'comment' => 'API書籍のレビューです。',
        ]);
    }

    // 日本語の入力エラーメッセージを確認する共通処理
    private function assertInputErrors(TestResponse $response, array $messages): void
    {
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(array_keys($messages));

        $errors = $response->json('errors');

        foreach ($messages as $field => $message) {
            // genres.0のような、配列内の項目名にも対応する
            $this->assertSame($message, $errors[$field][0]);
        }
    }
}
