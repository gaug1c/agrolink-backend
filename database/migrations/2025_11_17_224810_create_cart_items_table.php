<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('mongodb')->create('cart_items', function ($collection) {
            $collection->string('cart_id');
            $collection->string('product_id');
            $collection->integer('quantity')->default(1);
            $collection->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->dropIfExists('cart_items');
    }
};
