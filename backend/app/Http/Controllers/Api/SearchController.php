<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SearchHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Пошук товарів за назвою серед опублікованих товарів.
     */
    public function products(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $perPage = (int) $request->query('per_page', 12);

        if ($perPage <= 0) {
            $perPage = 12;
        }

        if ($query === '') {
            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                ],
                'links' => [
                    'first' => null,
                    'last' => null,
                    'prev' => null,
                    'next' => null,
                ],
                'query' => '',
            ]);
        }

        $products = Product::query()
            ->with(['category', 'user'])
            ->publishedForSearch()
            ->where('title', 'like', '%' . $query . '%')
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'data' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
            'links' => [
                'first' => $products->url(1),
                'last' => $products->url($products->lastPage()),
                'prev' => $products->previousPageUrl(),
                'next' => $products->nextPageUrl(),
            ],
            'query' => $query,
        ]);
    }

    /**
     * Підказки по назвах товарів.
     */
    public function suggestions(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if ($query === '') {
            return response()->json([]);
        }

        $suggestions = Product::query()
            ->select('title')
            ->publishedForSearch()
            ->where('title', 'like', '%' . $query . '%')
            ->distinct()
            ->orderBy('title')
            ->limit(8)
            ->pluck('title')
            ->map(fn ($title) => ['title' => $title])
            ->values();

        return response()->json($suggestions);
    }

    /**
     * Отримати історію пошуку авторизованого користувача.
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();

        $items = $user->searchHistories()
            ->where('is_visible', true)
            ->latest('searched_at')
            ->limit(10)
            ->get(['id', 'query', 'result_count', 'is_visible', 'searched_at']);

        return response()->json($items);
    }

    /**
     * Зберегти один пошуковий запит авторизованого користувача.
     */
    public function storeHistory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string'],
            'result_count' => ['required', 'integer', 'min:0'],
            'searched_at' => ['required', 'date'],
        ]);

        $query = trim($validated['query']);

        if ($query === '') {
            return response()->json([
                'message' => 'Порожній пошуковий запит не зберігається.',
            ], 422);
        }

        $history = SearchHistory::create([
            'user_id' => $request->user()->id,
            'query' => $query,
            'result_count' => $validated['result_count'],
            'is_visible' => true,
            'searched_at' => $validated['searched_at'],
        ]);

        return response()->json($history, 201);
    }

    /**
     * Синхронізувати guest history в БД після логіну/реєстрації.
     */
    public function syncHistory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.query' => ['required', 'string'],
            'items.*.result_count' => ['required', 'integer', 'min:0'],
            'items.*.searched_at' => ['required', 'date'],
        ]);

        $user = $request->user();
        $createdCount = 0;

        foreach ($validated['items'] as $item) {
            $query = trim($item['query']);

            if ($query === '') {
                continue;
            }

            SearchHistory::create([
                'user_id' => $user->id,
                'query' => $query,
                'result_count' => $item['result_count'],
                'is_visible' => true,
                'searched_at' => $item['searched_at'],
            ]);

            $createdCount++;
        }

        return response()->json([
            'message' => 'Історію пошуку синхронізовано успішно.',
            'created' => $createdCount,
        ]);
    }

    public function destroyHistoryItem(Request $request, SearchHistory $history): JsonResponse
    {
        if ($history->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Доступ заборонено.',
            ], 403);
        }

        $history->update([
            'is_visible' => false,
        ]);

        return response()->json([
            'message' => 'Запис історії видалено успішно.',
        ]);
    }

    public function clearHistory(Request $request): JsonResponse
    {
        $request->user()->searchHistories()
            ->where('is_visible', true)
            ->update([
                'is_visible' => false,
            ]);

        return response()->json([
            'message' => 'Історію пошуку очищено успішно.',
        ]);
    }
}