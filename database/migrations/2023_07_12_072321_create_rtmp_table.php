<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRtmpTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("CREATE TABLE `rtmps` ( `id` INT NOT NULL AUTO_INCREMENT , `created_by` INT NOT NULL , `name` VARCHAR(191) NOT NULL , `rtmp_url` VARCHAR(191) NOT NULL , `stream_key` VARCHAR(191) NOT NULL , `live_url` VARCHAR(191) NOT NULL , `status` INT NOT NULL , `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP , `updated_at` TIMESTAMP on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP , PRIMARY KEY (`id`)) ENGINE = MyISAM;");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rtmps');
    }
}
