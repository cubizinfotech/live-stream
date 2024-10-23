<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRtmpBlockedsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rtmp_blockeds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rtmp_id');
            $table->dateTime('blocked_datetime');
            $table->tinyInteger('status')->dafault(1)->comment('1->Active, 0->Inactive');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rtmp_blockeds');
    }
}