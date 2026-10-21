<?php

namespace Database\Seeders;

// いいねの対象となるレビューを取得するReviewモデルを読み込む
use App\Models\Review;
// いいねするユーザーを取得するUserモデルを読み込む
use App\Models\User;
// Seederの基本クラスを読み込む
use Illuminate\Database\Seeder;

// 各レビューに、投稿者本人を除く0〜3人のいいねを設定する
class ReviewLikeSeeder extends Seeder
{
    // このSeederを実行したときに呼び出される登録処理
    public function run(): void
    {
        // レビューをID順に取得し、いいね人数を一定の順番で割り当てる
        $reviews = Review::orderBy('id')->get();

        // 取得したレビューを1件ずつ処理する
        foreach ($reviews as $index => $review) {
            // 4で割った余りを使い、人数を0・1・2・3の繰り返しにする
            $likeCount = $index % 4;

            // 投稿者を除き、指定人数までのユーザーIDを配列で取得する
            $userIds = User::where('id', '!=', $review->user_id)
                ->orderBy('id')
                ->limit($likeCount)
                ->pluck('id')
                ->all();

            // 既存のいいねを残しながら、未登録の組み合わせを追加する
            $review->likedBy()->syncWithoutDetaching($userIds);
        }
    }
}
