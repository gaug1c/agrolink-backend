<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    /**
     * Inscription d'un utilisateur (consumer ou producer)
     */
    public function register(Request $request)
    {
        $userType = $request->input('userType', 'consumer');

        $rules = [
            'userType' => 'required|in:consumer,producer',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
            'phone' => 'nullable|string',
        ];

        if ($userType === 'consumer') {
            $rules = array_merge($rules, [
                'first_name' => 'required|string|max:255',
                'last_name'  => 'required|string|max:255',
                'city'      => 'required|string|max:255',
            ]);
        }

        if ($userType === 'producer') {
            $rules = array_merge($rules, [
                'responsibleFirstName' => 'nullable|string|max:255',
                'responsibleLastName'  => 'required|string|max:255',
                'province'             => 'required|string|max:255',
                'productionTypes'      => 'required',
                'producerPhone'        => 'required|string',
                'identityDocument'     => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            ]);
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $firstName = $userType === 'consumer' ? $request->first_name : ($request->responsibleFirstName ?? '');
        $lastName  = $userType === 'consumer' ? $request->last_name  : $request->responsibleLastName;

        $identityPath = null;
        if ($request->hasFile('identityDocument')) {
            $identityPath = $request->file('identityDocument')->store('identity_documents', 'public');
        }

        $user = User::create([
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'email'      => $request->email,
            'password'   => Hash::make($request->password),
            'phone'      => $request->phone ?? $request->producerPhone,
            'role'       => $userType,
            'status'     => 'active',
            'country'    => 'Gabon',

            // Producer specific
            'company_name'      => $request->structureName,
            'province'          => $request->province,
            'production_city'   => $request->productionCity,
            'production_village'=> $request->productionVillage,
            'production_types'  => $request->productionTypes,
            'identity_document' => $identityPath,
        ]);

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'success' => true,
            'message' => 'Inscription réussie',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ], 201);
    }

    /**
     * Connexion d'un utilisateur
     */
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        try {
            if (!$token = JWTAuth::attempt($credentials)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Identifiants invalides'
                ], 401);
            }
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de créer le token'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => auth()->user()
            ]
        ]);
    }

    /**
     * Récupérer l'utilisateur connecté
     */
    public function user(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => auth()->user()
        ]);
    }

    /**
     * Déconnexion (révoque le token)
     */
    public function logout(Request $request)
    {
        try {
            $token = JWTAuth::getToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token non fourni'
                ], 400);
            }

            // Vérifier si le token est valide (stateless)
            JWTAuth::parseToken()->check(); // renverra une exception si invalide ou expiré

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion réussie (stateless)'
            ]);
        } catch (TokenExpiredException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token expiré'
            ], 401);
        } catch (TokenInvalidException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token invalide'
            ], 401);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Problème avec le token'
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur interne',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
