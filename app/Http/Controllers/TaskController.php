<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskCreateRequest;
use App\Http\Requests\TaskUpdateRequest;
use App\Http\Utils\BasicUtil;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\ModuleUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\Task;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil, ModuleUtil,BasicUtil;


    public function createTask(TaskCreateRequest $request)
    {
        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");

            $this->isModuleEnabled("task_management");





                if (!$request->user()->hasPermissionTo('task_create')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }

                $request_data = $request->validated();


                $request_data["business_id"] = $request->user()->business_id;
                $request_data["is_active"] = true;
                $request_data["created_by"] = $request->user()->id;
                $request_data["assigned_by"] = $request->user()->id;




                $request_data["unique_identifier"] = $this->generateUniqueId("Project",$request_data["project_id"],"Task");


                $task =  Task::create($request_data);
                $task->assignees()->sync($request_data['assignees']);
                $task->labels()->sync($request_data['labels']);

                DB::commit();
                return response($task, 201);

        } catch (Exception $e) {

            DB::rollBack();
            return $this->sendError($e, 500, $request);
        }
    }



    public function updateTask(TaskUpdateRequest $request)
    {

        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            $this->isModuleEnabled("task_management");

                if (!$request->user()->hasPermissionTo('task_update')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }
                $business_id =  $request->user()->business_id;
                $request_data = $request->validated();




                $task_query_params = [
                    "id" => $request_data["id"],
                    "business_id" => $business_id
                ];
                // $task_prev = Task::where($task_query_params)
                //     ->first();
                // if (!$task_prev) {
                //     return response()->json([
                //         "message" => "no task listing found"
                //     ], 404);
                // }

                $task  =  tap(Task::where($task_query_params))->update(
                    collect($request_data)->only([
                        'name',
                        'description',


                      'assets',

                      'cover',


                        'start_date',
                        'due_date',
                        'end_date',
                        'status',
                        'project_id',
                        'parent_task_id',
                        "task_category_id",
                        'assigned_by',

                        // "is_active",
                        // "business_id",
                        // "created_by"

                    ])->toArray()
                )
                    // ->with("somthing")

                    ->first();
                if (!$task) {
                    return response()->json([
                        "message" => "something went wrong."
                    ], 500);
                }


                $task->labels()->sync($request_data['labels']);



                $task->assignees()->sync($request_data['assignees']);

                DB::commit();
                return response($task, 201);

        } catch (Exception $e) {
           DB::rollBack();
            return $this->sendError($e, 500, $request);
        }
    }



    public function getTasks(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            $this->isModuleEnabled("task_management");


            if (!$request->user()->hasPermissionTo('task_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;
            $tasks = Task::with("assigned_by","assignees","labels")

            ->where(
                [
                    "business_id" => $business_id
                ]
            )
                ->when(!empty($request->search_key), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        $term = $request->search_key;
                        $query->where("name", "like", "%" . $term . "%")
                            ->orWhere("location", "like", "%" . $term . "%")
                            ->orWhere("description", "like", "%" . $term . "%");
                    });
                })
                //    ->when(!empty($request->product_category_id), function ($query) use ($request) {
                //        return $query->where('product_category_id', $request->product_category_id);
                //    })

                ->when(!empty($request->project_id), function ($query) use ($request) {
                    return $query->where('project_id' , $request->project_id);
                })
                ->when(!empty($request->status), function ($query) use ($request) {
                    return $query->where('status' , $request->status);
                })

                ->when(!empty($request->start_date), function ($query) use ($request) {
                    return $query->where('created_at', ">=", $request->start_date);
                })
                ->when(!empty($request->end_date), function ($query) use ($request) {
                    return $query->where('created_at', "<=", ($request->end_date . ' 23:59:59'));
                })
                ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                    return $query->orderBy("tasks.id", $request->order_by);
                }, function ($query) {
                    return $query->orderBy("tasks.id", "DESC");
                })
                ->select('tasks.*',

                 )
                ->when(!empty($request->per_page), function ($query) use ($request) {
                    return $query->paginate($request->per_page);
                }, function ($query) {
                    return $query->get();
                });



            return response()->json($tasks, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }

     


    public function getTaskById($id, Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
               $this->isModuleEnabled("task_management");


            if (!$request->user()->hasPermissionTo('task_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;

            $task =  Task::with("assigned_by","assignees","labels")
            ->where([
                "id" => $id,
                "business_id" => $business_id
            ])
            ->select('tasks.*'
             )
                ->first();
            if (!$task) {

                return response()->json([
                    "message" => "no task listing found"
                ], 404);
            }

            return response()->json($task, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }




    public function deleteTasksByIds(Request $request, $ids)
    {

        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            $this->isModuleEnabled("task_management");


            if (!$request->user()->hasPermissionTo('task_delete')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;
            $idsArray = explode(',', $ids);
            $existingIds = Task::where([
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

            Task::destroy($existingIds);


            return response()->json(["message" => "data deleted sussfully","deleted_ids" => $existingIds], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
}
