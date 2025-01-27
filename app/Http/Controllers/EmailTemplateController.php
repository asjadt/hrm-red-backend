<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmailTemplateCreateRequest;
use App\Http\Requests\EmailTemplateUpdateRequest;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\EmailTemplate;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmailTemplateController extends Controller
{
    use ErrorUtil,UserActivityUtil;



    public function createEmailTemplate(EmailTemplateCreateRequest $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            return    DB::transaction(function () use (&$request) {
                if (!$request->user()->hasPermissionTo('template_create')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }

                $insertableData = $request->validated();
                $insertableData["wrapper_id"]  = !empty($insertableData["wrapper_id"])?$insertableData["wrapper_id"]:1;
                $template =  EmailTemplate::create($insertableData);



//  if the template is active then other templates of this type will deactive
                if ($template->is_active) {
                    EmailTemplate::where("id", "!=", $template->id)
                        ->where([
                            "type" => $template->type
                        ])
                        ->update([
                            "is_active" => false
                        ]);
                }


                return response($template, 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500,$request);
        }
    }



    public function updateEmailTemplate(EmailTemplateUpdateRequest $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            return    DB::transaction(function () use (&$request) {
                if (!$request->user()->hasPermissionTo('template_update')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }
                $updatableData = $request->validated();
                $updatableData["wrapper_id"]  = !empty($updatableData["wrapper_id"])?$updatableData["wrapper_id"]:1;
                $template  =  tap(EmailTemplate::where(["id" => $updatableData["id"]]))->update(
                    collect($updatableData)->only([
                        "name",
                        "template",
                        "wrapper_id"
                    ])->toArray()
                )


                    ->first();
                    if(!$template) {

                        return response()->json([
                            "message" => "no template found"
                            ],404);

                }

                //    if the template is active then other templates of this type will deactive
                if ($template->is_active) {
                    EmailTemplate::where("id", "!=", $template->id)
                        ->where([
                            "type" => $template->type
                        ])
                        ->update([
                            "is_active" => false
                        ]);
                }
                return response($template, 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500,$request);
        }
    }


    public function getEmailTemplates($perPage, Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('template_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }



            $templateQuery = new EmailTemplate();

            if (!empty($request->search_key)) {
                $templateQuery = $templateQuery->where(function ($query) use ($request) {
                    $term = $request->search_key;
                    $query->where("type", "like", "%" . $term . "%");
                });
            }

            if (!empty($request->start_date)) {
                $templateQuery = $templateQuery->where('created_at', ">=", $request->start_date);
            }
            if (!empty($request->end_date)) {
                $templateQuery = $templateQuery->where('created_at', "<=", ($request->end_date . ' 23:59:59'));
            }

            $templates = $templateQuery->orderByDesc("id")->paginate($perPage);
            return response()->json($templates, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500,$request);
        }
    }



    public function getEmailTemplateById($id, Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('template_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }


            $template = EmailTemplate::where([
                "id" => $id
            ])
            ->first();
            if(!$template){

                return response()->json([
                     "message" => "no email template found"
                ], 404);
            }
            return response()->json($template, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500,$request);
        }
    }


    public function getEmailTemplateTypes( Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('template_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

$types = [
    "email_verification_mail",
    "forget_password_mail",
    "welcome_message",

    "booking_updated_by_business_owner",
    "booking_status_changed_by_business_owner",
    "booking_confirmed_by_business_owner",
    "booking_deleted_by_business_owner",
     "booking_rejected_by_business_owner",

    "booking_created_by_client",
    "booking_updated_by_client",
    "booking_deleted_by_client",
    "booking_accepted_by_client",
    "booking_rejected_by_client",


    "job_created_by_business_owner",
    "job_updated_by_business_owner",
    "job_status_changed_by_business_owner",
    "job_deleted_by_business_owner",


];


            return response()->json($types, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500,$request);
        }
    }

    public function deleteEmailTemplateById($id,Request $request) {

        try{
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if(!$request->user()->hasPermissionTo('template_delete')){
                return response()->json([
                   "message" => "You can not perform this action"
                ],401);
           }
           EmailTemplate::where([
            "id" => $id
           ])
           ->delete();

            return response()->json(["ok" => true], 200);
        } catch(Exception $e){

        return $this->sendError($e,500,$request);
        }

    }
}
