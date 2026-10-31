<?php

namespace App\Http\Controllers\Api\V1;

// Controllerの基本クラスを読み込む
use App\Http\Controllers\Controller;
// 一覧APIの検索条件を確認するクラスを読み込む
use App\Http\Requests\ApiBookIndexRequest;
// 登録・更新APIの入力条件を確認するクラスを読み込む
use App\Http\Requests\ApiBookRequest;
// 書籍データをJSON向けに整形するクラスを読み込む
use App\Http\Resources\BookResource;
// 書籍モデルを読み込む
use App\Models\Book;
// データ取得条件を組み立てるクラスを読み込む
use Illuminate\Database\Eloquent\Builder;
// JSONを返す処理の戻り値の型を読み込む
use Illuminate\Http\JsonResponse;
// 複数件のResourceを返す場合の型を読み込む
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
// 本文なしの応答を返す場合の型を読み込む
use Illuminate\Http\Response;
// 配列から指定した項目を除く機能を読み込む
use Illuminate\Support\Arr;
// 複数の保存処理をまとめて扱う機能を読み込む
use Illuminate\Support\Facades\DB;

// 公開APIの書籍一覧・詳細・登録・更新・削除を扱う
class BookController extends Controller
{
    // 検索条件に合う書籍一覧を返す
    public function index(ApiBookIndexRequest $request): AnonymousResourceCollection
    {
        // 入力チェックを通過した検索条件を取得する
        $filters = $request->validated();

        // ジャンル・平均評価・レビュー件数を含めて書籍を取得する準備をする
        $query = Book::with('genres')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        // キーワードが指定された場合は、タイトルまたは著者名で検索する
        if (isset($filters['keyword'])) {
            $keyword = $filters['keyword'];

            // タイトルと著者名のOR条件を、ひとまとまりにする
            $query->where(function (Builder $bookQuery) use ($keyword) {
                $bookQuery->where('title', 'like', "%{$keyword}%")
                    ->orWhere('author', 'like', "%{$keyword}%");
            });
        }

        // ジャンルが指定された場合は、そのジャンルを持つ書籍に絞る
        if (isset($filters['genre_id'])) {
            $genreId = $filters['genre_id'];

            $query->whereHas('genres', function (Builder $genreQuery) use ($genreId) {
                $genreQuery->where('genres.id', $genreId);
            });
        }

        // Webの一覧と同じく、登録日時が新しい順に並べる
        $books = $query->orderByDesc('books.created_at')
            ->orderByDesc('books.id')
            // 件数の指定がなければ、1ページ10件にする
            ->paginate($filters['per_page'] ?? 10)
            // 次のページへのリンクにも検索条件を引き継ぐ
            ->withQueryString();

        return BookResource::collection($books);
    }

    // 入力チェックを通過した書籍を登録する
    public function store(ApiBookRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // 書籍の保存とジャンルの関連付けを、まとめて成功・取り消しする
        $book = DB::transaction(function () use ($validated) {
            // genresは中間テーブルに保存するため、booksの保存項目から除く
            $book = Book::create(Arr::except($validated, ['genres']));

            // 選択されたジャンルを関連付ける
            $book->genres()->sync($validated['genres']);

            return $book;
        });

        // 登録した書籍を、登録成功を表す201で返す
        return $this->bookResource($book)->response()->setStatusCode(201);
    }

    // URLで指定された書籍の詳細を返す
    public function show(Book $book): BookResource
    {
        return $this->bookResource($book);
    }

    // 入力チェックを通過した内容で、指定された書籍を更新する
    public function update(ApiBookRequest $request, Book $book): JsonResponse
    {
        $validated = $request->validated();

        // 書籍の更新とジャンルの変更を、まとめて成功・取り消しする
        DB::transaction(function () use ($validated, $book) {
            // 登録者IDを含む、チェック済みの書籍情報を更新する
            $book->update(Arr::except($validated, ['genres']));

            // ジャンルの関連付けを、送信された選択内容に置き換える
            $book->genres()->sync($validated['genres']);
        });

        // 更新後の書籍を、成功を表す200で返す
        return $this->bookResource($book)->response()->setStatusCode(200);
    }

    // URLで指定された書籍を削除する
    public function destroy(Book $book): Response
    {
        // 関連するレビュー・お気に入り・ジャンル紐付け・いいねはDBの設定で削除される
        $book->delete();

        // 削除成功を表す204を、本文なしで返す
        return response()->noContent();
    }

    // 詳細・登録・更新の応答に共通するデータを用意する
    private function bookResource(Book $book): BookResource
    {
        // ジャンルと、レビュー・投稿者をまとめて読み込む
        $book->load(['genres', 'reviews.user']);

        // 平均評価とレビュー件数を読み込む
        $book->loadAvg('reviews', 'rating');
        $book->loadCount('reviews');

        return new BookResource($book);
    }
}
