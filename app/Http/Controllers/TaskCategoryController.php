<?php

namespace App\Http\Controllers;

use App\Http\Requests\GetIdRequest;
use App\Http\Requests\TaskCategoryCreateRequest;
use App\Http\Requests\TaskCategoryUpdateRequest;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\ModuleUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\DisabledTaskCategory;
use App\Models\Task;
use App\Models\TaskCategory;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskCategoryController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil, ModuleUtil;


    public function createTaskCategory(TaskCategoryCreateRequest $request)
    {
        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            $this->isModuleEnabled("task_management");

                if (!$request->user()->hasPermissionTo('task_category_create')) {
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


                $task_category =  TaskCategory::create($request_data);


                DB::commit();
                return response($task_category, 201);


        } catch (Exception $e) {
          DB::rollBack();
            return $this->sendError($e, 500, $request);
        }
    }



    public function updateTaskCategory(TaskCategoryUpdateRequest $request)
    {

        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            $this->isModuleEnabled("task_management");

                if (!$request->user()->hasPermissionTo('task_category_update')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }
                // $business_id =  $request->user()->business_id;
                $request_data = $request->validated();



                $task_category_query_params = [
                    "id" => $request_data["id"],
                    // "business_id" => $business_id
                ];


                // if ($request->user()->hasRole('superadmin')) {
                //     if(!($task_category_prev->business_id == NULL && $task_category_prev->is_default == 1)) {
                //         return response()->json([
                //             "message" => "You do not have permission to update this task category due to role restrictions."
                //         ], 403);
                //     }

                // }
                // else {
                //     if(!($task_category_prev->business_id == $request->user()->business_id)) {
                //         return response()->json([
                //             "message" => "You do not have permission to update this task category due to role restrictions."
                //         ], 403);
                //     }
                // }
                $task_category  =  tap(TaskCategory::where($task_category_query_params))->update(
                    collect($request_data)->only([
                        'name',
                        "color",
                        'description',
                         // "is_default",
                        // "is_active",
                        // "business_id",

                    ])->toArray()
                )
                    // ->with("somthing")

                    ->first();
                if (!$task_category) {
                    return response()->json([
                        "message" => "something went wrong."
                    ], 500);
                }

                DB::commit();
                return response($task_category, 201);

        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError($e, 500, $request);
        }
    }


     public function toggleActiveTaskCategory(GetIdRequest $request)
     {

        DB::beginTransaction();
         try {
             $this->storeActivity($request, "DUMMY activity", "DUMMY description");
             $this->isModuleEnabled("task_management");
             if (!$request->user()->hasPermissionTo('task_category_activate')) {
                 return response()->json([
                     "message" => "You can not perform this action"
                 ], 401);
             }
             $request_data = $request->validated();

             $task_category =  TaskCategory::where([
                 "id" => $request_data["id"],
             ])
                 ->first();
             if (!$task_category) {

                 return response()->json([
                     "message" => "no data found"
                 ], 404);
             }
             $should_update = 0;
             $should_disable = 0;
             if (empty(auth()->user()->business_id)) {

                 if (auth()->user()->hasRole('superadmin')) {
                     if (($task_category->business_id != NULL || $task_category->is_default != 1)) {

                         return response()->json([
                             "message" => "You do not have permission to update this task category due to role restrictions."
                         ], 403);
                     } else {
                         $should_update = 1;
                     }
                 } else {
                     if ($task_category->business_id != NULL) {

                         return response()->json([
                             "message" => "You do not have permission to update this task category due to role restrictions."
                         ], 403);
                     } else if ($task_category->is_default == 0) {

                         if($task_category->created_by != auth()->user()->id) {

                             return response()->json([
                                 "message" => "You do not have permission to update this task category due to role restrictions."
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
                 if ($task_category->business_id != NULL) {
                     if (($task_category->business_id != auth()->user()->business_id)) {

                         return response()->json([
                             "message" => "You do not have permission to update this task category due to role restrictions."
                         ], 403);
                     } else {
                         $should_update = 1;
                     }
                 } else {
                     if ($task_category->is_default == 0) {
                         if ($task_category->created_by != auth()->user()->created_by) {

                             return response()->json([
                                 "message" => "You do not have permission to update this task category due to role restrictions."
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
                 $task_category->update([
                     'is_active' => !$task_category->is_active
                 ]);
             }

             if($should_disable) {
                 $disabled_task_category =    DisabledTaskCategory::where([
                     'task_category_id' => $task_category->id,
                     'business_id' => auth()->user()->business_id,
                     'created_by' => auth()->user()->id,
                 ])->first();
                 if(!$disabled_task_category) {
                    DisabledTaskCategory::create([
                         'task_category_id' => $task_category->id,
                         'business_id' => auth()->user()->business_id,
                         'created_by' => auth()->user()->id,
                     ]);
                 } else {
                     $disabled_task_category->delete();
                 }
             }

   DB::commit();
             return response()->json(['message' => 'Task Category status updated successfully'], 200);
         } catch (Exception $e) {
             DB::rollBack();
             return $this->sendError($e, 500, $request);
         }
     }

    public function getTaskCategories(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            $this->isModuleEnabled("task_management");
            if (!$request->user()->hasPermissionTo('task_category_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $created_by  = NULL;
            if(auth()->user()->business) {
                $created_by = auth()->user()->business->created_by;
            }

            $task_categories = TaskCategory::with("tasks")->when(empty($request->user()->business_id), function ($query) use ($request, $created_by) {
                if (auth()->user()->hasRole('superadmin')) {
                    return $query->where('task_categories.business_id', NULL)
                        ->where('task_categories.is_default', 1)
                        ->when(isset($request->is_active), function ($query) use ($request) {
                            return $query->where('task_categories.is_active', intval($request->is_active));
                        });
                } else {
                    return $query

                    ->where(function($query) use($request) {
                        $query->where('task_categories.business_id', NULL)
                        ->where('task_categories.is_default', 1)
                        ->where('task_categories.is_active', 1)
                        ->when(isset($request->is_active), function ($query) use ($request) {
                            if(intval($request->is_active)) {
                                return $query->whereDoesntHave("disabled", function($q) {
                                    $q->whereIn("disabled_task_categories.created_by", [auth()->user()->id]);
                                });
                            }

                        })
                        ->orWhere(function ($query) use ($request) {
                            $query->where('task_categories.business_id', NULL)
                                ->where('task_categories.is_default', 0)
                                ->where('task_categories.created_by', auth()->user()->id)
                                ->when(isset($request->is_active), function ($query) use ($request) {
                                    return $query->where('task_categories.is_active', intval($request->is_active));
                                });
                        });

                    });
                }
            })
                ->when(!empty($request->user()->business_id), function ($query) use ($request, $created_by) {
                    return $query
                    ->where(function($query) use($request, $created_by) {


                        $query->where('task_categories.business_id', NULL)
                        ->where('task_categories.is_default', 1)
                        ->where('task_categories.is_active', 1)
                        ->whereDoesntHave("disabled", function($q) use($created_by) {
                            $q->whereIn("disabled_task_categories.created_by", [$created_by]);
                        })
                        ->when(isset($request->is_active), function ($query) use ($request, $created_by)  {
                            if(intval($request->is_active)) {
                                return $query->whereDoesntHave("disabled", function($q) use($created_by) {
                                    $q->whereIn("disabled_task_categories.business_id",[auth()->user()->business_id]);
                                });
                            }

                        })


                        ->orWhere(function ($query) use($request, $created_by){
                            $query->where('task_categories.business_id', NULL)
                                ->where('task_categories.is_default', 0)
                                ->where('task_categories.created_by', $created_by)
                                ->where('task_categories.is_active', 1)

                                ->when(isset($request->is_active), function ($query) use ($request) {
                                    if(intval($request->is_active)) {
                                        return $query->whereDoesntHave("disabled", function($q) {
                                            $q->whereIn("disabled_task_categories.business_id",[auth()->user()->business_id]);
                                        });
                                    }

                                })


                                ;
                        })
                        ->orWhere(function ($query) use($request) {
                            $query->where('task_categories.business_id', auth()->user()->business_id)
                                ->where('task_categories.is_default', 0)
                                ->when(isset($request->is_active), function ($query) use ($request) {
                                    return $query->where('task_categories.is_active', intval($request->is_active));
                                });;
                        });
                    });


                })


                ->when(!empty($request->search_key), function ($query) use ($request) {
                return $query->where(function ($query) use ($request) {
                    $term = $request->search_key;
                    $query->where("task_categories.name", "like", "%" . $term . "%")
                        ->orWhere("task_categories.description", "like", "%" . $term . "%");
                });
            })

            //     when($request->user()->hasRole('superadmin'), function ($query) use ($request) {
            //     return $query->where('task_categories.business_id', NULL)
            //                  ->where('task_categories.is_default', 1);
            // })
            // ->when(!$request->user()->hasRole('superadmin'), function ($query) use ($request) {
            //     return $query->where('task_categories.business_id', $request->user()->business_id);
            // })


                //    ->when(!empty($request->product_category_id), function ($query) use ($request) {
                //        return $query->where('product_category_id', $request->product_category_id);
                //    })
                ->when(!empty($request->task_id), function ($query) use ($request) {
                    return $query->whereHas('tasks',function($query) use($request) {
                        $query->where('tasks.id',$request->task_id);
                    });
                })
                ->when(!empty($request->start_date), function ($query) use ($request) {
                    return $query->where('task_categories.created_at', ">=", $request->start_date);
                })
                ->when(!empty($request->end_date), function ($query) use ($request) {
                    return $query->where('task_categories.created_at', "<=", ($request->end_date . ' 23:59:59'));
                })
                ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                    return $query->orderBy("task_categories.id", $request->order_by);
                }, function ($query) {
                    return $query->orderBy("task_categories.id", "DESC");
                })
                ->when(!empty($request->per_page), function ($query) use ($request) {
                    return $query->paginate($request->per_page);
                }, function ($query) {
                    return $query->get();
                });;



            return response()->json($task_categories, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }

     


    public function getTaskCategoryById($id, Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            $this->isModuleEnabled("task_management");

            if (!$request->user()->hasPermissionTo('task_category_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $task_category =  TaskCategory::with("tasks")->where([
                "id" => $id,
            ])
            // ->when($request->user()->hasRole('superadmin'), function ($query) use ($request) {
            //     return $query->where('task_categories.business_id', NULL)
            //                  ->where('task_categories.is_default', 1);
            // })
            // ->when(!$request->user()->hasRole('superadmin'), function ($query) use ($request) {
            //     return $query->where('task_categories.business_id', $request->user()->business_id);
            // })
                ->first();
            if (!$task_category) {

                return response()->json([
                    "message" => "no data found"
                ], 404);
            }

            if (empty(auth()->user()->business_id)) {

                if (auth()->user()->hasRole('superadmin')) {
                    if (($task_category->business_id != NULL || $task_category->is_default != 1)) {

                        return response()->json([
                            "message" => "You do not have permission to update this task category due to role restrictions."
                        ], 403);
                    }
                } else {
                    if ($task_category->business_id != NULL) {

                        return response()->json([
                            "message" => "You do not have permission to update this task category due to role restrictions."
                        ], 403);
                    } else if ($task_category->is_default == 0 && $task_category->created_by != auth()->user()->id) {

                            return response()->json([
                                "message" => "You do not have permission to update this task category due to role restrictions."
                            ], 403);

                    }
                }
            } else {
                if ($task_category->business_id != NULL) {
                    if (($task_category->business_id != auth()->user()->business_id)) {

                        return response()->json([
                            "message" => "You do not have permission to update this task category due to role restrictions."
                        ], 403);
                    }
                } else {
                    if ($task_category->is_default == 0) {
                        if ($task_category->created_by != auth()->user()->created_by) {

                            return response()->json([
                                "message" => "You do not have permission to update this task category due to role restrictions."
                            ], 403);
                        }
                    }
                }
            }

            return response()->json($task_category, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }


    public function deleteTaskCategoriesByIds(Request $request, $ids)
    {

        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            $this->isModuleEnabled("task_management");
            if (!$request->user()->hasPermissionTo('task_category_delete')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $idsArray = explode(',', $ids);
            $existingIds = TaskCategory::whereIn('id', $idsArray)
            ->when(empty($request->user()->business_id), function ($query) use ($request) {
                if ($request->user()->hasRole("superadmin")) {
                    return $query->where('task_categories.business_id', NULL)
                        ->where('task_categories.is_default', 1);
                } else {
                    return $query->where('task_categories.business_id', NULL)
                        ->where('task_categories.is_default', 0)
                        ->where('task_categories.created_by', $request->user()->id);
                }
            })
            ->when(!empty($request->user()->business_id), function ($query) use ($request) {
                return $query->where('task_categories.business_id', $request->user()->business_id)
                    ->where('task_categories.is_default', 0);
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

            $task_exists =  Task::whereIn("task_category_id", $existingIds)->exists();
            if ($task_exists) {

                return response()->json([
                    "message" => "Some user's are using some of these task categories.",
                    // "conflicting_users" => $conflictingSocialSites
                ], 409);
            }

            TaskCategory::destroy($existingIds);


            return response()->json(["message" => "data deleted sussfully","deleted_ids" => $existingIds], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
}
