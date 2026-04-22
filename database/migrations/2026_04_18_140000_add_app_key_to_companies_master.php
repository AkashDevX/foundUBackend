<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'master';

    public function up(): void
    {
        Schema::connection($this->connection)->table('companies', function (Blueprint $table) {
            $table->string('app_key', 64)->nullable()->unique()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('companies', function (Blueprint $table) {
            $table->dropColumn('app_key');
        });
    }
};
