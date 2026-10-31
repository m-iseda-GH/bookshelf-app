<?php

namespace App\Http\Requests;

// 入力チェックとリクエストの許可を管理する基本クラスを読み込む
use Illuminate\Foundation\Http\FormRequest;

// レビューの投稿・更新に共通する入力条件をまとめる
class ReviewRequest extends FormRequest
{
    // リクエストを許可する。ログイン確認はルート側で行う
    public function authorize(): bool
    {
        return true;
    }

    // 評価とコメントの入力条件を指定する
    public function rules(): array
    {
        return [
            'rating' => [
                'required',   // 評価の選択を必須にする
                'integer',    // 整数として扱える値であることを確認する
                'between:1,5', // 評価を1〜5の範囲に限定する
            ],

            'comment' => [
                'required', // コメントの入力を必須にする
                'string',   // 文字列であることを確認する
            ],
        ];
    }

    // 入力条件を満たさなかった場合の日本語メッセージを指定する
    public function messages(): array
    {
        return [
            // 評価に関するエラーメッセージ
            'rating.required' => '評価を選択してください。',
            'rating.integer' => '評価は1〜5から選択してください。',
            'rating.between' => '評価は1〜5から選択してください。',

            // コメントに関するエラーメッセージ
            'comment.required' => 'コメントを入力してください。',
            'comment.string' => 'コメントの入力内容を確認してください。',
        ];
    }
}
