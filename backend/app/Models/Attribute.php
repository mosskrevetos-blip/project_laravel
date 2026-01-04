<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Attribute extends Model
{

    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['name', 'slug', 'type']; 

    
    // Додаю зв'язок атрибутів з їх опціями
    public function options(): HasMany
    {
        return $this->hasMany(AttributeOption::class);
    }

    // Зв'язок з категоріями
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,           // Указываем связанную модель
            'attribute_category',      // Имя связи (pivot-таблицы)
            'attribute_id',            // Внешний ключ на текущую модель (attributes)
            'category_id'              // Внешний ключ на связанную модель (categories)
        );
    }
}
