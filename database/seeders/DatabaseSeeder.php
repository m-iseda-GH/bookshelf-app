<?php

namespace Database\Seeders;

// Seederの基本クラスを読み込む
use Illuminate\Database\Seeder;

// 基本機能の初期データをまとめて登録する
class DatabaseSeeder extends Seeder
{
    // 各Seederを、必要なデータが先に登録される順番で実行する
    public function run(): void
    {
        $this->call([
            UserSeeder::class,       // ユーザーを登録する
            GenreSeeder::class,      // ジャンルを登録する
            BookSeeder::class,       // 書籍を登録し、ジャンルと関連付ける
            ReviewSeeder::class,     // ユーザーと書籍に結び付くレビューを登録する
            FavoriteSeeder::class,   // ユーザーと書籍のお気に入りの組み合わせを登録する
            ReviewLikeSeeder::class, // ユーザーとレビューのいいねの組み合わせを登録する
        ]);
    }
}
