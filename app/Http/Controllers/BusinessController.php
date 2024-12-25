<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuthRegisterBusinessRequest;
use App\Http\Requests\BusinessCreateRequest;
use App\Http\Requests\BusinessTakeOverRequest;
use App\Http\Requests\BusinessUpdatePart1Request;
use App\Http\Requests\BusinessUpdatePart2Request;
use App\Http\Requests\BusinessUpdatePart2RequestV2;
use App\Http\Requests\BusinessUpdatePart3Request;
use App\Http\Requests\BusinessUpdatePensionRequest;
use App\Http\Requests\BusinessUpdateRequest;
use App\Http\Requests\BusinessUpdateRequestPart4;
use App\Http\Requests\BusinessUpdateSeparateRequest;
use App\Http\Requests\CheckScheduleConflictRequest;
use App\Http\Requests\ImageUploadRequest;
use App\Http\Requests\MultipleImageUploadRequest;
use App\Http\Requests\GetIdRequest;
use App\Http\Utils\BasicUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\DiscountUtil;
use App\Http\Utils\EmailLogUtil;
use App\Http\Utils\UserActivityUtil;
use App\Mail\SendPassword;

use App\Models\Business;
use App\Models\BusinessPensionHistory;
use App\Models\BusinessSubscription;
use App\Models\BusinessTime;
use App\Models\ServicePlan;
use App\Models\User;
// use App\Models\WorkShift;
// use App\Models\WorkShiftHistory;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;

class BusinessController extends Controller
{
    use ErrorUtil, BusinessUtil, UserActivityUtil, DiscountUtil, BasicUtil, EmailLogUtil;



    /**
     *
     * @OA\Post(
     *      path="/v1.0/businesses",
     *      operationId="createBusiness",
     *      tags={"business_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to store business",
     *      description="This method is to store  business",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"user","business"},

     *
     *  @OA\Property(property="business", type="string", format="array",example={
     *  "owner_id":"1",
     *  "pension_scheme_registered" : 1,
     *   "pension_scheme_name" : "hh",
     *   "pension_scheme_letters" : {{"file" :"vv.jpg"}},
     * * "start_date":"start_date",
     * "name":"ABCD businesses",
     * "about":"Best businesses in Dhaka",
     * "web_page":"https://www.facebook.com/",
     * "identifier_prefix":"identifier_prefix",
     * "pin_code":"pin_code",
     *
     *  "phone":"01771034383",
     *  "email":"rifatalashwad@gmail.com",
     *  "phone":"01771034383",
     *  "additional_information":"No Additional Information",
     *  "address_line_1":"Dhaka",
     *  "address_line_2":"Dinajpur",
     *    * *  "lat":"23.704263332849386",
     *    * *  "long":"90.44707059805279",
     *
     *  "country":"Bangladesh",
     *  "city":"Dhaka",
     *  * "currency":"BDT",
     *  "postcode":"Dinajpur",
     *
     *  "logo":"https://images.unsplash.com/photo-1671410714831-969877d103b1?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=387&q=80",

     *  *  "image":"https://images.unsplash.com/photo-1671410714831-969877d103b1?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=387&q=80",
     *  "images":{"/a","/b","/c"}
     *
     * }),
     *
     *
     *
     *
     *
     *         ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */

    public function createBusiness(BusinessCreateRequest $request)
    {
        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            if (!$request->user()->hasPermissionTo('business_create')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $request_data = $request->validated();

             $request_data["business"] = $this->businessImageStore($request_data["business"]);


            $user = User::where([
                "id" =>  $request_data['business']['owner_id']
            ])
                ->first();

            if (!$user) {
                $error =  [
                    "message" => "The given data was invalid.",
                    "errors" => ["owner_id" => ["No User Found"]]
                ];
                throw new Exception(json_encode($error), 422);
            }

            if (!$user->hasRole('business_owner')) {
                $error =  [
                    "message" => "The given data was invalid.",
                    "errors" => ["owner_id" => ["The user is not a businesses Owner"]]
                ];
                throw new Exception(json_encode($error), 422);
            }



            $request_data['business']['status'] = "pending";

            $request_data['business']['created_by'] = $request->user()->id;
            $request_data['business']['reseller_id'] = $request->user()->id;
            $request_data['business']['is_active'] = true;
            $request_data['business']['is_self_registered_businesses'] = false;
            $request_data['business']["pension_scheme_letters"] = [];
            $business =  Business::create($request_data['business']);

            $this->storeDefaultsToBusiness($business);


            DB::commit();

            return response([
                "business" => $business
            ], 201);
        } catch (Exception $e) {
            $this->businessImageRollBack($request_data);

            DB::rollBack();
            return $this->sendError($e, 500, $request);
        }
    }



    /**
     *
     * @OA\Post(
     *      path="/v1.0/auth/check-schedule-conflict",
     *      operationId="checkScheduleConflict",
     *      tags={"business_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to store user with business",
     *      description="This method is to store user with business",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"user","business"},
     *
     *      @OA\Property(property="times", type="string", format="array",example={
     *
     *{"day":0,"start_at":"10:10:00","end_at":"10:15:00","is_weekend":true},
     *{"day":1,"start_at":"10:10:00","end_at":"10:15:00","is_weekend":true},
     *{"day":2,"start_at":"10:10:00","end_at":"10:15:00","is_weekend":true},
     *{"day":3,"start_at":"10:10:00","end_at":"10:15:00","is_weekend":true},
     *{"day":4,"start_at":"10:10:00","end_at":"10:15:00","is_weekend":true},
     *{"day":5,"start_at":"10:10:00","end_at":"10:15:00","is_weekend":true},
     *{"day":6,"start_at":"10:10:00","end_at":"10:15:00","is_weekend":true}
     *
     * }),
     *
     *


     *
     *

     *
     *         ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */
    public function checkScheduleConflict(CheckScheduleConflictRequest $request)
    {


        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            return  DB::transaction(function () use (&$request) {

                //     if(!$request->user()->hasPermissionTo('business_create')){
                //         return response()->json([
                //            "message" => "You can not perform this action"
                //         ],401);
                //    }
                $request_data = $request->validated();

                // $conflicted_work_shift_ids = collect();

                // $timesArray = collect($request_data["times"])->unique("day");
                // foreach ($timesArray as $business_time) {
                //     $work_shift_ids = WorkShift::where([
                //         "business_id" => auth()->user()->business_id
                //     ])
                //         ->whereHas('details', function ($query) use ($business_time) {
                //             $query->where('work_shift_details.day', ($business_time["day"]))
                //                 ->when(!empty($time["is_weekend"]), function ($query) {
                //                     $query->where('work_shift_details.is_weekend', 1);
                //                 })
                //                 ->where(function ($query) use ($business_time) {
                //                     $query->whereTime('work_shift_details.start_at', '<=', ($business_time["start_at"]))
                //                         ->orWhereTime('work_shift_details.end_at', '>=', ($business_time["end_at"]));
                //                 });
                //         })
                //         ->pluck("id");
                //     $conflicted_work_shift_ids = $conflicted_work_shift_ids->merge($work_shift_ids);
                // }
                // $conflicted_work_shift_ids = $conflicted_work_shift_ids->unique()->values()->all();

                // $conflicted_work_shifts =   WorkShift::whereIn("id", $conflicted_work_shift_ids)->get();

                // return response([
                //     "conflicted_work_shifts" => $conflicted_work_shifts
                // ], 200);

                throw new Exception("this feature is not available now",404);
            });
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }







