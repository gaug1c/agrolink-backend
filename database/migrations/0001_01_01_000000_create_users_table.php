<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mongodb')->create('users', function ($collection) {

            /* ------------------------------
             | Identité
             |------------------------------*/
            $collection->string('first_name')->nullable();
            $collection->string('last_name');
            $collection->string('email')->unique();
            $collection->string('password');
            $collection->string('phone')->nullable();
            $collection->string('avatar')->nullable();

            /* ------------------------------
             | Rôle & statut
             |------------------------------*/
            $collection->string('role')->default('consumer'); // consumer | producer | admin
            $collection->string('status')->default('active'); // active | suspended
            $collection->boolean('is_verified')->default(false);

            /* ------------------------------
             | Localisation (consumer)
             |------------------------------*/
            $collection->string('address')->nullable();
            $collection->string('city')->nullable();
            $collection->string('postal_code')->nullable();
            $collection->string('country')->default('Gabon');
            $collection->string('region')->nullable();

            /* ------------------------------
             | Producteur
             |------------------------------*/
            $collection->string('business_name')->nullable(); // Nom de la structure
            $collection->string('province')->nullable();
            $collection->string('production_city')->nullable(); // Ville de production
            $collection->string('production_village')->nullable(); // Village de production
            
            // Types de production (array)
            $collection->json('production_types')->nullable();
            $collection->string('other_production')->nullable(); // Autre type de production
            
            // Capacité de production
            $collection->string('cultivated_area')->nullable(); // Surface cultivée
            $collection->string('area_unit')->nullable(); // Unité (hectare, mètre carré)
            $collection->string('available_quantity')->nullable(); // Quantité disponible
            
            // Contact producteur
            $collection->boolean('is_whatsapp')->default(false);
            $collection->string('delivery_available')->nullable(); // oui | non
            
            // Documents
            $collection->string('identity_document')->nullable(); // Chemin du fichier
            $collection->string('mobile_money_number')->nullable();
            $collection->string('bank_account')->nullable();
            $collection->string('tax_id')->nullable();

            /* ------------------------------
             | Métadonnées
             |------------------------------*/
            $collection->timestamp('email_verified_at')->nullable();
            $collection->timestamp('phone_verified_at')->nullable();
            $collection->timestamp('last_login_at')->nullable();

            $collection->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->dropIfExists('users');
    }
};