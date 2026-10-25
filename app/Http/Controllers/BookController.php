<?php

namespace App\Http\Controllers;

// 書籍登録の入力チェックを行うクラスを読み込む
use App\Http\Requests\BookRequest;
// 書籍データを扱うモデルを読み込む
use App\Models\Book;
// 登録画面に表示するジャンルを扱うモデルを読み込む
use App\Models\Genre;
// 別の画面へ移動する処理の戻り値の型を読み込む
use Illuminate\Http\RedirectResponse;
// 複数の保存処理をまとめて扱う機能を読み込む
use Illuminate\Support\Facades\DB;
// 画面を返す処理の戻り値の型を読み込む
use Illuminate\View\View;

// 書籍の画面表示・登録・更新・削除を扱う
class BookController extends Controller
{
    // 書籍一覧画面を表示する
    public function index(): View
    {
        // 書籍と関連するジャンルをまとめて取得する
        $books = Book::with('genres')
            // 書籍ごとのレビュー評価の平均を取得する
            ->withAvg('reviews', 'rating')
            // 登録日時が新しい書籍から表示する
            ->orderByDesc('created_at')
            // 登録日時が同じ場合は、IDの大きい順に並べる
            ->orderByDesc('id')
            // 1ページ10件に分ける
            ->paginate(10);

        // 取得した一覧を、Bladeで使うbooksという名前で渡す
        return view('books.index', [
            'books' => $books,
        ]);
    }

    // 書籍登録画面を表示する
    public function create(): View
    {
        // チェックボックスに表示する全ジャンルを取得する
        $genres = Genre::all();

        // ジャンル一覧を、Bladeで使うgenresという名前で渡す
        return view('books.create', [
            'genres' => $genres,
        ]);
    }

    // 入力チェックを通過した書籍と、選択されたジャンルを保存する
    public function store(BookRequest $request): RedirectResponse
    {
        // BookRequestの入力チェックを通過した値を取得する
        $validated = $request->validated();

        // 書籍の保存とジャンルの関連付けを、まとめて成功・取り消しする
        DB::transaction(function () use ($request, $validated) {
            // ログイン中のユーザーを登録者として、書籍を保存する
            $book = $request->user()->books()->create([
                'title' => $validated['title'],
                'author' => $validated['author'],
                'isbn' => $validated['isbn'],
                'published_date' => $validated['published_date'],
                // 任意項目が送信されなかった場合は、未設定として保存する
                'description' => $validated['description'] ?? null,
                'image_url' => $validated['image_url'] ?? null,
            ]);

            // 選択されたジャンルを、book_genreを介して書籍に関連付ける
            $book->genres()->sync($validated['genres']);
        });

        // 登録完了のメッセージを付けて、書籍一覧へ移動する
        return redirect()->route('books.index')
            ->with('success', '書籍を登録しました。');
    }

    // URLで指定された書籍の詳細画面を表示する
    public function show(Book $book): View
    {
        // 詳細画面で使う関連データをまとめて読み込む
        $book->load([
            'genres',              // 書籍に関連付けられたジャンル
            'reviews.user',        // 書籍のレビューと、その投稿者
            'reviews.likedByUsers', // 各レビューにいいねしたユーザー
        ]);

        // 対象の書籍を、Bladeで使うbookという名前で渡す
        return view('books.show', [
            'book' => $book,
        ]);
    }

    // 登録者本人に、書籍の編集画面を表示する
    public function edit(Book $book): View
    {
        // BookPolicyのupdate()で、登録者本人かを確認する
        $this->authorize('update', $book);

        // 書籍に現在関連付けられているジャンルを取得する
        $book->load('genres');

        // チェックボックスの選択肢として、全ジャンルを取得する
        $genres = Genre::all();

        // 日付入力欄に年月日を表示するため、この書籍の出版日を文字列として扱う
        $book->mergeCasts([
            'published_date' => 'string',
        ]);

        // 編集対象の書籍とジャンル一覧を、編集画面へ渡す
        return view('books.edit', [
            'book' => $book,
            'genres' => $genres,
        ]);
    }

    // 登録者本人が、書籍情報とジャンルの関連付けを更新する
    public function update(BookRequest $request, Book $book): RedirectResponse
    {
        // BookPolicyのupdate()で、登録者本人かを確認する
        $this->authorize('update', $book);

        // BookRequestの入力チェックを通過した値を取得する
        $validated = $request->validated();

        // 書籍情報とジャンルの変更を、まとめて成功・取り消しする
        DB::transaction(function () use ($book, $validated) {
            // 入力内容で書籍情報を更新する
            $book->update([
                'title' => $validated['title'],
                'author' => $validated['author'],
                'isbn' => $validated['isbn'],
                'published_date' => $validated['published_date'],
                // 任意項目が未設定の場合は、nullで保存する
                'description' => $validated['description'] ?? null,
                'image_url' => $validated['image_url'] ?? null,
            ]);

            // 書籍のジャンルを、今回選択された内容にそろえる
            $book->genres()->sync($validated['genres']);
        });

        // 更新完了のメッセージを付けて、書籍詳細へ移動する
        return redirect()->route('books.show', $book)
            ->with('success', '書籍を更新しました。');
    }

    // 登録者本人が書籍を削除する
    public function destroy(Book $book): RedirectResponse
    {
        // BookPolicyのdelete()で、登録者本人かを確認する
        $this->authorize('delete', $book);

        // 対象の書籍をデータベースから削除する
        $book->delete();

        // 削除完了のメッセージを付けて、書籍一覧へ移動する
        return redirect()->route('books.index')
            ->with('success', '書籍を削除しました。');
    }
}
