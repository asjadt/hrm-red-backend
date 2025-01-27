<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRightToWorkHistoryCreateRequest;
use App\Http\Requests\UserRightToWorkHistoryUpdateRequest;
use App\Http\Utils\BasicUtil;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\Department;
use App\Models\EmployeeRightToWork;
use App\Models\EmployeeRightToWorkHistory;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserRightToWorkHistoryController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil, BasicUtil;






    public function createUserRightToWorkHistory(UserRightToWorkHistoryCreateRequest $request)
    {
        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");

                if (!$request->user()->hasPermissionTo('employee_right_to_work_history_create')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }


                $request_data = $request->validated();

                $request_data["right_to_work_docs"] =   $this->storeUploadedFiles($request_data["right_to_work_docs"],"file_name","right_to_work_docs");
                $this->makeFilePermanent($request_data["right_to_work_docs"],"file_name");


                $request_data["business_id"] = auth()->user()->business_id;
                $request_data["created_by"] = $request->user()->id;
                $request_data["is_manual"] = 1;




                $user_right_to_work_history =  EmployeeRightToWorkHistory::create($request_data);




                // $this->moveUploadedFiles(collect($request_data["right_to_work_docs"])->pluck("file_name"),"right_to_work_docs");

              DB::commit();


                return response($user_right_to_work_history, 201);

        } catch (Exception $e) {
           DB::rollBack();


       try {


        $this->moveUploadedFilesBack($request_data["right_to_work_docs"],"file_name","right_to_work_docs");



    } catch (Exception $innerException) {

        error_log("Failed to move right to work docs  files back: " . $innerException->getMessage());

    }




            return $this->sendError($e, 500, $request);
        }
    }


    public function updateRightToWorkHistory(UserRightToWorkHistoryUpdateRequest $request)
    {

        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");

                if (!$request->user()->hasPermissionTo('employee_right_to_work_history_update')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }


                $request_data = $request->validated();
                $request_data["right_to_work_docs"] =   $this->storeUploadedFiles($request_data["right_to_work_docs"],"file_name","right_to_work_docs");
                $this->makeFilePermanent($request_data["right_to_work_docs"],"file_name");
                $request_data["created_by"] = auth()->user()->id;
                $request_data["is_manual"] = 1;
                $request_data["business_id"] = auth()->user()->business_id;
                $all_manager_department_ids = $this->get_all_departments_of_manager();


                $current_user_id =  $request_data["user_id"];
                $issue_date_column = 'right_to_work_check_date';
                $expiry_date_column = 'right_to_work_expiry_date';

                $current_right_to_work = $this->getCurrentHistory(EmployeeRightToWorkHistory::class, 'current_right_to_work_id', $current_user_id, $issue_date_column, $expiry_date_column);


                $user_right_ro_work_history_query_params = [
                    "id" => $request_data["id"],
                    // "is_manual" => 1
                ];

                if ($current_right_to_work && $current_right_to_work->id == $request_data["id"]) {
                    $request_data["is_manual"] = 0;
                    $user_right_to_work_history =   EmployeeRightToWorkHistory::create($request_data);

                } else {
                    $user_right_to_work_history  =  tap(EmployeeRightToWorkHistory::where($user_right_ro_work_history_query_params))->update(
                        collect($request_data)->only([
                            'right_to_work_code',
                            'right_to_work_check_date',
                            'right_to_work_expiry_date',
                            'right_to_work_docs',

                            "is_manual",
                            'user_id',
                            "from_date",
                            "to_date",


                        ])->toArray()
                    )
                        ->first();
                }





                if (!$user_right_to_work_history) {
                    return response()->json([
                        "message" => "something went wrong."
                    ], 500);
                }




                // $this->moveUploadedFiles(collect($request_data["right_to_work_docs"])->pluck("file_name"),"right_to_work_docs");



                DB::commit();

                return response($user_right_to_work_history, 201);




        } catch (Exception $e) {
           DB::rollBack();
            return $this->sendError($e, 500, $request);
        }
    }



    public function getUserRightToWorkHistories(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('employee_right_to_work_history_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;
            $all_manager_department_ids = $this->get_all_departments_of_manager();

            $current_user_id = request()->user_id;
            $issue_date_column = 'right_to_work_check_date';
            $expiry_date_column = 'right_to_work_expiry_date';

            $current_visa = $this->getCurrentHistory(EmployeeRightToWorkHistory::class, 'current_right_to_work_id', $current_user_id, $issue_date_column, $expiry_date_column);




            $user_right_to_work_histories = EmployeeRightToWorkHistory::with([
                "creator" => function ($query) {
                    $query->select('users.id', 'users.first_Name','users.middle_Name',
                    'users.last_Name');
                },

            ])
            // ->where(["is_manual" => 1])
            ->whereHas("employee.department_user.department", function($query) use($all_manager_department_ids) {
              $query->whereIn("departments.id",$all_manager_department_ids);
           })
            ->when(!empty($request->search_key), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        $term = $request->search_key;
                        $query->where("employee_right_to_work_histories.name", "like", "%" . $term . "%");
                        //     ->orWhere("employee_right_to_work_histories.description", "like", "%" . $term . "%");
                    });
                })


                ->when(!empty($request->user_id), function ($query) use ($request) {
                    return $query->where('employee_right_to_work_histories.user_id', $request->user_id);
                })
                ->when(empty($request->user_id), function ($query) use ($request) {
                    return $query->where('employee_right_to_work_histories.user_id', $request->user()->id);
                })
                ->when(!empty($request->start_date), function ($query) use ($request) {
                    return $query->where('employee_right_to_work_histories.created_at', ">=", $request->start_date);
                })
                ->when(!empty($request->end_date), function ($query) use ($request) {
                    return $query->where('employee_right_to_work_histories.created_at', "<=", ($request->end_date . ' 23:59:59'));
                })
                ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                    return $query->orderBy("employee_right_to_work_histories.right_to_work_expiry_date", $request->order_by);
                }, function ($query) {
                    return $query->orderBy("employee_right_to_work_histories.right_to_work_expiry_date", "DESC");
                })
                ->when(!empty($request->per_page), function ($query) use ($request) {
                    return $query->paginate($request->per_page);
                }, function ($query) {
                    return $query->get();
                });;



            return response()->json($user_right_to_work_histories, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }

   


    public function getUserRightToWorkHistoryById($id, Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");

            if (!$request->user()->hasPermissionTo('employee_right_to_work_history_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;
            $all_manager_department_ids = $this->get_all_departments_of_manager();
            $user_right_to_work_history =  EmployeeRightToWorkHistory::where([
                "id" => $id,
                // "is_manual" => 1
            ])

            ->whereHas("employee.department_user.department", function($query) use($all_manager_department_ids) {
              $query->whereIn("departments.id",$all_manager_department_ids);
           })
                ->first();
            if (!$user_right_to_work_history) {

                return response()->json([
                    "message" => "no data found"
                ], 404);
            }

            return response()->json($user_right_to_work_history, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }




    public function deleteUserRightToWorkHistoriesByIds(Request $request, $ids)
    {

        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('employee_right_to_work_history_delete')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;
            $all_manager_department_ids = $this->get_all_departments_of_manager();
            $idsArray = explode(',', $ids);
            $existingIds = EmployeeRightToWorkHistory::whereIn('id', $idsArray)
            // ->where(["is_manual" => 1])
            ->whereHas("employee.department_user.department", function($query) use($all_manager_department_ids) {
              $query->whereIn("departments.id",$all_manager_department_ids);
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
            EmployeeRightToWorkHistory::destroy($existingIds);


            return response()->json(["message" => "data deleted sussfully","deleted_ids" => $existingIds], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
}
