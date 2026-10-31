<?php

namespace App\Http\Requests;

// ジャンル名の重複確認に使うモデルを読み込む
use App\Models\Genre;
// 入力チェックとリクエストの許可を管理する基本クラスを読み込む
use Illuminate\Foundation\Http\FormRequest;
// 登録済みデータとの重複チェックに使うルールを読み込む
use Illuminate\Validation\Rule;

// ジャンルの登録・更新に共通する入力条件をまとめる
class GenreRequest extends FormRequest
{
    // ログイン確認はルート側で行う
    public function authorize(): bool
    {
        return true;
    }

    // ジャンル名の入力条件を指定する
    public function rules(): array
    {
        return [
            'name' => [
                'bail',     // 条件に違反した時点で、この項目のチェックを止める
                'required', // 入力を必須にする
                'string',   // 文字列であることを確認する
                'max:255',  // 保存先の列に合わせ、255文字以内にする
                // 更新時は、URLで指定されたジャンル自身を重複確認から除外する
                Rule::unique(Genre::class, 'name')->ignore($this->route('genre')),
            ],
        ];
    }

    // 入力条件を満たさなかった場合の日本語メッセージを指定する
    public function messages(): array
    {
        return [
            'name.required' => 'ジャンル名を入力してください。',
            'name.string' => 'ジャンル名の入力内容を確認してください。',
            'name.max' => 'ジャンル名は:max文字以内で入力してください。',
            'name.unique' => 'このジャンル名はすでに登録されています。',
        ];
    }
}