    /**
     *
     * @OA\Post(
     *      path="/v1.0/auth/register-with-business",
     *      operationId="registerUserWithBusiness",
     *      tags={"business_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to store user with business",
     *      description="This method is to store user with business",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"user","business"},
     *             @OA\Property(property="user", type="string", format="array",example={
     * "first_Name":"Rifat",
     * "last_Name":"Al-Ashwad",
     * "middle_Name":"Al-Ashwad",
     * "email":"rifatalashwad@gmail.com",
     *  "password":"12345678",
     *  "password_confirmation":"12345678",
     *  "phone":"01771034383",
     *  "image":"https://images.unsplash.com/photo-1671410714831-969877d103b1?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=387&q=80",
     * "send_password":1,
     * "gender":"male"
     *
     *
     * }),
     *
     *   *      *    @OA\Property(property="times", type="string", format="array",example={
     *
     *{"day":0,"start_at":"10:10:00","end_at":"10:15:00","is_weekend":true},
     *{"day":1,"start_at":"10:10:00","end_at":"10:15:00","is_weekend":true},
     *{"day":2,"start_at":"10:10:00","end_at":"10:15:00","is_weekend":true},
     *{"day":3,"start_at":"10:10:00","end_at":"10:15:00","is_weekend":true},
     *{"day":4,"start_at":"10:10:00","end_at":"10:15:00","is_weekend":true},
     *{"day":5,"start_at":"10:10:00","end_at":"10:15:00","is_weekend":true},
     *{"day":6,"start_at":"10:10:00","end_at":"10:15:00","is_weekend":true}
     *
     * }),
     *
     *
     *  @OA\Property(property="business", type="string", format="array",example={
     *   "number_of_employees_allowed" : 1,
     *
     *   "pension_scheme_registered" : 1,
     *   "pension_scheme_name" : "hh",
     *   "pension_scheme_letters" : {{"file" :"vv.jpg"}},
     * "name":"ABCD businesses",
     * "start_date":"start_date",
     * "about":"Best businesses in Dhaka",
     * "web_page":"https://www.facebook.com/",
     * "identifier_prefix":"identifier_prefix",
     * "pin_code":"pin_code",
     *  "phone":"01771034383",
     *  "email":"rifatalashwad@gmail.com",
     *  "phone":"01771034383",
     *  "additional_information":"No Additional Information",
     *  "address_line_1":"Dhaka",
     *  "address_line_2":"Dinajpur",
     *    * *  "lat":"23.704263332849386",
     *    * *  "long":"90.44707059805279",
     *
     *  "country":"Bangladesh",
     *  "city":"Dhaka",
     *  * "currency":"BDT",
     *  "postcode":"Dinajpur",
     *
     *  "logo":"https://images.unsplash.com/photo-1671410714831-969877d103b1?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=387&q=80",

     *  *  "image":"https://images.unsplash.com/photo-1671410714831-969877d103b1?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=387&q=80",
     *  "images":{"/a","/b","/c"}
     *
     * }),
     *

     *
     *

     *
     *         ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */
    public function registerUserWithBusiness(AuthRegisterBusinessRequest $request)
    {
        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");


            if (!$request->user()->hasPermissionTo('business_create')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $request_data = $request->validated();


            // $request_data["business"] = $this->businessImageStore($request_data["business"]);

            $data = $this->createUserWithBusiness($request_data);


            DB::commit();

            return response(
                [
                    "user" => $data["user"],
                    "business" => $data["business"]
                ],
                201
            );
        } catch (Exception $e) {
            $this->businessImageRollBack($request_data);
            DB::rollBack();
            return $this->sendError($e, 500, $request);
        }
    }



