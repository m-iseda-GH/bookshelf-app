<?php

namespace App\Http\Controllers;

// 書籍データを扱うモデルを読み込む
use App\Models\Book;
// 登録・更新時に送信された入力内容を扱うクラスを読み込む
use Illuminate\Http\Request;
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
    public function create()
    {
        // 後の工程で実装する
    }

    // 入力内容をもとに書籍を保存する
    public function store(Request $request)
    {
        // 後の工程で実装する
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

    // 書籍編集画面を表示する
    public function edit(Book $book)
    {
        // 後の工程で実装する
    }

    // 書籍の登録内容を更新する
    public function update(Request $request, Book $book)
    {
        // 後の工程で実装する
    }

    // 書籍を削除する
    public function destroy(Book $book)
    {
        // 後の工程で実装する
    }
}
