<?php

namespace App\Actions\Fortify;

// 入力チェックのルールを表す型を読み込む
use Illuminate\Contracts\Validation\Rule;
// パスワードの入力条件を指定するクラスを読み込む
use Illuminate\Validation\Rules\Password;

// 複数のクラスで共有できる、パスワードの入力条件をまとめる
trait PasswordValidationRules
{
    /**
     * パスワードの入力条件を配列で返す。
     *
     * @return array<int, Rule|array<mixed>|string>
     */
    protected function passwordRules(): array
    {
        return [
            'required',          // 入力を必須にする
            'string',            // 文字列であることを確認する
            Password::default(), // 標準、またはアプリで設定したパスワード条件を適用する
            'confirmed',         // 確認用パスワードとの一致を確認する
        ];
    }
}