    /**
     *
     * @OA\Post(
     *      path="/v1.0/client/auth/register-with-business",
     *      operationId="registerUserWithBusinessClient",
     *      tags={"business_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to store user with business",
     *      description="This method is to store user with business",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"user","business"},
     *             @OA\Property(property="user", type="string", format="array",example={
     * "first_Name":"Rifat",
     * "last_Name":"Al-Ashwad",
     * "middle_Name":"Al-Ashwad",
     * "email":"rifatalashwad@gmail.com",
     *  "password":"12345678",
     *  "password_confirmation":"12345678",
     *  "phone":"01771034383",
     *  "image":"https://images.unsplash.com/photo-1671410714831-969877d103b1?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=387&q=80",
     * "send_password":1,
     * "gender":"male"
     *
     *
     * }),
     *
     *  @OA\Property(property="times", type="string", format="array",example={
     *
     *{"day":0,"start_at":"10:10:00","end_at":"10:15:00","is_weekend":true},
     *{"day":1,"start_at":"10:10:00","end_at":"10:15:00","is_weekend":true},
     *{"day":2,"start_at":"10:10:00","end_at":"10:15:00","is_weekend":true},
     *{"day":3,"start_at":"10:10:00","end_at":"10:15:00","is_weekend":true},
     *{"day":4,"start_at":"10:10:00","end_at":"10:15:00","is_weekend":true},
     *{"day":5,"start_at":"10:10:00","end_at":"10:15:00","is_weekend":true},
     *{"day":6,"start_at":"10:10:00","end_at":"10:15:00","is_weekend":true}
     *
     * }),
     *
     *
     *  @OA\Property(property="business", type="string", format="array",example={
     * "is_self_registered_businesses" : 0,
     *   * "number_of_employees_allowed" : 0,
     * "service_plan_id" : 0,
     * "service_plan_discount_code" : 0,
     *   "pension_scheme_registered" : 1,
     *   "pension_scheme_name" : "hh",
     *   "pension_scheme_letters" : {{"file" :"vv.jpg"}},
     * "name":"ABCD businesses",
     * "start_date":"start_date",
     * "about":"Best businesses in Dhaka",
     * "web_page":"https://www.facebook.com/",
     * "identifier_prefix":"identifier_prefix",
     *  "pin_code":"pin_code",
     *  "phone":"01771034383",
     *  "email":"rifatalashwad@gmail.com",
     *  "phone":"01771034383",
     *  "additional_information":"No Additional Information",
     *  "address_line_1":"Dhaka",
     *  "address_line_2":"Dinajpur",
     *    * *  "lat":"23.704263332849386",
     *    * *  "long":"90.44707059805279",
     *
     *  "country":"Bangladesh",
     *  "city":"Dhaka",
     *  * "currency":"BDT",
     *  "postcode":"Dinajpur",
     *
     *  "logo":"https://images.unsplash.com/photo-1671410714831-969877d103b1?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=387&q=80",

     *  *  "image":"https://images.unsplash.com/photo-1671410714831-969877d103b1?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=387&q=80",
     *  "images":{"/a","/b","/c"}
     *
     * }),
     *

     *
     *

     *
     *         ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */
    public function registerUserWithBusinessClient(AuthRegisterBusinessRequest $request)
    {
        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");


            $request_data = $request->validated();



            // $request_data["business"] = $this->businessImageStore($request_data["business"]);

            // $request_data['business']["active_module_ids"] = [];


            $data = $this->createUserWithBusiness($request_data);


            DB::commit();

            return response(
                [
                    "user" => $data["user"],
                    "business" => $data["business"]
                ],
                201
            );

        } catch (Exception $e) {


            // $this->businessImageRollBack($request_data);

            DB::rollBack();
            return $this->sendError($e, 500, $request);
        }
    }

    /**
     *
     * @OA\Put(
     *      path="/v1.0/businesses",
     *      operationId="updateBusiness",
     *      tags={"business_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update user with business",
     *      description="This method is to update user with business",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"user","business"},
     *             @OA\Property(property="user", type="string", format="array",example={
     *  * "id":1,
     * "first_Name":"Rifat",
     *  * "middle_Name":"Al-Ashwad",
     *
     * "last_Name":"Al-Ashwad",
     * "email":"rifatalashwad@gmail.com",
     *  "password":"12345678",
     *  "password_confirmation":"12345678",
     *  "phone":"01771034383",
     *  "image":"https://images.unsplash.com/photo-1671410714831-969877d103b1?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=387&q=80",
     * "gender":"male"
     *
     *
     * }),
     *
     *    *   *      *    @OA\Property(property="times", type="string", format="array",example={
     *
     *{"day":0,"start_at":"10:10:00","end_at":"10:15:00","is_weekend":true},
     *{"day":1,"start_at":"10:10:00","end_at":"10:15:00","is_weekend":true},
     *{"day":2,"start_at":"10:10:00","end_at":"10:15:00","is_weekend":true},
     *{"day":3,"start_at":"10:10:00","end_at":"10:15:00","is_weekend":true},
     *{"day":4,"start_at":"10:10:00","end_at":"10:15:00","is_weekend":true},
     *{"day":5,"start_at":"10:10:00","end_at":"10:15:00","is_weekend":true},
     *{"day":6,"start_at":"10:10:00","end_at":"10:15:00","is_weekend":true}
     *
     * }),
     *
     *  @OA\Property(property="business", type="string", format="array",example={
     *   *  * "id":1,
     * "name":"ABCD businesses",
     * "about":"Best businesses in Dhaka",
     * "web_page":"https://www.facebook.com/",
     * "identifier_prefix":"identifier_prefix",
     * "pin_code":"pin_code",
     *  "phone":"01771034383",
     *  "email":"rifatalashwad@gmail.com",
     *  "phone":"01771034383",
     *  "additional_information":"No Additional Information",
     *  "address_line_1":"Dhaka",
     *  "address_line_2":"Dinajpur",
     *    * *  "lat":"23.704263332849386",
     *    * *  "long":"90.44707059805279",
     *
     *  "country":"Bangladesh",
     *  "city":"Dhaka",
     *  "postcode":"Dinajpur",
     *
     *  "logo":"https://images.unsplash.com/photo-1671410714831-969877d103b1?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=387&q=80",
     *      *  *  "image":"https://images.unsplash.com/photo-1671410714831-969877d103b1?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=387&q=80",
     *  "images":{"/a","/b","/c"},
     *  "currency":"BDT",
     * "number_of_employees_allowed":20
     *
     * }),
     *

     *
     *

     *
     *         ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */
    public function updateBusiness(BusinessUpdateRequest $request)
    {

        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            if (!$request->user()->hasPermissionTo('business_update')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $request_data = $request->validated();

            $business = $this->businessOwnerCheck($request_data['business']["id"], FALSE);

            $request_data["business"] = $this->businessImageStore($request_data["business"],$business->id);




            //    user email check
            $userPrev = User::where([
                "id" => $request_data["user"]["id"]
            ]);
            // if (!$request->user()->hasRole('superadmin')) {
            //     $userPrev  = $userPrev->where(function ($query) {
            //         return  $query
            //         ->where('created_by', auth()->user()->id)
            //             ->orWhere('id', auth()->user()->id);
            //     });
            // }
            $userPrev = $userPrev->first();

            if (!$userPrev) {
                throw new Exception("no user found with this id", 404);
            }




            //  $businessPrev = Business::where([
            //     "id" => $request_data["business"]["id"]
            //  ]);

            // $businessPrev = $businessPrev->first();
            // if(!$businessPrev) {
            //     return response()->json([
            //        "message" => "no business found with this id"
            //     ],404);
            //   }

            if (!empty($request_data['user']['password'])) {
                $request_data['user']['password'] = Hash::make($request_data['user']['password']);
            } else {
                unset($request_data['user']['password']);
            }
            $request_data['user']['is_active'] = true;
            $request_data['user']['remember_token'] = Str::random(10);
            $request_data['user']['address_line_1'] = $request_data['business']['address_line_1'];
            $request_data['user']['address_line_2'] = $request_data['business']['address_line_2'];
            $request_data['user']['country'] = $request_data['business']['country'];
            $request_data['user']['city'] = $request_data['business']['city'];
            $request_data['user']['postcode'] = $request_data['business']['postcode'];
            $request_data['user']['lat'] = $request_data['business']['lat'];
            $request_data['user']['long'] = $request_data['business']['long'];

            $user  =  tap(User::where([
                "id" => $request_data['user']["id"]
            ]))->update(
                collect($request_data['user'])->only([
                    'first_Name',
                    'middle_Name',
                    'last_Name',
                    'phone',
                    'image',
                    'address_line_1',
                    'address_line_2',
                    'country',
                    'city',
                    'postcode',
                    'email',
                    'password',
                    "lat",
                    "long",
                    "gender"
                ])->toArray()
            )
                // ->with("somthing")

                ->first();
            if (!$user) {
                throw new Exception("something went wrong updating user.", 500);
            }

            // $user->syncRoles(["business_owner"]);


            if (!empty($request_data["business"]["is_self_registered_businesses"])) {
                $request_data['business']['service_plan_discount_amount'] = $this->getDiscountAmount($request_data['business']);
            }


            $business->fill(collect($request_data['business'])->only([
                "name",
                "start_date",
                "trail_end_date",
                "about",
                "web_page",
                "identifier_prefix",
                "pin_code",
                "phone",
                "email",
                "additional_information",
                "address_line_1",
                "address_line_2",
                "lat",
                "long",
                "country",
                "city",
                "currency",
                "postcode",
                "logo",
                "image",
                "background_image",
                "status",
                "is_active",

                "is_self_registered_businesses",
                "service_plan_id",
                "service_plan_discount_code",
                "service_plan_discount_amount",

                "pension_scheme_registered",
                "pension_scheme_name",
                "pension_scheme_letters",
                "number_of_employees_allowed",
                "owner_id",


                // 'created_by'
            ])->toArray());

            $business->save();


            if (empty($business)) {
                return response()->json([
                    "massage" => "something went wrong"
                ], 500);
            }


            // end business info ##############

            if (!empty($request_data["times"])) {

                $timesArray = collect($request_data["times"])->unique("day");


                // $conflicted_work_shift_ids = collect();

                // foreach ($timesArray as $business_time) {
                //     $work_shift_ids = WorkShift::where([
                //         "business_id" => auth()->user()->business_id
                //     ])
                //         ->whereHas('details', function ($query) use ($business_time) {
                //             $query->where('work_shift_details.day', ($business_time["day"]))
                //                 ->when(!empty($time["is_weekend"]), function ($query) {
                //                     $query->where('work_shift_details.is_weekend', 1);
                //                 })
                //                 ->where(function ($query) use ($business_time) {
                //                     $query->whereTime('work_shift_details.start_at', '<=', ($business_time["start_at"]))
                //                         ->orWhereTime('work_shift_details.end_at', '>=', ($business_time["end_at"]));
                //                 });
                //         })
                //         ->pluck("id");
                //     $conflicted_work_shift_ids = $conflicted_work_shift_ids->merge($work_shift_ids);
                // }
                // $conflicted_work_shift_ids = $conflicted_work_shift_ids->unique()->values()->all();

                // if (!empty($conflicted_work_shift_ids)) {
                //     WorkShift::whereIn("id", $conflicted_work_shift_ids)->update([
                //         "is_active" => 0
                //     ]);

                //     WorkShiftHistory::where([
                //         "to_date" => NULL
                //     ])
                //         ->whereIn("work_shift_id", $conflicted_work_shift_ids)
                //         ->update([
                //             "to_date" => now()
                //         ]);
                // }





                BusinessTime::where([
                    "business_id" => $business->id
                ])
                    ->delete();

                $timesArray = collect($request_data["times"])->unique("day");
                foreach ($timesArray as $business_time) {
                    BusinessTime::create([
                        "business_id" => $business->id,
                        "day" => $business_time["day"],
                        "start_at" => $business_time["start_at"],
                        "end_at" => $business_time["end_at"],
                        "is_weekend" => $business_time["is_weekend"],
                    ]);
                }
            }

            $business->service_plan = $business->service_plan;

            DB::commit();

            return response([
                "user" => $user,
                "business" => $business
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();

            return $this->sendError($e, 500, $request);
        }
    }


    /**
     *
     * @OA\Put(
     *      path="/v1.0/businesses-take-over",
     *      operationId="takeOverBusiness",
     *      tags={"business_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to take over business",
     *      description="This method is to  take over business",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"user","id"},

     *
     *  @OA\Property(property="id", type="string", format="array",example="1"),
     *

     *
     *

     *
     *         ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */
    public function takeOverBusiness(BusinessTakeOverRequest $request)
    {

        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            if (!$request->user()->hasRole('superadmin')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $request_data = $request->validated();


            $business = $this->businessOwnerCheck($request_data["id"],FALSE);


            $business->reseller_id = auth()->user()->id;


            $business->save();


            if (empty($business)) {
                return response()->json([
                    "massage" => "something went wrong"
                ], 500);
            }




            DB::commit();

            return response([

                "business" => $business
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();

            return $this->sendError($e, 500, $request);
        }
    }



    /**
     *
     * @OA\Put(
     *      path="/v1.0/businesses-part-1",
     *      operationId="updateBusinessPart1",
     *      tags={"business_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update user with business",
     *      description="This method is to update user with business",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"user","business"},
     *             @OA\Property(property="user", type="string", format="array",example={
     *  * "id":1,
     * "first_Name":"Rifat",
     *  * "middle_Name":"Al-Ashwad",
     *
     * "last_Name":"Al-Ashwad",
     * "email":"rifatalashwad@gmail.com",
     *  "password":"12345678",
     *  "password_confirmation":"12345678",
     *  "phone":"01771034383",
     *  "image":"https://images.unsplash.com/photo-1671410714831-969877d103b1?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=387&q=80",
     * "gender":"male"
     *
     *
     * }),
     *
     *

     *
     *

     *
     *         ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */
    public function updateBusinessPart1(BusinessUpdatePart1Request $request)
    {

        DB::beginTransaction();
        try {

            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            if (!$request->user()->hasPermissionTo('business_update')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $business = $this->businessOwnerCheck(auth()->user()->business_id, FALSE);

            $request_data = $request->validated();
            //    user email check
            $userPrev = User::where([
                "id" => $request_data["user"]["id"]
            ]);
            // if (!$request->user()->hasRole('superadmin')) {
            //     $userPrev  = $userPrev->where(function ($query) {
            //         return  $query->where('created_by', auth()->user()->id)
            //             ->orWhere('id', auth()->user()->id);
            //     });
            // }
            $userPrev = $userPrev->first();

            if (!$userPrev) {
                throw new Exception("no user found with this id", 404);
            }




            //  $businessPrev = Business::where([
            //     "id" => $request_data["business"]["id"]
            //  ]);

            // $businessPrev = $businessPrev->first();
            // if(!$businessPrev) {
            //     return response()->json([
            //        "message" => "no business found with this id"
            //     ],404);
            //   }


            if (!empty($request_data['user']['password'])) {
                $request_data['user']['password'] = Hash::make($request_data['user']['password']);
            } else {
                unset($request_data['user']['password']);
            }
            $request_data['user']['is_active'] = true;
            $request_data['user']['remember_token'] = Str::random(10);
            $request_data['user']['address_line_1'] = $request_data['business']['address_line_1'];
            $request_data['user']['address_line_2'] = $request_data['business']['address_line_2'];
            $request_data['user']['country'] = $request_data['business']['country'];
            $request_data['user']['city'] = $request_data['business']['city'];
            $request_data['user']['postcode'] = $request_data['business']['postcode'];
            $request_data['user']['lat'] = $request_data['business']['lat'];
            $request_data['user']['long'] = $request_data['business']['long'];
            $user  =  tap(User::where([
                "id" => $request_data['user']["id"]
            ]))->update(
                collect($request_data['user'])->only([
                    'first_Name',
                    'middle_Name',
                    'last_Name',
                    'phone',
                    'image',
                    'address_line_1',
                    'address_line_2',
                    'country',
                    'city',
                    'postcode',
                    'email',
                    'password',
                    "lat",
                    "long",
                    "gender"
                ])->toArray()
            )
                // ->with("somthing")

                ->first();
            if (!$user) {
                throw new Exception("something went wrong updating user.", 500);
            }


            DB::commit();
            return response([
                "user" => $user,

            ], 201);
        } catch (Exception $e) {

            DB::rollBack();

            return $this->sendError($e, 500, $request);
        }
    }

    /**
     *
     * @OA\Put(
     *      path="/v1.0/businesses-part-2",
     *      operationId="updateBusinessPart2",
     *      tags={"business_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update user with business",
     *      description="This method is to update user with business",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"user","business"},

     *
     *  @OA\Property(property="business", type="string", format="array",example={
     *   *  * "id":1,
     * "name":"ABCD businesses",
     * "about":"Best businesses in Dhaka",
     * "web_page":"https://www.facebook.com/",
     * "identifier_prefix":"identifier_prefix",
     * "pin_code":"pin_code",
     *  "phone":"01771034383",
     *  "email":"rifatalashwad@gmail.com",
     *  "phone":"01771034383",
     *  "additional_information":"No Additional Information",
     *  "address_line_1":"Dhaka",
     *  "address_line_2":"Dinajpur",
     *    * *  "lat":"23.704263332849386",
     *    * *  "long":"90.44707059805279",
     *
     *  "country":"Bangladesh",
     *  "city":"Dhaka",
     *  "postcode":"Dinajpur",
     *
     *  "logo":"https://images.unsplash.com/photo-1671410714831-969877d103b1?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=387&q=80",
     *      *  *  "image":"https://images.unsplash.com/photo-1671410714831-969877d103b1?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=387&q=80",
     *  "images":{"/a","/b","/c"},
     *  "currency":"BDT",

     * "number_of_employees_allowed":1
     *
     * }),
     *

     *
     *

     *
     *         ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */
    public function updateBusinessPart2(BusinessUpdatePart2Request $request)
    {

        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            if (!$request->user()->hasPermissionTo('business_update')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }



            $request_data = $request->validated();

            $business = $this->businessOwnerCheck($request_data['business']["id"], FALSE);


            if (!empty($request_data["business"]["images"])) {
                $request_data["business"]["images"] = $this->storeUploadedFiles($request_data["business"]["images"], "", "business_images");
                $this->makeFilePermanent($request_data["business"]["images"], "");
            }
            if (!empty($request_data["business"]["image"])) {
                $request_data["business"]["image"] = $this->storeUploadedFiles([$request_data["business"]["image"]], "", "business_images")[0];
                $this->makeFilePermanent([$request_data["business"]["image"]], "");
            }
            if (!empty($request_data["business"]["logo"])) {
                $request_data["business"]["logo"] = $this->storeUploadedFiles([$request_data["business"]["logo"]], "", "business_images")[0];
                $this->makeFilePermanent([$request_data["business"]["logo"]], "");
            }
            if (!empty($request_data["business"]["background_image"])) {
                $request_data["business"]["background_image"] = $this->storeUploadedFiles([$request_data["business"]["background_image"]], "", "business_images")[0];
                $this->makeFilePermanent([$request_data["business"]["background_image"]], "");
            }





            $business->fill(collect($request_data['business'])->only([
                "name",
                "start_date",
                "about",
                "web_page",
                "identifier_prefix",
                "pin_code",
                "phone",
                "email",
                "additional_information",
                "address_line_1",
                "address_line_2",
                "lat",
                "long",
                "country",
                "city",
                "postcode",
                "logo",
                "image",
                "status",
                "background_image",
                "currency",
                "number_of_employees_allowed"
            ])->toArray());

            $business->save();




            if (empty($business)) {
                return response()->json([
                    "massage" => "something went wrong"
                ], 500);
            }




            DB::commit();
            return response([
                "business" => $business
            ], 201);
        } catch (Exception $e) {

            DB::rollBack();

            return $this->sendError($e, 500, $request);
        }
    }

    /**
     *
     * @OA\Put(
     *      path="/v2.0/businesses-part-2",
     *      operationId="updateBusinessPart2V2",
     *      tags={"business_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update user with business",
     *      description="This method is to update user with business",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"user","business"},

     *
     *  @OA\Property(property="business", type="string", format="array",example={
     *   *  * "id":1,
     * "name":"ABCD businesses",
     * "about":"Best businesses in Dhaka",
     * "web_page":"https://www.facebook.com/",
     * "identifier_prefix":"identifier_prefix",
     * "pin_code":"pin_code",
     *  "phone":"01771034383",
     *  "email":"rifatalashwad@gmail.com",
     *  "phone":"01771034383",
     *  "additional_information":"No Additional Information",
     *  "address_line_1":"Dhaka",
     *  "address_line_2":"Dinajpur",
     *    * *  "lat":"23.704263332849386",
     *    * *  "long":"90.44707059805279",
     *
     *  "country":"Bangladesh",
     *  "city":"Dhaka",
     *  "postcode":"Dinajpur",
     *
     *  "logo":"https://images.unsplash.com/photo-1671410714831-969877d103b1?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=387&q=80",
     *      *  *  "image":"https://images.unsplash.com/photo-1671410714831-969877d103b1?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=387&q=80",
     *  "images":{"/a","/b","/c"},
     *  "currency":"BDT",
     * "number_of_employees_allowed":1
     *
     * }),
     *

     *
     *

     *
     *         ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */
    public function updateBusinessPart2V2(BusinessUpdatePart2RequestV2 $request)
    {

        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            if (!$request->user()->hasPermissionTo('business_update')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }



            $request_data = $request->validated();

            $business = $this->businessOwnerCheck($request_data['business']["id"], FALSE);

            if (!empty($request_data["business"]["images"])) {
                $request_data["business"]["images"] = $this->storeUploadedFiles($request_data["business"]["images"], "", "business_images");
                $this->makeFilePermanent($request_data["business"]["images"], "");
            }
            if (!empty($request_data["business"]["image"])) {
                $request_data["business"]["image"] = $this->storeUploadedFiles([$request_data["business"]["image"]], "", "business_images")[0];
                $this->makeFilePermanent([$request_data["business"]["image"]], "");
            }
            if (!empty($request_data["business"]["logo"])) {
                $request_data["business"]["logo"] = $this->storeUploadedFiles([$request_data["business"]["logo"]], "", "business_images")[0];
                $this->makeFilePermanent([$request_data["business"]["logo"]], "");
            }
            if (!empty($request_data["business"]["background_image"])) {
                $request_data["business"]["background_image"] = $this->storeUploadedFiles([$request_data["business"]["background_image"]], "", "business_images")[0];
                $this->makeFilePermanent([$request_data["business"]["background_image"]], "");
            }





            $business->fill(collect($request_data['business'])->only([
                "name",
                "email",
                "phone",
                "address_line_1",
                "city",
                "country",
                "postcode",
                "start_date",
                "web_page",
                "identifier_prefix",
                "pin_code"


            ])->toArray());

            $business->save();




            if (empty($business)) {
                return response()->json([
                    "massage" => "something went wrong"
                ], 500);
            }




            DB::commit();
            return response([
                "business" => $business
            ], 201);
        } catch (Exception $e) {

            DB::rollBack();

            return $this->sendError($e, 500, $request);
        }
    }

    /**
     *
     * @OA\Put(
     *      path="/v1.0/businesses-part-3",
     *      operationId="updateBusinessPart3",
     *      tags={"business_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update user with business",
     *      description="This method is to update user with business",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"user","business"},
     *             @OA\Property(property="user", type="string", format="array",example={
     *  * "id":1,
     * "first_Name":"Rifat",
     *  * "middle_Name":"Al-Ashwad",
     *
     * "last_Name":"Al-Ashwad",
     * "email":"rifatalashwad@gmail.com",
     *  "password":"12345678",
     *  "password_confirmation":"12345678",
     *  "phone":"01771034383",
     *  "image":"https://images.unsplash.com/photo-1671410714831-969877d103b1?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=387&q=80",
     * "gender":"male"
     *
     *
     * }),
     *
     *  @OA\Property(property="business", type="string", format="array",example={
     *   *  * "id":1,
     * "name":"ABCD businesses",
     * "about":"Best businesses in Dhaka",
     * "web_page":"https://www.facebook.com/",
     * "identifier_prefix":"identifier_prefix",
     * "pin_code":"pin_code",
     *  "phone":"01771034383",
     *  "email":"rifatalashwad@gmail.com",
     *  "phone":"01771034383",
     *  "additional_information":"No Additional Information",
     *  "address_line_1":"Dhaka",
     *  "address_line_2":"Dinajpur",
     *    * *  "lat":"23.704263332849386",
     *    * *  "long":"90.44707059805279",
     *
     *  "country":"Bangladesh",
     *  "city":"Dhaka",
     *  "postcode":"Dinajpur",
     *
     *  "logo":"https://images.unsplash.com/photo-1671410714831-969877d103b1?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=387&q=80",
     *      *  *  "image":"https://images.unsplash.com/photo-1671410714831-969877d103b1?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=387&q=80",
     *  "images":{"/a","/b","/c"},
     *  "currency":"BDT",
     * "number_of_employees_allowed":10
     *
     * }),
     *

     *
     *

     *
     *         ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */
    public function updateBusinessPart3(BusinessUpdatePart3Request $request)
    {

        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            if (!$request->user()->hasPermissionTo('business_update')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $business = $this->businessOwnerCheck(auth()->user()->business_id, FALSE);




            $request_data = $request->validated();



            if (!empty($request_data["times"])) {

                $timesArray = collect($request_data["times"])->unique("day");


                // $conflicted_work_shift_ids = collect();

                // foreach ($timesArray as $business_time) {
                //     $work_shift_ids = WorkShift::where([
                //         "business_id" => auth()->user()->business_id
                //     ])
                //         ->whereHas('details', function ($query) use ($business_time) {
                //             $query->where('work_shift_details.day', ($business_time["day"]))
                //                 ->when(!empty($time["is_weekend"]), function ($query) {
                //                     $query->where('work_shift_details.is_weekend', 1);
                //                 })
                //                 ->where(function ($query) use ($business_time) {
                //                     $query->whereTime('work_shift_details.start_at', '<=', ($business_time["start_at"]))
                //                         ->orWhereTime('work_shift_details.end_at', '>=', ($business_time["end_at"]));
                //                 });
                //         })
                //         ->pluck("id");
                //     $conflicted_work_shift_ids = $conflicted_work_shift_ids->merge($work_shift_ids);
                // }
                // $conflicted_work_shift_ids = $conflicted_work_shift_ids->unique()->values()->all();

                // if (!empty($conflicted_work_shift_ids)) {
                //     WorkShift::whereIn("id", $conflicted_work_shift_ids)->update([
                //         "is_active" => 0
                //     ]);

                //     WorkShiftHistory::where([
                //         "to_date" => NULL
                //     ])
                //         ->whereIn("work_shift_id", $conflicted_work_shift_ids)
                //         ->update([
                //             "to_date" => now()
                //         ]);
                // }





                BusinessTime::where([
                    "business_id" => $business->id
                ])
                    ->delete();

                $timesArray = collect($request_data["times"])->unique("day");
                foreach ($timesArray as $business_time) {
                    BusinessTime::create([
                        "business_id" => $business->id,
                        "day" => $business_time["day"],
                        "start_at" => $business_time["start_at"],
                        "end_at" => $business_time["end_at"],
                        "is_weekend" => $business_time["is_weekend"],
                    ]);
                }
            }




            DB::commit();
            return response([

                "business" => $business
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();

            return $this->sendError($e, 500, $request);
        }
    }

    /**
     *
     * @OA\Put(
     *      path="/v1.0/business-pension-information",
     *      operationId="updateBusinessPensionInformation",
     *      tags={"business_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update business pension information",
     *      description="This method is to update pension information",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"user","business"},
     *
     *  @OA\Property(property="business", type="string", format="array",example={
     *   *  * "id":1,
     *   "pension_scheme_registered" : 1,
     *   "pension_scheme_name" : "hh",
     *   "pension_scheme_letters" : {{"file" :"vv.jpg"}}
     *
     * }),
     *

     *
     *

     *
     *         ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */
    public function updateBusinessPensionInformation(BusinessUpdatePensionRequest $request)
    {


        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            if (!$request->user()->hasPermissionTo('business_update')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }


            $request_data = $request->validated();
            $business = $this->businessOwnerCheck($request_data['business']["id"], FALSE);

            $request_data["business"]["pension_scheme_letters"] = $this->storeUploadedFiles($request_data["business"]["pension_scheme_letters"], "", "pension_scheme_letters");

            $this->makeFilePermanent($request_data["business"]["pension_scheme_letters"], "");



            $pension_scheme_data =  collect($request_data['business'])->only([
                "pension_scheme_registered",
                "pension_scheme_name",
                "pension_scheme_letters",

            ])->toArray();


            $fields_to_check = [
                "pension_scheme_registered",
                "pension_scheme_name",
                "pension_scheme_letters",
            ];
            $date_fields = [];


            $fields_changed = $this->fieldsHaveChanged($fields_to_check, $business, $pension_scheme_data, $date_fields);

            if (
                $fields_changed
            ) {
                BusinessPensionHistory::create(array_merge(["created_by" => auth()->user()->id, "business_id" => $request_data['business']["id"]], $pension_scheme_data));
            }





            $business
                ->fill($pension_scheme_data)
                ->save();









            if (empty($business)) {
                return response()->json([
                    "massage" => "something went wrong"
                ], 500);
            }







            // $this->moveUploadedFiles(collect($request_data["business"]["pension_scheme_letters"])->pluck("file"),"pension_scheme_letters");



            DB::commit();
            return response([
                "business" => $business
            ], 201);
        } catch (Exception $e) {

            DB::rollBack();

            return $this->sendError($e, 500, $request);
        }
    }


    /**
     *
     * @OA\Put(
     *      path="/v1.0/businesses/toggle-active",
     *      operationId="toggleActiveBusiness",
     *      tags={"business_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to toggle business",
     *      description="This method is to toggle business",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"id","first_Name","last_Name","email","password","password_confirmation","phone","address_line_1","address_line_2","country","city","postcode","role"},
     *           @OA\Property(property="id", type="string", format="number",example="1"),
     *
     *         ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */

    public function toggleActiveBusiness(GetIdRequest $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('business_update')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $request_data = $request->validated();

            $business = $this->businessOwnerCheck($request_data["id"], FALSE);


            if (empty($business)) {
                throw new Exception("no business found", 404);
            }


            $business->update([
                'is_active' => !$business->is_active
            ]);

            return response()->json(['message' => 'business status updated successfully'], 200);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }





    /**
     *
     * @OA\Put(
     *      path="/v1.0/businesses/separate",
     *      operationId="updateBusinessSeparate",
     *      tags={"business_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update business",
     *      description="This method is to update business",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"business"},

     *
     *  @OA\Property(property="business", type="string", format="array",example={
     *   *  * "id":1,
     * "name":"ABCD businesses",
     * "about":"Best businesses in Dhaka",
     * "web_page":"https://www.facebook.com/",
     * "identifier_prefix":"identifier_prefix",
     * "pin_code":"pin_code",
     *
     *  "phone":"01771034383",
     *  "email":"rifatalashwad@gmail.com",
     *  "phone":"01771034383",
     *  "additional_information":"No Additional Information",
     *  "address_line_1":"Dhaka",
     *  "address_line_2":"Dinajpur",
     *    * *  "lat":"23.704263332849386",
     *    * *  "long":"90.44707059805279",
     *
     *  "country":"Bangladesh",
     *  "city":"Dhaka",
     *  "postcode":"Dinajpur",
     *
     *  "logo":"https://images.unsplash.com/photo-1671410714831-969877d103b1?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=387&q=80",
     *      *  *  "image":"https://images.unsplash.com/photo-1671410714831-969877d103b1?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=387&q=80",
     *  "images":{"/a","/b","/c"},
     * *  "currency":"BDT"
     *
     * }),
     *

     *

     *
     *         ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */
    public function updateBusinessSeparate(BusinessUpdateSeparateRequest $request)
    {

        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            if (!$request->user()->hasPermissionTo('business_update')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }



            $request_data = $request->validated();
            $business = $this->businessOwnerCheck($request_data['business']["id"], FALSE);

            //  business info ##############
            // $request_data['business']['status'] = "pending";
            $business->fill(collect($request_data['business'])->only([
                "name",
                "start_date",
                "about",
                "web_page",
                "identifier_prefix",
                "pin_code",

                "phone",
                "email",
                "additional_information",
                "address_line_1",
                "address_line_2",
                "lat",
                "long",
                "country",
                "city",
                "postcode",
                "logo",
                "image",
                "status",
                "background_image",
                "currency",

                "number_of_employees_allowed"
            ])->toArray());

            $business->save();


            if (empty($business)) {
                return response()->json([
                    "massage" => "no business found"
                ], 404);
            }

            DB::commit();
            return response([
                "business" => $business
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();

            return $this->sendError($e, 500, $request);
        }
    }



    /**
     *
     * @OA\Get(
     *      path="/v1.0/businesses",
     *      operationId="getBusinesses",
     *      tags={"business_management"},
     * *  @OA\Parameter(
     * name="start_date",
     * in="query",
     * description="start_date",
     * required=true,
     * example="2019-06-29"
     * ),
     * *  @OA\Parameter(
     * name="end_date",
     * in="query",
     * description="end_date",
     * required=true,
     * example="2019-06-29"
     * ),
     * *  @OA\Parameter(
     * name="search_key",
     * in="query",
     * description="search_key",
     * required=true,
     * example="search_key"
     * ),
     * *  @OA\Parameter(
     * name="country_code",
     * in="query",
     * description="country_code",
     * required=true,
     * example="country_code"
     * ),
     * *  @OA\Parameter(
     * name="address",
     * in="query",
     * description="address",
     * required=true,
     * example="address"
     * ),
     * *  @OA\Parameter(
     * name="city",
     * in="query",
     * description="city",
     * required=true,
     * example="city"
     * ),
     * *  @OA\Parameter(
     * name="start_lat",
     * in="query",
     * description="start_lat",
     * required=true,
     * example="3"
     * ),
     * *  @OA\Parameter(
     * name="end_lat",
     * in="query",
     * description="end_lat",
     * required=true,
     * example="2"
     * ),
     * *  @OA\Parameter(
     * name="start_long",
     * in="query",
     * description="start_long",
     * required=true,
     * example="1"
     * ),
     * *  @OA\Parameter(
     * name="end_long",
     * in="query",
     * description="end_long",
     * required=true,
     * example="4"
     * ),
     * *  @OA\Parameter(
     * name="per_page",
     * in="query",
     * description="per_page",
     * required=true,
     * example="10"
     * ),
     * *  @OA\Parameter(
     * name="order_by",
     * in="query",
     * description="order_by",
     * required=true,
     * example="ASC"
     * ),
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *
     *      summary="This method is to get businesses",
     *      description="This method is to get businesses",
     *

     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */

    public function getBusinesses(Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('business_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $businesses = Business::with([
                "owner" => function ($query) {
                    $query->select(
                        'users.id',
                        'users.first_Name',
                        'users.middle_Name',
                        'users.last_Name'
                    );
                },
                "creator" => function ($query) {
                    $query->select(
                        'users.id',
                        'users.first_Name',
                        'users.middle_Name',
                        'users.last_Name',
                        "users.email"
                    );
                },

            ])
                ->withCount('users')
                ->when(
                    !$request->user()->hasRole('superadmin'),
                    function ($query) use ($request) {
                        $query->where(function ($query) {
                            $query
                                // ->where('id', auth()->user()->business_id)
                                // ->orWhere('created_by', auth()->user()->id)
                                ->orWhere('owner_id', auth()->user()->id)
                                ->orWhere('reseller_id', auth()->user()->id)
                            ;
                        });
                    },
                )
                ->when(!empty($request->search_key), function ($query) use ($request) {
                    $term = $request->search_key;
                    return $query->where(function ($query) use ($term) {
                        $query->where("name", "like", "%" . $term . "%")
                            ->orWhere("phone", "like", "%" . $term . "%")
                            ->orWhere("email", "like", "%" . $term . "%")
                            ->orWhere("city", "like", "%" . $term . "%")
                            ->orWhere("postcode", "like", "%" . $term . "%");
                    });
                })
                ->when(!empty($request->start_date), function ($query) use ($request) {
                    return $query->where('created_at', ">=", $request->start_date);
                })
                ->when(!empty($request->end_date), function ($query) use ($request) {
                    return $query->where('created_at', "<=", ($request->end_date . ' 23:59:59'));
                })
                ->when(!empty($request->start_lat), function ($query) use ($request) {
                    return $query->where('lat', ">=", $request->start_lat);
                })
                ->when(!empty($request->end_lat), function ($query) use ($request) {
                    return $query->where('lat', "<=", $request->end_lat);
                })
                ->when(!empty($request->start_long), function ($query) use ($request) {
                    return $query->where('long', ">=", $request->start_long);
                })
                ->when(!empty($request->end_long), function ($query) use ($request) {
                    return $query->where('long', "<=", $request->end_long);
                })
                ->when(!empty($request->address), function ($query) use ($request) {
                    $term = $request->address;
                    return $query->where(function ($query) use ($term) {
                        $query->where("country", "like", "%" . $term . "%")
                            ->orWhere("city", "like", "%" . $term . "%");
                    });
                })
                ->when(!empty($request->country_code), function ($query) use ($request) {
                    return $query->orWhere("country", "like", "%" . $request->country_code . "%");
                })
                ->when(!empty($request->city), function ($query) use ($request) {
                    return $query->orWhere("city", "like", "%" . $request->city . "%");
                })



                ->when(!empty($request->created_by), function ($query) use ($request) {
                    return $query->where("created_by", $request->created_by);
                })


                ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                    return $query->orderBy("businesses.id", $request->order_by);
                }, function ($query) {
                    return $query->orderBy("businesses.id", "DESC");
                })
                ->when(!empty($request->per_page), function ($query) use ($request) {
                    return $query->paginate($request->per_page);
                }, function ($query) {
                    return $query->get();
                });

            return response()->json($businesses, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }

    /**
     *
     * @OA\Get(
     *      path="/v1.0/businesses/{id}",
     *      operationId="getBusinessById",
     *      tags={"business_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="id",
     *         required=true,
     *  example="1"
     *      ),
     *      summary="This method is to get business by id",
     *      description="This method is to get business by id",
     *

     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */

    public function getBusinessById($id, Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('business_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $business = $this->businessOwnerCheck($id,FALSE);

            $business->load('owner', 'times', 'service_plan');





            return response()->json($business, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }

 /**
     *
     * @OA\Get(
     *      path="/v1.0/business-subscriptions/{id}",
     *      operationId="getSubscriptionsByBusinessId",
     *      tags={"business_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="id",
     *         required=true,
     *  example="1"
     *      ),
     *      *              @OA\Parameter(
     *         name="per_page",
     *         in="path",
     *         description="per_page",
     *         required=true,
     *  example="1"
     *      ),
     *      summary="This method is to get subscriptions by id",
     *      description="This method is to get subscriptions by id",
     *

     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */

     public function getSubscriptionsByBusinessId($id, Request $request)
     {

         try {
             $this->storeActivity($request, "DUMMY activity", "DUMMY description");
             if (!$request->user()->hasPermissionTo('business_view')) {
                 return response()->json([
                     "message" => "You can not perform this action"
                 ], 401);
             }

             $business = $this->businessOwnerCheck($id,FALSE);

             $businessSubscriptionsQuery = BusinessSubscription::with("service_plan")
                 ->where([
                     "business_id" => $business->id
                 ]);


             $business_subscriptions = $this->retrieveData($businessSubscriptionsQuery, "business_subscriptions.id");
             $upcoming_business_subscription = [];


             $last_business_subscription = $businessSubscriptionsQuery->latest()->first();

             if (!empty($last_business_subscription)) {

                 $business_subscription_end_date = Carbon::parse($last_business_subscription->end_date);

                 // $upcoming_business_subscription_start_date = Carbon::parse($business_subscription_end_date->addDays($last_subscription->service_plan->duration_months));

                 $upcoming_business_subscription_start_date = $business_subscription_end_date;

                 $upcoming_service_plan = $last_business_subscription->service_plan;

                 $upcoming_business_subscription = [
                     'service_plan_id' => $upcoming_service_plan->id,
                     'start_date' => $upcoming_business_subscription_start_date,  // Start date of the subscription
                     'end_date' => Carbon::parse($upcoming_business_subscription_start_date)->addDays($last_business_subscription->service_plan->duration_months),  // End date based on plan duration
                     'amount' => $upcoming_service_plan->price,
                     "service_plan" => $upcoming_service_plan
                 ];
             } else {
                 $service_plan =    ServicePlan::where("id", $business->service_plan_id)->first();

                 if ($service_plan) {

                     // Check if trail_end_date is empty or a past date
                     if (empty($business->trail_end_date) || Carbon::parse($business->trail_end_date)->isPast()) {
                         $start_date = today();
                     } else {
                         // If trail_end_date is a future date
                         $start_date = Carbon::parse($business->trail_end_date);
                     }
                     $upcoming_business_subscription = [
                         'service_plan_id' => $service_plan->id,
                         'start_date' => $start_date,
                         'end_date' => Carbon::parse($start_date)->addDays($service_plan->duration_months),
                         'amount' => $service_plan->price,
                         "service_plan" => $service_plan
                     ];
                 }
             }


             $responseData = [
                 "subscriptions" => $business_subscriptions,
                 "upcoming_subscription" => $upcoming_business_subscription
             ];






             return response()->json($responseData, 200);
         } catch (Exception $e) {

             return $this->sendError($e, 500, $request);
         }
     }




    /**
     *
     * @OA\Get(
     *      path="/v2.0/businesses/{id}",
     *      operationId="getBusinessByIdV2",
     *      tags={"business_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="id",
     *         required=true,
     *  example="1"
     *      ),
     *      summary="This method is to get business by id",
     *      description="This method is to get business by id",
     *

     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */

    public function getBusinessByIdV2($id, Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('business_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $business  = Business::where(["id" => $id])
                ->when(
                    !$request->user()->hasRole('superadmin'),
                    function ($query) use ($request) {
                        $query->where(function ($query) {
                            $query
                                // ->where('id', auth()->user()->business_id)
                                // ->orWhere('created_by', auth()->user()->id)
                                ->orWhere('owner_id', auth()->user()->id)
                                ->orWhere('reseller_id', auth()->user()->id)
                            ;
                        });
                    },
                )
                ->select(
                    "id",
                    "name",
                    "email",
                    "phone",
                    "address_line_1",
                    "city",
                    "country",

                    "postcode",
                    "start_date",
                    "web_page",
                    'identifier_prefix',
                    "pin_code",
                    "reseller_id"
                )
                ->first();



            if (empty($business)) {
                throw new Exception("you are not the owner of the business or the requested business does not exist.", 401);
            }

            return response()->json($business, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }

  /**
     *
     * @OA\Get(
     *      path="/v1.0/businesses-id-by-email/{email}",
     *      operationId="getBusinessIdByEmail",
     *      tags={"business_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="email",
     *         in="path",
     *         description="email",
     *         required=true,
     *  example="1"
     *      ),
     *      summary="This method is to get business id by email",
     *      description="This method is to get business id by email",
     *

     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */

     public function getBusinessIdByEmail($email, Request $request)
     {

         try {
             $this->storeActivity($request, "DUMMY activity", "DUMMY description");
             if (!$request->user()->hasPermissionTo('business_view')) {
                 return response()->json([
                     "message" => "You can not perform this action"
                 ], 401);
             }

             $business  = Business::where(["email" => $email])
                 ->when(
                     !$request->user()->hasRole('superadmin'),
                     function ($query) use ($request) {
                         $query->where(function ($query) {
                             $query
                                 // ->where('id', auth()->user()->business_id)
                                 // ->orWhere('created_by', auth()->user()->id)
                                 ->orWhere('owner_id', auth()->user()->id)
                                 ->orWhere('reseller_id', auth()->user()->id)
                             ;
                         });
                     },
                 )
                 ->select(
                     "id"
                 )
                 ->first();

             if (empty($business)) {
                 throw new Exception("you are not the owner of the business or the requested business does not exist.", 401);
             }

             return response()->json($business, 200);
         } catch (Exception $e) {

             return $this->sendError($e, 500, $request);
         }
     }



    /**
     *
     * @OA\Get(
     *      path="/v1.0/businesses-pension-information/{id}",
     *      operationId="getBusinessPensionInformationById",
     *      tags={"business_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="id",
     *         required=true,
     *  example="1"
     *      ),
     *      *              @OA\Parameter(
     *         name="per_page",
     *         in="path",
     *         description="per_page",
     *         required=true,
     *  example="1"
     *      ),
     *      summary="This method is to get business pension information by id",
     *      description="This method is to get business pension information by id",
     *

     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */

    public function getBusinessPensionInformationById($id, Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('business_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business = $this->businessOwnerCheck($id,FALSE);

            if (!is_array($business->pension_scheme_letters) || empty($business->pension_scheme_letters)) {
                $business->pension_scheme_letters = [];
            } else {

                if (!is_string($business->pension_scheme_letters[0])) {
                    $business->pension_scheme_letters = [];
                }
            }

            $businessData = [
                'pension_scheme_registered' => $business->pension_scheme_registered,
                'pension_scheme_name' => $business->pension_scheme_name,
                'pension_scheme_letters' => $business->pension_scheme_letters,
            ];


            return response()->json($businessData, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }

    /**
     *
     * @OA\Get(
     *      path="/v1.0/businesses-pension-information-history/{id}",
     *      operationId="getBusinessPensionInformationHistoryByBusinessId",
     *      tags={"business_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="id",
     *         required=true,
     *  example="1"
     *      ),
     *      summary="This method is to get business pension information history by business id",
     *      description="This method is to get business pension information history by business id",
     *

     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */

    public function getBusinessPensionInformationHistoryByBusinessId($id, Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('business_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business = $this->businessOwnerCheck($id,FALSE);

            $businessPensionHistoriesQuery =  BusinessPensionHistory::where([
                "business_id" => $id
            ]);


            $businessPensionHistories = $this->retrieveData($businessPensionHistoriesQuery, "business_pension_histories.id");





            return response()->json($businessPensionHistories, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }


    /**
     *
     * @OA\Delete(
     *      path="/v1.0/businesses-pension-information-history/{ids}",
     *      operationId="deleteBusinessPensionInformationHistoryByIds",
     *      tags={"business_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="ids",
     *         in="path",
     *         description="ids",
     *         required=true,
     *  example="6,7,8"
     *      ),
     *      summary="This method is to delete business pension history by id",
     *      description="This method is to delete business pension history by id",
     *

     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */

    public function deleteBusinessPensionInformationHistoryByIds(Request $request, $ids)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('business_delete')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $idsArray = explode(',', $ids);
            $existingIds = BusinessPensionHistory::whereIn('business_pension_histories.id', $idsArray)
                ->where(function ($query) {
                    $query
                        // ->where('id', auth()->user()->business_id)
                        // ->orWhere('created_by', auth()->user()->id)
                        ->orWhere('owner_id', auth()->user()->id)
                        ->orWhere('reseller_id', auth()->user()->id)
                    ;
                })


                ->select('business_pension_histories.id')
                ->get()
                ->pluck('business_pension_histories.id')
                ->toArray();
            $nonExistingIds = array_diff($idsArray, $existingIds);


            if (!empty($nonExistingIds)) {
                return response()->json([
                    "message" => "Some or all of the specified data do not exist.",
                    "non_existing_ids" => $nonExistingIds
                ], 404);
            }


            BusinessPensionHistory::whereIn('id', $existingIds)->delete();

            return response()->json(["message" => "data deleted sussfully", "deleted_ids" => $existingIds], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }

    /**
     *
     * @OA\Delete(
     *      path="/v1.0/businesses/{ids}",
     *      operationId="deleteBusinessesByIds",
     *      tags={"business_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="ids",
     *         in="path",
     *         description="ids",
     *         required=true,
     *  example="6,7,8"
     *      ),
     *      summary="This method is to delete business by id",
     *      description="This method is to delete business by id",
     *

     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */

    public function deleteBusinessesByIds(Request $request, $ids)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('business_delete')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $idsArray = explode(',', $ids);
            $existingIds = Business::whereIn('id', $idsArray)
            ->when(
               !request()->user()->hasRole('superadmin'),
                 function ($query)  {
                     $query->where(function ($query) {
                         $query
                         // ->where('id', auth()->user()->business_id)
                         // ->orWhere('created_by', auth()->user()->id)
                             ->orWhere('reseller_id', auth()->user()->id)
                             ;
                     });
                 },
             )
                ->select('id')
                ->get()
                ->pluck('id')
                ->toArray();
            $nonExistingIds = array_diff($idsArray, $existingIds);


            if (!empty($nonExistingIds)) {
                return response()->json([
                    "message" => "Some or all of the specified data do not exist.",
                    "non_existing_ids" => $nonExistingIds
                ], 404);
            }

            // Disable foreign key checks
            // DB::statement('SET FOREIGN_KEY_CHECKS=0');
            Business::whereIn('id', $existingIds)->delete();
            // Re-enable foreign key checks
            // DB::statement('SET FOREIGN_KEY_CHECKS=1');
            return response()->json(["message" => "data deleted sussfully", "deleted_ids" => $existingIds], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }







    /**
     *
     * @OA\Get(
     *      path="/v1.0/businesses/by-business-owner/all",
     *      operationId="getAllBusinessesByBusinessOwner",
     *      tags={"business_management"},

     *       security={
     *           {"bearerAuth": {}}
     *       },

     *      summary="This method is to get businesses",
     *      description="This method is to get businesses",
     *

     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */

    public function getAllBusinessesByBusinessOwner(Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasRole('business_owner')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $businessesQuery = Business::where([
                "owner_id" => $request->user()->id
            ]);



            $businesses = $businessesQuery->orderByDesc("id")->get();
            return response()->json($businesses, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
}
