<?php

namespace App\Http\Requests\ProductComments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListProductCommentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['review', 'question'])],

            // Reviews: date_desc/date_asc/rating_desc/rating_asc/helpful_desc/helpful_asc
            // Questions: date_desc/date_asc/helpful_desc/helpful_asc
            'sort' => ['nullable', Rule::in([
                'date_desc', 'date_asc',
                'rating_desc', 'rating_asc',
                'helpful_desc', 'helpful_asc',
            ])],

            'rating' => ['nullable', 'integer', 'min:1', 'max:5'], // only for review
            'with_photos' => ['nullable', 'boolean'],
            'verified' => ['nullable', 'boolean'],

            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = $this->input('type');
            $sort = $this->input('sort');
            $rating = $this->input('rating');

            if ($type === 'question') {
                if (in_array($sort, ['rating_desc', 'rating_asc'], true)) {
                    $validator->errors()->add('sort', 'Сортування за рейтингом доступне лише для відгуків.');
                }

                if (!is_null($rating)) {
                    $validator->errors()->add('rating', 'Фільтр по рейтингу доступний лише для відгуків.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Тип списку обов’язковий.',
            'type.in' => 'Некоректний тип списку.',
            'per_page.max' => 'Максимум 100 записів на сторінку.',
        ];
    }
}