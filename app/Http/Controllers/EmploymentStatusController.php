<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmploymentStatusCreateRequest;
use App\Http\Requests\EmploymentStatusUpdateRequest;
use App\Http\Requests\GetIdRequest;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\DisabledEmploymentStatus;
use App\Models\EmploymentStatus;
use App\Models\SettingPaidLeaveEmploymentStatus;
use App\Models\SettingUnpaidLeaveEmploymentStatus;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmploymentStatusController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil;

    public function createEmploymentStatus(EmploymentStatusCreateRequest $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            return DB::transaction(function () use ($request) {
                if (!$request->user()->hasPermissionTo('employment_status_create')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }

                $request_data = $request->validated();


                $request_data["is_active"] = 1;
                $request_data["is_default"] = 0;
                $request_data["created_by"] = $request->user()->id;
                $request_data["business_id"] = $request->user()->business_id;

                if (empty($request->user()->business_id)) {
                    $request_data["business_id"] = NULL;
                    if ($request->user()->hasRole('superadmin')) {
                        $request_data["is_default"] = 1;
                    }
                }



                $employment_status =  EmploymentStatus::create($request_data);




                return response($employment_status, 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }


    public function updateEmploymentStatus(EmploymentStatusUpdateRequest $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            return DB::transaction(function () use ($request) {
                if (!$request->user()->hasPermissionTo('employment_status_update')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }

                $request_data = $request->validated();



                $employment_status_query_params = [
                    "id" => $request_data["id"],
                ];


                $employment_status  =  tap(EmploymentStatus::where($employment_status_query_params))->update(
                    collect($request_data)->only([
                        'name',
                        'color',
                        'description',
                        // "is_active",
                        // "business_id",

                    ])->toArray()
                )
                    // ->with("somthing")

                    ->first();
                if (!$employment_status) {
                    return response()->json([
                        "message" => "something went wrong."
                    ], 500);
                }

                return response($employment_status, 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }


     public function toggleActiveEmploymentStatus(GetIdRequest $request)
     {

         try {
             $this->storeActivity($request, "DUMMY activity", "DUMMY description");
             if (!$request->user()->hasPermissionTo('employment_status_activate')) {
                 return response()->json([
                     "message" => "You can not perform this action"
                 ], 401);
             }
             $request_data = $request->validated();

             $employment_status =  EmploymentStatus::where([
                 "id" => $request_data["id"],
             ])
                 ->first();
             if (!$employment_status) {

                 return response()->json([
                     "message" => "no data found"
                 ], 404);
             }
             $should_update = 0;
             $should_disable = 0;
             if (empty(auth()->user()->business_id)) {

                 if (auth()->user()->hasRole('superadmin')) {
                     if (($employment_status->business_id != NULL || $employment_status->is_default != 1)) {

                         return response()->json([
                             "message" => "You do not have permission to update this employment status due to role restrictions."
                         ], 403);
                     } else {
                         $should_update = 1;
                     }
                 } else {
                     if ($employment_status->business_id != NULL) {


                         return response()->json([
                             "message" => "You do not have permission to update this employment status due to role restrictions."
                         ], 403);
                     } else if ($employment_status->is_default == 0) {

                         if($employment_status->created_by != auth()->user()->id) {

                             return response()->json([
                                 "message" => "You do not have permission to update this employment status due to role restrictions."
                             ], 403);
                         }
                         else {
                             $should_update = 1;
                         }



                     }
                     else {
                      $should_disable = 1;

                     }
                 }
             } else {
                 if ($employment_status->business_id != NULL) {
                     if (($employment_status->business_id != auth()->user()->business_id)) {

                         return response()->json([
                             "message" => "You do not have permission to update this employment status due to role restrictions."
                         ], 403);
                     } else {
                         $should_update = 1;
                     }
                 } else {
                     if ($employment_status->is_default == 0) {
                         if ($employment_status->created_by != auth()->user()->created_by) {

                             return response()->json([
                                 "message" => "You do not have permission to update this employment status due to role restrictions."
                             ], 403);
                         } else {
                             $should_disable = 1;

                         }
                     } else {
                         $should_disable = 1;

                     }
                 }
             }

             if ($should_update) {
                 $employment_status->update([
                     'is_active' => !$employment_status->is_active
                 ]);
             }

             if($should_disable) {

                 $disabled_employment_status =    DisabledEmploymentStatus::where([
                     'employment_status_id' => $employment_status->id,
                     'business_id' => auth()->user()->business_id,
                     'created_by' => auth()->user()->id,
                 ])->first();
                 if(!$disabled_employment_status) {
                    DisabledEmploymentStatus::create([
                         'employment_status_id' => $employment_status->id,
                         'business_id' => auth()->user()->business_id,
                         'created_by' => auth()->user()->id,
                     ]);
                 } else {
                     $disabled_employment_status->delete();
                 }
             }


             return response()->json(['message' => 'employment status status updated successfully'], 200);
         } catch (Exception $e) {
             error_log($e->getMessage());
             return $this->sendError($e, 500, $request);
         }
     }

    public function getEmploymentStatuses(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('employment_status_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $created_by  = NULL;
            if(auth()->user()->business) {
                $created_by = auth()->user()->business->created_by;
            }


            $employment_statuses = EmploymentStatus::when(empty($request->user()->business_id), function ($query) use ($request, $created_by) {
                if (auth()->user()->hasRole('superadmin')) {
                    return $query->where('employment_statuses.business_id', NULL)
                        ->where('employment_statuses.is_default', 1)
                        ->when(isset($request->is_active), function ($query) use ($request) {
                            return $query->where('employment_statuses.is_active', intval($request->is_active));
                        });
                } else {
                    return $query

                    ->where(function($query) use($request) {
                        $query->where('employment_statuses.business_id', NULL)
                        ->where('employment_statuses.is_default', 1)
                        ->where('employment_statuses.is_active', 1)
                        ->when(isset($request->is_active), function ($query) use ($request) {
                            if(intval($request->is_active)) {
                                return $query->whereDoesntHave("disabled", function($q) {
                                    $q->whereIn("disabled_employment_statuses.created_by", [auth()->user()->id]);
                                });
                            }

                        })
                        ->orWhere(function ($query) use ($request) {
                            $query->where('employment_statuses.business_id', NULL)
                                ->where('employment_statuses.is_default', 0)
                                ->where('employment_statuses.created_by', auth()->user()->id)
                                ->when(isset($request->is_active), function ($query) use ($request) {
                                    return $query->where('employment_statuses.is_active', intval($request->is_active));
                                });
                        });

                    });
                }
            })
                ->when(!empty($request->user()->business_id), function ($query) use ($request, $created_by) {
                    return $query
                    ->where(function($query) use($request, $created_by) {


                        $query->where('employment_statuses.business_id', NULL)
                        ->where('employment_statuses.is_default', 1)
                        ->where('employment_statuses.is_active', 1)
                        ->whereDoesntHave("disabled", function($q) use($created_by) {
                            $q->whereIn("disabled_employment_statuses.created_by", [$created_by]);
                        })
                        ->when(isset($request->is_active), function ($query) use ($request, $created_by)  {
                            if(intval($request->is_active)) {
                                return $query->whereDoesntHave("disabled", function($q) use($created_by) {
                                    $q->whereIn("disabled_employment_statuses.business_id",[auth()->user()->business_id]);
                                });
                            }

                        })


                        ->orWhere(function ($query) use($request, $created_by){
                            $query->where('employment_statuses.business_id', NULL)
                                ->where('employment_statuses.is_default', 0)
                                ->where('employment_statuses.created_by', $created_by)
                                ->where('employment_statuses.is_active', 1)

                                ->when(isset($request->is_active), function ($query) use ($request) {
                                    if(intval($request->is_active)) {
                                        return $query->whereDoesntHave("disabled", function($q) {
                                            $q->whereIn("disabled_employment_statuses.business_id",[auth()->user()->business_id]);
                                        });
                                    }

                                })


                                ;
                        })
                        ->orWhere(function ($query) use($request) {
                            $query->where('employment_statuses.business_id', auth()->user()->business_id)
                                ->where('employment_statuses.is_default', 0)
                                ->when(isset($request->is_active), function ($query) use ($request) {
                                    return $query->where('employment_statuses.is_active', intval($request->is_active));
                                });;
                        });
                    });


                })
                ->when(!empty($request->search_key), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        $term = $request->search_key;
                        $query->where("employment_statuses.name", "like", "%" . $term . "%")
                            ->orWhere("employment_statuses.description", "like", "%" . $term . "%");
                    });
                })
                //    ->when(!empty($request->product_category_id), function ($query) use ($request) {
                //        return $query->where('product_category_id', $request->product_category_id);
                //    })
                ->when(!empty($request->start_date), function ($query) use ($request) {
                    return $query->where('employment_statuses.created_at', ">=", $request->start_date);
                })
                ->when(!empty($request->end_date), function ($query) use ($request) {
                    return $query->where('employment_statuses.created_at', "<=", ($request->end_date . ' 23:59:59'));
                })


                ->when(!empty($request->name), function ($query) use ($request) {
                    return $query->where('employment_statuses.name', $request->name );
                })
                ->when(!empty($request->description), function ($query) use ($request) {
                    return $query->where('employment_statuses.description', $request->description );
                })



                ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                    return $query->orderBy("employment_statuses.id", $request->order_by);
                }, function ($query) {
                    return $query->orderBy("employment_statuses.id", "DESC");
                })
                ->when(!empty($request->per_page), function ($query) use ($request) {
                    return $query->paginate($request->per_page);
                }, function ($query) {
                    return $query->get();
                });;



            return response()->json($employment_statuses, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }



    public function getEmploymentStatusById($id, Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('employment_status_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $employment_status =  EmploymentStatus::where([
                "id" => $id,

            ])
                ->first();
                if (!$employment_status) {

                    return response()->json([
                        "message" => "no data found"
                    ], 404);
                }

                if (empty(auth()->user()->business_id)) {

                    if (auth()->user()->hasRole('superadmin')) {
                        if (($employment_status->business_id != NULL || $employment_status->is_default != 1)) {

                            return response()->json([
                                "message" => "You do not have permission to update this employment status due to role restrictions."
                            ], 403);
                        }
                    } else {
                        if ($employment_status->business_id != NULL) {

                            return response()->json([
                                "message" => "You do not have permission to update this employment status due to role restrictions."
                            ], 403);
                        } else if ($employment_status->is_default == 0 && $employment_status->created_by != auth()->user()->id) {

                                return response()->json([
                                    "message" => "You do not have permission to update this employment status due to role restrictions."
                                ], 403);

                        }
                    }
                } else {
                    if ($employment_status->business_id != NULL) {
                        if (($employment_status->business_id != auth()->user()->business_id)) {

                            return response()->json([
                                "message" => "You do not have permission to update this employment status due to role restrictions."
                            ], 403);
                        }
                    } else {
                        if ($employment_status->is_default == 0) {
                            if ($employment_status->created_by != auth()->user()->created_by) {

                                return response()->json([
                                    "message" => "You do not have permission to update this employment status due to role restrictions."
                                ], 403);
                            }
                        }
                    }
                }


            return response()->json($employment_status, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }



    public function deleteEmploymentStatusesByIds(Request $request, $ids)
    {

        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('employment_status_delete')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $idsArray = explode(',', $ids);
            $existingIds = EmploymentStatus::whereIn('id', $idsArray)
            ->when(empty($request->user()->business_id), function ($query) use ($request) {
                if ($request->user()->hasRole("superadmin")) {
                    return $query->where('employment_statuses.business_id', NULL)
                        ->where('employment_statuses.is_default', 1);
                } else {
                    return $query->where('employment_statuses.business_id', NULL)
                        ->where('employment_statuses.is_default', 0)
                        ->where('employment_statuses.created_by', $request->user()->id);
                }
            })
            ->when(!empty($request->user()->business_id), function ($query) use ($request) {
                return $query->where('employment_statuses.business_id', $request->user()->business_id)
                    ->where('employment_statuses.is_default', 0);
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


            $conflictingUsers = User::whereIn("employment_status_id", $existingIds)->get(['id', 'first_Name',
            'last_Name',]);

            if ($conflictingUsers->isNotEmpty()) {
                return response()->json([
                    "message" => "Some users are associated with the specified employment statuses",
                    "conflicting_users" => $conflictingUsers
                ], 409);
            }



            $paid_employment_status_exists =  SettingPaidLeaveEmploymentStatus::whereIn("employment_status_id",$existingIds)->exists();
            if($paid_employment_status_exists) {
                $conflictingPaidEmploymentStatus = SettingPaidLeaveEmploymentStatus::whereIn("employment_status_id", $existingIds)->get(['id']);

                return response()->json([
                    "message" => "Some leave settings are associated with the specified employment statuses",
                    "conflicting_paid_employment_status" => $conflictingPaidEmploymentStatus
                ], 409);

            }
            $unpaid_employment_status_exists =  SettingUnpaidLeaveEmploymentStatus::whereIn("employment_status_id",$existingIds)->exists();
            if($unpaid_employment_status_exists) {
                $conflictingUnpaidEmploymentStatus = SettingPaidLeaveEmploymentStatus::whereIn("employment_status_id", $existingIds)->get(['id']);

                return response()->json([
                    "message" => "Some leave settings are associated with the specified employment statuses",
                    "conflicting_unpaid_employment_status" => $conflictingUnpaidEmploymentStatus
                ], 409);

            }
            EmploymentStatus::destroy($existingIds);


            return response()->json(["message" => "data deleted sussfully","deleted_ids" => $existingIds], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
}
