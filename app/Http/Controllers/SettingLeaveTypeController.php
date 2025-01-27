<?php

namespace App\Http\Controllers;

use App\Http\Requests\GetIdRequest;
use App\Http\Requests\SettingLeaveTypeCreateRequest;
use App\Http\Requests\SettingLeaveTypeUpdateRequest;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\DisabledSettingLeaveType;
use App\Models\Leave;
use App\Models\SettingLeaveType;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingLeaveTypeController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil;

    public function createSettingLeaveType(SettingLeaveTypeCreateRequest $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            return DB::transaction(function () use ($request) {
                if (!$request->user()->hasPermissionTo('setting_leave_type_create')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }

                $request_data = $request->validated();



                $request_data["is_default"] = 0;
                $request_data["created_by"] = $request->user()->id;
                $request_data["business_id"] = $request->user()->business_id;

                if (empty($request->user()->business_id)) {
                    $request_data["business_id"] = NULL;
                    if ($request->user()->hasRole('superadmin')) {
                        $request_data["is_default"] = 1;
                    }
                }





                $setting_leave_type =  SettingLeaveType::create($request_data);



                $setting_leave_type->employment_statuses()->sync($request_data["employment_statuses"]);



                return response($setting_leave_type, 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }


    public function updateSettingLeaveType(SettingLeaveTypeUpdateRequest $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            return DB::transaction(function () use ($request) {
                if (!$request->user()->hasPermissionTo('setting_leave_type_update')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }
                $request_data = $request->validated();



                $setting_leave_type_query_params = [
                    "id" => $request_data["id"],

                ];

                $setting_leave_type  =  tap(SettingLeaveType::where($setting_leave_type_query_params))->update(
                    collect($request_data)->only([
                        'name',
        'type',
        'amount',
        'description',
        // 'is_earning_enabled',
         "is_active"



                         // "is_default",
                        // "is_active",
                        // "business_id",

                    ])->toArray()
                )
                    // ->with("somthing")

                    ->first();
                if (!$setting_leave_type) {
                    return response()->json([
                        "message" => "something went wrong."
                    ], 500);
                }
                $setting_leave_type->employment_statuses()->sync($request_data["employment_statuses"]);
                return response($setting_leave_type, 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }



     public function toggleActiveSettingLeaveType(GetIdRequest $request)
     {

         try {
             $this->storeActivity($request, "DUMMY activity","DUMMY description");
             if (!$request->user()->hasPermissionTo('setting_leave_type_activate')) {
                 return response()->json([
                     "message" => "You can not perform this action"
                 ], 401);
             }
             $request_data = $request->validated();

             $setting_leave_type =  SettingLeaveType::where([
                "id" => $request_data["id"],
            ])
                ->first();
            if (!$setting_leave_type) {

                return response()->json([
                    "message" => "no data found"
                ], 404);
            }
            $should_update = 0;
            $should_disable = 0;
            if (empty(auth()->user()->business_id)) {

                if (auth()->user()->hasRole('superadmin')) {
                    if (($setting_leave_type->business_id != NULL || $setting_leave_type->is_default != 1)) {

                        return response()->json([
                            "message" => "You do not have permission to update this leave type due to role restrictions."
                        ], 403);
                    } else {
                        $should_update = 1;
                    }
                } else {
                    if ($setting_leave_type->business_id != NULL) {

                        return response()->json([
                            "message" => "You do not have permission to update this leave type due to role restrictions."
                        ], 403);
                    } else if ($setting_leave_type->is_default == 0) {

                        if($setting_leave_type->created_by != auth()->user()->id) {

                            return response()->json([
                                "message" => "You do not have permission to update this leave type due to role restrictions."
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
                if ($setting_leave_type->business_id != NULL) {
                    if (($setting_leave_type->business_id != auth()->user()->business_id)) {

                        return response()->json([
                            "message" => "You do not have permission to update this leave type due to role restrictions."
                        ], 403);
                    } else {
                        $should_update = 1;
                    }
                } else {
                    if ($setting_leave_type->is_default == 0) {
                        if ($setting_leave_type->created_by != auth()->user()->created_by) {

                            return response()->json([
                                "message" => "You do not have permission to update this leave type status due to role restrictions."
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
                $setting_leave_type->update([
                    'is_active' => !$setting_leave_type->is_active
                ]);
            }

            if($should_disable) {

                $disabled_setting_leave_type =    DisabledSettingLeaveType::where([
                    'setting_leave_type_id' => $setting_leave_type->id,
                    'business_id' => auth()->user()->business_id,
                    'created_by' => auth()->user()->id,
                ])->first();
                if(!$disabled_setting_leave_type) {
                    DisabledSettingLeaveType::create([
                        'setting_leave_type_id' => $setting_leave_type->id,
                        'business_id' => auth()->user()->business_id,
                        'created_by' => auth()->user()->id,
                    ]);
                } else {
                    $disabled_setting_leave_type->delete();
                }
            }


            return response()->json(['message' => 'leave type status updated successfully'], 200);
         } catch (Exception $e) {
             error_log($e->getMessage());
             return $this->sendError($e, 500, $request);
         }
     }


     public function toggleEarningEnabledSettingLeaveType(GetIdRequest $request)
     {

         try {
             $this->storeActivity($request, "DUMMY activity","DUMMY description");
             if (!$request->user()->hasPermissionTo('setting_leave_type_update')) {
                 return response()->json([
                     "message" => "You can not perform this action"
                 ], 401);
             }
             $request_data = $request->validated();

             $setting_leave_type =  SettingLeaveType::where([
                "id" => $request_data["id"],
            ])
            ->when(empty($request->user()->business_id), function ($query) use ($request) {
                return $query->where('setting_leave_types.business_id', NULL)
                             ->where('setting_leave_types.is_default', 1);
            })
            ->when(!empty($request->user()->business_id), function ($query) use ($request) {
                return $query->where('setting_leave_types.business_id', $request->user()->business_id);
            })
                ->first();
            if (!$setting_leave_type) {

                return response()->json([
                    "message" => "no data found"
                ] , 404);
            }



             $setting_leave_type->update([
                 'is_earning_enabled' => !$setting_leave_type->is_earning_enabled
             ]);

             return response()->json(['message' => 'User status updated successfully'], 200);
         } catch (Exception $e) {
             error_log($e->getMessage());
             return $this->sendError($e, 500, $request);
         }
     }

    public function getSettingLeaveTypes(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('setting_leave_type_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $created_by  = NULL;
            if(auth()->user()->business) {
                $created_by = auth()->user()->business->created_by;
            }


            $setting_leave_types = SettingLeaveType::with("employment_statuses")
            ->when(empty($request->user()->business_id), function ($query) use ($request, $created_by) {
                if (auth()->user()->hasRole('superadmin')) {
                    return $query->where('setting_leave_types.business_id', NULL)
                        ->where('setting_leave_types.is_default', 1)
                        ->when(isset($request->is_active), function ($query) use ($request) {
                            return $query->where('setting_leave_types.is_active', intval($request->is_active));
                        });
                } else {
                    return $query

                    ->where(function($query) use($request) {
                        $query->where('setting_leave_types.business_id', NULL)
                        ->where('setting_leave_types.is_default', 1)
                        ->where('setting_leave_types.is_active', 1)
                        ->when(isset($request->is_active), function ($query) use ($request) {
                            if(intval($request->is_active)) {
                                return $query->whereDoesntHave("disabled", function($q) {
                                    $q->whereIn("disabled_setting_leave_types.created_by", [auth()->user()->id]);
                                });
                            }

                        })
                        ->orWhere(function ($query) use ($request) {
                            $query->where('setting_leave_types.business_id', NULL)
                                ->where('setting_leave_types.is_default', 0)
                                ->where('setting_leave_types.created_by', auth()->user()->id)
                                ->when(isset($request->is_active), function ($query) use ($request) {
                                    return $query->where('setting_leave_types.is_active', intval($request->is_active));
                                });
                        });

                    });
                }
            })
                ->when(!empty($request->user()->business_id), function ($query) use ($request, $created_by) {
                    return $query
                    ->where(function($query) use($request, $created_by)  {
                        $query




                        // ->where(function($query)  use ($request, $created_by) {
                        //     $query->where('setting_leave_types.business_id', NULL)
                        //     ->where('setting_leave_types.is_default', 1)
                        //     ->where('setting_leave_types.is_active', 1)
                        //     ->whereDoesntHave("disabled", function($q) use($created_by) {
                        //         $q->whereIn("disabled_setting_leave_types.created_by", [$created_by]);
                        //     })
                        //     ->when(isset($request->is_active), function ($query) use ($request, $created_by)  {
                        //         if(intval($request->is_active)) {
                        //             return $query->whereDoesntHave("disabled", function($q) use($created_by) {
                        //                 $q->whereIn("disabled_setting_leave_types.business_id",[auth()->user()->business_id]);
                        //             });
                        //         }

                        //     });
                        // })





                        // ->orWhere(function ($query) use($request, $created_by){
                        //     $query->where('setting_leave_types.business_id', NULL)
                        //         ->where('setting_leave_types.is_default', 0)
                        //         ->where('setting_leave_types.created_by', $created_by)
                        //         ->where('setting_leave_types.is_active', 1)

                        //         ->when(isset($request->is_active), function ($query) use ($request) {
                        //             if(intval($request->is_active)) {
                        //                 return $query->whereDoesntHave("disabled", function($q) {
                        //                     $q->whereIn("disabled_setting_leave_types.business_id",[auth()->user()->business_id]);
                        //                 });
                        //             }

                        //         })


                        //         ;
                        // })

                        ->where(function ($query) use($request, $created_by) {
                            $query->where('setting_leave_types.business_id', auth()->user()->business_id)
                                ->where('setting_leave_types.is_default', 0)
                                ->when(isset($request->is_active), function ($query) use ($request, $created_by) {
                                    return $query
                                    ->where('setting_leave_types.is_active', intval($request->is_active))
                                    ->whereDoesntHave("disabled", function ($q) use ($created_by) {
                                        $q->whereIn("disabled_setting_leave_types.created_by", [$created_by]);
                                    })
                                    ->whereDoesntHave("disabled", function ($q) use ($created_by) {
                                        $q->whereIn("disabled_setting_leave_types.business_id", [auth()->user()->business_id]);
                                    })
                                    ;
                                });
                        });
                    });


                })
                ->when(!empty($request->search_key), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        $term = $request->search_key;
                        $query->where("setting_leave_types.name", "like", "%" . $term . "%")
                            ->orWhere("setting_leave_types.type", "like", "%" . $term . "%");
                    });
                })
                //    ->when(!empty($request->product_category_id), function ($query) use ($request) {
                //        return $query->where('product_category_id', $request->product_category_id);
                //    })
                ->when(!empty($request->start_date), function ($query) use ($request) {
                    return $query->where('setting_leave_types.created_at', ">=", $request->start_date);
                })
                ->when(!empty($request->end_date), function ($query) use ($request) {
                    return $query->where('setting_leave_types.created_at', "<=", ($request->end_date . ' 23:59:59'));
                })
                ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                    return $query->orderBy("setting_leave_types.id", $request->order_by);
                }, function ($query) {
                    return $query->orderBy("setting_leave_types.id", "DESC");
                })
                ->when(!empty($request->per_page), function ($query) use ($request) {
                    return $query->paginate($request->per_page);
                }, function ($query) {
                    return $query->get();
                });;



            return response()->json($setting_leave_types, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }

    


    public function getSettingLeaveTypeById($id, Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('setting_leave_type_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $setting_leave_type =  SettingLeaveType::with("employment_statuses")->where([
                "id" => $id,
            ])
                ->first();


                if (!$setting_leave_type) {

                    return response()->json([
                        "message" => "no data found"
                    ], 404);
                }
                if (empty(auth()->user()->business_id)) {

                    if (auth()->user()->hasRole('superadmin')) {
                        if (($setting_leave_type->business_id != NULL || $setting_leave_type->is_default != 1)) {

                            return response()->json([
                                "message" => "You do not have permission to update this leave type due to role restrictions."
                            ], 403);
                        }
                    } else {
                        if ($setting_leave_type->business_id != NULL) {

                            return response()->json([
                                "message" => "You do not have permission to update this leave type due to role restrictions."
                            ], 403);
                        } else if ($setting_leave_type->is_default == 0 && $setting_leave_type->created_by != auth()->user()->id) {

                                return response()->json([
                                    "message" => "You do not have permission to update this leave type due to role restrictions."
                                ], 403);

                        }
                    }
                } else {
                    if ($setting_leave_type->business_id != NULL) {
                        if (($setting_leave_type->business_id != auth()->user()->business_id)) {

                            return response()->json([
                                "message" => "You do not have permission to update this leave type due to role restrictions."
                            ], 403);
                        }
                    } else {
                        if ($setting_leave_type->is_default == 0) {
                            if ($setting_leave_type->created_by != auth()->user()->created_by) {

                                return response()->json([
                                    "message" => "You do not have permission to update this leave type due to role restrictions."
                                ], 403);
                            }
                        }
                    }
                }




            return response()->json($setting_leave_type, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }


    public function deleteSettingLeaveTypesByIds(Request $request, $ids)
    {

        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('setting_leave_type_delete')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $idsArray = explode(',', $ids);
            $existingIds = SettingLeaveType::whereIn('id', $idsArray)
            ->when(empty($request->user()->business_id), function ($query) use ($request) {
                if ($request->user()->hasRole("superadmin")) {
                    return $query->where('setting_leave_types.business_id', NULL)
                        ->where('setting_leave_types.is_default', 1);
                } else {
                    return $query->where('setting_leave_types.business_id', NULL)
                        ->where('setting_leave_types.is_default', 0)
                        ->where('setting_leave_types.created_by', $request->user()->id);
                }
            })
            ->when(!empty($request->user()->business_id), function ($query) use ($request) {
                return $query->where('setting_leave_types.business_id', $request->user()->business_id)
                    ->where('setting_leave_types.is_default', 0);
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

          $leave_exists =  Leave::whereIn("leave_type_id",$existingIds)->exists();
            if($leave_exists) {
                $conflictingLeaves = Leave::whereIn("leave_type_id", $existingIds)->get(['id']);


                return response()->json([
                    "message" => "Some leaves are associated with the specified setting_leave_types",
                    "conflicting_leaves" => $conflictingLeaves
                ], 409);

            }




            SettingLeaveType::destroy($existingIds);


            return response()->json(["message" => "data deleted sussfully","deleted_ids" => $existingIds], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
}
