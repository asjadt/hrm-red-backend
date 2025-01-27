<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotificationStatusUpdateRequest;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\Notification;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    use ErrorUtil, BusinessUtil,UserActivityUtil;

    public function getNotifications(Request $request)
    {
        try {

            $this->storeActivity($request, "DUMMY activity","DUMMY description");

            $data["notifications"] = Notification::with("sender","business")->where([
                "receiver_id" => $request->user()->id
            ]
        )

        ->when(!empty($request->status), function ($query) use ($request) {
            return $query->where('notifications.status', $request->status);
        })
        ->when(!empty($request->start_date), function ($query) use ($request) {
            return $query->where('notifications.created_at', ">=", $request->start_date);
        })
        ->when(!empty($request->end_date), function ($query) use ($request) {
            return $query->where('notifications.created_at', "<=", ($request->end_date . ' 23:59:59'));
        })
        ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
            return $query->orderBy("notifications.id", $request->order_by);
        }, function ($query) {
            return $query->orderBy("notifications.id", "DESC");
        })
        ->when(!empty($request->per_page), function ($query) use ($request) {
            return $query->paginate($request->per_page);
        }, function ($query) {
            return $query->get();
        });









            // $total_data = count($notifications->items());
            // for ($i = 0; $i < $total_data; $i++) {

            //     $notifications->items()[$i]["title"] = $notifications->items()[$i]->template->title_template;
            //      $notifications->items()[$i]["description"] = $notifications->items()[$i]->template->template;
            //      $notifications->items()[$i]["link"] = ($notifications->items()[$i]->template->link);


            //     if (!empty($notifications->items()[$i]->type == "reminder")) {


            //         $notifications->items()[$i]["title"] =  str_replace(
            //             "[business_owner_name]",

            //             ($notifications->items()[$i]->business->owner->first_Name . " " . $notifications->items()[$i]->business->owner->last_Name),

            //             $notifications->items()[$i]["title"]



            //         );

            //         $notifications->items()[$i]["description"] =  str_replace(
            //             "[business_owner_name]",

            //             ($notifications->items()[$i]->business->owner->first_Name . " " . $notifications->items()[$i]->business->owner->last_Name),

            //             $notifications->items()[$i]["description"]
            //         );




            //         $notifications->items()[$i]["link"] =  str_replace(
            //             "[bid_id]",
            //             $notifications->items()[$i]->bid_id,
            //             $notifications->items()[$i]["link"]
            //         );



            //     }




            // }

            // $data = json_decode(json_encode($notifications),true);

            $data["total_unread_messages"] = Notification::where('receiver_id', $request->user()->id)->where([
                "status" => "unread"
            ])->count();
            return response()->json( $data , 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500,$request);
        }
    }

 
    public function getNotificationsByBusinessId($business_id,$perPage, Request $request)
    {
        try {
     $this->storeActivity($request, "DUMMY activity","DUMMY description");

             $business = $this->businessOwnerCheck($business_id,FALSE);

            $notificationsQuery = Notification::where([
                "receiver_id" => $request->user()->id,
                "business_id" => $business_id
            ]);



            $notifications = $notificationsQuery->orderByDesc("id")->paginate($perPage);


            $total_data = count($notifications->items());
            for ($i = 0; $i < $total_data; $i++) {

                 $notifications->items()[$i]["template_string"] = json_decode($notifications->items()[$i]->template->template);

                 error_log($notifications->items()[$i]["template_string"]);


                if (!empty($notifications->items()[$i]->customer_id)) {
                    $notifications->items()[$i]["template_string"] =  str_replace(
                        "[customer_name]",

                        ($notifications->items()[$i]->customer->first_Name . " " . $notifications->items()[$i]->customer->last_Name),

                        $notifications->items()[$i]["template_string"]
                    );
                }

                if (!empty($notifications->items()[$i]->business_id)) {
                    $notifications->items()[$i]["template_string"] =  str_replace(
                        "[business_owner_name]",

                        ($notifications->items()[$i]->business->owner->first_Name . " " . $notifications->items()[$i]->business->owner->last_Name),

                        $notifications->items()[$i]["template_string"]
                    );

                    $notifications->items()[$i]["template_string"] =  str_replace(
                        "[business_name]",

                        ($notifications->items()[$i]->business->name),

                        $notifications->items()[$i]["template_string"]
                    );
                }

                if(in_array($notifications->items()[$i]->template->type,["booking_created_by_client","booking_accepted_by_client"]) ) {

                    $notifications->items()[$i]["template_string"] =  str_replace(
                        "[Date]",
                        ($notifications->items()[$i]->booking->job_start_date),

                        $notifications->items()[$i]["template_string"]
                    );
                    $notifications->items()[$i]["template_string"] =  str_replace(
                        "[Time]",
                        ($notifications->items()[$i]->booking->job_start_time),

                        $notifications->items()[$i]["template_string"]
                    );


                }



                $notifications->items()[$i]["link"] = json_decode($notifications->items()[$i]->template->link);



                $notifications->items()[$i]["link"] =  str_replace(
                    "[customer_id]",
                    $notifications->items()[$i]->customer_id,
                    $notifications->items()[$i]["link"]
                );




                $notifications->items()[$i]["link"] =  str_replace(
                    "[business_id]",
                    $notifications->items()[$i]->business_id,
                    $notifications->items()[$i]["link"]
                );

                $notifications->items()[$i]["link"] =  str_replace(
                    "[bid_id]",
                    $notifications->items()[$i]->bid_id,
                    $notifications->items()[$i]["link"]
                );
            }

            $data = json_decode(json_encode($notifications),true);

            $data["total_unread_messages"] = Notification::where('receiver_id', $request->user()->id)->where([
                "status" => "unread"
            ])->count();
            return response()->json($data, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500,$request);
        }
    }






    public function updateNotificationStatus(NotificationStatusUpdateRequest $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            return    DB::transaction(function () use (&$request) {

                $updatableData = $request->validated();


     Notification::whereIn('id', $updatableData["notification_ids"])
    ->where('receiver_id', $request->user()->id)
    ->update([
        "status" => "read"
    ]);



                return response(["ok" => true], 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500,$request);
        }
    }



    public function deleteNotificationById($id,Request $request) {

        try{
            $this->storeActivity($request, "DUMMY activity","DUMMY description");

            $notification = Notification::where([
                "id" => $id,
                'receiver_id' => $request->user()->id
            ])->first();

            if(!$notification) {

                return response(["message" => "Notification not found"], 404);
            }

            $notification->delete();
            return response(["message" => "Notification deleted"], 200);



        } catch(Exception $e){

        return $this->sendError($e,500,$request);


        }

    }
}
