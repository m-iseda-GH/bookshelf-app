<?php

namespace App\Http\Controllers;

// レビューの投稿対象となる書籍を扱う
use App\Models\Book;
// 編集・削除・いいねの対象となるレビューを扱う
use App\Models\Review;
// 送信された入力内容やログインユーザーを取得する
use Illuminate\Http\Request;

// レビューの投稿・編集・削除・いいねを扱う
class ReviewController extends Controller
{
    // 指定された書籍へレビューを投稿する
    public function store(Request $request, Book $book)
    {
        // 入力チェックと保存処理は、レビュー機能の工程で実装する
    }

    // レビュー編集画面を表示する
    public function edit(Review $review)
    {
        // 編集画面の表示は、レビュー機能の工程で実装する
    }

    // レビューの内容を更新する
    public function update(Request $request, Review $review)
    {
        // 入力チェックと更新処理は、レビュー機能の工程で実装する
    }

    // レビューを削除する
    public function destroy(Review $review)
    {
        // 削除処理は、レビュー機能の工程で実装する
    }

    // レビューへのいいねを登録・解除する
    public function toggleLike(Request $request, Review $review)
    {
        // 登録・解除処理は、いいね機能の工程で実装する
    }
}
