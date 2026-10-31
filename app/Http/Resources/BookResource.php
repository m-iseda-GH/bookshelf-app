<?php

namespace App\Http\Resources;

// ジャンル情報を整形する際に使うモデルを読み込む
use App\Models\Genre;
// レビュー情報を整形する際に使うモデルを読み込む
use App\Models\Review;
// APIへのリクエストを表すクラスを読み込む
use Illuminate\Http\Request;
// データをJSON向けに整形する基本クラスを読み込む
use Illuminate\Http\Resources\Json\JsonResource;

// 書籍APIで返す項目と形式を定義する
class BookResource extends JsonResource
{
    // 渡された書籍データを、APIで返す配列に変換する
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'user_id' => (int) $this->user_id,
            'title' => $this->title,
            'author' => $this->author,
            'isbn' => $this->isbn,
            // 出版日は、年・月・日だけの形式にする
            'published_date' => $this->published_date->format('Y-m-d'),
            'description' => $this->description,
            'image_url' => $this->image_url,

            // レビューがない場合の平均評価はnullにする
            'reviews_avg_rating' => $this->reviews_avg_rating === null
                ? null
                : (float) $this->reviews_avg_rating,

            'reviews_count' => (int) $this->reviews_count,

            // 読み込み済みのジャンルを、IDと名前だけの形式にする
            'genres' => $this->whenLoaded('genres', function () {
                return $this->genres->map(function (Genre $genre) {
                    return [
                        'id' => (int) $genre->id,
                        'name' => $genre->name,
                    ];
                });
            }),

            // 詳細APIでレビューを読み込んだ場合に、レビュー一覧を含める
            'reviews' => $this->whenLoaded('reviews', function () {
                return $this->reviews->map(function (Review $review) {
                    return [
                        'id' => (int) $review->id,
                        'user_name' => $review->user->name,
                        'rating' => (int) $review->rating,
                        'comment' => $review->comment,
                        'created_at' => $review->created_at?->toISOString(),
                    ];
                });
            }),

            // 登録日時と更新日時を、時差情報を含む形式にする
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
