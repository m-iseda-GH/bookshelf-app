<?php

namespace App\Http\Requests;

// Fortify標準のLoginRequestを、区別できる別名で読み込む
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

// 標準のログイン入力条件を引き継ぎ、日本語メッセージを設定する
class LoginRequest extends FortifyLoginRequest
{
    // 入力条件を満たさなかった場合のメッセージを指定する
    public function messages(): array
    {
        return [
            // メールアドレスに関するエラーメッセージ
            'email.required' => 'メールアドレスを入力してください。',
            'email.string' => 'メールアドレスの入力内容を確認してください。',

            // パスワードに関するエラーメッセージ
            'password.required' => 'パスワードを入力してください。',
            'password.string' => 'パスワードの入力内容を確認してください。',
        ];
    }
}
