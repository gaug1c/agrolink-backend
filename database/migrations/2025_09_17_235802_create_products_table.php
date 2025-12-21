<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('mongodb')->create('products', function ($collection) {
            $collection->string('name');
            $collection->mediumText('description')->nullable();
            $collection->double('price');
            $collection->integer('stock')->default(0);
            $collection->string('category_id');
            $collection->string('user_id'); // producteur
            $collection->string('image')->nullable();
            $collection->boolean('is_active')->default(true);
            $collection->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->dropIfExists('jobs');
    }
};

