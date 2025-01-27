<?php

namespace App\Http\Controllers;

use App\Http\Requests\SingleFileUploadRequest;
use App\Http\Requests\UserPayslipCreateRequest;
use App\Http\Requests\UserPayslipUpdateRequest;
use App\Http\Utils\BasicUtil;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\Department;
use App\Models\Payslip;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserPayslipController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil, BasicUtil;




    public function createUserPayslip(UserPayslipCreateRequest $request)
    {
        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");

                if (!$request->user()->hasPermissionTo('employee_payslip_create')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }

                $request_data = $request->validated();

                if(!empty($request_data["payment_record_file"])) {
                    $request_data["payment_record_file"] = $this->storeUploadedFiles($request_data["payment_record_file"],"","payment_record_file");
                    $this->makeFilePermanent($request_data["payment_record_file"],"");
                }
                if(!empty($request_data["payslip_file"])) {
                    $request_data["payslip_file"] = $this->storeUploadedFiles([$request_data["payslip_file"]],"","payment_record_file")[0];
                    $this->makeFilePermanent($request_data["payslip_file"],"");
                }

                $request_data["created_by"] = $request->user()->id;



                // Fetch user's bank details
                $user = User::find($request_data["user_id"]); // Assuming user_id is passed in the request

                if ($user) {
                    $request_data["bank_id"] = $user->bank_id;
                    $request_data["sort_code"] = $user->sort_code;
                    $request_data["account_number"] = $user->account_number;
                    $request_data["account_name"] = $user->account_name;
                } else {
                   throw new Exception ("User not found");
                }


                // Check if payment_method is bank_transfer and payment_notes is empty
                if ($request_data["payment_method"] === 'bank_transfer' && empty(trim($request_data["payment_notes"]))) {
                    // Build the payment notes with the user's bank details
                    $payment_notes = "Payment made to bank account number: " . $user->account_number . " Sort Code: " . $user->sort_code . " Account Name: " . $user->account_name;
                    $request_data["payment_notes"] = $payment_notes;
                }





                $user_payslip =  Payslip::create($request_data);






                // $this->moveUploadedFiles($request_data["payment_record_file"],"payment_record_file");




DB::commit();
                return response($user_payslip, 201);

        } catch (Exception $e) {
           DB::rollBack();



           try {

            if(!empty($request_data["payslip_file"])) {
                $this->moveUploadedFilesBack([$request_data["payslip_file"]],"","payment_record_file");
            }

        } catch (Exception $innerException) {

            error_log("Failed to move payment_record  files back: " . $innerException->getMessage());

        }

        try {
            if(!empty($request_data["payment_record_file"])) {
                $this->moveUploadedFilesBack($request_data["payment_record_file"],"","payment_record_file");
            }


        } catch (Exception $innerException) {

            error_log("Failed to move payment_record  files back: " . $innerException->getMessage());

        }






            return $this->sendError($e, 500, $request);
        }
    }


    public function updateUserPayslip(UserPayslipUpdateRequest $request)
    {

        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");

                if (!$request->user()->hasPermissionTo('employee_payslip_update')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }

                $request_data = $request->validated();

                if(!empty($request_data["payment_record_file"])) {
                    $request_data["payment_record_file"] = $this->storeUploadedFiles($request_data["payment_record_file"],"","payment_record_file");
                    $this->makeFilePermanent($request_data["payment_record_file"],"");
                }

                if(!empty($request_data["payslip_file"])) {
                    $request_data["payslip_file"] = $this->storeUploadedFiles([$request_data["payslip_file"]],"","payment_record_file")[0];
                    $this->makeFilePermanent($request_data["payslip_file"],"");
                }





  // Fetch user's bank details
  $user = User::find($request_data["user_id"]); // Assuming user_id is passed in the request

  if ($user) {
      $request_data["bank_id"] = $user->bank_id;
      $request_data["sort_code"] = $user->sort_code;
      $request_data["account_number"] = $user->account_number;
      $request_data["account_name"] = $user->account_name;
  } else {
     throw new Exception ("User not found");
  }

  // Check if payment_method is bank_transfer and payment_notes is empty
  if ($request_data["payment_method"] === 'bank_transfer' && empty(trim($request_data["payment_notes"]))) {
    // Build the payment notes with the user's bank details
    $payment_notes = "Payment made to bank account number: " . $user->account_number . " Sort Code: " . $user->sort_code . " Account Name: " . $user->account_name;
    $request_data["payment_notes"] = $payment_notes;
}

                $user_payslip_query_params = [
                    "id" => $request_data["id"],
                ];
                // $user_education_history_prev = UserEducationHistory::where($user_education_history_query_params)
                //     ->first();
                // if (!$user_education_history_prev) {
                //     return response()->json([
                //         "message" => "no user education history found"
                //     ], 404);
                // }

                $user_payslip = Payslip::where($user_payslip_query_params)->first();

                if ($user_payslip) {
                    $user_payslip->fill(
                        collect($request_data)->only([
                            'user_id',
                            'month',
                            'year',
                            'payment_method',
                            'payment_notes',
                            'payment_amount',
                            'payment_date',
                            'payslip_file',
                            'payment_record_file',
                            'payroll_id',
                            'gross_pay',
                            'tax',
                            'employee_ni_deduction',
                            'employer_ni',
                            // 'created_by'
                        ])->toArray()
                    )->save();
                }


                if (!$user_payslip) {
                    return response()->json([
                        "message" => "something went wrong."
                    ], 500);
                }



                // $this->moveUploadedFiles($request_data["payment_record_file"],"payment_record_file");

DB::commit();
                return response($user_payslip, 201);

        } catch (Exception $e) {
        DB::rollBack();

            return $this->sendError($e, 500, $request);
        }
    }


    public function getUserPayslips(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('employee_payslip_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;
            $all_manager_department_ids = $this->get_all_departments_of_manager();
            $user_payslips = Payslip::with([
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
                        $query->where("payslips.payment_notes", "like", "%" . $term . "%");
                        //     ->orWhere("user_education_histories.description", "like", "%" . $term . "%");
                    });
                })
                //    ->when(!empty($request->product_category_id), function ($query) use ($request) {
                //        return $query->where('product_category_id', $request->product_category_id);
                //    })

                ->when(!empty($request->user_id), function ($query) use ($request) {
                    return $query->where('payslips.user_id', $request->user_id);
                })
                ->when(empty($request->user_id), function ($query) use ($request) {
                    return $query->where('payslips.user_id', $request->user()->id);
                })
                ->when(!empty($request->start_date), function ($query) use ($request) {
                    return $query->where('payslips.created_at', ">=", $request->start_date);
                })
                ->when(!empty($request->end_date), function ($query) use ($request) {
                    return $query->where('payslips.created_at', "<=", ($request->end_date . ' 23:59:59'));
                })
                ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                    return $query->orderBy("payslips.id", $request->order_by);
                }, function ($query) {
                    return $query->orderBy("payslips.id", "DESC");
                })
                ->when(!empty($request->per_page), function ($query) use ($request) {
                    return $query->paginate($request->per_page);
                }, function ($query) {
                    return $query->get();
                });;



            return response()->json($user_payslips, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
 

    public function getUserPayslipById($id, Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('employee_payslip_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;
            $all_manager_department_ids = $this->get_all_departments_of_manager();
            $user_payslip =  Payslip::where([
                "id" => $id,
            ])
            ->whereHas("user.department_user.department", function($query) use($all_manager_department_ids) {
              $query->whereIn("departments.id",$all_manager_department_ids);
           })
                ->first();
            if (!$user_payslip) {

                return response()->json([
                    "message" => "no data found"
                ], 404);
            }

            return response()->json($user_payslip, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }



    public function deleteUserPayslipsByIds(Request $request, $ids)
    {

        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('employee_payslip_delete')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;
            $all_manager_department_ids = $this->get_all_departments_of_manager();
            $idsArray = explode(',', $ids);
            $existingIds = Payslip::whereIn('id', $idsArray)
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
            Payslip::destroy($existingIds);


            return response()->json(["message" => "data deleted sussfully","deleted_ids" => $existingIds], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }


}
