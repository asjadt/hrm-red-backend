<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeRotaCreateRequest;
use App\Http\Requests\EmployeeRotaUpdateRequest;
use App\Http\Requests\GetIdRequest;
use App\Http\Utils\BasicUtil;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\Department;
use App\Models\EmployeeRota;
use App\Models\EmployeeRotaDetail;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDF;
use Maatwebsite\Excel\Facades\Excel;


class EmployeeRotaController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil, BasicUtil;


    public function createEmployeeRota(EmployeeRotaCreateRequest $request)
    {

        try {

            $this->storeActivity($request, "DUMMY activity","DUMMY description");


            return DB::transaction(function () use ($request) {
                if (!$request->user()->hasPermissionTo('employee_rota_create')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }
                $request_data = $request->validated();


                $all_manager_department_ids = $this->get_all_departments_of_manager();

                $departments = [];

                if(!empty($request_data['departments'])) {
                    $departments = Department::whereIn('id', $request_data['departments'])
                    ->whereIn(
                        "id",
                        $all_manager_department_ids
                    )
                    ->whereDoesntHave("employee_rota")
                    ->where('business_id', auth()->user()->business_id)
                    ->pluck("id");

                    $invalid_department_ids = array_diff($request_data['departments'], $departments->toArray());


                    if (!empty($invalid_department_ids)) {
                        throw new Exception('Invalid department IDs found.',403);
                    }

                }



                $users = [];

                if(!empty($request_data['users'])) {
                    $users = User::where([
                        'users.business_id' => auth()->user()->business_id,
                        "is_active" => 1
                    ])
                    ->whereHas('departments', function($query) use($all_manager_department_ids) {
                        $query->whereIn('departments.id', $all_manager_department_ids);
                    })
                    ->whereDoesntHave("employee_rota")
                    ->whereIn('users.id', $request_data['users'])
                    ->whereNotIn('users.id', [auth()->user()->id])
                    ->pluck("id");

                    $invalid_user_ids = array_diff($request_data['users'], $users->toArray());


                    if (!empty($invalid_user_ids)) {
                        throw new Exception('Invalid user IDs found.',403);
                    }
                }








                $request_data["business_id"] = $request->user()->business_id;
                $request_data["is_active"] = true;
                $request_data["created_by"] = $request->user()->id;
                $request_data["is_default"] = false;




                $data_to_process = collect();

                $common_data = collect([
                    'name' => $request_data['name'],
                    'description' => $request_data['description'],
                    'is_default' => $request_data['is_default'],
                    'is_active' => $request_data['is_active'],
                    'business_id' => $request_data['business_id'],
                    'created_by' => $request_data['created_by'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if (!empty($departments)) {
                    $data_to_process = $data_to_process->concat(collect($departments)->map(function ($department_id) use ($common_data) {
                        return $common_data->merge(['department_id' => $department_id, 'user_id' => NULL])->all();
                    }));
                }

                if (!empty($users)) {
                    $data_to_process = $data_to_process->concat(collect($users)->map(function ($user_id) use ($common_data) {
                        return $common_data->merge(['department_id' => NULL,'user_id' => $user_id])->all();
                    }));
                }

                if ($data_to_process->isNotEmpty()) {
                    $chunkSize = 1000; // Example chunk size
                    $data_to_process->chunk($chunkSize)->each(function ($chunk) {
                        EmployeeRota::insert($chunk->toArray());
                    });
                    // $employee_rota->details()->createMany($request_data['details']);
                }


                // Query to retrieve data based on department and user IDs
$employeeRotaIds = EmployeeRota::whereIn('department_id', $departments)
->orWhereIn('user_id', $users)
->pluck("id");


$processedDetails = collect($request_data['details'])->crossJoin($employeeRotaIds)->map(function ($item) {
    $detail = $item[0];
    $employeeRotaId = $item[1];

    return [
        'employee_rota_id' => $employeeRotaId,
        'day' => $detail['day'],
        'start_at' => $detail['start_at'],
        'end_at' => $detail['end_at'],
        'break_type' => $detail['break_type'],
        'break_hours' => $detail['break_hours'],
        "created_at" => now(),
        "updated_at" => now()
    ];
});



if ($processedDetails->isNotEmpty()) {
    $chunkSize = 1000; // Example chunk size
    $processedDetails->chunk($chunkSize)->each(function ($chunk) {
        EmployeeRotaDetail::insert($chunk->toArray());
    });
    // $employee_rota->details()->createMany($request_data['details']);
}



                // if(!empty($request_data['departments'])){
                //     foreach($request_data['departments'] as $department_id) {
                //         $employee_rota =  EmployeeRota::create($request_data);
                //         $employee_rota->details()->createMany($request_data['details']);
                //         $employee_rota->departments()->sync([$department_id]);
                //     }
                // }


                // if(!empty($request_data['users'])){
                //     foreach($request_data['users'] as $user_id) {
                //         $employee_rota =  EmployeeRota::create($request_data);
                //         $employee_rota->details()->createMany($request_data['details']);
                //         $employee_rota->users()->sync([$user_id]);
                //     }
                // }


















                return response(["ok" => true], 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }


    public function updateEmployeeRota(EmployeeRotaUpdateRequest $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");

            return DB::transaction(function () use ($request) {

                if (!$request->user()->hasPermissionTo('employee_rota_update')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }


                $request_data = $request->validated();





                $employee_rota_query_params = [
                    "id" => $request_data["id"],
                ];



                $employee_rota  =  tap(EmployeeRota::where($employee_rota_query_params))->update(
                    collect($request_data)->only([
        'name',
        'type',
        "description",
        'start_date',
        'end_date',


                    ])->toArray()
                )
                    // ->with("somthing")

                    ->first();

                if (!$employee_rota) {
                    return response()->json([
                        "message" => "something went wrong."
                    ], 500);
                }





                $employee_rota->details()->delete();
                $employee_rota->details()->createMany($request_data['details']);




                return response($employee_rota, 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }



     public function toggleActiveEmployeeRota(GetIdRequest $request)
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

            $employee_rota = EmployeeRota::where([
                "id" => $request_data["id"],
                "business_id" => auth()->user()->business_id
            ])
            ->where(function($query) use ($all_manager_department_ids) {
                $query->whereIn("employee_rotas.department_id", $all_manager_department_ids)
                ->orWhereHas("user.departments", function($query) use ($all_manager_department_ids) {
                    $query->whereIn("departments.id", $all_manager_department_ids);
                });
            })
            ->whereNotIn("employee_rotas.user_id", [auth()->user()->id])
                ->first();
            if (!$employee_rota) {
                return response()->json([
                    "message" => "no rota found"
                ], 404);
            }
            $is_active = !$employee_rota->is_active;




             $employee_rota->update([
                 'is_active' => $is_active
             ]);


             return response()->json(['message' => 'Rota status updated successfully'], 200);
         } catch (Exception $e) {
             error_log($e->getMessage());
             return $this->sendError($e, 500, $request);
         }
     }


    public function getEmployeeRotas(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");

            if (!$request->user()->hasPermissionTo('employee_rota_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }


            $all_manager_department_ids = $this->get_all_departments_of_manager();


            $employee_rotas = EmployeeRota::with("details","department","user")
            ->where([
                "employee_rotas.business_id" => auth()->user()->business_id
            ])



            ->where(function($query) use ($all_manager_department_ids) {
                $query->whereIn("employee_rotas.department_id", $all_manager_department_ids)
                ->orWhereHas("user.departments", function($query) use ($all_manager_department_ids) {
                    $query->whereIn("departments.id", $all_manager_department_ids);
                });
            })
            ->where(function($query)  {
                $query->whereNotIn("employee_rotas.user_id", [auth()->user()->id])
                ->orWhereNull("employee_rotas.user_id");
            })





                ->when(!empty($request->search_key), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        $term = $request->search_key;
                        $query->where("employee_rotas.name", "like", "%" . $term . "%")
                            ->orWhere("employee_rotas.description", "like", "%" . $term . "%");
                    });
                })



                ->when(isset($request->name), function ($query) use ($request) {
                    $term = $request->name;
                    return $query->where("employee_rotas.name", "like", "%" . $term . "%");
                })
                ->when(isset($request->description), function ($query) use ($request) {
                    $term = $request->description;
                    return $query->where("employee_rotas.description", "like", "%" . $term . "%");
                })






                ->when(isset($request->is_default), function ($query) use ($request) {
                    return $query->where('employee_rotas.is_default', intval($request->is_default));
                })


                //    ->when(!empty($request->product_category_id), function ($query) use ($request) {
                //        return $query->where('product_category_id', $request->product_category_id);
                //    })
                ->when(!empty($request->start_date), function ($query) use ($request) {
                    return $query->where('employee_rotas.created_at', ">=", $request->start_date);
                })

                ->when(!empty($request->end_date), function ($query) use ($request) {
                    return $query->where('employee_rotas.created_at', "<=", ($request->end_date . ' 23:59:59'));
                })
                ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                    return $query->orderBy("employee_rotas.id", $request->order_by);
                }, function ($query) {
                    return $query->orderBy("employee_rotas.id", "DESC");
                })
                ->when(!empty($request->per_page), function ($query) use ($request) {
                    return $query->paginate($request->per_page);
                }, function ($query) {
                    return $query->get();
                });


                if (!empty($request->response_type) && in_array(strtoupper($request->response_type), ['PDF', 'CSV'])) {
                    // if (strtoupper($request->response_type) == 'PDF') {
                    //     $pdf = PDF::loadView('pdf.employee_rotas', ["employee_rotas" => $employee_rotas]);
                    //     return $pdf->download(((!empty($request->file_name) ? $request->file_name : 'employee') . '.pdf'));
                    // } elseif (strtoupper($request->response_type) === 'CSV') {

                    //     return Excel::download(new EmployeeRotasExport($employee_rotas), ((!empty($request->file_name) ? $request->file_name : 'employee') . '.csv'));
                    // }
                } else {
                    return response()->json($employee_rotas, 200);
                }


            return response()->json($employee_rotas, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }



    public function getEmployeeRotaById($id, Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('employee_rota_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $all_manager_department_ids = $this->get_all_departments_of_manager();


            $employee_rota =  EmployeeRota::with("details","department","user")
            ->where([
                "id" => $id,
                "employee_rotas.business_id" => auth()->user()->business_id
            ])

            ->where(function($query) use ($all_manager_department_ids) {

                $query->whereIn("employee_rotas.department_id", $all_manager_department_ids)
                ->orWhereHas("user.departments", function($query) use ($all_manager_department_ids) {
                    $query->whereIn("departments.id", $all_manager_department_ids);
                })
                ->orWhereIn("employee_rotas.user_id", [auth()->user()->id]);

            })




                ->first();
            if (empty($employee_rota)) {

                return response()->json([
                    "message" => "no employee rota found"
                ], 404);
            }


            return response()->json($employee_rota, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }

    }



     public function getEmployeeRotaByUserId($user_id, Request $request)
     {
         try {
             $this->storeActivity($request, "DUMMY activity","DUMMY description");
             if (!$request->user()->hasPermissionTo('employee_rota_view')) {
                 return response()->json([
                     "message" => "You can not perform this action"
                 ], 401);
             }
             $business_id =  auth()->user()->business_id;

             $all_manager_department_ids = $this->get_all_departments_of_manager();


             $employee_rota =   EmployeeRota::with("details")
            ->where(function($query) use($business_id,$user_id) {
                $query->where([
                    "business_id" => $business_id
                ])->whereHas('users', function ($query) use ($user_id) {
                    $query->where('users.id', $user_id);
                });
            })
            ->where(function($query) use ($all_manager_department_ids) {

                $query->whereIn("employee_rotas.department_id", $all_manager_department_ids)
                ->orWhereHas("user.departments", function($query) use ($all_manager_department_ids) {
                    $query->whereIn("departments.id", $all_manager_department_ids);
                })
                ->orWhereIn("employee_rotas.user_id", [auth()->user()->id]);

            })



            ->first();



             if (!$employee_rota) {

                 return response()->json([
                     "message" => "no employee rota found for the user"
                 ], 404);
             }

             return response()->json($employee_rota, 200);
         } catch (Exception $e) {

             return $this->sendError($e, 500, $request);
         }
     }


    public function deleteEmployeeRotasByIds(Request $request, $ids)
    {

        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('employee_rota_delete')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $all_manager_department_ids = $this->get_all_departments_of_manager();

            $idsArray = explode(',', $ids);
            $existingIds = EmployeeRota::where([
                "business_id" => auth()->user()->business_id,
            ])
            ->where(function($query) use ($all_manager_department_ids) {
                $query->whereIn("employee_rotas.department_id", $all_manager_department_ids)
                ->orWhereHas("user.departments", function($query) use ($all_manager_department_ids) {
                    $query->whereIn("departments.id", $all_manager_department_ids);
                });
            })
            ->whereNotIn("employee_rotas.user_id", [auth()->user()->id])


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



            EmployeeRota::destroy($existingIds);


            return response()->json(["message" => "data deleted sussfully", "deleted_ids" => $existingIds], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
}
