<?php

namespace App\Http\Requests\ProductComments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check(); // любой зарегистрированный может оставить review/question
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['review', 'question'])],

            // Рейтинг обязателен только для review
            'rating' => [
                'nullable',
                'integer',
                'min:1',
                'max:5',
                'required_if:type,review',
                'prohibited_if:type,question',
            ],

            // По ТЗ текст не обязателен
            'body' => ['nullable', 'string', 'max:5000'],

            // Только для review (для question запрещаем)
            'pros' => [
                'nullable',
                'string',
                'max:5000',
                'prohibited_if:type,question',
            ],
            'cons' => [
                'nullable',
                'string',
                'max:5000',
                'prohibited_if:type,question',
            ],

            // Фото: максимум 5
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => [
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp,bmp,gif,tif,tiff',
                'max:10240', // 10MB per image
            ],

            // YouTube: максимум 1 ссылка
            'youtube_url' => [
                'nullable',
                'string',
                'max:500',
                'url',
                'regex:/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\/.+$/i',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = $this->input('type');
            $body = trim((string) $this->input('body', ''));
            $pros = trim((string) $this->input('pros', ''));
            $cons = trim((string) $this->input('cons', ''));
            $hasImages = !empty($this->file('images'));
            $hasYoutube = !empty($this->input('youtube_url'));

            // Для review: хотя бы что-то из контента должно быть
            if ($type === 'review') {
                if ($body === '' && $pros === '' && $cons === '' && !$hasImages && !$hasYoutube) {
                    $validator->errors()->add(
                        'body',
                        'Для відгуку вкажіть хоча б одне поле: текст, переваги, недоліки, фото або YouTube.'
                    );
                }
            }

            // Для question: хотя бы текст или медиа/ссылка
            if ($type === 'question') {
                if ($body === '' && !$hasImages && !$hasYoutube) {
                    $validator->errors()->add(
                        'body',
                        'Для питання вкажіть текст або додайте фото/YouTube.'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Тип коментаря обов’язковий.',
            'type.in' => 'Некоректний тип коментаря.',

            'rating.required_if' => 'Для відгуку рейтинг обов’язковий.',
            'rating.min' => 'Рейтинг має бути від 1 до 5.',
            'rating.max' => 'Рейтинг має бути від 1 до 5.',
            'rating.prohibited_if' => 'Для питання рейтинг не допускається.',

            'body.max' => 'Текст не може перевищувати 5000 символів.',
            'pros.max' => 'Поле "Переваги" не може перевищувати 5000 символів.',
            'cons.max' => 'Поле "Недоліки" не може перевищувати 5000 символів.',
            'pros.prohibited_if' => 'Поле "Переваги" доступне лише для відгуку.',
            'cons.prohibited_if' => 'Поле "Недоліки" доступне лише для відгуку.',

            'images.array' => 'Фото мають бути передані масивом.',
            'images.max' => 'Максимум 5 фото на один коментар.',
            'images.*.image' => 'Файл повинен бути зображенням.',
            'images.*.mimes' => 'Дозволені формати: jpg, jpeg, png, webp, bmp, gif, tif, tiff.',
            'images.*.max' => 'Розмір одного фото не може перевищувати 10MB.',

            'youtube_url.url' => 'YouTube-посилання має бути валідним URL.',
            'youtube_url.regex' => 'Дозволені тільки youtube.com або youtu.be.',
        ];
    }
}