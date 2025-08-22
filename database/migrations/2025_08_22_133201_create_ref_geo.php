<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'ref_province',
            function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('name');
                $table->string('code')->unique();

                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->ulid('created_by')->index();
                $table->ulid('updated_by')->index();

                $table->foreign('created_by')->references('id')->on('sys_users');
                $table->foreign('updated_by')->references('id')->on('sys_users');
            }
        );

        Schema::create(
            'ref_city',
            function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->ulid('province_id')->index();
                $table->string('name');
                $table->string('code')->unique();

                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->ulid('created_by')->index();
                $table->ulid('updated_by')->index();

                $table->foreign('province_id')->references('id')->on('ref_province');
                $table->foreign('created_by')->references('id')->on('sys_users');
                $table->foreign('updated_by')->references('id')->on('sys_users');
            }
        );

        Schema::create(
            'ref_district',
            function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->ulid('city_id')->index();
                $table->string('name');
                $table->string('code')->unique();

                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->ulid('created_by')->index();
                $table->ulid('updated_by')->index();

                $table->foreign('city_id')->references('id')->on('ref_city');
                $table->foreign('created_by')->references('id')->on('sys_users');
                $table->foreign('updated_by')->references('id')->on('sys_users');
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
        Schema::drop('ref_district');
        Schema::drop('ref_city');
        Schema::drop('ref_province');
    }
};
