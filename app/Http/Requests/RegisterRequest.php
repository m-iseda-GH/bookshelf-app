<?php

namespace App\Http\Requests;

// メールアドレスの重複確認に使うUserモデルを読み込む
use App\Models\User;
// 入力チェックとリクエストの許可を管理する基本クラスを読み込む
use Illuminate\Foundation\Http\FormRequest;
// メールアドレスの重複チェックに使うルールを読み込む
use Illuminate\Validation\Rule;
// パスワードの入力条件を指定するクラスを読み込む
use Illuminate\Validation\Rules\Password;

// ユーザー登録時の入力条件をまとめる
class RegisterRequest extends FormRequest
{
    // 未ログインの利用者も登録リクエストを送信できるようにする
    public function authorize(): bool
    {
        return true;
    }

    // 登録画面から受け取る各項目の入力条件を指定する
    public function rules(): array
    {
        return [
            // 名前は必須・文字列・255文字以内にする
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required', // 入力を必須にする
                'string',   // 文字列であることを確認する
                'email',    // メールアドレスの形式を確認する
                'max:255',  // 255文字以内にする
                // 登録済みのメールアドレスと重複しないことを確認する
                Rule::unique(User::class),
            ],

            'password' => [
                'required',          // 入力を必須にする
                'string',            // 文字列であることを確認する
                Password::default(), // 現在の標準パスワード条件を適用する
                'confirmed',         // 確認用パスワードとの一致を確認する
            ],
        ];
    }

    // 入力条件を満たさなかった場合のメッセージを日本語で指定する
    public function messages(): array
    {
        return [
            // 名前に関するエラーメッセージ
            'name.required' => 'お名前を入力してください。',
            'name.string' => 'お名前の入力内容を確認してください。',
            'name.max' => 'お名前は:max文字以内で入力してください。',

            // メールアドレスに関するエラーメッセージ
            'email.required' => 'メールアドレスを入力してください。',
            'email.string' => 'メールアドレスの入力内容を確認してください。',
            'email.email' => 'メールアドレスを正しい形式で入力してください。',
            'email.max' => 'メールアドレスは:max文字以内で入力してください。',
            'email.unique' => 'このメールアドレスはすでに登録されています。',

            // パスワードに関するエラーメッセージ
            'password.required' => 'パスワードを入力してください。',
            'password.string' => 'パスワードの入力内容を確認してください。',
            'password.min' => 'パスワードは:min文字以上で入力してください。',
            'password.confirmed' => '確認用パスワードが一致しません。',
        ];
    }
}
