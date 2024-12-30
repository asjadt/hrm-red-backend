<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBusinessesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->date("start_date");
            $table->text("about")->nullable();
            $table->string("web_page")->nullable();
            $table->string("phone")->nullable();
            $table->string("email")->nullable()->unique();
            $table->text("additional_information")->nullable();
            $table->string("address_line_1")->nullable();
            $table->string("address_line_2")->nullable();
            $table->string("lat")->nullable();
            $table->string("long")->nullable();
            $table->string("country");
            $table->string("city");
            $table->string("postcode")->nullable();
            $table->string("currency")->nullable();


            $table->string("logo")->nullable();
            $table->string("image")->nullable();
            $table->string("background_image")->nullable();


            $table->unsignedBigInteger("service_plan_id")->nullable();
            $table->foreign('service_plan_id')->references('id')->on('service_plans')->onDelete('restrict');

            $table->string("service_plan_discount_code")->nullable();
            $table->double("service_plan_discount_amount")->nullable();




            $table->boolean('pension_scheme_registered')->default(false);
            $table->string('pension_scheme_name')->nullable();
            $table->json('pension_scheme_letters')->nullable();



            $table->string('status')->default("pending");
            // $table->enum('status', ['status1', 'status2',  'status3'])->default("status1");
            $table->boolean("is_active")->default(false);
            $table->boolean("is_self_registered_businesses")->default(false);



            $table->integer("number_of_employees_allowed")->nullable()->default(0);
            $table->boolean("flexible_rota_enabled")->nullable()->default(false);



            $table->unsignedBigInteger("reseller_id");
            $table->unsignedBigInteger("owner_id");
            $table->unsignedBigInteger("created_by");
            $table->softDeletes();


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
        Schema::dropIfExists('businesses');
    }
}
