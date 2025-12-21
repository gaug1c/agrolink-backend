<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    /**
     * Afficher le panier de l'utilisateur
     */
    public function index(Request $request)
    {
        $cart = Cart::with(['items.product.category', 'items.product.producer'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$cart) {
            return response()->json([
                'success' => true,
                'message' => 'Panier vide',
                'data' => [
                    'items' => [],
                    'total_items' => 0,
                    'subtotal' => 0
                ],
                'currency' => 'FCFA'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Contenu du panier',
            'data' => [
                'items' => $cart->items,
                'total_items' => $cart->total_items,
                'subtotal' => $cart->subtotal
            ],
            'currency' => 'FCFA'
        ]);
    }

    /**
     * Ajouter un produit au panier
     */
    public function add(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1'
        ], [
            'product_id.required' => 'Le produit est requis',
            'product_id.exists' => 'Ce produit n\'existe pas',
            'quantity.required' => 'La quantité est requise',
            'quantity.min' => 'La quantité minimum est 1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $product = Product::find($request->product_id);

        // Vérifier si le produit est disponible
        if ($product->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Ce produit n\'est plus disponible'
            ], 422);
        }

        // Vérifier le stock
        if ($product->stock < $request->quantity) {
            return response()->json([
                'success' => false,
                'message' => "Stock insuffisant. Stock disponible: {$product->stock}"
            ], 422);
        }

        // Vérifier la quantité minimum de commande
        if ($product->min_order_quantity && $request->quantity < $product->min_order_quantity) {
            return response()->json([
                'success' => false,
                'message' => "La quantité minimum de commande est {$product->min_order_quantity} {$product->unit}"
            ], 422);
        }

        try {
            // Créer ou récupérer le panier
            $cart = Cart::firstOrCreate([
                'user_id' => $request->user()->id
            ]);

            // Ajouter ou mettre à jour l'item
            $cartItem = CartItem::where('cart_id', $cart->id)
                ->where('product_id', $request->product_id)
                ->first();

            if ($cartItem) {
                $newQuantity = $cartItem->quantity + $request->quantity;
                
                // Vérifier le stock pour la nouvelle quantité
                if ($product->stock < $newQuantity) {
                    return response()->json([
                        'success' => false,
                        'message' => "Vous avez déjà {$cartItem->quantity} unité(s) dans le panier. Stock maximum disponible: {$product->stock}"
                    ], 422);
                }

                $cartItem->quantity = $newQuantity;
                $cartItem->save();
                $message = 'Quantité mise à jour dans le panier';
            } else {
                $cartItem = CartItem::create([
                    'cart_id' => $cart->id,
                    'product_id' => $request->product_id,
                    'quantity' => $request->quantity
                ]);
                $message = 'Produit ajouté au panier';
            }

            $cart->load(['items.product']);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'cart' => $cart,
                    'total_items' => $cart->total_items,
                    'subtotal' => $cart->subtotal
                ],
                'currency' => 'FCFA'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'ajout au panier',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour la quantité d'un produit dans le panier
     */
    public function update(Request $request, $itemId)
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1'
        ], [
            'quantity.required' => 'La quantité est requise',
            'quantity.min' => 'La quantité minimum est 1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $cartItem = CartItem::whereHas('cart', function($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        })->find($itemId);

        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Article non trouvé dans le panier'
            ], 404);
        }

        $product = $cartItem->product;

        // Vérifier le stock
        if ($product->stock < $request->quantity) {
            return response()->json([
                'success' => false,
                'message' => "Stock insuffisant. Stock disponible: {$product->stock}"
            ], 422);
        }

        // Vérifier la quantité minimum
        if ($product->min_order_quantity && $request->quantity < $product->min_order_quantity) {
            return response()->json([
                'success' => false,
                'message' => "La quantité minimum de commande est {$product->min_order_quantity} {$product->unit}"
            ], 422);
        }

        $cartItem->quantity = $request->quantity;
        $cartItem->save();

        $cart = $cartItem->cart;
        $cart->load(['items.product']);

        return response()->json([
            'success' => true,
            'message' => 'Quantité mise à jour',
            'data' => [
                'cart' => $cart,
                'total_items' => $cart->total_items,
                'subtotal' => $cart->subtotal
            ],
            'currency' => 'FCFA'
        ]);
    }

    /**
     * Supprimer un produit du panier
     */
    public function remove(Request $request, $itemId)
    {
        $cartItem = CartItem::whereHas('cart', function($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        })->find($itemId);

        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Article non trouvé dans le panier'
            ], 404);
        }

        $cart = $cartItem->cart;
        $cartItem->delete();

        $cart->load(['items.product']);

        return response()->json([
            'success' => true,
            'message' => 'Produit retiré du panier',
            'data' => [
                'cart' => $cart,
                'total_items' => $cart->total_items,
                'subtotal' => $cart->subtotal
            ],
            'currency' => 'FCFA'
        ]);
    }

    /**
     * Vider le panier
     */
    public function clear(Request $request)
    {
        $cart = Cart::where('user_id', $request->user()->id)->first();

        if (!$cart) {
            return response()->json([
                'success' => true,
                'message' => 'Le panier est déjà vide'
            ]);
        }

        $cart->items()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Panier vidé avec succès',
            'data' => [
                'items' => [],
                'total_items' => 0,
                'subtotal' => 0
            ],
            'currency' => 'FCFA'
        ]);
    }

    /**
     * Nombre d'articles dans le panier (badge)
     */
    public function count(Request $request)
    {
        $cart = Cart::where('user_id', $request->user()->id)->first();

        $count = $cart ? $cart->total_items : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'count' => $count
            ]
        ]);
    }

    /**
     * Vérifier la disponibilité des produits du panier avant commande
     */
    public function checkAvailability(Request $request)
    {
        $cart = Cart::with('items.product')->where('user_id', $request->user()->id)->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Votre panier est vide'
            ], 422);
        }

        $unavailableItems = [];
        $warnings = [];

        foreach ($cart->items as $item) {
            $product = $item->product;

            // Produit inactif
            if ($product->status !== 'active') {
                $unavailableItems[] = [
                    'item_id' => $item->id,
                    'product_name' => $product->name,
                    'reason' => 'Produit non disponible'
                ];
                continue;
            }

            // Stock insuffisant
            if ($product->stock < $item->quantity) {
                $unavailableItems[] = [
                    'item_id' => $item->id,
                    'product_name' => $product->name,
                    'requested' => $item->quantity,
                    'available' => $product->stock,
                    'reason' => 'Stock insuffisant'
                ];
                continue;
            }

            // Avertissement produit bientôt périmé
            if ($product->expiry_date && $product->expiry_date->diffInDays(now()) <= 2) {
                $warnings[] = [
                    'product_name' => $product->name,
                    'message' => "Ce produit expire le {$product->expiry_date->format('d/m/Y')}"
                ];
            }
        }

        if (!empty($unavailableItems)) {
            return response()->json([
                'success' => false,
                'message' => 'Certains produits ne sont plus disponibles',
                'data' => [
                    'unavailable_items' => $unavailableItems,
                    'warnings' => $warnings
                ]
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tous les produits sont disponibles',
            'data' => [
                'warnings' => $warnings,
                'cart' => $cart,
                'total_items' => $cart->total_items,
                'subtotal' => $cart->subtotal
            ],
            'currency' => 'FCFA'
        ]);
    }
}