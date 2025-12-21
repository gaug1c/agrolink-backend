<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('mongodb')->create('jobs', function ($collection) {
            $collection->string('queue')->nullable();
            $collection->mediumText('payload');
            $collection->integer('attempts')->default(0);
            $collection->timestamp('reserved_at')->nullable();
            $collection->timestamp('available_at');
            $collection->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->dropIfExists('jobs');
    }
};
