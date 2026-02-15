<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use MongoDB\BSON\ObjectId;

class OrderController extends Controller
{
    // Afficher l'historique des commandes
    public function index(Request $request)
    {
        $query = Order::with('items.product')
            ->where('user_id', $request->user()->_id);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->to_date);
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'Historique des commandes',
            'data' => $orders,
            'currency' => 'FCFA'
        ]);
    }

    // Afficher les détails d'une commande
    public function show(Request $request, $id)
    {
        try {
            $order = Order::with('items.product.category', 'payment')
                ->where('user_id', $request->user()->_id)
                ->find(new ObjectId($id));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ID de commande invalide'
            ], 400);
        }

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Commande non trouvée'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Détails de la commande',
            'data' => $order,
            'currency' => 'FCFA'
        ]);
    }

    // Créer une nouvelle commande à partir du panier
    // Version DÉVELOPPEMENT sans transactions MongoDB
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shipping_address' => 'required|string|max:255',
            'shipping_city' => 'required|string|in:Libreville,Port-Gentil,Franceville,Oyem,Moanda,Mouila,Lambaréné,Tchibanga,Koulamoutou,Makokou',
            'shipping_postal_code' => 'nullable|string|max:10',
            'shipping_country' => 'required|string|max:100',
            'phone' => [
                'required',
                'string',
                'regex:#^(\+241|00241)?[0-9]{8,9}$#'
            ],
            'delivery_instructions' => 'nullable|string|max:500',
            'payment_method' => 'required|in:card,mobile_money,bank_transfer,cash_on_delivery'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Récupérer le panier avec les produits
        $cart = Cart::with('items.product')
            ->where('user_id', $request->user()->_id)
            ->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Votre panier est vide'
            ], 422);
        }

        // Vérifier la disponibilité du stock pour tous les produits
        $stockErrors = [];
        foreach ($cart->items as $item) {
            if (!$item->product) {
                $stockErrors[] = "Produit introuvable dans le panier";
                continue;
            }
            
            if ($item->product->stock < $item->quantity) {
                $stockErrors[] = "{$item->product->name}: stock insuffisant (disponible: {$item->product->stock}, demandé: {$item->quantity})";
            }
        }

        if (!empty($stockErrors)) {
            return response()->json([
                'success' => false,
                'message' => 'Stock insuffisant pour certains produits',
                'errors' => $stockErrors
            ], 422);
        }

        try {
            // Calculer les montants - CONVERSION DECIMAL128 EN FLOAT
            $subtotal = 0;
            $productShippingCost = 0;

            foreach ($cart->items as $item) {
                // Convertir Decimal128 en float si nécessaire
                $price = $item->product->discount_price ?? $item->product->price;
                $price = $this->toFloat($price);
                
                $quantity = (int) $item->quantity;
                $subtotal += $quantity * $price;

                // Frais de livraison spécifiques au produit
                if ($item->product->shipping_cost) {
                    $shippingCost = $this->toFloat($item->product->shipping_cost);
                    $productShippingCost += $shippingCost;
                }
            }

            // Frais de livraison par ville
            $cityShippingCosts = [
                'Libreville' => 2000,
                'Port-Gentil' => 5000,
                'Franceville' => 7000,
                'Oyem' => 6000,
                'Moanda' => 7000,
                'Mouila' => 5000,
                'Lambaréné' => 4000,
                'Tchibanga' => 6000,
                'Koulamoutou' => 6000,
                'Makokou' => 7000
            ];

            $baseShippingCost = $cityShippingCosts[$request->shipping_city] ?? 5000;
            $totalShippingCost = $productShippingCost + $baseShippingCost;
            $totalAmount = $subtotal + $totalShippingCost;

            // Arrondir à 2 décimales
            $subtotal = round($subtotal, 2);
            $totalShippingCost = round($totalShippingCost, 2);
            $totalAmount = round($totalAmount, 2);

            // Créer la commande
            $order = Order::create([
                'user_id' => $request->user()->_id,
                'order_number' => 'CMD-' . strtoupper(uniqid()),
                'subtotal' => $subtotal,
                'shipping_cost' => $totalShippingCost,
                'total_amount' => $totalAmount,
                'status' => 'pending',
                'payment_status' => 'pending',
                'payment_method' => $request->payment_method,
                'shipping_address' => $request->shipping_address,
                'shipping_city' => $request->shipping_city,
                'shipping_postal_code' => $request->shipping_postal_code,
                'shipping_country' => $request->shipping_country ?? 'Gabon',
                'phone' => $request->phone,
                'delivery_instructions' => $request->delivery_instructions,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Créer les items de commande et mettre à jour le stock
            $orderItems = [];
            foreach ($cart->items as $item) {
                $price = $item->product->discount_price ?? $item->product->price;
                $price = $this->toFloat($price);
                $quantity = (int) $item->quantity;

                // Vérifier à nouveau le stock (protection contre les conditions de course)
                if ($item->product->stock < $quantity) {
                    // Annuler la commande si stock insuffisant
                    $order->delete();
                    throw new \Exception("Stock insuffisant pour {$item->product->name}");
                }

                $subtotalItem = round($quantity * $price, 2);

                $orderItem = OrderItem::create([
                    'order_id' => $order->_id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'product_sku' => $item->product->sku ?? null,
                    'product_image' => $item->product->image ?? null,
                    'quantity' => $quantity,
                    'price' => $price,
                    'subtotal' => $subtotalItem,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                $orderItems[] = $orderItem;

                // Décrémenter le stock de manière atomique
                $item->product->decrement('stock', $quantity);
                
                // Incrémenter le nombre de ventes
                $item->product->increment('sales_count', $quantity);
            }

            // Vider le panier
            $cart->items()->delete();

            // Marquer le panier comme converti
            $cart->update([
                'converted_to_order_id' => $order->_id,
                'converted_at' => now()
            ]);

            // Charger les relations pour la réponse
            $order->load('items.product', 'user');

            // Déclencher les événements (optionnel en dev)
            // event(new \App\Events\OrderCreated($order));

            // Envoyer les notifications (désactivé en dev)
            if (config('app.env') === 'production') {
                try {
                    \Mail::to($request->user()->email)->queue(
                        new \App\Mail\OrderConfirmation($order)
                    );

                    \Notification::send(
                        \App\Models\User::where('role', 'admin')->get(),
                        new \App\Notifications\NewOrderNotification($order)
                    );
                } catch (\Exception $e) {
                    \Log::warning('Erreur envoi notification commande', [
                        'order_id' => $order->_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Commande créée avec succès',
                'data' => [
                    'order' => $order,
                    'items_count' => count($orderItems),
                    'currency' => 'FCFA',
                    'estimated_delivery' => $this->calculateEstimatedDelivery($request->shipping_city)
                ],
                'next_step' => $request->payment_method === 'cash_on_delivery' 
                    ? 'Commande confirmée - Paiement à la livraison' 
                    : 'Procéder au paiement',
                'payment_url' => $request->payment_method !== 'cash_on_delivery'
                    ? route('payment.process', ['order' => $order->_id])
                    : null
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Erreur création commande', [
                'user_id' => $request->user()->_id,
                'cart_id' => $cart->_id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la commande',
                'error' => config('app.debug') ? $e->getMessage() : 'Une erreur est survenue. Veuillez réessayer.'
            ], 500);
        }
    }

    // Annuler une commande
    public function cancel(Request $request, $id)
    {
        try {
            $order = Order::where('user_id', $request->user()->_id)
                ->find(new ObjectId($id));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ID de commande invalide'
            ], 400);
        }

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Commande non trouvée'
            ], 404);
        }

        if (!in_array($order->status, ['pending', 'processing', 'confirmed'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cette commande ne peut plus être annulée'
            ], 422);
        }

        try {
            // Restaurer le stock
            foreach ($order->items as $item) {
                if ($item->product) {
                    $item->product->increment('stock', $item->quantity);
                    $item->product->decrement('sales_count', $item->quantity);
                }
            }

            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $request->reason ?? 'Annulé par le client'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Commande annulée avec succès',
                'data' => $order
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur annulation commande', [
                'order_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'annulation de la commande',
                'error' => config('app.debug') ? $e->getMessage() : 'Une erreur est survenue'
            ], 500);
        }
    }

    // Confirmer la livraison
    public function confirmDelivery(Request $request, $id)
    {
        try {
            $order = Order::where('user_id', $request->user()->_id)
                ->find(new ObjectId($id));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ID de commande invalide'
            ], 400);
        }

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Commande non trouvée'
            ], 404);
        }

        if ($order->status !== 'shipped') {
            return response()->json([
                'success' => false,
                'message' => 'La commande n\'a pas encore été expédiée'
            ], 422);
        }

        $order->update([
            'status' => 'delivered',
            'delivered_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Livraison confirmée',
            'data' => $order
        ]);
    }

    // Suivre une commande
    public function track(Request $request, $orderNumber)
    {
        $order = Order::with('items.product')
            ->where('user_id', $request->user()->_id)
            ->where('order_number', $orderNumber)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Commande non trouvée'
            ], 404);
        }

        $timeline = [
            [
                'status' => 'pending',
                'label' => 'Commande reçue',
                'completed' => true,
                'date' => $order->created_at
            ],
            [
                'status' => 'confirmed',
                'label' => 'Commande confirmée',
                'completed' => in_array($order->status, ['confirmed', 'processing', 'shipped', 'delivered']),
                'date' => $order->confirmed_at
            ],
            [
                'status' => 'processing',
                'label' => 'En préparation',
                'completed' => in_array($order->status, ['processing', 'shipped', 'delivered']),
                'date' => $order->processing_at
            ],
            [
                'status' => 'shipped',
                'label' => 'Expédiée',
                'completed' => in_array($order->status, ['shipped', 'delivered']),
                'date' => $order->shipped_at
            ],
            [
                'status' => 'delivered',
                'label' => 'Livrée',
                'completed' => $order->status === 'delivered',
                'date' => $order->delivered_at
            ]
        ];

        return response()->json([
            'success' => true,
            'message' => 'Suivi de commande',
            'data' => [
                'order' => $order,
                'timeline' => $timeline,
                'estimated_delivery' => $order->estimated_delivery_date
            ]
        ]);
    }

    // MÉTHODES PRIVÉES HELPER

    // Convertir Decimal128 ou autres types en float
    private function toFloat($value): float
    {
        if ($value instanceof \MongoDB\BSON\Decimal128) {
            return (float) $value->__toString();
        }
        
        return (float) $value;
    }

    // Calculer la date de livraison estimée
    private function calculateEstimatedDelivery(string $city): string
    {
        $deliveryDays = [
            'Libreville' => 2,
            'Port-Gentil' => 4,
            'Franceville' => 5,
            'Oyem' => 5,
            'Moanda' => 5,
            'Mouila' => 4,
            'Lambaréné' => 3,
            'Tchibanga' => 5,
            'Koulamoutou' => 5,
            'Makokou' => 6
        ];

        $days = $deliveryDays[$city] ?? 5;
        return now()->addDays($days)->format('d/m/Y');
    }
}