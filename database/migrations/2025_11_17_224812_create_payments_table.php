<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('mongodb')->create('payments', function ($collection) {
            $collection->string('order_id');
            $collection->double('amount');
            $collection->string('method');
            $collection->string('status')->default('pending');
            $collection->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->dropIfExists('cache');
    }
};





