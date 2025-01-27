<?php

namespace App\Http\Controllers;

use App\Http\Requests\BusinessTierCreateRequest;
use App\Http\Requests\BusinessTierUpdateRequest;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\BusinessTier;
use App\Models\BusinessTierModule;
use App\Models\Module;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BusinessTierController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil;


    public function createBusinessTier(BusinessTierCreateRequest $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            return DB::transaction(function () use ($request) {
                if (!$request->user()->hasPermissionTo('business_tier_create')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }

                $request_data = $request->validated();




                $request_data["is_active"] = 1;
                $request_data["created_by"] = $request->user()->id;


                $business_tier =  BusinessTier::create($request_data);

                $default_modules = Module::where([

                     "is_enabled" => 1,
                ])
                ->get();

                if ($default_modules->isNotEmpty()) {
                    // Transform the collection to an array for createMany
                    $default_modules->map(function ($module) use ($business_tier, $request) {

                       BusinessTierModule::create([
                        "name" => $module->name,
                        "is_enabled" => 1,
                        "business_tier_id" => $business_tier->id,
                        "module_id" => $module->id,
                        'created_by' => $request->user()->id,
                    ]);
                    return 0;

                    })->toArray();


                }

                return response($business_tier, 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }


    public function updateBusinessTier(BusinessTierUpdateRequest $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            return DB::transaction(function () use ($request) {
                if (!$request->user()->hasPermissionTo('business_tier_update')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }
                // $business_id =  $request->user()->business_id;
                $request_data = $request->validated();





                $business_tier  =  tap(BusinessTier::where([
                    "id" => $request_data["id"],

                ]))->update(
                    collect($request_data)->only([
                        'name',
                        // "is_active",
                        // 'created_by'
                    ])->toArray()
                )
                    // ->with("somthing")

                    ->first();

                if (!$business_tier) {
                    return response()->json([
                        "message" => "something went wrong."
                    ], 500);
                }

                return response($business_tier, 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }



    public function getBusinessTiers(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('business_tier_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }


            $business_tiers = BusinessTier::with("modules")
            ->when(!empty($request->search_key), function ($query) use ($request) {
                return $query->where(function ($query) use ($request) {
                    $term = $request->search_key;
                    $query->where("business_tiers.name", "like", "%" . $term . "%");
                });
            })

            //     when($request->user()->hasRole('superadmin'), function ($query) use ($request) {
            //     return $query->where('business_tiers.business_id', NULL)
            //                  ->where('business_tiers.is_default', 1);
            // })
            // ->when(!$request->user()->hasRole('superadmin'), function ($query) use ($request) {
            //     return $query->where('business_tiers.business_id', $request->user()->business_id);
            // })


                //    ->when(!empty($request->product_category_id), function ($query) use ($request) {
                //        return $query->where('product_category_id', $request->product_category_id);
                //    })
                ->when(!empty($request->start_date), function ($query) use ($request) {
                    return $query->where('business_tiers.created_at', ">=", $request->start_date);
                })
                ->when(!empty($request->end_date), function ($query) use ($request) {
                    return $query->where('business_tiers.created_at', "<=", ($request->end_date . ' 23:59:59'));
                })
                ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                    return $query->orderBy("business_tiers.id", $request->order_by);
                }, function ($query) {
                    return $query->orderBy("business_tiers.id", "DESC");
                })
                ->when(!empty($request->per_page), function ($query) use ($request) {
                    return $query->paginate($request->per_page);
                }, function ($query) {
                    return $query->get();
                });;



            return response()->json($business_tiers, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }



    public function getBusinessTierById($id, Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('business_tier_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $business_tier =  BusinessTier::where([
                "id" => $id,
            ])
            // ->when($request->user()->hasRole('superadmin'), function ($query) use ($request) {
            //     return $query->where('business_tiers.business_id', NULL)
            //                  ->where('business_tiers.is_default', 1);
            // })
            // ->when(!$request->user()->hasRole('superadmin'), function ($query) use ($request) {
            //     return $query->where('business_tiers.business_id', $request->user()->business_id);
            // })
                ->first();
            if (!$business_tier) {

                return response()->json([
                    "message" => "no data found"
                ], 404);
            }

            return response()->json($business_tier, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }



    public function deleteBusinessTiersByIds(Request $request, $ids)
    {

        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('business_tier_delete')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $idsArray = explode(',', $ids);
            $existingIds = BusinessTier::whereIn('id', $idsArray)
            // ->when($request->user()->hasRole('superadmin'), function ($query) use ($request) {
            //     return $query->where('business_tiers.business_id', NULL)
            //                  ->where('business_tiers.is_default', 1);
            // })
            // ->when(!$request->user()->hasRole('superadmin'), function ($query) use ($request) {
            //     return $query->where('business_tiers.business_id', $request->user()->business_id)
            //     ->where('business_tiers.is_default', 0);
            // })
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



            BusinessTier::destroy($existingIds);


            return response()->json(["message" => "data deleted sussfully","deleted_ids" => $existingIds], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
}
