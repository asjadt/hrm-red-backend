<?php

namespace App\Http\Controllers;

use App\Exports\WorkShiftsExport;
use App\Http\Components\WorkShiftHistoryComponent;
use App\Http\Requests\GetIdRequest;
use App\Http\Requests\WorkShiftCreateRequest;
use App\Http\Requests\WorkShiftUpdateRequest;
use App\Http\Utils\BasicUtil;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\ModuleUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\Attendance;
use App\Models\BusinessTime;
use App\Models\Department;
use App\Models\EmployeeUserWorkShiftHistory;
use App\Models\WorkShiftHistory;
use App\Models\User;

use App\Models\WorkShift;
use App\Models\WorkShiftDetailHistory;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDF;
use Maatwebsite\Excel\Facades\Excel;

class WorkShiftController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil, BasicUtil, ModuleUtil;


    protected $workShiftHistoryComponent;


    public function __construct(WorkShiftHistoryComponent $workShiftHistoryComponent,)
    {
        $this->workShiftHistoryComponent = $workShiftHistoryComponent;
    }


    /**
     *
     * @OA\Post(
     *      path="/v1.0/work-shifts",
     *      operationId="createWorkShift",
     *      tags={"administrator.work_shift"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to store work shift",
     *      description="This method is to store work shift",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *     @OA\Property(property="name", type="string", format="string", example="Updated Christmas"),
     *     @OA\Property(property="type", type="string", format="string", example="regular"),
     *  *     @OA\Property(property="description", type="string", format="string", example="description"),
     *      *  *     @OA\Property(property="is_personal", type="boolean", format="boolean", example="0"),
     *
     *     @OA\Property(property="break_type", type="string", format="string", example="paid"),
     *  *     @OA\Property(property="break_hours", type="boolean", format="boolean", example="0"),
     *
     *     @OA\Property(property="departments", type="string",  format="array", example={1,2,3}),
     *  *     @OA\Property(property="work_locations", type="string",  format="array", example={1,2,3}),

     *     @OA\Property(property="users", type="string", format="array", example={1,2,3}),
     * *     @OA\Property(property="details", type="string", format="array", example={
     *         {
     *             "day": "0",
     *             "start_at": "",
     *             "end_at": "",
     *             "is_weekend": 0
     *         },
     *         {
     *             "day": "1",
     *             "start_at": "",
     *             "end_at": "",
     *             "is_weekend": 0
     *         },
     *         {
     *             "day": "2",
     *             "start_at": "",
     *             "end_at": "",
     *             "is_weekend": 0
     *         },
     *         {
     *             "day": "3",
     *             "start_at": "",
     *             "end_at": "",
     *             "is_weekend": 0
     *         },
     *         {
     *             "day": "4",
     *             "start_at": "",
     *             "end_at": "",
     *             "is_weekend": 0
     *         },
     *         {
     *             "day": "5",
     *             "start_at": "",
     *             "end_at": "",
     *             "is_weekend": 0
     *         },
     *         {
     *             "day": "6",
     *             "start_at": "",
     *             "end_at": "",
     *             "is_weekend": 0
     *         }
     *     }),

     *     @OA\Property(property="start_date", type="string", format="date", example="2023-11-16"),
     *     @OA\Property(property="end_date", type="string", format="date", example="2023-11-16"),
     *
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

    public function createWorkShift(WorkShiftCreateRequest $request)
    {

        try {

            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            return DB::transaction(function () use ($request) {
                if (!$request->user()->hasPermissionTo('work_shift_create')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }
                $request_data = $request->validated();



                if (empty($request_data['departments'])) {

                    $request_data['departments'] = Department::where(
                        [

                            "business_id" => auth()->user()->business_id,
                            "manager_id" => auth()->user()->id

                        ]

                    )
                        ->pluck("id");
                }

                if ($request_data["type"] !== "flexible" && !empty(env("MATCH_BUSINESS_SCHEDULE")) && false) {
                    $check_work_shift_details =  $this->checkWorkShiftDetails($request_data['details']);
                    if (!$check_work_shift_details["ok"]) {
                        throw new Exception(json_encode($check_work_shift_details["error"]), $check_work_shift_details["status"]);
                    }
                } else if ($request_data["type"] == "flexible") {
                    $this->isModuleEnabled("flexible_shifts");
                }




                $request_data["business_id"] = auth()->user()->business_id;
                $request_data["is_active"] = true;
                $request_data["created_by"] = $request->user()->id;
                $request_data["is_default"] = false;


                $work_shift =  WorkShift::create($request_data);

                $work_shift->departments()->sync($request_data['departments']);

                $work_shift->work_locations()->sync($request_data["work_locations"]);
                // $work_shift->users()->sync($request_data['users'], []);

                $request_data['details'] = collect($request_data['details'])->map(function ($el) {

                    if ($el["is_weekend"]) {
                        $el["start_at"] = NULL;
                        $el["end_at"] = NULL;
                    }
                    return $el;
                })->toArray();
                $work_shift->details()->createMany($request_data['details']);

                if (false) {
                    $employee_work_shift_history_data = $work_shift->toArray();
                    $employee_work_shift_history_data["work_shift_id"] = $work_shift->id;
                    // $employee_work_shift_history_data["from_date"] = $request_data["start_date"]?$request_data["start_date"]:now();
                    $employee_work_shift_history_data["from_date"] = auth()->user()->business->start_date;
                    $employee_work_shift_history_data["to_date"] = NULL;

                    $employee_work_shift_history =  WorkShiftHistory::create($employee_work_shift_history_data);
                    $employee_work_shift_history->departments()->sync($request_data['departments']);


                    $employee_work_shift_history->details()->createMany($request_data['details']);
                }






                return response($work_shift, 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }
    /**
     *
     * @OA\Put(
     *      path="/v1.0/work-shifts-check",
     *      operationId="updateWorkShiftCheck",
     *      tags={"administrator.work_shift"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update work shift ",
     *      description="This method is to update work_shift",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *      @OA\Property(property="id", type="number", format="number", example="Updated Christmas"),
     *     @OA\Property(property="name", type="string", format="string", example="Updated Christmas"),
     *     @OA\Property(property="type", type="string", format="string", example="regular"),
     *     @OA\Property(property="description", type="string", format="string", example="description"),
     *    *      *  *     @OA\Property(property="is_personal", type="boolean", format="boolean", example="0"),
     *   *     @OA\Property(property="break_type", type="string", format="string", example="paid"),
     *  *     @OA\Property(property="break_hours", type="boolean", format="boolean", example="0"),
     *
     *     @OA\Property(property="departments", type="string",  format="array", example={1,2,3,4}),
     *      *  *     @OA\Property(property="work_locations", type="string",  format="array", example={1,2,3}),

     *     @OA\Property(property="users", type="string", format="array", example={1,2,3}),
     * *     @OA\Property(property="details", type="string", format="array", example={
     *         {
     *             "day": "0",
     *             "start_at": "",
     *             "end_at": "",
     *             "is_weekend": 0
     *         },
     *         {
     *             "day": "1",
     *             "start_at": "",
     *             "end_at": "",
     *             "is_weekend": 0
     *         },
     *         {
     *             "day": "2",
     *             "start_at": "",
     *             "end_at": "",
     *             "is_weekend": 0
     *         },
     *         {
     *             "day": "3",
     *             "start_at": "",
     *             "end_at": "",
     *             "is_weekend": 0
     *         },
     *         {
     *             "day": "4",
     *             "start_at": "",
     *             "end_at": "",
     *             "is_weekend": 0
     *         },
     *         {
     *             "day": "5",
     *             "start_at": "",
     *             "end_at": "",
     *             "is_weekend": 0
     *         },
     *         {
     *             "day": "6",
     *             "start_at": "",
     *             "end_at": "",
     *             "is_weekend": 0
     *         }
     *     }),

     *     @OA\Property(property="start_date", type="string", format="date", example="2023-11-16"),
     *     @OA\Property(property="end_date", type="string", format="date", example=""),
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

    public function updateWorkShiftCheck(WorkShiftUpdateRequest $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            return DB::transaction(function () use ($request) {

                if (!$request->user()->hasPermissionTo('work_shift_update')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }


                $request_data = $request->validated();
                if (empty($request_data['departments'])) {
                    $request_data['departments'] = [Department::where("business_id", auth()->user()->business_id)->whereNull("parent_id")->first()->id];
                }




                if ($request_data["type"] !== "flexible" && !empty(env("MATCH_BUSINESS_SCHEDULE")) && false) {
                    $check_work_shift_details =  $this->checkWorkShiftDetails($request_data['details']);
                    if (!$check_work_shift_details["ok"]) {

                        throw new Exception(json_encode($check_work_shift_details["error"]), $check_work_shift_details["status"]);
                    }
                } else {
                    $this->isModuleEnabled("flexible_shifts");
                }






                $work_shift_query_params = [
                    "id" => $request_data["id"],
                ];

                $work_shift_prev = WorkShift::where($work_shift_query_params)->first();

                $work_shift = WorkShift::where($work_shift_query_params)->first();

                if ($work_shift) {
                    $work_shift->fill(
                        collect($request_data)->only([
                            'name',
                            'type',
                            'description',
                            'is_personal',
                            'break_type',
                            'break_hours',
                        ])->toArray()
                    );
                }




                if (!$work_shift) {
                    return response()->json([
                        "message" => "something went wrong."
                    ], 500);
                }

                // $work_shift->departments()->delete();
                // $work_shift->departments()->sync($request_data['departments'], []);

                // $work_shift->users()->delete();
                // $work_shift->users()->sync($request_data['users'], []);

                $work_shift_prev_details  =  ($work_shift_prev->details)->toArray();





                $fields_to_check = [
                    // 'name',
                    'type',
                    "description",

                    'is_personal',
                    'break_type',
                    'break_hours'
                ];
                $fields_changed = false; // Initialize to false
                foreach ($fields_to_check as $field) {
                    $value1 = $work_shift_prev->$field;
                    $value2 = $work_shift->$field;

                    if ($value1 !== $value2) {
                        $fields_changed = true;
                        break;
                    }
                }


                if (!$fields_changed) {
                    $fields_to_check = [
                        'work_shift_id',
                        'day',
                        "start_at",
                        'end_at',
                        'is_weekend',
                    ];
                    $fields_changed = false; // Initialize to false
                    foreach ($fields_to_check as $field) {

                        foreach ($work_shift_prev_details as $key => $prev_detail) {
                            $value1 = $prev_detail[$field];
                            $value2 = $work_shift->details[$key]->$field;


                            if ($value1 != $value2) {
                                $fields_changed = true;
                                break 2;
                            }
                        }
                    }
                }



                if (false) {
                    if (
                        $fields_changed
                    ) {

                        WorkShiftHistory::where([
                            "to_date" => NULL
                        ])
                            ->where("work_shift_id", $work_shift_prev->id)
                            // ->whereHas('users',function($query) use($work_shift_prev)  {
                            //     $query->whereIn("users.id",$work_shift_prev->users()->pluck("users.id"));
                            // })
                            ->update([
                                "to_date" => now()
                            ]);

                        $last_inactive_date = WorkShiftHistory::where("work_shift_id", $work_shift->id)
                        ->orderByDesc("work_shift_histories.id")
                            ->first();

                        $employee_work_shift_history_data = $work_shift->toArray();
                        $employee_work_shift_history_data["work_shift_id"] = $work_shift->id;
                        $employee_work_shift_history_data["from_date"] = $last_inactive_date->to_date;
                        $employee_work_shift_history_data["to_date"] = NULL;
                        $employee_work_shift_history =  WorkShiftHistory::create($employee_work_shift_history_data);
                        $employee_work_shift_history->details()->createMany($request_data['details']);
                        $employee_work_shift_history->departments()->sync($request_data['departments']);



                        //  $employee_work_shift_history->users()->sync($work_shift->users()->pluck("id"));

                        $user_ids = $work_shift->users()->pluck('users.id')->toArray();

                        // Define the additional pivot data for each user
                        $pivot_data = collect($user_ids)->mapWithKeys(function ($user_id) {
                            return [$user_id => ['from_date' => now(), 'to_date' => null]];
                        });
                        $employee_work_shift_history->users()->sync($pivot_data);
                    }
                }




                $affected_user_ids = [];
                if (
                    $fields_changed
                ) {
                    $work_shift_histories_after =  WorkShiftHistory::where("work_shift_id", $work_shift->id)
                        ->where(function ($query) use ($request_data) {
                            $query->whereDate("from_date", ">", today());
                        })
                        ->orderByDesc("work_shift_histories.id")
                        ->get();
                    foreach ($work_shift_histories_after as $work_shift_history_after) {
                        $affected_user_ids[] = $work_shift_history_after->user_id;
                        // $work_shift_history_after->fill(
                        //     collect($request_data)->only([
                        //         'name',
                        //         'type',
                        //         'description',
                        //         'is_personal',
                        //         'break_type',
                        //         'break_hours',
                        //         // 'from_date',
                        //         // 'to_date',
                        //         // "is_active",
                        //         // "business_id",
                        //         // "created_by"
                        //     ])->toArray()
                        // )->save();
                        // $work_shift_history_after->details()->delete();
                        // $work_shift_history_after->details()->createMany($request_data['details']);

                    }

                    $work_shift_histories_before =  WorkShiftHistory::where("work_shift_id", $work_shift->id)
                        ->where(function ($query) use ($request_data) {
                            $query->whereDate("from_date", "<=", today())
                                ->where(function ($query) use ($request_data) {
                                    $query->whereDate("to_date", ">=", today())
                                        ->orWhereNull("to_date");
                                });
                        })
                        ->orderByDesc("work_shift_histories.id")
                        ->get();

                    foreach ($work_shift_histories_before as $work_shift_history_before) {
                        $affected_user_ids[] = $work_shift_history_before->user_id;

                        //                 $request_data["from_date"] = today();
                        //                 $request_data["to_date"] = $work_shift_history_before->to_date;
                        //                 $request_data["work_shift_id"] = $work_shift->id;
                        //                 $request_data["user_id"] = $work_shift_history_before->user_id;

                        //                 $work_shift_history_current =  WorkShiftHistory::create(collect($request_data)->only([
                        //                    'name',
                        // "break_type",
                        // "break_hours",
                        // 'type',
                        // "description",

                        // 'is_business_default',
                        // 'is_personal',

                        // "is_default",
                        // "is_active",
                        // "business_id",
                        // "created_by",

                        // "from_date",
                        // "to_date",
                        // "work_shift_id",
                        // "user_id",

                        //                 ])->toArray());

                        //                 $work_shift_history_current->details()->createMany($request_data['details']);

                        //                 $work_shift_history_before->to_date = today();
                        //                 $work_shift_history_before->save();
                    }
                }





                $work_shift->affected_user = User::whereIn("id", $affected_user_ids)
                    ->select("id", "first_Name", "middle_Name", "last_name")
                    ->get();




                return response($work_shift, 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }

    /**
     *
     * @OA\Put(
     *      path="/v1.0/work-shifts",
     *      operationId="updateWorkShift",
     *      tags={"administrator.work_shift"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update work shift ",
     *      description="This method is to update work_shift",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *      @OA\Property(property="id", type="number", format="number", example="Updated Christmas"),
     *     @OA\Property(property="name", type="string", format="string", example="Updated Christmas"),
     *     @OA\Property(property="type", type="string", format="string", example="regular"),
     *     @OA\Property(property="description", type="string", format="string", example="description"),
     *    *      *  *     @OA\Property(property="is_personal", type="boolean", format="boolean", example="0"),
     *   *     @OA\Property(property="break_type", type="string", format="string", example="paid"),
     *  *     @OA\Property(property="break_hours", type="boolean", format="boolean", example="0"),
     *
     *     @OA\Property(property="departments", type="string",  format="array", example={1,2,3,4}),
     *      *  *     @OA\Property(property="work_locations", type="string",  format="array", example={1,2,3}),

     *     @OA\Property(property="users", type="string", format="array", example={1,2,3}),
     * *     @OA\Property(property="details", type="string", format="array", example={
     *         {
     *             "day": "0",
     *             "start_at": "",
     *             "end_at": "",
     *             "is_weekend": 0
     *         },
     *         {
     *             "day": "1",
     *             "start_at": "",
     *             "end_at": "",
     *             "is_weekend": 0
     *         },
     *         {
     *             "day": "2",
     *             "start_at": "",
     *             "end_at": "",
     *             "is_weekend": 0
     *         },
     *         {
     *             "day": "3",
     *             "start_at": "",
     *             "end_at": "",
     *             "is_weekend": 0
     *         },
     *         {
     *             "day": "4",
     *             "start_at": "",
     *             "end_at": "",
     *             "is_weekend": 0
     *         },
     *         {
     *             "day": "5",
     *             "start_at": "",
     *             "end_at": "",
     *             "is_weekend": 0
     *         },
     *         {
     *             "day": "6",
     *             "start_at": "",
     *             "end_at": "",
     *             "is_weekend": 0
     *         }
     *     }),

     *     @OA\Property(property="start_date", type="string", format="date", example="2023-11-16"),
     *     @OA\Property(property="end_date", type="string", format="date", example=""),
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

    public function updateWorkShift(WorkShiftUpdateRequest $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            return DB::transaction(function () use ($request) {

                if (!$request->user()->hasPermissionTo('work_shift_update')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }



                $request_data = $request->validated();
                if (empty($request_data['departments'])) {
                    $request_data['departments'] = [Department::where("business_id", auth()->user()->business_id)->whereNull("parent_id")->first()->id];
                }




                if ($request_data["type"] !== "flexible" && !empty(env("MATCH_BUSINESS_SCHEDULE")) && false) {
                    $check_work_shift_details =  $this->checkWorkShiftDetails($request_data['details']);
                    if (!$check_work_shift_details["ok"]) {

                        throw new Exception(json_encode($check_work_shift_details["error"]), $check_work_shift_details["status"]);
                    }
                } else if ($request_data["type"] == "flexible") {
                    $this->isModuleEnabled("flexible_shifts");
                }





                $work_shift_query_params = [
                    "id" => $request_data["id"],
                ];

                $work_shift_prev = WorkShift::where($work_shift_query_params)->first();

                $work_shift  =  tap(WorkShift::where($work_shift_query_params))->update(
                    collect($request_data)->only([
                        'name',
                        'type',
                        "description",

                        'is_personal',
                        'break_type',
                        'break_hours',

                        // 'start_date',
                        // 'end_date',
                        // "is_active",
                        // "business_id",
                        // "created_by"

                    ])->toArray()
                )
                    // ->with("somthing")

                    ->first();

                if (!$work_shift) {
                    return response()->json([
                        "message" => "something went wrong."
                    ], 500);
                }

                // $work_shift->departments()->delete();
                // $work_shift->departments()->sync($request_data['departments'], []);

                // $work_shift->users()->delete();
                // $work_shift->users()->sync($request_data['users'], []);
                $work_shift->departments()->sync($request_data['departments']);
                $work_shift->work_locations()->sync($request_data['work_locations']);
                $work_shift_prev_details  =  ($work_shift_prev->details)->toArray();

                $work_shift->details()->delete();
                $work_shift->details()->createMany($request_data['details']);



                $fields_to_check = [
                    // 'name',
                    'type',
                    // "description",
                    'is_personal',
                    'break_type',
                    'break_hours'
                ];

                $fields_changed = false; // Initialize to false
                foreach ($fields_to_check as $field) {
                    $value1 = $work_shift_prev->$field;
                    $value2 = $work_shift->$field;

                    if ($value1 !== $value2) {
                        $fields_changed = true;
                        break;
                    }
                }

                if (!$fields_changed) {
                    $fields_to_check = [
                        'work_shift_id',
                        'day',
                        "start_at",
                        'end_at',
                        'is_weekend',
                    ];
                    $fields_changed = false; // Initialize to false
                    foreach ($fields_to_check as $field) {

                        foreach ($work_shift_prev_details as $key => $prev_detail) {
                            $value1 = $prev_detail[$field];
                            $value2 = $work_shift->details[$key]->$field;

                            if ($value1 != $value2) {
                                $fields_changed = true;
                                break 2;
                            }
                        }
                    }
                }


                $work_shift_histories = WorkShiftHistory::where([
                    "work_shift_id" => $work_shift->id
                ])
                    ->get();

                if (
                    $fields_changed
                ) {
                    $attendance_exists = Attendance::whereIn("work_shift_history_id", $work_shift_histories->pluck("id")->toArray())
                        ->exists();

                    if ($attendance_exists) {
                        throw new Exception("Some attendances exist for this work shift. You cannot delete it. Please create a new one instead.", 409);
                    }
                }






                if (false) {
                    if (
                        $fields_changed
                    ) {

                        WorkShiftHistory::where([
                            "to_date" => NULL
                        ])
                            ->where("work_shift_id", $work_shift_prev->id)
                            // ->whereHas('users',function($query) use($work_shift_prev)  {
                            //     $query->whereIn("users.id",$work_shift_prev->users()->pluck("users.id"));
                            // })
                            ->update([
                                "to_date" => now()
                            ]);

                        $last_inactive_date = WorkShiftHistory::where("work_shift_id", $work_shift->id)
                        ->orderByDesc("work_shift_histories.id")
                            ->first();

                        $employee_work_shift_history_data = $work_shift->toArray();
                        $employee_work_shift_history_data["work_shift_id"] = $work_shift->id;
                        $employee_work_shift_history_data["from_date"] = $last_inactive_date->to_date;
                        $employee_work_shift_history_data["to_date"] = NULL;
                        $employee_work_shift_history =  WorkShiftHistory::create($employee_work_shift_history_data);
                        $employee_work_shift_history->details()->createMany($request_data['details']);
                        $employee_work_shift_history->departments()->sync($request_data['departments']);



                        //  $employee_work_shift_history->users()->sync($work_shift->users()->pluck("id"));

                        $user_ids = $work_shift->users()->pluck('users.id')->toArray();

                        // Define the additional pivot data for each user
                        $pivot_data = collect($user_ids)->mapWithKeys(function ($user_id) {
                            return [$user_id => ['from_date' => now(), 'to_date' => null]];
                        });

                        $employee_work_shift_history->users()->sync($pivot_data);
                    }
                }






                $affected_user_ids = [];
                if (
                    $fields_changed
                ) {
                    $work_shift_histories_after =  WorkShiftHistory::where("work_shift_id", $work_shift->id)
                        ->where(function ($query) use ($request_data) {
                            $query->whereDate("from_date", ">", today())
                                ->whereHas("users", function ($query) {
                                    $query->where("employee_user_work_shift_histories.from_date", ">", today());
                                });
                        })
                        ->orderByDesc("work_shift_histories.id")
                        ->get();

                    foreach ($work_shift_histories_after as $work_shift_history_after) {
                        $affected_user_ids[] = $work_shift_history_after->user_id;
                        $work_shift_history_after->fill(
                            collect($request_data)->only([
                                'name',
                                'type',
                                'description',
                                'is_personal',
                                'break_type',
                                'break_hours',
                                // 'from_date',
                                // 'to_date',
                                // "is_active",
                                // "business_id",
                                // "created_by"
                            ])->toArray()
                        )->save();
                        $work_shift_history_after->details()->delete();
                        $work_shift_history_after->details()->createMany($request_data['details']);
                    }


                    $work_shift_histories_before =  WorkShiftHistory::where("work_shift_id", $work_shift->id)
                        ->where(function ($query) use ($request_data) {
                            $query->whereDate("from_date", "<=", today())
                                ->where(function ($query) use ($request_data) {
                                    $query->whereDate("to_date", ">=", today())
                                        ->orWhereNull("to_date");
                                });
                        })

                        ->orderByDesc("work_shift_histories.id")
                        ->get();

                    foreach ($work_shift_histories_before as $work_shift_history_before) {
                        $affected_user_ids[] = $work_shift_history_before->user_id;
                        $request_data["work_shift_id"] = $work_shift->id;
                        $request_data["user_id"] = $work_shift_history_before->user_id;
                        $request_data["to_date"] = $work_shift_history_before->to_date;

                        $attendance_exists = Attendance::where([
                            "work_shift_history_id" => $work_shift_history_before->id
                        ])
                            ->exists();


                        if (!empty($attendance_exists)) {
                            $work_shift_history_before->to_date = Carbon::yesterday();
                            $work_shift_history_before->save();
                            $request_data["from_date"] = today();
                        } else {
                            $request_data["from_date"] = $work_shift_history_before->from_date;
                            $work_shift_history_before->delete();
                        }



                        $work_shift_history_current =  WorkShiftHistory::create(collect($request_data)->only([
                            'name',
                            "break_type",
                            "break_hours",
                            'type',
                            "description",

                            'is_business_default',
                            'is_personal',

                            "is_default",
                            "is_active",
                            "business_id",
                            "created_by",

                            "from_date",
                            "to_date",
                            "work_shift_id",
                            "user_id",

                        ])->toArray());

                        $work_shift_history_current->details()->createMany($request_data['details']);
                    }
                }





                $work_shift->affected_user = User::whereIn("id", $affected_user_ids)
                    ->select("id", "first_Name", "middle_Name", "last_name")
                    ->get();

                return response($work_shift, 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }


    /**
     *
     * @OA\Put(
     *      path="/v1.0/work-shifts/toggle-active",
     *      operationId="toggleActiveWorkShift",
     *      tags={"administrator.work_shift"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to toggle work shift activity",
     *      description="This method is to toggle work shift activity",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"id","first_Name","last_Name","email","password","password_confirmation","phone","address_line_1","address_line_2","country","city","postcode","role"},
     *           @OA\Property(property="id", type="string", format="number",example="1"),
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


    public function toggleActiveWorkShift(GetIdRequest $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('user_update')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $request_data = $request->validated();


            $all_manager_department_ids = $this->get_all_departments_of_manager();

            $work_shift = WorkShift::where([
                "id" => $request_data["id"],
                "business_id" => auth()->user()->business_id
            ])
                ->whereHas("departments", function ($query) use ($all_manager_department_ids) {
                    $query->whereIn("departments.id", $all_manager_department_ids);
                })


                ->first();
            if (!$work_shift) {

                return response()->json([
                    "message" => "no department found"
                ], 404);
            }




            $is_active = !$work_shift->is_active;

            if ($is_active) {
                // Assuming you have a 'Details' model for your 'details' table
                // Transform the retrieved data into the required structure using the collection
                $details = $work_shift->details->map(function ($detail) {
                    return [
                        'day' => $detail->day,
                        'is_weekend' => (bool) $detail->is_weekend,
                        'start_at' => $detail->start_at,
                        'end_at' => $detail->end_at,
                    ];
                });



                if ($work_shift->type !== "flexible" && !empty(env("MATCH_BUSINESS_SCHEDULE")) && false) {
                    $check_work_shift_details =  $this->checkWorkShiftDetails($details);
                    if (!$check_work_shift_details["ok"]) {

                        throw new Exception(json_encode([
                            "message" => "The specified work shift does not align with the allowed business hours.",
                            "details" => $check_work_shift_details["error"]
                        ]), $check_work_shift_details["status"]);
                    }
                } else {
                    $this->isModuleEnabled("flexible_shifts");
                }
            }

            $work_shift->update([
                'is_active' => $is_active
            ]);

            return response()->json(['message' => 'department status updated successfully'], 200);


            if (false) {
                if ($is_active) {

                    $last_inactive_date = WorkShiftHistory::where("work_shift_id", $work_shift->id)
                        ->orderByDesc("work_shift_histories.id")
                        ->first();


                    $employee_work_shift_history_data = $work_shift->toArray();

                    $employee_work_shift_history_data["is_active"] = $is_active;

                    $employee_work_shift_history_data["work_shift_id"] = $work_shift->id;
                    $employee_work_shift_history_data["from_date"] = $last_inactive_date->to_date;
                    $employee_work_shift_history_data["to_date"] = NULL;
                    $employee_work_shift_history =  WorkShiftHistory::create($employee_work_shift_history_data);
                    $employee_work_shift_history->details()->createMany($work_shift->details->toArray());
                    $user_ids = $work_shift->users()->pluck('users.id')->toArray();
                    $pivot_data = collect($user_ids)->mapWithKeys(function ($user_id) {
                        return [$user_id => ['from_date' => now(), 'to_date' => null]];
                    });
                    $employee_work_shift_history->users()->sync($pivot_data);

                    // $employee_work_shift_history_data = $work_shift->toArray();
                    // $employee_work_shift_history_data["work_shift_id"] = $work_shift->id;
                    // $employee_work_shift_history_data["from_date"] = now();
                    // $employee_work_shift_history_data["to_date"] = NULL;
                    // $employee_work_shift_history =  WorkShiftHistory::create($employee_work_shift_history_data);
                    // $employee_work_shift_history->details()->createMany($request_data['details']);
                    // //  $employee_work_shift_history->users()->sync($work_shift->users()->pluck("id"));

                    // $user_ids = $work_shift->users()->pluck('users.id')->toArray();

                    // // Define the additional pivot data for each user
                    // $pivot_data = collect($user_ids)->mapWithKeys(function ($user_id) {
                    // return [$user_id => ['from_date' => now(), 'to_date' => null]];
                    // });
                    // $employee_work_shift_history->users()->sync($pivot_data);
                } else {

                    WorkShiftHistory::where([
                        "to_date" => NULL
                    ])
                        ->where("work_shift_id", $work_shift->id)
                        // ->whereHas('users',function($query) use($work_shift)  {
                        //     $query->whereIn("users.id",$work_shift->users()->pluck("users.id"));
                        // })
                        ->update([
                            "to_date" => now()
                        ]);
                }
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }
    /**
     *
     * @OA\Get(
     *      path="/v1.0/work-shifts",
     *      operationId="getWorkShifts",
     *      tags={"administrator.work_shift"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *   *   *              @OA\Parameter(
     *         name="response_type",
     *         in="query",
     *         description="response_type: in pdf,csv,json",
     *         required=true,
     *  example="json"
     *      ),
     *      *   *              @OA\Parameter(
     *         name="file_name",
     *         in="query",
     *         description="file_name",
     *         required=true,
     *  example="employee"
     *      ),

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
     *
     *
     * @OA\Parameter(
     * name="name",
     * in="query",
     * description="name",
     * required=true,
     * example="name"
     * ),
     * @OA\Parameter(
     * name="description",
     * in="query",
     * description="description",
     * required=true,
     * example="description"
     * ),
     *    * @OA\Parameter(
     * name="type",
     * in="query",
     * description="type",
     * required=true,
     * example="type"
     * ),
     *
     *
     * *  @OA\Parameter(
     * name="search_key",
     * in="query",
     * description="search_key",
     * required=true,
     * example="search_key"
     * ),
     *      * *  @OA\Parameter(
     * name="is_personal",
     * in="query",
     * description="is_personal",
     * required=true,
     * example="1"
     * ),
     *
     *
     * @OA\Parameter(
     * name="order_by",
     * in="query",
     * description="order_by",
     * required=true,
     * example="ASC"
     * ),
     *

     *      summary="This method is to get work shifts  ",
     *      description="This method is to get work shifts ",
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

    public function getWorkShifts(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('work_shift_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            // $business_times =  BusinessTime::where([
            //         "is_weekend" => 1,
            //         "business_id" => auth()->user()->business_id,
            //     ])->get();

            $all_manager_department_ids = $this->get_all_departments_of_manager();

            $work_shifts_query = WorkShift::with("details", "departments", "users", "work_locations");

            $work_shifts = $this->workShiftHistoryComponent->updateWorkShiftsQuery($all_manager_department_ids, $work_shifts_query)

                ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                    return $query->orderBy("work_shifts.id", $request->order_by);
                }, function ($query) {
                    return $query->orderBy("work_shifts.id", "DESC");
                })
                ->when(!empty($request->per_page), function ($query) use ($request) {
                    return $query->paginate($request->per_page);
                }, function ($query) {
                    return $query->get();
                });


            if (!empty($request->response_type) && in_array(strtoupper($request->response_type), ['PDF', 'CSV'])) {
                if (strtoupper($request->response_type) == 'PDF') {

                    if (empty($work_shifts)) {
                        $pdf = PDF::loadView('pdf.no_data', []);
                    } else {
                        $pdf = PDF::loadView('pdf.work_shifts', ["work_shifts" => $work_shifts]);
                    }
                    return $pdf->download(((!empty($request->file_name) ? $request->file_name : 'employee') . '.pdf'));
                } elseif (strtoupper($request->response_type) === 'CSV') {

                    return Excel::download(new WorkShiftsExport($work_shifts), ((!empty($request->file_name) ? $request->file_name : 'employee') . '.csv'));
                }
            } else {
                return response()->json($work_shifts, 200);
            }


            return response()->json($work_shifts, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
    /**
     *
     * @OA\Get(
     *      path="/v2.0/work-shifts",
     *      operationId="getWorkShiftsV2",
     *      tags={"administrator.work_shift"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *   *   *              @OA\Parameter(
     *         name="response_type",
     *         in="query",
     *         description="response_type: in pdf,csv,json",
     *         required=true,
     *  example="json"
     *      ),
     *      *   *              @OA\Parameter(
     *         name="file_name",
     *         in="query",
     *         description="file_name",
     *         required=true,
     *  example="employee"
     *      ),

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
     *
     *
     * @OA\Parameter(
     * name="name",
     * in="query",
     * description="name",
     * required=true,
     * example="name"
     * ),
     * @OA\Parameter(
     * name="description",
     * in="query",
     * description="description",
     * required=true,
     * example="description"
     * ),
     *    * @OA\Parameter(
     * name="type",
     * in="query",
     * description="type",
     * required=true,
     * example="type"
     * ),
     *
     *
     * *  @OA\Parameter(
     * name="search_key",
     * in="query",
     * description="search_key",
     * required=true,
     * example="search_key"
     * ),
     *      * *  @OA\Parameter(
     * name="is_personal",
     * in="query",
     * description="is_personal",
     * required=true,
     * example="1"
     * ),
     *
     *
     * @OA\Parameter(
     * name="order_by",
     * in="query",
     * description="order_by",
     * required=true,
     * example="ASC"
     * ),
     *

     *      summary="This method is to get work shifts  ",
     *      description="This method is to get work shifts ",
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

    public function getWorkShiftsV2(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('work_shift_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }


            $all_manager_department_ids = $this->get_all_departments_of_manager();

            $work_shifts_query = WorkShift::with(

                [
                    "departments" => function ($query) {
                        $query->select(
                            'departments.id',
                            'departments.name',
                        );
                    },
                    "work_locations" => function ($query) {
                        $query->select(
                            'work_locations.id',
                            'work_locations.name',
                        );
                    },



                ]
            );

            $work_shifts = $this->workShiftHistoryComponent->updateWorkShiftsQuery($all_manager_department_ids, $work_shifts_query)

                ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                    return $query->orderBy("work_shifts.id", $request->order_by);
                }, function ($query) {
                    return $query->orderBy("work_shifts.id", "DESC");
                })
                ->select(


                    "work_shifts.id",
                    "work_shifts.name",
                    "work_shifts.type",
                    "work_shifts.break_type",
                    "work_shifts.business_id",

                    "work_shifts.description",
                    "work_shifts.is_active",
                    "work_shifts.is_business_default",
                    "work_shifts.is_default",
                    "work_shifts.is_personal",

                )
                ->when(!empty($request->per_page), function ($query) use ($request) {
                    return $query->paginate($request->per_page);
                }, function ($query) {
                    return $query->get();
                });


            if (!empty($request->response_type) && in_array(strtoupper($request->response_type), ['PDF', 'CSV'])) {
                // if (strtoupper($request->response_type) == 'PDF') {
                //     $pdf = PDF::loadView('pdf.work_shifts', ["work_shifts" => $work_shifts]);
                //     return $pdf->download(((!empty($request->file_name) ? $request->file_name : 'employee') . '.pdf'));
                // } elseif (strtoupper($request->response_type) === 'CSV') {

                //     return Excel::download(new WorkShiftsExport($work_shifts), ((!empty($request->file_name) ? $request->file_name : 'employee') . '.csv'));
                // }
            } else {
                return response()->json($work_shifts, 200);
            }




            return response()->json($work_shifts, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
    /**
     *
     * @OA\Get(
     *      path="/v1.0/work-shifts/{id}",
     *      operationId="getWorkShiftById",
     *      tags={"administrator.work_shift"},
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
     *      summary="This method is to get work shift by id",
     *      description="This method is to get work shift by id",
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


    public function getWorkShiftById($id, Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('work_shift_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  auth()->user()->business_id;

            $all_manager_department_ids = $this->get_all_departments_of_manager();

            $work_shift =  WorkShift::with("details", "departments", "users", "work_locations")
                ->where([
                    "id" => $id
                ])
                ->where(function ($query) use ($all_manager_department_ids) {
                    $query
                        ->where([
                            "work_shifts.business_id" => auth()->user()->business_id
                        ])
                        ->whereHas("departments", function ($query) use ($all_manager_department_ids) {
                            $query->whereIn("departments.id", $all_manager_department_ids);
                        })


                    ;
                })

                // ->orWhere(function ($query) {
                //     $query->where([
                //         "is_active" => 1,
                //         "business_id" => NULL,
                //         "is_default" => 1
                //     ]);
                // })
                ->first();
            if (!$work_shift) {

                return response()->json([
                    "message" => "no work shift found"
                ], 404);
            }


            return response()->json($work_shift, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }
    /**
     *
     * @OA\Get(
     *      path="/v1.0/work-shifts/get-by-user-id/{user_id}",
     *      operationId="getWorkShiftByUserId",
     *      tags={"administrator.work_shift"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="user_id",
     *         in="path",
     *         description="user_id",
     *         required=true,
     *  example="6"
     *      ),
     *      summary="This method is to get work shift by user id",
     *      description="This method is to get work shift by user id",
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


    public function getWorkShiftByUserId($user_id, Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            $user_id = intval($user_id);
            $request_user_id = auth()->user()->id;

            $hasPermission = auth()->user()->hasPermissionTo('work_shift_view');

            if ((!$hasPermission && ($request_user_id !== $user_id))) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }



            $all_manager_department_ids = $this->get_all_departments_of_manager();

            $this->validateUserQuery($user_id, $all_manager_department_ids);


            //  $business_times =    BusinessTime::where([
            //     "is_weekend" => 1,
            //     "business_id" => auth()->user()->business_id,
            // ])->get();


            $work_shift =   $this->workShiftHistoryComponent->getWorkShiftByUserId($user_id);



            return response()->json($work_shift, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }

    public function get_work_shift_detailsV3($work_shift_history, $in_date)
    {
        $day_number = Carbon::parse($in_date)->dayOfWeek;

        $work_shift_details = $work_shift_history->details->first(function ($detail) use ($day_number) {
            return $detail->day == $day_number;
        });


        return $work_shift_details;
    }

    /**
     *
     * @OA\Get(
     *      path="/v2.0/work-shifts/get-by-user-id/{user_id}",
     *      operationId="getWorkShiftByUserIdV2",
     *      tags={"administrator.work_shift"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="user_id",
     *         in="path",
     *         description="user_id",
     *         required=true,
     *  example="6"
     *      ),
     *  *              @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="start_date",
     *         required=true,
     *  example="6"
     *      ),
     *  *  *              @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="end_date",
     *         required=true,
     *  example="6"
     *      ),
     *      summary="This method is to get work shift by user id",
     *      description="This method is to get work shift by user id",
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


    public function getWorkShiftByUserIdV2($user_id, Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            $user_id = intval($user_id);
            $request_user_id = auth()->user()->id;

            $hasPermission = auth()->user()->hasPermissionTo('work_shift_view');

            if ((!$hasPermission && ($request_user_id !== $user_id))) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $start_date = request()->input("start_date");
            $end_date = request()->input("end_date");

            if (empty($start_date) || empty($end_date)) {
                return response()->json([
                    "message" => "start date and end date is required"
                ], 400);
            }

            $all_manager_department_ids = $this->get_all_departments_of_manager();

            $this->validateUserQuery($user_id, $all_manager_department_ids);

            $user = User::where([
                "id" => $user_id
            ])->first();




            $work_shift_histories =   $this->workShiftHistoryComponent->get_work_shift_histories($start_date, $end_date, $user_id, false);


            // Parse start and end dates
            $start_date = Carbon::parse($start_date);
            $end_date = Carbon::parse($end_date);

            $joining_date = Carbon::parse($user->joining_date);

            if ($joining_date->gt($end_date)) {
                return response()->json(
                    [
                        "message" => ("Employee joining date is " . $joining_date)
                    ],
                    409
                );
            }

            if ($joining_date->gt($start_date)) {
                $start_date = $joining_date;
            }


            // Create date range between start and end dates
            $date_range = $start_date->daysUntil($end_date);


            $dates = [];
            // Map date range to create attendance details
            // Iterate through the date range
            foreach ($date_range as $date) {
                // Convert Carbon date object to string for comparison
                $date = Carbon::parse($date);

                // Initialize the date data array
                $date_data = [
                    "date" => $date,
                ];

                // Check if work shift histories are available
                if (!empty($work_shift_histories)) {
                    // Find the corresponding work shift history for the given date
                    $work_shift_history = $work_shift_histories->first(function ($history) use ($date, $end_date) {
                        $fromDate = Carbon::parse($history->from_date);
                        $toDate = $history->to_date ? Carbon::parse($history->to_date) : $end_date;

                        return $date->greaterThanOrEqualTo($fromDate)
                            && ($toDate === null || $date->lessThanOrEqualTo($toDate));
                    });

                    // If a work shift history is found, get its details
                    if (!empty($work_shift_history)) {


                        $work_shift_details = $this->get_work_shift_detailsV3($work_shift_history, $date);

                        // Add work shift details to the date data array
                        $date_data["work_shift_details"]["is_weekend"] = $work_shift_details->is_weekend;
                        $date_data["work_shift_details"]["start_at"] = $work_shift_details->start_at;
                        $date_data["work_shift_details"]["end_at"] = $work_shift_details->end_at;
                        $date_data["work_shift_details"]["work_shift_id"] = $work_shift_details->work_shift_id;
                        $date_data["work_shift_details"]["day"] = $work_shift_details->day;
                        $date_data["work_shift_details"]["break_minutes"] = round($work_shift_history->break_hours * 60);

                        $date_data["work_shift_details"]["type"] = $work_shift_details->type;

                    }
                }

                // Add the date data to the dates array
                $dates[] = $date_data;
            }

            return response()->json($dates, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }






    /**
     *
     *     @OA\Delete(
     *      path="/v1.0/work-shifts/{ids}",
     *      operationId="deleteWorkShiftsByIds",
     *      tags={"administrator.work_shift"},
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
     *      summary="This method is to delete work shift by id",
     *      description="This method is to delete work shift by id",
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

    public function deleteWorkShiftsByIds(Request $request, $ids)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('work_shift_delete')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  auth()->user()->business_id;
            $idsArray = explode(',', $ids);

            $existingIds = WorkShift::where([
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


            WorkShiftHistory::where([
                "to_date" => NULL
            ])
                ->whereIn("work_shift_id", $existingIds)
                // ->whereHas('users',function($query) use($work_shift_prev)  {
                //     $query->whereIn("users.id",$work_shift_prev->users()->pluck("users.id"));
                // })
                ->update([
                    "to_date" => now()
                ]);




            WorkShift::destroy($existingIds);

            return response()->json(["message" => "data deleted sussfully", "deleted_ids" => $existingIds], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
}
