<?php

namespace App\Actions\Fortify;

// ユーザー登録の入力条件とエラーメッセージを読み込む
use App\Http\Requests\RegisterRequest;
// usersテーブルへの登録に使うUserモデルを読み込む
use App\Models\User;
// パスワードをハッシュ化する機能を読み込む
use Illuminate\Support\Facades\Hash;
// 入力内容をチェックする機能を読み込む
use Illuminate\Support\Facades\Validator;
// 入力チェックに失敗したときの例外を表す型を読み込む
use Illuminate\Validation\ValidationException;
// Fortifyがユーザー登録処理に求めるインターフェースを読み込む
use Laravel\Fortify\Contracts\CreatesNewUsers;

// Fortifyから呼び出されるユーザー登録処理を定義する
class CreateNewUser implements CreatesNewUsers
{
    /**
     * 入力内容をチェックし、問題がなければユーザーを登録する。
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        // 入力条件と日本語メッセージを取得するためのオブジェクトを作る
        $formRequest = new RegisterRequest;

        // FormRequestに定義した条件でチェックし、通過した入力値を取得する
        $validated = Validator::make(
            $input,
            $formRequest->rules(),
            $formRequest->messages()
        )->validate();

        // チェックを通過した値でユーザーを登録する
        return User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            // パスワードを元に戻せない形式に変換して保存する
            'password' => Hash::make($validated['password']),
        ]);
    }
}
