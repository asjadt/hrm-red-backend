<?php

namespace App\Http\Controllers;

use App\Http\Requests\SettingLeaveCreateRequest;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\SettingLeave;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingLeaveController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil;


    public function createSettingLeave(SettingLeaveCreateRequest $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            return DB::transaction(function () use ($request) {
                if (!$request->user()->hasPermissionTo('setting_leave_create')) {
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

                $setting_leave =     SettingLeave::updateOrCreate($check_data, $request_data);

                $setting_leave->special_users()->sync($request_data['special_users']);
                $setting_leave->special_roles()->sync($request_data['special_roles']);
                $setting_leave->paid_leave_employment_statuses()->sync($request_data['paid_leave_employment_statuses']);
                $setting_leave->unpaid_leave_employment_statuses()->sync($request_data['unpaid_leave_employment_statuses']);


                return response($setting_leave, 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }


 
    public function getSettingLeave(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('setting_leave_create')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }




            $setting_leave = SettingLeave::with("special_users","special_roles","paid_leave_employment_statuses","unpaid_leave_employment_statuses")

            ->when(empty($request->user()->business_id), function ($query) use ($request) {
                if (auth()->user()->hasRole('superadmin')) {
                    return $query->where('setting_leaves.business_id', NULL)
                        ->where('setting_leaves.is_default', 1)
                        ->when(isset($request->is_active), function ($query) use ($request) {
                            return $query->where('setting_leaves.is_active', intval($request->is_active));
                        });
                } else {
                    return   $query->where('setting_leaves.business_id', NULL)
                    ->where('setting_leaves.is_default', 0)
                    ->where('setting_leaves.created_by', auth()->user()->id);
                }
            })
                ->when(!empty($request->user()->business_id), function ($query) use ($request) {
                 return   $query->where('setting_leaves.business_id', auth()->user()->business_id)
                    ->where('setting_leaves.is_default', 0);


                })
                ->when(!empty($request->search_key), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        $term = $request->search_key;
                        $query->where("setting_leaves.name", "like", "%" . $term . "%");
                    });
                })
                //    ->when(!empty($request->product_category_id), function ($query) use ($request) {
                //        return $query->where('product_category_id', $request->product_category_id);
                //    })
                ->when(!empty($request->start_date), function ($query) use ($request) {
                    return $query->where('setting_leaves.created_at', ">=", $request->start_date);
                })
                ->when(!empty($request->end_date), function ($query) use ($request) {
                    return $query->where('setting_leaves.created_at', "<=", ($request->end_date . ' 23:59:59'));
                })
                ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                    return $query->orderBy("setting_leaves.id", $request->order_by);
                }, function ($query) {
                    return $query->orderBy("setting_leaves.id", "DESC");
                })
                ->when(!empty($request->per_page), function ($query) use ($request) {
                    return $query->paginate($request->per_page);
                }, function ($query) {
                    return $query->get();
                });;



            return response()->json($setting_leave, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }




















}
