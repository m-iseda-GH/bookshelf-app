<?php

namespace App\Http\Controllers;

// お気に入り登録・解除の対象となる書籍を扱う
use App\Models\Book;
// 別の画面へ移動する処理の戻り値の型を読み込む
use Illuminate\Http\RedirectResponse;
// ログインユーザーなどのリクエスト情報を取得する
use Illuminate\Http\Request;
// 画面を返す処理の戻り値の型を読み込む
use Illuminate\View\View;

// お気に入りに関する処理を扱う
class FavoriteController extends Controller
{
    // ログイン中のユーザーのお気に入り一覧を表示する
    public function index(Request $request): View
    {
        // favoritesを介して、ログイン中のユーザーのお気に入り書籍を取得する
        $books = $request->user()->favoriteBooks()
            // ページ送りの順序を一定にするため、書籍IDの大きい順に並べる
            ->orderByDesc('books.id')
            // 1ページ10件に分ける
            ->paginate(10);

        // 取得した一覧を、Bladeで使うbooksという名前で渡す
        return view('favorites.index', [
            'books' => $books,
        ]);
    }

    // 指定された書籍のお気に入り登録・解除を切り替える
    public function toggle(Request $request, Book $book): RedirectResponse
    {
        // ログイン中のユーザーについて、URLの書籍の登録・解除を切り替える
        $changes = $request->user()->favoriteBooks()->toggle([$book->id]);

        // 追加した書籍IDの有無に応じて、完了メッセージを決める
        $message = count($changes['attached']) > 0
            ? 'お気に入りに追加しました。'
            : 'お気に入りを解除しました。';

        // 直前の画面のURLから、パス部分を取得する
        $previousPath = parse_url(url()->previous(), PHP_URL_PATH);

        // 一覧から操作した場合は、一覧の先頭ページへ戻る
        if ($previousPath === '/favorites') {
            return redirect()->route('favorites.index')
                ->with('success', $message);
        }

        // 書籍詳細から操作した場合は、その書籍の詳細画面へ戻る
        return redirect()->route('books.show', $book)
            ->with('success', $message);
    }
}
