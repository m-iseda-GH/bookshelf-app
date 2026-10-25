<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 書籍情報を保存するテーブルを作成
        Schema::create('books', function (Blueprint $table) {
            // 書籍ID（自動採番される主キー）
            $table->id();
            // 登録者のユーザーID（usersテーブルのidを参照）
            $table->foreignId('user_id')->constrained('users');
            // 書名（最大255文字）
            $table->string('title', 255);
            // 著者名（最大255文字）
            $table->string('author', 255);
            // ISBN（13文字用の列・同じISBNの重複を禁止）
            $table->char('isbn', 13)->unique();
            // 出版日（基本設計ではNULLを許可しない）
            $table->date('published_date');
            // 書籍の説明（任意入力）
            $table->text('description')->nullable();
            // 画像URL（最大2048文字・任意入力）
            $table->string('image_url', 2048)->nullable();
            // 作成日時と更新日時
            $table->timestamps();
        });
    }

    // マイグレーションを取り消す際、書籍テーブルを削除
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
