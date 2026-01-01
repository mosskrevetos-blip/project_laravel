<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'parent_id',
        'icon_url',
        'image_url'
    ];

    /**
     * Получить родительскую категорию.
     * Связь "принадлежит к" (belongsTo)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Получить дочерние категории.
     * Связь "имеет много" (hasMany)
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    // Зв'язок з товарами
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    // Зв'язок з атрибутами
    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'attribute_category', 'category_id', 'attribute_id');
    }
}