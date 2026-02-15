<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Category extends Model
{
    /**
     * La connexion de base de données à utiliser
     */
    protected $connection = 'mongodb';

    /**
     * Le nom de la collection MongoDB
     */
    protected $collection = 'categories';

    /**
     * Les attributs qui peuvent être assignés en masse
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
        'icon',
        'image',
        'is_active'
    ];

    /**
     * Les attributs qui doivent être castés
     */
    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Les valeurs par défaut des attributs
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Relation: Catégorie parente
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Relation: Sous-catégories (enfants)
     */
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Relation: Produits de cette catégorie
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    /**
     * Scope: Catégories actives uniquement
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Catégories parentes uniquement
     */
    public function scopeParents($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope: Sous-catégories uniquement
     */
    public function scopeChildren($query)
    {
        return $query->whereNotNull('parent_id');
    }

    /**
     * Accesseur pour l'URL de l'image
     */
    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return asset('storage/' . $this->image);
        }
        return null;
    }

    /**
     * Mutateur pour générer automatiquement le slug
     */
    public function setNameAttribute($value)
    {
        $this->attributes['name'] = $value;
        $this->attributes['slug'] = \Illuminate\Support\Str::slug($value);
    }
}