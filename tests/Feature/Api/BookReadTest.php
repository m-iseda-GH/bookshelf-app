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
// Laravelのテスト用基本クラスを読み込む
use Tests\TestCase;

// 公開APIの書籍一覧・詳細を確認する
class BookReadTest extends TestCase
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

    // ゲストが、ジャンル・平均評価・レビュー件数付きの一覧を取得できることを確認する
    public function testGuestCanGetBookIndexWithGenresAndReviewStatistics(): void
    {
        $owner = User::factory()->create();
        $genre = Genre::create(['name' => '小説']);
        $book = $this->createBook($owner);

        $book->genres()->attach($genre->id);

        // 4点と5点のレビューを用意し、平均4.5点にする
        $this->createReview($book, 4);
        $this->createReview($book, 5);

        // JSONで結果を受け取るリクエストを送る
        $response = $this->getJson(route('api.v1.books.index'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $book->id);
        $response->assertJsonPath('data.0.user_id', $owner->id);
        $response->assertJsonPath('data.0.title', $book->title);
        $response->assertJsonPath('data.0.published_date', '2020-01-01');
        $response->assertJsonPath('data.0.reviews_avg_rating', 4.5);
        $response->assertJsonPath('data.0.reviews_count', 2);
        $response->assertJsonPath('data.0.genres.0.id', $genre->id);
        $response->assertJsonPath('data.0.genres.0.name', '小説');

        // 一覧では、レビュー本文の一覧を含めないことを確認する
        $response->assertJsonMissingPath('data.0.reviews');

        $this->assertGuest();
    }

    // 登録日時が新しい順、同じ日時ならIDの大きい順になることを確認する
    public function testBookIndexOrdersByNewestRegistrationDate(): void
    {
        $owner = User::factory()->create();

        $firstBook = $this->createBook($owner, '9781234567897');
        $secondBook = $this->createBook($owner, '9781234567804');
        $olderBook = $this->createBook($owner, '9781234567811');

        // IDだけの順序では判別できないよう、登録日時を明示する
        $firstBook->forceFill(['created_at' => '2025-01-02 12:00:00'])->save();
        $secondBook->forceFill(['created_at' => '2025-01-02 12:00:00'])->save();
        $olderBook->forceFill(['created_at' => '2025-01-01 12:00:00'])->save();

        $response = $this->getJson(route('api.v1.books.index'));

        $response->assertOk();

        // JSONの一覧からIDだけを取り出して、並び順を確認する
        $this->assertSame([
            $secondBook->id,
            $firstBook->id,
            $olderBook->id,
        ], array_column($response->json('data'), 'id'));
    }

    // タイトルと著者名の、どちらも部分一致で検索できることを確認する
    public function testBookIndexSearchesTitlesAndAuthors(): void
    {
        $owner = User::factory()->create();

        $titleBook = $this->createBook($owner, '9781234567897', 'PHPの基礎');
        $authorBook = $this->createBook(
            $owner,
            '9781234567804',
            '開発の本',
            'PHP研究会'
        );
        $this->createBook($owner, '9781234567811', '別の書籍');

        $response = $this->getJson(route('api.v1.books.index', [
            'keyword' => 'PHP',
        ]));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        // 順序に関係なく、検索に一致する2冊だけが取得されることを確認する
        $this->assertEqualsCanonicalizing(
            [$titleBook->id, $authorBook->id],
            array_column($response->json('data'), 'id')
        );
    }

    // 文字列の「0」も、検索キーワードとして使えることを確認する
    public function testBookIndexCanSearchForZero(): void
    {
        $owner = User::factory()->create();

        $book = $this->createBook(
            $owner,
            '9781234567897',
            '0から始めるプログラミング'
        );
        $this->createBook($owner, '9781234567804', '別の書籍');

        $response = $this->getJson(route('api.v1.books.index', [
            'keyword' => '0',
        ]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $book->id);
    }

    // ジャンル単独の絞り込みと、キーワードとの組み合わせを確認する
    public function testBookIndexFiltersByGenreAndKeyword(): void
    {
        $owner = User::factory()->create();
        $genre = Genre::create(['name' => '対象ジャンル']);
        $otherGenre = Genre::create(['name' => '別のジャンル']);

        $titleBook = $this->createBook($owner, '9781234567897', 'PHPの基本');
        $authorBook = $this->createBook(
            $owner,
            '9781234567804',
            '開発の本',
            'PHP研究会'
        );
        $otherGenreBook = $this->createBook($owner, '9781234567811', 'PHPの応用');
        $nonMatchingBook = $this->createBook($owner, '9781234567828', '別の書籍');

        $titleBook->genres()->attach($genre->id);
        $authorBook->genres()->attach($genre->id);
        $nonMatchingBook->genres()->attach($genre->id);
        $otherGenreBook->genres()->attach($otherGenre->id);

        // ジャンルだけで絞り込むと、対象ジャンルの3冊が返ることを確認する
        $genreResponse = $this->getJson(route('api.v1.books.index', [
            'genre_id' => $genre->id,
        ]));

        $genreResponse->assertOk();
        $genreResponse->assertJsonCount(3, 'data');

        $this->assertEqualsCanonicalizing([
            $titleBook->id,
            $authorBook->id,
            $nonMatchingBook->id,
        ], array_column($genreResponse->json('data'), 'id'));

        // ジャンルとキーワードの両方に一致する書籍だけを取得する
        $combinedResponse = $this->getJson(route('api.v1.books.index', [
            'genre_id' => $genre->id,
            'keyword' => 'PHP',
        ]));

        $combinedResponse->assertOk();
        $combinedResponse->assertJsonCount(2, 'data');

        $this->assertEqualsCanonicalizing(
            [$titleBook->id, $authorBook->id],
            array_column($combinedResponse->json('data'), 'id')
        );
    }

    // 件数指定・ページ指定と、次のページへの検索条件の引き継ぎを確認する
    public function testBookIndexPaginatesAndPreservesFilters(): void
    {
        $owner = User::factory()->create();
        $genre = Genre::create(['name' => 'ページ送り用ジャンル']);

        foreach (range(1, 11) as $number) {
            $book = $this->createBook(
                $owner,
                sprintf('978123456%04d', $number),
                'ページ送り用の書籍'
            );

            $book->genres()->attach($genre->id);
        }

        // 指定がなければ、1ページ10件になることを確認する
        $this->getJson(route('api.v1.books.index'))
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 11);

        // 1ページ2件として、2ページ目を取得する
        $response = $this->getJson(route('api.v1.books.index', [
            'keyword' => 'ページ送り',
            'genre_id' => $genre->id,
            'page' => 2,
            'per_page' => 2,
        ]));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.current_page', 2);
        $response->assertJsonPath('meta.per_page', 2);
        $response->assertJsonPath('meta.total', 11);

        // 次のページのURLから、検索条件を取り出す
        $nextUrl = $response->json('links.next');

        $this->assertIsString($nextUrl);

        parse_str((string) parse_url($nextUrl, PHP_URL_QUERY), $nextQuery);

        $this->assertSame('ページ送り', $nextQuery['keyword']);
        $this->assertSame((string) $genre->id, $nextQuery['genre_id']);
        $this->assertSame('2', $nextQuery['per_page']);
        $this->assertSame('3', $nextQuery['page']);
    }

    // 検索に一致する書籍がなければ、空の一覧が返ることを確認する
    public function testBookIndexReturnsEmptyDataWhenNothingMatches(): void
    {
        $owner = User::factory()->create();
        $this->createBook($owner, '9781234567897', 'PHP入門');

        $this->getJson(route('api.v1.books.index', [
            'keyword' => '存在しないキーワード',
        ]))
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    // 検索条件が不正な場合に、日本語の入力エラーが返ることを確認する
    public function testBookIndexRejectsInvalidParameters(): void
    {
        $cases = [
            [
                'query' => ['keyword' => ['PHP']],
                'field' => 'keyword',
                'message' => 'キーワードの入力内容を確認してください。',
            ],
            [
                'query' => ['keyword' => str_repeat('あ', 256)],
                'field' => 'keyword',
                'message' => 'キーワードは255文字以内で入力してください。',
            ],
            [
                'query' => ['genre_id' => 'abc'],
                'field' => 'genre_id',
                'message' => 'ジャンルの指定内容を確認してください。',
            ],
            [
                'query' => ['genre_id' => 999999],
                'field' => 'genre_id',
                'message' => '指定されたジャンルは登録されていません。',
            ],
            [
                'query' => ['page' => 0],
                'field' => 'page',
                'message' => 'ページ番号は1以上の整数で指定してください。',
            ],
            [
                'query' => ['page' => 'abc'],
                'field' => 'page',
                'message' => 'ページ番号は1以上の整数で指定してください。',
            ],
            [
                'query' => ['page' => ''],
                'field' => 'page',
                'message' => 'ページ番号を指定してください。',
            ],
            [
                'query' => ['per_page' => 0],
                'field' => 'per_page',
                'message' => '1ページの件数は1〜100の整数で指定してください。',
            ],
            [
                'query' => ['per_page' => 101],
                'field' => 'per_page',
                'message' => '1ページの件数は1〜100の整数で指定してください。',
            ],
            [
                'query' => ['per_page' => 'abc'],
                'field' => 'per_page',
                'message' => '1ページの件数は1〜100の整数で指定してください。',
            ],
            [
                'query' => ['per_page' => ''],
                'field' => 'per_page',
                'message' => '1ページの件数を指定してください。',
            ],
        ];

        foreach ($cases as $case) {
            $field = $case['field'];

            $response = $this->getJson(route('api.v1.books.index', $case['query']));

            // 422は、入力内容に問題があることを表すステータスコード
            $response->assertStatus(422);
            $response->assertJsonValidationErrors([$field]);
            $response->assertJsonPath("errors.{$field}.0", $case['message']);
        }
    }

    // ゲストが、ジャンルとレビュー付きの書籍詳細を取得できることを確認する
    public function testGuestCanGetBookDetailsWithReviews(): void
    {
        $owner = User::factory()->create();
        $genre = Genre::create(['name' => '詳細確認用ジャンル']);
        $book = $this->createBook($owner);
        $book->genres()->attach($genre->id);

        $review = $this->createReview($book, 5);

        $response = $this->getJson(route('api.v1.books.show', $book));

        $response->assertOk();
        $response->assertJsonPath('data.id', $book->id);
        $response->assertJsonPath('data.title', $book->title);
        $response->assertJsonPath('data.genres.0.id', $genre->id);
        $response->assertJsonPath('data.reviews_count', 1);
        $response->assertJsonCount(1, 'data.reviews');

        // レビューの投稿者名・評価・コメント・投稿日時を確認する
        $response->assertJsonPath('data.reviews.0.id', $review->id);
        $response->assertJsonPath('data.reviews.0.user_name', $review->user->name);
        $response->assertJsonPath('data.reviews.0.rating', 5);
        $response->assertJsonPath('data.reviews.0.comment', $review->comment);
        $response->assertJsonPath(
            'data.reviews.0.created_at',
            $review->fresh()->created_at->toISOString()
        );

        $this->assertEquals(5, $response->json('data.reviews_avg_rating'));
        $this->assertGuest();
    }

    // ジャンル・レビューがない書籍も、詳細を取得できることを確認する
    public function testBookDetailsIncludeEmptyRelationsForUnreviewedBook(): void
    {
        $owner = User::factory()->create();
        $book = $this->createBook($owner);

        $this->getJson(route('api.v1.books.show', $book))
            ->assertOk()
            ->assertJsonPath('data.id', $book->id)
            ->assertJsonPath('data.genres', [])
            ->assertJsonPath('data.reviews', [])
            ->assertJsonPath('data.reviews_count', 0)
            ->assertJsonPath('data.reviews_avg_rating', null);
    }

    // 存在しない書籍の詳細は、404になることを確認する
    public function testMissingBookReturnsNotFound(): void
    {
        $owner = User::factory()->create();
        $book = $this->createBook($owner);

        $bookId = $book->id;
        $book->delete();

        $this->getJson(route('api.v1.books.show', $bookId))
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    // 各テストで使う書籍を作成する共通の準備処理
    private function createBook(
        User $owner,
        string $isbn = '9781234567897',
        string $title = 'API確認用の書籍',
        string $author = 'テスト著者'
    ): Book {
        return $owner->books()->create([
            'title' => $title,
            'author' => $author,
            'isbn' => $isbn,
            'published_date' => '2020-01-01',
        ]);
    }

    // 指定した点数のレビューを、別の投稿者で作成する共通の準備処理
    private function createReview(Book $book, int $rating): Review
    {
        $reviewer = User::factory()->create([
            'name' => 'APIレビュー投稿者',
        ]);

        return $book->reviews()->create([
            'user_id' => $reviewer->id,
            'rating' => $rating,
            'comment' => 'API確認用のレビューです。',
        ]);
    }
}
