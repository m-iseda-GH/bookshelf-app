<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 書籍とジャンルの紐付けを保存するテーブルを作成
    public function up(): void
    {
        Schema::create('book_genre', function (Blueprint $table) {
            // 対象の書籍ID（書籍の削除時、この紐付けも削除）
            $table->foreignId('book_id')
                ->constrained('books')
                ->cascadeOnDelete();
            // 対象のジャンルID（紐付けがあるジャンルの削除を拒否）
            $table->foreignId('genre_id')
                ->constrained('genres')
                ->restrictOnDelete();
            // 2つのIDを主キーにして、同じ組み合わせの重複を防止
            $table->primary(['book_id', 'genre_id']);
        });
    }

    // マイグレーションを取り消す際に、紐付けテーブルを削除
    public function down(): void
    {
        Schema::dropIfExists('book_genre');
    }
};
