<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('mongodb')->create('order_items', function ($collection) {
            $collection->string('order_id');
            $collection->string('product_id');
            $collection->integer('quantity');
            $collection->double('price');
            $collection->timestamps();
        });

    }

    public function down(): void
    {
        Schema::connection('mongodb')->dropIfExists('jobs');
    }
};
