<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Attribute;
use App\Models\Category;

class AttributeSeeder extends Seeder
{
    public function run(): void
    {
        // --- Создаём атрибуты (используем firstOrCreate для безопасности) ---
        $floors = Attribute::firstOrCreate(['slug' => 'floors'], ['name' => 'Этажность', 'type' => 'number']);
        $area = Attribute::firstOrCreate(['slug' => 'area'], ['name' => 'Общая площадь', 'type' => 'text']);
        $rooms = Attribute::firstOrCreate(['slug' => 'rooms'], ['name' => 'Количество комнат', 'type' => 'number']);
        
        $breed = Attribute::firstOrCreate(['slug' => 'breed'], ['name' => 'Порода', 'type' => 'text']);
        $vaccinations = Attribute::firstOrCreate(['slug' => 'vaccinations'], ['name' => 'Прививки', 'type' => 'boolean']);
        
        // --- Находим категории ---
        // Убедитесь, что у вас есть категории с такими title в базе, иначе сидер ничего не привяжет
        $apartmentsCategory = Category::where('title', 'Квартиры')->first();
        $catsCategory = Category::where('title', 'Коты')->first();

        // --- Привязываем атрибуты к категориям с помощью sync() ---
        if ($apartmentsCategory) {
            // sync() гарантирует, что будут только эти связи, без дубликатов
            $apartmentsCategory->attributes()->sync([$floors->id, $area->id, $rooms->id]);
        }
        if ($catsCategory) {
            $catsCategory->attributes()->sync([$breed->id, $vaccinations->id]);
        }
    }
}