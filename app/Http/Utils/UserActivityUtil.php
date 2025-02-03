<?php

namespace App\Http\Utils;

use App\Models\ActivityLog;
use App\Models\ErrorLog;
use Exception;
use Illuminate\Http\Request;

trait UserActivityUtil
{
    // this function do all the task and returns transaction id or -1
    public function storeActivity(Request $request,$activity="",$description="")
    {

 $user = auth()->user();



$activityLog = [

    "api_url" => $request->fullUrl(),
    "fields" => json_encode(request()->all()),
    "token" => request()->bearerToken()?request()->bearerToken():"",
    "user" => auth()->user() ? json_encode(auth()->user()) : "",
    "user_id" => auth()->user() ?auth()->user()->id:"",
    "ip_address" => request()->header('X-Forwarded-For'),
    "request_method" => $request->method(),
    "activity"=> $activity,
    "description"=> $description,
    "device" => $request->header('User-Agent')
];
       
return true;

    }
}
