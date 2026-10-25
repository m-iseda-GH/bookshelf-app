<?php

// マイグレーションの基本クラスを読み込む
use Illuminate\Database\Migrations\Migration;
// テーブルの列を定義するための型を読み込む
use Illuminate\Database\Schema\Blueprint;
// テーブル構造を操作する機能を読み込む
use Illuminate\Support\Facades\Schema;

// usersテーブルへFortify用の3列を追加する
return new class extends Migration
{
    // マイグレーション実行時に列を追加する
    public function up(): void
    {
        // 既存のusersテーブルの構造を変更する
        Schema::table('users', function (Blueprint $table) {
            // 二要素認証のコード生成に使う秘密情報の保存先
            $table->text('two_factor_secret')
                ->after('password')
                ->nullable();

            // 二要素認証で使う復旧用コードの保存先
            $table->text('two_factor_recovery_codes')
                ->after('two_factor_secret')
                ->nullable();

            // 二要素認証の設定確認が完了した日時の保存先
            $table->timestamp('two_factor_confirmed_at')
                ->after('two_factor_recovery_codes')
                ->nullable();
        });
    }

    // マイグレーションを戻すときに、追加した3列を削除する
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
            ]);
        });
    }
};
