<?php

namespace Database\Seeders;

// usersテーブルを扱うUserモデルを読み込む
use App\Models\User;
// Seederの基本クラスを読み込む
use Illuminate\Database\Seeder;
// パスワードをハッシュ化する機能を読み込む
use Illuminate\Support\Facades\Hash;

// 指定された初期ユーザーを登録する
class UserSeeder extends Seeder
{
    // このSeederを実行したときに呼び出される登録処理
    public function run(): void
    {
        // 登録する5人の名前とメールアドレスを用意する
        $users = [
            ['name' => '山田太郎', 'email' => 'yamada@example.com'],
            ['name' => '鈴木花子', 'email' => 'suzuki@example.com'],
            ['name' => '田中一郎', 'email' => 'tanaka@example.com'],
            ['name' => '佐藤美咲', 'email' => 'sato@example.com'],
            ['name' => '高橋健太', 'email' => 'takahashi@example.com'],
        ];

        // 用意したユーザーを1人ずつ登録する
        foreach ($users as $user) {
            // 同じメールアドレスのユーザーが存在しなければ新規登録する
            User::firstOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    // 共通パスワードを照合用のハッシュ値に変換する
                    'password' => Hash::make('password'),
                ]
            );
        }
    }
}
