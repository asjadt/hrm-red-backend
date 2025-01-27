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
            ->orderByDesc("employee_address_histories.id")
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





    public function generateEmployeeId(Request $request)
    {

     $user_id =   $this->generateUniqueId("Business",auth()->user()->business_id,"User","user_id");

        return response()->json(["user_id" => $user_id], 200);
    }



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
