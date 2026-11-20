<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSellableFieldsToRawMaterialsTable extends Migration
{
    public function up()
    {
        Schema::table('raw_materials', function (Blueprint $table) {
            $table->boolean('is_sellable')->default(false)->after('cost_price');
            $table->decimal('sell_price', 15, 2)->default(0)->after('is_sellable');
            $table->unsignedBigInteger('category_id')->nullable()->after('sell_price');
            $table->string('cover')->nullable()->after('category_id');
        });
    }

    public function down()
    {
        Schema::table('raw_materials', function (Blueprint $table) {
            $table->dropColumn(['is_sellable', 'sell_price', 'category_id', 'cover']);
        });
    }
}