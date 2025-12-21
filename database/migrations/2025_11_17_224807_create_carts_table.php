<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('mongodb')->create('carts', function ($collection) {
            $collection->string('user_id');
            $collection->timestamps();
        });

    }

    public function down(): void
    {
        Schema::connection('mongodb')->dropIfExists('cache');
    }
};









