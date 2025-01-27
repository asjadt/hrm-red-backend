<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserSocialSiteCreateRequest;
use App\Http\Requests\UserSocialSiteUpdateRequest;
use App\Http\Utils\BasicUtil;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\Department;
use App\Models\SocialSite;
use App\Models\UserSocialSite;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserSocialSiteController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil, BasicUtil;



      public function createUserSocialSite(UserSocialSiteCreateRequest $request)
      {
          try {
              $this->storeActivity($request, "DUMMY activity","DUMMY description");
              return DB::transaction(function () use ($request) {
                  if (!$request->user()->hasPermissionTo('employee_social_site_create')) {
                      return response()->json([
                          "message" => "You can not perform this action"
                      ], 401);
                  }

                  $request_data = $request->validated();






                  $request_data["created_by"] = $request->user()->id;


UserSocialSite::where([
    'social_site_id'=> $request_data["social_site_id"],
    'user_id' => $request_data["user_id"] ,
])->delete();

                  $user_social_site =  UserSocialSite::create($request_data);



                  return response($user_social_site, 201);
              });
          } catch (Exception $e) {
              error_log($e->getMessage());
              return $this->sendError($e, 500, $request);
          }
      }



      public function updateUserSocialSite(UserSocialSiteUpdateRequest $request)
      {

          try {
              $this->storeActivity($request, "DUMMY activity","DUMMY description");
              return DB::transaction(function () use ($request) {
                  if (!$request->user()->hasPermissionTo('employee_social_site_update')) {
                      return response()->json([
                          "message" => "You can not perform this action"
                      ], 401);
                  }
                  $business_id =  $request->user()->business_id;
                  $request_data = $request->validated();



                  $user_social_site_query_params = [
                      "id" => $request_data["id"]
                  ];
                  // $user_social_site_prev = UserSocialSite::where($user_social_site_query_params)
                  //     ->first();
                  // if (!$user_social_site_prev) {
                  //     return response()->json([
                  //         "message" => "no user social site found"
                  //     ], 404);
                  // }

                  if (empty($request["profile_link"])) {
                    UserSocialSite::where($user_social_site_query_params)->delete();
                    return response(["ok" => true], 201);
                  } else {
                    $user_social_site  =  tap(UserSocialSite::where($user_social_site_query_params))->update(
                        collect($request_data)->only([
                          'social_site_id',
                          'user_id',
                          'profile_link',
                          // "created_by"

                        ])->toArray()
                    )
                        // ->with("somthing")

                        ->first();
                    if (empty($user_social_site)) {
                        return response()->json([
                            "message" => "something went wrong."
                        ], 500);
                    }

                  }


                  return response($user_social_site, 201);
              });
          } catch (Exception $e) {
              error_log($e->getMessage());
              return $this->sendError($e, 500, $request);
          }
      }



      public function getUserSocialSites(Request $request)
      {
          try {
              $this->storeActivity($request, "DUMMY activity","DUMMY description");
              if (!$request->user()->hasPermissionTo('employee_social_site_view')) {
                  return response()->json([
                      "message" => "You can not perform this action"
                  ], 401);
              }
              $business_id =  $request->user()->business_id;
              $all_manager_department_ids = $this->get_all_departments_of_manager();

              $user_social_sites = SocialSite::where('is_active', 1)
              ->with(['user_social_site' => function ($query) use ($request, $all_manager_department_ids) {
                  $query->when(!empty($request->user_id), function ($query) use ($request, $all_manager_department_ids) {
                    return $query->where('user_social_sites.user_id', $request->user_id)
                    ->whereHas("user.department_user.department", function($query) use($all_manager_department_ids) {
                        $query->whereIn("departments.id",$all_manager_department_ids);
                     });

                })
                ->when(empty($request->user_id), function ($query) use ($request) {
                    return $query->where('user_social_sites.user_id', $request->user()->id);
                })
                ;
              }])

              ->when(!empty($request->search_key), function ($query) use ($request) {
                      return $query->where(function ($query) use ($request) {
                          $term = $request->search_key;
                          $query->where("social_sites.name", "like", "%" . $term . "%");
                          //     ->orWhere("user_social_sites.description", "like", "%" . $term . "%");
                      });
                  })
                  //    ->when(!empty($request->product_category_id), function ($query) use ($request) {
                  //        return $query->where('product_category_id', $request->product_category_id);
                  //    })


                  ->when(!empty($request->start_date), function ($query) use ($request) {
                      return $query->where('user_social_sites.created_at', ">=", $request->start_date);
                  })
                  ->when(!empty($request->end_date), function ($query) use ($request) {
                      return $query->where('user_social_sites.created_at', "<=", ($request->end_date . ' 23:59:59'));
                  })
                  ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                      return $query->orderBy("social_sites.id", $request->order_by);
                  }, function ($query) {
                      return $query->orderBy("social_sites.id", "DESC");
                  })
                  ->when(!empty($request->per_page), function ($query) use ($request) {
                      return $query->paginate($request->per_page);
                  }, function ($query) {
                      return $query->get();
                  });;



              return response()->json($user_social_sites, 200);
          } catch (Exception $e) {

              return $this->sendError($e, 500, $request);
          }
      }

      

      public function getUserSocialSiteById($id, Request $request)
      {
          try {
              $this->storeActivity($request, "DUMMY activity","DUMMY description");
              if (!$request->user()->hasPermissionTo('employee_social_site_view')) {
                  return response()->json([
                      "message" => "You can not perform this action"
                  ], 401);
              }

              $all_manager_department_ids = $this->get_all_departments_of_manager();
              $user_social_site =  UserSocialSite::where([
                  "id" => $id,
              ])
              ->whereHas("user.department_user.department", function($query) use($all_manager_department_ids) {
                $query->whereIn("departments.id",$all_manager_department_ids);
             })
              ->whereHas("user", function($q) use($request) {
                $q->where("users.business_id", auth()->user()->business_id)
                ->orWhere("users.created_by", $request->user()->id);
            })
                  ->first();
              if (!$user_social_site) {

                  return response()->json([
                      "message" => "no data found"
                  ], 404);
              }

              return response()->json($user_social_site, 200);
          } catch (Exception $e) {

              return $this->sendError($e, 500, $request);
          }
      }




      public function deleteUserSocialSitesByIds(Request $request, $ids)
      {

          try {
              $this->storeActivity($request, "DUMMY activity","DUMMY description");
              if (!$request->user()->hasPermissionTo('employee_social_site_delete')) {
                  return response()->json([
                      "message" => "You can not perform this action"
                  ], 401);
              }

              $all_manager_department_ids = $this->get_all_departments_of_manager();
              $idsArray = explode(',', $ids);
              $existingIds = UserSocialSite::whereIn("id",$idsArray)
              ->whereHas("user.department_user.department", function($query) use($all_manager_department_ids) {
                $query->whereIn("departments.id",$all_manager_department_ids);
             })
                  ->whereIn('id', $idsArray)
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
              UserSocialSite::destroy($existingIds);


              return response()->json(["message" => "data deleted sussfully","deleted_ids" => $existingIds], 200);
          } catch (Exception $e) {

              return $this->sendError($e, 500, $request);
          }
      }
}
