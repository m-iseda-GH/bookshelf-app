<?php

namespace App\Http\Controllers;

// レビューの入力条件を定義したクラスを読み込む
use App\Http\Requests\ReviewRequest;
// 投稿先の書籍を扱うモデルを読み込む
use App\Models\Book;
// レビューデータを扱うモデルを読み込む
use App\Models\Review;
// 別の画面へ移動する処理の戻り値の型を読み込む
use Illuminate\Http\RedirectResponse;
// 更新・いいねの処理で、送信内容やログインユーザーを取得する
use Illuminate\Http\Request;
// 入力エラーとしてメッセージを返すための例外を読み込む
use Illuminate\Validation\ValidationException;

// レビューの投稿・編集・削除・いいねを扱う
class ReviewController extends Controller
{
    // 入力チェックを通過したレビューを、指定された書籍へ投稿する
    public function store(ReviewRequest $request, Book $book): RedirectResponse
    {
        // ReviewRequestの入力チェックを通過した値を取得する
        $validated = $request->validated();

        // ログイン中のユーザーが、この書籍へ投稿済みかを確認する
        if ($book->reviews()->where('user_id', $request->user()->id)->exists()) {
            // 投稿済みの場合は、コメント欄にエラーメッセージを表示する
            throw ValidationException::withMessages([
                'comment' => 'この書籍にはすでにレビューを投稿しています。',
            ]);
        }

        // 投稿先の書籍に関連付けてレビューを保存する
        // book_idは、この関連付けから自動で設定される
        $book->reviews()->create([
            // 投稿者にはログイン中のユーザーのIDを設定する
            'user_id' => $request->user()->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'],
        ]);

        // 投稿完了のメッセージを付けて、書籍詳細画面へ移動する
        return redirect()->route('books.show', $book)
            ->with('success', 'レビューを投稿しました。');
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
