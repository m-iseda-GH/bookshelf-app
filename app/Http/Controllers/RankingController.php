<?php

namespace App\Http\Controllers;

// ランキングの対象となる書籍モデルを読み込む
use App\Models\Book;
// 画面を返す処理の戻り値の型を読み込む
use Illuminate\View\View;

// 書籍ランキングに関する処理を扱う
class RankingController extends Controller
{
    // 平均評価の高い書籍を、最大10冊表示する
    public function index(): View
    {
        // レビューが1件以上ある書籍だけを対象にする
        $rankedBooks = Book::has('reviews')
            // レビューの平均評価を、reviews_avg_ratingという名前で取得する
            ->withAvg('reviews', 'rating')
            // レビュー件数を、reviews_countという名前で取得する
            ->withCount('reviews')
            // 平均評価が高い順に並べる
            ->orderByDesc('reviews_avg_rating')
            // 平均評価が同じ場合は、IDの小さい順に並べる
            ->orderBy('books.id')
            // 上位10冊に絞る
            ->limit(10)
            ->get();

        // 集計した書籍を、Bladeで使うrankedBooksという名前で渡す
        return view('ranking.index', [
            'rankedBooks' => $rankedBooks,
        ]);
    }
}
