<?php

namespace App\Http\Requests\ProductComments;

use App\Models\ProductCommentMedia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUpdateProductCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        return method_exists($user, 'hasRole')
            ? ($user->hasRole('admin') || $user->hasRole('manager'))
            : false;
    }

    /**
     * ВАЖНО:
     * При multipart/form-data поле media_sync часто приходит строкой JSON.
     * Здесь приводим его к array ДО валидации.
     */
    protected function prepareForValidation(): void
    {
        $mediaSync = $this->input('media_sync');

        if (is_string($mediaSync) && $mediaSync !== '') {
            $decoded = json_decode($mediaSync, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge([
                    'media_sync' => $decoded,
                ]);
            }
        }

        // normalize remove_media_ids if comes as json string
        $removeIds = $this->input('remove_media_ids');
        if (is_string($removeIds) && $removeIds !== '') {
            $decoded = json_decode($removeIds, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge([
                    'remove_media_ids' => $decoded,
                ]);
            }
        }
    }

    public function rules(): array
    {
        return [
            // editable content
            'body' => ['nullable', 'string', 'max:5000'],
            'pros' => ['nullable', 'string', 'max:5000'],
            'cons' => ['nullable', 'string', 'max:5000'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],

            // new images append
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => [
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp,bmp,gif,tif,tiff',
                'max:10240',
            ],

            // optional youtube append
            'youtube_url' => [
                'nullable',
                'string',
                'max:500',
                'url',
                'regex:/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\/.+$/i',
            ],

            // legacy: explicit remove ids
            'remove_media_ids' => ['nullable', 'array'],
            'remove_media_ids.*' => ['integer', 'exists:product_comment_media,id'],

            // full media sync mode
            'media_sync' => ['nullable', 'array'],
            'media_sync.*.id' => ['nullable', 'integer'],
            'media_sync.*.type' => ['required_with:media_sync', Rule::in(['image', 'youtube'])],
            'media_sync.*.url' => ['nullable', 'string', 'max:2000'],
            'media_sync.*.external_url' => ['nullable', 'string', 'max:2000'],
            'media_sync.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $comment = $this->route('comment');

            if (!$comment) {
                return;
            }

            // rating only for review
            if ($this->filled('rating') && $comment->type !== 'review') {
                $validator->errors()->add('rating', 'Рейтинг можна редагувати тільки для відгуку.');
            }

            // pros/cons only for review
            if (($this->filled('pros') || $this->filled('cons')) && $comment->type !== 'review') {
                $validator->errors()->add('pros', 'Поля переваги/недоліки доступні тільки для відгуків.');
            }

            // remove_media_ids must belong to this comment
            if ($this->filled('remove_media_ids')) {
                $ids = collect($this->input('remove_media_ids', []))
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();

                if ($ids->isNotEmpty()) {
                    $badExists = ProductCommentMedia::query()
                        ->whereIn('id', $ids->all())
                        ->where('comment_id', '!=', $comment->id)
                        ->exists();

                    if ($badExists) {
                        $validator->errors()->add(
                            'remove_media_ids',
                            'Серед media ID є елементи, що не належать цьому коментарю.'
                        );
                    }
                }
            }

            // media_sync ids must belong to this comment
            if ($this->filled('media_sync')) {
                $ids = collect($this->input('media_sync', []))
                    ->pluck('id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();

                if ($ids->isNotEmpty()) {
                    $badExists = ProductCommentMedia::query()
                        ->whereIn('id', $ids->all())
                        ->where('comment_id', '!=', $comment->id)
                        ->exists();

                    if ($badExists) {
                        $validator->errors()->add(
                            'media_sync',
                            'Серед media_sync є ID, що не належать цьому коментарю.'
                        );
                    }
                }

                $ytCount = collect($this->input('media_sync', []))
                    ->filter(fn ($item) => ($item['type'] ?? null) === 'youtube')
                    ->count();

                if ($ytCount > 1) {
                    $validator->errors()->add('media_sync', 'Дозволено лише 1 YouTube-посилання на коментар.');
                }
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
            'media_sync.array' => 'Поле media_sync повинно бути масивом.',
        ];
    }
}