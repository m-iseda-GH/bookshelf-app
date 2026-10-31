<?php

namespace Tests\Unit\Models;

// 確認対象の書籍モデルを読み込む
use App\Models\Book;
// 確認対象のジャンルモデルを読み込む
use App\Models\Genre;
// 確認対象のレビューモデルを読み込む
use App\Models\Review;
// 確認対象のユーザーモデルを読み込む
use App\Models\User;
// PHPの日付オブジェクトに共通する型を読み込む
use DateTimeInterface;
// テスト用のテーブル準備と、テスト後のデータ変更の取り消しを行う
use Illuminate\Foundation\Testing\RefreshDatabase;
// データベースの接続先を確認する機能を読み込む
use Illuminate\Support\Facades\DB;
// パスワードのハッシュ化と照合を行う機能を読み込む
use Illuminate\Support\Facades\Hash;
// Laravelのテスト用基本クラスを読み込む
use Tests\TestCase;

// モデルの属性変換と関連付けを確認する
class ModelTest extends TestCase
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

    // 出版日が、PHPの日付オブジェクトとして扱われることを確認する
    public function testBookConvertsPublishedDateToDateObject(): void
    {
        $book = new Book([
            'published_date' => '2020-01-15',
        ]);

        $this->assertInstanceOf(DateTimeInterface::class, $book->published_date);
        $this->assertSame('2020-01-15', $book->published_date->format('Y-m-d'));
    }

    // 通常のパスワードはハッシュ化され、既存のハッシュは二重変換されないことを確認する
    public function testUserHashesPlainPasswordsAndPreservesExistingHashes(): void
    {
        $password = 'password123';

        $user = new User([
            'password' => $password,
        ]);

        $this->assertNotSame($password, $user->password);
        $this->assertTrue(Hash::check($password, $user->password));

        // すでにハッシュ化された値を渡した場合も確認する
        $hash = Hash::make($password);

        $userWithHash = new User([
            'password' => $hash,
        ]);

        $this->assertSame($hash, $userWithHash->password);
    }

    // 配列に変換したとき、パスワードとログイン保持用トークンが含まれないことを確認する
    public function testUserSerializationHidesPasswordsAndRememberTokens(): void
    {
        $user = new User([
            'name' => 'モデル確認ユーザー',
            'email' => 'model-test@example.com',
            'password' => 'password123',
        ]);

        // 確認用のログイン保持用トークンを設定する
        $user->forceFill([
            'remember_token' => 'test-token',
        ]);

        $data = $user->toArray();

        $this->assertSame('モデル確認ユーザー', $data['name']);
        $this->assertSame('model-test@example.com', $data['email']);
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('remember_token', $data);
    }

    // 書籍から登録者、ユーザーから自分の書籍を取得できることを確認する
    public function testBookAndUserRelationshipsReturnCorrectRecords(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();

        $book = $this->createBook($owner);
        $otherBook = $this->createBook($otherOwner, '9781234567804');

        $this->assertTrue($book->user->is($owner));

        $this->assertSame(
            [$book->id],
            $owner->books()->get()->modelKeys()
        );
        $this->assertSame(
            [$otherBook->id],
            $otherOwner->books()->get()->modelKeys()
        );
    }

    // 書籍とジャンルを、双方から取得・解除できることを確認する
    public function testBookAndGenreRelationshipsWorkInBothDirections(): void
    {
        $owner = User::factory()->create();

        $book = $this->createBook($owner);
        $otherBook = $this->createBook($owner, '9781234567804');

        $fiction = Genre::create(['name' => '小説']);
        $history = Genre::create(['name' => '歴史']);

        $book->genres()->attach([$fiction->id, $history->id]);
        $otherBook->genres()->attach($history->id);

        // 書籍から、関連するジャンルを取得する
        $this->assertEqualsCanonicalizing(
            [$fiction->id, $history->id],
            $book->genres()->get()->modelKeys()
        );

        // ジャンルから、関連する書籍を取得する
        $this->assertSame(
            [$book->id],
            $fiction->books()->get()->modelKeys()
        );
        $this->assertEqualsCanonicalizing(
            [$book->id, $otherBook->id],
            $history->books()->get()->modelKeys()
        );

        // 1冊目だけ、歴史との関連付けを解除する
        $book->genres()->detach($history->id);

        $this->assertSame(
            [$fiction->id],
            $book->genres()->get()->modelKeys()
        );
        $this->assertSame(
            [$otherBook->id],
            $history->books()->get()->modelKeys()
        );
    }

    // レビューの書籍・投稿者と、それぞれのレビュー一覧を確認する
    public function testReviewReturnsItsBookAndReviewer(): void
    {
        $owner = User::factory()->create();
        $reviewer = User::factory()->create();
        $otherReviewer = User::factory()->create();

        $book = $this->createBook($owner);
        $otherBook = $this->createBook($owner, '9781234567804');

        $review = $this->createReview($book, $reviewer);
        $otherReview = $this->createReview($otherBook, $otherReviewer);

        $this->assertTrue($review->book->is($book));
        $this->assertTrue($review->user->is($reviewer));

        $this->assertSame(
            [$review->id],
            $book->reviews()->get()->modelKeys()
        );
        $this->assertSame(
            [$otherReview->id],
            $otherBook->reviews()->get()->modelKeys()
        );

        $this->assertSame(
            [$review->id],
            $reviewer->reviews()->get()->modelKeys()
        );
        $this->assertSame(
            [$otherReview->id],
            $otherReviewer->reviews()->get()->modelKeys()
        );

        // 書籍の登録者には、投稿していないレビューが紐付かないことを確認する
        $this->assertFalse($owner->reviews()->exists());
    }

    // お気に入りを、ユーザーと書籍の双方から確認できることを確認する
    public function testFavoritesCanBeReadAndRemovedFromBothSides(): void
    {
        $owner = User::factory()->create();
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = $this->createBook($owner);

        $user->favoriteBooks()->attach($book->id);
        $otherUser->favoriteBooks()->attach($book->id);

        $this->assertSame(
            [$book->id],
            $user->favoriteBooks()->get()->modelKeys()
        );
        $this->assertEqualsCanonicalizing(
            [$user->id, $otherUser->id],
            $book->favoritedBy()->get()->modelKeys()
        );

        // 1人分だけ解除し、もう1人分は残ることを確認する
        $user->favoriteBooks()->detach($book->id);

        $this->assertFalse($user->favoriteBooks()->exists());
        $this->assertSame(
            [$otherUser->id],
            $book->favoritedBy()->get()->modelKeys()
        );
        $this->assertSame(
            [$book->id],
            $otherUser->favoriteBooks()->get()->modelKeys()
        );
    }

    // いいねを、ユーザーとレビューの双方から確認できることを確認する
    public function testReviewLikesCanBeReadAndRemovedFromBothSides(): void
    {
        $owner = User::factory()->create();
        $reviewer = User::factory()->create();
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $book = $this->createBook($owner);
        $review = $this->createReview($book, $reviewer);

        $user->likedReviews()->attach($review->id);
        $otherUser->likedReviews()->attach($review->id);

        $this->assertSame(
            [$review->id],
            $user->likedReviews()->get()->modelKeys()
        );
        $this->assertEqualsCanonicalizing(
            [$user->id, $otherUser->id],
            $review->likedByUsers()->get()->modelKeys()
        );

        // 1人分だけ解除し、もう1人分は残ることを確認する
        $user->likedReviews()->detach($review->id);

        $this->assertFalse($user->likedReviews()->exists());
        $this->assertSame(
            [$otherUser->id],
            $review->likedByUsers()->get()->modelKeys()
        );
        $this->assertSame(
            [$review->id],
            $otherUser->likedReviews()->get()->modelKeys()
        );
    }

    // 確認用の書籍を作成する共通処理
    private function createBook(
        User $owner,
        string $isbn = '9781234567897'
    ): Book {
        return $owner->books()->create([
            'title' => 'モデル確認用の書籍',
            'author' => 'テスト著者',
            'isbn' => $isbn,
            'published_date' => '2020-01-01',
        ]);
    }

    // 確認用のレビューを作成する共通処理
    private function createReview(Book $book, User $reviewer): Review
    {
        return $book->reviews()->create([
            'user_id' => $reviewer->id,
            'rating' => 4,
            'comment' => 'モデル確認用のレビューです。',
        ]);
    }
}
