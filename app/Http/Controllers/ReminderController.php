<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReminderCreateRequest;
use App\Http\Requests\ReminderUpdateRequest;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\Reminder;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReminderController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil;



    public function createReminder(ReminderCreateRequest $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            return DB::transaction(function () use ($request) {
                if (!$request->user()->hasPermissionTo('reminder_create')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }

                $request_data = $request->validated();

                $reminder_options = config("setup-config.reminder_options");

                $reminder_option = collect($reminder_options)->first(function ($item) use ($request_data) {
                    return $item['entity_name'] === $request_data["entity_name"];
                });

                if(empty($reminder_option)) {
                    $error =  [
                        "message" => "The given data was invalid.",
                        "errors" => ["entity_name"=>["Invalid Entity Name."]]
                 ];
                    throw new Exception(json_encode($error),422);
                }


                $request_data["model_name"] = $reminder_option["model_name"];
                $request_data["issue_date_column"] = $reminder_option["issue_date_column"];
                $request_data["expiry_date_column"] = $reminder_option["expiry_date_column"];
                $request_data["user_eligible_field"] = $reminder_option["user_eligible_field"];
                $request_data["user_relationship"] = $reminder_option["user_relationship"];



                $request_data["business_id"] = $request->user()->business_id;
                $request_data["is_active"] = true;
                $request_data["created_by"] = $request->user()->id;

                $reminder =  Reminder::create($request_data);



                return response($reminder, 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }



    public function updateReminder(ReminderUpdateRequest $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            return DB::transaction(function () use ($request) {
                if (!$request->user()->hasPermissionTo('reminder_update')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }
                $business_id =  $request->user()->business_id;
                $request_data = $request->validated();

                $reminder_options = config("setup-config.reminder_options");

                $reminder_option = collect($reminder_options)->first(function ($item) use ($request_data) {
                    return $item['entity_name'] === $request_data["entity_name"];
                });

                if(empty($reminder_option)) {
                    $error =  [
                        "message" => "The given data was invalid.",
                        "errors" => ["entity_name"=>["Invalid Entity Name."]]
                 ];
                    throw new Exception(json_encode($error),422);
                }


                $request_data["model_name"] = $reminder_option["model_name"];
                $request_data["issue_date_column"] = $reminder_option["issue_date_column"];
                $request_data["expiry_date_column"] = $reminder_option["expiry_date_column"];
                $request_data["user_eligible_field"] = $reminder_option["user_eligible_field"];
                $request_data["user_relationship"] = $reminder_option["user_relationship"];



                $reminder_query_params = [
                    "id" => $request_data["id"],
                    "business_id" => $business_id
                ];
                // $reminder_prev = Reminder::where($reminder_query_params)
                //     ->first();
                // if (!$reminder_prev) {
                //     return response()->json([
                //         "message" => "no reminder found"
                //     ], 404);
                // }

                $reminder  =  tap(Reminder::where($reminder_query_params))->update(
                    collect($request_data)->only([
                        'title',
                        'model_name',
                        "issue_date_column",
                        'expiry_date_column',
                        "user_eligible_field",
                        "user_relationship",
                        'duration',
                        'duration_unit',
                        'send_time',
                        'frequency_after_first_reminder',
                        'reminder_limit',
                        'keep_sending_until_update',
                        'entity_name',
                    ])->toArray()
                )
                    // ->with("somthing")

                    ->first();

                if (!$reminder) {
                    return response()->json([
                        "message" => "something went wrong."
                    ], 500);
                }

                return response($reminder, 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }



     public function getReminderEntityNames(Request $request)
     {
         try {
             $this->storeActivity($request, "DUMMY activity","DUMMY description");
             if (!$request->user()->hasPermissionTo('reminder_create')) {
                 return response()->json([
                     "message" => "You can not perform this action"
                 ], 401);
             }


             $reminder_options = config("setup-config.reminder_options");

             $reminder_entity_names = collect($reminder_options)->pluck("entity_name")->toArray();


             return response()->json($reminder_entity_names, 200);
         } catch (Exception $e) {

             return $this->sendError($e, 500, $request);
         }
     }


    public function getReminders(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('reminder_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;
            $reminders = Reminder::where(
                [
                    "reminders.business_id" => $business_id
                ]
            )
                ->when(!empty($request->search_key), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        $term = $request->search_key;
                        // $query->where("reminders.name", "like", "%" . $term . "%")
                        //     ->orWhere("reminders.description", "like", "%" . $term . "%");
                    });
                })
                //    ->when(!empty($request->product_category_id), function ($query) use ($request) {
                //        return $query->where('product_category_id', $request->product_category_id);
                //    })
                ->when(!empty($request->start_date), function ($query) use ($request) {
                    return $query->where('reminders.created_at', ">=", $request->start_date);
                })
                ->when(!empty($request->end_date), function ($query) use ($request) {
                    return $query->where('reminders.created_at', "<=", ($request->end_date . ' 23:59:59'));
                })
                ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                    return $query->orderBy("reminders.id", $request->order_by);
                }, function ($query) {
                    return $query->orderBy("reminders.id", "DESC");
                })
                ->when(!empty($request->per_page), function ($query) use ($request) {
                    return $query->paginate($request->per_page);
                }, function ($query) {
                    return $query->get();
                });;



            return response()->json($reminders, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
  

    public function getReminderById($id, Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('reminder_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;
            $reminder =  Reminder::where([
                "id" => $id,
                "business_id" => $business_id
            ])
                ->first();
            if (!$reminder) {

                return response()->json([
                    "message" => "no data found"
                ], 404);
            }

            return response()->json($reminder, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }



    public function deleteRemindersByIds(Request $request, $ids)
    {

        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('reminder_delete')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;
            $idsArray = explode(',', $ids);
            $existingIds = Reminder::where([
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
            Reminder::destroy($existingIds);


            return response()->json(["message" => "data deleted sussfully","deleted_ids" => $existingIds], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
}
