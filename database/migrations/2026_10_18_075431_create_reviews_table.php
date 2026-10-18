<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 書籍への評価とコメントを保存するテーブルを作成
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            // レビューID（自動採番される主キー）
            $table->id();
            // 投稿者のユーザーID（usersテーブルのidを参照）
            $table->foreignId('user_id')->constrained('users');
            // 対象の書籍ID（書籍の削除時に、このレビューも削除）
            $table->foreignId('book_id')
                ->constrained('books')
                ->cascadeOnDelete();
            // 評価値を保存する整数列（1〜5の範囲は入力チェックで制限）
            $table->unsignedTinyInteger('rating');
            // レビュー本文（必須）
            $table->text('comment');
            // 作成日時と更新日時
            $table->timestamps();
            // 同じユーザーによる、同じ書籍へのレビューの重複を防止
            $table->unique(['user_id', 'book_id']);
        });
    }

    // マイグレーションを取り消す際に、レビューテーブルを削除
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
