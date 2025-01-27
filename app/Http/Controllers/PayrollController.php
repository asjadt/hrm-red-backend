<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayrollCreateRequest;
use App\Http\Utils\BasicUtil;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\PayrunUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\Department;
use App\Models\Payroll;
use App\Models\Payrun;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil, PayrunUtil, BasicUtil;



    public function createPayroll(PayrollCreateRequest $request)
    {
        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

                if (!$request->user()->hasPermissionTo('payrun_create')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }

                $request_data = $request->validated();


                $payrun = Payrun::where([
                    "id" => $request_data["payrun_id"],
                    "business_id" => auth()->user()->business_id
                ])

                    ->first();

                if (!$payrun) {
                    $error = [
                        "message" => "The given data was invalid.",
                        "errors" => ["payrun_id" => ["The payrun_id field is invalid."]]
                    ];
                    throw new Exception(json_encode($error), 500);
                }

                $employees = User::whereIn("id", $request_data["users"])
                    ->whereDoesntHave("payrolls", function ($q) use ($payrun) {
                        $q->where("payrolls.start_date", $payrun->start_date)
                            ->where("payrolls.end_date", $payrun->end_date);
                    })




                    // ->where(function ($query) use ($payrun) {
                    //     $query->whereHas("departments.payrun_department", function ($query) use ($payrun) {
                    //         $query->where("payrun_departments.payrun_id", $payrun->id);
                    //     })
                    //         ->orWhereHas("payrun_user", function ($query) use ($payrun) {
                    //             $query->where("payrun_users.payrun_id", $payrun->id);
                    //         });
                    // })




                    ->get();






                $processed_employees =  $this->process_payrun($payrun, $employees, $request_data["start_date"], $request_data["end_date"], true, true);

                DB::commit();
                return response()->json($processed_employees, 201);

        } catch (Exception $e) {
            DB::rollBack();
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }






    public function getPayrolls(Request $request)
    {
        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            if (!$request->user()->hasPermissionTo('payrun_create')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }


            $all_manager_department_ids = $this->get_all_departments_of_manager();

            if (empty($request->payrun_id)) {
                $error = [
                    "message" => "The given data was invalid.",
                    "errors" => ["payrun_id" => ["The payrun_id field is required."]]
                ];
                throw new Exception(json_encode($error), 422);
            }







            $payrun = Payrun::where([
                "id" => $request->payrun_id,
                "business_id" => auth()->user()->business_id
            ])
                ->where(function ($query) use ($all_manager_department_ids) {
                    $query->whereHas("departments", function ($query) use ($all_manager_department_ids) {
                        $query->whereIn("departments.id", $all_manager_department_ids);
                    })
                        ->orWhereHas("users.department_user.department_user.department", function ($query) use ($all_manager_department_ids) {
                            $query->whereIn("departments.id", $all_manager_department_ids);
                        });
                })
                ->first();

            if (!$payrun) {
                $error = [
                    "message" => "The given data was invalid.",
                    "errors" => ["payrun_id" => ["The payrun_id field is invalid."]]
                ];
                throw new Exception(json_encode($error), 422);
            }




            $employees = User::where([
                "business_id" => $payrun->business_id,
                "is_active" => 1
            ])
                ->whereDoesntHave("payrolls", function ($q) use ($payrun) {
                    $q->where("payrolls.start_date", $payrun->start_date)
                        ->where("payrolls.end_date", $payrun->end_date);
                })

                ->whereNotIn("id", [auth()->user()->id])
                ->whereHas("department_user.department", function ($query) use ($all_manager_department_ids) {
                    $query->whereIn("departments.id", $all_manager_department_ids);
                })
                ->when(!empty($request->user_ids), function ($query) use ($request, $all_manager_department_ids) {
                    $user_ids = explode(',', $request->user_ids);
                    $query->whereIn("users.id", $user_ids);
                })
                // ->where(function ($query) use ($payrun) {
                //     $query->whereHas("departments.payrun_department", function ($query) use ($payrun) {
                //         $query->where("payrun_departments.payrun_id", $payrun->id);
                //     })
                //         ->orWhereHas("payrun_user", function ($query) use ($payrun) {
                //             $query->where("payrun_users.payrun_id", $payrun->id);
                //         });
                // })


                ->get();


            $processed_employees =  $this->process_payrun($payrun, $employees, $request->start_date, $request->end_date, true, false);

            DB::commit();

            return response()->json($processed_employees, 200);

        } catch (Exception $e) {
            DB::rollBack();
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }





     public function getPayrollsReport(Request $request)
     {
         DB::beginTransaction();
         try {
             $this->storeActivity($request, "DUMMY activity", "DUMMY description");

             if (!$request->user()->hasPermissionTo('payrun_create')) {
                 return response()->json([
                     "message" => "You can not perform this action"
                 ], 401);
             }

    $numberOfMonths = !empty($request->months)?$request->months:12;
             $all_manager_department_ids = $this->get_all_departments_of_manager();

             $departments = Department::whereIn("id",$all_manager_department_ids)->get();

 // Initialize an array to store the report data
 $reportData = [];

   // Get the current date
   $currentDate = Carbon::now();

   // Loop through the specified number of months
   for ($i = 0; $i < $numberOfMonths; $i++) {
    // Calculate the start and end dates for each month
    $startDate = $currentDate->copy()->startOfMonth()->subMonths($i);
    $endDate = $currentDate->copy()->endOfMonth()->subMonths($i);

    $data = [];
    foreach($departments as  $department) {
        $departmentData = [];

        $departmentData["department"] = $department;
        $departmentData["dates"] = [$startDate, $endDate];

      $payrolls =    Payroll::
    whereBetween('payrolls.end_date', [$startDate, $endDate])
    ->whereHas("user.department_user.department",function($query) use($department) {
          $query->whereIn("departments.id",[$department->id]);
    })

    ->get();

    $total_salary = $payrolls->sum(function ($payroll) {
        return $payroll->regular_hours_salary + $payroll->overtime_hours_salary; // Add more columns if needed
    });

    $departmentData["total_salary"] = $total_salary;
       // Push the department data into the $data array
       $data[] = $departmentData;

    }



    // Store the data for the current month in the report array
    $reportData[$startDate->format('F Y')] = $data;
}

             DB::commit();

             return response()->json($reportData, 200);

         } catch (Exception $e) {
             DB::rollBack();
             error_log($e->getMessage());
             return $this->sendError($e, 500, $request);
         }
     }



     public function getPendingPayrollUsers(Request $request)
     {
         DB::beginTransaction();
         try {
             $this->storeActivity($request, "DUMMY activity", "DUMMY description");

             if (!$request->user()->hasPermissionTo('payrun_create')) {
                 return response()->json([
                     "message" => "You can not perform this action"
                 ], 401);
             }

   $all_manager_department_ids = $this->get_all_departments_of_manager();

   if (empty($request->start_date)) {
    $error = [
        "message" => "The given data was invalid.",
        "errors" => ["start_date" => ["The start_date field is required."]]
    ];
    throw new Exception(json_encode($error), 422);
}
if (empty($request->end_date)) {
    $error = [
        "message" => "The given data was invalid.",
        "errors" => ["end_date" => ["The end_date field is required."]]
    ];
    throw new Exception(json_encode($error), 422);
}




             $employees = User::where([
                 "business_id" => auth()->user()->business_id,
                 "is_active" => 1
             ])


                 ->whereDoesntHave("payrolls", function ($q) use ($request) {
                     $q->where("payrolls.start_date",">=", $request->start_date)
                         ->where("payrolls.end_date","<=", $request->end_date);
                 })

                 ->whereNotIn("id", [auth()->user()->id])

                 ->whereHas("department_user.department", function ($query) use ($all_manager_department_ids) {
                     $query->whereIn("departments.id", $all_manager_department_ids);
                 })




                 ->when(!empty($request->departments), function ($query) use ($request) {
                    $department_ids = explode(',', $request->departments);
                    $query->whereHas("department_user.department", function ($query) use($department_ids) {
                        $query->whereIn("departments.id", $department_ids);
                    });
                 })




                 ->when(!empty($request->user_ids), function ($query) use ($request, $all_manager_department_ids) {
                     $user_ids = explode(',', $request->user_ids);
                     $query->whereIn("users.id", $user_ids);
                 })




                 ->get();

                 $processed_employees = collect($this->estimate_payrun_data($employees, $request->start_date, $request->end_date));

                 // Filter out users with both regular_hours_salary and overtime_hours_salary as 0
                 $filtered_employees = $processed_employees->filter(function($employee) {
                     return $employee['payroll']['regular_hours_salary'] != 0 || $employee['payroll']['overtime_hours_salary'] != 0;
                 });

                 // Convert the filtered collection back to an array if needed
                 $filtered_employees_array = $filtered_employees->values()->all();





             DB::commit();

             return response()->json($filtered_employees_array, 200);

         } catch (Exception $e) {
             DB::rollBack();

             return $this->sendError($e, 500, $request);
         }
     }
    

    public function getPayrollList(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            if (!$request->user()->hasPermissionTo('payrun_create')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }


              $all_manager_department_ids = $this->get_all_departments_of_manager();
            //  if(!$request->payrun_id) {
            //     $error = [ "message" => "The given data was invalid.",
            //     "errors" => ["payrun_id"=>["The payrun_id field is required."]]
            //     ];
            //         throw new Exception(json_encode($error),422);
            //  }







            if (!empty($request->payrun_id)) {
                $payrun = Payrun::where([

                    "business_id" => auth()->user()->business_id
                ])


                    ->where(function ($query) use ($all_manager_department_ids) {
                        $query->whereHas("departments", function ($query) use ($all_manager_department_ids) {
                            $query->whereIn("departments.id", $all_manager_department_ids);
                        })
                            ->orWhereHas("users.department_user.department", function ($query) use ($all_manager_department_ids) {
                                $query->whereIn("departments.id", $all_manager_department_ids);
                            });
                    })
                    ->first();

                if (!$payrun) {
                    $error = [
                        "message" => "The given data was invalid.",
                        "errors" => ["payrun_id" => ["The payrun_id field is invalid."]]
                    ];
                    throw new Exception(json_encode($error), 422);
                }
            }



            $payrolls = Payroll::with("user", "payrun")
                ->when(!empty($request->payrun_id), function ($query) use ($request) {
                    $query->where([
                        "payrun_id" => $request->payrun_id
                    ]);
                })

                ->when(!empty($request->user_ids), function ($query) use ($request) {
                    $user_ids = explode(',', $request->user_ids);
                    $query->orWhereHas("user", function ($query) use ($user_ids) {
                        $query->whereIn("users..id", $user_ids);
                    });
                })
                ->where(function ($query) use ($all_manager_department_ids) {
                    $query->whereHas("user.department_user.department", function ($query) use ($all_manager_department_ids) {
                        $query->whereIn("departments.id", $all_manager_department_ids);
                    });
                })
                ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                    return $query->orderBy("payrolls.id", $request->order_by);
                }, function ($query) {
                    return $query->orderBy("payrolls.id", "DESC");
                })

                ->when(!empty($request->per_page), function ($query) use ($request) {
                    return $query->paginate($request->per_page);
                }, function ($query) {
                    return $query->get();
                });


            return response()->json($payrolls, 200);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }
}
