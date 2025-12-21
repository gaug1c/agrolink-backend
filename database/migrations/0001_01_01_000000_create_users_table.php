<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mongodb')->create('users', function ($collection) {
            $collection->string('name');
            $collection->string('email');
            $collection->string('password');
            $collection->string('phone')->nullable();
            $collection->string('avatar')->nullable();
            $collection->string('role')->default('consumer'); // enum remplacé par string
            $collection->string('status')->default('active'); // enum remplacé par string
            $collection->string('address')->nullable();
            $collection->string('city')->nullable();
            $collection->string('postal_code')->nullable();
            $collection->string('country')->default('Gabon');
            $collection->string('region')->nullable();
            $collection->string('bio')->nullable(); // text() remplacé par string()
            $collection->string('business_name')->nullable();
            $collection->string('business_registration')->nullable();
            $collection->string('tax_id')->nullable();
            $collection->string('bank_account')->nullable();
            $collection->string('mobile_money_number')->nullable();
            $collection->boolean('is_verified')->default(false);
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

