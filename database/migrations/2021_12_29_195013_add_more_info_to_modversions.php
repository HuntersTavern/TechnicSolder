<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMoreInfoToModversions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('modversions', function (Blueprint $table) {
            $table->string('loader')->after('mcversion')->default('forge');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('modversions', function (Blueprint $table) {
            $table->dropColumn('loader');
        });
    }
}
