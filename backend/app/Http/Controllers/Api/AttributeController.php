<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AttributeController extends Controller
{
    public function index()
    {
        // Загружаем атрибуты вместе с их опциями и привязанными категориями
        return Attribute::with(['options', 'categories'])->latest()->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:attributes,slug',
            'type' => ['required', Rule::in(['text', 'number', 'boolean', 'select'])],
            'categories' => 'required|array|min:1',
            'categories.*' => 'required|exists:categories,id',
            'options' => 'nullable|array',
            'options.*.value' => 'required|string',
        ]);

        $attribute = DB::transaction(function () use ($validated) {
            // 1. Создаём сам атрибут
            $attribute = Attribute::create([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'type' => $validated['type'],
            ]);

            // 2. Если есть опции, создаём их
            if ($validated['type'] === 'select' && !empty($validated['options'])) {
                $optionsToCreate = array_map(function($option) {
                    return ['value' => $option['value']];
                }, $validated['options']);

                $attribute->options()->createMany($optionsToCreate);
            }

            // 3. Привязываем категории
            $attribute->categories()->sync($validated['categories']);

            return $attribute;
        });

        return response()->json($attribute->load(['options', 'categories']), 201);
    }

    public function show(Attribute $attribute)
    {
        return $attribute->load(['options', 'categories']);
    }

    public function update(Request $request, Attribute $attribute)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', Rule::unique('attributes')->ignore($attribute->id)],
            'type' => ['required', Rule::in(['text', 'number', 'boolean', 'select'])],
            'categories' => 'required|array|min:1',
            'categories.*' => 'required|exists:categories,id',
            'options' => 'nullable|array',
            'options.*.value' => 'required|string',
        ]);

        $attribute = DB::transaction(function () use ($validated, $attribute) {
            // 1. Обновляем атрибут
            $attribute->update([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'type' => $validated['type'],
            ]);

            // 2. Обновляем опции (удаляем старые, создаём новые)
            $attribute->options()->delete();
            if ($validated['type'] === 'select' && !empty($validated['options'])) {
                $optionsToCreate = array_map(function($option) {
                    return ['value' => $option['value']];
                }, $validated['options']);

                $attribute->options()->createMany($optionsToCreate);
            }

            // 3. Синхронизируем категории
            $attribute->categories()->sync($validated['categories']);

            return $attribute;
        });

        return response()->json($attribute->load(['options', 'categories']));
    }

    public function destroy(Attribute $attribute)
    {
        $attribute->delete();
        return response()->json(null, 204);
    }
}