<?php

namespace App\Http\Requests\ProductComments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReactToProductCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'reaction' => ['required', Rule::in(['like', 'dislike'])],
        ];
    }

    public function messages(): array
    {
        return [
            'reaction.required' => 'Реакція обов’язкова.',
            'reaction.in' => 'Дозволені реакції: like або dislike.',
        ];
    }
}