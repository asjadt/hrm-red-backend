<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImageUploadRequest;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\Business;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class BusinessBackgroundImageController extends Controller
{
    use ErrorUtil,UserActivityUtil;



     public function updateBusinessBackgroundImage(ImageUploadRequest $request)
     {
         try{
             $this->storeActivity($request, "DUMMY activity","DUMMY description");
             if (!$request->user()->hasPermissionTo('global_business_background_image_create')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

             $insertableData = $request->validated();

             $location =  config("setup-config.business_background_image_location");

             $new_file_name = time() . "_" ."business_background_image.jpeg";

             $insertableData["image"]->move(public_path($location), $new_file_name);


// Update the desired key in the configuration
config(["setup-config.business_background_image_location_full" => $location . "/" . $new_file_name]);

// Save the updated configuration to the file
File::put(config_path('setup-config.php'), '<?php return ' . var_export(config('setup-config'), true) . ';');









             return response()->json(["image" => $new_file_name,"location" => $location,"full_location"=>("/".$location."/".$new_file_name)], 200);


         } catch(Exception $e){
             error_log($e->getMessage());
         return $this->sendError($e,500,$request);
         }
     }




     public function updateBusinessBackgroundImageByUser(ImageUploadRequest $request)
     {
         try{
             $this->storeActivity($request, "DUMMY activity","DUMMY description");



             $insertableData = $request->validated();

             $location =  config("setup-config.business_background_image_location");


             $new_file_name = time() . '_' . str_replace(' ', '_', $insertableData["image"]->getClientOriginalName());
             $insertableData["image"]->move(public_path($location), $new_file_name);


             User::where([
                "id" => $request->user()->id
             ])
             ->update([
                "background_image" => ("/".$location."/".$new_file_name)
             ]);










             return response()->json(["image" => $new_file_name,"location" => $location,"full_location"=>("/".$location."/".$new_file_name)], 200);


         } catch(Exception $e){
             error_log($e->getMessage());
         return $this->sendError($e,500,$request);
         }
     }



    
    public function getBusinessBackgroundImage(Request $request) {

        try{
            $this->storeActivity($request, "DUMMY activity","DUMMY description");

            if (!$request->user()->hasPermissionTo('global_business_background_image_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }


            $image = ("/".  config("setup-config.business_background_image_location_full"));




            return response()->json($image, 200);
        } catch(Exception $e){

        return $this->sendError($e,500,$request);
        }

    }


}
