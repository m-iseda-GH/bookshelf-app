<?php

namespace App\Http\Controllers;

// ジャンルの登録・更新時の入力チェックを行うクラスを読み込む
use App\Http\Requests\GenreRequest;
// ジャンルデータを扱うモデルを読み込む
use App\Models\Genre;
// 別の画面へ移動する処理の戻り値の型を読み込む
use Illuminate\Http\RedirectResponse;
// 画面を返す処理の戻り値の型を読み込む
use Illuminate\View\View;

// ジャンルの一覧・詳細表示と、登録・更新・削除を扱う
class GenreController extends Controller
{
    // 書籍数付きのジャンル一覧を表示する
    public function index(): View
    {
        // 各ジャンルに関連する書籍数を、books_countという名前で取得する
        $genres = Genre::withCount('books')
            // IDの小さい順に並べる
            ->orderBy('id')
            ->get();

        // ジャンル一覧を、Bladeで使うgenresという名前で渡す
        return view('genres.index', [
            'genres' => $genres,
        ]);
    }

    // ジャンル登録画面を表示する
    public function create(): View
    {
        return view('genres.create');
    }

    // 入力チェックを通過したジャンル名を保存する
    public function store(GenreRequest $request): RedirectResponse
    {
        // チェックを通過した入力値でジャンルを登録する
        Genre::create($request->validated());

        // 登録完了のメッセージを付けて、ジャンル一覧へ移動する
        return redirect()->route('genres.index')
            ->with('success', 'ジャンルを登録しました。');
    }

    // 指定されたジャンルと、そのジャンルの書籍一覧を表示する
    public function show(Genre $genre): View
    {
        // このジャンルに関連付けられた書籍だけを取得する
        $books = $genre->books()
            // 書籍カードに表示するジャンルをまとめて読み込む
            ->with('genres')
            // ページ送りの順序を一定にするため、書籍IDの大きい順に並べる
            ->orderByDesc('books.id')
            // 1ページ10件に分ける
            ->paginate(10);

        // ジャンルと書籍一覧を、Bladeで使う名前で渡す
        return view('genres.show', [
            'genre' => $genre,
            'books' => $books,
        ]);
    }

    // 現在のジャンル名を表示した編集画面を返す
    public function edit(Genre $genre): View
    {
        return view('genres.edit', [
            'genre' => $genre,
        ]);
    }

    // 入力チェックを通過したジャンル名で更新する
    public function update(GenreRequest $request, Genre $genre): RedirectResponse
    {
        // 対象のジャンル名を更新する
        $genre->update($request->validated());

        // 更新完了のメッセージを付けて、ジャンル一覧へ移動する
        return redirect()->route('genres.index')
            ->with('success', 'ジャンルを更新しました。');
    }

    // 書籍が登録されていないジャンルだけを削除する
    public function destroy(Genre $genre): RedirectResponse
    {
        // このジャンルに関連する書籍が存在するかを確認する
        if ($genre->books()->exists()) {
            // 書籍がある場合は削除せず、一覧にエラーメッセージを表示する
            return redirect()->route('genres.index')
                ->with('error', '書籍が登録されているジャンルは削除できません。');
        }

        // 関連する書籍がないジャンルを削除する
        $genre->delete();

        // 削除完了のメッセージを付けて、ジャンル一覧へ移動する
        return redirect()->route('genres.index')
            ->with('success', 'ジャンルを削除しました。');
    }
}
