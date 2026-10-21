<?php

namespace Database\Seeders;

// genresテーブルを扱うGenreモデルを読み込む
use App\Models\Genre;
// Seederの基本クラスを読み込む
use Illuminate\Database\Seeder;

// 指定された固定ジャンル10件を登録する
class GenreSeeder extends Seeder
{
    // このSeederを実行したときに呼び出される登録処理
    public function run(): void
    {
        // 登録するジャンル名を用意する
        $genreNames = [
            '小説',
            'ビジネス',
            '技術書',
            '自己啓発',
            'エッセイ',
            '歴史',
            '科学',
            '芸術',
            '料理',
            '旅行',
        ];

        // 用意したジャンル名を1件ずつ処理する
        foreach ($genreNames as $name) {
            // 同じ名前のジャンルが存在しなければ新規登録する
            Genre::firstOrCreate(['name' => $name]);
        }
    }
}
