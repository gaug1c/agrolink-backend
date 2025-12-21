<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Category;
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
            $query->active();
        }

        // Afficher uniquement les catégories parentes
        if ($request->has('parents_only') && $request->parents_only) {
            $query->parents();
        }

        // Inclure les sous-catégories
        if ($request->has('with_children') && $request->with_children) {
            $query->with('children');
        }

        // Inclure le nombre de produits
        if ($request->has('with_products_count') && $request->with_products_count) {
            $query->withCount(['products' => function($q) {
                $q->where('status', 'active');
            }]);
        }

        $categories = $query->orderBy('name')->get();

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
        $categories = Category::with(['children' => function($query) {
            $query->active()->withCount(['products' => function($q) {
                $q->where('status', 'active');
            }]);
        }])
        ->active()
        ->parents()
        ->withCount(['products' => function($q) {
            $q->where('status', 'active');
        }])
        ->orderBy('name')
        ->get();

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
            'name' => 'required|string|max:255|unique:categories',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
            'icon' => 'nullable|string|max:255',
            'is_active' => 'boolean'
        ], [
            'name.required' => 'Le nom de la catégorie est obligatoire',
            'name.unique' => 'Cette catégorie existe déjà',
            'parent_id.exists' => 'La catégorie parente n\'existe pas',
            'image.image' => 'Le fichier doit être une image',
            'image.mimes' => 'Format d\'image accepté: jpeg, png, jpg, svg',
            'image.max' => 'L\'image ne doit pas dépasser 2Mo'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $categoryData = $request->except('image');
            $categoryData['slug'] = Str::slug($request->name);

            // Gérer l'image
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('categories', 'public');
                $categoryData['image'] = $path;
            }

            $category = Category::create($categoryData);

            return response()->json([
                'success' => true,
                'message' => 'Catégorie créée avec succès',
                'data' => $category
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la catégorie',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher une catégorie spécifique avec ses produits
     */
    public function show($id)
    {
        $category = Category::with(['children', 'parent'])
            ->withCount(['products' => function($q) {
                $q->where('status', 'active');
            }])
            ->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Catégorie non trouvée'
            ], 404);
        }

        // Récupérer quelques produits de cette catégorie
        $products = $category->products()
            ->with('producer')
            ->where('status', 'active')
            ->inStock()
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
            'name' => 'sometimes|required|string|max:255|unique:categories,name,' . $id,
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
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
            $categoryData = $request->except('image');

            if ($request->has('name')) {
                $categoryData['slug'] = Str::slug($request->name);
            }

            // Gérer la nouvelle image
            if ($request->hasFile('image')) {
                // Supprimer l'ancienne image
                if ($category->image) {
                    Storage::disk('public')->delete($category->image);
                }
                $path = $request->file('image')->store('categories', 'public');
                $categoryData['image'] = $path;
            }

            $category->update($categoryData);

            return response()->json([
                'success' => true,
                'message' => 'Catégorie mise à jour avec succès',
                'data' => $category
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer une catégorie (Admin)
     */
    public function destroy($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Catégorie non trouvée'
            ], 404);
        }

        // Vérifier si la catégorie a des produits
        if ($category->products()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer cette catégorie car elle contient des produits'
            ], 422);
        }

        // Vérifier si la catégorie a des sous-catégories
        if ($category->children()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer cette catégorie car elle a des sous-catégories'
            ], 422);
        }

        try {
            // Supprimer l'image
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }

            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Catégorie supprimée avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Catégories populaires (les plus utilisées)
     */
    public function popular()
    {
        $categories = Category::active()
            ->withCount(['products' => function($q) {
                $q->where('status', 'active');
            }])
            ->having('products_count', '>', 0)
            ->orderBy('products_count', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Catégories populaires',
            'data' => $categories
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

        $categories = Category::active()
            ->where('name', 'like', "%{$request->q}%")
            ->orWhere('description', 'like', "%{$request->q}%")
            ->withCount(['products' => function($q) {
                $q->where('status', 'active');
            }])
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Résultats de recherche',
            'data' => $categories
        ]);
    }
}