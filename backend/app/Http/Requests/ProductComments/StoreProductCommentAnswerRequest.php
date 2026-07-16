<?php

namespace App\Http\Requests\ProductComments;

use App\Models\ProductComment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductCommentAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();
        if (!$user) return false;

        /** @var ProductComment|null $rootComment */
        $rootComment = $this->route('comment');
        if (!$rootComment) return false;

        // отвечать можно только на корневой review/question
        if ($rootComment->parent_id !== null) return false;
        if (!in_array($rootComment->type, ['review', 'question'], true)) return false;

        // seller товара или admin/manager
        $isAdminOrManager = method_exists($user, 'hasRole')
            ? ($user->hasRole('admin') || $user->hasRole('manager'))
            : false;

        $productOwnerId = (int) optional($rootComment->product)->user_id;
        $isSellerOwner = $productOwnerId > 0 && $productOwnerId === (int)$user->id;

        return $isAdminOrManager || $isSellerOwner;
    }

    public function rules(): array
    {
        return [
            // type/rating/pros/cons не принимаем в answer
            'type' => ['prohibited'],
            'rating' => ['prohibited'],
            'pros' => ['prohibited'],
            'cons' => ['prohibited'],

            // Для ответа тело обязательно
            'body' => ['required', 'string', 'max:5000'],

            // Фото до 5
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => [
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp,bmp,gif,tif,tiff',
                'mimetypes:image/jpeg,image/png,image/webp,image/bmp,image/gif,image/tiff',
                'max:10240',
            ],

            // max 1 youtube
            'youtube_url' => [
                'nullable',
                'string',
                'max:500',
                'url',
                'regex:/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\/.+$/i',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'Текст відповіді обов’язковий.',
            'body.max' => 'Текст відповіді не може перевищувати 5000 символів.',

            'images.max' => 'Максимум 5 фото на одну відповідь.',
            'images.*.image' => 'Файл повинен бути зображенням.',
            'images.*.mimetypes' => 'Некоректний MIME-тип файлу.',
            'images.*.mimes' => 'Дозволені формати: jpg, jpeg, png, webp, bmp, gif, tif, tiff.',
            'images.*.max' => 'Розмір одного фото не може перевищувати 10MB.',

            'youtube_url.url' => 'YouTube-посилання має бути валідним URL.',
            'youtube_url.regex' => 'Дозволені тільки youtube.com або youtu.be.',
        ];
    }
}