<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('mongodb')->create('orders', function ($collection) {
            $collection->string('user_id');
            $collection->string('status')->default('pending');
            $collection->double('total');
            $collection->string('payment_method')->nullable();
            $collection->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->dropIfExists('jobs');
    }
};
