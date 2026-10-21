<?php

namespace Database\Seeders;

// お気に入りにする書籍のIDを取得するBookモデルを読み込む
use App\Models\Book;
// お気に入りを登録するユーザーを取得するUserモデルを読み込む
use App\Models\User;
// Seederの基本クラスを読み込む
use Illuminate\Database\Seeder;

// 各ユーザーに3〜5冊のお気に入りを設定する
class FavoriteSeeder extends Seeder
{
    // このSeederを実行したときに呼び出される登録処理
    public function run(): void
    {
        // メールアドレスごとに、お気に入りにする書籍のISBNを用意する
        $favorites = [
            // 山田太郎：3冊
            'yamada@example.com' => [
                '9784101010014', // 吾輩は猫である
                '9784422100524', // 人を動かす
                '9784873115658', // リーダブルコード
            ],
            // 鈴木花子：4冊
            'suzuki@example.com' => [
                '9784101010021', // 坊っちゃん
                '9784478025819', // 嫌われる勇気
                '9784163902302', // 火花
                '9784822289607', // FACTFULNESS
            ],
            // 田中一郎：3冊
            'tanaka@example.com' => [
                '9784873115658', // リーダブルコード
                '9784048930598', // Clean Code
                '9784309226712', // サピエンス全史
            ],
            // 佐藤美咲：5冊
            'sato@example.com' => [
                '9784422100524', // 人を動かす
                '9784863940246', // 7つの習慣
                '9784478025819', // 嫌われる勇気
                '9784822251468', // コンテナ物語
                '9784163902302', // 火花
            ],
            // 高橋健太：4冊
            'takahashi@example.com' => [
                '9784873115658', // リーダブルコード
                '9784309226712', // サピエンス全史
                '9784048930598', // Clean Code
                '9784822289607', // FACTFULNESS
            ],
        ];

        // ユーザーごとに、お気に入りの登録処理を行う
        foreach ($favorites as $email => $isbns) {
            // メールアドレスでユーザーを取得し、存在しなければエラーにする
            $user = User::where('email', $email)->firstOrFail();

            // 指定したISBNに対応する書籍IDを配列で取得する
            $bookIds = Book::whereIn('isbn', $isbns)->pluck('id')->all();

            // 既存のお気に入りを残し、未登録の組み合わせを追加する
            $user->favoriteBooks()->syncWithoutDetaching($bookIds);
        }
    }
}
