<?php

namespace App\Http\Requests;

// ISBNの重複確認に使う書籍モデルを読み込む
use App\Models\Book;
// 選択されたジャンルの存在確認に使うモデルを読み込む
use App\Models\Genre;
// 入力チェックを管理する基本クラスを読み込む
use Illuminate\Foundation\Http\FormRequest;
// 重複確認や存在確認のルールを読み込む
use Illuminate\Validation\Rule;

// 書籍登録・編集の入力条件をまとめる
class BookRequest extends FormRequest
{
    // ログイン確認はルート側で行い、このリクエストを許可する
    public function authorize(): bool
    {
        return true;
    }

    // 各入力項目の条件を指定する
    public function rules(): array
    {
        // 編集時の対象書籍を取得する。登録時はnull（対象なし）になる
        $book = $this->route('book');

        return [
            // タイトルは必須・文字列・255文字以内にする
            'title' => ['required', 'string', 'max:255'],

            // 著者名は必須・文字列・255文字以内にする
            'author' => ['required', 'string', 'max:255'],

            'isbn' => [
                // 条件に失敗したら、この項目の残りのチェックを止める
                'bail',
                'required', // 入力を必須にする
                'string',   // 文字列であることを確認する
                'digits:13', // 半角数字13桁であることを確認する
                // ISBNの重複を禁止する。編集時は対象書籍自身を除外する
                Rule::unique(Book::class, 'isbn')->ignore($book),
            ],

            // 出版日は必須・有効な日付にする
            'published_date' => ['required', 'date'],

            // 説明は任意入力・文字列・500文字以内にする
            'description' => ['nullable', 'string', 'max:500'],

            // 画像URLは任意入力・URL形式・2048文字以内にする
            'image_url' => ['nullable', 'string', 'url', 'max:2048'],

            // ジャンルは配列として受け取り、1つ以上の選択を必須にする
            'genres' => ['required', 'array', 'min:1'],

            // 選択された各ジャンルIDが整数で、実際に存在することを確認する
            'genres.*' => [
                'required',
                'integer',
                Rule::exists(Genre::class, 'id'),
            ],
        ];
    }

    // 入力条件を満たさなかった場合のメッセージを指定する
    public function messages(): array
    {
        return [
            // タイトルに関するメッセージ
            'title.required' => 'タイトルを入力してください。',
            'title.string' => 'タイトルの入力内容を確認してください。',
            'title.max' => 'タイトルは:max文字以内で入力してください。',

            // 著者名に関するメッセージ
            'author.required' => '著者名を入力してください。',
            'author.string' => '著者名の入力内容を確認してください。',
            'author.max' => '著者名は:max文字以内で入力してください。',

            // ISBNに関するメッセージ
            'isbn.required' => 'ISBNを入力してください。',
            'isbn.string' => 'ISBNの入力内容を確認してください。',
            'isbn.digits' => 'ISBNは半角数字:digits桁で入力してください。',
            'isbn.unique' => 'このISBNはすでに登録されています。',

            // 出版日に関するメッセージ
            'published_date.required' => '出版日を入力してください。',
            'published_date.date' => '出版日の入力内容を確認してください。',

            // 説明に関するメッセージ
            'description.string' => '説明の入力内容を確認してください。',
            'description.max' => '説明は:max文字以内で入力してください。',

            // 画像URLに関するメッセージ
            'image_url.string' => '画像URLの入力内容を確認してください。',
            'image_url.url' => '画像URLの入力内容を確認してください。',
            'image_url.max' => '画像URLは:max文字以内で入力してください。',

            // ジャンル選択に関するメッセージ
            'genres.required' => 'ジャンルを1つ以上選択してください。',
            'genres.array' => 'ジャンルの選択内容を確認してください。',
            'genres.min' => 'ジャンルを1つ以上選択してください。',
            'genres.*.required' => 'ジャンルの選択内容を確認してください。',
            'genres.*.integer' => 'ジャンルの選択内容を確認してください。',
            'genres.*.exists' => 'ジャンルの選択内容を確認してください。',
        ];
    }
}
