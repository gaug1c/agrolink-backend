<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Afficher la liste des produits avec filtres
     * Adapté pour Agrolink Gabon
     */
    public function index(Request $request)
    {
        $query = Product::with(['category', 'producer'])
            ->where('status', 'active');

        // Recherche par mot-clé
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('local_name', 'like', "%{$search}%");
            });
        }

        // Filtrer par catégorie
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filtrer par région de production
        if ($request->has('region')) {
            $query->where('region', $request->region);
        }

        // Filtrer par ville
        if ($request->has('city')) {
            $query->where('city', $request->city);
        }

        // Filtrer par type (bio, local, importé)
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filtrer par disponibilité
        if ($request->has('in_stock') && $request->in_stock) {
            $query->where('stock', '>', 0);
        }

        // Filtrer par prix
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Filtrer par producteur
        if ($request->has('producer_id')) {
            $query->where('producer_id', $request->producer_id);
        }

        // Produits en promotion
        if ($request->has('on_sale') && $request->on_sale) {
            $query->whereNotNull('discount_price');
        }

        // Tri
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        switch ($sortBy) {
            case 'price_asc':
                $query->orderBy('price', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('price', 'desc');
                break;
            case 'name':
                $query->orderBy('name', 'asc');
                break;
            case 'popularity':
                $query->orderBy('views', 'desc');
                break;
            default:
                $query->orderBy($sortBy, $sortOrder);
        }

        $perPage = $request->get('per_page', 15);
        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Liste des produits',
            'data' => $products,
            'currency' => 'FCFA'
        ]);
    }

    /**
     * Créer un nouveau produit (pour les producteurs)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'local_name' => 'nullable|string|max:255',
            'description' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0|lt:price',
            'unit' => 'required|in:kg,g,l,ml,piece,bunch,bag,box',
            'stock' => 'required|integer|min:0',
            'min_order_quantity' => 'nullable|integer|min:1',
            'region' => 'required|string',
            'city' => 'required|string|in:Libreville,Port-Gentil,Franceville,Oyem,Moanda,Mouila,Lambaréné,Tchibanga,Koulamoutou,Makokou',
            'type' => 'required|in:bio,local,conventionnel',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'harvest_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after:harvest_date',
            'shipping_cost' => 'nullable|numeric|min:0',
            'available_for_delivery' => 'boolean',
            'available_for_pickup' => 'boolean'
        ], [
            'name.required' => 'Le nom du produit est obligatoire',
            'description.required' => 'La description est obligatoire',
            'category_id.required' => 'La catégorie est obligatoire',
            'category_id.exists' => 'Cette catégorie n\'existe pas',
            'price.required' => 'Le prix est obligatoire',
            'price.min' => 'Le prix doit être positif',
            'discount_price.lt' => 'Le prix promotionnel doit être inférieur au prix normal',
            'unit.required' => 'L\'unité de mesure est obligatoire',
            'stock.required' => 'La quantité en stock est obligatoire',
            'region.required' => 'La région de production est obligatoire',
            'city.required' => 'La ville est obligatoire',
            'type.required' => 'Le type de produit est obligatoire'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $productData = $request->except('images');
            $productData['producer_id'] = $request->user()->id;
            $productData['slug'] = Str::slug($request->name);
            $productData['status'] = 'active';

            $product = Product::create($productData);

            // Gestion des images
            if ($request->hasFile('images')) {
                $images = [];
                foreach ($request->file('images') as $image) {
                    $path = $image->store('products', 'public');
                    $images[] = $path;
                }
                $product->images = json_encode($images);
                $product->save();
            }

            $product->load('category', 'producer');

            return response()->json([
                'success' => true,
                'message' => 'Produit créé avec succès',
                'data' => $product
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du produit',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher les détails d'un produit
     */
    public function show($id)
    {
        $product = Product::with(['category', 'producer', 'reviews.user'])
            ->where('status', 'active')
            ->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produit non trouvé'
            ], 404);
        }

        // Incrémenter le compteur de vues
        $product->increment('views');

        // Produits similaires
        $similarProducts = Product::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('status', 'active')
            ->limit(4)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Détails du produit',
            'data' => [
                'product' => $product,
                'similar_products' => $similarProducts
            ],
            'currency' => 'FCFA'
        ]);
    }

    /**
     * Mettre à jour un produit
     */
    public function update(Request $request, $id)
    {
        $product = Product::where('producer_id', $request->user()->id)->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produit non trouvé ou non autorisé'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'local_name' => 'nullable|string|max:255',
            'description' => 'sometimes|required|string',
            'category_id' => 'sometimes|required|exists:categories,id',
            'price' => 'sometimes|required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'unit' => 'sometimes|required|in:kg,g,l,ml,piece,bunch,bag,box',
            'stock' => 'sometimes|required|integer|min:0',
            'status' => 'sometimes|in:active,inactive,out_of_stock',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $product->update($request->except('images'));

            // Gestion des nouvelles images
            if ($request->hasFile('images')) {
                // Supprimer les anciennes images
                if ($product->images) {
                    $oldImages = json_decode($product->images, true);
                    foreach ($oldImages as $oldImage) {
                        Storage::disk('public')->delete($oldImage);
                    }
                }

                // Ajouter les nouvelles images
                $images = [];
                foreach ($request->file('images') as $image) {
                    $path = $image->store('products', 'public');
                    $images[] = $path;
                }
                $product->images = json_encode($images);
                $product->save();
            }

            $product->load('category', 'producer');

            return response()->json([
                'success' => true,
                'message' => 'Produit mis à jour avec succès',
                'data' => $product
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
     * Supprimer un produit
     */
    public function destroy(Request $request, $id)
    {
        $product = Product::where('producer_id', $request->user()->id)->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produit non trouvé ou non autorisé'
            ], 404);
        }

        try {
            // Supprimer les images
            if ($product->images) {
                $images = json_decode($product->images, true);
                foreach ($images as $image) {
                    Storage::disk('public')->delete($image);
                }
            }

            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Produit supprimé avec succès'
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
     * Produits par catégorie
     */
    public function byCategory($categoryId)
    {
        $category = Category::find($categoryId);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Catégorie non trouvée'
            ], 404);
        }

        $products = Product::with('producer')
            ->where('category_id', $categoryId)
            ->where('status', 'active')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => "Produits de la catégorie: {$category->name}",
            'data' => [
                'category' => $category,
                'products' => $products
            ],
            'currency' => 'FCFA'
        ]);
    }

    /**
     * Produits en vedette/populaires
     */
    public function featured()
    {
        $products = Product::with(['category', 'producer'])
            ->where('status', 'active')
            ->where('is_featured', true)
            ->orWhere('views', '>', 100)
            ->orderBy('views', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Produits en vedette',
            'data' => $products,
            'currency' => 'FCFA'
        ]);
    }

    /**
     * Produits du jour/frais
     */
    public function fresh()
    {
        $products = Product::with(['category', 'producer'])
            ->where('status', 'active')
            ->whereDate('harvest_date', '>=', now()->subDays(3))
            ->orderBy('harvest_date', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Produits frais du jour',
            'data' => $products,
            'currency' => 'FCFA'
        ]);
    }
}