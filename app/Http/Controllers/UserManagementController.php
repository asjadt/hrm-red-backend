<?php

namespace App\Http\Controllers;

use App\Exports\EmployeeSchedulesExport;
use App\Exports\UserExport;
use App\Exports\UsersExport;
use App\Http\Components\AttendanceComponent;
use App\Http\Components\HolidayComponent;
use App\Http\Components\LeaveComponent;
use App\Http\Components\ProjectComponent;
use App\Http\Components\UserManagementComponent;
use App\Http\Components\WorkLocationComponent;
use App\Http\Components\WorkShiftHistoryComponent;
use App\Http\Requests\AssignPermissionRequest;
use App\Http\Requests\AssignRoleRequest;
use App\Http\Requests\GuestUserRegisterRequest;
use App\Http\Requests\ImageUploadRequest;
use App\Http\Requests\UserCreateRequest;
use App\Http\Requests\GetIdRequest;
use App\Http\Requests\MultipleFileUploadRequest;
use App\Http\Requests\SingleFileUploadRequest;
use App\Http\Requests\UserCreateRecruitmentProcessRequest;
use App\Http\Requests\UserCreateV2Request;
use App\Http\Requests\UserPasswordUpdateRequest;
use App\Http\Requests\UserStoreDetailsRequest;
use App\Http\Requests\UserUpdateAddressRequest;
use App\Http\Requests\UserUpdateBankDetailsRequest;
use App\Http\Requests\UserUpdateEmergencyContactRequest;
use App\Http\Requests\UserUpdateJoiningDateRequest;
use App\Http\Requests\UserUpdateProfileRequest;
use App\Http\Requests\UserUpdateRecruitmentProcessRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Http\Requests\UserUpdateV2Request;
use App\Http\Requests\UserUpdateV3Request;
use App\Http\Requests\UserUpdateV4Request;
use App\Http\Utils\BasicUtil;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\EmailLogUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\ModuleUtil;
use App\Http\Utils\UserActivityUtil;
use App\Http\Utils\UserDetailsUtil;
use App\Mail\SendOriginalPassword;
use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\Business;
use App\Models\Department;
use App\Models\DepartmentUser;
use App\Models\EmployeeAddressHistory;
use App\Models\LeaveRecord;
use App\Models\Module;
use App\Models\Role;

use App\Models\User;
use App\Models\UserAssetHistory;
use Carbon\Carbon;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\File;
use PDF;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;


use Illuminate\Support\Facades\Mail;

