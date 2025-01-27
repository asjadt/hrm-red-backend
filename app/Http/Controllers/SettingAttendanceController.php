<?php

namespace App\Http\Controllers;

use App\Http\Requests\SettingAttendanceCreateRequest;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\Role;
use App\Models\SettingAttendance;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingAttendanceController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil;



    public function createSettingAttendance(SettingAttendanceCreateRequest $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            return DB::transaction(function () use ($request) {
                if (!$request->user()->hasPermissionTo('setting_attendance_create')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }

                $request_data = $request->validated();

                $request_data["is_active"] = 1;





                if (empty($request->user()->business_id)) {
                    $request_data["business_id"] = NULL;
                    $request_data["is_default"] = 0;
                    if ($request->user()->hasRole('superadmin')) {
                        $request_data["is_default"] = 1;
                    }
                    $check_data =     [
                        "business_id" => $request_data["business_id"],
                        "is_default" => $request_data["is_default"]
                    ];
                    if (!$request->user()->hasRole('superadmin')) {
                        $check_data["created_by"] =    auth()->user()->id;
                    }
                } else {
                    $request_data["business_id"] = auth()->user()->business_id;
                    $request_data["is_default"] = 0;
                    $check_data =     [
                        "business_id" => $request_data["business_id"],
                        "is_default" => $request_data["is_default"]
                    ];
                }

                $setting_attendance =     SettingAttendance::updateOrCreate($check_data, $request_data);





                 $setting_attendance->special_users()->sync($request_data['special_users']);
                 $setting_attendance->special_roles()->sync($request_data['special_roles']);

                 $permission = 'attendance_approve';

                 foreach($request_data['special_users'] as $special_user_id){
                    $special_user = User::where([
                        "id" => $special_user_id
                    ])->first();
                    if(!$special_user) {
                          throw new Exception("no special user found");
                    }
                    if (!$special_user->hasPermissionTo($permission)) {
                        $special_user->givePermissionTo($permission);
                    }
                 }

                 foreach($request_data['special_roles'] as $special_role_id){
                    $special_role = Role::where([
                        "id" => $special_role_id
                    ])->first();
                    if(!$special_role) {
                          throw new Exception("no special role found");
                    }

                 if (!$special_role->hasPermissionTo($permission)) {
    $special_role->givePermissionTo($permission);
}
                 }







                return response($setting_attendance, 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }


 

     public function getSettingAttendance(Request $request)
     {
         try {
             $this->storeActivity($request, "DUMMY activity","DUMMY description");
             if (!$request->user()->hasPermissionTo('setting_attendance_create')) {
                 return response()->json([
                     "message" => "You can not perform this action"
                 ], 401);
             }


             $setting_attendance = SettingAttendance::with("special_users","special_roles")
             ->when(empty($request->user()->business_id), function ($query) use ($request) {
                if (auth()->user()->hasRole('superadmin')) {
                    return $query->where('setting_attendances.business_id', NULL)
                        ->where('setting_attendances.is_default', 1)
                        ->when(isset($request->is_active), function ($query) use ($request) {
                            return $query->where('setting_attendances.is_active', intval($request->is_active));
                        });
                } else {
                    return   $query->where('setting_attendances.business_id', NULL)
                    ->where('setting_attendances.is_default', 0)
                    ->where('setting_attendances.created_by', auth()->user()->id);
                }
            })
                ->when(!empty($request->user()->business_id), function ($query) use ($request) {
                 return   $query->where('setting_attendances.business_id', auth()->user()->business_id)
                    ->where('setting_attendances.is_default', 0);


                })


                 ->when(!empty($request->search_key), function ($query) use ($request) {
                     return $query->where(function ($query) use ($request) {
                         $term = $request->search_key;
                        //  $query->where("setting_attendances.name", "like", "%" . $term . "%");
                     });
                 })
                 //    ->when(!empty($request->product_category_id), function ($query) use ($request) {
                 //        return $query->where('product_category_id', $request->product_category_id);
                 //    })
                 ->when(!empty($request->start_date), function ($query) use ($request) {
                     return $query->where('setting_attendances.created_at', ">=", $request->start_date);
                 })
                 ->when(!empty($request->end_date), function ($query) use ($request) {
                     return $query->where('setting_attendances.created_at', "<=", ($request->end_date . ' 23:59:59'));
                 })
                 ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                     return $query->orderBy("setting_attendances.id", $request->order_by);
                 }, function ($query) {
                     return $query->orderBy("setting_attendances.id", "DESC");
                 })
                 ->when(!empty($request->per_page), function ($query) use ($request) {
                     return $query->paginate($request->per_page);
                 }, function ($query) {
                     return $query->get();
                 });;



             return response()->json($setting_attendance, 200);
         } catch (Exception $e) {

             return $this->sendError($e, 500, $request);
         }
     }
}
