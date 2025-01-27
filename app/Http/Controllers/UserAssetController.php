<?php

namespace App\Http\Controllers;

use App\Exports\UserAssetsExport;
use App\Http\Requests\SingleFileUploadRequest;
use App\Http\Requests\UserAssetAddExistingRequest;
use App\Http\Requests\UserAssetCreateRequest;
use App\Http\Requests\UserAssetReturnRequest;
use App\Http\Requests\UserAssetUpdateRequest;
use App\Http\Utils\BasicUtil;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\Department;
use App\Models\UserAsset;
use App\Models\UserAssetHistory;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDF;
use Maatwebsite\Excel\Facades\Excel;

class UserAssetController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil, BasicUtil;







      public function createUserAsset(UserAssetCreateRequest $request)
      {
        DB::beginTransaction();
          try {
              $this->storeActivity($request, "DUMMY activity","DUMMY description");

                  if (!$request->user()->hasPermissionTo('employee_asset_create')) {
                      return response()->json([
                          "message" => "You can not perform this action"
                      ], 401);
                  }

                  $request_data = $request->validated();

                  if(!empty($request_data["image"])) {
                    $request_data["image"]= $this->storeUploadedFiles([$request_data["image"]],"","assets")[0];
                    $this->makeFilePermanent($request_data["image"],"");
                  }



                  $request_data["created_by"] = $request->user()->id;
                  $request_data["business_id"] = $request->user()->business_id;


                  if(empty($request_data["user_id"])) {
                    $request_data["status"] = "available";
                  } else {
                    $request_data["status"] = "assigned";
                  }


                  $user_asset =  UserAsset::create($request_data);



                  $user_asset_history  =  UserAssetHistory::create([
                    'user_id' => $user_asset->user_id,
                    "user_asset_id" => $user_asset->id,

        'name' => $user_asset->name,
        'code' => $user_asset->code,
        'serial_number' => $user_asset->serial_number,
        'type' => $user_asset->type,
        "is_working" => $user_asset->is_working,
        "status" => $user_asset->status,
        'image' => $user_asset->image,
        'date' => $user_asset->date,
        'note' => $user_asset->note,
        "business_id" => $user_asset->business_id,
                    "from_date" => now(),
                    "to_date" => NULL,
                    'created_by' => $request_data["created_by"]

                  ]
                  );

                  if($user_asset->status == "returned") {
                    $user_asset->user_id = NULL;
                    $user_asset->save();
                  }



                //   $this->moveUploadedFiles($request_data["image"],"assets");


    DB::commit();
                  return response($user_asset, 201);




          } catch (Exception $e) {


            try {
                if(!empty($request_data["image"])) {

                    $this->moveUploadedFilesBack([$request_data["image"]],"","assets");
                       }
            } catch (Exception $innerException) {
                error_log("Failed to move assets files back: " . $innerException->getMessage());
            }




    DB::rollBack();


              return $this->sendError($e, 500, $request);
          }
      }



       public function addExistingUserAsset(UserAssetAddExistingRequest $request)
       {

           try {
               $this->storeActivity($request, "DUMMY activity","DUMMY description");
               return DB::transaction(function () use ($request) {
                   if (!$request->user()->hasPermissionTo('employee_asset_update')) {
                       return response()->json([
                           "message" => "You can not perform this action"
                       ], 401);
                   }

                   $request_data = $request->validated();




                   $user_asset_query_params = [
                       "id" => $request_data["id"],
                   ];
                   $user_asset_prev = UserAsset::where($user_asset_query_params)
                       ->first();
                   if (!$user_asset_prev) {

                       return response()->json([
                           "message" => "no user document found"
                       ], 404);
                   }


                   $user_asset  =  tap(UserAsset::where($user_asset_query_params))->update(
                       collect($request_data)->only([
                            'user_id',


                       ])->toArray()
                   )
                       // ->with("somthing")

                       ->first();
                   if (!$user_asset) {
                       return response()->json([
                           "message" => "something went wrong."
                       ], 500);
                   }

                   if($user_asset_prev->user_id != $user_asset->user_id) {
                    UserAssetHistory::where([
                        'user_id' => $user_asset_prev->user_id,
                        "user_asset_id" => $user_asset_prev->id,
                        "to_date" => NULL
                    ])
                    ->update([
                        "to_date" => now(),
                    ]);


                        $user_asset_history  =  UserAssetHistory::create([
                            'user_id' => $user_asset->user_id,
                            "user_asset_id" => $user_asset->id,

                            'name' => $user_asset->name,
                            'code' => $user_asset->code,
                            'serial_number' => $user_asset->serial_number,
                            'type' => $user_asset->type,
                            "is_working" => $user_asset->is_working,
                            "status" => $user_asset->status,
                            'image' => $user_asset->image,
                            'date' => $user_asset->date,
                            'note' => $user_asset->note,
                            "business_id" => $user_asset->business_id,

                            "from_date" => now(),
                            "to_date" => NULL,
                            'created_by' => $user_asset->created_by

                          ]
                          );


                   }

                   return response($user_asset, 201);
               });
           } catch (Exception $e) {
               error_log($e->getMessage());
               return $this->sendError($e, 500, $request);
           }
       }




      public function updateUserAsset(UserAssetUpdateRequest $request)
      {

          try {
              $this->storeActivity($request, "DUMMY activity","DUMMY description");
              return DB::transaction(function () use ($request) {
                  if (!$request->user()->hasPermissionTo('employee_asset_update')) {
                      return response()->json([
                          "message" => "You can not perform this action"
                      ], 401);
                  }

                  $request_data = $request->validated();
                  if(!empty($request_data["image"])) {
                    $request_data["image"]= $this->storeUploadedFiles([$request_data["image"]],"","assets");
                    $this->makeFilePermanent($request_data["image"],"");
                  }

                  if($request_data["status"] == "returned") {
                    $request_data["user_id"] = NULL;
                  }




                  $user_asset_query_params = [
                      "id" => $request_data["id"],
                  ];
                  $user_asset_prev = UserAsset::where($user_asset_query_params)
                      ->first();
                  if (!$user_asset_prev) {

                      return response()->json([
                          "message" => "no user asset found"
                      ], 404);
                  }

                  $user_asset  =  tap(UserAsset::where($user_asset_query_params))->update(
                      collect($request_data)->only([
                           'user_id',
                          'name',
                          'code',
                          'serial_number',
                          'is_working',
                          "status",
                          'type',
                          'image',
                          'date',
                          'note',
                          // 'created_by',

                      ])->toArray()
                  )
                      // ->with("somthing")

                      ->first();
                  if (!$user_asset) {
                      return response()->json([
                          "message" => "something went wrong."
                      ], 500);
                  }
                  if($user_asset_prev->user_id != $user_asset->user_id) {

                    $user_asset->status = "assigned";
                    $user_asset->save();


                    UserAssetHistory::where([
                        'user_id' => $user_asset_prev->user_id,
                        "user_asset_id" => $user_asset_prev->id,
                        "to_date" => NULL
                    ])
                    ->update([
                        "to_date" => now(),
                        "status" => "returned",
                    ]);

                   }


                   $user_asset_history  =  UserAssetHistory::create([
                    'user_id' => $user_asset->user_id,
                    "user_asset_id" => $user_asset->id,

                    'name' => $user_asset->name,
                    'code' => $user_asset->code,
                    'serial_number' => $user_asset->serial_number,
                    'type' => $user_asset->type,
                    "is_working" => $user_asset->is_working,
                    "status" => $user_asset->status,
                    'image' => $user_asset->image,
                    'date' => $user_asset->date,
                    'note' => $user_asset->note,
                    "business_id" => $user_asset->business_id,

                    "from_date" => now(),
                    "to_date" => NULL,
                    'created_by' => $user_asset->created_by

                  ]);
                //    $this->moveUploadedFiles($request_data["image"],"assets");
                  return response($user_asset, 201);
              });
          } catch (Exception $e) {
              error_log($e->getMessage());
              return $this->sendError($e, 500, $request);
          }
      }

       public function returnUserAsset(UserAssetReturnRequest $request)
       {

           try {
               $this->storeActivity($request, "DUMMY activity","DUMMY description");
               return DB::transaction(function () use ($request) {
                   if (!$request->user()->hasPermissionTo('employee_asset_update')) {
                       return response()->json([
                           "message" => "You can not perform this action"
                       ], 401);
                   }

                   $request_data = $request->validated();





                   $user_asset_query_params = [
                       "id" => $request_data["id"],
                       "user_id" => $request_data["user_id"],
                       "business_id" => auth()->user()->business_id
                   ];

                   $user_asset  =  UserAsset::where($user_asset_query_params)

                       // ->with("somthing")
                       ->first();

                   if (empty($user_asset)) {
                       return response()->json([
                           "message" => "something went wrong."
                       ], 500);
                   }
                   $user_asset->user_id = NULL;
                   $user_asset->status = "available";
                   $user_asset->save();

                     UserAssetHistory::where([
                         'user_id' => $request_data["user_id"],
                         "user_asset_id" => $request_data["id"],
                         "to_date" => NULL
                     ])
                     ->update([
                         "to_date" => now(),
                         "status" => "returned",
                     ]);






                   return response($user_asset, 201);
               });
           } catch (Exception $e) {
               error_log($e->getMessage());
               return $this->sendError($e, 500, $request);
           }
       }




      public function getUserAssets(Request $request)
      {
          try {
              $this->storeActivity($request, "DUMMY activity","DUMMY description");
              if (!$request->user()->hasPermissionTo('employee_asset_view')) {
                  return response()->json([
                      "message" => "You can not perform this action"
                  ], 401);
              }
              $all_manager_department_ids = $this->get_all_departments_of_manager();
              $user_assets = UserAsset::with([
                  "creator" => function ($query) {
                      $query->select('users.id', 'users.first_Name','users.middle_Name',
                      'users.last_Name');
                  },
                  "user" => function ($query) {
                    $query->select('users.id', 'users.first_Name','users.middle_Name',
                    'users.last_Name');
                },


              ])
              ->where([
                "business_id" => auth()->user()->business_id
              ])


              ->where(function($query) use($all_manager_department_ids) {
                $query->whereHas("user.department_user.department", function($query) use($all_manager_department_ids) {
                    $query->whereIn("departments.id",$all_manager_department_ids);
                 })
                 ->orWhere('user_assets.user_id', NULL)
                 ->orWhereHas("user", function($query)  {
                    $query->where("users.id",auth()->user()->id);
                 })
                 ;

              })




              ->when(!empty($request->search_key), function ($query) use ($request) {
                      return $query->where(function ($query) use ($request) {
                          $term = $request->search_key;
                          $query->where("user_assets.name", "like", "%" . $term . "%");
                          $query->orWhere("user_assets.code", "like", "%" . $term . "%");
                          $query->orWhere("user_assets.serial_number", "like", "%" . $term . "%");

                          //     ->orWhere("user_assets.description", "like", "%" . $term . "%");
                      });
                  })

                  ->when(!empty($request->name), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        $term = $request->name;
                        $query->where("user_assets.name", "like", "%" . $term . "%");

                    });
                })

                ->when(!empty($request->asset_code), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        $term = $request->asset_code;
                        $query->where("user_assets.code", "like", "%" . $term . "%");

                    });
                })
                ->when(!empty($request->serial_number), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        $term = $request->serial_number;
                        $query->where("user_assets.serial_no", "like", "%" . $term . "%");

                    });
                })

                ->when(isset($request->is_working), function ($query) use ($request) {
                    return $query->where('user_assets.is_working', intval($request->is_working));
                })


                ->when(!empty($request->date), function ($query) use ($request) {
                    return $query->where('user_assets.date', $request->date);
                })




                  ->when(!empty($request->user_id), function ($query) use ($request) {
                      return $query->where('user_assets.user_id', $request->user_id);
                  })

                  ->when(!empty($request->not_in_user_id), function ($query) use ($request) {
                    return $query->where(function($query) use($request) {
                        $query->whereNotIn('user_assets.user_id', [$request->not_in_user_id])
                        ->orWhereNull('user_assets.user_id');
                    });


                })






                  ->when(!empty($request->type), function ($query) use ($request) {
                    return $query->where('user_assets.type', $request->type);
                })

                ->when(!empty($request->status), function ($query) use ($request) {
                    return $query->where('user_assets.status', $request->status);
                })


                //   ->when(empty($request->user_id), function ($query) use ($request) {
                //       return $query->where('user_assets.user_id', $request->user()->id);
                //   })
                  ->when(!empty($request->start_date), function ($query) use ($request) {
                      return $query->where('user_assets.date', ">=", $request->start_date);
                  })
                  ->when(!empty($request->end_date), function ($query) use ($request) {
                      return $query->where('user_assets.date', "<=", ($request->end_date . ' 23:59:59'));
                  })
                  ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                      return $query->orderBy("user_assets.id", $request->order_by);
                  }, function ($query) {
                      return $query->orderBy("user_assets.id", "DESC");
                  })
                  ->when(!empty($request->per_page), function ($query) use ($request) {
                      return $query->paginate($request->per_page);
                  }, function ($query) {
                      return $query->get();
                  });;

                  if (!empty($request->response_type) && in_array(strtoupper($request->response_type), ['PDF', 'CSV'])) {
                    if (strtoupper($request->response_type) == 'PDF') {
                        $pdf = PDF::loadView('pdf.user_assets', ["user_assets" => $user_assets]);
                        return $pdf->download(((!empty($request->file_name) ? $request->file_name : 'employee') . '.pdf'));
                    } elseif (strtoupper($request->response_type) === 'CSV') {

                        return Excel::download(new UserAssetsExport($user_assets), ((!empty($request->file_name) ? $request->file_name : 'employee') . '.csv'));
                    }
                } else {
                    return response()->json($user_assets, 200);
                }


              return response()->json($user_assets, 200);
          } catch (Exception $e) {

              return $this->sendError($e, 500, $request);
          }
      }

      

      public function getUserAssetById($id, Request $request)
      {
          try {
              $this->storeActivity($request, "DUMMY activity","DUMMY description");
              if (!$request->user()->hasPermissionTo('employee_asset_view')) {
                  return response()->json([
                      "message" => "You can not perform this action"
                  ], 401);
              }

              $all_manager_department_ids = $this->get_all_departments_of_manager();
              $user_asset =  UserAsset::where([
                  "id" => $id,
                  "business_id" => auth()->user()->business_id
              ])
              ->where(function($query) use($all_manager_department_ids) {
                $query->whereHas("user.department_user.department", function($query) use($all_manager_department_ids) {
                    $query->whereIn("departments.id",$all_manager_department_ids);
                 })
                 ->orWhere('user_assets.user_id', NULL)
                 ;

              })
                  ->first();
              if (!$user_asset) {

                  return response()->json([
                      "message" => "no data found"
                  ], 404);
              }



                // if(!empty($user_asset->user->departments[0])){
                //     if(!in_array($user_asset->user->departments[0]->id,$all_manager_department_ids)){
                //         return response()->json([
                //             "message" => "The use assigned is not in your department"
                //         ], 409);
                //     }
                // } else {
                //     return response()->json([
                //         "message" => "The use assigned don't have a department"
                //     ], 409);
                // }






              return response()->json($user_asset, 200);
          } catch (Exception $e) {

              return $this->sendError($e, 500, $request);
          }
      }



      public function deleteUserAssetsByIds(Request $request, $ids)
      {

          try {
              $this->storeActivity($request, "DUMMY activity","DUMMY description");
              if (!$request->user()->hasPermissionTo('employee_asset_delete')) {
                  return response()->json([
                      "message" => "You can not perform this action"
                  ], 401);
              }

              $all_manager_department_ids = $this->get_all_departments_of_manager();
              $idsArray = explode(',', $ids);

              $userAssets = UserAsset::whereIn('id', $idsArray)
              ->where(function($query) use($all_manager_department_ids) {
                $query->whereHas("user.department_user.department", function($query) use($all_manager_department_ids) {
                    $query->whereIn("departments.id",$all_manager_department_ids);
                 })
                 ->orWhere('user_assets.user_id', NULL);
              })
             ->where([
                "business_id" => auth()->user()->business_id
              ])
                  ->select('id')
                  ->get();

            $canDeleteAssetIds = $userAssets->filter(function ($asset) {
                    return $asset->can_delete;
                })->pluck('id')->toArray();
              $nonExistingIds = array_diff($idsArray, $canDeleteAssetIds);

              if (!empty($nonExistingIds)) {

                  return response()->json([
                      "message" => "Some or all of the specified data do not exist."
                  ], 404);
              }

            UserAsset::destroy($canDeleteAssetIds);


            UserAssetHistory::where([
                "to_date" => NULL
            ])
            ->whereIn("user_asset_id",$canDeleteAssetIds)
            ->update([
                "to_date" => now(),
            ]);





              return response()->json(["message" => "data deleted sussfully","deleted_ids" => $canDeleteAssetIds], 200);
          } catch (Exception $e) {

              return $this->sendError($e, 500, $request);
          }
      }
}
