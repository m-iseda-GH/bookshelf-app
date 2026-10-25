<?php

namespace App\Http\Controllers;

// お気に入り登録・解除の対象となる書籍を扱う
use App\Models\Book;
// ログインユーザーなどのリクエスト情報を取得する
use Illuminate\Http\Request;

// お気に入りに関する処理を扱う
class FavoriteController extends Controller
{
    // お気に入り一覧画面を表示する
    public function index()
    {
        // 一覧の取得と画面表示は、後の工程で実装する
    }

    // 指定された書籍のお気に入り登録・解除を切り替える
    public function toggle(Request $request, Book $book)
    {
        // 登録・解除処理は、お気に入り機能の工程で実装する
    }
}
