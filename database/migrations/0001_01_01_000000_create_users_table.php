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
            $collection->string('first_name');
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
            $collection->string('business_name')->nullable();
            $collection->string('province')->nullable();
            $collection->string('production_city')->nullable();
            $collection->string('production_village')->nullable();

            // Tableau (sera casté en array dans le model)
            $collection->json('production_types')->nullable();

            $collection->string('identity_document')->nullable(); // chemin du fichier
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
