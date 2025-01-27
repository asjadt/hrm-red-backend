<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserJobHistoryCreateRequest;
use App\Http\Requests\UserJobHistoryUpdateRequest;
use App\Http\Utils\BasicUtil;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\Department;
use App\Models\UserJobHistory;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDF;
use Maatwebsite\Excel\Facades\Excel;

class UserJobHistoryController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil, BasicUtil;



      public function createUserJobHistory(UserJobHistoryCreateRequest $request)
      {
          try {
              $this->storeActivity($request, "DUMMY activity","DUMMY description");
              return DB::transaction(function () use ($request) {
                  if (!$request->user()->hasPermissionTo('employee_job_history_create')) {
                      return response()->json([
                          "message" => "You can not perform this action"
                      ], 401);
                  }

                  $request_data = $request->validated();







                  $request_data["created_by"] = $request->user()->id;

                  $user_job_history =  UserJobHistory::create($request_data);



                  return response($user_job_history, 201);
              });
          } catch (Exception $e) {
              error_log($e->getMessage());
              return $this->sendError($e, 500, $request);
          }
      }



      public function updateUserJobHistory(UserJobHistoryUpdateRequest $request)
      {

          try {
              $this->storeActivity($request, "DUMMY activity","DUMMY description");
              return DB::transaction(function () use ($request) {
                  if (!$request->user()->hasPermissionTo('employee_job_history_update')) {
                      return response()->json([
                          "message" => "You can not perform this action"
                      ], 401);
                  }
                  $business_id =  $request->user()->business_id;
                  $request_data = $request->validated();




                  $user_job_history_query_params = [
                      "id" => $request_data["id"],
                  ];
                  // $user_job_history_prev = UserJobHistory::where($user_job_history_query_params)
                  //     ->first();
                  // if (!$user_job_history_prev) {
                  //     return response()->json([
                  //         "message" => "no user job history found"
                  //     ], 404);
                  // }

                  $user_job_history  =  tap(UserJobHistory::where($user_job_history_query_params))->update(
                      collect($request_data)->only([
                        'user_id',
                        'company_name',
                        'country',
                        'job_title',
                        'employment_start_date',
                        'employment_end_date',
                        'responsibilities',
                        'supervisor_name',
                        'contact_information',
                        'work_location',
                        'achievements',
                        // 'created_by',

                      ])->toArray()
                  )
                      // ->with("somthing")

                      ->first();
                  if (!$user_job_history) {
                      return response()->json([
                          "message" => "something went wrong."
                      ], 500);
                  }

                  return response($user_job_history, 201);
              });
          } catch (Exception $e) {
              error_log($e->getMessage());
              return $this->sendError($e, 500, $request);
          }
      }



      public function getUserJobHistories(Request $request)
      {
          try {
              $this->storeActivity($request, "DUMMY activity","DUMMY description");
              if (!$request->user()->hasPermissionTo('employee_job_history_view')) {
                  return response()->json([
                      "message" => "You can not perform this action"
                  ], 401);
              }
              $business_id =  $request->user()->business_id;
              $all_manager_department_ids = $this->get_all_departments_of_manager();
              $user_job_histories = UserJobHistory::with([
                  "creator" => function ($query) {
                      $query->select('users.id', 'users.first_Name','users.middle_Name',
                      'users.last_Name');
                  },

              ])
              ->whereHas("user.department_user.department", function($query) use($all_manager_department_ids) {
                $query->whereIn("departments.id",$all_manager_department_ids);
             })
              ->when(!empty($request->search_key), function ($query) use ($request) {
                      return $query->where(function ($query) use ($request) {
                          $term = $request->search_key;
                          $query->where("user_job_histories.name", "like", "%" . $term . "%");
                          //     ->orWhere("user_job_histories.description", "like", "%" . $term . "%");
                      });
                  })
                  //    ->when(!empty($request->product_category_id), function ($query) use ($request) {
                  //        return $query->where('product_category_id', $request->product_category_id);
                  //    })

                  ->when(!empty($request->user_id), function ($query) use ($request) {
                      return $query->where('user_job_histories.user_id', $request->user_id);
                  })
                  ->when(empty($request->user_id), function ($query) use ($request) {
                      return $query->where('user_job_histories.user_id', $request->user()->id);
                  })
                  ->when(!empty($request->start_date), function ($query) use ($request) {
                      return $query->where('user_job_histories.created_at', ">=", $request->start_date);
                  })
                  ->when(!empty($request->end_date), function ($query) use ($request) {
                      return $query->where('user_job_histories.created_at', "<=", ($request->end_date . ' 23:59:59'));
                  })
                  ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                      return $query->orderBy("user_job_histories.id", $request->order_by);
                  }, function ($query) {
                      return $query->orderBy("user_job_histories.id", "DESC");
                  })
                  ->when(!empty($request->per_page), function ($query) use ($request) {
                      return $query->paginate($request->per_page);
                  }, function ($query) {
                      return $query->get();
                  });;

                  if (!empty($request->response_type) && in_array(strtoupper($request->response_type), ['PDF', 'CSV'])) {
                    if (strtoupper($request->response_type) == 'PDF') {
                        $pdf = PDF::loadView('pdf.users', ["user_job_histories" => $user_job_histories]);
                        return $pdf->download(((!empty($request->file_name) ? $request->file_name : 'employee') . '.pdf'));
                    } elseif (strtoupper($request->response_type) === 'CSV') {

                        // return Excel::download(new UsersExport($users), ((!empty($request->file_name) ? $request->file_name : 'employee') . '.csv'));

                    }
                } else {
                    return response()->json($user_job_histories, 200);
                }


          } catch (Exception $e) {

              return $this->sendError($e, 500, $request);
          }
      }

      

      public function getUserJobHistoryById($id, Request $request)
      {
          try {
              $this->storeActivity($request, "DUMMY activity","DUMMY description");
              if (!$request->user()->hasPermissionTo('employee_job_history_view')) {
                  return response()->json([
                      "message" => "You can not perform this action"
                  ], 401);
              }
              $business_id =  $request->user()->business_id;
              $all_manager_department_ids = $this->get_all_departments_of_manager();
              $user_job_history =  UserJobHistory::where([
                  "id" => $id,
              ])
              ->whereHas("user.department_user.department", function($query) use($all_manager_department_ids) {
                $query->whereIn("departments.id",$all_manager_department_ids);
             })
                  ->first();
              if (!$user_job_history) {

                  return response()->json([
                      "message" => "no data found"
                  ], 404);
              }

              return response()->json($user_job_history, 200);
          } catch (Exception $e) {

              return $this->sendError($e, 500, $request);
          }
      }



      public function deleteUserJobHistoriesByIds(Request $request, $ids)
      {

          try {
              $this->storeActivity($request, "DUMMY activity","DUMMY description");
              if (!$request->user()->hasPermissionTo('employee_job_history_delete')) {
                  return response()->json([
                      "message" => "You can not perform this action"
                  ], 401);
              }
              $business_id =  $request->user()->business_id;
              $all_manager_department_ids = $this->get_all_departments_of_manager();
              $idsArray = explode(',', $ids);
              $existingIds = UserJobHistory::whereIn('id', $idsArray)
              ->whereHas("user.department_user.department", function($query) use($all_manager_department_ids) {
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
              UserJobHistory::destroy($existingIds);


              return response()->json(["message" => "data deleted sussfully","deleted_ids" => $existingIds], 200);
          } catch (Exception $e) {

              return $this->sendError($e, 500, $request);
          }
      }
}
