<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserCreateRecruitmentProcessRequest;
use App\Http\Requests\UserUpdateRecruitmentProcessRequest;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\ModuleUtil;
use App\Http\Utils\UserActivityUtil;
use App\Http\Utils\UserDetailsUtil;
use App\Models\User;
use App\Models\UserRecruitmentProcess;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserRecruitmentProcessController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil, ModuleUtil, UserDetailsUtil;


    public function createUserRecruitmentProcess(UserCreateRecruitmentProcessRequest $request)
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

            $request_data["recruitment_processes"] = $this->storeUploadedFiles($request_data["recruitment_processes"],"attachments","recruitment_processes",[]);
            $this->makeFilePermanent($request_data["recruitment_processes"],"attachments",[]);


            $updatableUser = User::where([
                "id" => $request_data["user_id"]
            ])->first();

            if (!$updatableUser) {

                return response()->json([
                    "message" => "no user found"
                ], 404);
            }


            if ($updatableUser->hasRole("superadmin") && $request_data["role"] != "superadmin") {
                return response()->json([
                    "message" => "You can not change the role of super admin"
                ], 401);
            }
            if (!$request->user()->hasRole('superadmin') && $updatableUser->business_id != auth()->user()->business_id && $updatableUser->created_by != $request->user()->id) {
                return response()->json([
                    "message" => "You can not update this user"
                ], 401);
            }

            $this->store_recruitment_processes($request_data, $updatableUser);


            // $this->moveUploadedFiles(collect($request_data["recruitment_processes"])->pluck("attachments"),"recruitment_processes");


            DB::commit();
            return response($updatableUser, 201);
        } catch (Exception $e) {
          DB::rollBack();




          try {
            $this->moveUploadedFilesBack($request_data["recruitment_processes"], "attachments", "recruitment_processes", []);
        } catch (Exception $innerException) {
            error_log("Failed to move recruitment processes files back: " . $innerException->getMessage());
        }



            return $this->sendError($e, 500, $request);
        }
    }




    public function updateUserRecruitmentProcess(UserUpdateRecruitmentProcessRequest $request)
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


            $request_data["recruitment_processes"] = $this->storeUploadedFiles($request_data["recruitment_processes"],"attachments","recruitment_processes",[]);
            $this->makeFilePermanent($request_data["recruitment_processes"],"attachments",[]);


            $updatableUser = User::where([
                "id" => $request_data["user_id"]
            ])->first();

            if (!$updatableUser) {

                return response()->json([
                    "message" => "no user found"
                ], 404);
            }


            if ($updatableUser->hasRole("superadmin") && $request_data["role"] != "superadmin") {
                return response()->json([
                    "message" => "You can not change the role of super admin"
                ], 401);
            }
            if (!$request->user()->hasRole('superadmin') && $updatableUser->business_id != auth()->user()->business_id && $updatableUser->created_by != $request->user()->id) {
                return response()->json([
                    "message" => "You can not update this user"
                ], 401);
            }


            $this->update_recruitment_processes_v2($request_data, $updatableUser);


            // $this->moveUploadedFiles(collect($request_data["recruitment_processes"])->pluck("attachments"),"recruitment_processes");

            DB::commit();
            return response($updatableUser, 201);
        } catch (Exception $e) {
        DB::rollBack();
            return $this->sendError($e, 500, $request);
        }
    }

    

    public function getUserRecruitmentProcessesById($id, Request $request)
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


            $user_recruitment_process = UserRecruitmentProcess::with("recruitment_process")
                ->where([
                    "id" =>$id
                ])
                ->whereHas("user.department_user.department", function ($query) use ($all_manager_department_ids) {
                    $query->whereIn("departments.id", $all_manager_department_ids);
                })
                ->whereNotNull("description")
                ->first();



            if (!$user_recruitment_process) {
                return response()->json([
                    "message" => "no recruitment process found"
                ], 404);
            }





            return response()->json($user_recruitment_process, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }


    public function deleteUserRecruitmentProcess($ids, Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('user_update')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }


            $all_manager_department_ids = $this->get_all_departments_of_manager();

            $idsArray = explode(',', $ids);
            $existingIds = UserRecruitmentProcess::whereHas("user.department_user.department", function ($query) use ($all_manager_department_ids) {
                    $query->whereIn("departments.id", $all_manager_department_ids);
                })
                ->whereHas("user", function ($query) {
                    $query->whereNotIn("users.id", [auth()->user()->id]);
                })

                ->whereIn('id', $idsArray)
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

            UserRecruitmentProcess::destroy($existingIds);


            return response()->json(["message" => "data deleted sussfully", "deleted_ids" => $existingIds], 200);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }
}
