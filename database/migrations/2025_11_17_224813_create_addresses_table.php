


<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('mongodb')->create('addresses', function ($collection) {
            $collection->string('user_id');
            $collection->string('address');
            $collection->string('city');
            $collection->string('country')->default('Gabon');
            $collection->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->dropIfExists('cache');
    }
};



