

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('mongodb')->create('order_status_histories', function ($collection) {
            $collection->string('order_id');
            $collection->string('status');
            $collection->timestamp('changed_at');
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->dropIfExists('cache');
    }
};


