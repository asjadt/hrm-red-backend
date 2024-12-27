<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnnouncementCreateRequest;
use App\Http\Requests\AnnouncementStatusUpdateRequest;
use App\Http\Requests\AnnouncementUpdateRequest;
use App\Http\Utils\BasicUtil;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\Announcement;
use App\Models\Department;
use App\Models\DepartmentAnnouncement;
use App\Models\User;
use App\Models\UserAnnouncement;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnnouncementController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil, BasicUtil;
    
    /**
     *
     * @OA\Post(
     *      path="/v1.0/announcements",
     *      operationId="createAnnouncement",
     *      tags={"administrator.announcements"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to store announcement",
     *      description="This method is to store announcement",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
 * @OA\Property(property="name", type="string", format="string", example="tttttt"),
 * @OA\Property(property="start_date", type="string", format="date", example="2023-11-14"),
 * @OA\Property(property="end_date", type="string", format="date", example="2023-11-23"),
 * @OA\Property(property="description", type="string", format="string", example="erg ear ga&nbsp;"),
 * @OA\Property(property="departments", type="string", format="array", example={1,2,3}),
 *
 *
     *
     *         ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */

    public function createAnnouncement(AnnouncementCreateRequest $request)
    {
        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");

                if (!$request->user()->hasPermissionTo('announcement_create')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }

                $request_data = $request->validated();




                $request_data["business_id"] = $request->user()->business_id;
                $request_data["is_active"] = true;
                $request_data["created_by"] = $request->user()->id;

                $announcement =  Announcement::create($request_data);
                $announcement->departments()->sync($request_data['departments']);



             $user_ids  = User::

                whereHas("department_user.department", function($query) use($request_data) {
                    $query->whereIn("departments.id",$request_data["departments"]);
                })
                ->orWhereHas("roles", function ($query) {
                    return $query->where("roles.name", "business_owner");
                })
                ->pluck("id")->unique();
            $announcement->users()->attach($user_ids, ['status' => 'unread']);


            DB::commit();
                return response($announcement, 201);

        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError($e, 500, $request);
        }
    }

    /**
     *
     * @OA\Put(
     *      path="/v1.0/announcements",
     *      operationId="updateAnnouncement",
     *      tags={"administrator.announcements"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update announcement ",
     *      description="This method is to update announcement",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
*      @OA\Property(property="id", type="number", format="number", example="Updated Christmas"),
 * @OA\Property(property="name", type="string", format="string", example="tttttt"),
 * @OA\Property(property="start_date", type="string", format="date", example="2023-11-14"),
 * @OA\Property(property="end_date", type="string", format="date", example="2023-11-23"),
 * @OA\Property(property="description", type="string", format="string", example="erg ear ga&nbsp;"),
 * @OA\Property(property="departments", type="string", format="array", example={1,2,3})

     *
     *         ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */

    public function updateAnnouncement(AnnouncementUpdateRequest $request)
    {

        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");

                if (!$request->user()->hasPermissionTo('announcement_update')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }
                $business_id =  $request->user()->business_id;
                $request_data = $request->validated();




                $announcement_query_params = [
                    "id" => $request_data["id"],
                    "business_id" => $business_id
                ];
                $announcement_prev = Announcement::where($announcement_query_params)
                    ->first();
                if (!$announcement_prev) {
                    return response()->json([
                        "message" => "no announcement found"
                    ], 404);
                }

                $announcement  =  tap(Announcement::where($announcement_query_params))->update(
                    collect($request_data)->only([
                        'name',
                        'start_date',
                        'end_date',
                        'description',
                        // "is_active",
                        // "business_id",
                        // "created_by"

                    ])->toArray()
                )
                    // ->with("somthing")

                    ->first();
                if (!$announcement) {
                    return response()->json([
                        "message" => "something went wrong."
                    ], 500);
                }
                $announcement->departments()->sync($request_data['departments']);



                $user_ids = User::whereHas("department_user.department", function($query) use($request_data) {
                    $query->whereIn("departments.id", $request_data["departments"]);
                })->pluck("id")->unique();
                $announcement->users()->sync($user_ids, ['status' => 'unread']);



                DB::commit();

                return response($announcement, 201);

        } catch (Exception $e) {
            DB::rollBack();

            return $this->sendError($e, 500, $request);
        }
    }


    /**
     *
     * @OA\Get(
     *      path="/v1.0/announcements",
     *      operationId="getAnnouncements",
     *      tags={"administrator.announcements"},
     *       security={
     *           {"bearerAuth": {}}
     *       },

     *              @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="per_page",
     *         required=true,
     *  example="6"
     *      ),

     *      * *  @OA\Parameter(
     * name="start_date",
     * in="query",
     * description="start_date",
     * required=true,
     * example="2019-06-29"
     * ),
     * *  @OA\Parameter(
     * name="end_date",
     * in="query",
     * description="end_date",
     * required=true,
     * example="2019-06-29"
     * ),
     * *  @OA\Parameter(
     * name="search_key",
     * in="query",
     * description="search_key",
     * required=true,
     * example="search_key"
     * ),
     *   * *  @OA\Parameter(
     * name="status",
     * in="query",
     * description="status",
     * required=true,
     * example="status"
     * ),
     * *  @OA\Parameter(
     * name="order_by",
     * in="query",
     * description="order_by",
     * required=true,
     * example="ASC"
     * ),

     *      summary="This method is to get announcements  ",
     *      description="This method is to get announcements ",
     *

     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */

    public function getAnnouncements(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('announcement_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;
            $all_manager_department_ids = $this->get_all_departments_of_manager();
            $announcements = Announcement::with([
                "creator" => function ($query) {
                    $query->select('users.id', 'users.first_Name','users.middle_Name',
                    'users.last_Name');
                },
                "departments" => function ($query) {
                    $query->select('departments.id', 'departments.name'); // Specify the fields for the creator relationship
                }
            ])
            ->where(
                [
                    "announcements.business_id" => $business_id
                ]
            )
            ->where(function($query) use($all_manager_department_ids) {
                $query->whereHas("departments",function($query) use($all_manager_department_ids) {
                  $query->whereIn("departments.id",$all_manager_department_ids);
                })

                ->orWhereDoesntHave("departments");
            })



                ->when(!empty($request->search_key), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        $term = $request->search_key;
                        $query->where("announcements.name", "like", "%" . $term . "%")
                            ->orWhere("announcements.description", "like", "%" . $term . "%");
                    });
                })

                ->when(!empty($request->status), function ($query) use ($request) {


                    $query->whereHas("users", function($query) use ($request) {
                        $query->where('status', $request->status)
                        ->where("user_id",auth()->user()->id);
                    });


                })
                //    ->when(!empty($request->product_category_id), function ($query) use ($request) {
                //        return $query->where('product_category_id', $request->product_category_id);
                //    })
                ->when(!empty($request->start_date), function ($query) use ($request) {
                    return $query->where('announcements.created_at', ">=", $request->start_date);
                })
                ->when(!empty($request->end_date), function ($query) use ($request) {
                    return $query->where('announcements.created_at', "<=", ($request->end_date . ' 23:59:59'));
                })
                ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                    return $query->orderBy("announcements.id", $request->order_by);
                }, function ($query) {
                    return $query->orderBy("announcements.id", "DESC");
                })
                ->when(!empty($request->per_page), function ($query) use ($request) {
                    return $query->paginate($request->per_page);
                }, function ($query) {
                    return $query->get();
                });;



            return response()->json($announcements, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }








    /**
     *
     * @OA\Get(
     *      path="/v1.0/announcements/{id}",
     *      operationId="getAnnouncementById",
     *      tags={"administrator.announcements"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="id",
     *         required=true,
     *  example="6"
     *      ),
     *      summary="This method is to get announcement by id",
     *      description="This method is to get announcement by id",
     *

     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */


    public function getAnnouncementById($id, Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('announcement_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;
            $announcement =  Announcement::with([
                "creator" => function ($query) {
                    $query->select('users.id', 'users.first_Name','users.middle_Name',
                    'users.last_Name');
                },
                "departments" => function ($query) {
                    $query->select('departments.id', 'departments.name'); // Specify the fields for the creator relationship
                }
            ])

->where([
                "id" => $id,
                "business_id" => $business_id
            ])
                ->first();
            if (!$announcement) {
                return response()->json([
                    "message" => "no data found"
                ], 404);
            }

            return response()->json($announcement, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }



    /**
     *
     *     @OA\Delete(
     *      path="/v1.0/announcements/{ids}",
     *      operationId="deleteAnnouncementsByIds",
     *      tags={"administrator.announcements"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="ids",
     *         in="path",
     *         description="ids",
     *         required=true,
     *  example="1,2,3"
     *      ),
     *      summary="This method is to delete announcement by id",
     *      description="This method is to delete announcement by id",
     *

     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */

    public function deleteAnnouncementsByIds(Request $request, $ids)
    {

        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('announcement_delete')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;
            $idsArray = explode(',', $ids);
            $existingIds = Announcement::where([
                "business_id" => $business_id
            ])
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
            Announcement::destroy($existingIds);


            return response()->json(["message" => "data deleted sussfully","deleted_ids" => $existingIds], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }




       /**
     *
     * @OA\Get(
     *      path="/v1.0/clients/announcements",
     *      operationId="getAnnouncementsClient",
     *      tags={"administrator.announcements.client"},
     *       security={
     *           {"bearerAuth": {}}
     *       },

     *              @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="per_page",
     *         required=true,
     *  example="6"
     *      ),

     *      * *  @OA\Parameter(
     * name="start_date",
     * in="query",
     * description="start_date",
     * required=true,
     * example="2019-06-29"
     * ),
     * *  @OA\Parameter(
     * name="end_date",
     * in="query",
     * description="end_date",
     * required=true,
     * example="2019-06-29"
     * ),
     * *  @OA\Parameter(
     * name="search_key",
     * in="query",
     * description="search_key",
     * required=true,
     * example="search_key"
     * ),
     * *  @OA\Parameter(
     * name="order_by",
     * in="query",
     * description="order_by",
     * required=true,
     * example="ASC"
     * ),

     *      summary="This method is to get announcements  ",
     *      description="This method is to get announcements ",
     *

     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */

     public function getAnnouncementsClient(Request $request)
     {
         try {
             $this->storeActivity($request, "DUMMY activity","DUMMY description");

             $business_id =  $request->user()->business_id;

             $all_parent_departments_of_user = $this->all_parent_departments_of_user(auth()->user()->id);

          $this->addAnnouncementIfMissing($all_parent_departments_of_user);






             $announcements = Announcement::with([
                 "creator" => function ($query) {
                     $query->select('users.id', 'users.first_Name','users.middle_Name',
                     'users.last_Name');
                 },
                 "departments" => function ($query) {
                     $query->select('departments.id', 'departments.name'); // Specify the fields for the creator relationship
                 },


             ])

             ->where(
                 [
                     "announcements.business_id" => $business_id
                 ]
             )
             ->whereHas("users", function($query) use($request) {
                $query->where("user_announcements.user_id",auth()->user()->id)
                ->when(!empty($request->status), function ($query) use ($request) {
                 $query->where('user_announcements.status', $request->status);
                });
            })

                 ->when(!empty($request->search_key), function ($query) use ($request) {
                     return $query->where(function ($query) use ($request) {
                         $term = $request->search_key;
                         $query->where("announcements.name", "like", "%" . $term . "%")
                             ->orWhere("announcements.description", "like", "%" . $term . "%");
                     });
                 })

                 ->when(!empty($request->start_date), function ($query) use ($request) {
                     return $query->where('announcements.created_at', ">=", $request->start_date);
                 })
                 ->when(!empty($request->end_date), function ($query) use ($request) {
                     return $query->where('announcements.created_at', "<=", ($request->end_date . ' 23:59:59'));
                 })
                 ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                     return $query->orderBy("announcements.id", $request->order_by);
                 }, function ($query) {
                     return $query->orderBy("announcements.id", "DESC");
                 })
                 ->when(!empty($request->per_page), function ($query) use ($request) {
                     return $query->paginate($request->per_page);
                 }, function ($query) {
                     return $query->get();
                 });;



             return response()->json($announcements, 200);
         } catch (Exception $e) {

             return $this->sendError($e, 500, $request);
         }
     }

     /**
     *
     * @OA\Get(
     *      path="/v1.0/clients/announcements-count",
     *      operationId="getAnnouncementsCountClient",
     *      tags={"administrator.announcements.client"},
     *       security={
     *           {"bearerAuth": {}}
     *       },

     *              @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="per_page",
     *         required=true,
     *  example="6"
     *      ),

     *      * *  @OA\Parameter(
     * name="start_date",
     * in="query",
     * description="start_date",
     * required=true,
     * example="2019-06-29"
     * ),
     * *  @OA\Parameter(
     * name="end_date",
     * in="query",
     * description="end_date",
     * required=true,
     * example="2019-06-29"
     * ),
     * *  @OA\Parameter(
     * name="search_key",
     * in="query",
     * description="search_key",
     * required=true,
     * example="search_key"
     * ),
     * *  @OA\Parameter(
     * name="order_by",
     * in="query",
     * description="order_by",
     * required=true,
     * example="ASC"
     * ),

     *      summary="This method is to get announcements count  ",
     *      description="This method is to get announcements count ",
     *

     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */

     public function getAnnouncementsCountClient(Request $request)
     {
         try {
             $this->storeActivity($request, "DUMMY activity","DUMMY description");

             $all_parent_departments_of_user = $this->all_parent_departments_of_user(auth()->user()->id);

             $this->addAnnouncementIfMissing($all_parent_departments_of_user);

             $business_id =  $request->user()->business_id;
             $announcements = Announcement::with([
                 "creator" => function ($query) {
                     $query->select('users.id', 'users.first_Name','users.middle_Name',
                     'users.last_Name');
                 },
                 "departments" => function ($query) {
                     $query->select('departments.id', 'departments.name'); // Specify the fields for the creator relationship
                 },


             ])

             ->where(
                 [
                     "announcements.business_id" => $business_id
                 ]
             )
             ->whereHas("users", function($query) use($request) {
                $query->where("user_announcements.user_id",auth()->user()->id)
                ->when(!empty($request->status), function ($query) use ($request) {
                 $query->where('user_announcements.status', $request->status);
                });
            })

                 ->when(!empty($request->search_key), function ($query) use ($request) {
                     return $query->where(function ($query) use ($request) {
                         $term = $request->search_key;
                         $query->where("announcements.name", "like", "%" . $term . "%")
                             ->orWhere("announcements.description", "like", "%" . $term . "%");
                     });
                 })

                 ->when(!empty($request->start_date), function ($query) use ($request) {
                     return $query->where('announcements.created_at', ">=", $request->start_date);
                 })
                 ->when(!empty($request->end_date), function ($query) use ($request) {
                     return $query->where('announcements.created_at', "<=", ($request->end_date . ' 23:59:59'));
                 })
                 ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                     return $query->orderBy("announcements.id", $request->order_by);
                 }, function ($query) {
                     return $query->orderBy("announcements.id", "DESC");
                 })
                 ->when(!empty($request->per_page), function ($query) use ($request) {
                     return $query->paginate($request->per_page);
                 }, function ($query) {
                     return $query->get();
                 })
                 ->count();



             return response()->json($announcements, 200);
         } catch (Exception $e) {

             return $this->sendError($e, 500, $request);
         }
     }

     /**
     *
     * @OA\Put(
     *      path="/v1.0/clients/announcements/change-status",
     *      operationId="updateAnnouncementStatus",
     *      tags={"administrator.announcements.client"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update announcement status",
     *      description="This method is to update announcement status",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"announcement_ids"},
     *    @OA\Property(property="announcement_ids", type="string", format="array", example={1,2,3,4,5,6}),

*
     *
     *         ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */

    public function updateAnnouncementStatus(AnnouncementStatusUpdateRequest $request)
    {
        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");


                $request_data = $request->validated();


     UserAnnouncement::whereIn('announcement_id', $request_data["announcement_ids"])
    ->where('user_id', auth()->user()->id)
    ->update([
        "status" => "read"
    ]);



    DB::commit();
                return response(["ok" => true], 201);

        } catch (Exception $e) {
        DB::rollBack();
            return $this->sendError($e, 500,$request);
        }
    }


}
