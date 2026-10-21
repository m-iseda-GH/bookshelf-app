<?php

namespace Database\Seeders;

// booksテーブルを扱うBookモデルを読み込む
use App\Models\Book;
// 登録済みジャンルを取得するGenreモデルを読み込む
use App\Models\Genre;
// 書籍の登録者を取得するUserモデルを読み込む
use App\Models\User;
// Seederの基本クラスを読み込む
use Illuminate\Database\Seeder;

// 元資料の書籍11冊と、ジャンルとの関連付けを登録する
class BookSeeder extends Seeder
{
    // このSeederを実行したときに呼び出される登録処理
    public function run(): void
    {
        // 元資料の指定に従い、最初のユーザーを登録者として取得する
        $user = User::first();

        // 書名・著者・ISBN・出版日・説明・ジャンル名を用意する
        $books = [
            [
                'title' => '吾輩は猫である',
                'author' => '夏目漱石',
                'isbn' => '9784101010014',
                'published_date' => '1905-01-01',
                'description' => '猫の視点から人間社会を描いた小説です。',
                'genres' => ['小説'],
            ],
            [
                'title' => '人を動かす',
                'author' => 'D・カーネギー',
                'isbn' => '9784422100524',
                'published_date' => '1936-10-01',
                'description' => '人との関わり方やコミュニケーションについて考える本です。',
                'genres' => ['ビジネス', '自己啓発'],
            ],
            [
                'title' => 'リーダブルコード',
                'author' => 'Dustin Boswell',
                'isbn' => '9784873115658',
                'published_date' => '2012-06-23',
                'description' => '読みやすく理解しやすいコードの書き方を学ぶ本です。',
                'genres' => ['技術書'],
            ],
            [
                'title' => '7つの習慣',
                'author' => 'スティーブン・R・コヴィー',
                'isbn' => '9784863940246',
                'published_date' => '2013-08-30',
                'description' => '日々の行動や考え方を見直すための本です。',
                'genres' => ['ビジネス', '自己啓発'],
            ],
            [
                'title' => '坊っちゃん',
                'author' => '夏目漱石',
                'isbn' => '9784101010021',
                'published_date' => '1906-04-01',
                'description' => '教師として赴任した青年を主人公にした小説です。',
                'genres' => ['小説'],
            ],
            [
                'title' => 'サピエンス全史',
                'author' => 'ユヴァル・ノア・ハラリ',
                'isbn' => '9784309226712',
                'published_date' => '2016-09-08',
                'description' => '人類の歴史を幅広い視点でたどる本です。',
                'genres' => ['歴史', '科学'],
            ],
            [
                'title' => 'Clean Code',
                'author' => 'Robert C. Martin',
                'isbn' => '9784048930598',
                'published_date' => '2017-12-18',
                'description' => '保守しやすいコードを書くための考え方を学ぶ本です。',
                'genres' => ['技術書'],
            ],
            [
                'title' => '嫌われる勇気',
                'author' => '岸見一郎・古賀史健',
                'isbn' => '9784478025819',
                'published_date' => '2013-12-13',
                'description' => '人間関係や生き方について考える本です。',
                'genres' => ['自己啓発'],
            ],
            [
                'title' => '火花',
                'author' => '又吉直樹',
                'isbn' => '9784163902302',
                'published_date' => '2015-03-11',
                'description' => 'お笑い芸人たちの交流や葛藤を描いた小説です。',
                'genres' => ['小説'],
            ],
            [
                'title' => 'FACTFULNESS',
                'author' => 'ハンス・ロスリング',
                'isbn' => '9784822289607',
                'published_date' => '2019-01-11',
                'description' => 'データを通じて世界の捉え方を見直す本です。',
                'genres' => ['ビジネス', '科学'],
            ],
            [
                'title' => 'コンテナ物語',
                'author' => 'マルク・レビンソン',
                'isbn' => '9784822251468',
                'published_date' => '2007-01-18',
                'description' => 'コンテナ輸送の発展と社会への影響を扱う本です。',
                'genres' => ['ビジネス', '歴史'],
            ],
        ];

        // 画像番号に使う順番と書籍情報を、1冊ずつ取り出す
        foreach ($books as $index => $data) {
            // 同じISBNの書籍が存在しなければ新規登録する
            $book = Book::firstOrCreate(
                ['isbn' => $data['isbn']],
                [
                    'user_id' => $user->id,
                    'title' => $data['title'],
                    'author' => $data['author'],
                    'published_date' => $data['published_date'],
                    'description' => $data['description'],
                    // 配列の順番を使い、画像番号を1〜11にする
                    'image_url' => 'https://placehold.co/200x300/e2e8f0/475569?text='.($index + 1),
                ]
            );

            // 指定されたジャンル名に対応するIDを、配列で取得する
            $genreIds = Genre::whereIn('name', $data['genres'])->pluck('id')->all();

            // book_genreの関連付けを、指定したジャンルIDにそろえる
            $book->genres()->sync($genreIds);
        }
    }
}
