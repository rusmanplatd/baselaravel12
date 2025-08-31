<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'ref_geo_country',
            function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('name');
                $table->string('code')->unique();
                $table->string('iso_code', 3)->unique()->nullable();
                $table->string('phone_code')->nullable();

                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->ulid('created_by')->index();
                $table->ulid('updated_by')->index();

                $table->foreign('created_by')->references('id')->on('sys_users');
                $table->foreign('updated_by')->references('id')->on('sys_users');
            }
        );

        Schema::create(
            'ref_geo_province',
            function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->ulid('country_id')->index();
                $table->string('name');
                $table->string('code');

                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->ulid('created_by')->index();
                $table->ulid('updated_by')->index();

                $table->foreign('country_id')->references('id')->on('ref_geo_country');
                $table->foreign('created_by')->references('id')->on('sys_users');
                $table->foreign('updated_by')->references('id')->on('sys_users');

                $table->unique(['country_id', 'code']);
            }
        );

        Schema::create(
            'ref_geo_city',
            function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->ulid('province_id')->index();
                $table->string('name');
                $table->string('code');

                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->ulid('created_by')->index();
                $table->ulid('updated_by')->index();

                $table->foreign('province_id')->references('id')->on('ref_geo_province');
                $table->foreign('created_by')->references('id')->on('sys_users');
                $table->foreign('updated_by')->references('id')->on('sys_users');

                $table->unique(['province_id', 'code']);
            }
        );

        Schema::create(
            'ref_geo_district',
            function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->ulid('city_id')->index();
                $table->string('name');
                $table->string('code');

                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->ulid('created_by')->index();
                $table->ulid('updated_by')->index();

                $table->foreign('city_id')->references('id')->on('ref_geo_city');
                $table->foreign('created_by')->references('id')->on('sys_users');
                $table->foreign('updated_by')->references('id')->on('sys_users');

                $table->unique(['city_id', 'code']);
            }
        );

        Schema::create(
            'ref_geo_village',
            function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->ulid('district_id')->index();
                $table->string('name');
                $table->string('code');

                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->ulid('created_by')->index();
                $table->ulid('updated_by')->index();

                $table->foreign('district_id')->references('id')->on('ref_geo_district');
                $table->foreign('created_by')->references('id')->on('sys_users');
                $table->foreign('updated_by')->references('id')->on('sys_users');

                $table->unique(['district_id', 'code']);
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('ref_geo_village');
        Schema::drop('ref_geo_district');
        Schema::drop('ref_geo_city');
        Schema::drop('ref_geo_province');
        Schema::drop('ref_geo_country');
    }
};
