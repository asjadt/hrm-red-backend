<?php

namespace App\Http\Controllers;

use App\Exports\PayrunsExport;
use App\Http\Requests\GetIdRequest;
use App\Http\Requests\PayrunCreateRequest;
use App\Http\Requests\PayrunUpdateRequest;
use App\Http\Utils\BasicUtil;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\PayrunUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\Department;
use App\Models\Payrun;
use App\Models\PayrunDepartment;
use App\Models\PayrunUser;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use PDF;
use Maatwebsite\Excel\Facades\Excel;

class PayrunController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil, BasicUtil, PayrunUtil;



    public function createPayrun(PayrunCreateRequest $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            return DB::transaction(function () use ($request) {
                if (!$request->user()->hasPermissionTo('payrun_create')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }

                $request_data = $request->validated();


                $request_data["business_id"] = $request->user()->business_id;
                $request_data["is_active"] = true;
                $request_data["created_by"] = $request->user()->id;
                $payrun =  Payrun::create($request_data);

                $request_data['departments'] = Department::where([
                    "business_id" => auth()->user()->business_id
                ])
                ->pluck("id");




                $payrun->departments()->sync($request_data['departments']);


                if(!empty($request_data['users'])){
                    $employees = User::whereIn("id", $request_data["users"])
                    ->whereDoesntHave("payrolls", function ($q) use ($payrun) {
                        $q->where("payrolls.start_date", $payrun->start_date)
                            ->where("payrolls.end_date", $payrun->end_date);
                    })
                    ->get();

                $processed_employees =  $this->process_payrun($payrun, $employees, $request_data["start_date"], $request_data["end_date"], true, true);
                $payrun->users()->sync($request_data['users'], []);
                }






                // $payrun->departments()->sync($request_data['departments'], []);


                // $payrun->load([
                //   "payrolls"
                // ]);


                return response($payrun, 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }


    public function updatePayrun(PayrunUpdateRequest $request)
    {


        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            return DB::transaction(function () use ($request) {
                if (!$request->user()->hasPermissionTo('payrun_update')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }
                $business_id =  $request->user()->business_id;
                $request_data = $request->validated();

                $payrun_query_params = [
                    "id" => $request_data["id"],
                    "business_id" => $business_id
                ];

                $payrun  =  tap(Payrun::where($payrun_query_params))->update(
                    collect($request_data)->only([
                        "period_type",
                        "start_date",
                        "end_date",
                        "generating_type",
                        "consider_type",
                        "consider_overtime",
                        "notes",

                        "consider_overtime",
                        "notes",
                    ])->toArray()
                )
                    // ->with("somthing")

                    ->first();
                if (!$payrun) {
                    return response()->json([
                        "message" => "something went wrong."
                    ], 500);
                }


                // $payrun->departments()->sync($request_data['departments'], []);
                // $payrun->users()->sync($request_data['users'], []);





                return response($payrun, 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }



     public function toggleActivePayrun(GetIdRequest $request)
     {

         try {
             $this->storeActivity($request, "DUMMY activity","DUMMY description");
             if (!$request->user()->hasPermissionTo('user_update')) {
                 return response()->json([
                     "message" => "You can not perform this action"
                 ], 401);
             }
             $request_data = $request->validated();

             $all_manager_department_ids = $this->get_all_departments_of_manager();

            $payrun = Payrun::where([
                "id" => $request_data["id"],
                "business_id" => auth()->user()->business_id
            ])
                ->first();
            if (!$payrun) {

                return response()->json([
                    "message" => "no payrun found"
                ], 404);
            }


             $payrun_department_exists = PayrunDepartment::where([
                "payrun_id" => $payrun->id
            ])
            ->whereIn("department_id",$all_manager_department_ids)
            ->exists();


            $payrun_user_exists = PayrunUser::where([
                "payrun_id" => $payrun->id
            ])
            ->whereHas("user.department_user.department", function($query) use($all_manager_department_ids) {
                 $query->whereIn("departments.id",$all_manager_department_ids);
            })
            ->exists();




            if((!$payrun_department_exists) && !$payrun_user_exists){

                return response()->json([
                    "message" => "You don't have access to this payrun"
                ], 403);
            }


             $payrun->update([
                 'is_active' => !$payrun->is_active
             ]);

             return response()->json(['message' => 'payrun status updated successfully'], 200);
         } catch (Exception $e) {
             error_log($e->getMessage());
             return $this->sendError($e, 500, $request);
         }
     }



    public function getPayruns(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('payrun_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;

            $all_manager_department_ids = $this->get_all_departments_of_manager();

            $payruns = Payrun::withCount("payrolls")
            ->where(
                [
                    "business_id" => $business_id
                ]
            )
            ->where(function($query) use($all_manager_department_ids) {
                $query->whereHas("departments", function($query) use($all_manager_department_ids) {
                    $query->whereIn("departments.id",$all_manager_department_ids);
                 })
                 ->orWhereHas("users.department_user.department", function($query) use($all_manager_department_ids) {
                    $query->whereIn("departments.id",$all_manager_department_ids);
                 });
            })

            ->when(!empty($request->period), function ($query) use ($request) {
                return $query->where('payruns.period_type', $request->period);
            })

            ->when(!empty($request->type), function ($query) use ($request) {
                return $query->where('payruns.generating_type', $request->type);
            })
            ->when(isset($request->is_considering_overtime), function ($query) use ($request) {
                return $query->where('payruns.consider_overtime', intval($request->is_considering_overtime));
            })
            ->when(!empty($request->date), function ($query) use ($request) {
                return $query->where('payruns.start_date', "<=", $request->date)
                    ->where('payruns.end_date', ">=", $request->date);
            })


                // ->when(!empty($request->search_key), function ($query) use ($request) {
                //     return $query->where(function ($query) use ($request) {
                //         $term = $request->search_key;
                //         $query->where("name", "like", "%" . $term . "%")
                //             ->orWhere("description", "like", "%" . $term . "%");
                //     });
                // })
                ->when(isset($request->is_active), function ($query) use ($request) {
                    return $query->where('payruns.is_active', intval($request->is_active));
                })
                ->when(!empty($request->start_date), function ($query) use ($request) {
                    return $query->where('payruns.start_date', ">=", $request->start_date);
                })
                ->when(!empty($request->end_date), function ($query) use ($request) {
                    return $query->where('payruns.end_date', "<=", ($request->end_date . ' 23:59:59'));
                })
                ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                    return $query->orderBy("payruns.id", $request->order_by);
                }, function ($query) {
                    return $query->orderBy("payruns.id", "DESC");
                })
                ->when(!empty($request->per_page), function ($query) use ($request) {
                    return $query->paginate($request->per_page);
                }, function ($query) {
                    return $query->get();
                });

                if (!empty($request->response_type) && in_array(strtoupper($request->response_type), ['PDF', 'CSV'])) {
                    if (strtoupper($request->response_type) == 'PDF') {
                        $pdf = PDF::loadView('pdf.payruns', ["payruns" => $payruns]);
                        return $pdf->download(((!empty($request->file_name) ? $request->file_name : 'employee') . '.pdf'));
                    } elseif (strtoupper($request->response_type) === 'CSV') {

                        return Excel::download(new PayrunsExport($payruns), ((!empty($request->file_name) ? $request->file_name : 'employee') . '.csv'));
                    }
                } else {
                    return response()->json($payruns, 200);
                }

            return response()->json($payruns, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }

    
    public function getPayrunById($id, Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('payrun_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }


            $all_manager_department_ids = $this->get_all_departments_of_manager();

           $payrun = Payrun::with("departments","users")
           ->where([
               "id" => $id,
               "business_id" => auth()->user()->business_id
           ])
               ->first();

           if (!$payrun) {

               return response()->json([
                   "message" => "no payrun found"
               ], 404);
           }


           $payrun_department_exists = PayrunDepartment::where([
            "payrun_id" => $payrun->id
        ])
        ->whereIn("department_id",$all_manager_department_ids)
        ->exists();


        $payrun_user_exists = PayrunUser::where([
            "payrun_id" => $payrun->id
        ])
        ->whereHas("user.department_user.department", function($query) use($all_manager_department_ids) {
             $query->whereIn("departments.id",$all_manager_department_ids);
        })
        ->exists();




        if((!$payrun_department_exists) && !$payrun_user_exists){

            return response()->json([
                "message" => "You don't have access to this payrun"
            ], 403);
        }




            return response()->json($payrun, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }




    public function deletePayrunsByIds(Request $request, $ids)
    {

        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('payrun_delete')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;

            $all_manager_department_ids = $this->get_all_departments_of_manager();



            $idsArray = explode(',', $ids);
            $existingIds = Payrun::where([
                "business_id" => $business_id
            ])
            ->where(function($query) use($all_manager_department_ids) {
                $query->whereHas("departments", function($query) use($all_manager_department_ids) {
                    $query->whereIn("departments.id",$all_manager_department_ids);
                 })
                 ->orWhereHas("users.department_user.department", function($query) use($all_manager_department_ids) {
                    $query->whereIn("departments.id",$all_manager_department_ids);
                 });
            })

                ->whereIn('id', $idsArray)
                ->select('id')
                ->get()
                ->pluck('id')
                ->toArray();
            $nonExistingIds = array_diff($idsArray, $existingIds);

            if (!empty($nonExistingIds)) {

                return response()->json([
                    "message" => "Some or all of the specified data do not exist. or something else"
                ], 404);
            }

            Payrun::destroy($existingIds);


            return response()->json(["message" => "data deleted sussfully","deleted_ids" => $existingIds], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
}

