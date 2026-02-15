<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * Afficher toutes les catégories
     */
    public function index(Request $request)
    {
        $query = Category::query();

        // Filtrer uniquement les catégories actives
        if ($request->has('active') && $request->active) {
            $query->where('is_active', true);
        }

        // Afficher uniquement les catégories parentes
        if ($request->has('parents_only') && $request->parents_only) {
            $query->whereNull('parent_id');
        }

        // Inclure les sous-catégories
        if ($request->has('with_children') && $request->with_children) {
            $categories = $query->orderBy('name')->get();
            $categories->each(function($category) {
                $category->children = Category::where('parent_id', $category->_id)->get();
            });
        } else {
            $categories = $query->orderBy('name')->get();
        }

        // Inclure le nombre de produits
        if ($request->has('with_products_count') && $request->with_products_count) {
            $categories->each(function($category) {
                $category->products_count = $category->products()->where('status', 'active')->count();
            });
        }

        return response()->json([
            'success' => true,
            'message' => 'Liste des catégories',
            'data' => $categories
        ]);
    }

    /**
     * Afficher les catégories principales avec leurs sous-catégories
     */
    public function tree()
    {
        $categories = Category::where('is_active', true)
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        $categories->each(function($category) {
            $category->children = Category::where('parent_id', $category->_id)
                ->where('is_active', true)
                ->get();
            
            $category->products_count = $category->products()->where('status', 'active')->count();
            
            $category->children->each(function($child) {
                $child->products_count = $child->products()->where('status', 'active')->count();
            });
        });

        return response()->json([
            'success' => true,
            'message' => 'Arbre des catégories',
            'data' => $categories
        ]);
    }

    /**
     * Créer une nouvelle catégorie (Admin)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'is_active' => 'boolean'
        ], [
            'name.required' => 'Le nom de la catégorie est obligatoire'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Vérification unicité du nom
            if (Category::where('name', $request->name)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette catégorie existe déjà'
                ], 422);
            }

            // Vérification que le parent_id existe si fourni
            if ($request->parent_id) {
                $parent = Category::find($request->parent_id);
                if (!$parent) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Catégorie parente non trouvée'
                    ], 404);
                }
            }

            $category = Category::create([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'description' => $request->description,
                'parent_id' => $request->parent_id,
                'icon' => $request->icon,
                'is_active' => $request->is_active ?? true
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Catégorie créée avec succès',
                'data' => $category
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher une catégorie spécifique avec ses produits
     */
    public function show($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Catégorie non trouvée'
            ], 404);
        }

        // Charger les sous-catégories
        $category->children = Category::where('parent_id', $category->_id)->get();
        
        // Charger la catégorie parente si elle existe
        if ($category->parent_id) {
            $category->parent = Category::find($category->parent_id);
        }

        // Nombre de produits
        $category->products_count = $category->products()->where('status', 'active')->count();

        // Récupérer quelques produits de cette catégorie
        $products = $category->products()
            ->where('status', 'active')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Détails de la catégorie',
            'data' => [
                'category' => $category,
                'products' => $products
            ]
        ]);
    }

    /**
     * Mettre à jour une catégorie (Admin)
     */
    public function update(Request $request, $id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Catégorie non trouvée'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Vérifier l'unicité du nom si modifié
            if ($request->has('name') && $request->name !== $category->name) {
                if (Category::where('name', $request->name)->where('_id', '!=', $id)->exists()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ce nom de catégorie existe déjà'
                    ], 422);
                }
            }

            // Vérifier que le parent_id existe si fourni
            if ($request->has('parent_id') && $request->parent_id) {
                // Empêcher qu'une catégorie soit son propre parent
                if ($request->parent_id === $id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Une catégorie ne peut pas être son propre parent'
                    ], 422);
                }

                $parent = Category::find($request->parent_id);
                if (!$parent) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Catégorie parente non trouvée'
                    ], 404);
                }
            }

            $categoryData = $request->only(['name', 'description', 'parent_id', 'icon', 'is_active']);

            if ($request->has('name')) {
                $categoryData['slug'] = Str::slug($request->name);
            }

            $category->update($categoryData);

            return response()->json([
                'success' => true,
                'message' => 'Catégorie mise à jour avec succès',
                'data' => $category->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id)
{
    $category = Category::find($id);

    if (!$category) {
        return response()->json([
            'success' => false,
            'message' => 'Catégorie non trouvée'
        ], 404);
    }

    // 🔥 MongoDB SAFE
    if (Product::where('category_id', $category->_id)->exists()) {
        return response()->json([
            'success' => false,
            'message' => 'Impossible de supprimer cette catégorie car elle contient des produits'
        ], 422);
    }

    if (Category::where('parent_id', $category->_id)->exists()) {
        return response()->json([
            'success' => false,
            'message' => 'Impossible de supprimer cette catégorie car elle a des sous-catégories'
        ], 422);
    }

    $category->delete();

    return response()->json([
        'success' => true,
        'message' => 'Catégorie supprimée avec succès'
    ]);
}

    /**
     * Catégories populaires (les plus utilisées)
     */
    public function popular()
    {
        $categories = Category::where('is_active', true)->get();

        $categoriesWithCount = $categories->map(function($category) {
            $category->products_count = $category->products()->where('status', 'active')->count();
            return $category;
        })->filter(function($category) {
            return $category->products_count > 0;
        })->sortByDesc('products_count')->take(10)->values();

        return response()->json([
            'success' => true,
            'message' => 'Catégories populaires',
            'data' => $categoriesWithCount
        ]);
    }

    /**
     * Rechercher des catégories
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:2'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Veuillez entrer au moins 2 caractères',
                'errors' => $validator->errors()
            ], 422);
        }

        $searchTerm = $request->q;

        $categories = Category::where('is_active', true)
            ->where(function($query) use ($searchTerm) {
                $query->where('name', 'like', "%{$searchTerm}%")
                      ->orWhere('description', 'like', "%{$searchTerm}%");
            })
            ->limit(20)
            ->get();

        $categories->each(function($category) {
            $category->products_count = $category->products()->where('status', 'active')->count();
        });

        return response()->json([
            'success' => true,
            'message' => 'Résultats de recherche',
            'data' => $categories
        ]);
    }
}