<?php

namespace App\Http\Requests\ProductComments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUpdateProductCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();
        if (!$user) return false;

        return method_exists($user, 'hasRole')
            ? ($user->hasRole('admin') || $user->hasRole('manager'))
            : false;
    }

    public function rules(): array
    {
        return [
            // Можно редактировать содержимое админом
            'body' => ['nullable', 'string', 'max:5000'],
            'pros' => ['nullable', 'string', 'max:5000'],
            'cons' => ['nullable', 'string', 'max:5000'],

            // Можно менять рейтинг только review
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],

            // Можно заменить/добавить медиа
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => [
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp,bmp,gif,tif,tiff',
                'max:10240',
            ],
            'youtube_url' => [
                'nullable',
                'string',
                'max:500',
                'url',
                'regex:/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\/.+$/i',
            ],

            // Список id медиа для удаления
            'remove_media_ids' => ['nullable', 'array'],
            'remove_media_ids.*' => ['integer', 'exists:product_comment_media,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $comment = $this->route('comment');

            if (!$comment) return;

            // rating только для review
            if ($this->filled('rating') && $comment->type !== 'review') {
                $validator->errors()->add('rating', 'Рейтинг можна редагувати тільки для відгуку.');
            }

            // pros/cons только для review
            if (($this->filled('pros') || $this->filled('cons')) && $comment->type !== 'review') {
                $validator->errors()->add('pros', 'Поля переваги/недоліки доступні тільки для відгуків.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'body.max' => 'Текст не може перевищувати 5000 символів.',
            'pros.max' => 'Поле "Переваги" не може перевищувати 5000 символів.',
            'cons.max' => 'Поле "Недоліки" не може перевищувати 5000 символів.',
            'rating.min' => 'Рейтинг має бути від 1 до 5.',
            'rating.max' => 'Рейтинг має бути від 1 до 5.',

            'images.max' => 'Максимум 5 фото.',
            'images.*.image' => 'Файл повинен бути зображенням.',
            'images.*.mimes' => 'Дозволені формати: jpg, jpeg, png, webp, bmp, gif, tif, tiff.',
            'images.*.max' => 'Розмір одного фото не може перевищувати 10MB.',

            'youtube_url.url' => 'YouTube-посилання має бути валідним URL.',
            'youtube_url.regex' => 'Дозволені тільки youtube.com або youtu.be.',
        ];
    }
}