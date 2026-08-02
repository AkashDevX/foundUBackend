<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dropdown / modal options for the mobile registration wizard (foundU).
 * Edit rows in DB to change labels without shipping a new APK.
 */
return new class extends Migration
{
    protected $connection = 'master';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('registration_picklist_items')) {
            return;
        }

        Schema::connection($this->connection)->create('registration_picklist_items', function (Blueprint $table) {
            $table->id();
            $table->string('picklist_key', 64)->index();
            $table->string('value', 255);
            $table->string('label', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['picklist_key', 'value']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('registration_picklist_items');
    }
};
