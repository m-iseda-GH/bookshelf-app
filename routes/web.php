<?php

// 書籍の処理を読み込む
use App\Http\Controllers\BookController;
// お気に入りの処理を読み込む
use App\Http\Controllers\FavoriteController;
// ジャンルの処理を読み込む
use App\Http\Controllers\GenreController;
// ランキングの処理を読み込む
use App\Http\Controllers\RankingController;
// レビューの処理を読み込む
use App\Http\Controllers\ReviewController;
// URLと処理の対応を登録する機能を読み込む
use Illuminate\Support\Facades\Route;

// トップページに書籍一覧を表示する
Route::get('/', [BookController::class, 'index']);

// ログインが必要な処理をまとめる
Route::middleware('auth')->group(function () {
    // 書籍の登録・編集・更新・削除のルートを登録する
    Route::resource('books', BookController::class)
        ->except(['index', 'show']);

    // ジャンル管理のルートを登録する
    Route::resource('genres', GenreController::class);

    // お気に入り一覧のルートを登録する
    Route::get('/favorites', [FavoriteController::class, 'index'])
        ->name('favorites.index');

    // 指定された書籍のお気に入り登録・解除のルートを登録する
    Route::post('/books/{book}/favorites', [FavoriteController::class, 'toggle'])
        ->name('favorites.toggle');

    // 指定された書籍へのレビュー投稿ルートを登録する
    Route::post('/books/{book}/reviews', [ReviewController::class, 'store'])
        ->name('reviews.store');

    // レビューの編集画面・更新・削除のルートを登録する
    Route::resource('reviews', ReviewController::class)
        ->only(['edit', 'update', 'destroy']);

    // レビューへのいいね登録・解除のルートを登録する
    Route::post('/reviews/{review}/like', [ReviewController::class, 'toggleLike'])
        ->name('reviews.like');
});

// 未ログインでも書籍一覧・詳細を閲覧できるようにする
Route::resource('books', BookController::class)
    ->only(['index', 'show']);

// 未ログインでもランキングを閲覧できるようにする
Route::get('/ranking', [RankingController::class, 'index'])
    ->name('ranking.index');
