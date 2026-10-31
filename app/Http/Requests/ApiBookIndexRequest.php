<?php

namespace App\Http\Requests;

// 指定されたジャンルの存在確認に使うモデルを読み込む
use App\Models\Genre;
// 入力チェックを管理する基本クラスを読み込む
use Illuminate\Foundation\Http\FormRequest;
// データの存在確認に使うルールを読み込む
use Illuminate\Validation\Rule;

// 書籍一覧APIの検索条件を確認する
class ApiBookIndexRequest extends FormRequest
{
    // 認証なしで一覧APIを利用できるようにする
    public function authorize(): bool
    {
        return true;
    }

    // URLで受け取る検索条件を指定する
    public function rules(): array
    {
        return [
            // キーワードは任意入力・文字列・255文字以内にする
            'keyword' => ['bail', 'nullable', 'string', 'max:255'],

            // ジャンルIDは任意入力とし、指定された場合は存在を確認する
            'genre_id' => [
                'bail',
                'nullable',
                'integer',
                Rule::exists(Genre::class, 'id'),
            ],

            // ページ番号が指定された場合は、1以上の整数にする
            'page' => ['bail', 'sometimes', 'required', 'integer', 'min:1'],

            // 件数が指定された場合は、1〜100の整数にする
            'per_page' => ['bail', 'sometimes', 'required', 'integer', 'between:1,100'],
        ];
    }

    // 検索条件に問題がある場合の日本語メッセージを指定する
    public function messages(): array
    {
        return [
            'keyword.string' => 'キーワードの入力内容を確認してください。',
            'keyword.max' => 'キーワードは:max文字以内で入力してください。',

            'genre_id.integer' => 'ジャンルの指定内容を確認してください。',
            'genre_id.exists' => '指定されたジャンルは登録されていません。',

            'page.required' => 'ページ番号を指定してください。',
            'page.integer' => 'ページ番号は1以上の整数で指定してください。',
            'page.min' => 'ページ番号は1以上の整数で指定してください。',

            'per_page.required' => '1ページの件数を指定してください。',
            'per_page.integer' => '1ページの件数は1〜100の整数で指定してください。',
            'per_page.between' => '1ページの件数は1〜100の整数で指定してください。',
        ];
    }
}
