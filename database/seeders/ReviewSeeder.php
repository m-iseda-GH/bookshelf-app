<?php

namespace Database\Seeders;

// 登録済み書籍のIDを取得するBookモデルを読み込む
use App\Models\Book;
// reviewsテーブルへ登録するReviewモデルを読み込む
use App\Models\Review;
// 登録済みユーザーのIDを取得するUserモデルを読み込む
use App\Models\User;
// Seederの基本クラスを読み込む
use Illuminate\Database\Seeder;

// 動作確認用のレビュー32件を登録する
class ReviewSeeder extends Seeder
{
    // このSeederを実行したときに呼び出される登録処理
    public function run(): void
    {
        // メールアドレスをキー、ユーザーIDを値にした配列を取得する
        $userIds = User::pluck('id', 'email')->all();

        // ISBNをキー、書籍IDを値にした配列を取得する
        $bookIds = Book::pluck('id', 'isbn')->all();

        // 書籍のISBNごとに、投稿者・評価・コメントを用意する
        $reviews = [
            // 吾輩は猫である：3件
            '9784101010014' => [
                ['email' => 'yamada@example.com', 'rating' => 5, 'comment' => '猫の視点で人間を描く表現が面白かったです。'],
                ['email' => 'suzuki@example.com', 'rating' => 4, 'comment' => '独特の言葉遣いとユーモアを楽しめました。'],
                ['email' => 'tanaka@example.com', 'rating' => 3, 'comment' => '時代背景を調べながら読むと理解が深まりました。'],
            ],
            // 人を動かす：4件
            '9784422100524' => [
                ['email' => 'yamada@example.com', 'rating' => 5, 'comment' => '相手の立場で考える大切さを学べました。'],
                ['email' => 'suzuki@example.com', 'rating' => 4, 'comment' => '具体例が多く、日常の会話に取り入れやすいです。'],
                ['email' => 'sato@example.com', 'rating' => 4, 'comment' => '人との接し方を振り返るきっかけになりました。'],
                ['email' => 'takahashi@example.com', 'rating' => 5, 'comment' => '職場でのコミュニケーションに役立ちました。'],
            ],
            // リーダブルコード：3件
            '9784873115658' => [
                ['email' => 'suzuki@example.com', 'rating' => 5, 'comment' => '変数名の付け方を改善する参考になりました。'],
                ['email' => 'tanaka@example.com', 'rating' => 5, 'comment' => 'コメントを書く目的がよく分かりました。'],
                ['email' => 'takahashi@example.com', 'rating' => 4, 'comment' => '小さな改善を積み重ねる考え方が参考になります。'],
            ],
            // 7つの習慣：3件
            '9784863940246' => [
                ['email' => 'yamada@example.com', 'rating' => 4, 'comment' => '毎日の習慣を見直すきっかけになりました。'],
                ['email' => 'sato@example.com', 'rating' => 5, 'comment' => '優先順位を考えて行動する大切さを学べました。'],
                ['email' => 'takahashi@example.com', 'rating' => 4, 'comment' => '長期的な目標について考え直すことができました。'],
            ],
            // 坊っちゃん：3件
            '9784101010021' => [
                ['email' => 'suzuki@example.com', 'rating' => 4, 'comment' => '主人公の率直な性格が印象に残りました。'],
                ['email' => 'tanaka@example.com', 'rating' => 3, 'comment' => '展開が分かりやすく、最後まで読み進められました。'],
                ['email' => 'sato@example.com', 'rating' => 4, 'comment' => '登場人物同士のやり取りを楽しめました。'],
            ],
            // サピエンス全史：3件
            '9784309226712' => [
                ['email' => 'yamada@example.com', 'rating' => 5, 'comment' => '人類の歴史を大きな流れで捉えられました。'],
                ['email' => 'tanaka@example.com', 'rating' => 4, 'comment' => '歴史と科学を結び付ける視点が興味深かったです。'],
                ['email' => 'takahashi@example.com', 'rating' => 5, 'comment' => '普段の価値観を見直すきっかけになりました。'],
            ],
            // Clean Code：3件
            '9784048930598' => [
                ['email' => 'tanaka@example.com', 'rating' => 5, 'comment' => '関数を小さくまとめる考え方が参考になりました。'],
                ['email' => 'sato@example.com', 'rating' => 4, 'comment' => '保守しやすいコードについて理解が深まりました。'],
                ['email' => 'takahashi@example.com', 'rating' => 4, 'comment' => '読み手を意識して実装する大切さを学べました。'],
            ],
            // 嫌われる勇気：2件
            '9784478025819' => [
                ['email' => 'suzuki@example.com', 'rating' => 5, 'comment' => '人間関係との向き合い方を考える機会になりました。'],
                ['email' => 'sato@example.com', 'rating' => 4, 'comment' => '対話形式なので内容を追いやすかったです。'],
            ],
            // 火花：3件
            '9784163902302' => [
                ['email' => 'yamada@example.com', 'rating' => 4, 'comment' => '芸人たちの葛藤が印象に残りました。'],
                ['email' => 'sato@example.com', 'rating' => 3, 'comment' => '人物の心情をじっくり考えながら読みました。'],
                ['email' => 'takahashi@example.com', 'rating' => 4, 'comment' => '結末まで余韻の残る作品でした。'],
            ],
            // FACTFULNESS：3件
            '9784822289607' => [
                ['email' => 'suzuki@example.com', 'rating' => 5, 'comment' => '数値を確かめて判断する大切さを学べました。'],
                ['email' => 'tanaka@example.com', 'rating' => 4, 'comment' => '思い込みに気付くための具体例が役立ちました。'],
                ['email' => 'takahashi@example.com', 'rating' => 5, 'comment' => 'データから世界を見る視点が身に付きました。'],
            ],
            // コンテナ物語：2件
            '9784822251468' => [
                ['email' => 'yamada@example.com', 'rating' => 4, 'comment' => '輸送の仕組みが変わった経緯を学べました。'],
                ['email' => 'sato@example.com', 'rating' => 5, 'comment' => '物流と暮らしのつながりがよく分かりました。'],
            ],
        ];

        // 書籍ごとに、その書籍へ投稿するレビュー一覧を取り出す
        foreach ($reviews as $isbn => $bookReviews) {
            // 取り出したレビューを1件ずつ登録する
            foreach ($bookReviews as $data) {
                Review::create([
                    // メールアドレスから、実際の投稿者IDを取り出す
                    'user_id' => $userIds[$data['email']],
                    // ISBNから、実際の書籍IDを取り出す
                    'book_id' => $bookIds[$isbn],
                    'rating' => $data['rating'],
                    'comment' => $data['comment'],
                ]);
            }
        }
    }
}
