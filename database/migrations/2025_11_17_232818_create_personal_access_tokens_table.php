<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('mongodb')->create('personal_access_tokens', function ($collection) {
            $collection->string('tokenable_id');
            $collection->string('tokenable_type');
            $collection->string('name');
            $collection->string('token')->unique();
            $collection->timestamp('last_used_at')->nullable();
            $collection->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->dropIfExists('personal_access_tokens');
    }
};

