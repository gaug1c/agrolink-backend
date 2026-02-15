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
     * Liste des produits (public)
     */
    public function index(Request $request)
    {
        $query = Product::with(['category', 'producer'])
            ->where('status', 'active');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('local_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('region')) {
            $query->where('region', $request->region);
        }

        if ($request->filled('city')) {
            $query->where('city', $request->city);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->boolean('in_stock')) {
            $query->where('stock', '>', 0);
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        if ($request->filled('producer_id')) {
            $query->where('producer_id', $request->producer_id);
        }

        if ($request->boolean('on_sale')) {
            $query->whereNotNull('discount_price');
        }

        // Tri
        match ($request->get('sort_by')) {
            'price_asc'  => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'name'       => $query->orderBy('name', 'asc'),
            'popularity' => $query->orderBy('views', 'desc'),
            default      => $query->orderBy(
                $request->get('sort_by', 'created_at'),
                $request->get('sort_order', 'desc')
            ),
        };

        $products = $query->paginate($request->get('per_page', 15));

        // Ajouter disponibilité calculée
        $products->getCollection()->transform(function ($product) {
            $product->is_available = $product->status === 'active' && $product->stock > 0;
            return $product;
        });

        return response()->json([
            'success' => true,
            'message' => 'Liste des produits',
            'data' => $products,
            'currency' => 'FCFA'
        ]);
    }

    /**
     * Création produit (producteur)
     */
    public function store(Request $request)
    {
        if (!$request->user()->isProducer()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux producteurs'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'local_name' => 'nullable|string|max:255',
            'description' => 'required|string',
            'category_id' => 'required|exists:categories,_id',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0|lt:price',
            'unit' => 'required|in:kg,g,l,ml,piece,bunch,bag,box',
            'stock' => 'required|integer|min:0',
            'min_order_quantity' => 'nullable|integer|min:1',
            'region' => 'required|string',
            'city' => 'required|string',
            'type' => 'required|in:bio,local,conventionnel',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'harvest_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after:harvest_date',
            'shipping_cost' => 'nullable|numeric|min:0',
            'available_for_delivery' => 'boolean',
            'available_for_pickup' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->except('images');
        $data['producer_id'] = $request->user()->id;
        $data['slug'] = Str::slug($request->name);
        $data['status'] = 'active';

        $product = Product::create($data);

        if ($request->hasFile('images')) {
            $paths = [];
            foreach ($request->file('images') as $image) {
                $paths[] = $image->store('products', 'public');
            }
            $product->images = $paths;
            $product->save();
        }

        $product->load('category', 'producer');
        $product->is_available = $product->stock > 0;

        return response()->json([
            'success' => true,
            'message' => 'Produit créé avec succès',
            'data' => $product
        ], 201);
    }

    /**
     * Détails produit
     */
    public function show($id)
    {
        $product = Product::with(['category', 'producer'])
            ->where('status', 'active')
            ->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produit non trouvé'
            ], 404);
        }

        $product->increment('views');
        $product->is_available = $product->stock > 0;

        return response()->json([
            'success' => true,
            'data' => $product,
            'currency' => 'FCFA'
        ]);
    }

    /**
     * Mise à jour produit (producteur)
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

        $product->update($request->except('images'));

        if ($request->hasFile('images')) {
            if (is_array($product->images)) {
                foreach ($product->images as $img) {
                    Storage::disk('public')->delete($img);
                }
            }

            $paths = [];
            foreach ($request->file('images') as $image) {
                $paths[] = $image->store('products', 'public');
            }
            $product->images = $paths;
            $product->save();
        }

        $product->is_available = $product->stock > 0;

        return response()->json([
            'success' => true,
            'message' => 'Produit mis à jour',
            'data' => $product
        ]);
    }

    /**
     * Suppression produit
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

        if (is_array($product->images)) {
            foreach ($product->images as $img) {
                Storage::disk('public')->delete($img);
            }
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Produit supprimé'
        ]);
    }
// Admin - Mise à jour du statut du produit
    public function updateStatus(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produit non trouvé'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:active,inactive,out_of_stock'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $product->status = $request->status;
        $product->save();

        return response()->json([
            'success' => true,
            'message' => 'Statut du produit mis à jour',
            'data' => $product
        ]);
    }
}