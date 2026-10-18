<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // ユーザーと「いいね」したレビューの紐付けを保存
    public function up(): void
    {
        Schema::create('review_likes', function (Blueprint $table) {
            // いいねしたユーザーのID
            $table->foreignId('user_id')->constrained('users');
            // 対象のレビューID（レビュー削除時に、このいいねも削除）
            $table->foreignId('review_id')
                ->constrained('reviews')
                ->cascadeOnDelete();
            // 同じユーザー・同じレビューの組み合わせの重複を防止
            $table->primary(['user_id', 'review_id']);
        });
    }

    // マイグレーションを取り消す際に、いいねテーブルを削除
    public function down(): void
    {
        Schema::dropIfExists('review_likes');
    }
};
