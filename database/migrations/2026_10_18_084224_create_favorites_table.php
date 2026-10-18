<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // ユーザーとお気に入り書籍の紐付けを保存
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            // お気に入り登録したユーザーのID
            $table->foreignId('user_id')->constrained('users');
            // 対象の書籍ID（書籍の削除時に、このお気に入りも削除）
            $table->foreignId('book_id')
                ->constrained('books')
                ->cascadeOnDelete();
            // 同じユーザー・同じ書籍の組み合わせの重複を防止
            $table->primary(['user_id', 'book_id']);
        });
    }

    // マイグレーションを取り消す際に、お気に入りテーブルを削除
    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
