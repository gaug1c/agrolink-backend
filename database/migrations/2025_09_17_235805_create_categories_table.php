<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('mongodb')->create('categories', function ($collection) {
            $collection->string('name');
            $collection->string('slug')->unique();
            $collection->string('icon')->nullable();
            $collection->string('description')->nullable();
            $collection->string('parent_id')->nullable(); // pour MongoDB, on stocke l'id du parent en string
            $collection->boolean('is_active')->default(true);
            $collection->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->dropIfExists('categories');
    }
};

