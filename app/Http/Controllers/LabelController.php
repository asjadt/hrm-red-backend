<?php

namespace App\Http\Controllers;

use App\Http\Requests\LabelAssignRequest;
use App\Http\Requests\LabelCreateRequest;
use App\Http\Requests\LabelUpdateRequest;
use App\Http\Utils\BasicUtil;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\ModuleUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\Label;
use App\Models\TaskLabel;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LabelController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil, ModuleUtil,BasicUtil;

    public function createLabel(LabelCreateRequest $request)
    {
        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");

            $this->isModuleEnabled("project_and_label_management");





                if (!$request->user()->hasPermissionTo('label_create')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }

                $request_data = $request->validated();


                $request_data["business_id"] = $request->user()->business_id;
                $request_data["is_active"] = true;
                $request_data["created_by"] = $request->user()->id;





                $request_data["unique_identifier"] = $this->generateUniqueId("Project",$request_data["project_id"],"Label");


                $label =  Label::create($request_data);


                DB::commit();
                return response($label, 201);

        } catch (Exception $e) {

            DB::rollBack();
            return $this->sendError($e, 500, $request);
        }
    }



    public function updateLabel(LabelUpdateRequest $request)
    {

        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            $this->isModuleEnabled("project_and_label_management");

                if (!$request->user()->hasPermissionTo('label_update')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }
                $business_id =  $request->user()->business_id;
                $request_data = $request->validated();




                $label_query_params = [
                    "id" => $request_data["id"],
                    "business_id" => $business_id
                ];
                // $label_prev = Label::where($label_query_params)
                //     ->first();
                // if (!$label_prev) {
                //     return response()->json([
                //         "message" => "no label listing found"
                //     ], 404);
                // }

                $label  =  tap(Label::where($label_query_params))->update(
                    collect($request_data)->only([
                        'name',
                        'color',

                        // "is_active",
                        // "business_id",
                        // "created_by"

                    ])->toArray()
                )
                    // ->with("somthing")

                    ->first();
                if (!$label) {
                    return response()->json([
                        "message" => "something went wrong."
                    ], 500);
                }


                DB::commit();
                return response($label, 201);

        } catch (Exception $e) {
           DB::rollBack();
            return $this->sendError($e, 500, $request);
        }
    }



     public function assignLabel(LabelAssignRequest $request)
     {

         DB::beginTransaction();
         try {
             $this->storeActivity($request, "DUMMY activity","DUMMY description");
             $this->isModuleEnabled("project_and_label_management");

                 if (!$request->user()->hasPermissionTo('label_update')) {
                     return response()->json([
                         "message" => "You can not perform this action"
                     ], 401);
                 }

                 $request_data = $request->validated();




                 foreach($request_data["label_ids"] as $label_id){


                    TaskLabel::create([
                        "label_id" => $label_id,
                        "task_id" => $request_data["task_id"]
                    ]);
                 }






                 DB::commit();
                 return response(["ok" => true], 201);

         } catch (Exception $e) {
            DB::rollBack();
             return $this->sendError($e, 500, $request);
         }
     }



     public function dischargeLabel(LabelAssignRequest $request)
     {

         DB::beginTransaction();
         try {
             $this->storeActivity($request, "DUMMY activity","DUMMY description");
             $this->isModuleEnabled("project_and_label_management");

                 if (!$request->user()->hasPermissionTo('label_update')) {
                     return response()->json([
                         "message" => "You can not perform this action"
                     ], 401);
                 }

                 $request_data = $request->validated();


                 TaskLabel::where([
                    "task_id" => $request_data["task_id"]
                ])
                ->whereIn("label_id",$request_data["label_ids"])
                ->delete();








                 DB::commit();
                 return response(["ok" => true], 201);

         } catch (Exception $e) {
            DB::rollBack();
             return $this->sendError($e, 500, $request);
         }
     }



    public function getLabels(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            $this->isModuleEnabled("project_and_label_management");


            if (!$request->user()->hasPermissionTo('label_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }


            $labels = Label::where(
                [
                    "business_id" => auth()->user()->id
                ]
            )

                ->when(!empty($request->search_key), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        $term = $request->search_key;
                        $query->where("name", "like", "%" . $term . "%")
                            ->orWhere("color", "like", "%" . $term . "%");
                    });
                })




                ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                    return $query->orderBy("labels.id", $request->order_by);
                }, function ($query) {
                    return $query->orderBy("labels.id", "DESC");
                })
                ->select('labels.*',

                 )
                ->when(!empty($request->per_page), function ($query) use ($request) {
                    return $query->paginate($request->per_page);
                }, function ($query) {
                    return $query->get();
                });



            return response()->json($labels, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }



    public function getLabelById($id, Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
               $this->isModuleEnabled("project_and_label_management");


            if (!$request->user()->hasPermissionTo('label_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;
            $label =  Label::where([
                "id" => $id,
                "business_id" => $business_id
            ])
            ->select('labels.*'
             )
                ->first();
            if (!$label) {

                return response()->json([
                    "message" => "no label listing found"
                ], 404);
            }

            return response()->json($label, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }



     
    public function deleteLabelsByIds(Request $request, $ids)
    {

        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            $this->isModuleEnabled("project_and_label_management");


            if (!$request->user()->hasPermissionTo('label_delete')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;
            $idsArray = explode(',', $ids);
            $existingIds = Label::where([
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

            Label::destroy($existingIds);


            return response()->json(["message" => "data deleted sussfully","deleted_ids" => $existingIds], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
}
