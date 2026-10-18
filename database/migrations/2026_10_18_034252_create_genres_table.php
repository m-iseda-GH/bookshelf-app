<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // ジャンル情報を保存するテーブルを作成
    public function up(): void
    {
        Schema::create('genres', function (Blueprint $table) {
            // ジャンルID（自動採番される主キー）
            $table->id();
            // ジャンル名（最大255文字・同じ名前の重複を禁止）
            $table->string('name', 255)->unique();
            // 作成日時と更新日時
            $table->timestamps();
        });
    }

    // マイグレーションを取り消す際に、ジャンルテーブルを削除
    public function down(): void
    {
        Schema::dropIfExists('genres');
    }
};
