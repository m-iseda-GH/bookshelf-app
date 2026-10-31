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
// 送信内容やログインユーザーを取得するクラスを読み込む
use Illuminate\Http\Request;
// 入力エラーとしてメッセージを返すための例外を読み込む
use Illuminate\Validation\ValidationException;
// 画面を返す処理の戻り値の型を読み込む
use Illuminate\View\View;

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

    // 投稿者本人に、レビュー編集画面を表示する
    public function edit(Review $review): View
    {
        // ReviewPolicyのupdate()で、投稿者本人かを確認する
        $this->authorize('update', $review);

        // 編集画面に表示する書籍情報を読み込む
        $review->load('book');

        // 対象のレビューを、Bladeで使うreviewという名前で渡す
        return view('reviews.edit', [
            'review' => $review,
        ]);
    }

    // 投稿者本人が、レビューの評価とコメントを更新する
    public function update(ReviewRequest $request, Review $review): RedirectResponse
    {
        // ReviewPolicyのupdate()で、投稿者本人かを確認する
        $this->authorize('update', $review);

        // ReviewRequestの入力チェックを通過した値を取得する
        $validated = $request->validated();

        // 対象のレビューの評価とコメントを更新する
        $review->update([
            'rating' => $validated['rating'],
            'comment' => $validated['comment'],
        ]);

        // 更新完了のメッセージを付けて、対象の書籍詳細画面へ移動する
        return redirect()->route('books.show', $review->book_id)
            ->with('success', 'レビューを更新しました。');
    }

    // 投稿者本人がレビューを削除する
    public function destroy(Review $review): RedirectResponse
    {
        // ReviewPolicyのdelete()で、投稿者本人かを確認する
        $this->authorize('delete', $review);

        // 削除後の移動先に使う書籍IDを控える
        $bookId = $review->book_id;

        // 対象のレビューをデータベースから削除する
        // 関連するいいねは、DBの外部キー設定により一緒に削除される
        $review->delete();

        // 削除完了のメッセージを付けて、対象の書籍詳細画面へ移動する
        return redirect()->route('books.show', $bookId)
            ->with('success', 'レビューを削除しました。');
    }

    // ログイン中のユーザーによる、レビューへのいいねを追加・解除する
    public function toggleLike(Request $request, Review $review): RedirectResponse
    {
        // ログイン中のユーザーと、URLのレビューのいいねを切り替える
        $changes = $request->user()->likedReviews()->toggle([$review->id]);

        // 追加されたレビューIDがあれば追加、なければ解除のメッセージにする
        $message = count($changes['attached']) > 0
            ? 'いいねしました。'
            : 'いいねを解除しました。';

        // 完了メッセージを付けて、そのレビューがある書籍詳細画面へ戻る
        return redirect()->route('books.show', $review->book_id)
            ->with('success', $message);
    }
}
