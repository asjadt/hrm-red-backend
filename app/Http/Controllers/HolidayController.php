<?php

namespace App\Http\Controllers;

use App\Exports\HolidayExport;
use App\Http\Components\DepartmentComponent;
use App\Http\Requests\HolidayCreateRequest;
use App\Http\Requests\HolidaySelfCreateRequest;
use App\Http\Requests\HolidayUpdateRequest;
use App\Http\Requests\HolidayUpdateStatusRequest;
use App\Http\Utils\BasicUtil;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\HolidayUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\Holiday;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use PDF;
use Maatwebsite\Excel\Facades\Excel;

class HolidayController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil, BasicUtil;


    protected $departmentComponent;


    public function __construct(DepartmentComponent $departmentComponent)
    {

        $this->departmentComponent = $departmentComponent;
    }



     public function createSelfHoliday(HolidaySelfCreateRequest $request)
     {
         try {
             $this->storeActivity($request, "DUMMY activity", "DUMMY description");
             return DB::transaction(function () use ($request) {


                 $request_data = $request->validated();


                 $request_data["business_id"] = $request->user()->business_id;
                 $request_data["is_active"] = true;
                 $request_data["created_by"] = $request->user()->id;
                 $request_data["status"] = (auth()->user()->hasRole("business_owner") ? "approved" : "pending_approval");

                 $holiday =  Holiday::create($request_data);



                 $holiday->users()->sync([auth()->user()->id]);





                 return response()->json($holiday, 201);
             });
         } catch (Exception $e) {
             error_log($e->getMessage());
             return $this->sendError($e, 500, $request);
         }
     }




    public function createHoliday(HolidayCreateRequest $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            return DB::transaction(function () use ($request) {
                if (!$request->user()->hasPermissionTo('holiday_create')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }

                $request_data = $request->validated();




                $request_data["business_id"] = $request->user()->business_id;
                $request_data["is_active"] = true;
                $request_data["created_by"] = $request->user()->id;
                $request_data["status"] = (auth()->user()->hasRole("business_owner") ? "approved" : "pending_approval");

                $holiday =  Holiday::create($request_data);


                $holiday->departments()->sync($request_data['departments']);
                $holiday->users()->sync($request_data['users']);





                return response()->json($holiday, 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }



     public function approveHoliday(HolidayUpdateStatusRequest $request)
     {

         try {
             $this->storeActivity($request, "DUMMY activity", "DUMMY description");
             return DB::transaction(function () use ($request) {
                 if (!$request->user()->hasPermissionTo('holiday_update')) {
                     return response()->json([
                         "message" => "You can not perform this action"
                     ], 401);
                 }
                 $business_id =  $request->user()->business_id;
                 $request_data = $request->validated();



                 $holiday_query_params = [
                     "id" => $request_data["id"],
                     "business_id" => $business_id
                 ];


                 $holiday = Holiday::where($holiday_query_params)->first();



                 if ($holiday) {
                     $holiday->fill(collect($request_data)->only(['status'])->toArray());
                     $holiday->save();
                 }



             if (!$holiday) {
                 return response()->json([
                     "message" => "Something went wrong."
                 ], 500);
             }



            return response()->json()($holiday, 201);
             });
         } catch (Exception $e) {
             error_log($e->getMessage());
             return $this->sendError($e, 500, $request);
         }
     }


    public function updateHoliday(HolidayUpdateRequest $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            return DB::transaction(function () use ($request) {
                if (!$request->user()->hasPermissionTo('holiday_update')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }
                $business_id =  $request->user()->business_id;
                $request_data = $request->validated();



                $holiday_query_params = [
                    "id" => $request_data["id"],
                    "business_id" => $business_id
                ];
                $holiday_prev = Holiday::where($holiday_query_params)
                    ->first();
                if (!$holiday_prev) {

                    return response()->json([
                        "message" => "no holiday found"
                    ], 404);
                }

                $holiday  =  tap(Holiday::where($holiday_query_params))->update(
                    collect($request_data)->only([
                        'name',
                        'description',
                        'start_date',
                        'end_date', '
                        repeats_annually',
                        // 'business_id',
                        // 'is_active',

                    ])->toArray()
                )
                    // ->with("somthing")

                    ->first();
                if (!$holiday) {
                    return response()->json([
                        "message" => "something went wrong."
                    ], 500);
                }
                $holiday->departments()->sync($request_data['departments']);
                $holiday->users()->sync($request_data['users']);
                return response($holiday, 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }



    public function getHolidays(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('holiday_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;

            $all_manager_department_ids = $this->get_all_departments_of_manager();
            $all_user_of_manager = $this->get_all_user_of_manager($all_manager_department_ids);
            $all_parent_department_ids = $this->departmentComponent->all_parent_departments_of_user(auth()->user()->id);


            $holidays = Holiday::with([
                "creator" => function ($query) {
                    $query->select(
                        'users.id',
                        'users.first_Name',
                        'users.middle_Name',
                        'users.last_Name'
                    );
                },
                "departments" => function ($query) {
                    $query->select('departments.id', 'departments.name'); // Specify the fields for the creator relationship
                },
                "users"
            ])
                ->where(
                    [
                        "holidays.business_id" => $business_id
                    ]
                )

                ->when(
                    (request()->has('show_my_data') && intval(request()->show_my_data) == 1),
                    function ($query) use ($all_parent_department_ids) {


                        $query->where(function ($query) use ($all_parent_department_ids) {
                            $query->whereHas("departments", function ($query) use ($all_parent_department_ids) {
                                $query->whereIn("departments.id", $all_parent_department_ids);
                            })
                                ->orWhereHas("users", function ($query) {
                                    $query->whereIn(
                                        "users.id",
                                        [auth()->user()->id]
                                    );
                                })
                                ->orWhere(function ($query) {
                                    $query->whereDoesntHave("users")
                                        ->whereDoesntHave("departments");
                                });
                        });
                    },
                    function ($query) use ($all_manager_department_ids, $all_user_of_manager) {

                        $query->where(function ($query) use ($all_manager_department_ids, $all_user_of_manager) {
                            $query->whereHas("departments", function ($query) use ($all_manager_department_ids) {
                                $query->whereIn("departments.id", $all_manager_department_ids);
                            })
                                ->orWhereHas("users", function ($query) use ($all_user_of_manager) {
                                    $query->whereIn(
                                        "users.id",
                                        $all_user_of_manager
                                    );
                                });

                                if (auth()->user()->hasRole('business_owner')) {
                                    $query ->orWhere(function ($query) {
                                        $query->whereDoesntHave("users")
                                            ->whereDoesntHave("departments");
                                    });
                                }



                        });

                    }

                )





                ->when(!empty($request->search_key), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        $term = $request->search_key;
                        $query->where("holidays.name", "like", "%" . $term . "%")
                            ->orWhere("holidays.description", "like", "%" . $term . "%");
                    });
                })
                //    ->when(!empty($request->product_category_id), function ($query) use ($request) {
                //        return $query->where('product_category_id', $request->product_category_id);
                //    })
                ->when(!empty($request->name), function ($query) use ($request) {
                    return $query->where("holidays.name", "like", "%" . $request->name . "%");
                })

                ->when(isset($request->repeat), function ($query) use ($request) {
                    return $query->where('holidays.repeats_annually', intval($request->repeat));
                })
                ->when(!empty($request->description), function ($query) use ($request) {
                    return $query->where("holidays.description", "like", "%" . $request->description . "%");
                })

                ->when(!empty($request->department_id), function ($query) use ($request) {
                    $idsArray = explode(',', $request->department_id);
                    $query->whereHas('departments', function ($query) use ($idsArray) {
                        $query->whereIn("departments.id", $idsArray);
                    });
                })


                ->when(!empty($request->start_date), function ($query) use ($request) {
                    return $query->where('holidays.created_at', ">=", $request->start_date);
                })
                ->when(!empty($request->end_date), function ($query) use ($request) {
                    return $query->where('holidays.created_at', "<=", ($request->end_date . ' 23:59:59'));
                })
                ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                    return $query->orderBy("holidays.id", $request->order_by);
                }, function ($query) {
                    return $query->orderBy("holidays.id", "DESC");
                })
                ->when(!empty($request->per_page), function ($query) use ($request) {
                    return $query->paginate($request->per_page);
                }, function ($query) {
                    return $query->get();
                });


            if (!empty($request->response_type) && in_array(strtoupper($request->response_type), ['PDF', 'CSV'])) {
                if (strtoupper($request->response_type) == 'PDF') {
                    $pdf = PDF::loadView('pdf.holidays', ["holidays" => $holidays]);
                    return $pdf->download(((!empty($request->file_name) ? $request->file_name : 'employee') . '.pdf'));
                } elseif (strtoupper($request->response_type) === 'CSV') {

                    return Excel::download(new HolidayExport($holidays), ((!empty($request->file_name) ? $request->file_name : 'leave') . '.csv'));
                }
            } else {
                return response()->json($holidays, 200);
            }


            return response()->json($holidays, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }



    public function getHolidayById($id, Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('holiday_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $all_manager_department_ids = $this->get_all_departments_of_manager();
            $all_user_of_manager = $this->get_all_user_of_manager($all_manager_department_ids);
            $all_parent_department_ids = $this->departmentComponent->all_parent_departments_of_user(auth()->user()->id);
            $holiday =  Holiday::with([
                "creator" => function ($query) {
                    $query->select(
                        'users.id',
                        'users.first_Name',
                        'users.middle_Name',
                        'users.last_Name'
                    );
                },
                "departments" => function ($query) {
                    $query->select('departments.id', 'departments.name'); // Specify the fields for the creator relationship
                },
                "users"
            ])->where([
                "id" => $id,
                "business_id" => auth()->user()->business_id
            ])
            ->where(function ($query) use ($all_parent_department_ids, $all_manager_department_ids,$all_user_of_manager) {
                $query->whereHas("departments", function ($query) use ($all_parent_department_ids,$all_manager_department_ids) {
                    $query->whereIn("departments.id", array_merge($all_parent_department_ids,$all_manager_department_ids));
                })
                    ->orWhereHas("users", function ($query) use($all_user_of_manager) {
                        $query->whereIn(
                            "users.id",
                            array_merge([auth()->user()->id],$all_user_of_manager)
                        );
                    })
                    ->orWhere(function ($query) {
                        $query->whereDoesntHave("users")
                            ->whereDoesntHave("departments");
                    });
            })
                ->first();
            if (!$holiday) {

                return response()->json([
                    "message" => "no holiday found"
                ], 404);
            }

            return response()->json($holiday, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }



    public function deleteHolidaysByIds(Request $request, $ids)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('holiday_delete')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;
            $idsArray = explode(',', $ids);
            $existingIds = Holiday::where([
                "business_id" => $business_id
            ])
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
            Holiday::destroy($existingIds);


            return response()->json(["message" => "data deleted sussfully", "deleted_ids" => $existingIds], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
}
