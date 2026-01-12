<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function register(Request $request)
{
    // Déterminer le type d'utilisateur
    $userType = $request->input('userType', 'consumer');

    /** -------------------
     *  VALIDATION
     *  -------------------
     */
    $rules = [
        'userType' => 'required|in:consumer,producer',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|min:6|confirmed',
        'phone' => 'nullable|string',
    ];

    // Champs CONSUMER
    if ($userType === 'consumer') {
        $rules = array_merge($rules, [
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'city'      => 'required|string|max:255',
        ]);
    }

    // Champs PRODUCER
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

    /** -------------------
     *  GESTION DU NOM
     *  -------------------
     */
    if ($userType === 'consumer') {
        $firstName = $request->firstName;
        $lastName  = $request->lastName;
    } else {
        $firstName = $request->responsibleFirstName ?? '';
        $lastName  = $request->responsibleLastName;
    }

    /** -------------------
     *  UPLOAD FICHIER
     *  -------------------
     */
    $identityPath = null;
    if ($request->hasFile('identityDocument')) {
        $identityPath = $request->file('identityDocument')
            ->store('identity_documents', 'public');
    }

    /** -------------------
     *  CRÉATION USER
     *  -------------------
     */
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

    /** -------------------
     *  JWT TOKEN
     *  -------------------
     */
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

}