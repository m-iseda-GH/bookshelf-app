<?php

// 公開APIの書籍処理を読み込む
use App\Http\Controllers\Api\V1\BookController;
// リクエスト情報を扱うクラスを読み込む
use Illuminate\Http\Request;
// URLと処理を関連付ける機能を読み込む
use Illuminate\Support\Facades\Route;

// Sanctumで認証されたユーザーの情報を返す
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// このファイルには自動で/apiが付くため、ここではv1を指定する
Route::prefix('v1')->name('api.v1.')->group(function () {
    // 書籍の一覧・詳細・登録・更新・削除APIを登録する
    Route::apiResource('books', BookController::class);
});
