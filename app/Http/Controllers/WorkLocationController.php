<?php

namespace App\Http\Controllers;

use App\Http\Components\WorkLocationComponent;
use App\Http\Requests\GetIdRequest;
use App\Http\Requests\WorkLocationCreateRequest;
use App\Http\Requests\WorkLocationUpdateRequest;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\DisabledWorkLocation;
use App\Models\User;
use App\Models\WorkLocation;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkLocationController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil;

    protected $workLocationComponent;


    public function __construct(WorkLocationComponent $workLocationComponent)
    {
        $this->workLocationComponent = $workLocationComponent;

    }


    public function createWorkLocation(WorkLocationCreateRequest $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            return DB::transaction(function () use ($request) {
                if (!$request->user()->hasPermissionTo('work_location_create')) {
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




                $work_location =  WorkLocation::create($request_data);




                return response($work_location, 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }



    public function updateWorkLocation(WorkLocationUpdateRequest $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            return DB::transaction(function () use ($request) {
                if (!$request->user()->hasPermissionTo('work_location_update')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }
                $request_data = $request->validated();



                $work_location_query_params = [
                    "id" => $request_data["id"],
                ];

                $work_location  =  tap(WorkLocation::where($work_location_query_params))->update(
                    collect($request_data)->only([
                        'name',
                        'description',
                        'address',
                        "is_location_enabled",
                        "latitude",
                        "longitude",
                        "is_geo_location_enabled",
                        "is_ip_enabled",
                        "max_radius",
                        "ip_address",

                        // "is_default",
                        // "is_active",
                        // "business_id",
                        // "created_by"

                    ])->toArray()
                )
                    // ->with("somthing")

                    ->first();
                if (!$work_location) {
                    return response()->json([
                        "message" => "something went wrong."
                    ], 500);
                }




                return response($work_location, 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }


    public function toggleActiveWorkLocation(GetIdRequest $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('work_location_activate')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $request_data = $request->validated();

            $work_location =  WorkLocation::where([
                "id" => $request_data["id"],
            ])
                ->first();
            if (!$work_location) {

                return response()->json([
                    "message" => "no data found"
                ], 404);
            }
            $should_update = 0;
            $should_disable = 0;
            if (empty(auth()->user()->business_id)) {

                if (auth()->user()->hasRole('superadmin')) {
                    if (($work_location->business_id != NULL || $work_location->is_default != 1)) {

                        return response()->json([
                            "message" => "You do not have permission to update this work location due to role restrictions."
                        ], 403);
                    } else {
                        $should_update = 1;
                    }
                } else {
                    if ($work_location->business_id != NULL) {

                        return response()->json([
                            "message" => "You do not have permission to update this work location due to role restrictions."
                        ], 403);
                    } else if ($work_location->is_default == 0) {

                        if($work_location->created_by != auth()->user()->id) {

                            return response()->json([
                                "message" => "You do not have permission to update this work location due to role restrictions."
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
                if ($work_location->business_id != NULL) {
                    if (($work_location->business_id != auth()->user()->business_id)) {

                        return response()->json([
                            "message" => "You do not have permission to update this work location due to role restrictions."
                        ], 403);
                    } else {
                        $should_update = 1;
                    }
                } else {
                    if ($work_location->is_default == 0) {
                        if ($work_location->created_by != auth()->user()->created_by) {

                            return response()->json([
                                "message" => "You do not have permission to update this work location due to role restrictions."
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
                $work_location->update([
                    'is_active' => !$work_location->is_active
                ]);
            }

            if($should_disable) {

                $disabled_work_location =    DisabledWorkLocation::where([
                    'work_location_id' => $work_location->id,
                    'business_id' => auth()->user()->business_id,
                    'created_by' => auth()->user()->id,
                ])->first();
                if(!$disabled_work_location) {
                    DisabledWorkLocation::create([
                        'work_location_id' => $work_location->id,
                        'business_id' => auth()->user()->business_id,
                        'created_by' => auth()->user()->id,
                    ]);
                } else {
                    $disabled_work_location->delete();
                }
            }


            return response()->json(['message' => 'WorkLocation status updated successfully'], 200);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }



    public function getWorkLocations(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('work_location_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

   $work_locations = $this->workLocationComponent->getWorkLocations();


            return response()->json($work_locations, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }

    


    public function getWorkLocationById($id, Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('work_location_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $work_location =  WorkLocation::where([
                "work_locations.id" => $id,
            ])

                ->first();

                if (!$work_location) {

                    return response()->json([
                        "message" => "no data found"
                    ], 404);
                }

                if (empty(auth()->user()->business_id)) {

                    if (auth()->user()->hasRole('superadmin')) {
                        if (($work_location->business_id != NULL || $work_location->is_default != 1)) {

                            return response()->json([
                                "message" => "You do not have permission to update this work location due to role restrictions."
                            ], 403);
                        }
                    } else {
                        if ($work_location->business_id != NULL) {

                            return response()->json([
                                "message" => "You do not have permission to update this work location due to role restrictions."
                            ], 403);
                        } else if ($work_location->is_default == 0 && $work_location->created_by != auth()->user()->id) {

                                return response()->json([
                                    "message" => "You do not have permission to update this work location due to role restrictions."
                                ], 403);

                        }
                    }
                } else {
                    if ($work_location->business_id != NULL) {
                        if (($work_location->business_id != auth()->user()->business_id)) {

                            return response()->json([
                                "message" => "You do not have permission to update this work location due to role restrictions."
                            ], 403);
                        }
                    } else {
                        if ($work_location->is_default == 0) {
                            if ($work_location->created_by != auth()->user()->created_by) {

                                return response()->json([
                                    "message" => "You do not have permission to update this work location due to role restrictions."
                                ], 403);
                            }
                        }
                    }
                }



            return response()->json($work_location, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }



    public function deleteWorkLocationsByIds(Request $request, $ids)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('work_location_delete')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $idsArray = explode(',', $ids);
            $existingIds = WorkLocation::whereIn('id', $idsArray)
                ->when(empty($request->user()->business_id), function ($query) use ($request) {
                    if ($request->user()->hasRole("superadmin")) {
                        return $query->where('work_locations.business_id', NULL)
                            ->where('work_locations.is_default', 1);
                    } else {
                        return $query->where('work_locations.business_id', NULL)
                            ->where('work_locations.is_default', 0)
                            ->where('work_locations.created_by', $request->user()->id);
                    }
                })
                ->when(!empty($request->user()->business_id), function ($query) use ($request) {
                    return $query->where('work_locations.business_id', $request->user()->business_id)
                        ->where('work_locations.is_default', 0);
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


            $conflictingUsers = User::whereHas("work_locations", function($query) use($existingIds) {
                $query->whereIn("work_location_id", $existingIds);
            })->get(['id', 'first_name', 'last_name']);

            if ($conflictingUsers->isNotEmpty()) {
                return response()->json([
                    "message" => "Some users are associated with the specified departments",
                    "conflicting_users" => $conflictingUsers
                ], 409);
            }







            WorkLocation::destroy($existingIds);


            return response()->json(["message" => "data deleted sussfully", "deleted_ids" => $existingIds], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
}

