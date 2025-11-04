<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Str;

class FileService
{
    /**
     * Генерирует уникальное имя файла на основе ID пользователя и даты.
     * Пример: 5_26_10_2025_17_40_55.webp
     *
     * @param UploadedFile $file
     * @return string
     */
    public function generateUniqueFilename(UploadedFile $file): string
    {
        $userId = Auth::id() ?? 'guest';
        $timestamp = Carbon::now()->format('d_m_Y_H_i_s');
        
        // Генерируем 4 случайных символа для уникальности
        $random = Str::random(4); 

        // Принудительно устанавливаем расширение .webp
        $extension = 'webp';

        return "{$userId}_{$timestamp}_{$random}.{$extension}";
    }
}