<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use MongoDB\BSON\ObjectId;

class CartController extends Controller
{
    /**
     * Afficher le panier de l'utilisateur
     */
    public function index(Request $request)
    {
        $cart = Cart::with(['items.product.category', 'items.product.producer'])
            ->where('user_id', $request->user()->_id)
            ->first();

        if (!$cart || $cart->items->isEmpty()) {
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
            'product_id' => 'required',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $product = Product::find($request->product_id);

        if (!$product || $product->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Produit non disponible'
            ], 422);
        }

        if ($product->stock < $request->quantity) {
            return response()->json([
                'success' => false,
                'message' => "Stock insuffisant. Disponible: {$product->stock}"
            ], 422);
        }

        if ($product->min_order_quantity && $request->quantity < $product->min_order_quantity) {
            return response()->json([
                'success' => false,
                'message' => "Quantité minimum: {$product->min_order_quantity} {$product->unit}"
            ], 422);
        }

        $cart = Cart::firstOrCreate([
            'user_id' => $request->user()->_id
        ]);

        $cartItem = CartItem::where('cart_id', $cart->_id)
            ->where('product_id', $request->product_id)
            ->first();

        if ($cartItem) {
            $newQuantity = $cartItem->quantity + $request->quantity;
            if ($product->stock < $newQuantity) {
                return response()->json([
                    'success' => false,
                    'message' => "Vous avez déjà {$cartItem->quantity} unité(s). Stock max: {$product->stock}"
                ], 422);
            }
            $cartItem->quantity = $newQuantity;
            $cartItem->save();
            $message = 'Quantité mise à jour dans le panier';
        } else {
            $cartItem = CartItem::create([
                'cart_id' => $cart->_id,
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
    }

    /**
     * Mettre à jour la quantité d'un produit dans le panier
     */
    public function update(Request $request, $itemId)
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $cart = Cart::where('user_id', $request->user()->_id)->first();

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Panier vide'
            ], 404);
        }

        $cartItem = CartItem::where('_id', new ObjectId($itemId))
            ->where('cart_id', $cart->_id)
            ->first();

        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Article non trouvé dans le panier'
            ], 404);
        }

        $product = $cartItem->product;

        if ($product->stock < $request->quantity) {
            return response()->json([
                'success' => false,
                'message' => "Stock insuffisant. Disponible: {$product->stock}"
            ], 422);
        }

        if ($product->min_order_quantity && $request->quantity < $product->min_order_quantity) {
            return response()->json([
                'success' => false,
                'message' => "Quantité minimum: {$product->min_order_quantity} {$product->unit}"
            ], 422);
        }

        $cartItem->quantity = $request->quantity;
        $cartItem->save();

        $cart = Cart::with(['items.product'])->find($cartItem->cart_id);

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
        // Récupérer le panier de l'utilisateur
        $cart = Cart::where('user_id', $request->user()->_id)->first();

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Panier vide'
            ], 404);
        }

        // Vérifier que l'article appartient bien au panier
        try {
            $cartItem = CartItem::where('_id', new ObjectId($itemId))
                ->where('cart_id', $cart->_id)
                ->first();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ID invalide'
            ], 400);
        }

        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Article non trouvé dans le panier'
            ], 404);
        }

        // Supprimer l'article
        $cartItem->delete();

        // Recharger le panier avec les items
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
        $cart = Cart::where('user_id', $request->user()->_id)->first();

        if ($cart) {
            $cart->items()->delete();
        }

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
     * Nombre d'articles dans le panier
     */
    public function count(Request $request)
    {
        $cart = Cart::where('user_id', $request->user()->_id)->first();
        $count = $cart ? $cart->total_items : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'count' => $count
            ]
        ]);
    }

    public function checkAvailability(Request $request)
    {
        // Récupérer le panier de l'utilisateur
        $cart = Cart::with('items.product')->where('user_id', $request->user()->_id)->first();

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

            if (!$product || $product->status !== 'active') {
                $unavailableItems[] = [
                    'item_id' => $item->_id,
                    'product_name' => $product->name ?? 'Produit supprimé',
                    'reason' => 'Produit non disponible'
                ];
                continue;
            }

            if ($product->stock < $item->quantity) {
                $unavailableItems[] = [
                    'item_id' => $item->_id,
                    'product_name' => $product->name,
                    'requested' => $item->quantity,
                    'available' => $product->stock,
                    'reason' => 'Stock insuffisant'
                ];
                continue;
            }

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
                'cart' => $cart,
                'total_items' => $cart->total_items,
                'subtotal' => $cart->subtotal,
                'warnings' => $warnings
            ],
            'currency' => 'FCFA'
        ]);
    }

}
