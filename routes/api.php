<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\CartController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\ForgotPasswordController;
use App\Http\Controllers\API\ResetPasswordController;

/*
|--------------------------------------------------------------------------
| API Routes - Agrolink Gabon
|--------------------------------------------------------------------------
*/

// ============================================
// Routes publiques (sans authentification)
// ============================================

Route::get('/test', function () {
    \Log::info('Route /api/test appelée'); // devrait logguer
    return response()->json(['message' => 'Test OK']);
});

Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLink']);
Route::post('/reset-password', [ResetPasswordController::class, 'reset']);



// Authentication
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);//okay
    Route::post('/login', [AuthController::class, 'login']);//okay
});

// Catégories
Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);//okay
    Route::get('/tree', [CategoryController::class, 'tree']);//okay
    Route::get('/popular', [CategoryController::class, 'popular']);//okay -- à tester enocre avce des produits déjà dedans
    Route::get('/search', [CategoryController::class, 'search']);//okay
    Route::get('/{id}', [CategoryController::class, 'show']);//okay
});

// Produits
Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);//okay
    Route::get('/featured', [ProductController::class, 'featured']);//okay 
    Route::get('/fresh', [ProductController::class, 'fresh']);//okay
    Route::get('/category/{categoryId}', [ProductController::class, 'byCategory']);//okay
    Route::get('/{id}', [ProductController::class, 'show']);//okay
});

// ============================================
// Routes protégées (authentification requise)
// ============================================

Route::middleware('jwt.auth')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);//okay
        Route::get('/user', [AuthController::class, 'user']);//okay
    });

    // Panier
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index']);//okay
        Route::post('/add', [CartController::class, 'add']);//okay
        Route::put('/items/{itemId}', [CartController::class, 'update']);//okay
        Route::delete('/items/{itemId}', [CartController::class, 'remove']);//okay
        Route::delete('/clear', [CartController::class, 'clear']);//okay
        Route::get('/count', [CartController::class, 'count']);//okay
        Route::get('/check-availability', [CartController::class, 'checkAvailability']);//okay
    });

    // Commandes
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);//okay
        Route::post('/', [OrderController::class, 'store']);//okay
        Route::get('/{id}', [OrderController::class, 'show']);//okay
        Route::post('/{id}/cancel', [OrderController::class, 'cancel']);//okay
        Route::get('/track/{orderNumber}', [OrderController::class, 'track']);//okay
        Route::post('/{id}/confirm-delivery', [OrderController::class, 'confirmDelivery']);//okay
    });

    // routes/api.php
    Route::prefix('producers')->group(function () {
        Route::get('/', [ProducerController::class, 'index']);
        Route::get('/{id}', [ProducerController::class, 'show']);
        Route::get('/{id}/products', [ProducerController::class, 'products']);
        Route::get('/{id}/stats', [ProducerController::class, 'stats']);
        Route::get('/search', [ProducerController::class, 'search']);
    });

    // Produits (pour producteurs)
    Route::prefix('products')->group(function () {
        Route::post('/', [ProductController::class, 'store']);//okay
        Route::put('/{id}', [ProductController::class, 'update']);//okay
        Route::delete('/{id}', [ProductController::class, 'destroy']);//okay
    });

    // Catégories (Admin uniquement)
    Route::middleware('admin')->prefix('categories')->group(function () {
        Route::post('/', [CategoryController::class, 'store']);//okay
        Route::put('/{id}', [CategoryController::class, 'update']);//okay
        Route::delete('/{id}', [CategoryController::class, 'destroy']);//okay
        Route::patch('/admin/products/{id}/status', [ProductController::class, 'updateStatus']);
    });
});

