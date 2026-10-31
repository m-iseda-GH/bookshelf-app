<?php

namespace App\Http\Requests;

// 登録者IDの存在確認に使うユーザーモデルを読み込む
use App\Models\User;
// データの存在確認に使うルールを読み込む
use Illuminate\Validation\Rule;

// Web版の入力条件を引き継ぎ、API用の登録者IDの条件を追加する
class ApiBookRequest extends BookRequest
{
    // 登録・更新時の入力条件を指定する
    public function rules(): array
    {
        // 親クラスの入力条件に、登録者IDの条件を追加する
        return array_merge(parent::rules(), [
            'user_id' => [
                'bail',     // 条件に違反した時点で、この項目のチェックを止める
                'required', // 登録者IDの指定を必須にする
                'integer',  // 整数として扱える値であることを確認する
                // 指定されたユーザーが実際に存在することを確認する
                Rule::exists(User::class, 'id'),
            ],
        ]);
    }

    // Web版のメッセージに、登録者IDの日本語メッセージを追加する
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'user_id.required' => '登録者を指定してください。',
            'user_id.integer' => '登録者の指定内容を確認してください。',
            'user_id.exists' => '指定された登録者は登録されていません。',
        ]);
    }
}
