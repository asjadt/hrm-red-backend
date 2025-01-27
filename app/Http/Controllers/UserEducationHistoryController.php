<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserEducationHistoryCreateRequest;
use App\Http\Requests\UserEducationHistoryUpdateRequest;
use App\Http\Utils\BasicUtil;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\Department;
use App\Models\UserEducationHistory;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserEducationHistoryController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil, BasicUtil;



    public function createUserEducationHistory(UserEducationHistoryCreateRequest $request)
    {

        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            // return DB::transaction(function () use ($request) {
                if (!$request->user()->hasPermissionTo('employee_education_history_create')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }

                $request_data = $request->validated();
                $request_data["attachments"] = $this->storeUploadedFiles($request_data["attachments"],"","education_docs");
                $this->makeFilePermanent($request_data["attachments"],"");






                $request_data["created_by"] = $request->user()->id;

                $user_education_history =  UserEducationHistory::create($request_data);


                // $this->moveUploadedFiles($request_data["attachments"],"education_docs");

                DB::commit();

                return response($user_education_history, 201);

        } catch (Exception $e) {



             try {
                $this->moveUploadedFilesBack($request_data["attachments"],"","education_docs");
            } catch (Exception $innerException) {
                error_log("Failed to move education docs files back: " . $innerException->getMessage());
            }




        DB::rollBack();
            return $this->sendError($e, 500, $request);
        }
    }



    public function updateUserEducationHistory(UserEducationHistoryUpdateRequest $request)
    {

        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");

                if (!$request->user()->hasPermissionTo('employee_education_history_update')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }

                $request_data = $request->validated();


                $user_education_history_query_params = [
                    "id" => $request_data["id"],
                ];
             $user_education_history = UserEducationHistory::where($user_education_history_query_params)->first();

                    $this->moveUploadedFilesBack($user_education_history->attachments,"","education_docs");

                    $request_data["attachments"] = $this->storeUploadedFiles($request_data["attachments"],"","education_docs");
                    $this->makeFilePermanent($request_data["attachments"],"");


             if($user_education_history) {
                $user_education_history->fill( collect($request_data)->only([
                    'user_id',
                    'degree',
                    'major',
                    'school_name',
                    'graduation_date',
                    'start_date',

                    'achievements',
                    'description',
                    'address',
                    'country',
                    'city',
                    'postcode',
                    'is_current',
                    "attachments"

                ])->toArray());
                $user_education_history->save();

             } else {
                    return response()->json([
                        "message" => "something went wrong."
                    ], 500);
             }
                // $this->moveUploadedFiles($request_data["attachments"],"education_docs");

                DB::commit();
                return response($user_education_history, 201);

        } catch (Exception $e) {
            DB::rollBack();
            try {
                $this->moveUploadedFilesBack($request_data["attachments"],"","education_docs");
            } catch (Exception $innerException) {
                error_log("Failed to move education docs files back: " . $innerException->getMessage());
            }
            return $this->sendError($e, 500, $request);
        }
    }



    public function getUserEducationHistories(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('employee_education_history_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;


            $all_manager_department_ids = $this->get_all_departments_of_manager();


            $user_education_histories = UserEducationHistory::with([
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
                        $query->where("user_education_histories.name", "like", "%" . $term . "%");
                        //     ->orWhere("user_education_histories.description", "like", "%" . $term . "%");
                    });
                })
                //    ->when(!empty($request->product_category_id), function ($query) use ($request) {
                //        return $query->where('product_category_id', $request->product_category_id);
                //    })

                ->when(!empty($request->user_id), function ($query) use ($request) {
                    return $query->where('user_education_histories.user_id', $request->user_id);
                })
                ->when(empty($request->user_id), function ($query) use ($request) {
                    return $query->where('user_education_histories.user_id', $request->user()->id);
                })
                ->when(!empty($request->start_date), function ($query) use ($request) {
                    return $query->where('user_education_histories.created_at', ">=", $request->start_date);
                })
                ->when(!empty($request->end_date), function ($query) use ($request) {
                    return $query->where('user_education_histories.created_at', "<=", ($request->end_date . ' 23:59:59'));
                })
                ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                    return $query->orderBy("user_education_histories.id", $request->order_by);
                }, function ($query) {
                    return $query->orderBy("user_education_histories.id", "DESC");
                })
                ->when(!empty($request->per_page), function ($query) use ($request) {
                    return $query->paginate($request->per_page);
                }, function ($query) {
                    return $query->get();
                });;



            return response()->json($user_education_histories, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }

    


    public function getUserEducationHistoryById($id, Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('employee_education_history_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;
            $all_manager_department_ids = $this->get_all_departments_of_manager();
            $user_education_history =  UserEducationHistory::where([
                "id" => $id,
            ])
            ->whereHas("user.department_user.department", function($query) use($all_manager_department_ids) {
              $query->whereIn("departments.id",$all_manager_department_ids);
           })
                ->first();
            if (!$user_education_history) {

                return response()->json([
                    "message" => "no data found"
                ], 404);
            }

            return response()->json($user_education_history, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }




    public function deleteUserEducationHistoriesByIds(Request $request, $ids)
    {

        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('employee_education_history_delete')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;
            $all_manager_department_ids = $this->get_all_departments_of_manager();
            $idsArray = explode(',', $ids);
            $existingIds = UserEducationHistory::whereIn('id', $idsArray)
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
            UserEducationHistory::destroy($existingIds);


            return response()->json(["message" => "data deleted sussfully","deleted_ids" => $existingIds], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
}
