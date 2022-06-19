<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOlxAdsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('olx_ads', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('query');
            $table->string('url');
            $table->string('title');
            $table->float('price');
            $table->string('image');
            $table->timestamp('creation_date');
            $table->string('location');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('olx_ads');
    }
}
