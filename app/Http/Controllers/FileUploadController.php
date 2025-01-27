<?php

namespace App\Http\Controllers;

use App\Http\Requests\SingleFileUploadRequest;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use Exception;
use Illuminate\Http\Request;

class FileUploadController extends Controller
{
    use UserActivityUtil,ErrorUtil;
 
     public function createFileSingleV3(SingleFileUploadRequest $request)
     {
         try {
             $this->storeActivity($request, "DUMMY activity", "DUMMY description");


             $request_data = $request->validated();

             $location =  config("setup-config.temporary_files_location");

             $new_file_name = time() . '_' . str_replace(' ', '_', $request_data["file"]->getClientOriginalName());

             $request_data["file"]->move(public_path($location), $new_file_name);




             return response()->json([

            "file" => $new_file_name,
            "location" => $location,
             "full_location" => ("/" . $location . "/" . $new_file_name)


            ], 200);
         } catch (Exception $e) {
             error_log($e->getMessage());
             return $this->sendError($e, 500, $request);
         }
     }
}
