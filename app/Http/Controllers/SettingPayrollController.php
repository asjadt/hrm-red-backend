<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImageUploadRequest;
use App\Http\Requests\SettingPayrunCreateRequest;
use App\Http\Requests\SettingPayslipCreateRequest;
use App\Http\Utils\BasicUtil;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\SettingPayrun;
use App\Models\SettingPayslip;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingPayrollController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil, BasicUtil;


    public function createSettingPayrun(SettingPayrunCreateRequest $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            return DB::transaction(function () use ($request) {
                if (!$request->user()->hasPermissionTo('setting_payroll_create')) {
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

                $setting_payrun =     SettingPayrun::updateOrCreate($check_data, $request_data);

                $setting_payrun->restricted_users()->sync($request_data['restricted_users']);
                $setting_payrun->restricted_departments()->sync($request_data['restricted_departments']);




                return response($setting_payrun, 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }



    public function getSettingPayrun(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('setting_payroll_create')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }


            $setting_payrun = SettingPayrun::with("restricted_users", "restricted_departments")
                ->when(empty($request->user()->business_id), function ($query) use ($request) {
                    if (auth()->user()->hasRole('superadmin')) {
                        return $query->where('setting_payruns.business_id', NULL)
                            ->where('setting_payruns.is_default', 1)
                            ->when(isset($request->is_active), function ($query) use ($request) {
                                return $query->where('setting_payruns.is_active', intval($request->is_active));
                            });
                    } else {
                        return   $query->where('setting_payruns.business_id', NULL)
                            ->where('setting_payruns.is_default', 0)
                            ->where('setting_payruns.created_by', auth()->user()->id);
                    }
                })
                ->when(!empty($request->user()->business_id), function ($query) use ($request) {
                    return   $query->where('setting_payruns.business_id', auth()->user()->business_id)
                        ->where('setting_payruns.is_default', 0);
                })

                ->when(!empty($request->search_key), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        $term = $request->search_key;
                        //  $query->where("setting_payruns.name", "like", "%" . $term . "%");
                    });
                })
                //    ->when(!empty($request->product_category_id), function ($query) use ($request) {
                //        return $query->where('product_category_id', $request->product_category_id);
                //    })
                ->when(!empty($request->start_date), function ($query) use ($request) {
                    return $query->where('setting_payruns.created_at', ">=", $request->start_date);
                })
                ->when(!empty($request->end_date), function ($query) use ($request) {
                    return $query->where('setting_payruns.created_at', "<=", ($request->end_date . ' 23:59:59'));
                })
                ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                    return $query->orderBy("setting_payruns.id", $request->order_by);
                }, function ($query) {
                    return $query->orderBy("setting_payruns.id", "DESC");
                })
                ->when(!empty($request->per_page), function ($query) use ($request) {
                    return $query->paginate($request->per_page);
                }, function ($query) {
                    return $query->get();
                });;



            return response()->json($setting_payrun, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }




    public function createSettingPayslip(SettingPayslipCreateRequest $request)
    {
        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

                if (!$request->user()->hasPermissionTo('setting_payroll_create')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }
                $request_data = $request->validated();
                $request_data["created_by"] = $request->user()->id;
                $request_data["is_active"] = 1;

                if(!empty($request_data["logo"])) {
                    $request_data["logo"]= $this->storeUploadedFiles([$request_data["logo"]],"","payslip_logo")[0];
                    $this->makeFilePermanent($request_data["logo"],"");
                  }


                if (empty($request->user()->business_id)) {

                    $request_data["business_id"] = NULL;
                    $request_data["is_default"] = 1;

                    $setting_payslip  =  SettingPayslip::updateOrCreate(
                        [
                            "business_id" => $request_data["business_id"],
                            "is_default" => $request_data["is_default"]
                        ],

                        $request_data



                    );
                } else {
                    $request_data["business_id"] = $request->user()->business_id;
                    $request_data["is_default"] = 0;
                    $setting_payslip =     SettingPayslip::updateOrCreate(
                        [
                            "business_id" => $request_data["business_id"],
                            "is_default" => $request_data["is_default"]
                        ],
                        $request_data
                    );
                }



                DB::commit();
                return response($setting_payslip, 201);

        } catch (Exception $e) {
            DB::rollBack();
            try {
                if(!empty($request_data["image"])) {

                    $this->moveUploadedFilesBack([$request_data["image"]],"","payslip_logo");
                       }
            } catch (Exception $innerException) {
                error_log("Failed to move assets files back: " . $innerException->getMessage());
            }
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }


 
    public function getSettingPayslip(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('setting_payroll_create')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }


            $setting_payslip = SettingPayslip::when(empty($request->user()->business_id), function ($query) use ($request) {
                return $query->where('setting_payslips.business_id', NULL)
                    ->where('setting_payslips.is_default', 1);
            })
                ->when(!empty($request->user()->business_id), function ($query) use ($request) {
                    return $query->where('setting_payslips.business_id', $request->user()->business_id)
                        ->where('setting_payslips.is_default', 0);
                })
                ->when(!empty($request->search_key), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        $term = $request->search_key;
                        //  $query->where("setting_payslips.name", "like", "%" . $term . "%");
                    });
                })
                //    ->when(!empty($request->product_category_id), function ($query) use ($request) {
                //        return $query->where('product_category_id', $request->product_category_id);
                //    })
                ->when(!empty($request->start_date), function ($query) use ($request) {
                    return $query->where('setting_payslips.created_at', ">=", $request->start_date);
                })
                ->when(!empty($request->end_date), function ($query) use ($request) {
                    return $query->where('setting_payslips.created_at', "<=", ($request->end_date . ' 23:59:59'));
                })
                ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                    return $query->orderBy("setting_payslips.id", $request->order_by);
                }, function ($query) {
                    return $query->orderBy("setting_payslips.id", "DESC");
                })
                ->when(!empty($request->per_page), function ($query) use ($request) {
                    return $query->paginate($request->per_page);
                }, function ($query) {
                    return $query->get();
                });;



            return response()->json($setting_payslip, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
}
