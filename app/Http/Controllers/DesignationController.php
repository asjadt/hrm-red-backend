<?php

namespace App\Http\Controllers;

use App\Http\Requests\DesignationCreateRequest;
use App\Http\Requests\DesignationUpdateRequest;
use App\Http\Requests\GetIdRequest;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\Designation;
use App\Models\DisabledDesignation;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DesignationController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil;

    public function createDesignation(DesignationCreateRequest $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            return DB::transaction(function () use ($request) {
                if (!$request->user()->hasPermissionTo('designation_create')) {
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




                $designation =  Designation::create($request_data);




                return response($designation, 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }


    public function updateDesignation(DesignationUpdateRequest $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            return DB::transaction(function () use ($request) {
                if (!$request->user()->hasPermissionTo('designation_update')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }
                $request_data = $request->validated();



                $designation_query_params = [
                    "id" => $request_data["id"],
                ];

                $designation  =  tap(Designation::where($designation_query_params))->update(
                    collect($request_data)->only([
                        'name',
                        'description',
                        // "is_default",
                        // "is_active",
                        // "business_id",
                        // "created_by"

                    ])->toArray()
                )
                    // ->with("somthing")

                    ->first();
                if (!$designation) {
                    return response()->json([
                        "message" => "something went wrong."
                    ], 500);
                }




                return response($designation, 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }



    public function toggleActiveDesignation(GetIdRequest $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('designation_activate')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $request_data = $request->validated();

            $designation =  Designation::where([
                "id" => $request_data["id"],
            ])
                ->first();
            if (!$designation) {

                return response()->json([
                    "message" => "no data found"
                ], 404);
            }
            $should_update = 0;
            $should_disable = 0;
            if (empty(auth()->user()->business_id)) {

                if (auth()->user()->hasRole('superadmin')) {
                    if (($designation->business_id != NULL || $designation->is_default != 1)) {

                        return response()->json([
                            "message" => "You do not have permission to update this designation due to role restrictions."
                        ], 403);
                    } else {
                        $should_update = 1;
                    }
                } else {
                    if ($designation->business_id != NULL) {

                        return response()->json([
                            "message" => "You do not have permission to update this designation due to role restrictions."
                        ], 403);
                    } else if ($designation->is_default == 0) {

                        if($designation->created_by != auth()->user()->id) {

                            return response()->json([
                                "message" => "You do not have permission to update this designation due to role restrictions."
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
                if ($designation->business_id != NULL) {
                    if (($designation->business_id != auth()->user()->business_id)) {

                        return response()->json([
                            "message" => "You do not have permission to update this designation due to role restrictions."
                        ], 403);
                    } else {
                        $should_update = 1;
                    }
                } else {
                    if ($designation->is_default == 0) {
                        if ($designation->created_by != auth()->user()->created_by) {

                            return response()->json([
                                "message" => "You do not have permission to update this designation due to role restrictions."
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
                $designation->update([
                    'is_active' => !$designation->is_active
                ]);
            }

            if($should_disable) {

                $disabled_designation =    DisabledDesignation::where([
                    'designation_id' => $designation->id,
                    'business_id' => auth()->user()->business_id,
                    'created_by' => auth()->user()->id,
                ])->first();
                if(!$disabled_designation) {
                    DisabledDesignation::create([
                        'designation_id' => $designation->id,
                        'business_id' => auth()->user()->business_id,
                        'created_by' => auth()->user()->id,
                    ]);
                } else {
                    $disabled_designation->delete();
                }
            }


            return response()->json(['message' => 'Designation status updated successfully'], 200);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }


    public function getDesignations(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('designation_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $created_by  = NULL;
            if(auth()->user()->business) {
                $created_by = auth()->user()->business->created_by;
            }



            $designations = Designation::when(empty($request->user()->business_id), function ($query) use ($request, $created_by) {
                if (auth()->user()->hasRole('superadmin')) {
                    return $query->where('designations.business_id', NULL)
                        ->where('designations.is_default', 1)
                        ->when(isset($request->is_active), function ($query) use ($request) {
                            return $query->where('designations.is_active', intval($request->is_active));
                        });
                } else {
                    return $query

                    ->where(function($query) use($request) {
                        $query->where('designations.business_id', NULL)
                        ->where('designations.is_default', 1)
                        ->where('designations.is_active', 1)
                        ->when(isset($request->is_active), function ($query) use ($request) {
                            if(intval($request->is_active)) {
                                return $query->whereDoesntHave("disabled", function($q) {
                                    $q->whereIn("disabled_designations.created_by", [auth()->user()->id]);
                                });
                            }

                        })
                        ->orWhere(function ($query) use ($request) {
                            $query->where('designations.business_id', NULL)
                                ->where('designations.is_default', 0)
                                ->where('designations.created_by', auth()->user()->id)
                                ->when(isset($request->is_active), function ($query) use ($request) {
                                    return $query->where('designations.is_active', intval($request->is_active));
                                });
                        });

                    });
                }
            })
                ->when(!empty($request->user()->business_id), function ($query) use ($request, $created_by) {
                    return $query
                    ->where(function($query) use($request, $created_by) {


                        $query->where('designations.business_id', NULL)
                        ->where('designations.is_default', 1)
                        ->where('designations.is_active', 1)
                        ->whereDoesntHave("disabled", function($q) use($created_by) {
                            $q->whereIn("disabled_designations.created_by", [$created_by]);
                        })
                        ->when(isset($request->is_active), function ($query) use ($request, $created_by)  {
                            if(intval($request->is_active)) {
                                return $query->whereDoesntHave("disabled", function($q) use($created_by) {
                                    $q->whereIn("disabled_designations.business_id",[auth()->user()->business_id]);
                                });
                            }

                        })


                        ->orWhere(function ($query) use($request, $created_by){
                            $query->where('designations.business_id', NULL)
                                ->where('designations.is_default', 0)
                                ->where('designations.created_by', $created_by)
                                ->where('designations.is_active', 1)

                                ->when(isset($request->is_active), function ($query) use ($request) {
                                    if(intval($request->is_active)) {
                                        return $query->whereDoesntHave("disabled", function($q) {
                                            $q->whereIn("disabled_designations.business_id",[auth()->user()->business_id]);
                                        });
                                    }

                                })


                                ;
                        })
                        ->orWhere(function ($query) use($request) {
                            $query->where('designations.business_id', auth()->user()->business_id)
                                ->where('designations.is_default', 0)
                                ->when(isset($request->is_active), function ($query) use ($request) {
                                    return $query->where('designations.is_active', intval($request->is_active));
                                });;
                        });
                    });


                })
                ->when(!empty($request->search_key), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        $term = $request->search_key;
                        $query->where("designations.name", "like", "%" . $term . "%")
                            ->orWhere("designations.description", "like", "%" . $term . "%");
                    });
                })
                //    ->when(!empty($request->product_category_id), function ($query) use ($request) {
                //        return $query->where('product_category_id', $request->product_category_id);
                //    })
                ->when(!empty($request->start_date), function ($query) use ($request) {
                    return $query->where('designations.created_at', ">=", $request->start_date);
                })
                ->when(!empty($request->end_date), function ($query) use ($request) {
                    return $query->where('designations.created_at', "<=", ($request->end_date . ' 23:59:59'));
                })
                ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                    return $query->orderBy("designations.id", $request->order_by);
                }, function ($query) {
                    return $query->orderBy("designations.id", "DESC");
                })
                ->when(!empty($request->per_page), function ($query) use ($request) {
                    return $query->paginate($request->per_page);
                }, function ($query) {
                    return $query->get();
                });;



            return response()->json($designations, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }



    public function getDesignationById($id, Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('designation_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $designation =  Designation::where([
                "designations.id" => $id,
            ])

                ->first();

                if (!$designation) {

                    return response()->json([
                        "message" => "no data found"
                    ], 404);
                }

                if (empty(auth()->user()->business_id)) {

                    if (auth()->user()->hasRole('superadmin')) {
                        if (($designation->business_id != NULL || $designation->is_default != 1)) {


                            return response()->json([
                                "message" => "You do not have permission to update this designation due to role restrictions."
                            ], 403);
                        }
                    } else {
                        if ($designation->business_id != NULL) {

                            return response()->json([
                                "message" => "You do not have permission to update this designation due to role restrictions."
                            ], 403);
                        } else if ($designation->is_default == 0 && $designation->created_by != auth()->user()->id) {

                                return response()->json([
                                    "message" => "You do not have permission to update this designation due to role restrictions."
                                ], 403);

                        }
                    }
                } else {
                    if ($designation->business_id != NULL) {
                        if (($designation->business_id != auth()->user()->business_id)) {

                            return response()->json([
                                "message" => "You do not have permission to update this designation due to role restrictions."
                            ], 403);
                        }
                    } else {
                        if ($designation->is_default == 0) {
                            if ($designation->created_by != auth()->user()->created_by) {

                                return response()->json([
                                    "message" => "You do not have permission to update this designation due to role restrictions."
                                ], 403);
                            }
                        }
                    }
                }



            return response()->json($designation, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }



    public function deleteDesignationsByIds(Request $request, $ids)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('designation_delete')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $idsArray = explode(',', $ids);
            $existingIds = Designation::whereIn('id', $idsArray)
                ->when(empty($request->user()->business_id), function ($query) use ($request) {
                    if ($request->user()->hasRole("superadmin")) {
                        return $query->where('designations.business_id', NULL)
                            ->where('designations.is_default', 1);
                    } else {
                        return $query->where('designations.business_id', NULL)
                            ->where('designations.is_default', 0)
                            ->where('designations.created_by', $request->user()->id);
                    }
                })
                ->when(!empty($request->user()->business_id), function ($query) use ($request) {
                    return $query->where('designations.business_id', $request->user()->business_id)
                        ->where('designations.is_default', 0);
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


            $conflictingUsers = User::whereIn("designation_id", $existingIds)->get([
                'id', 'first_Name',
                'last_Name',
            ]);

            if ($conflictingUsers->isNotEmpty()) {
                return response()->json([
                    "message" => "Some users are associated with the specified designations",
                    "conflicting_users" => $conflictingUsers
                ], 409);
            }



            Designation::destroy($existingIds);


            return response()->json(["message" => "data deleted sussfully", "deleted_ids" => $existingIds], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
}
