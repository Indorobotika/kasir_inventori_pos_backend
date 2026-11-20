<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderCancellationReportsTable extends Migration
{
    public function up()
    {
        Schema::create('order_cancellation_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('invoice_number')->index();
            $table->decimal('nominal', 15, 2)->default(0);
            $table->text('berita_acara');
            $table->unsignedBigInteger('canceled_by')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('order_cancellation_reports');
    }
}