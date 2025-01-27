<?php

namespace App\Http\Controllers;

use App\Http\Requests\DepartmentCreateRequest;
use App\Http\Requests\DepartmentUpdateRequest;
use App\Http\Requests\GetIdRequest;
use App\Http\Utils\BasicUtil;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\Department;
use App\Models\DepartmentUser;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDF;
use Maatwebsite\Excel\Facades\Excel;

class DepartmentController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil, BasicUtil;

    
    public function createDepartment(DepartmentCreateRequest $request)
    {
        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");

                if (!$request->user()->hasPermissionTo('department_create')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }

                $request_data = $request->validated();



                if (empty($request_data["parent_id"])) {
                    $parent_department = Department::whereNull('parent_id')
                    ->where('departments.business_id', '=', auth()->user()->business_id)
                    ->first();

                    $request_data["parent_id"] = $parent_department["id"];
                }

                $request_data["business_id"] = $request->user()->business_id;
                $request_data["is_active"] = true;
                $request_data["created_by"] = $request->user()->id;

                $department =  Department::create($request_data);

                // if(!empty($department->manager_id)) {
                //     $department->users()->attach($department->manager_id);
                // }

                DB::commit();
                return response($department, 201);

        } catch (Exception $e) {
            DB::rollBack();

            return $this->sendError($e, 500, $request);
        }
    }



    public function updateDepartment(DepartmentUpdateRequest $request)
    {


        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");

                if (!$request->user()->hasPermissionTo('department_update')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }
                $business_id =  $request->user()->business_id;
                $request_data = $request->validated();







                $department_query_params = [
                    "id" => $request_data["id"],
                    "business_id" => $business_id
                ];
                $department_prev = Department::where($department_query_params)
                    ->first();
                if (!$department_prev) {

                    return response()->json([
                        "message" => "no department found"
                    ], 404);
                }
                $main_parent_department = Department::whereNull('parent_id')
                ->where('departments.business_id', '=', auth()->user()->business_id)
                ->first();

                if (!$main_parent_department) {

                    return response()->json([
                        "message" => "main parent not found."
                    ], 409);
                }


                if (empty($request_data["parent_id"])) {
                    $request_data["parent_id"] = $main_parent_department->id;
                } else {
                    $previous_parent_id =  $department_prev->parent_id;
                    if($previous_parent_id !== $request_data["parent_id"]) {
                        $descendantIds = $department_prev->getAllDescendantIds();
                        if (in_array($request_data["parent_id"], $descendantIds)) {
                         Department::where([
                            "id" => $request_data["parent_id"]
                          ])->update(
                            [
                                "parent_id" => $main_parent_department->id
                            ]
                          );


                        }
                    }


                }


                $department  =  tap(Department::where($department_query_params))->update(
                    collect($request_data)->only([


                        "name",
                        "work_location_id",
                        "description",
                        // "is_active",
                        "manager_id",
                        "parent_id",

                    ])->toArray()
                )
                    // ->with("somthing")

                    ->first();
                if (!$department) {
                    return response()->json([
                        "message" => "something went wrong."
                    ], 500);
                }





                // if ($department_prev->manager_id != $department->manager_id) {
                //     // Remove the previous manager's relationship with the department
                // $last_added_manager =  DepartmentUser::where([
                //         'department_id' => $department->id,
                //         'user_id'       => $department_prev->manager_id
                //     ])
                //    ->orderByDesc("id")
                //     ->first();

                //     if(!empty($last_added_manager)) {
                //         $last_added_manager->delete();
                //     }



                //     // Attach the new manager to the department
                //     $department->users()->attach($department->manager_id);
                // }




                DB::commit();
                return response($department, 201);

        } catch (Exception $e) {
            DB::rollBack();
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }


     public function toggleActiveDepartment(GetIdRequest $request)
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

            $department = Department::where([
                "id" => $request_data["id"],
                "business_id" => auth()->user()->business_id
            ])
                ->first();
            if (!$department) {

                return response()->json([
                    "message" => "no department found"
                ], 404);
            }
            if (!$department->parent_id) {

                return response()->json([
                    "message" => "You can not change the status of main parent department."
                ], 409);
            }
            if(!in_array($department->id,$all_manager_department_ids)){
                return response()->json([
                    "message" => "You don't have access to this department"
                ], 403);
            }


             $department->update([
                 'is_active' => !$department->is_active
             ]);

             return response()->json(['message' => 'department status updated successfully'], 200);
         } catch (Exception $e) {
             error_log($e->getMessage());
             return $this->sendError($e, 500, $request);
         }
     }


    public function getDepartments(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('department_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;

            $all_manager_department_ids = $this->get_all_departments_of_manager();

            $departments = Department::with("work_location")
            ->where(
                [
                    "business_id" => $business_id
                ]
            )

            ->whereIn("id",$all_manager_department_ids)


            ->when(request()->has("not_in_rota") && intval(request()->input("not_in_rota")), function ($query) {

                $query->whereDoesntHave("employee_rota");
            })




            ->when(isset($request->doesnt_have_payrun), function ($query) use ($request) {
                if(intval($request->doesnt_have_payrun)) {
                    return $query->whereDoesntHave("payrun_departments");
                } else {
                    return $query;
                }

            })





            ->when(isset($request->hide_parent), function ($query) use ($request) {
                if(intval($request->hide_parent)) {
                    return $query->whereNotNull("parent_id");
                } else {
                    return $query;
                }

            })
                ->when(!empty($request->search_key), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        $term = $request->search_key;
                        $query->where("name", "like", "%" . $term . "%")
                            ->orWhere("description", "like", "%" . $term . "%");
                    });
                })
                ->when(!empty($request->name), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        $term = $request->name;
                        $query->where("name", "like", "%" . $term . "%");
                    });
                })
                ->when(!empty($request->description), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        $term = $request->description;
                        $query->where("description", "like", "%" . $term . "%");
                    });
                })
                ->when(isset($request->is_active), function ($query) use ($request) {
                    return $query->where('departments.is_active', intval($request->is_active));
                })
                ->when(!empty($request->start_date), function ($query) use ($request) {
                    return $query->where('departments.created_at', ">=", $request->start_date);
                })
                ->when(!empty($request->end_date), function ($query) use ($request) {
                    return $query->where('departments.created_at', "<=", ($request->end_date . ' 23:59:59'));
                })
                ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                    return $query->orderBy("departments.id", $request->order_by);
                }, function ($query) {
                    return $query->orderBy("departments.id", "DESC");
                })
                ->select('departments.*',
                DB::raw('
         COALESCE(
             (SELECT COUNT(department_users.user_id) FROM department_users WHERE department_users.department_id = departments.id),
             0
         ) AS total_users
         '),

         DB::raw('IF(departments.manager_id = ' . auth()->user()->id . ', 1, 0) AS restrict_delete')
                 )
                ->when(!empty($request->per_page), function ($query) use ($request) {
                    return $query->paginate($request->per_page);
                }, function ($query) {
                    return $query->get();
                });



            return response()->json($departments, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }


     public function getDepartmentsV2(Request $request)
     {
         try {
             $this->storeActivity($request, "DUMMY activity","DUMMY description");
             if (!$request->user()->hasPermissionTo('department_view')) {
                 return response()->json([
                     "message" => "You can not perform this action"
                 ], 401);
             }
             $business_id =  $request->user()->business_id;
             $department = Department::with([

                "manager" => function ($query) {
                    $query->select('users.id', 'users.first_Name','users.middle_Name',
                    'users.last_Name');
                },
                'recursiveChildren.manager',
                'recursiveChildren.recursiveChildren'

            ])
            ->where(
                 [
                     "business_id" => $business_id,
                    //  "parent_id" => NULL,
                     "manager_id" => auth()->user()->id
                 ]
             )

                 ->orderBy("departments.id", "ASC")
                 ->select('departments.*')
                ->first();

                if (!$department) {

                    return response()->json([
                        "message" => "no department found"
                    ], 404);
                }



                $department->total_users_counts = User::where([
                    "business_id" => $business_id
                ])
                ->count();


             return response()->json($department, 200);
         } catch (Exception $e) {

             return $this->sendError($e, 500, $request);
         }
     }


     public function getDepartmentsV3(Request $request)
     {
         try {
             $this->storeActivity($request, "DUMMY activity","DUMMY description");
             if (!$request->user()->hasPermissionTo('department_view')) {
                 return response()->json([
                     "message" => "You can not perform this action"
                 ], 401);
             }
             $business_id =  $request->user()->business_id;

             $departments = Department::with([
                'manager',
                'recursiveChildren',
            ])
            ->where([
                'business_id' => $business_id,
                'manager_id' => auth()->user()->id,
            ])

            ->orderBy('id', 'ASC')
            ->get();

            foreach ($departments as $department) {
                $department->total_users_count = $department->getTotalUsersCountAttribute();
            }

            return response()->json($departments, 200);


            if (!empty($request->response_type) && in_array(strtoupper($request->response_type), ['PDF', 'CSV'])) {
                if (strtoupper($request->response_type) == 'PDF') {
                    $pdf = PDF::loadView('pdf.org_structure', ["departments" => $departments]);
                    return $pdf->download(((!empty($request->file_name) ? $request->file_name : 'employee') . '.pdf'));
                }
                // elseif (strtoupper($request->response_type) === 'CSV') {

                //     return Excel::download(new UsersExport($users), ((!empty($request->file_name) ? $request->file_name : 'employee') . '.csv'));
                // }
            } else {
                return response()->json($departments, 200);
            }



         } catch (Exception $e) {

             return $this->sendError($e, 500, $request);
         }
     }



    public function getDepartmentById($id, Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('department_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;

            $all_manager_department_ids = $this->get_all_departments_of_manager();

            $department =  Department::with("work_shifts")->where([
                "id" => $id,
                "business_id" => $business_id
            ])
            ->select('departments.*',
            DB::raw('
     COALESCE(
         (SELECT COUNT(department_users.user_id) FROM department_users WHERE department_users.department_id = departments.id),
         0
     ) AS total_users
     '),
             )
                ->first();
            if (!$department) {

                return response()->json([
                    "message" => "no department found"
                ], 404);
            }
            if(!in_array($department->id,$all_manager_department_ids)){

                return response()->json([
                    "message" => "You don't have access to this department"
                ], 403);
            }

            return response()->json($department, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }




    public function deleteDepartmentsByIds(Request $request, $ids)
    {

        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('department_delete')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;


            $all_manager_department_ids = $this->get_all_departments_of_manager();

            $idsArray = explode(',', $ids);
            $existingIds = Department::where([
                "business_id" => $business_id
            ])
            ->whereNotNull('parent_id')
            ->whereIn("id",$all_manager_department_ids)
            ->where(function($query) {
                $query->whereNotIn("departments.manager_id",[auth()->user()->id])
                ->when(auth()->user()->hasRole("business_owner"), function($query) {
                       $query->orWhereNull("departments.manager_id");
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





            $conflictingUsers = User::whereHas("departments", function($query) use($existingIds) {
                $query->whereIn("department_id", $existingIds);
            })->get(['id', 'first_name', 'last_name']);

            if ($conflictingUsers->isNotEmpty()) {
                return response()->json([
                    "message" => "Some users are associated with the specified departments",
                    "conflicting_users" => $conflictingUsers
                ], 409);
            }








            Department::destroy($existingIds);


            return response()->json(["message" => "data deleted sussfully","deleted_ids" => $existingIds], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
}
