<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('firmware', function (Blueprint $table) {
            $table->string('model')->default('trmnl')->after('version_tag');
            $table->unique(['version_tag', 'model']);
        });
    }

    public function down(): void
    {
        Schema::table('firmware', function (Blueprint $table) {
            $table->dropUnique(['version_tag', 'model']);
            $table->dropColumn('model');
        });
    }
};