// eeeeee
class UserManagementController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil, ModuleUtil, UserDetailsUtil,BasicUtil, EmailLogUtil;

    protected $workShiftHistoryComponent;
    protected $holidayComponent;
    protected $leaveComponent;
    protected $attendanceComponent;
    protected $userManagementComponent;
    protected $workLocationComponent;
    protected $projectComponent;

    public function __construct(WorkShiftHistoryComponent $workShiftHistoryComponent, HolidayComponent $holidayComponent,  LeaveComponent $leaveComponent, AttendanceComponent $attendanceComponent, UserManagementComponent $userManagementComponent, WorkLocationComponent $workLocationComponent, ProjectComponent $projectComponent)
    {

        $this->workShiftHistoryComponent = $workShiftHistoryComponent;
        $this->holidayComponent = $holidayComponent;
        $this->leaveComponent = $leaveComponent;
        $this->attendanceComponent = $attendanceComponent;
        $this->userManagementComponent = $userManagementComponent;
        $this->workLocationComponent = $workLocationComponent;
        $this->projectComponent = $projectComponent;


    }






    function generate_unique_username($firstName, $middleName, $lastName, $business_id = null)
    {
        $baseUsername = $firstName . "." . ($middleName ? $middleName . "." : "") . $lastName;
        $username = $baseUsername;
        $counter = 1;

        // Check if the generated username is already in use within the specified business
        while (User::where('user_name', $username)->where('business_id', $business_id)->exists()) {
            // If the username exists, append a counter to make it unique
            $username = $baseUsername . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     *
     * @OA\Post(
     *      path="/v1.0/users",
     *      operationId="createUser",
     *      tags={"user_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to store user",
     *      description="This method is to store user",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"first_Name","last_Name","email","password","password_confirmation","phone","address_line_1","address_line_2","country","city","postcode","role"},
     *             @OA\Property(property="first_Name", type="string", format="string",example="Rifat"),
     *      *            @OA\Property(property="middle_Name", type="string", format="string",example="Al"),
     *      *      *            @OA\Property(property="NI_number", type="string", format="string",example="drtjdjdj"),
     *
     *
     *            @OA\Property(property="last_Name", type="string", format="string",example="Al"),
     *
     *
     *              @OA\Property(property="gender", type="string", format="string",example="male"),
     *                @OA\Property(property="is_in_employee", type="boolean", format="boolean",example="1"),
     *               @OA\Property(property="designation_id", type="number", format="number",example="1"),

     *               @OA\Property(property="salary_per_annum", type="string", format="string",example="10"),
     *  *            @OA\Property(property="weekly_contractual_hours", type="string", format="string",example="10"),
     *               @OA\Property(property="minimum_working_days_per_week", type="string", format="string",example="5"),
     *     @OA\Property(property="overtime_rate", type="string", format="string",example="5"),
     *
     *
     *     @OA\Property(property="joining_date", type="string", format="date", example="2023-11-16"),
     *
     *            @OA\Property(property="email", type="string", format="string",example="rifatalashwad0@gmail.com"),
     *    *            @OA\Property(property="image", type="string", format="string",example="...png"),

     * *  @OA\Property(property="password", type="string", format="boolean",example="12345678"),
     *  * *  @OA\Property(property="password_confirmation", type="string", format="boolean",example="12345678"),
     *  * *  @OA\Property(property="phone", type="string", format="boolean",example="01771034383"),
     *  * *  @OA\Property(property="address_line_1", type="string", format="boolean",example="dhaka"),
     *  * *  @OA\Property(property="address_line_2", type="string", format="boolean",example="dinajpur"),
     *  * *  @OA\Property(property="country", type="string", format="boolean",example="Bangladesh"),
     *  * *  @OA\Property(property="city", type="string", format="boolean",example="Dhaka"),
     *  * *  @OA\Property(property="postcode", type="string", format="boolean",example="1207"),
     *     *  * *  @OA\Property(property="lat", type="string", format="boolean",example="1207"),
     *     *  * *  @OA\Property(property="long", type="string", format="boolean",example="1207"),
     *  *  * *  @OA\Property(property="role", type="string", format="boolean",example="customer"),

     *
     * *   @OA\Property(property="emergency_contact_details", type="string", format="array", example={})
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

    public function createUser(UserCreateRequest $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('user_create')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id = $request->user()->business_id;

            $request_data = $request->validated();

            return      DB::transaction(function () use ($request_data) {
                if (!auth()->user()->hasRole('superadmin') && $request_data["role"] == "superadmin") {

                    $error =  [
                        "message" => "You can not create superadmin.",
                    ];
                    throw new Exception(json_encode($error), 403);
                }


                $request_data['password'] = Hash::make($request_data['password']);
                $request_data['is_active'] = true;
                $request_data['remember_token'] = Str::random(10);


                if (!empty($business_id)) {
                    $request_data['business_id'] = $business_id;
                }


                $user =  User::create($request_data);
                $username = $this->generate_unique_username($user->first_Name, $user->middle_Name, $user->last_Name, $user->business_id);
                $user->user_name = $username;
                $user->email_verified_at = now();
                $user->save();
                $user->assignRole($request_data['role']);
                $user->roles = $user->roles->pluck('name');
                return response($user, 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }
    /**
     *
     * @OA\Post(
     *      path="/v2.0/users",
     *      operationId="createUserV2",
     *      tags={"user_management.employee"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to store user",
     *      description="This method is to store user",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"first_Name","last_Name","email","password","password_confirmation","phone","address_line_1","address_line_2","country","city","postcode","role"},
     *             @OA\Property(property="first_Name", type="string", format="string",example="Rifat"),
     *      *            @OA\Property(property="middle_Name", type="string", format="string",example="Al"),
     *     *      *      *            @OA\Property(property="NI_number", type="string", format="string",example="drtjdjdj"),
     *
     *            @OA\Property(property="last_Name", type="string", format="string",example="Al"),
     * *            @OA\Property(property="user_id", type="string", format="string",example="045674"),
     *
     *
     *              @OA\Property(property="gender", type="string", format="string",example="male"),
     *                @OA\Property(property="is_in_employee", type="boolean", format="boolean",example="1"),
     *               @OA\Property(property="designation_id", type="number", format="number",example="1"),
     *              @OA\Property(property="employment_status_id", type="number", format="number",example="1"),
     *               @OA\Property(property="salary_per_annum", type="string", format="string",example="10"),
     *  *  *               @OA\Property(property="weekly_contractual_hours", type="string", format="string",example="10"),
     * *  *  *               @OA\Property(property="minimum_working_days_per_week", type="string", format="string",example="5"),
     *   *     @OA\Property(property="overtime_rate", type="string", format="string",example="5"),
     *
     *     @OA\Property(property="joining_date", type="string", format="date", example="2023-11-16"),
     *
     *            @OA\Property(property="email", type="string", format="string",example="rifatalashwad0@gmail.com"),
     *    *            @OA\Property(property="image", type="string", format="string",example="...png"),

     * *  @OA\Property(property="password", type="string", format="boolean",example="12345678"),
     *  * *  @OA\Property(property="password_confirmation", type="string", format="boolean",example="12345678"),
     *  * *  @OA\Property(property="phone", type="string", format="boolean",example="01771034383"),
     *  * *  @OA\Property(property="address_line_1", type="string", format="boolean",example="dhaka"),
     *  * *  @OA\Property(property="address_line_2", type="string", format="boolean",example="dinajpur"),
     *  * *  @OA\Property(property="country", type="string", format="boolean",example="Bangladesh"),
     *  * *  @OA\Property(property="city", type="string", format="boolean",example="Dhaka"),
     *  * *  @OA\Property(property="postcode", type="string", format="boolean",example="1207"),
     *     *  * *  @OA\Property(property="lat", type="string", format="boolean",example="1207"),
     *     *  * *  @OA\Property(property="long", type="string", format="boolean",example="1207"),
     *  *  * *  @OA\Property(property="role", type="string", format="boolean",example="customer"),
     *      *  *  * *  @OA\Property(property="work_shift_id", type="number", format="number",example="1"),
     *  *     @OA\Property(property="work_location_ids", type="integer", format="int", example="1,1"),
     *
     *
     *
     * @OA\Property(property="recruitment_processes", type="string", format="array", example={
     * {
     * "recruitment_process_id":1,
     * "description":"description",
     * "attachments":{"/abcd.jpg","/efgh.jpg"}
     * },
     *      * {
     * "recruitment_process_id":1,
     * "description":"description",
     * "attachments":{"/abcd.jpg","/efgh.jpg"}
     * }
     *
     *
     *
     * }),
     *
     *      *  * @OA\Property(property="departments", type="string", format="array", example={1,2,3}),
     *
     * *   @OA\Property(property="emergency_contact_details", type="string", format="array", example={}),
     *
     *  *  * *  @OA\Property(property="immigration_status", type="string", format="string",example="british_citizen"),
     *         @OA\Property(property="is_active_visa_details", type="boolean", format="boolean",example="1"),
     *  *         @OA\Property(property="is_active_right_to_works", type="boolean", format="boolean",example="1"),
     *

     *     @OA\Property(property="sponsorship_details", type="string", format="string", example={
     *    "date_assigned": "2023-01-01",
     *    "expiry_date": "2024-01-01",
     *    "status": "pending",
     *  *    "note": "pending",
     *  *    "certificate_number": "pending note",
     *  *    "current_certificate_status": "pending",
     * *  *    "is_sponsorship_withdrawn": 1
     * }),
     *
     * *
     * *
     * *
     * *
     * *
     * *
     *
     *
     *       @OA\Property(property="visa_details", type="string", format="array", example={
     *      "BRP_number": "BRP123",
     *      "visa_issue_date": "2023-01-01",
     *      "visa_expiry_date": "2024-01-01",
     *      "place_of_issue": "City",
     *      "visa_docs": {
     *        {
     *          "file_name": "document1.pdf",
     *          "description": "Description 1"
     *        },
     *        {
     *  *          "file_name": "document2.pdf",
     *          "description": "Description 2"
     *        }
     *      }
     *
     * }
     * ),
     * *
     * @OA\Property(
     *     property="right_to_works",
     *     type="string",
     *     format="string",
     *     example={
     *         "right_to_work_code": "Code123",
     *         "right_to_work_check_date": "2023-01-01",
     *         "right_to_work_expiry_date": "2024-01-01",
     *         "right_to_work_docs": {
     *             {
     *                 "file_name": "document1.pdf",
     *                 "description": "Description 1"
     *             },
     *             {
     *                 "file_name": "document2.pdf",
     *                 "description": "Description 2"
     *             }
     *         }
     *     }
     * ),

     *
     *
     *
     *
     *
     *
     *
     *  *     @OA\Property(property="passport_details", type="string", format="string", example={
     *    "passport_number": "ABC123",
     *    "passport_issue_date": "2023-01-01",
     *    "passport_expiry_date": "2024-01-01",
     *    "place_of_issue": "City"
     *
     * })
     *
     *
     *

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

    public function createUserV2(UserCreateV2Request $request)
    {
        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            if (!$request->user()->hasPermissionTo('user_create')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id = $request->user()->business_id;

            $request_data = $request->validated();




            // $this->moveUploadedFiles(collect($request_data["recruitment_processes"])->pluck("attachments"),"recruitment_processes");
            //  $this->moveUploadedFiles(collect($request_data["right_to_works"]["right_to_work_docs"])->pluck("file_name"),"right_to_work_docs");
            //  $this->moveUploadedFiles(collect($request_data["visa_details"]["visa_docs"])->pluck("file_name"),"visa_docs");
            // throw new Exception("fff");
            // throw new Exception(json_encode(collect($request_data["recruitment_processes"])->pluck("attachments")));



            $request_data["recruitment_processes"] = $this->storeUploadedFiles($request_data["recruitment_processes"],"attachments","recruitment_processes",[]);
          $this->makeFilePermanent($request_data["recruitment_processes"],"attachments",[]);



            $request_data["right_to_works"]["right_to_work_docs"] = $this->storeUploadedFiles($request_data["right_to_works"]["right_to_work_docs"],"file_name","right_to_work_docs");
            $this->makeFilePermanent($request_data["right_to_works"]["right_to_work_docs"],"file_name");

            $request_data["visa_details"]["visa_docs"] = $this->storeUploadedFiles($request_data["visa_details"]["visa_docs"],"file_name","visa_docs");
            $this->makeFilePermanent($request_data["visa_details"]["visa_docs"],"file_name");




            if (!$request->user()->hasRole('superadmin') && $request_data["role"] == "superadmin") {

                $error =  [
                    "message" => "You can not create superadmin.",
                ];
                throw new Exception(json_encode($error), 403);
            }

            // $request_data['password'] = Hash::make($request['password']);

            $password = Str::random(11);
            $request_data['password'] = Hash::make($password);




            $request_data['is_active'] = true;
            $request_data['remember_token'] = Str::random(10);


            if (!empty($business_id)) {
                $request_data['business_id'] = $business_id;
            }


            $user =  User::create($request_data);
            $username = $this->generate_unique_username($user->first_Name, $user->middle_Name, $user->last_Name, $user->business_id);
            $user->user_name = $username;
            $token = Str::random(30);
            $user->resetPasswordToken = $token;
            $user->resetPasswordExpires = Carbon::now()->subDays(-1);
            $user->pension_eligible = 0;
            $user->save();
            $this->delete_old_histories();



            if (!empty($request_data['departments'])) {
                $user->departments()->sync($request_data['departments']);
                }





            // if (!empty($user->departments) && !empty($request_data['departments'][0])) {
            //     // Fetch the first department ID and user ID
            //     $departmentUser = DepartmentUser::where([
            //         'department_id' => $user->departments[0]->id,
            //         'user_id'       => $user->id
            //     ])->first();

            //     // Check if the DepartmentUser relationship exists
            //     if (!empty($departmentUser)) {
            //         // Update the department_id to the new department ID from the request data
            //         $departmentUser->update(['department_id' => $request_data['departments'][0]]);
            //     }
            // }




            $user->work_locations()->sync($request_data["work_location_ids"]);

            $user->assignRole($request_data['role']);


            if(!empty($request_data["work_shift_id"])) {
                $this->store_work_shift_history($request_data["work_shift_id"], $user);
            }



            $this->store_project($request_data, $user);

            $this->store_pension($request_data, $user);
            $this->store_recruitment_processes($request_data, $user);

            if (in_array($request["immigration_status"], ['sponsored'])) {
                $this->store_sponsorship_details($request_data, $user);
            }
            if (in_array($request["immigration_status"], ['immigrant', 'sponsored'])) {
                $this->store_passport_details($request_data, $user);
                $this->store_visa_details($request_data, $user);
            }
            if (in_array($request["immigration_status"], ['ilr', 'immigrant', 'sponsored'])) {
                $this->store_right_to_works($request_data, $user);
            }
            $user->roles = $user->roles->pluck('name');

            if (env("SEND_EMAIL") == true) {
                $this->checkEmailSender($user->id,0);

                Mail::to($user->email)->send(new SendOriginalPassword($user, $password));

                $this->storeEmailSender($user->id,0);

            }





            DB::commit();
            return response($user, 201);
        } catch (Exception $e) {

            DB::rollBack();

            try {
                $this->moveUploadedFilesBack($request_data["recruitment_processes"], "attachments", "recruitment_processes", []);
            } catch (Exception $innerException) {
                error_log("Failed to move recruitment processes files back: " . $innerException->getMessage());
            }

            try {
                $this->moveUploadedFilesBack($request_data["right_to_works"]["right_to_work_docs"], "file_name", "right_to_work_docs");
            } catch (Exception $innerException) {
                error_log("Failed to move right to work docs back: " . $innerException->getMessage());
            }

            try {
                $this->moveUploadedFilesBack($request_data["visa_details"]["visa_docs"], "file_name", "visa_docs");
            } catch (Exception $innerException) {
                error_log("Failed to move visa docs back: " . $innerException->getMessage());
            }



            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }





    }



    /**
     *
     * @OA\Put(
     *      path="/v1.0/users",
     *      operationId="updateUser",
     *      tags={"user_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update user",
     *      description="This method is to update user",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"id","first_Name","last_Name","email","password","password_confirmation","phone","address_line_1","address_line_2","country","city","postcode","role"},
     *           @OA\Property(property="id", type="string", format="number",example="1"),
     *             @OA\Property(property="first_Name", type="string", format="string",example="Rifat"),
     *   *            @OA\Property(property="middle_Name", type="string", format="string",example="How was this?"),
     *     *      *      *            @OA\Property(property="NI_number", type="string", format="string",example="drtjdjdj"),
     *            @OA\Property(property="last_Name", type="string", format="string",example="How was this?"),
     *
     *
     *      * *            @OA\Property(property="user_id", type="string", format="string",example="045674"),
     *            @OA\Property(property="email", type="string", format="string",example="How was this?"),
     *    *    *            @OA\Property(property="image", type="string", format="string",example="...png"),
     *                @OA\Property(property="gender", type="string", format="string",example="male"),
     *                @OA\Property(property="is_in_employee", type="boolean", format="boolean",example="1"),
     *               @OA\Property(property="designation_id", type="number", format="number",example="1"),
     *              @OA\Property(property="employment_status_id", type="number", format="number",example="1"),
     *               @OA\Property(property="salary_per_annum", type="string", format="string",example="10"),
     *           @OA\Property(property="weekly_contractual_hours", type="string", format="string",example="10"),
     *     *           @OA\Property(property="minimum_working_days_per_week", type="string", format="string",example="10"),
     *   *     @OA\Property(property="overtime_rate", type="string", format="string",example="5"),
     *
     *     @OA\Property(property="joining_date", type="string", format="date", example="2023-11-16"),

     * *  @OA\Property(property="password", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="password_confirmation", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="phone", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="address_line_1", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="address_line_2", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="country", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="city", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="postcode", type="boolean", format="boolean",example="1"),
     *     *     *  * *  @OA\Property(property="lat", type="string", format="boolean",example="1207"),
     *     *  * *  @OA\Property(property="long", type="string", format="boolean",example="1207"),
     *  *  * *  @OA\Property(property="role", type="boolean", format="boolean",example="customer"),
     *      *      *  *  * *  @OA\Property(property="work_shift_id", type="number", format="number",example="1"),
     *      *  * @OA\Property(property="departments", type="string", format="array", example={1,2,3}),
     * * *   @OA\Property(property="emergency_contact_details", type="string", format="array", example={})
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

    public function updateUser(UserUpdateRequest $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('user_update')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $request_data = $request->validated();



            $userQuery = User::where([
                "id" => $request["id"]
            ]);
            $updatableUser = $userQuery->first();
            if ($updatableUser->hasRole("superadmin") && $request["role"] != "superadmin") {
                return response()->json([
                    "message" => "You can not change the role of super admin"
                ], 401);
            }
            if (!$request->user()->hasRole('superadmin') && $updatableUser->business_id != $request->user()->business_id && $updatableUser->created_by != $request->user()->id) {
                return response()->json([
                    "message" => "You can not update this user"
                ], 401);
            }


            if (!empty($request_data['password'])) {
                $request_data['password'] = Hash::make($request_data['password']);
            } else {
                unset($request_data['password']);
            }
            $request_data['is_active'] = true;
            $request_data['remember_token'] = Str::random(10);
            $userQueryTerms = [
                "id" => $request_data["id"],
            ];

            if(!empty($request_data["joining_date"])) {
                $this->validateJoiningDate($request_data["joining_date"], $request_data["id"]);
            }

            $user = User::where($userQueryTerms)->first();

            if ($user) {
                $user->fill(collect($request_data)->only([
                    'first_Name',
                    'middle_Name',
                    'NI_number',
                    'last_Name',
                    "email",
                    'user_id',
                    'password',
                    'phone',
                    'address_line_1',
                    'address_line_2',
                    'country',
                    'city',
                    'postcode',
                    "lat",
                    "long",
                    "image",
                    'gender',

                    // 'is_in_employee',

                    'designation_id',
                    'employment_status_id',
                    'joining_date',
                    'emergency_contact_details',
                    'salary_per_annum',
                    'weekly_contractual_hours',
                    'minimum_working_days_per_week',
                    'overtime_rate',
                ])->toArray());

                $user->save();
            }
            if (!$user) {

                return response()->json([
                    "message" => "no user found"
                ], 404);
            }
            $user->syncRoles([$request_data['role']]);



            $user->roles = $user->roles->pluck('name');


            return response($user, 201);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }



/**
     *
     * @OA\Put(
     *      path="/v1.0/users/update-password",
     *      operationId="updatePassword",
     *      tags={"user_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update user",
     *      description="This method is to update user",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"id","first_Name","last_Name","email","password","password_confirmation","phone","address_line_1","address_line_2","country","city","postcode","role"},
     *           @OA\Property(property="id", type="string", format="number",example="1"),
     *             @OA\Property(property="password", type="string", format="string",example="Rifat"),


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

     public function updatePassword(UserPasswordUpdateRequest $request)
     {

         try {
             $this->storeActivity($request, "DUMMY activity", "DUMMY description");
             if (!$request->user()->hasPermissionTo('user_update')) {
                 return response()->json([
                     "message" => "You can not perform this action"
                 ], 401);
             }
             $request_data = $request->validated();



             $updatableUser = User::where([
                 "id" => $request["id"]
             ])->first();


             if (!$updatableUser) {
                 return response()->json([
                     "message" => "no user found"
                 ], 404);
             }




             if (empty(auth()->user()->business_id)) {
                 if (empty($updatableUser->business_id)) {
                     if (!auth()->user()->hasRole("superadmin")) {
                         throw new Exception("you can not update this user's password", 401);
                     }
                 } else {
                     $business = Business::where([
                         "id" => $updatableUser->business_id
                     ])
                         ->first();

                     if ($business->reseller_id !== auth()->user()->id && !auth()->user()->hasRole("superadmin")) {
                         throw new Exception("you can not update this user's password", 401);
                     }
                 }
             } else {
                 $all_manager_department_ids = $this->get_all_departments_of_manager();

                 $verifiedUser = User::where([
                     "id" => $updatableUser->id
                 ])
                     ->whereHas("department_user.department", function ($query) use ($all_manager_department_ids) {
                         $query->whereIn("departments.id", $all_manager_department_ids);
                     })
                     ->first();

                 if (empty($verifiedUser)) {
                     throw new Exception("you can not update this user's password dd", 401);
                 }
             }


             if (!empty($request_data['password'])) {
                 $request_data['password'] = Hash::make($request_data['password']);
             } else {
                 unset($request_data['password']);
             }

             if ($updatableUser) {
                 $updatableUser->fill(collect($request_data)->only([
                     'password',
                 ])->toArray());

                 $updatableUser->save();
             }

             $updatableUser->roles = $updatableUser->roles->pluck('name');

             return response($updatableUser, 201);
         } catch (Exception $e) {
             error_log($e->getMessage());
             return $this->sendError($e, 500, $request);
         }
     }



    /**
     *
     * @OA\Put(
     *      path="/v1.0/users/assign-roles",
     *      operationId="assignUserRole",
     *      tags={"user_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update user",
     *      description="This method is to update user",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"id","first_Name","last_Name","email","password","password_confirmation","phone","address_line_1","address_line_2","country","city","postcode","role"},
     *           @OA\Property(property="id", type="string", format="number",example="1"),
     *
     *  *  * *  @OA\Property(property="roles", type="string", format="array",example={"business_owner#1","business_admin#1"})

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

    public function assignUserRole(AssignRoleRequest $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('user_update')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $request_data = $request->validated();



            $userQuery = User::where([
                "id" => $request["id"]
            ]);
            $user = $userQuery->first();

            if (!$user) {

                return response()->json([
                    "message" => "no user found"
                ], 404);
            }


            foreach ($request_data["roles"] as $role) {
                if ($user->hasRole("superadmin") && $role != "superadmin") {
                    return response()->json([
                        "message" => "You can not change the role of super admin"
                    ], 401);
                }
                if (!$request->user()->hasRole('superadmin') && $user->business_id != $request->user()->business_id && $user->created_by != $request->user()->id) {
                    return response()->json([
                        "message" => "You can not update this user"
                    ], 401);
                }
            }

            $roles = Role::whereIn('name', $request_data["roles"])->get();

            $user->syncRoles($roles);



            $user->roles = $user->roles->pluck('name');


            return response($user, 201);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }




    /**
     *
     * @OA\Put(
     *      path="/v1.0/users/assign-permissions",
     *      operationId="assignUserPermission",
     *      tags={"user_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update user",
     *      description="This method is to update user",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"id","first_Name","last_Name","email","password","password_confirmation","phone","address_line_1","address_line_2","country","city","postcode","role"},
     *           @OA\Property(property="id", type="string", format="number",example="1"),
     *
     *  *  * *  @OA\Property(property="permissions", type="string", format="array",example={"business_owner","business_admin"})

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

    public function assignUserPermission(AssignPermissionRequest $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            if (!$request->user()->hasRole('superadmin')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $request_data = $request->validated();

            $userQuery = User::where([
                "id" => $request["id"]
            ]);
            $user = $userQuery->first();

            if (!$user) {

                return response()->json([
                    "message" => "no user found"
                ], 404);
            }


            foreach ($request_data["permissions"] as $role) {
                if ($user->hasRole("superadmin") && $role != "superadmin") {
                    return response()->json([
                        "message" => "You can not change the role of super admin"
                    ], 401);
                }
                if (!$request->user()->hasRole('superadmin') && $user->business_id != $request->user()->business_id && $user->created_by != $request->user()->id) {
                    return response()->json([
                        "message" => "You can not update this user"
                    ], 401);
                }
            }


            $permissions = Permission::whereIn('name', $request_data["permissions"])->get();
            $user->givePermissionTo($permissions);



            return response($user, 201);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }

    /**
     *
     * @OA\Put(
     *      path="/v2.0/users",
     *      operationId="updateUserV2",
     *      tags={"user_management.employee"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update user",
     *      description="This method is to update user",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"id","first_Name","last_Name","email","password","password_confirmation","phone","address_line_1","address_line_2","country","city","postcode","role"},
     *           @OA\Property(property="id", type="string", format="number",example="1"),
     *             @OA\Property(property="first_Name", type="string", format="string",example="Rifat"),
     *   *            @OA\Property(property="middle_Name", type="string", format="string",example="How was this?"),
     *     *      *      *            @OA\Property(property="NI_number", type="string", format="string",example="drtjdjdj"),
     *            @OA\Property(property="last_Name", type="string", format="string",example="How was this?"),
     *
     *
     *      * *            @OA\Property(property="user_id", type="string", format="string",example="045674"),
     *            @OA\Property(property="email", type="string", format="string",example="How was this?"),
     *    *    *            @OA\Property(property="image", type="string", format="string",example="...png"),
     *                @OA\Property(property="gender", type="string", format="string",example="male"),
     *                @OA\Property(property="is_in_employee", type="boolean", format="boolean",example="1"),
     *               @OA\Property(property="designation_id", type="number", format="number",example="1"),
     *              @OA\Property(property="employment_status_id", type="number", format="number",example="1"),
     *               @OA\Property(property="salary_per_annum", type="string", format="string",example="10"),
     *           @OA\Property(property="weekly_contractual_hours", type="string", format="string",example="10"),
     *      *           @OA\Property(property="minimum_working_days_per_week", type="string", format="string",example="5"),
     *   *     @OA\Property(property="overtime_rate", type="string", format="string",example="5"),
     *
     *     @OA\Property(property="joining_date", type="string", format="date", example="2023-11-16"),

     * *  @OA\Property(property="password", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="password_confirmation", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="phone", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="address_line_1", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="address_line_2", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="country", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="city", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="postcode", type="boolean", format="boolean",example="1"),
     *     *     *  * *  @OA\Property(property="lat", type="string", format="boolean",example="1207"),
     *     *  * *  @OA\Property(property="long", type="string", format="boolean",example="1207"),
     *  *  * *  @OA\Property(property="role", type="boolean", format="boolean",example="customer"),
     *      *      *  *  * *  @OA\Property(property="work_shift_id", type="number", format="number",example="1"),
     *  *     @OA\Property(property="work_location_ids", type="integer", format="int", example="1"),
     * *         @OA\Property(property="is_active_visa_details", type="boolean", format="boolean",example="1"),
     *  * *         @OA\Property(property="is_active_right_to_works", type="boolean", format="boolean",example="1"),
     *     * @OA\Property(property="recruitment_processes", type="string", format="array", example={
     * {
     * "recruitment_process_id":1,
     * "description":"description",
     * "attachments":{"/abcd.jpg","/efgh.jpg"}
     * },
     *      * {
     * "recruitment_process_id":1,
     * "description":"description",
     * "attachments":{"/abcd.jpg","/efgh.jpg"}
     * }
     *
     *
     *
     * }),
     *      *  * @OA\Property(property="departments", type="string", format="array", example={1,2,3}),
     * * *   @OA\Property(property="emergency_contact_details", type="string", format="array", example={})
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

    public function updateUserV2(UserUpdateV2Request $request)
    {
        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");


            if (!$request->user()->hasPermissionTo('user_update')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $request_data = $request->validated();
            $userQuery = User::where([
                "id" => $request["id"]
            ]);
            $updatableUser = $userQuery->first();
            if ($updatableUser->hasRole("superadmin") && $request["role"] != "superadmin") {
                return response()->json([
                    "message" => "You can not change the role of super admin"
                ], 401);
            }
            if (!$request->user()->hasRole('superadmin') && $updatableUser->business_id != $request->user()->business_id && $updatableUser->created_by != $request->user()->id) {
                return response()->json([
                    "message" => "You can not update this user"
                ], 401);
            }


            if (!empty($request_data['password'])) {
                $request_data['password'] = Hash::make($request_data['password']);
            } else {
                unset($request_data['password']);
            }



            $request_data["recruitment_processes"] = $this->storeUploadedFiles($request_data["recruitment_processes"],"attachments","recruitment_processes",[]);
            $this->makeFilePermanent($request_data["recruitment_processes"],"attachments",[]);


            $request_data["right_to_works"]["right_to_work_docs"] = $this->storeUploadedFiles($request_data["right_to_works"]["right_to_work_docs"],"file_name","right_to_work_docs");
            $this->makeFilePermanent($request_data["right_to_works"]["right_to_work_docs"],"file_name");

            $request_data["visa_details"]["visa_docs"] = $this->storeUploadedFiles($request_data["visa_details"]["visa_docs"],"file_name","visa_docs");
            $this->makeFilePermanent($request_data["visa_details"]["visa_docs"],"file_name");



            $request_data['is_active'] = true;
            $request_data['remember_token'] = Str::random(10);





            $userQueryTerms = [
                "id" => $request_data["id"],
            ];


            if(!empty($request_data["joining_date"])) {
                $this->validateJoiningDate($request_data["joining_date"], $request_data["id"]);
            }

            $user = User::where($userQueryTerms)->first();

            if ($user) {
                $user->fill(collect($request_data)->only([
                    'first_Name',
                    'last_Name',
                    'middle_Name',
                    "NI_number",

                    "email",
                    "color_theme_name",
                    'emergency_contact_details',
                    'gender',

                    // 'is_in_employee',

                    'designation_id',
                    'employment_status_id',
                    'joining_date',
                    "date_of_birth",
                    'salary_per_annum',
                    'weekly_contractual_hours',
                    'minimum_working_days_per_week',
                    'overtime_rate',
                    'phone',
                    'image',
                    'address_line_1',
                    'address_line_2',
                    'country',
                    'city',
                    'postcode',
                    "lat",
                    "long",
                    'is_active_visa_details',
                    "is_active_right_to_works",
                    'is_sponsorship_offered',
                    "immigration_status",


                ])->toArray());

                $user->save();
            }
            if (!$user) {

                return response()->json([
                    "message" => "no user found"
                ], 404);
            }

            $this->delete_old_histories();


            if (!empty($request_data['departments'])) {
                $user->departments()->sync($request_data['departments']);
                }


            // if (!empty($user->departments) && !empty($request_data['departments'][0])) {
            //     // Fetch the first department ID and user ID
            //     $departmentUser = DepartmentUser::where([
            //         'department_id' => $user->departments[0]->id,
            //         'user_id'       => $user->id
            //     ])->first();

            //     // Check if the DepartmentUser relationship exists
            //     if (!empty($departmentUser)) {
            //         // Update the department_id to the new department ID from the request data
            //         $departmentUser->update(['department_id' => $request_data['departments'][0]]);
            //     }
            // }


            // // Get the user's departments
            // $departments = $user->departments->pluck("id");


            // // Remove the first department from the collection
            // $removedDepartment = $departments->shift();

            // // Insert the department from $request_data at the beginning of the collection
            // $departments->prepend($request_data['departments'][0]);

            // // Update the user's departments
            // $user->departments()->sync($departments);

            $user->work_locations()->sync($request_data["work_location_ids"]);

            $user->syncRoles([$request_data['role']]);

             if(!empty($request_data["work_shift_id"])) {
                $this->update_work_shift_history($request_data["work_shift_id"], $user);
            }


            $this->update_address_history($request_data, $user);
            $this->update_recruitment_processes($request_data, $user);



            if (in_array($request["immigration_status"], ['sponsored'])) {
                $this->update_sponsorship($request_data, $user);
            }


            if (in_array($request["immigration_status"], ['immigrant', 'sponsored'])) {
                $this->update_passport_details($request_data, $user);
                $this->update_visa_details($request_data, $user);
            }

            if (in_array($request["immigration_status"], ['ilr', 'immigrant', 'sponsored'])) {
                $this->update_right_to_works($request_data, $user);
            }

            $user->roles = $user->roles->pluck('name');









            // $this->moveUploadedFiles(collect($request_data["recruitment_processes"])->pluck("attachments"),"recruitment_processes");

            // $this->moveUploadedFiles(collect($request_data["right_to_works"]["right_to_work_docs"])->pluck("file_name"),"right_to_works");

            // $this->moveUploadedFiles(collect($request_data["visa_details"]["visa_docs"])->pluck("file_name"),"visa_details");













            DB::commit();
            return response($user, 201);
        } catch (Exception $e) {
            DB::rollBack();


            return $this->sendError($e, 500, $request);
        }
    }

    /**
     *
     * @OA\Put(
     *      path="/v3.0/users",
     *      operationId="updateUserV3",
     *      tags={"user_management.employee"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update user",
     *      description="This method is to update user",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"id","first_Name","last_Name","email","password","password_confirmation","phone","address_line_1","address_line_2","country","city","postcode","role"},
     *           @OA\Property(property="id", type="string", format="number",example="1"),
     *             @OA\Property(property="first_Name", type="string", format="string",example="Rifat"),
     *   *            @OA\Property(property="middle_Name", type="string", format="string",example="How was this?"),
     *     *      *      *            @OA\Property(property="NI_number", type="string", format="string",example="drtjdjdj"),
     *            @OA\Property(property="last_Name", type="string", format="string",example="How was this?"),
     *
     *

     *            @OA\Property(property="email", type="string", format="string",example="How was this?"),

     *                @OA\Property(property="gender", type="string", format="string",example="male"),

     *               @OA\Property(property="designation_id", type="number", format="number",example="1"),
     *              @OA\Property(property="employment_status_id", type="number", format="number",example="1"),
     *               @OA\Property(property="salary_per_annum", type="string", format="string",example="10"),
     *           @OA\Property(property="weekly_contractual_hours", type="string", format="string",example="10"),
     *      *           @OA\Property(property="minimum_working_days_per_week", type="string", format="string",example="5"),
     *   *     @OA\Property(property="overtime_rate", type="string", format="string",example="5"),
     *
     *     @OA\Property(property="joining_date", type="string", format="date", example="2023-11-16"),


     *  * *  @OA\Property(property="phone", type="boolean", format="boolean",example="1"),

     *      *      *  *  * *  @OA\Property(property="work_shift_id", type="number", format="number",example="1"),
     *  *     @OA\Property(property="work_location_ids", type="integer", format="int", example="1,2"),

     *      *  * @OA\Property(property="departments", type="string", format="array", example={1,2,3}),

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

    public function updateUserV3(UserUpdateV3Request $request)
    {
        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");


            if (!$request->user()->hasPermissionTo('user_update')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $request_data = $request->validated();
            $userQuery = User::where([
                "id" => $request["id"]
            ]);
            $updatableUser = $userQuery->first();
            if ($updatableUser->hasRole("superadmin") && $request["role"] != "superadmin") {
                return response()->json([
                    "message" => "You can not change the role of super admin"
                ], 401);
            }
            if (!$request->user()->hasRole('superadmin') && $updatableUser->business_id != $request->user()->business_id && $updatableUser->created_by != $request->user()->id) {
                return response()->json([
                    "message" => "You can not update this user"
                ], 401);
            }


            if (!empty($request_data['password'])) {
                $request_data['password'] = Hash::make($request_data['password']);
            } else {
                unset($request_data['password']);
            }
            $request_data['is_active'] = true;
            $request_data['remember_token'] = Str::random(10);





            $userQueryTerms = [
                "id" => $request_data["id"],
            ];



            if(!empty($request_data["joining_date"])) {
                $this->validateJoiningDate($request_data["joining_date"], $request_data["id"]);
            }



            $user = User::where($userQueryTerms)->first();

            if ($user) {
                $user->fill(collect($request_data)->only([
                    'first_Name',
                    'last_Name',
                    'middle_Name',
                    "NI_number",
                    "email",
                    'gender',

                    'designation_id',
                    'employment_status_id',
                    'joining_date',
                    "date_of_birth",
                    'salary_per_annum',
                    'weekly_contractual_hours',
                    'minimum_working_days_per_week',
                    'overtime_rate',
                    'phone',
                    'image'



                ])->toArray());

                $user->save();
            }
            if (!$user) {

                return response()->json([
                    "message" => "no user found"
                ], 404);
            }


            if (!empty($request_data['departments'])) {
            $user->departments()->sync($request_data['departments']);
            }




            // if (!empty($user->departments) && !empty($request_data['departments'][0])) {

                // // Fetch the first department ID and user ID
                // $departmentUser = DepartmentUser::where([
                //     'department_id' => $user->departments[0]->id,
                //     'user_id'       => $user->id
                // ])->first();

                // // Check if the DepartmentUser relationship exists
                // if (!empty($departmentUser)) {
                //     // Update the department_id to the new department ID from the request data
                //     $departmentUser->update(['department_id' => $request_data['departments'][0]]);
                // }
            // }
            // // Get the user's departments
            // $departments = $user->departments->pluck("id");

            // // Remove the first department from the collection
            // $removedDepartment = $departments->shift();

            // // Insert the department from $request_data at the beginning of the collection
            // $departments->prepend($request_data['departments'][0]);

            // // Update the user's departments
            // $user->departments()->sync($departments);

            $user->work_locations()->sync($request_data["work_location_ids"]);


            $this->update_work_shift($request_data, $user);



            DB::commit();
            return response($user, 201);
        } catch (Exception $e) {
            DB::rollBack();


            return $this->sendError($e, 500, $request);
        }
    }

      /**
     *
     * @OA\Put(
     *      path="/v4.0/users",
     *      operationId="updateUserV4",
     *      tags={"user_management.employee"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update user",
     *      description="This method is to update user",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"id","first_Name","last_Name","email","password","password_confirmation","phone","address_line_1","address_line_2","country","city","postcode","role"},
     *           @OA\Property(property="id", type="string", format="number",example="1"),
     *             @OA\Property(property="first_Name", type="string", format="string",example="Rifat"),
     *   *            @OA\Property(property="middle_Name", type="string", format="string",example="How was this?"),
     *     *      *      *            @OA\Property(property="NI_number", type="string", format="string",example="drtjdjdj"),
     *            @OA\Property(property="last_Name", type="string", format="string",example="How was this?"),
     *
     *

     *            @OA\Property(property="email", type="string", format="string",example="How was this?"),

     *                @OA\Property(property="gender", type="string", format="string",example="male"),


     *  * *  @OA\Property(property="phone", type="boolean", format="boolean",example="1"),

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

     public function updateUserV4(UserUpdateV4Request $request)
     {
         DB::beginTransaction();
         try {
             $this->storeActivity($request, "DUMMY activity", "DUMMY description");


             if (!$request->user()->hasPermissionTo('user_update')) {
                 return response()->json([
                     "message" => "You can not perform this action"
                 ], 401);
             }
             $request_data = $request->validated();
             $userQuery = User::where([
                 "id" => $request["id"]
             ]);
             $updatableUser = $userQuery->first();
             if ($updatableUser->hasRole("superadmin") && $request["role"] != "superadmin") {
                 return response()->json([
                     "message" => "You can not change the role of super admin"
                 ], 401);
             }
             if (!$request->user()->hasRole('superadmin') && $updatableUser->business_id != $request->user()->business_id && $updatableUser->created_by != $request->user()->id) {
                 return response()->json([
                     "message" => "You can not update this user"
                 ], 401);
             }


             if (!empty($request_data['password'])) {
                 $request_data['password'] = Hash::make($request_data['password']);
             } else {
                 unset($request_data['password']);
             }
             $request_data['is_active'] = true;
             $request_data['remember_token'] = Str::random(10);





             $userQueryTerms = [
                 "id" => $request_data["id"],
             ];






             $user = User::where($userQueryTerms)->first();

             if ($user) {
                 $user->fill(collect($request_data)->only([
                     'first_Name',
                     'last_Name',
                     'middle_Name',
                     "NI_number",
                     "email",
                     'gender',

                     'designation_id',
                     'employment_status_id',
                     'joining_date',
                     "date_of_birth",
                     'salary_per_annum',
                     'weekly_contractual_hours',
                     'minimum_working_days_per_week',
                     'overtime_rate',
                     'phone',



                 ])->toArray());

                 $user->save();
             }
             if (!$user) {

                 return response()->json([
                     "message" => "no user found"
                 ], 404);
             }





            //  if (!empty($user->departments) && !empty($request_data['departments'][0])) {
            //     // Fetch the first department ID and user ID
            //     $departmentUser = DepartmentUser::where([
            //         'department_id' => $user->departments[0]->id,
            //         'user_id'       => $user->id
            //     ])->first();

            //     // Check if the DepartmentUser relationship exists
            //     if (!empty($departmentUser)) {
            //         // Update the department_id to the new department ID from the request data
            //         $departmentUser->update(['department_id' => $request_data['departments'][0]]);
            //     }
            // }

            //  // Get the user's departments
            //  $departments = $user->departments->pluck("id");

            //  // Remove the first department from the collection
            //  $removedDepartment = $departments->shift();

            //  // Insert the department from $request_data at the beginning of the collection
            //  $departments->prepend($request_data['departments'][0]);

            //  // Update the user's departments
            //  $user->departments()->sync($departments);








             DB::commit();
             return response($user, 201);
         } catch (Exception $e) {
             DB::rollBack();


             return $this->sendError($e, 500, $request);
         }
     }




    /**
     *
     * @OA\Put(
     *      path="/v1.0/users/update-address",
     *      operationId="updateUserAddress",
     *      tags={"user_management.employee"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update user address",
     *      description="This method is to update user address",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(

     *           @OA\Property(property="id", type="string", format="number",example="1"),

     *

     *  * *  @OA\Property(property="phone", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="address_line_1", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="address_line_2", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="country", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="city", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="postcode", type="boolean", format="boolean",example="1"),
     *     *     *  * *  @OA\Property(property="lat", type="string", format="boolean",example="1207"),
     *     *  * *  @OA\Property(property="long", type="string", format="boolean",example="1207"),

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

    public function updateUserAddress(UserUpdateAddressRequest $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('user_update')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $request_data = $request->validated();



            $userQuery = User::where([
                "id" => $request["id"]
            ]);
            $updatableUser = $userQuery->first();
            if ($updatableUser->hasRole("superadmin") && $request["role"] != "superadmin") {
                return response()->json([
                    "message" => "You can not change the role of super admin"
                ], 401);
            }
            if (!$request->user()->hasRole('superadmin') && $updatableUser->business_id != $request->user()->business_id && $updatableUser->created_by != $request->user()->id) {
                return response()->json([
                    "message" => "You can not update this user"
                ], 401);
            }



            $user_query  = User::where([
                "id" => $request_data["id"],
            ]);




            $user  =  tap($user_query)->update(
                collect($request_data)->only([
                    'phone',
                    'address_line_1',
                    'address_line_2',
                    'country',
                    'city',
                    'postcode',
                    'lat',
                    'long',

                ])->toArray()
            )
                // ->with("somthing")
                ->first();
            if (!$user) {

                return response()->json([
                    "message" => "no user found"
                ], 404);
            }

            // history section

            $address_history_data = [
                'user_id' => $user->id,
                'from_date' => now(),
                'created_by' => $request->user()->id,
                'address_line_1' => $request_data["address_line_1"],
                'address_line_2' => $request_data["address_line_2"],
                'country' => $request_data["country"],
                'city' => $request_data["city"],
                'postcode' => $request_data["postcode"],
                'lat' => $request_data["lat"],
                'long' => $request_data["long"]
            ];

            $employee_address_history  =  EmployeeAddressHistory::where([
                "user_id" =>   $updatableUser->id,
                "to_date" => NULL
            ])
                ->latest('created_at')
                ->first();

            if ($employee_address_history) {
                $fields_to_check = ["address_line_1", "address_line_2", "country", "city", "postcode"];


                $fields_changed = false; // Initialize to false
                foreach ($fields_to_check as $field) {
                    $value1 = $employee_address_history->$field;
                    $value2 = $request_data[$field];

                    if ($value1 !== $value2) {
                        $fields_changed = true;
                        break;
                    }
                }





                if (
                    $fields_changed
                ) {
                    $employee_address_history->to_date = now();
                    $employee_address_history->save();
                    EmployeeAddressHistory::create($address_history_data);
                }
            } else {
                EmployeeAddressHistory::create($address_history_data);
            }

            // end history section


            return response($user, 201);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }




    /**
     *
     * @OA\Put(
     *      path="/v1.0/users/update-bank-details",
     *      operationId="updateUserBankDetails",
     *      tags={"user_management.employee"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update user address",
     *      description="This method is to update user address",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(

     *           @OA\Property(property="id", type="string", format="number",example="1"),
     *  * *  @OA\Property(property="bank_id", type="number", format="number",example="1"),
     *  * *  @OA\Property(property="sort_code", type="string", format="string",example="sort_code"),
     *  * *  @OA\Property(property="account_number", type="string", format="string",example="account_number"),
     *  * *  @OA\Property(property="account_name", type="string", format="string",example="account_name")
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

    public function updateUserBankDetails(UserUpdateBankDetailsRequest $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('user_update')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $request_data = $request->validated();



            $userQuery = User::where([
                "id" => $request["id"]
            ]);
            $updatableUser = $userQuery->first();
            if ($updatableUser->hasRole("superadmin") && $request["role"] != "superadmin") {
                return response()->json([
                    "message" => "You can not change the role of super admin"
                ], 401);
            }
            if (!$request->user()->hasRole('superadmin') && $updatableUser->business_id != $request->user()->business_id && $updatableUser->created_by != $request->user()->id) {
                return response()->json([
                    "message" => "You can not update this user"
                ], 401);
            }



            $user_query  = User::where([
                "id" => $request_data["id"],
            ]);




            $user  =  tap($user_query)->update(
                collect($request_data)->only([
                    'bank_id',
                    'sort_code',
                    'account_number',
                    'account_name',
                ])->toArray()
            )
                 ->with("bank")
                ->first();
            if (!$user) {

                return response()->json([
                    "message" => "no user found"
                ], 404);
            }








            return response($user, 201);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }
    /**
     *
     * @OA\Put(
     *      path="/v1.0/users/update-joining-date",
     *      operationId="updateUserJoiningDate",
     *      tags={"user_management.employee"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update user address",
     *      description="This method is to update user address",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(

     *  @OA\Property(property="id", type="string", format="number",example="1"),
     *  @OA\Property(property="joining_date", type="string", format="string",example="joining_date")
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

    public function updateUserJoiningDate(UserUpdateJoiningDateRequest $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('user_update')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $request_data = $request->validated();

            $userQuery = User::where([
                "id" => $request["id"]
            ]);

            $updatableUser = $userQuery->first();
            if ($updatableUser->hasRole("superadmin") && $request["role"] != "superadmin") {
                return response()->json([
                    "message" => "You can not change the role of super admin"
                ], 401);
            }

            if (!$request->user()->hasRole('superadmin') && $updatableUser->business_id != $request->user()->business_id && $updatableUser->created_by != $request->user()->id) {
                return response()->json([
                    "message" => "You can not update this user"
                ], 401);
            }

            $user_query  = User::where([
                "id" => $request_data["id"],
            ]);




            if(!empty($request_data["joining_date"])) {
                $this->validateJoiningDate($request_data["joining_date"], $request_data["id"]);
            }


            $user = tap($user_query)->update(
                collect($request_data)->only([
                    'joining_date'
                ])->toArray()
            )
                // ->with("somthing")
                ->first();


            if (!$user) {
                return response()->json([
                    "message" => "no user found"
                ], 404);
            }



            return response($user, 201);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }

    /**
     *
     * @OA\Put(
     *      path="/v1.0/users/update-emergency-contact",
     *      operationId="updateEmergencyContact",
     *      tags={"user_management.employee"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update user contact",
     *      description="This method is to update contact",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(

     *           @OA\Property(property="id", type="string", format="number",example="1"),

     * * *   @OA\Property(property="emergency_contact_details", type="string", format="array", example={})

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

    public function updateEmergencyContact(UserUpdateEmergencyContactRequest $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('user_update')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $request_data = $request->validated();



            $userQuery = User::where([
                "id" => $request["id"]
            ]);
            $updatableUser = $userQuery->first();
            if ($updatableUser->hasRole("superadmin") && $request["role"] != "superadmin") {
                return response()->json([
                    "message" => "You can not change the role of super admin"
                ], 401);
            }
            if (!$request->user()->hasRole('superadmin') && $updatableUser->business_id != $request->user()->business_id && $updatableUser->created_by != $request->user()->id) {
                return response()->json([
                    "message" => "You can not update this user"
                ], 401);
            }



            $userQueryTerms = [
                "id" => $request_data["id"],
            ];

            $user  =  tap(User::where($userQueryTerms))->update(
                collect($request_data)->only([
                    'emergency_contact_details'

                ])->toArray()
            )
                // ->with("somthing")

                ->first();
            if (!$user) {

                return response()->json([
                    "message" => "no user found"
                ], 404);
            }



            return response($user, 201);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }


    /**
     *
     * @OA\Put(
     *      path="/v1.0/users/toggle-active",
     *      operationId="toggleActiveUser",
     *      tags={"user_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to toggle user activity",
     *      description="This method is to toggle user activity",
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

    public function toggleActiveUser(GetIdRequest $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('user_update')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $request_data = $request->validated();

            $userQuery  = User::where(["id" => $request_data["id"]]);
            if (!auth()->user()->hasRole('superadmin')) {
                $userQuery = $userQuery->where(function ($query) {
                    $query->where('business_id', auth()->user()->business_id)
                        ->orWhere('created_by', auth()->user()->id)
                        ->orWhere('id', auth()->user()->id);
                });
            }

            $user =  $userQuery->first();
            if (!$user) {

                return response()->json([
                    "message" => "no user found"
                ], 404);
            }
            if ($user->hasRole("superadmin")) {
                return response()->json([
                    "message" => "superadmin can not be deactivated"
                ], 401);
            }

            $user->update([
                'is_active' => !$user->is_active
            ]);

            return response()->json(['message' => 'User status updated successfully'], 200);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }

    /**
     *
     * @OA\Put(
     *      path="/v1.0/users/profile",
     *      operationId="updateUserProfile",
     *      tags={"user_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update user profile",
     *      description="This method is to update user profile",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"id","first_Name","last_Name","email","password","password_confirmation","phone","address_line_1","address_line_2","country","city","postcode","role"},
     *           @OA\Property(property="id", type="string", format="number",example="1"),
     *             @OA\Property(property="first_Name", type="string", format="string",example="Rifat"),
     *            @OA\Property(property="last_Name", type="string", format="string",example="How was this?"),
     *            @OA\Property(property="email", type="string", format="string",example="How was this?"),

     * *  @OA\Property(property="password", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="password_confirmation", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="phone", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="address_line_1", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="address_line_2", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="country", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="city", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="postcode", type="boolean", format="boolean",example="1"),
     *     *     *  * *  @OA\Property(property="lat", type="string", format="boolean",example="1207"),
     *     *  * *  @OA\Property(property="long", type="string", format="boolean",example="1207"),
     * * *   @OA\Property(property="emergency_contact_details", type="string", format="array", example={})

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

    public function updateUserProfile(UserUpdateProfileRequest $request)
    {

        try {

            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            $request_data = $request->validated();


            if (!empty($request_data['password'])) {
                $request_data['password'] = Hash::make($request_data['password']);
            } else {
                unset($request_data['password']);
            }


            $userQuery = User::where([
                "id" => $request["id"]
            ]);
            $updatableUser = $userQuery->first();
            //  $request_data['is_active'] = true;
            //  $request_data['remember_token'] = Str::random(10);
            $user  =  tap(User::where(["id" => $request->user()->id]))->update(
                collect($request_data)->only([
                    'first_Name',
                    'middle_Name',

                    'last_Name',
                    'password',
                    'phone',
                    'address_line_1',
                    'address_line_2',
                    'country',
                    'city',
                    'postcode',
                    "lat",
                    "long",
                    "image",
                    "gender",
                    'emergency_contact_details',

                ])->toArray()
            )
                // ->with("somthing")

                ->first();

            if (!$user) {

                return response()->json([
                    "message" => "no user found"
                ], 404);
            }

            // history section
            $this->update_address_history($request_data, $user);
            // end history section



            $user->roles = $user->roles->pluck('name');


            return response($user, 201);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }



    /**
     *
     * @OA\Get(
     *      path="/v1.0/users",
     *      operationId="getUsers",
     *      tags={"user_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *   *              @OA\Parameter(
     *         name="response_type",
     *         in="query",
     *         description="response_type: in pdf,csv,json",
     *         required=true,
     *  example="json"
     *      ),
     *      *   *              @OA\Parameter(
     *         name="file_name",
     *         in="query",
     *         description="file_name",
     *         required=true,
     *  example="employee"
     *      ),
     *              @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="per_page",
     *         required=true,
     *  example="6"
     *      ),
     *      * *  @OA\Parameter(
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
     *
     *
     * @OA\Parameter(
     * name="full_name",
     * in="query",
     * description="full_name",
     * required=true,
     * example="full_name"
     * ),
     *
     *    * @OA\Parameter(
     * name="employee_id",
     * in="query",
     * description="employee_id",
     * required=true,
     * example="1"
     * ),
     *
     *  *
     *    * @OA\Parameter(
     * name="email",
     * in="query",
     * description="email",
     * required=true,
     * example="email"
     * ),
     *
     *
     *
     *
     * *  @OA\Parameter(
     * name="search_key",
     * in="query",
     * description="search_key",
     * required=true,
     * example="search_key"
     * ),
     *   * *  @OA\Parameter(
     * name="is_in_employee",
     * in="query",
     * description="is_in_employee",
     * required=true,
     * example="1"
     * ),
     *
     * @OA\Parameter(
     * name="is_in_employee",
     * in="query",
     * description="is_in_employee",
     * required=true,
     * example="1"
     * ),
     *  * @OA\Parameter(
     * name="designation_id",
     * in="query",
     * description="designation_id",
     * required=true,
     * example="1"
     * ),
     *    *  * @OA\Parameter(
     * name="work_location_ids",
     * in="query",
     * description="work_location_ids",
     * required=true,
     * example="1,2"
     * ),
     *     *    *  * @OA\Parameter(
     * name="holiday_id",
     * in="query",
     * description="holiday_id",
     * required=true,
     * example="1"
     * ),
     *
     * @OA\Parameter(
     * name="has_this_project",
     * in="query",
     * description="has_this_project",
     * required=true,
     * example="1"
     * ),
     *
     *      *     @OA\Parameter(
     * name="business_id",
     * in="query",
     * description="business_id",
     * required=true,
     * example="1"
     * ),
     *
     *  *      *     @OA\Parameter(
     * name="employment_status_id",
     * in="query",
     * description="employment_status_id",
     * required=true,
     * example="1"
     * ),
     *      *  *      *     @OA\Parameter(
     * name="immigration_status",
     * in="query",
     * description="immigration_status",
     * required=true,
     * example="immigration_status"
     * ),
     *      *  @OA\Parameter(
     * name="pension_scheme_status",
     * in="query",
     * description="pension_scheme_status",
     * required=true,
     * example="pension_scheme_status"
     * ),
     *  @OA\Parameter(
     * name="sponsorship_status",
     * in="query",
     * description="sponsorship_status",
     * required=true,
     * example="sponsorship_status"
     * ),

     * *  @OA\Parameter(
     * name="sponsorship_note",
     * in="query",
     * description="sponsorship_note",
     * required=true,
     * example="sponsorship_note"
     * ),
     * *  @OA\Parameter(
     * name="sponsorship_certificate_number",
     * in="query",
     * description="sponsorship_certificate_number",
     * required=true,
     * example="sponsorship_certificate_number"
     * ),
     * *  @OA\Parameter(
     * name="sponsorship_current_certificate_status",
     * in="query",
     * description="sponsorship_current_certificate_status",
     * required=true,
     * example="sponsorship_current_certificate_status"
     * ),
     * *  @OA\Parameter(
     * name="sponsorship_is_sponsorship_withdrawn",
     * in="query",
     * description="sponsorship_is_sponsorship_withdrawn",
     * required=true,
     * example="0"
     * ),
     *  * *  @OA\Parameter(
     * name="start_joining_date",
     * in="query",
     * description="start_joining_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *  *  * *  @OA\Parameter(
     * name="end_joining_date",
     * in="query",
     * description="end_joining_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *
     *
     *    *    *  *   @OA\Parameter(
     * name="start_pension_pension_enrollment_issue_date",
     * in="query",
     * description="start_pension_pension_enrollment_issue_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *
     *   @OA\Parameter(
     * name="end_pension_pension_enrollment_issue_date",
     * in="query",
     * description="end_pension_pension_enrollment_issue_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *
     *    *  *   @OA\Parameter(
     * name="start_pension_re_enrollment_due_date_date",
     * in="query",
     * description="start_pension_re_enrollment_due_date_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *
     *   @OA\Parameter(
     * name="end_pension_re_enrollment_due_date_date",
     * in="query",
     * description="end_pension_re_enrollment_due_date_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *    *   @OA\Parameter(
     * name="pension_re_enrollment_due_date_in_day",
     * in="query",
     * description="pension_re_enrollment_due_date_in_day",
     * required=true,
     * example="50"
     * ),
     *
     *
     * @OA\Parameter(
     * name="start_sponsorship_date_assigned",
     * in="query",
     * description="start_sponsorship_date_assigned",
     * required=true,
     * example="2024-01-21"
     * ),
     *
     *   @OA\Parameter(
     * name="end_sponsorship_date_assigned",
     * in="query",
     * description="end_sponsorship_date_assigned",
     * required=true,
     * example="2024-01-21"
     * ),
     *
     *    *  *   @OA\Parameter(
     * name="start_sponsorship_expiry_date",
     * in="query",
     * description="start_sponsorship_expiry_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *
     *   @OA\Parameter(
     * name="end_sponsorship_expiry_date",
     * in="query",
     * description="end_sponsorship_expiry_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *    *   @OA\Parameter(
     * name="sponsorship_expires_in_day",
     * in="query",
     * description="sponsorship_expires_in_day",
     * required=true,
     * example="50"
     * ),
     *
     *
     *      *    *  *   @OA\Parameter(
     * name="start_passport_issue_date",
     * in="query",
     * description="start_passport_issue_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *
     *   @OA\Parameter(
     * name="end_passport_issue_date",
     * in="query",
     * description="end_passport_issue_date",
     * required=true,
     * example="2024-01-21"
     * ),
     * @OA\Parameter(
     * name="start_passport_expiry_date",
     * in="query",
     * description="start_passport_expiry_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *
     *   @OA\Parameter(
     * name="end_passport_expiry_date",
     * in="query",
     * description="end_passport_expiry_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *   *    *   @OA\Parameter(
     * name="passport_expires_in_day",
     * in="query",
     * description="passport_expires_in_day",
     * required=true,
     * example="50"
     * ),
     *     * @OA\Parameter(
     * name="start_visa_issue_date",
     * in="query",
     * description="start_visa_issue_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *
     *   @OA\Parameter(
     * name="end_visa_issue_date",
     * in="query",
     * description="end_visa_issue_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *      *     * @OA\Parameter(
     * name="start_visa_expiry_date",
     * in="query",
     * description="start_visa_expiry_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *
     *   @OA\Parameter(
     * name="end_visa_expiry_date",
     * in="query",
     * description="end_visa_expiry_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *     @OA\Parameter(
     * name="visa_expires_in_day",
     * in="query",
     * description="visa_expires_in_day",
     * required=true,
     * example="50"
     * ),
     * * @OA\Parameter(
     * name="start_right_to_work_check_date",
     * in="query",
     * description="start_right_to_work_check_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *
     * @OA\Parameter(
     * name="end_right_to_work_check_date",
     * in="query",
     * description="end_right_to_work_check_date",
     * required=true,
     * example="2024-01-21"
     * ),
     * @OA\Parameter(
     * name="start_right_to_work_expiry_date",
     * in="query",
     * description="start_right_to_work_expiry_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *
     * @OA\Parameter(
     * name="end_right_to_work_expiry_date",
     * in="query",
     * description="end_right_to_work_expiry_date",
     * required=true,
     * example="2024-01-21"
     * ),
     * @OA\Parameter(
     * name="right_to_work_expires_in_day",
     * in="query",
     * description="right_to_work_expires_in_day",
     * required=true,
     * example="50"
     * ),

     *
     *
     *  *      *     @OA\Parameter(
     * name="project_id",
     * in="query",
     * description="project_id",
     * required=true,
     * example="1"
     * ),
     *     * @OA\Parameter(
     * name="department_id",
     * in="query",
     * description="department_id",
     * required=true,
     * example="1"
     * ),
     *
     * *      *   * *  @OA\Parameter(
     * name="doesnt_have_payrun",
     * in="query",
     * description="doesnt_have_payrun",
     * required=true,
     * example="1"
     * ),
     *
     *      *   * *  @OA\Parameter(
     * name="is_active",
     * in="query",
     * description="is_active",
     * required=true,
     * example="1"
     * ),
     *
     *    * *  @OA\Parameter(
     * name="role",
     * in="query",
     * description="role",
     * required=true,
     * example="admin,manager"
     * ),
     *
     *  @OA\Parameter(
     * name="is_on_holiday",
     * in="query",
     * description="is_on_holiday",
     * required=true,
     * example="1"
     * ),
     *
     *  *  @OA\Parameter(
     * name="upcoming_expiries",
     * in="query",
     * description="upcoming_expiries",
     * required=true,
     * example="passport"
     * ),
     *    *
     *    *    *   *  * *  @OA\Parameter(
     * name="not_in_rota",
     * in="query",
     * description="not_in_rota",
     * required=true,
     * example="1"
     * ),
     *
     *
     *
     *      summary="This method is to get user",
     *      description="This method is to get user",
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

    public function getUsers(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            if (!$request->user()->hasPermissionTo('user_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }


            $all_manager_department_ids = $this->get_all_departments_of_manager();


            $usersQuery = User::with(
                [
                    "designation" => function ($query) {
                        $query->select(
                            'designations.id',
                            'designations.name',
                        );
                    },
                    "roles",
                    "work_locations"
                ]
            );

            $usersQuery = $this->userManagementComponent->updateUsersQuery($all_manager_department_ids, $usersQuery);
            $users = $this->retrieveData($usersQuery, "users.first_Name");



            if (!empty($request->response_type) && in_array(strtoupper($request->response_type), ['PDF', 'CSV'])) {
                if (strtoupper($request->response_type) == 'PDF') {
                    $pdf = PDF::loadView('pdf.users', ["users" => $users]);
                    return $pdf->download(((!empty($request->file_name) ? $request->file_name : 'employee') . '.pdf'));
                } elseif (strtoupper($request->response_type) === 'CSV') {

                    return Excel::download(new UsersExport($users), ((!empty($request->file_name) ? $request->file_name : 'employee') . '.csv'));
                }
            } else {
                return response()->json($users, 200);
            }
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }




    /**
     *
     * @OA\Get(
     *      path="/v2.0/users",
     *      operationId="getUsersV2",
     *      tags={"user_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="per_page",
     *         required=true,
     *  example="6"
     *      ),
     *      * *  @OA\Parameter(
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
     *   * *  @OA\Parameter(
     * name="is_in_employee",
     * in="query",
     * description="is_in_employee",
     * required=true,
     * example="1"
     * ),
     *    *   * *  @OA\Parameter(
     * name="business_id",
     * in="query",
     * description="business_id",
     * required=true,
     * example="1"
     * ),
     *   *   * *  @OA\Parameter(
     * name="is_active",
     * in="query",
     * description="is_active",
     * required=true,
     * example="1"
     * ),
     *
     *
     *    * *  @OA\Parameter(
     * name="role",
     * in="query",
     * description="role",
     * required=true,
     * example="admin,manager"
     * ),
     *      summary="This method is to get user",
     *      description="This method is to get user",
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

    public function getUsersV2(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('user_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $all_manager_department_ids = $this->get_all_departments_of_manager();

            $usersQuery = User::with(
                [
                    "designation" => function ($query) {
                        $query->select(
                            'designations.id',
                            'designations.name',
                        );
                    },
                    "roles",
                    "recruitment_processes",
                    "work_locations"
                ]
            );

            $usersQuery = $this->userManagementComponent->updateUsersQuery($all_manager_department_ids, $usersQuery);
            $usersQuery = $usersQuery->withCount('all_users as user_count');
            $users = $this->retrieveData($usersQuery, "users.first_Name");






            $data["data"] = $users;
            $data["data_highlights"] = [];

            $data["data_highlights"]["total_active_users"] = $users->filter(function ($user) {
                return $user->is_active == 1;
            })->count();
            $data["data_highlights"]["total_users"] = $users->count();

            return response()->json($data, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }


    /**
     *
     * @OA\Get(
     *      path="/v3.0/users",
     *      operationId="getUsersV3",
     *      tags={"user_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="per_page",
     *         required=true,
     *  example="6"
     *      ),
     *      * *  @OA\Parameter(
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
     *   * *  @OA\Parameter(
     * name="is_in_employee",
     * in="query",
     * description="is_in_employee",
     * required=true,
     * example="1"
     * ),
     *    *   * *  @OA\Parameter(
     * name="business_id",
     * in="query",
     * description="business_id",
     * required=true,
     * example="1"
     * ),
     *   *   * *  @OA\Parameter(
     * name="is_active",
     * in="query",
     * description="is_active",
     * required=true,
     * example="1"
     * ),
     *
     *
     *    * *  @OA\Parameter(
     * name="role",
     * in="query",
     * description="role",
     * required=true,
     * example="admin,manager"
     * ),
     *      summary="This method is to get user",
     *      description="This method is to get user",
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

    public function getUsersV3(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('user_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $all_manager_department_ids = $this->get_all_departments_of_manager();

            $usersQuery = User::with(
                [
                    "designation" => function ($query) {
                        $query->select(
                            'designations.id',
                            'designations.name',
                        );
                    },
                    "roles",
                    "recruitment_processes",
                    "work_locations"
                ]
            );

            $usersQuery = $this->userManagementComponent->updateUsersQuery($all_manager_department_ids, $usersQuery);
            $usersQuery = $usersQuery->withCount('all_users as user_count');
            $users = $this->retrieveData($usersQuery, "users.first_Name");



            $data["data"] = $users;
            $data["data_highlights"] = [];

            $data["data_highlights"]["total_active_users"] = $users->filter(function ($user) {
                return $user->is_active == 1;
            })->count();
            $data["data_highlights"]["total_users"] = $users->count();

            return response()->json($data, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }


    /**
     *
     * @OA\Get(
     *      path="/v4.0/users",
     *      operationId="getUsersV4",
     *      tags={"user_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *   *              @OA\Parameter(
     *         name="response_type",
     *         in="query",
     *         description="response_type: in pdf,csv,json",
     *         required=true,
     *  example="json"
     *      ),
     *      *   *              @OA\Parameter(
     *         name="file_name",
     *         in="query",
     *         description="file_name",
     *         required=true,
     *  example="employee"
     *      ),
     *              @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="per_page",
     *         required=true,
     *  example="6"
     *      ),
     *      * *  @OA\Parameter(
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
     *
     *
     * @OA\Parameter(
     * name="full_name",
     * in="query",
     * description="full_name",
     * required=true,
     * example="full_name"
     * ),
     *
     *    * @OA\Parameter(
     * name="employee_id",
     * in="query",
     * description="employee_id",
     * required=true,
     * example="1"
     * ),
     *
     *  *
     *    * @OA\Parameter(
     * name="email",
     * in="query",
     * description="email",
     * required=true,
     * example="email"
     * ),
     *
     *
     *
     *
     * *  @OA\Parameter(
     * name="search_key",
     * in="query",
     * description="search_key",
     * required=true,
     * example="search_key"
     * ),
     *   * *  @OA\Parameter(
     * name="is_in_employee",
     * in="query",
     * description="is_in_employee",
     * required=true,
     * example="1"
     * ),
     *
     * @OA\Parameter(
     * name="is_in_employee",
     * in="query",
     * description="is_in_employee",
     * required=true,
     * example="1"
     * ),
     *  * @OA\Parameter(
     * name="designation_id",
     * in="query",
     * description="designation_id",
     * required=true,
     * example="1"
     * ),
     *    *  * @OA\Parameter(
     * name="work_location_ids",
     * in="query",
     * description="work_location_ids",
     * required=true,
     * example="1,2"
     * ),
     *     *    *  * @OA\Parameter(
     * name="holiday_id",
     * in="query",
     * description="holiday_id",
     * required=true,
     * example="1"
     * ),
     *
     * @OA\Parameter(
     * name="has_this_project",
     * in="query",
     * description="has_this_project",
     * required=true,
     * example="1"
     * ),
     *
     *      *     @OA\Parameter(
     * name="business_id",
     * in="query",
     * description="business_id",
     * required=true,
     * example="1"
     * ),
     *
     *  *      *     @OA\Parameter(
     * name="employment_status_id",
     * in="query",
     * description="employment_status_id",
     * required=true,
     * example="1"
     * ),
     *      *  *      *     @OA\Parameter(
     * name="immigration_status",
     * in="query",
     * description="immigration_status",
     * required=true,
     * example="immigration_status"
     * ),
     *      *  @OA\Parameter(
     * name="pension_scheme_status",
     * in="query",
     * description="pension_scheme_status",
     * required=true,
     * example="pension_scheme_status"
     * ),
     *  @OA\Parameter(
     * name="sponsorship_status",
     * in="query",
     * description="sponsorship_status",
     * required=true,
     * example="sponsorship_status"
     * ),

     * *  @OA\Parameter(
     * name="sponsorship_note",
     * in="query",
     * description="sponsorship_note",
     * required=true,
     * example="sponsorship_note"
     * ),
     * *  @OA\Parameter(
     * name="sponsorship_certificate_number",
     * in="query",
     * description="sponsorship_certificate_number",
     * required=true,
     * example="sponsorship_certificate_number"
     * ),
     * *  @OA\Parameter(
     * name="sponsorship_current_certificate_status",
     * in="query",
     * description="sponsorship_current_certificate_status",
     * required=true,
     * example="sponsorship_current_certificate_status"
     * ),
     * *  @OA\Parameter(
     * name="sponsorship_is_sponsorship_withdrawn",
     * in="query",
     * description="sponsorship_is_sponsorship_withdrawn",
     * required=true,
     * example="0"
     * ),
     *  * *  @OA\Parameter(
     * name="start_joining_date",
     * in="query",
     * description="start_joining_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *  *  * *  @OA\Parameter(
     * name="end_joining_date",
     * in="query",
     * description="end_joining_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *
     *
     *    *    *  *   @OA\Parameter(
     * name="start_pension_pension_enrollment_issue_date",
     * in="query",
     * description="start_pension_pension_enrollment_issue_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *
     *   @OA\Parameter(
     * name="end_pension_pension_enrollment_issue_date",
     * in="query",
     * description="end_pension_pension_enrollment_issue_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *
     *    *  *   @OA\Parameter(
     * name="start_pension_re_enrollment_due_date_date",
     * in="query",
     * description="start_pension_re_enrollment_due_date_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *
     *   @OA\Parameter(
     * name="end_pension_re_enrollment_due_date_date",
     * in="query",
     * description="end_pension_re_enrollment_due_date_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *    *   @OA\Parameter(
     * name="pension_re_enrollment_due_date_in_day",
     * in="query",
     * description="pension_re_enrollment_due_date_in_day",
     * required=true,
     * example="50"
     * ),
     *
     *
     * @OA\Parameter(
     * name="start_sponsorship_date_assigned",
     * in="query",
     * description="start_sponsorship_date_assigned",
     * required=true,
     * example="2024-01-21"
     * ),
     *
     *   @OA\Parameter(
     * name="end_sponsorship_date_assigned",
     * in="query",
     * description="end_sponsorship_date_assigned",
     * required=true,
     * example="2024-01-21"
     * ),
     *
     *    *  *   @OA\Parameter(
     * name="start_sponsorship_expiry_date",
     * in="query",
     * description="start_sponsorship_expiry_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *
     *   @OA\Parameter(
     * name="end_sponsorship_expiry_date",
     * in="query",
     * description="end_sponsorship_expiry_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *    *   @OA\Parameter(
     * name="sponsorship_expires_in_day",
     * in="query",
     * description="sponsorship_expires_in_day",
     * required=true,
     * example="50"
     * ),
     *
     *
     *      *    *  *   @OA\Parameter(
     * name="start_passport_issue_date",
     * in="query",
     * description="start_passport_issue_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *
     *   @OA\Parameter(
     * name="end_passport_issue_date",
     * in="query",
     * description="end_passport_issue_date",
     * required=true,
     * example="2024-01-21"
     * ),
     * @OA\Parameter(
     * name="start_passport_expiry_date",
     * in="query",
     * description="start_passport_expiry_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *
     *   @OA\Parameter(
     * name="end_passport_expiry_date",
     * in="query",
     * description="end_passport_expiry_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *   *    *   @OA\Parameter(
     * name="passport_expires_in_day",
     * in="query",
     * description="passport_expires_in_day",
     * required=true,
     * example="50"
     * ),
     *     * @OA\Parameter(
     * name="start_visa_issue_date",
     * in="query",
     * description="start_visa_issue_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *
     *   @OA\Parameter(
     * name="end_visa_issue_date",
     * in="query",
     * description="end_visa_issue_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *      *     * @OA\Parameter(
     * name="start_visa_expiry_date",
     * in="query",
     * description="start_visa_expiry_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *
     *   @OA\Parameter(
     * name="end_visa_expiry_date",
     * in="query",
     * description="end_visa_expiry_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *     @OA\Parameter(
     * name="visa_expires_in_day",
     * in="query",
     * description="visa_expires_in_day",
     * required=true,
     * example="50"
     * ),
     * * @OA\Parameter(
     * name="start_right_to_work_check_date",
     * in="query",
     * description="start_right_to_work_check_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *
     * @OA\Parameter(
     * name="end_right_to_work_check_date",
     * in="query",
     * description="end_right_to_work_check_date",
     * required=true,
     * example="2024-01-21"
     * ),
     * @OA\Parameter(
     * name="start_right_to_work_expiry_date",
     * in="query",
     * description="start_right_to_work_expiry_date",
     * required=true,
     * example="2024-01-21"
     * ),
     *
     * @OA\Parameter(
     * name="end_right_to_work_expiry_date",
     * in="query",
     * description="end_right_to_work_expiry_date",
     * required=true,
     * example="2024-01-21"
     * ),
     * @OA\Parameter(
     * name="right_to_work_expires_in_day",
     * in="query",
     * description="right_to_work_expires_in_day",
     * required=true,
     * example="50"
     * ),

     *
     *
     *  *      *     @OA\Parameter(
     * name="project_id",
     * in="query",
     * description="project_id",
     * required=true,
     * example="1"
     * ),
     *     * @OA\Parameter(
     * name="department_id",
     * in="query",
     * description="department_id",
     * required=true,
     * example="1"
     * ),
     *
     * *      *   * *  @OA\Parameter(
     * name="doesnt_have_payrun",
     * in="query",
     * description="doesnt_have_payrun",
     * required=true,
     * example="1"
     * ),
     *
     *      *   * *  @OA\Parameter(
     * name="is_active",
     * in="query",
     * description="is_active",
     * required=true,
     * example="1"
     * ),
     *
     *    * *  @OA\Parameter(
     * name="role",
     * in="query",
     * description="role",
     * required=true,
     * example="admin,manager"
     * ),
     *
     *  @OA\Parameter(
     * name="is_on_holiday",
     * in="query",
     * description="is_on_holiday",
     * required=true,
     * example="1"
     * ),
     *
     *  *  @OA\Parameter(
     * name="upcoming_expiries",
     * in="query",
     * description="upcoming_expiries",
     * required=true,
     * example="passport"
     * ),
     *
     *
     *
     *
     *      summary="This method is to get user",
     *      description="This method is to get user",
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

    public function getUsersV4(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            if (!$request->user()->hasPermissionTo('user_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }


            $all_manager_department_ids = $this->get_all_departments_of_manager();

            $usersQuery = User::with(
                [
                    "designation" => function ($query) {
                        $query->select(
                            'designations.id',
                            'designations.name',
                        );
                    },
                    "roles" => function ($query) {
                        $query->select(
                            'roles.id',
                            'roles.name',
                        );
                    },

                ]
            );

            $usersQuery = $this->userManagementComponent->updateUsersQuery($all_manager_department_ids, $usersQuery);
            $usersQuery = $usersQuery->select(
                "users.id",
                "users.first_Name",
                "users.middle_Name",
                "users.last_Name",
                "users.user_id",
                "users.email",
                "users.image",
                "users.status",
            );
            $users = $this->retrieveData($usersQuery, "users.first_Name");




            if (!empty($request->response_type) && in_array(strtoupper($request->response_type), ['PDF', 'CSV'])) {
                //  if (strtoupper($request->response_type) == 'PDF') {
                //      $pdf = PDF::loadView('pdf.users', ["users" => $users]);
                //      return $pdf->download(((!empty($request->file_name) ? $request->file_name : 'employee') . '.pdf'));
                //  } elseif (strtoupper($request->response_type) === 'CSV') {

                //      return Excel::download(new UsersExport($users), ((!empty($request->file_name) ? $request->file_name : 'employee') . '.csv'));
                //  }
            } else {
                return response()->json($users, 200);
            }
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }




    /**
     *
     * @OA\Get(
     *      path="/v5.0/users",
     *      operationId="getUsersV5",
     *      tags={"user_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *   *              @OA\Parameter(
     *         name="response_type",
     *         in="query",
     *         description="response_type: in pdf,csv,json",
     *         required=true,
     *  example="json"
     *      ),
     *      *   *              @OA\Parameter(
     *         name="file_name",
     *         in="query",
     *         description="file_name",
     *         required=true,
     *  example="employee"
     *      ),
     *              @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="per_page",
     *         required=true,
     *  example="6"
     *      ),
     *      * *  @OA\Parameter(
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
     *
     *
     * @OA\Parameter(
     * name="full_name",
     * in="query",
     * description="full_name",
     * required=true,
     * example="full_name"
     * ),

     *
     *
     *
     *
     *      summary="This method is to get user",
     *      description="This method is to get user",
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

     public function getUsersV5(Request $request)
     {
         try {
             $this->storeActivity($request, "DUMMY activity", "DUMMY description");

             if (!$request->user()->hasPermissionTo('user_view')) {
                 return response()->json([
                     "message" => "You can not perform this action"
                 ], 401);
             }


             $all_manager_department_ids = $this->get_all_departments_of_manager();

             $usersQuery = User::query();

             $usersQuery = $this->userManagementComponent->updateUsersQuery($all_manager_department_ids, $usersQuery);
             $usersQuery = $usersQuery->select(
                 "users.id",
                 "users.first_Name",
                 "users.middle_Name",
                 "users.last_Name",
                 "users.joining_date",
             );
             $users = $this->retrieveData($usersQuery, "users.first_Name");




             if (!empty($request->response_type) && in_array(strtoupper($request->response_type), ['PDF', 'CSV'])) {
                 //  if (strtoupper($request->response_type) == 'PDF') {
                 //      $pdf = PDF::loadView('pdf.users', ["users" => $users]);
                 //      return $pdf->download(((!empty($request->file_name) ? $request->file_name : 'employee') . '.pdf'));
                 //  } elseif (strtoupper($request->response_type) === 'CSV') {

                 //      return Excel::download(new UsersExport($users), ((!empty($request->file_name) ? $request->file_name : 'employee') . '.csv'));
                 //  }
             } else {
                 return response()->json($users, 200);
             }
         } catch (Exception $e) {

             return $this->sendError($e, 500, $request);
         }
     }

 /**
     *
     * @OA\Get(
     *      path="/v7.0/users",
     *      operationId="getUsersV7",
     *      tags={"user_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *   *              @OA\Parameter(
     *         name="response_type",
     *         in="query",
     *         description="response_type: in pdf,csv,json",
     *         required=true,
     *  example="json"
     *      ),
     *      *   *              @OA\Parameter(
     *         name="file_name",
     *         in="query",
     *         description="file_name",
     *         required=true,
     *  example="employee"
     *      ),
     *              @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="per_page",
     *         required=true,
     *  example="6"
     *      ),
     *      * *  @OA\Parameter(
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
     *
     *
     * @OA\Parameter(
     * name="full_name",
     * in="query",
     * description="full_name",
     * required=true,
     * example="full_name"
     * ),

     *
     *
     *
     *
     *      summary="This method is to get user",
     *      description="This method is to get user",
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

     public function getUsersV7(Request $request)
     {
         try {
             $this->storeActivity($request, "DUMMY activity", "DUMMY description");

             if (!$request->user()->hasPermissionTo('user_view')) {
                 return response()->json([
                     "message" => "You can not perform this action"
                 ], 401);
             }


             $all_manager_department_ids = $this->get_all_departments_of_manager();

             $usersQuery = User::query();

             $usersQuery = $this->userManagementComponent->updateUsersQuery($all_manager_department_ids, $usersQuery);
             $usersQuery = $usersQuery->select(
                 "users.id",
                 "users.first_Name",
                 "users.middle_Name",
                 "users.last_Name",
                 "users.image",
                 "users.email",
                 "users.is_active"
             );


             $users = $this->retrieveData($usersQuery, "users.first_Name");

             if (request()->input("per_page")) {


                 // Modify the paginated items
                 $modifiedUsers = $users->getCollection()->each(function ($user) {
                     $user->handle_self_registered_businesses = $user->hasAllPermissions(['handle_self_registered_businesses', 'system_setting_update', 'system_setting_view']) ? 1 : 0;
                     $resold_businesses = collect($user->resold_businesses);

                     // Count the total number of resold businesses
                     $user->resold_businesses_count = $resold_businesses->count();

                     // Count the number of active subscribed businesses
                     $user->active_subscribed_businesses_count = $resold_businesses->filter(function($business) {
                         return $business->is_subscribed;
                     })->count();
                     return $user;
                 });

                 // Recreate the paginator with modified items
                 $users = new \Illuminate\Pagination\LengthAwarePaginator(
                     $modifiedUsers,
                     $users->total(),
                     $users->perPage(),
                     $users->currentPage(),
                     [
                         'path' => $users->path(),
                         'query' => $users->appends(request()->query())->toArray(),
                     ]
                 );
             } else {
                 $users = $users->each(function ($user) {
                     $user->handle_self_registered_businesses = $user->hasAllPermissions(['handle_self_registered_businesses', 'system_setting_update', 'system_setting_view']) ? 1 : 0;
                     $resold_businesses = collect($user->resold_businesses);

                     // Count the total number of resold businesses
                     $user->resold_businesses_count = $resold_businesses->count();

                     // Count the number of active subscribed businesses
                     $user->active_subscribed_businesses_count = $resold_businesses->filter(function($business) {
                         return $business->is_subscribed;
                     })->count();
                     return $user;
                 });
             }

             return response()->json($users, 200);
         } catch (Exception $e) {

             return $this->sendError($e, 500, $request);
         }
     }


    /**
     *
     * @OA\Get(
     *      path="/v1.0/users/{id}",
     *
     *      operationId="getUserById",
     *      tags={"user_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="id",
     *         required=true,
     *  example="6"
     *      ),
     *   *   *              @OA\Parameter(
     *         name="response_type",
     *         in="query",
     *         description="response_type: in pdf, json",
     *         required=true,
     *  example="json"
     *      ),
     *     @OA\Parameter(
     *         name="file_name",
     *         in="query",
     *         description="file_name",
     *         required=true,
     *  example="employee"
     *      ),
     *
     *
     *  * @OA\Parameter(
     *     name="employee_details",
     *     in="query",
     *     description="Employee Details",
     *     required=true,
     *     example="employee_details"
     * ),
     * @OA\Parameter(
     *     name="leave_allowances",
     *     in="query",
     *     description="Leave Allowances",
     *     required=true,
     *     example="leave_allowances"
     * ),
     * @OA\Parameter(
     *     name="attendances",
     *     in="query",
     *     description="Attendances",
     *     required=true,
     *     example="attendances"
     * ),
     * @OA\Parameter(
     *     name="leaves",
     *     in="query",
     *     description="Leaves",
     *     required=true,
     *     example="leaves"
     * ),
     * @OA\Parameter(
     *     name="documents",
     *     in="query",
     *     description="Documents",
     *     required=true,
     *     example="documents"
     * ),
     * @OA\Parameter(
     *     name="assets",
     *     in="query",
     *     description="Assets",
     *     required=true,
     *     example="assets"
     * ),
     * @OA\Parameter(
     *     name="educational_history",
     *     in="query",
     *     description="Educational History",
     *     required=true,
     *     example="educational_history"
     * ),
     * @OA\Parameter(
     *     name="job_history",
     *     in="query",
     *     description="Job History",
     *     required=true,
     *     example="job_history"
     * ),
     * @OA\Parameter(
     *     name="current_cos_details",
     *     in="query",
     *     description="Current COS Details",
     *     required=true,
     *     example="current_cos_details"
     * ),
     *
     *
     *    * @OA\Parameter(
     *     name="current_pension_details",
     *     in="query",
     *     description="Current COS Details",
     *     required=true,
     *     example="current_pension_details"
     * ),
     *
     *
     *
     * @OA\Parameter(
     *     name="current_passport_details",
     *     in="query",
     *     description="Current Passport Details",
     *     required=true,
     *     example="current_passport_details"
     * ),
     * @OA\Parameter(
     *     name="current_visa_details",
     *     in="query",
     *     description="Current Visa Details",
     *     required=true,
     *     example="current_visa_details"
     * ),
     *   * @OA\Parameter(
     *     name="current_right_to_works",
     *     in="query",
     *     description="Current right to works",
     *     required=true,
     *     example="current_right_to_works"
     * ),
     * @OA\Parameter(
     *     name="address_details",
     *     in="query",
     *     description="Address Details",
     *     required=true,
     *     example="address_details"
     * ),
     * @OA\Parameter(
     *     name="contact_details",
     *     in="query",
     *     description="Contact Details",
     *     required=true,
     *     example="contact_details"
     * ),
     * @OA\Parameter(
     *     name="notes",
     *     in="query",
     *     description="Notes",
     *     required=true,
     *     example="notes"
     * ),
     * @OA\Parameter(
     *     name="bank_details",
     *     in="query",
     *     description="Bank Details",
     *     required=true,
     *     example="bank_details"
     * ),
     * @OA\Parameter(
     *     name="social_links",
     *     in="query",
     *     description="Social Links",
     *     required=true,
     *     example="social_links"
     * ),
     *  * @OA\Parameter(
     *     name="employee_details_name",
     *     in="query",
     *     description="Employee Name",
     *     required=true,
     *     example="John Doe"
     * ),
     * @OA\Parameter(
     *     name="employee_details_user_id",
     *     in="query",
     *     description="Employee User ID",
     *     required=true,
     *     example="123456"
     * ),
     * @OA\Parameter(
     *     name="employee_details_email",
     *     in="query",
     *     description="Employee Email",
     *     required=true,
     *     example="john.doe@example.com"
     * ),
     * @OA\Parameter(
     *     name="employee_details_phone",
     *     in="query",
     *     description="Employee Phone",
     *     required=true,
     *     example="123-456-7890"
     * ),
     * @OA\Parameter(
     *     name="employee_details_gender",
     *     in="query",
     *     description="Employee Gender",
     *     required=true,
     *     example="male"
     * ),
     * @OA\Parameter(
     *     name="leave_allowance_name",
     *     in="query",
     *     description="Leave Allowance Name",
     *     required=true,
     *     example="Annual Leave"
     * ),
     * @OA\Parameter(
     *     name="leave_allowance_type",
     *     in="query",
     *     description="Leave Allowance Type",
     *     required=true,
     *     example="Paid"
     * ),
     * @OA\Parameter(
     *     name="leave_allowance_allowance",
     *     in="query",
     *     description="Leave Allowance Amount",
     *     required=true,
     *     example="20"
     * ),
     * @OA\Parameter(
     *     name="leave_allowance_earned",
     *     in="query",
     *     description="Leave Allowance Earned",
     *     required=true,
     *     example="10"
     * ),
     * @OA\Parameter(
     *     name="leave_allowance_availability",
     *     in="query",
     *     description="Leave Allowance Availability",
     *     required=true,
     *     example="Yes"
     * ),
     * @OA\Parameter(
     *     name="attendance_date",
     *     in="query",
     *     description="Attendance Date",
     *     required=true,
     *     example="2024-02-13"
     * ),
     * @OA\Parameter(
     *     name="attendance_start_time",
     *     in="query",
     *     description="Attendance Start Time",
     *     required=true,
     *     example="08:00:00"
     * ),
     * @OA\Parameter(
     *     name="attendance_end_time",
     *     in="query",
     *     description="Attendance End Time",
     *     required=true,
     *     example="17:00:00"
     * ),
     * @OA\Parameter(
     *     name="attendance_break",
     *     in="query",
     *     description="Attendance Break Time",
     *     required=true,
     *     example="01:00:00"
     * ),
     * @OA\Parameter(
     *     name="attendance_schedule",
     *     in="query",
     *     description="Attendance Schedule",
     *     required=true,
     *     example="Regular"
     * ),
     * @OA\Parameter(
     *     name="attendance_overtime",
     *     in="query",
     *     description="Attendance Overtime",
     *     required=true,
     *     example="02:00:00"
     * ),
     * @OA\Parameter(
     *     name="leave_date_time",
     *     in="query",
     *     description="Leave Date and Time",
     *     required=true,
     *     example="2024-02-14 08:00:00"
     * ),
     * @OA\Parameter(
     *     name="leave_type",
     *     in="query",
     *     description="Leave Type",
     *     required=true,
     *     example="Sick Leave"
     * ),
     * @OA\Parameter(
     *     name="leave_duration",
     *     in="query",
     *     description="Leave Duration",
     *     required=true,
     *     example="8"
     * ),
     * @OA\Parameter(
     *     name="total_leave_hours",
     *     in="query",
     *     description="Total Leave Hours",
     *     required=true,
     *     example="8"
     * ),
     * @OA\Parameter(
     *     name="document_title",
     *     in="query",
     *     description="Document Title",
     *     required=true,
     *     example="Annual Report"
     * ),
     * @OA\Parameter(
     *     name="document_added_by",
     *     in="query",
     *     description="Document Added By",
     *     required=true,
     *     example="Jane Smith"
     * ),
     * @OA\Parameter(
     *     name="asset_name",
     *     in="query",
     *     description="Asset Name",
     *     required=true,
     *     example="Laptop"
     * ),
     * @OA\Parameter(
     *     name="asset_code",
     *     in="query",
     *     description="Asset Code",
     *     required=true,
     *     example="LT12345"
     * ),
     * @OA\Parameter(
     *     name="asset_serial_number",
     *     in="query",
     *     description="Asset Serial Number",
     *     required=true,
     *     example="SN6789"
     * ),
     * @OA\Parameter(
     *     name="asset_is_working",
     *     in="query",
     *     description="Is Asset Working",
     *     required=true,
     *     example="true"
     * ),
     * @OA\Parameter(
     *     name="asset_type",
     *     in="query",
     *     description="Asset Type",
     *     required=true,
     *     example="Electronic"
     * ),
     * @OA\Parameter(
     *     name="asset_date",
     *     in="query",
     *     description="Asset Date",
     *     required=true,
     *     example="2024-02-13"
     * ),
     * @OA\Parameter(
     *     name="asset_note",
     *     in="query",
     *     description="Asset Note",
     *     required=true,
     *     example="This is a laptop for development purposes."
     * ),
     * @OA\Parameter(
     *     name="educational_history_degree",
     *     in="query",
     *     description="Educational History Degree",
     *     required=true,
     *     example="Bachelor of Science"
     * ),
     * @OA\Parameter(
     *     name="educational_history_major",
     *     in="query",
     *     description="Educational History Major",
     *     required=true,
     *     example="Computer Science"
     * ),
     * @OA\Parameter(
     *     name="educational_history_start_date",
     *     in="query",
     *     description="Educational History Start Date",
     *     required=true,
     *     example="2018-09-01"
     * ),
     * @OA\Parameter(
     *     name="educational_history_achievements",
     *     in="query",
     *     description="Educational History Achievements",
     *     required=true,
     *     example="Graduated with honors"
     * ),
     * @OA\Parameter(
     *     name="job_history_job_title",
     *     in="query",
     *     description="Job History Job Title",
     *     required=true,
     *     example="Software Engineer"
     * ),
     * @OA\Parameter(
     *     name="job_history_company",
     *     in="query",
     *     description="Job History Company",
     *     required=true,
     *     example="Tech Solutions Inc."
     * ),
     * @OA\Parameter(
     *     name="job_history_start_on",
     *     in="query",
     *     description="Job History Start Date",
     *     required=true,
     *     example="2020-03-15"
     * ),
     * @OA\Parameter(
     *     name="job_history_end_at",
     *     in="query",
     *     description="Job History End Date",
     *     required=true,
     *     example="2022-05-30"
     * ),
     * @OA\Parameter(
     *     name="job_history_supervisor",
     *     in="query",
     *     description="Job History Supervisor",
     *     required=true,
     *     example="John Smith"
     * ),
     * @OA\Parameter(
     *     name="job_history_country",
     *     in="query",
     *     description="Job History Country",
     *     required=true,
     *     example="United States"
     * ),
     *
     *  * @OA\Parameter(
     *     name="current_pension_details_pension_scheme_status",
     *     in="query",
     *     description="current_pension_details_pension_scheme_status",
     *     required=true,
     *     example="2023-05-15"
     * ),
     *  * @OA\Parameter(
     *     name="current_pension_details_pension_enrollment_issue_date",
     *     in="query",
     *     description="current_pension_details_pension_enrollment_issue_date",
     *     required=true,
     *     example="2023-05-15"
     * ),
     *  * @OA\Parameter(
     *     name="current_pension_details_pension_scheme_opt_out_date",
     *     in="query",
     *     description="current_pension_details_pension_scheme_opt_out_date",
     *     required=true,
     *     example="2023-05-15"
     * ),
     *  * @OA\Parameter(
     *     name="current_pension_details_pension_re_enrollment_due_date",
     *     in="query",
     *     description="current_pension_details_pension_re_enrollment_due_date",
     *     required=true,
     *     example="2023-05-15"
     * ),

     *

     *
     * @OA\Parameter(
     *     name="current_cos_details_date_assigned",
     *     in="query",
     *     description="Date COS Assigned",
     *     required=true,
     *     example="2023-05-15"
     * ),
     * @OA\Parameter(
     *     name="current_cos_details_expiry_date",
     *     in="query",
     *     description="COS Expiry Date",
     *     required=true,
     *     example="2025-05-14"
     * ),
     * @OA\Parameter(
     *     name="current_cos_details_certificate_number",
     *     in="query",
     *     description="COS Certificate Number",
     *     required=true,
     *     example="COS12345"
     * ),
     * @OA\Parameter(
     *     name="current_cos_details_current_certificate_status",
     *     in="query",
     *     description="Current COS Certificate Status",
     *     required=true,
     *     example="Active"
     * ),
     * @OA\Parameter(
     *     name="current_cos_details_note",
     *     in="query",
     *     description="COS Note",
     *     required=true,
     *     example="Employee is eligible for work under the current COS."
     * ),
     * @OA\Parameter(
     *     name="current_passport_details_issue_date",
     *     in="query",
     *     description="Passport Issue Date",
     *     required=true,
     *     example="2022-01-01"
     * ),
     * @OA\Parameter(
     *     name="current_passport_details_expiry_date",
     *     in="query",
     *     description="Passport Expiry Date",
     *     required=true,
     *     example="2032-01-01"
     * ),
     * @OA\Parameter(
     *     name="current_passport_details_passport_number",
     *     in="query",
     *     description="Passport Number",
     *     required=true,
     *     example="P123456"
     * ),
     * @OA\Parameter(
     *     name="current_passport_details_place_of_issue",
     *     in="query",
     *     description="Passport Place of Issue",
     *     required=true,
     *     example="United Kingdom"
     * ),
     * @OA\Parameter(
     *     name="current_visa_details_issue_date",
     *     in="query",
     *     description="Visa Issue Date",
     *     required=true,
     *     example="2023-01-01"
     * ),
     * @OA\Parameter(
     *     name="current_visa_details_expiry_date",
     *     in="query",
     *     description="Visa Expiry Date",
     *     required=true,
     *     example="2025-01-01"
     * ),
     * @OA\Parameter(
     *     name="current_visa_details_brp_number",
     *     in="query",
     *     description="BRP Number",
     *     required=true,
     *     example="BRP1234567890"
     * ),
     * @OA\Parameter(
     *     name="current_visa_details_place_of_issue",
     *     in="query",
     *     description="Visa Place of Issue",
     *     required=true,
     *     example="United Kingdom"
     * ),
     *
     *  * @OA\Parameter(
     *     name="current_right_to_works_right_to_work_code",
     *     in="query",
     *     description="Right to Work Code",
     *     required=true,
     *     example="123456"
     * ),
     * @OA\Parameter(
     *     name="current_right_to_works_right_to_work_check_date",
     *     in="query",
     *     description="Right to Work Check Date",
     *     required=true,
     *     example="2023-01-01"
     * ),
     * @OA\Parameter(
     *     name="current_right_to_works_right_to_work_expiry_date",
     *     in="query",
     *     description="Right to Work Expiry Date",
     *     required=true,
     *     example="2025-01-01"
     * ),

     * @OA\Parameter(
     *     name="address_details_address",
     *     in="query",
     *     description="Address",
     *     required=true,
     *     example="123 Main Street"
     * ),
     * @OA\Parameter(
     *     name="address_details_city",
     *     in="query",
     *     description="City",
     *     required=true,
     *     example="London"
     * ),
     * @OA\Parameter(
     *     name="address_details_country",
     *     in="query",
     *     description="Country",
     *     required=true,
     *     example="United Kingdom"
     * ),
     * @OA\Parameter(
     *     name="address_details_postcode",
     *     in="query",
     *     description="Postcode",
     *     required=true,
     *     example="AB12 3CD"
     * ),
     * @OA\Parameter(
     *     name="contact_details_first_name",
     *     in="query",
     *     description="First Name",
     *     required=true,
     *     example="John"
     * ),
     * @OA\Parameter(
     *     name="contact_details_last_name",
     *     in="query",
     *     description="Last Name",
     *     required=true,
     *     example="Doe"
     * ),
     * @OA\Parameter(
     *     name="contact_details_relationship",
     *     in="query",
     *     description="Relationship",
     *     required=true,
     *     example="Spouse"
     * ),
     * @OA\Parameter(
     *     name="contact_details_address",
     *     in="query",
     *     description="Address",
     *     required=true,
     *     example="456 Elm Street"
     * ),
     * @OA\Parameter(
     *     name="contact_details_postcode",
     *     in="query",
     *     description="Postcode",
     *     required=true,
     *     example="XY12 3Z"
     * ),
     * @OA\Parameter(
     *     name="contact_details_day_time_tel_number",
     *     in="query",
     *     description="Daytime Telephone Number",
     *     required=true,
     *     example="123-456-7890"
     * ),
     * @OA\Parameter(
     *     name="contact_details_evening_time_tel_number",
     *     in="query",
     *     description="Evening Telephone Number",
     *     required=true,
     *     example="789-456-1230"
     * ),
     * @OA\Parameter(
     *     name="contact_details_mobile_tel_number",
     *     in="query",
     *     description="Mobile Telephone Number",
     *     required=true,
     *     example="987-654-3210"
     * ),
     * @OA\Parameter(
     *     name="notes_title",
     *     in="query",
     *     description="Notes Title",
     *     required=true,
     *     example="Meeting Notes"
     * ),
     * @OA\Parameter(
     *     name="notes_description",
     *     in="query",
     *     description="Notes Description",
     *     required=true,
     *     example="Discussed project progress."
     * ),
     * @OA\Parameter(
     *     name="bank_details_name",
     *     in="query",
     *     description="Bank Name",
     *     required=true,
     *     example="ABC Bank"
     * ),
     * @OA\Parameter(
     *     name="bank_details_sort_code",
     *     in="query",
     *     description="Bank Sort Code",
     *     required=true,
     *     example="12-34-56"
     * ),
     * @OA\Parameter(
     *     name="bank_details_account_name",
     *     in="query",
     *     description="Account Name",
     *     required=true,
     *     example="John Doe"
     * ),
     * @OA\Parameter(
     *     name="bank_details_account_number",
     *     in="query",
     *     description="Account Number",
     *     required=true,
     *     example="12345678"
     * ),
     * @OA\Parameter(
     *     name="social_links_website",
     *     in="query",
     *     description="Website",
     *     required=true,
     *     example="example.com"
     * ),
     * @OA\Parameter(
     *     name="social_links_url",
     *     in="query",
     *     description="Social Media URL",
     *     required=true,
     *     example="https://twitter.com/example"
     * ),

     *
     *

     *      summary="This method is to get user by id",
     *      description="This method is to get user by id",
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

    public function getUserById($id, Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('user_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $user = User::with(
                [
                    "roles",
                    "departments",
                    "designation",
                    "employment_status",
                    "business",
                    "work_locations",
                    "pension_detail"

                ]
            )
                ->where([
                    "id" => $id
                ])
                ->when(!$request->user()->hasRole('superadmin'), function ($query) use ($request) {
                    return $query->where(function ($query) {
                        return  $query->where('created_by', auth()->user()->id)
                            ->orWhere('id', auth()->user()->id)
                            ->orWhere('business_id', auth()->user()->business_id);
                    });
                })
                ->first();
            if (!$user) {

                return response()->json([
                    "message" => "no user found"
                ], 404);
            }
            // ->whereHas('roles', function ($query) {
            //     // return $query->where('name','!=', 'customer');
            // });
            $user->work_shift = $user->work_shifts()->first();

            if (!empty($request->response_type) && in_array(strtoupper($request->response_type), ['PDF', 'CSV'])) {
                if (strtoupper($request->response_type) == 'PDF') {
                    $pdf = PDF::loadView('pdf.user', ["user" => $user, "request" => $request]);
                    return $pdf->download(((!empty($request->file_name) ? $request->file_name : 'employee') . '.pdf'));
                } elseif (strtoupper($request->response_type) === 'CSV') {

                    return Excel::download(new UserExport($user), ((!empty($request->file_name) ? $request->file_name : 'employee') . '.csv'));
                }
            } else {
                return response()->json($user, 200);
            }

            return response()->json($user, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }

    /**
     *
     * @OA\Get(
     *      path="/v2.0/users/{id}",
     *      operationId="getUserByIdV2",
     *      tags={"user_management.employee"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="id",
     *         required=true,
     *  example="6"
     *      ),

     *      summary="This method is to get user by id",
     *      description="This method is to get user by id",
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

    public function getUserByIdV2($id, Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('user_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $all_manager_department_ids = $this->get_all_departments_of_manager();
            $user = User::with(
                [
                    "designation" => function ($query) {
                        $query->select(
                            'designations.id',
                            'designations.name',
                        );
                    },
                    "roles",
                    "departments",
                    "employment_status",
                    "sponsorship_details",
                    "passport_details",
                    "visa_details",
                    "right_to_works",
                    "work_shifts",
                    "recruitment_processes",
                    "work_locations"
                ]

            )

                ->where([
                    "id" => $id
                ])
                ->when(!$request->user()->hasRole('superadmin'), function ($query) use ($request, $all_manager_department_ids) {
                    return $query->where(function ($query) use ($all_manager_department_ids) {
                        return  $query->where('created_by', auth()->user()->id)
                            ->orWhere('id', auth()->user()->id)
                            ->orWhere('business_id', auth()->user()->business_id)
                            ->orWhereHas("department_user.department", function ($query) use ($all_manager_department_ids) {
                                $query->whereIn("departments.id", $all_manager_department_ids);
                            });
                    });
                })
                ->first();
            if (!$user) {

                return response()->json([
                    "message" => "no user found"
                ], 404);
            }
            // ->whereHas('roles', function ($query) {
            //     // return $query->where('name','!=', 'customer');
            // });
            $user->work_shift = $user->work_shifts()->first();

            $user->department_ids = [$user->departments->pluck("id")[0]];






            return response()->json($user, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }


    /**
     *
     * @OA\Get(
     *      path="/v3.0/users/{id}",
     *      operationId="getUserByIdV3",
     *      tags={"user_management.employee"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="id",
     *         required=true,
     *  example="6"
     *      ),

     *      summary="This method is to get user by id",
     *      description="This method is to get user by id",
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

    public function getUserByIdV3($id, Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('user_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $all_manager_department_ids = $this->get_all_departments_of_manager();


            $user = User::with(
                [
                    "designation" => function ($query) {
                        $query->select(
                            'designations.id',
                            'designations.name',
                        );
                    },
                    "roles",
                    "departments",
                    "employment_status",
                    "sponsorship_details",
                    "passport_details",
                    "visa_details",
                    "right_to_works",
                    "work_shifts",
                    "recruitment_processes",
                    "work_locations",
                    "bank"
                ]

            )

                ->where([
                    "id" => $id
                ])
                ->when(!$request->user()->hasRole('superadmin'), function ($query) use ($request, $all_manager_department_ids) {
                    return $query->where(function ($query) use ($all_manager_department_ids) {
                        return  $query->where('created_by', auth()->user()->id)
                            ->orWhere('id', auth()->user()->id)
                            ->orWhere('business_id', auth()->user()->business_id)
                            ->orWhereHas("department_user.department", function ($query) use ($all_manager_department_ids) {
                                $query->whereIn("departments.id", $all_manager_department_ids);
                            });
                    });
                })
                ->first();

            if (!$user) {

                return response()->json([
                    "message" => "no user found"
                ], 404);
            }
            // ->whereHas('roles', function ($query) {
            //     // return $query->where('name','!=', 'customer');
            // });
            $user->work_shift = $user->work_shifts()->first();



            $user->department_ids = [$user->departments->pluck("id")[0]];




            $data = [];
            $data["user_data"] = $user;


            $leave_types = $this->userManagementComponent->getLeaveDetailsByUserIdfunc($id, $all_manager_department_ids);

            $data["leave_allowance_data"] = $leave_types;


            $user_recruitment_processes = $this->userManagementComponent->getRecruitmentProcessesByUserIdFunc($id, $all_manager_department_ids);

            $data["user_recruitment_processes_data"] = $user_recruitment_processes;


            $data["attendances_data"] = $this->attendanceComponent->getAttendanceV2Data();

            $data["leaves_data"] = $this->leaveComponent->getLeaveV4Func();


             $data["rota_data"] = $this->userManagementComponent->getRotaData($user->id,$user->joining_date);








            $lastAttendanceDate =  Attendance::where([
                  "user_id" => $user->id
              ])->orderBy("in_date")->first();


              $lastLeaveDate =    LeaveRecord::
              whereHas("leave",function($query) use($user) {
                $query->where("leaves.user_id",$user->id);
              })->orderBy("leave_records.date")->first();

              $lastAssetAssignDate = UserAssetHistory::where([
                  "user_id" => $user->id
              ])->orderBy("from_date")->first();

// Convert the dates to Carbon instances for comparison
$lastAttendanceDate = $lastAttendanceDate ? Carbon::parse($lastAttendanceDate->in_date) : null;
$lastLeaveDate = $lastLeaveDate ? Carbon::parse($lastLeaveDate->date) : null;
$lastAssetAssignDate = $lastAssetAssignDate ? Carbon::parse($lastAssetAssignDate->from_date) : null;

// Find the oldest date
$oldestDate = null;

if ($lastAttendanceDate && (!$oldestDate || $lastAttendanceDate->lt($oldestDate))) {
    $oldestDate = $lastAttendanceDate;
}

if ($lastLeaveDate && (!$oldestDate || $lastLeaveDate->lt($oldestDate))) {
    $oldestDate = $lastLeaveDate;
}

if ($lastAssetAssignDate && (!$oldestDate || $lastAssetAssignDate->lt($oldestDate))) {
    $oldestDate = $lastAssetAssignDate;
}

$data["user_data"]["last_activity_date"] = $oldestDate;







            return response()->json($data, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }

   /**
     *
     * @OA\Get(
     *      path="/v4.0/users/{id}",
     *      operationId="getUserByIdV4",
     *      tags={"user_management.employee"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="id",
     *         required=true,
     *  example="6"
     *      ),

     *      summary="This method is to get user by id",
     *      description="This method is to get user by id",
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

     public function getUserByIdV4($id, Request $request)
     {
         try {
             $this->storeActivity($request, "DUMMY activity", "DUMMY description");
             if (!$request->user()->hasPermissionTo('user_view')) {
                 return response()->json([
                     "message" => "You can not perform this action"
                 ], 401);
             }
             $all_manager_department_ids = $this->get_all_departments_of_manager();
             $user = User::with(
                 [
                     "roles",
                 ]

             )

                 ->where([
                     "id" => $id
                 ])
                 ->when(!$request->user()->hasRole('superadmin'), function ($query) use ($request, $all_manager_department_ids) {
                     return $query->where(function ($query) use ($all_manager_department_ids) {
                         return $query->where('created_by', auth()->user()->id)
                             ->orWhere('id', auth()->user()->id)
                             ->orWhere('business_id', auth()->user()->business_id)
                             ->orWhereHas("department_user.department", function ($query) use ($all_manager_department_ids) {
                                 $query->whereIn("departments.id", $all_manager_department_ids);
                             });
                     });
                 })
                 ->select(
                     "users.id",
                     "users.first_Name",
                     "users.middle_Name",
                     "users.last_Name",
                     "users.email",

                     'users.address_line_1',
                     'users.address_line_2',
                     'users.country',
                     'users.city',
                     'users.postcode',
                     'users.gender',
                     'users.phone',
                 )
                 ->first();
             if (!$user) {

                 return response()->json([
                     "message" => "no user found"
                 ], 404);
             }


             $user->handle_self_registered_businesses = $user->hasAllPermissions(['handle_self_registered_businesses', 'system_setting_update', 'system_setting_view']) ? 1 : 0;


            //  $modules = Module::where('modules.is_enabled', 1)
            //      ->orderBy("modules.name", "ASC")

            //      ->select("id", "name")
            //      ->get()

            //      ->map(function ($item) use ($user) {
            //          $item->is_enabled = 0;

            //          $resellerModule =    ResellerModule::where([
            //              "reseller_id" => $user->id,
            //              "module_id" => $item->id
            //          ])
            //              ->first();

            //          if (!empty($resellerModule)) {
            //              if ($resellerModule->is_enabled) {
            //                  $item->is_enabled = 1;
            //              }
            //          }
            //          return $item;
            //      });

            //  $user->modules = $modules;




             return response()->json($user, 200);
         } catch (Exception $e) {

             return $this->sendError($e, 500, $request);
         }
     }

    /**
     *
     * @OA\Get(
     *      path="/v1.0/users/get-leave-details/{id}",
     *      operationId="getLeaveDetailsByUserId",
     *      tags={"user_management.employee"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="id",
     *         required=true,
     *  example="6"
     *      ),

     *      summary="This method is to get user by id",
     *      description="This method is to get user by id",
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

    public function getLeaveDetailsByUserId($id, Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('user_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $all_manager_department_ids = $this->get_all_departments_of_manager();

            $leave_types = $this->userManagementComponent->getLeaveDetailsByUserIdfunc($id, $all_manager_department_ids);


            return response()->json($leave_types, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }

           /**
     *
     * @OA\Get(
     *      path="/v1.0/users/load-data-for-leaves/{id}",
     *      operationId="getLoadDataForLeaveByUserId",
     *      tags={"user_management.employee"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="id",
     *         required=true,
     *  example="6"
     *      ),

         *     *     *              @OA\Parameter(
     *         name="start_date",
     *         in="path",
     *         description="start_date",
     *         required=true,
     *  example="1"
     *      ),
     *     *     *     *              @OA\Parameter(
     *         name="end_date",
     *         in="path",
     *         description="end_date",
     *         required=true,
     *  example="1"
     *      ),
     *

     *      summary="This method is to get user attendance related data by id",
     *      description="This method is to get user attendance related data by id",
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

     public function getLoadDataForLeaveByUserId($id, Request $request)
     {

         // foreach (File::glob(storage_path('logs') . '/*.log') as $file) {
         //     File::delete($file);
         // }
         try {
             $this->storeActivity($request, "DUMMY activity", "DUMMY description");
             if (!$request->user()->hasPermissionTo('user_view')) {
                 return response()->json([
                     "message" => "You can not perform this action"
                 ], 401);
             }
             $start_date = !empty(request()->start_date) ? request()->start_date : Carbon::now()->startOfYear()->format('Y-m-d');
             $end_date = !empty(request()->end_date) ? request()->end_date : Carbon::now()->endOfYear()->format('Y-m-d');


             $user_id = intval($id);
             $request_user_id = auth()->user()->id;
             if (!$request->user()->hasPermissionTo('user_view') && ($request_user_id !== $user_id)) {
                 return response()->json([
                     "message" => "You can not perform this action"
                 ], 401);
             }

             $all_manager_department_ids = $this->get_all_departments_of_manager();
             $user =    $this->validateUserQuery($user_id,$all_manager_department_ids);



            $already_taken_attendance_dates = $this->attendanceComponent->get_already_taken_attendance_dates($user->id, $start_date, $end_date);

            $already_taken_leave_dates = $this->leaveComponent->get_already_taken_leave_dates($start_date, $end_date, $user->id, (isset($is_full_day_leave) ? $is_full_day_leave : NULL));

            $blocked_dates_collection = collect($already_taken_attendance_dates);

            $blocked_dates_collection = $blocked_dates_collection->merge($already_taken_leave_dates);

            $unique_blocked_dates_collection = $blocked_dates_collection->unique();
            $blocked_dates_collection = $unique_blocked_dates_collection->values()->all();


            $colored_dates =  $this->userManagementComponent->getHolodayDetailsV2($user->id,$start_date,$end_date,false);



            // $workShiftHistories =  $this->get_work_shift_histories($start_date, $end_date, $user->id, ["flexible"]);

        $responseArray = [
            "blocked_dates" => $blocked_dates_collection,
            "colored_dates" => $colored_dates,
        ];

             return response()->json($responseArray, 200);
         } catch (Exception $e) {

             return $this->sendError($e, 500, $request);
         }
     }


       /**
     *
     * @OA\Get(
     *      path="/v1.0/users/load-data-for-attendances/{id}",
     *      operationId="getLoadDataForAttendanceByUserId",
     *      tags={"user_management.employee"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="id",
     *         required=true,
     *  example="6"
     *      ),

         *     *     *              @OA\Parameter(
     *         name="start_date",
     *         in="path",
     *         description="start_date",
     *         required=true,
     *  example="1"
     *      ),
     *     *     *     *              @OA\Parameter(
     *         name="end_date",
     *         in="path",
     *         description="end_date",
     *         required=true,
     *  example="1"
     *      ),
     *

     *      summary="This method is to get user attendance related data by id",
     *      description="This method is to get user attendance related data by id",
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

     public function getLoadDataForAttendanceByUserId($id, Request $request)
     {

         // foreach (File::glob(storage_path('logs') . '/*.log') as $file) {
         //     File::delete($file);
         // }
         try {
             $this->storeActivity($request, "DUMMY activity", "DUMMY description");
             if (!$request->user()->hasPermissionTo('user_view')) {
                 return response()->json([
                     "message" => "You can not perform this action"
                 ], 401);
             }
             $start_date = !empty(request()->start_date) ? request()->start_date : Carbon::now()->startOfYear()->format('Y-m-d');
             $end_date = !empty(request()->end_date) ? request()->end_date : Carbon::now()->endOfYear()->format('Y-m-d');


             $user_id = intval($id);
             $request_user_id = auth()->user()->id;
             if (!$request->user()->hasPermissionTo('user_view') && ($request_user_id !== $user_id)) {
                 return response()->json([
                     "message" => "You can not perform this action"
                 ], 401);
             }

             $all_manager_department_ids = $this->get_all_departments_of_manager();

             $user =    $this->validateUserQuery($user_id,$all_manager_department_ids);

        $disabled_days_for_attendance = $this->userManagementComponent->getDisableDatesForAttendance($user->id,$start_date,$end_date);

        $holiday_details =  $this->userManagementComponent->getHolodayDetails($id,$start_date,$end_date,true);

        $work_shift =   $this->workShiftHistoryComponent->getWorkShiftByUserId($user_id);


        $responseArray = [
            "disabled_days_for_attendance" => $disabled_days_for_attendance,
            "holiday_details" => $holiday_details,
            "work_shift" => $work_shift
        ];
             return response()->json($responseArray, 200);
         } catch (Exception $e) {

             return $this->sendError($e, 500, $request);
         }
     }

       /**
     *
     * @OA\Get(
     *      path="/v1.0/load-global-data-for-attendances",
     *      operationId="getLoadGlobalDataForAttendance",
     *      tags={"user_management.employee"},
     *       security={
     *           {"bearerAuth": {}}
     *       },

         *     *     *              @OA\Parameter(
     *         name="start_date",
     *         in="path",
     *         description="start_date",
     *         required=true,
     *  example="1"
     *      ),
     *     *     *     *              @OA\Parameter(
     *         name="end_date",
     *         in="path",
     *         description="end_date",
     *         required=true,
     *  example="1"
     *      ),
     *

     *      summary="This method is to get user attendance related data ",
     *      description="This method is to get user attendance related data ",
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

     public function getLoadGlobalDataForAttendance($id, Request $request)
     {

         // foreach (File::glob(storage_path('logs') . '/*.log') as $file) {
         //     File::delete($file);
         // }
         try {
             $this->storeActivity($request, "DUMMY activity", "DUMMY description");
             if (!$request->user()->hasPermissionTo('user_view')) {
                 return response()->json([
                     "message" => "You can not perform this action"
                 ], 401);
             }

// @@@@@@@@@@@@@@@@

$all_manager_department_ids = $this->get_all_departments_of_manager();


$usersQuery = User::with(
    [
        "designation" => function ($query) {
            $query->select(
                'designations.id',
                'designations.name',
            );
        },
        "roles",
        "work_locations"
    ]
);

$usersQuery = $this->userManagementComponent->updateUsersQuery($all_manager_department_ids, $usersQuery);

$users = $this->retrieveData($usersQuery, "users.first_Name");


        $work_locations = $this->workLocationComponent->getWorkLocations();



        $projects =   $this->projectComponent->getProjects();


        $responseArray = [
            "work_locations" => $work_locations,
            "users" => $users,
            "projects" => $projects
        ];
             return response()->json($responseArray, 200);
         } catch (Exception $e) {
             return $this->sendError($e, 500, $request);
         }
     }



    /**
     *
     * @OA\Get(
     *      path="/v1.0/users/get-disable-days-for-attendances/{id}",
     *      operationId="getDisableDaysForAttendanceByUserId",
     *      tags={"user_management.employee"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="id",
     *         required=true,
     *  example="6"
     *      ),


     *      summary="This method is to get user by id",
     *      description="This method is to get user by id",
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

    public function getDisableDaysForAttendanceByUserId($id, Request $request)
    {


        // foreach (File::glob(storage_path('logs') . '/*.log') as $file) {
        //     File::delete($file);
        // }
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('user_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $start_date = !empty(request()->start_date) ? request()->start_date : Carbon::now()->startOfYear()->format('Y-m-d');
            $end_date = !empty(request()->end_date) ? request()->end_date : Carbon::now()->endOfYear()->format('Y-m-d');

            $user_id = intval($id);
            $request_user_id = auth()->user()->id;
            if (!$request->user()->hasPermissionTo('user_view') && ($request_user_id !== $user_id)) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $all_manager_department_ids = $this->get_all_departments_of_manager();
            $user =    $this->validateUserQuery($user_id,$all_manager_department_ids);


            $result_array = $this->userManagementComponent->getDisableDatesForAttendance($user->id,$start_date,$end_date);


            return response()->json($result_array, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }





    /**
     *
     * @OA\Get(
     *      path="/v1.0/users/get-attendances/{id}",
     *      operationId="getAttendancesByUserId",
     *      tags={"user_management.employee"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="id",
     *         required=true,
     *  example="6"
     *      ),
     *      *     *              @OA\Parameter(
     *         name="is_including_leaves",
     *         in="path",
     *         description="is_including_leaves",
     *         required=true,
     *  example="1"
     *      ),
     *    @OA\Parameter(
     *         name="is_full_day_leave",
     *         in="path",
     *         description="is_full_day_leave",
     *         required=true,
     *  example="1"
     *      ),


     *      summary="This method is to get user by id",
     *      description="This method is to get user by id",
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

    public function getAttendancesByUserId($id, Request $request)
    {

        // foreach (File::glob(storage_path('logs') . '/*.log') as $file) {
        //     File::delete($file);
        // }

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            $user_id = intval($id);
            $request_user_id = auth()->user()->id;
            if (!$request->user()->hasPermissionTo('user_view') && ($request_user_id !== $user_id)) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $all_manager_department_ids = $this->get_all_departments_of_manager();
            $user =    $this->validateUserQuery($user_id,$all_manager_department_ids);


            $start_date = !empty($request->start_date) ? $request->start_date : Carbon::now()->startOfYear()->format('Y-m-d');
            $end_date = !empty($request->end_date) ? $request->end_date : Carbon::now()->endOfYear()->format('Y-m-d');



            $already_taken_attendance_dates = $this->attendanceComponent->get_already_taken_attendance_dates($user->id, $start_date, $end_date);




            $already_taken_leave_dates = $this->leaveComponent->get_already_taken_leave_dates($start_date, $end_date, $user->id, (isset($is_full_day_leave) ? $is_full_day_leave : NULL));


            $result_collection = collect($already_taken_attendance_dates);

            if (isset($request->is_including_leaves)) {
                if (intval($request->is_including_leaves) == 1) {
                    $result_collection = $result_collection->merge($already_taken_leave_dates);
                }
            }

            $unique_result_collection = $result_collection->unique();
            $result_array = $unique_result_collection->values()->all();




            return response()->json($result_array, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }

    /**
     *
     * @OA\Get(
     *      path="/v1.0/users/get-leaves/{id}",
     *      operationId="getLeavesByUserId",
     *      tags={"user_management.employee"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="id",
     *         required=true,
     *  example="6"
     *      ),



     *      summary="This method is to get user by id",
     *      description="This method is to get user by id",
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

    public function getLeavesByUserId($id, Request $request)
    {


        foreach (File::glob(storage_path('logs') . '/*.log') as $file) {
            File::delete($file);
        }
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            $user_id = intval($id);
            $request_user_id = auth()->user()->id;
            if (!$request->user()->hasPermissionTo('user_view') && ($request_user_id !== $user_id)) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $all_manager_department_ids = $this->get_all_departments_of_manager();
            $user =    $this->validateUserQuery($user_id,$all_manager_department_ids);


            $start_date = !empty($request->start_date) ? $request->start_date : Carbon::now()->startOfYear()->format('Y-m-d');
            $end_date = !empty($request->end_date) ? $request->end_date : Carbon::now()->endOfYear()->format('Y-m-d');


            $already_taken_leave_dates = $this->leaveComponent->get_already_taken_leave_dates($start_date, $end_date, $user->id);


            $result_collection = $already_taken_leave_dates->unique();

            $result_array = $result_collection->values()->all();


            return response()->json($result_array, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }

    /**
     *
     * @OA\Get(
     *      path="/v1.0/users/get-holiday-details/{id}",
     *      operationId="getholidayDetailsByUserId",
     *      tags={"user_management.employee"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="id",
     *         required=true,
     *  example="6"
     *      ),
     *     *              @OA\Parameter(
     *         name="is_including_attendance",
     *         in="path",
     *         description="is_including_attendance",
     *         required=true,
     *  example="1"
     *      ),
     *     *     *              @OA\Parameter(
     *         name="start_date",
     *         in="path",
     *         description="start_date",
     *         required=true,
     *  example="1"
     *      ),
     *     *     *     *              @OA\Parameter(
     *         name="end_date",
     *         in="path",
     *         description="end_date",
     *         required=true,
     *  example="1"
     *      ),
     *
     *

     *      summary="This method is to get user by id",
     *      description="This method is to get user by id",
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

    public function getholidayDetailsByUserId($id, Request $request)
    {


        // foreach (File::glob(storage_path('logs') . '/*.log') as $file) {
        //     File::delete($file);
        // }
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");


            $start_date = !empty(request()->start_date) ? request()->start_date : Carbon::now()->startOfYear()->format('Y-m-d');
            $end_date = !empty(request()->end_date) ? request()->end_date : Carbon::now()->endOfYear()->format('Y-m-d');

            $user_id = intval($id);
            $request_user_id = auth()->user()->id;

            if ((!$request->user()->hasPermissionTo('user_view') && ($request_user_id !== $user_id))) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }


            $all_manager_department_ids = $this->get_all_departments_of_manager();

            $this->validateUserQuery($user_id,$all_manager_department_ids);

        $result_array =  $this->userManagementComponent->getHolodayDetails($id,$start_date,$end_date,true);



            return response()->json($result_array, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }







    /**
     *
     * @OA\Get(
     *      path="/v1.0/users/get-schedule-information/by-user",
     *      operationId="getScheduleInformation",
     *      tags={"user_management.employee"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *   *   *              @OA\Parameter(
     *         name="response_type",
     *         in="query",
     *         description="response_type: in pdf,csv,json",
     *         required=true,
     *  example="json"
     *      ),
     *      *   *              @OA\Parameter(
     *         name="file_name",
     *         in="query",
     *         description="file_name",
     *         required=true,
     *  example="employee"
     *      ),

     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="start_date",
     *         required=true,
     *         example="start_date"
     *      ),
     *
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="end_date",
     *         required=true,
     *         example="end_date"
     *      ),
     *    *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="user_id",
     *         required=true,
     *         example="1"
     *      ),
     *

     *      summary="This method is to get user by id",
     *      description="This method is to get user by id",
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

    public function getScheduleInformation(Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('user_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $start_date = !empty($request->start_date) ? $request->start_date : Carbon::now()->startOfYear()->format('Y-m-d');
            $end_date = !empty($request->end_date) ? $request->end_date : Carbon::now()->endOfYear()->format('Y-m-d');
            $all_manager_department_ids = $this->get_all_departments_of_manager();



            $usersQuery = User::with(
                ["departments"]
            );

            $usersQuery = $this->userManagementComponent->updateUsersQuery($all_manager_department_ids, $usersQuery);
            $usersQuery = $usersQuery->select(
                "users.id",
                "users.first_Name",
                "users.middle_Name",
                "users.last_Name",
                "users.image",
            );
            $employees = $this->retrieveData($usersQuery, "users.first_Name");



            $employees =    $employees->map(function ($employee) use ($start_date, $end_date) {

   $data = $this->userManagementComponent->getScheduleInformationData($employee->id,$start_date,$end_date);


                $employee->schedule_data = $data["schedule_data"];
                $employee->total_capacity_hours = $data["total_capacity_hours"];






                return $employee;
            });


            if (!empty($request->response_type) && in_array(strtoupper($request->response_type), ['PDF', 'CSV'])) {
                if (strtoupper($request->response_type) == 'PDF') {
                    $pdf = PDF::loadView('pdf.employee-schedule', ["employees" => $employees]);
                    return $pdf->download(((!empty($request->file_name) ? $request->file_name : 'schedule') . '.pdf'));
                } elseif (strtoupper($request->response_type) === 'CSV') {
                    return Excel::download(new EmployeeSchedulesExport($employees), ((!empty($request->file_name) ? $request->file_name : 'employee') . '.csv'));
                }
            } else {

                return response()->json($employees, 200);
            }
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }




    /**
     *
     * @OA\Get(
     *      path="/v1.0/users/get-recruitment-processes/{id}",
     *      operationId="getRecruitmentProcessesByUserId",
     *      tags={"user_management.employee"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="id",
     *         required=true,
     *  example="6"
     *      ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="start_date",
     *         required=true,
     *         example="start_date"
     *      ),
     *
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="end_date",
     *         required=true,
     *         example="end_date"
     *      ),

     *      summary="This method is to get user by id",
     *      description="This method is to get user by id",
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

    public function getRecruitmentProcessesByUserId($id, Request $request)
    {
        //  $logPath = storage_path('logs');
        //  foreach (File::glob($logPath . '/*.log') as $file) {
        //      File::delete($file);
        //  }
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('user_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }


            $all_manager_department_ids = $this->get_all_departments_of_manager();


            $user_recruitment_processes = $this->userManagementComponent->getRecruitmentProcessesByUserIdFunc($id, $all_manager_department_ids);




            return response()->json($user_recruitment_processes, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }





    /**
     *
     * @OA\Delete(
     *      path="/v1.0/users/{ids}",
     *      operationId="deleteUsersByIds",
     *      tags={"user_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="ids",
     *         in="path",
     *         description="ids",
     *         required=true,
     *  example="1,2,3"
     *      ),
     *      summary="This method is to delete user by ids",
     *      description="This method is to delete user by ids",
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

    public function deleteUsersByIds($ids, Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            if (!$request->user()->hasPermissionTo('user_delete')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }


            $idsArray = explode(',', $ids);
            $existingIds = User::whereIn('id', $idsArray)
                ->when(!$request->user()->hasRole('superadmin'), function ($query) {
                    return $query->where(function ($query) {
                        return  $query->where('created_by', auth()->user()->id)
                            ->orWhere('business_id', auth()->user()->business_id);
                    });
                })
                ->select('id')
                ->get()
                ->pluck('id')
                ->toArray();
            $nonExistingIds = array_diff($idsArray, $existingIds);

            if (!empty($nonExistingIds)) {
                return response()->json([
                    "message" => "Some or all of the specified data do not exist."
                ], 404);
            }
            // Check if any of the existing users are superadmins
            $superadminCheck = User::whereIn('id', $existingIds)->whereHas('roles', function ($query) {
                $query->where('name', 'superadmin');
            })->exists();

            if ($superadminCheck) {
                return response()->json([
                    "message" => "Superadmin user(s) cannot be deleted."
                ], 401);
            }
            $userCheck = User::whereIn('id', $existingIds)->where("id", auth()->user()->id)->exists();

            if ($userCheck) {
                return response()->json([
                    "message" => "You can not delete your self."
                ], 401);
            }

            User::whereIn('id', $existingIds)->delete();
            return response()->json(["message" => "data deleted sussfully", "deleted_ids" => $existingIds], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }




    /**
     *
     * @OA\Get(
     *      path="/v1.0/users/generate/employee-id",
     *      operationId="generateEmployeeId",
     *      tags={"user_management.employee"},
     *       security={
     *           {"bearerAuth": {}}
     *       },



     *      summary="This method is to generate employee id",
     *      description="This method is to generate employee id",
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
    public function generateEmployeeId(Request $request)
    {

     $user_id =   $this->generateUniqueId("Business",auth()->user()->business_id,"User","user_id");

        return response()->json(["user_id" => $user_id], 200);
    }


    /**
     *
     * @OA\Get(
     *      path="/v1.0/users/validate/employee-id/{user_id}",
     *      operationId="validateEmployeeId",
     *      tags={"user_management.employee"},
     *       security={
     *           {"bearerAuth": {}}
     *       },

     *              @OA\Parameter(
     *         name="user_id",
     *         in="path",
     *         description="user_id",
     *         required=true,
     *  example="1"
     *      ),
     *    *              @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="id",
     *         required=true,
     *  example="1"
     *      ),


     *      summary="This method is to validate employee id",
     *      description="This method is to validate employee id",
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
    public function validateEmployeeId($user_id, Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            $user_id_exists =  DB::table('users')->where(
                [
                    'user_id' => $user_id,
                    "business_id" => $request->user()->business_id
                ]
            )
                ->when(
                    !empty($request->id),
                    function ($query) use ($request) {
                        $query->whereNotIn("id", [$request->id]);
                    }
                )
                ->exists();



            return response()->json(["user_id_exists" => $user_id_exists], 200);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }



    /**
     *
     * @OA\Get(
     *      path="/v1.0/users/get/user-activity",
     *      operationId="getUserActivity",
     *      tags={"user_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="per_page",
     *         required=true,
     *  example="6"
     *      ),
     *      * *  @OA\Parameter(
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
     *  * *  @OA\Parameter(
     * name="user_id",
     * in="query",
     * description="user_id",
     * required=true,
     * example="1"
     * ),
     *
     *
     *     * *  @OA\Parameter(
     * name="order_by",
     * in="query",
     * description="order_by",
     * required=true,
     * example="ASC"
     * ),

     *      summary="This method is to get user activity",
     *      description="This method is to get user activity",
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

    public function getUserActivity(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            $this->isModuleEnabled("user_activity");

            $all_manager_department_ids = $this->get_all_departments_of_manager();


            //  if (!$request->user()->hasPermissionTo('user_view')) {
            //      return response()->json([
            //          "message" => "You can not perform this action"
            //      ], 401);
            //  }

            $user =     User::where(["id" => $request->user_id])
                ->when((!auth()->user()->hasRole("superadmin") && auth()->user()->id != $request->user_id), function ($query) use ($all_manager_department_ids) {
                    $query->whereHas("department_user.department", function ($query) use ($all_manager_department_ids) {
                        $query->whereIn("departments.id", $all_manager_department_ids);
                    });
                })





                ->first();
            if (!$user) {

                return response()->json([
                    "message" => "User not found"
                ], 404);
            }




            $activity = ActivityLog::where("activity", "!=", "DUMMY activity")
                ->where("description", "!=", "DUMMY description")

                ->when(!empty($request->user_id), function ($query) use ($request) {
                    return $query->where('user_id', $request->user_id);
                })
                ->when(empty($request->user_id), function ($query) use ($request) {
                    return $query->where('user_id', $request->user()->id);
                })
                ->when(!empty($request->search_key), function ($query) use ($request) {
                    $term = $request->search_key;
                    return $query->where(function ($subquery) use ($term) {
                        $subquery->where("activity", "like", "%" . $term . "%")
                            ->orWhere("description", "like", "%" . $term . "%");
                    });
                })



                ->when(!empty($request->start_date), function ($query) use ($request) {
                    return $query->where('created_at', ">=", $request->start_date);
                })
                ->when(!empty($request->end_date), function ($query) use ($request) {
                    return $query->where('created_at', "<=", ($request->end_date . ' 23:59:59'));
                })

                ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                    return $query->orderBy("id", $request->order_by);
                }, function ($query) {
                    return $query->orderBy("id", "DESC");
                })
                ->select(
                    "api_url",
                    "activity",
                    "description",
                    "ip_address",
                    "request_method",
                    "device",
                    "created_at",
                    "updated_at",
                    "user",
                    "user_id",
                )


                ->when(!empty($request->per_page), function ($query) use ($request) {
                    return $query->paginate($request->per_page);
                }, function ($query) {
                    return $query->get();
                });;

            return response()->json($activity, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
}
