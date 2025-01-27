<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserVisaHistoryCreateRequest;
use App\Http\Requests\UserVisaHistoryUpdateRequest;
use App\Http\Utils\BasicUtil;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\Department;

use App\Models\EmployeeVisaDetailHistory;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserVisaHistoryController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil, BasicUtil;




    public function createUserVisaHistory(UserVisaHistoryCreateRequest $request)
    {
        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");

                if (!$request->user()->hasPermissionTo('employee_visa_history_create')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }

                $request_data = $request->validated();
                $request_data["visa_docs"] =   $this->storeUploadedFiles($request_data["visa_docs"],"file_name","visa_docs");
                $this->makeFilePermanent($request_data["visa_docs"],"file_name");

                $request_data["created_by"] = $request->user()->id;
                $request_data["business_id"] = auth()->user()->business_id;
                $request_data["is_manual"] = 1;




                $user_visa_history =  EmployeeVisaDetailHistory::create($request_data);

                // $this->moveUploadedFiles(collect($request_data["visa_docs"])->pluck("file_name"),"visa_docs");

                DB::commit();

                return response($user_visa_history, 201);

        } catch (Exception $e) {
            DB::rollBack();



          try {


            $this->moveUploadedFilesBack($request_data["visa_docs"],"file_name","visa_docs");



        } catch (Exception $innerException) {

            error_log("Failed to move right to work docs  files back: " . $innerException->getMessage());

        }

            return $this->sendError($e, 500, $request);
        }
    }


    public function updateUserVisaHistory(UserVisaHistoryUpdateRequest $request)
    {

        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");

                if (!$request->user()->hasPermissionTo('employee_visa_history_update')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }


                $request_data = $request->validated();

                $request_data["visa_docs"] =   $this->storeUploadedFiles($request_data["visa_docs"],"file_name","visa_docs");
                $this->makeFilePermanent($request_data["visa_docs"],"file_name");


                $request_data["created_by"] = auth()->user()->id;
                $request_data["is_manual"] = 1;
                $request_data["business_id"] = auth()->user()->business_id;
                $all_manager_department_ids = $this->get_all_departments_of_manager();


                $current_user_id =  $request_data["user_id"];
                $issue_date_column = 'visa_issue_date';
                $expiry_date_column = 'visa_expiry_date';

                $current_visa = $this->getCurrentHistory(EmployeeVisaDetailHistory::class, 'current_visa_id', $current_user_id, $issue_date_column, $expiry_date_column);


                $user_visa_history_query_params = [
                    "id" => $request_data["id"],
                    // "is_manual" => 1
                ];

                if ($current_visa && $current_visa->id == $request_data["id"]) {
                    $request_data["is_manual"] = 0;
                    $user_visa_history =   EmployeeVisaDetailHistory::create($request_data);

                } else {
                    $user_visa_history  =  tap(EmployeeVisaDetailHistory::where($user_visa_history_query_params))->update(
                        collect($request_data)->only([
                            'BRP_number',
                            "visa_issue_date",
                            "visa_expiry_date",
                            "place_of_issue",
                            "visa_docs",


                            "is_manual",
                            'user_id',
                            "from_date",
                            "to_date",


                        ])->toArray()
                    )
                        ->first();
                }





                if (!$user_visa_history) {
                    return response()->json([
                        "message" => "something went wrong."
                    ], 500);
                }



                // $this->moveUploadedFiles(collect($request_data["visa_docs"])->pluck("file_name"),"visa_docs");


                DB::commit();

                return response($user_visa_history, 201);








        } catch (Exception $e) {
         DB::rollBack();
            return $this->sendError($e, 500, $request);
        }
    }




    public function getUserVisaHistories(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('employee_visa_history_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;
            $all_manager_department_ids = $this->get_all_departments_of_manager();


            $current_user_id = request()->user_id;
            $issue_date_column = 'visa_issue_date';
            $expiry_date_column = 'visa_expiry_date';
            $current_visa = $this->getCurrentHistory(EmployeeVisaDetailHistory::class, 'current_visa_id', $current_user_id, $issue_date_column, $expiry_date_column);



            $user_visa_histories = EmployeeVisaDetailHistory::with([
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
                        $query->where("employee_visa_detail_histories.name", "like", "%" . $term . "%");
                        //     ->orWhere("employee_visa_detail_histories.description", "like", "%" . $term . "%");
                    });
                })
                //    ->when(!empty($request->product_category_id), function ($query) use ($request) {
                //        return $query->where('product_category_id', $request->product_category_id);
                //    })

                ->when(!empty($request->user_id), function ($query) use ($request) {
                    return $query->where('employee_visa_detail_histories.user_id', $request->user_id);
                })
                ->when(empty($request->user_id), function ($query) use ($request) {
                    return $query->where('employee_visa_detail_histories.user_id', $request->user()->id);
                })
                ->when(!empty($request->start_date), function ($query) use ($request) {
                    return $query->where('employee_visa_detail_histories.created_at', ">=", $request->start_date);
                })
                ->when(!empty($request->end_date), function ($query) use ($request) {
                    return $query->where('employee_visa_detail_histories.created_at', "<=", ($request->end_date . ' 23:59:59'));
                })
                ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                    return $query->orderBy("employee_visa_detail_histories.visa_expiry_date", $request->order_by);
                }, function ($query) {
                    return $query->orderBy("employee_visa_detail_histories.visa_expiry_date", "DESC");
                })
                ->when(!empty($request->per_page), function ($query) use ($request) {
                    return $query->paginate($request->per_page);
                }, function ($query) {
                    return $query->get();
                });;



            return response()->json($user_visa_histories, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }

    

    public function getUserVisaHistoryById($id, Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('employee_visa_history_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;
            $all_manager_department_ids = $this->get_all_departments_of_manager();
            $user_visa_history =  EmployeeVisaDetailHistory::where([
                "id" => $id,
                // "is_manual" => 1
            ])

            ->whereHas("employee.department_user.department", function($query) use($all_manager_department_ids) {
              $query->whereIn("departments.id",$all_manager_department_ids);
           })
                ->first();
            if (!$user_visa_history) {

                return response()->json([
                    "message" => "no data found"
                ], 404);
            }

            return response()->json($user_visa_history, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }




    public function deleteUserVisaHistoriesByIds(Request $request, $ids)
    {

        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('employee_visa_history_delete')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;
            $all_manager_department_ids = $this->get_all_departments_of_manager();
            $idsArray = explode(',', $ids);
            $existingIds = EmployeeVisaDetailHistory::whereIn('id', $idsArray)
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
            EmployeeVisaDetailHistory::destroy($existingIds);


            return response()->json(["message" => "data deleted sussfully","deleted_ids" => $existingIds], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
}
