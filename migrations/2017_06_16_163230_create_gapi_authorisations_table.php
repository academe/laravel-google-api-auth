<?php

use Illuminate\Database\Migrations\Migration;
use Academe\GoogleApi\Models\Authorisation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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

            // The name of this authorisation instance, to distinguish
            // between authorisations for a laravel user.
            $table->string('name', 100)->default(Authorisation::DEFAULT_NAME);

            // Names are unique for each laravel user.
            $table->unique(['user_id', 'name']);

            // States:
            // + "auth" - user has been sent to Google to authorise
            // + "active" - account authorised
            // + "inactive" - account authorisation withdrawn
            $table->enum('state', ['auth', 'active', 'inactive'])->default('auth');

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

            // The scope of the current authorisation.
            // This will be a JSON-encoded array.
            $table->text('scope')->nullable();

            // The unique Google user ID, so we can recognise multiple authorisations
            // against the same user (effectively authorisation records that can be merged).
            $table->string('google_user_id', 191)->nullable()->index();

            $table->string('google_email', 250)->nullable();

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
