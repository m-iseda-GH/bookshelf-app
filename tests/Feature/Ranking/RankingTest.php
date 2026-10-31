<?php

namespace Tests\Feature\Ranking;

// テスト用の書籍を扱うモデルを読み込む
use App\Models\Book;
// テスト用のユーザーを作成するモデルを読み込む
use App\Models\User;
// テスト用のテーブル準備と、テスト後のデータ変更の取り消しを行う
use Illuminate\Foundation\Testing\RefreshDatabase;
// データベースの接続先を確認する機能を読み込む
use Illuminate\Support\Facades\DB;
// Laravelのテスト用基本クラスを読み込む
use Tests\TestCase;

// ランキングの表示と集計内容を確認する
class RankingTest extends TestCase
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

    // ゲストが、平均評価の高い順にランキングを閲覧できることを確認する
    public function testGuestCanViewBooksOrderedByAverageRating(): void
    {
        $this->withoutVite();

        $owner = User::factory()->create();

        // 平均4点・5点・3点の書籍を、この順番で登録する
        $middleBook = $this->createBook($owner, '9781234567897', [5, 3]);
        $highBook = $this->createBook($owner, '9781234567804', [5]);
        $lowBook = $this->createBook($owner, '9781234567811', [3, 3, 3]);

        // ログインせずにランキング画面を開く
        $response = $this->get(route('ranking.index'));

        $response->assertOk();
        $response->assertViewIs('ranking.index');

        // 画面上でも、平均評価の高い順に表示されることを確認する
        $response->assertSeeInOrder([
            $highBook->title,
            $middleBook->title,
            $lowBook->title,
        ]);

        // Bladeに渡された書籍の並び順を確認する
        $rankedBooks = $response->viewData('rankedBooks');

        $this->assertSame([
            $highBook->id,
            $middleBook->id,
            $lowBook->id,
        ], $rankedBooks->modelKeys());

        // 5点と3点の平均が4点、レビュー件数が2件であることを確認する
        $middleRankedBook = $rankedBooks->firstWhere('id', $middleBook->id);

        $this->assertEquals(4, $middleRankedBook->reviews_avg_rating);
        $this->assertEquals(2, $middleRankedBook->reviews_count);

        // 画面に平均評価とレビュー件数が表示されることを確認する
        $response->assertSee('4.00');
        $response->assertSee('(2件のレビュー)');

        $this->assertGuest();
    }

    // ログイン中も、ランキングを閲覧できることを確認する
    public function testAuthenticatedUserCanViewRanking(): void
    {
        $this->withoutVite();

        $user = User::factory()->create();
        $book = $this->createBook($user, '9781234567897', [4]);

        $this->actingAs($user)
            ->get(route('ranking.index'))
            ->assertOk()
            ->assertViewIs('ranking.index')
            ->assertSee($book->title);

        $this->assertAuthenticatedAs($user);
    }

    // レビューがない書籍は、ランキングに含まれないことを確認する
    public function testRankingExcludesBooksWithoutReviews(): void
    {
        $this->withoutVite();

        $owner = User::factory()->create();

        $unreviewedBook = $this->createBook($owner, '9781234567897');
        $reviewedBook = $this->createBook($owner, '9781234567804', [1]);

        $response = $this->get(route('ranking.index'));

        $response->assertOk();
        $response->assertSee($reviewedBook->title);
        $response->assertDontSee($unreviewedBook->title);

        // レビューがある1冊だけが取得されていることを確認する
        $this->assertSame(
            [$reviewedBook->id],
            $response->viewData('rankedBooks')->modelKeys()
        );
    }

    // 書籍が11冊以上あっても、上位10冊だけ表示されることを確認する
    public function testRankingDisplaysOnlyTopTenBooks(): void
    {
        $this->withoutVite();

        $owner = User::factory()->create();

        // 最初に、ランキングから外れる平均1点の書籍を用意する
        $lowBook = $this->createBook($owner, '9781234567897', [1]);

        // 続けて、平均5点の書籍を10冊用意する
        $expectedIds = [];

        foreach (range(1, 10) as $number) {
            $book = $this->createBook(
                $owner,
                sprintf('978123456%04d', $number),
                [5]
            );

            $expectedIds[] = $book->id;
        }

        $response = $this->get(route('ranking.index'));

        $response->assertOk();

        $rankedBooks = $response->viewData('rankedBooks');

        // 平均5点の10冊だけが取得されていることを確認する
        $this->assertCount(10, $rankedBooks);
        $this->assertSame($expectedIds, $rankedBooks->modelKeys());

        // 平均1点の書籍は、画面にも表示されないことを確認する
        $response->assertDontSee($lowBook->title);
    }

    // 平均評価が同じ場合は、書籍IDの小さい順になることを確認する
    public function testEqualRatingsAreOrderedByBookId(): void
    {
        $this->withoutVite();

        $owner = User::factory()->create();

        // レビュー件数は異なるが、平均評価はすべて4点にする
        $firstBook = $this->createBook($owner, '9781234567897', [5, 3]);
        $secondBook = $this->createBook($owner, '9781234567804', [4]);
        $thirdBook = $this->createBook($owner, '9781234567811', [4, 4, 4]);

        $response = $this->get(route('ranking.index'));

        $response->assertOk();

        $this->assertSame([
            $firstBook->id,
            $secondBook->id,
            $thirdBook->id,
        ], $response->viewData('rankedBooks')->modelKeys());
    }

    // レビューがある書籍が0冊なら、案内文が表示されることを確認する
    public function testRankingShowsEmptyMessageWhenNoBooksHaveReviews(): void
    {
        $this->withoutVite();

        $owner = User::factory()->create();
        $book = $this->createBook($owner, '9781234567897');

        $response = $this->get(route('ranking.index'));

        $response->assertOk();
        $response->assertViewIs('ranking.index');
        $response->assertSee('まだレビューが投稿された書籍がありません。');
        $response->assertDontSee($book->title);

        $this->assertCount(0, $response->viewData('rankedBooks'));
    }

    // 書籍と、指定された点数のレビューを作成する共通の準備処理
    private function createBook(
        User $owner,
        string $isbn,
        array $ratings = []
    ): Book {
        $book = $owner->books()->create([
            'title' => "ランキング確認用書籍（{$isbn}）",
            'author' => 'テスト著者',
            'isbn' => $isbn,
            'published_date' => '2020-01-01',
        ]);

        // 点数ごとに投稿者を用意し、同じ書籍へのレビュー重複を避ける
        foreach ($ratings as $rating) {
            $reviewer = User::factory()->create();

            $book->reviews()->create([
                'user_id' => $reviewer->id,
                'rating' => $rating,
                'comment' => 'ランキング確認用のレビューです。',
            ]);
        }

        return $book;
    }
}
