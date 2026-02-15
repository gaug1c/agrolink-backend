<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class AuthController extends Controller
{
    /**
     * REGISTER – Inscription producteur / consommateur
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
        // Champs communs
        'userType'    => 'required|in:producteur,acheteur,consumer,producer',
        'first_name'  => 'required|string|max:255',
        'last_name'   => 'required|string|max:255',
        'email'       => 'required|string|email|max:255',
        'password'    => 'required|string|min:8|confirmed',
        'phone'       => 'required|string',
        'city'        => 'required|string|max:255',
        'acceptTerms' => 'required|accepted',

        // Producteur
        'nomStructure'      => 'required_if:userType,producteur,producer|string|max:255',
        'typesProduction'   => 'required_if:userType,producteur,producer|array',
        'province'          => 'required_if:userType,producteur,producer|string|max:255',
        'cityProduction'    => 'required_if:userType,producteur,producer|string|max:255',
        'villageProduction' => 'nullable|string|max:255',

        // ❌ PLUS OBLIGATOIRES
        'surfaceCultivee'    => 'nullable|numeric',
        'uniteSurface'       => 'nullable|string|max:50',
        'quantiteDisponible' => 'nullable|string|max:255',

        'isWhatsApp'    => 'nullable|boolean',
        'pieceIdentite' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',

        // Consommateur
        'address'     => 'nullable|string|max:255',
        'postal_code' => 'nullable|string|max:20',
        ], [
            'acceptTerms.accepted' => 'Vous devez accepter les conditions d’utilisation',
        ]);


        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        // Vérification email unique (MongoDB safe)
        if (User::where('email', $request->email)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cet email est déjà utilisé'
            ], 422);
        }

        // Mapper rôle
        $role = $this->mapUserTypeToRole($request->userType);

        // Données communes
        $userData = [
            'first_name'  => $request->first_name,
            'last_name'   => $request->last_name,
            'email'       => $request->email,
            'password'    => Hash::make($request->password),
            'phone'       => $request->phone,
            'role'        => $role,
            'status'      => 'active',
            'is_verified' => false,
            'city'        => $request->city,
            'country'     => 'Gabon',
            'address'     => $request->address,
            'postal_code' => $request->postal_code,
        ];

        // Données producteur
        if ($role === 'producer') {
            $userData = array_merge($userData, [
                'business_name'      => $request->nomStructure,
                'province'           => $request->province,
                'production_city'    => $request->cityProduction,
                'production_village' => $request->villageProduction,
                'production_types'   => $request->typesProduction,
                'cultivated_area'    => (string) $request->surfaceCultivee,
                'area_unit'          => $request->uniteSurface,
                'available_quantity' => $request->quantiteDisponible,
                'is_whatsapp'        => $request->isWhatsApp ?? false,
            ]);

            if ($request->hasFile('pieceIdentite')) {
                $userData['identity_document'] =
                    $request->file('pieceIdentite')->store('identity_documents', 'public');
            }
        }

        // Création utilisateur MongoDB
        $user = User::create($userData);

        // JWT Token
        $token = JWTAuth::fromUser($user);

        return response()->json([
            'success' => true,
            'message' => 'Inscription réussie',
            'data' => [
                'user'       => $user,
                'token'      => $token,
                'token_type' => 'Bearer',
            ]
        ], 201);
    }

    /**
     * LOGIN
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
                'message' => 'Erreur lors de la création du token'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie',
            'data' => [
                'token'      => $token,
                'token_type' => 'Bearer',
                'user'       => auth()->user()
            ]
        ]);
    }

    /**
     * UTILISATEUR CONNECTÉ
     */
    public function user()
    {
        return response()->json([
            'success' => true,
            'data'    => auth()->user()
        ]);
    }

    /**
     * LOGOUT (JWT stateless)
     */
    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion réussie'
            ]);
        } catch (TokenExpiredException|TokenInvalidException|JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token invalide ou expiré'
            ], 401);
        }
    }

    /**
     * Mapper userType → role
     */
    private function mapUserTypeToRole(string $userType): string
    {
        return match ($userType) {
            'producteur', 'producer' => 'producer',
            default                  => 'consumer',
        };
    }
}
