<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGapiAuthorisationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $table_name = config('googleapi.authorisation_table');

        Schema::create($table_name, function (Blueprint $table) {
            //
            $table->increments('id');

            // The creator/owner of the authorisation.
            // We are assuming the user ID is numeric, and not a UUID.
            $table->integer('user_id')->unsigned();
            $table->foreign('user_id')->references('id')->on('users');

            // Access token.
            // We do not know the maximum length of the token, so we won't set one.
            $table->text('access_token')->nullable();

            // Refresh token.
            $table->string('refresh_token', 250)->nullable();

            // The local time the current access_token was created by Google.
            // We will just use an int so there are no problems spanning timezones
            // and implicit conversions when moving into and out of the database.
            $table->integer('created_time')->unsigned()->nullable();

            // The period the access_token will last, in seconds.
            $table->integer('expires_in')->unsigned()->nullable();

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
        $table_name = config('googleapi.authorisation_table');

        Schema::drop($table_name);
    }
}
