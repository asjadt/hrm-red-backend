<?php

namespace App\Http\Controllers;

use App\Exports\LeavesExport;
use App\Http\Components\AuthorizationComponent;
use App\Http\Components\DepartmentComponent;
use App\Http\Components\HolidayComponent;
use App\Http\Components\LeaveComponent;
use App\Http\Components\WorkShiftHistoryComponent;
use App\Http\Requests\LeaveApproveRequest;
use App\Http\Requests\LeaveArrearApproveRequest;
use App\Http\Requests\LeaveBypassRequest;
use App\Http\Requests\LeaveCreateRequest;
use App\Http\Requests\LeaveSelfCreateRequest;
use App\Http\Requests\LeaveUpdateRequest;
use App\Http\Requests\MultipleFileUploadRequest;
use App\Http\Utils\BasicNotificationUtil;
use App\Http\Utils\BasicUtil;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\LeaveUtil;
use App\Http\Utils\PayrunUtil;
use App\Http\Utils\UserActivityUtil;

use App\Models\Leave;
use App\Models\LeaveApproval;
use App\Models\LeaveHistory;
use App\Models\LeaveRecord;
use App\Models\LeaveRecordArrear;
use App\Models\Payroll;

use App\Models\PayrollLeaveRecord;

use App\Models\SettingLeave;
use App\Models\User;

use App\Observers\LeaveObserver;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDF;
use Maatwebsite\Excel\Facades\Excel;


class LeaveController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil, LeaveUtil, PayrunUtil, BasicNotificationUtil, BasicUtil;

    protected $authorizationComponent;
    protected $leaveComponent;
    protected $departmentComponent;
    protected $workShiftHistoryComponent;
    protected $holidayComponent;

    public function __construct(AuthorizationComponent $authorizationComponent, LeaveComponent $leaveComponent, DepartmentComponent $departmentComponent, WorkShiftHistoryComponent $workShiftHistoryComponent, HolidayComponent $holidayComponent)
    {
        $this->authorizationComponent = $authorizationComponent;
        $this->leaveComponent = $leaveComponent;
        $this->departmentComponent = $departmentComponent;
        $this->workShiftHistoryComponent = $workShiftHistoryComponent;
        $this->holidayComponent = $holidayComponent;
    }






    public function createSelfLeave(LeaveSelfCreateRequest $request)
    {
        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");


            $request_data = $request->validated();

            $request_data["user_id"] = auth()->user()->id;

            $request_data["attachments"] = $this->storeUploadedFiles($request_data["attachments"], "", "leave_attachments");
            $this->makeFilePermanent($request_data["attachments"], "");




            $processed_leave_data = $this->leaveComponent->processLeaveRequest($request_data);

            $leave =  Leave::create($processed_leave_data["leave_data"]);

            $leaveRecordsCollection = collect($processed_leave_data["leave_record_data_list"]);
            // Define chunk size (e.g., 100 records per chunk)
            $chunkSize = 1000;
            // Chunk the collection and insert each chunk
            $leaveRecordsCollection->chunk($chunkSize)->each(function ($chunk) use ($leave) {
                $leave->records()->createMany($chunk->toArray());
            });




            //  $this->leaveComponent->validateLeaveAvailability($leave);


            foreach ($leave->records as $leave_record) {
                $this->adjust_payroll_on_leave_update($leave_record, 0);
            }


            //  $leaveObserver = new LeaveObserver();
            //  $leaveObserver->create($leave);



            $leave_history_data = $leave->toArray();
            $leave_history_data['leave_id'] = $leave->id;
            $leave_history_data['actor_id'] = auth()->user()->id;
            $leave_history_data['action'] = "create";
            $leave_history_data['is_approved'] = NULL;
            $leave_history_data['leave_created_at'] = $leave->created_at;
            $leave_history_data['leave_updated_at'] = $leave->updated_at;
            $leave_history = LeaveHistory::create($leave_history_data);



            // Chunk the collection and insert each chunk
            $leaveRecordsCollection->chunk($chunkSize)->each(function ($chunk) use ($leave_history) {
                $leave_history->records()->createMany($chunk->toArray());
            });




            $this->send_notification($leave, $leave->employee, "Leave Request Taken", "create", "leave");



            // $this->moveUploadedFiles($request_data["attachments"],"leave_request_docs");



            DB::commit();

            return response($leave, 200);
        } catch (Exception $e) {
            DB::rollBack();



            try {
                $this->moveUploadedFilesBack($request_data["attachments"], "", "leave_attachments");
            } catch (Exception $innerException) {
                error_log("Failed to move leave files back: " . $innerException->getMessage());
            }






            return $this->sendError($e, 500, $request);
        }
    }


    public function createLeave(LeaveCreateRequest $request)
    {
        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            $request_data = $request->validated();


            $user_id = intval($request_data["user_id"]);

            $request_user_id = auth()->user()->id;


            if ((!auth()->user()->hasPermissionTo('leave_create') && ($request_user_id !== $user_id))) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }




            $request_data["attachments"] = $this->storeUploadedFiles($request_data["attachments"], "", "leave_attachments");

            $this->makeFilePermanent($request_data["attachments"], "");



            $processed_leave_data = $this->leaveComponent->processLeaveRequest($request_data);

            $leave =  Leave::create($processed_leave_data["leave_data"]);

            $leaveRecordsCollection = collect($processed_leave_data["leave_record_data_list"]);
            // Define chunk size (e.g., 100 records per chunk)
            $chunkSize = 1000;
            // Chunk the collection and insert each chunk
            $leaveRecordsCollection->chunk($chunkSize)->each(function ($chunk) use ($leave) {
                $leave->records()->createMany($chunk->toArray());
            });

            // $this->leaveComponent->validateLeaveAvailability($leave);





            // $leaveObserver = new LeaveObserver();
            // $leaveObserver->create($leave);

            $leave_history_data = $leave->toArray();
            $leave_history_data['leave_id'] = $leave->id;
            $leave_history_data['actor_id'] = auth()->user()->id;
            $leave_history_data['action'] = "create";
            $leave_history_data['is_approved'] = NULL;
            $leave_history_data['leave_created_at'] = $leave->created_at;
            $leave_history_data['leave_updated_at'] = $leave->updated_at;
            $leave_history = LeaveHistory::create($leave_history_data);



            // Chunk the collection and insert each chunk
            $leaveRecordsCollection->chunk($chunkSize)->each(function ($chunk) use ($leave_history) {
                $leave_history->records()->createMany($chunk->toArray());
            });



            $this->send_notification($leave, $leave->employee, "Leave Request Taken", "create", "leave");





            // $this->moveUploadedFiles($request_data["attachments"],"leave_request_docs");








            DB::commit();

            return response($leave, 200);
        } catch (Exception $e) {
            DB::rollBack();



            try {
                $this->moveUploadedFilesBack($request_data["attachments"], "", "leave_attachments");
            } catch (Exception $innerException) {
                error_log("Failed to move leave files back: " . $innerException->getMessage());
            }






            return $this->sendError($e, 500, $request);
        }
    }



    public function approveLeave(LeaveApproveRequest $request)
    {

        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");


            if (!$request->user()->hasPermissionTo('leave_approve')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $all_manager_department_ids = $this->get_all_departments_of_manager();


            $request_data = $request->validated();


            $leave = Leave::where([
                "id" => $request_data["leave_id"]
            ])
                ->where('leaves.business_id', '=', auth()->user()->business_id)
                ->whereHas("employee.department_user.department", function ($query) use ($all_manager_department_ids) {
                    $query->whereIn("departments.id", $all_manager_department_ids);
                })
                ->whereHas("employee", function ($query) {
                    $query->whereNotIn("users.id", [auth()->user()->id]);
                })

                ->first();

            if (empty($leave)) {
                throw new Exception("No leave request found", 400);
            }



            $request_data["created_by"] = $request->user()->id;

            $leave_approval =  LeaveApproval::create($request_data);
            if (!$leave_approval) {
                return response()->json([
                    "message" => "something went wrong."
                ], 500);
            }



            $this->processLeaveApproval($leave, $request_data["is_approved"]);





            $leave_history_data = $leave->toArray();
            $leave_history_data['leave_id'] = $leave->id;
            $leave_history_data['actor_id'] = auth()->user()->id;

            $leave_history_data['action'] = $request_data["is_approved"] ? "approve" : "reject";

            $leave_history_data['is_approved'] =  $request_data['is_approved'];
            $leave_history_data['leave_created_at'] = $leave->created_at;
            $leave_history_data['leave_updated_at'] = $leave->updated_at;

            $leave_history = LeaveHistory::create($leave_history_data);



            $chunkSize = 1000;

            // Fetch the records with the necessary fields
            $leave_records = $leave->records()->get(['date', 'start_time', 'end_time', 'capacity_hours', 'leave_hours']);

            // Chunk the records and insert each chunk
            $leave_records->chunk($chunkSize, function ($chunk) use ($leave_history) {
                // Convert the chunk to an array
                $data = $chunk->toArray();

                // Bulk insert the chunk data
                $leave_history->records()->createMany($data);
            });



            foreach ($leave->records as $leave_record) {
                $this->adjust_payroll_on_leave_update($leave_record, $request_data["add_in_next_payroll"]);
            }

            if (!empty($request_data["add_in_next_payroll"]) && ($leave->status ==
                "approved")) {
                LeaveRecordArrear::whereHas("leave_record", function ($query) use ($leave) {
                        $query
                            ->whereIn("leave_records.id", $leave->records()->pluck("leave_records.id"));
                    })
                    ->update(["status" => "approved"]);
            }


            if ($request_data["is_approved"]) {
                $this->send_notification($leave, $leave->employee, "Leave Request Approved", "approve", "leave");
            } else {
                $this->send_notification($leave, $leave->employee, "Leave Request Rejected", "reject", "leave");
            }


            DB::commit();
            return response($leave_approval, 201);
        } catch (Exception $e) {
            DB::rollBack();
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }




    public function bypassLeave(LeaveBypassRequest $request)
    {

        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            if (!$request->user()->hasPermissionTo('leave_approve')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $request_data = $request->validated();
            $request_data["created_by"] = $request->user()->id;

            $setting_leave = SettingLeave::where([
                "business_id" => auth()->user()->business_id,
                "is_default" => 0
            ])->first();

            if (!$setting_leave->allow_bypass) {

                return response([
                    "message" => "bypass not allowed"
                ], 400);
            }

            $leave = Leave::where([
                "id" => $request_data["leave_id"],
                "business_id" => auth()->user()->business_id
            ])
                ->first();

            if (!$leave) {

                return response([
                    "message" => "no leave found"
                ], 400);
            }
            $leave->status = "approved";
            $leave->save();


            foreach ($leave->records as $leave_record) {
                $this->adjust_payroll_on_leave_update($leave_record, $request_data["add_in_next_payroll"]);
            }

            $leave_history_data = $leave->toArray();
            $leave_history_data['leave_id'] = $leave->id;
            $leave_history_data['actor_id'] = auth()->user()->id;
            $leave_history_data['action'] = "bypass";
            $leave_history_data['is_approved'] = NULL;
            $leave_history_data['leave_created_at'] = $leave->created_at;
            $leave_history_data['leave_updated_at'] = $leave->updated_at;


            $this->send_notification($leave, $leave->employee, "Leave Request Approved", "approve", "leave");

            DB::commit();
            return response($leave, 200);
        } catch (Exception $e) {
            DB::rollBack();
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }


    public function approveLeaveRecordArrear(LeaveArrearApproveRequest $request)
    {

        DB::beginTransaction();
        try {

            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            // Check permission to approve attendance
            if (!$request->user()->hasPermissionTo("leave_approve")) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            // Extract data
            $request_data = $request->validated();

            foreach ($request_data["leave_record_ids"] as $leave_record_id) {
                $leave_record_arrear = LeaveRecordArrear::where([
                    "leave_record_id" => $leave_record_id
                ])
                    ->first();

                if ($leave_record_arrear) {
                    if ($leave_record_arrear->status == "pending_approval") {
                        $leave_record_arrear->status = "approved";
                        $leave_record_arrear->save();
                    }
                }
            }




            DB::commit();
            return response($leave_record_arrear, 200);
        } catch (Exception $e) {
            DB::rollBack();
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }




    public function updateLeave(LeaveUpdateRequest $request)
    {

        DB::beginTransaction();
        try {

            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('leave_update')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;
            $request_data = $request->validated();
            $request_data["attachments"] = $this->storeUploadedFiles($request_data["attachments"], "", "leave_attachments");
            $this->makeFilePermanent($request_data["attachments"], "");

            $processed_leave_data = $this->leaveComponent->processLeaveRequest($request_data);

            $leave_query_params = [
                "id" => $request_data["id"],
                "business_id" => $business_id
            ];

            $leave  =  tap(Leave::where($leave_query_params))->update(
                collect($processed_leave_data["leave_data"])->only([
                    'leave_duration',
                    'day_type',
                    'leave_type_id',
                    'user_id',
                    'date',
                    'note',
                    'start_date',
                    'end_date',

                    'attachments',
                    "hourly_rate"
                    // "is_active",
                    // "business_id",
                    // "created_by"

                ])->toArray()
            )
                // ->with("somthing")

                ->first();
            if (!$leave) {
                return response()->json([
                    "message" => "something went wrong."
                ], 500);
            }


            // Get the IDs of existing leave records
            $existingRecordIds = $leave->records()->pluck('id');
            $existingRecordIdsArray = $existingRecordIds->toArray();

            // Delete records that don't exist in the new data
            $recordsToDelete = array_diff($existingRecordIdsArray, array_column($processed_leave_data["leave_record_data_list"], 'id'));


            $recordDataList = collect($processed_leave_data["leave_record_data_list"]);


            // Separate collections for updates and inserts
            $leaveRecordsToUpdate = collect();
            $leaveRecordsToInsert = collect();

            // Partition records into updates and inserts
            $recordDataList->each(function ($recordData) use ($leave, $existingRecordIds, &$leaveRecordsToUpdate, &$leaveRecordsToInsert) {
                $recordData["leave_id"] = $leave->id;
                $record = $existingRecordIds->contains($recordData['id']);
                if ($record) {
                    // Add to update collection
                    $leaveRecordsToUpdate->push($recordData);
                } else {
                    // Add to insert collection
                    $leaveRecordsToInsert->push($recordData);
                }
            });

            // Perform bulk updates using updateOrCreate
            $leaveRecordsToUpdate->each(function ($data) use ($leave) {
                LeaveRecord::where("id", $data["id"])
                    ->where("leave_id", $leave->id)
                    ->update(collect($data)->only([
                        'date',
                        'start_time',
                        'end_time',
                        "capacity_hours",
                        "leave_hours",
                    ])->toArray());
            });

            // Perform bulk inserts using insert method for efficiency
            if ($leaveRecordsToInsert->isNotEmpty()) {
                $chunkSize = 1000;
                $leaveRecordsToInsert->chunk($chunkSize)->each(function ($chunk) use ($leave) {
                    $leave->records()->createMany($chunk->toArray());
                });
            }






            // $this->leaveComponent->validateLeaveAvailability($leave);


            $payrolls = Payroll::whereHas("payroll_leave_records", function ($query) use ($recordsToDelete) {
                $query->whereIn("payroll_leave_records.leave_record_id", $recordsToDelete);
            })->get();

            PayrollLeaveRecord::whereIn("leave_record_id", $recordsToDelete)
                ->delete();

            $this->recalculate_payrolls($payrolls);


            $leave->records()->whereIn('id', $recordsToDelete)->delete();



            foreach ($leave->records as $leave_record) {
                $this->adjust_payroll_on_leave_update($leave_record, 0);
            }



            $leave_history_data = $leave->toArray();
            $leave_history_data['leave_id'] = $leave->id;
            $leave_history_data['actor_id'] = auth()->user()->id;
            $leave_history_data['action'] = "update";
            $leave_history_data['is_approved'] = NULL;
            $leave_history_data['leave_created_at'] = $leave->created_at;
            $leave_history_data['leave_updated_at'] = $leave->updated_at;
            $leave_history = LeaveHistory::create($leave_history_data);

            $leave_record_history = $leave->records->toArray();
            $leave_record_history["leave_id"] = $leave_history->id;


            // Perform bulk inserts using insert method for efficiency
            if ($recordDataList->isNotEmpty()) {
                $chunkSize = 1000;
                $recordDataList->chunk($chunkSize)->each(function ($chunk) use ($leave_history) {
                    $leave_history->records()->createMany($chunk->toArray());
                });
            }


            $this->send_notification($leave, $leave->employee, "Leave Request Updated", "update", "leave");


            // $this->moveUploadedFiles($request_data["attachments"],"leave_request_docs");

            DB::commit();
            return response($leave, 201);
        } catch (Exception $e) {
            DB::rollBack();
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }


    public function getLeaves(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('leave_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $all_manager_department_ids = $this->departmentComponent->get_all_departments_of_manager();


            $business_id =  $request->user()->business_id;


            $leavesQuery = Leave::where(
                [
                    "leaves.business_id" => $business_id
                ]
            );


            $leavesQuery =   $this->leaveComponent->updateLeavesQuery($all_manager_department_ids, $leavesQuery);

            $leaves = $this->retrieveData($leavesQuery, "leaves.id");



            foreach ($leaves as $leave) {
                $leave->total_leave_hours = $leave->records->sum(function ($record) {
                    $startTime = Carbon::parse($record->start_time);
                    $endTime = Carbon::parse($record->end_time);
                    return $startTime->diffInHours($endTime);
                });
            }

            if (!empty($request->response_type) && in_array(strtoupper($request->response_type), ['PDF', 'CSV'])) {
                if (strtoupper($request->response_type) == 'PDF') {
                    $pdf = PDF::loadView('pdf.leaves', ["leaves" => $leaves]);
                    return $pdf->download(((!empty($request->file_name) ? $request->file_name : 'employee') . '.pdf'));
                } elseif (strtoupper($request->response_type) === 'CSV') {

                    return Excel::download(new LeavesExport($leaves), ((!empty($request->file_name) ? $request->file_name : 'leave') . '.csv'));
                }
            } else {
                return response()->json($leaves, 200);
            }
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }


    public function getLeaveArrears(Request $request)
    {
        try {

            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('leave_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $all_manager_department_ids = $this->departmentComponent->get_all_departments_of_manager();







            $all_manager_department_ids = $this->departmentComponent->get_all_departments_of_manager();


            $leavesQuery =  Leave::with([
                "employee" => function ($query) {
                    $query->select(
                        'users.id',
                        'users.first_Name',
                        'users.middle_Name',
                        'users.last_Name',
                        'users.image'
                    );
                },
                "employee.departments" => function ($query) {
                    // You can select specific fields from the departments table if needed
                    $query->select(
                        'departments.id',
                        'departments.name',
                        "departments.description"
                    );
                },
                "leave_type" => function ($query) {
                    $query->select(
                        'setting_leave_types.id',
                        'setting_leave_types.name',
                        'setting_leave_types.type',
                        'setting_leave_types.amount',

                    );
                },

            ])
                ->when(
                    !empty($request->arrear_status),
                    function ($query) use ($request) {
                        $query->whereHas("records.arrear", function ($query) use ($request) {
                            $query
                                ->where(
                                    "leave_record_arrears.status",
                                    $request->arrear_status
                                );
                        });
                    },
                    function ($query) use ($request) {
                        $query->whereHas("records.arrear", function ($query) use ($request) {
                            $query
                                ->whereNotNull(
                                    "leave_record_arrears.status"
                                );
                        });
                    }


                );


            $leavesQuery =   $this->leaveComponent->updateLeavesQuery($all_manager_department_ids, $leavesQuery);

            $leaves = $this->retrieveData($leavesQuery, "leaves.id");













            foreach ($leaves as $leave) {
                $leave->total_leave_hours = $leave->records->sum(function ($record) {
                    $startTime = Carbon::parse($record->start_time);
                    $endTime = Carbon::parse($record->end_time);
                    return $startTime->diffInHours($endTime);
                });
            }
            $data["data"] = $leaves;


            $data["data_highlights"] = [];

            $data["data_highlights"]["employees_on_leave"] = $leaves->count();

            $data["data_highlights"]["total_leave_hours"] = $leaves->reduce(function ($carry, $leave) {
                return $carry + $leave->records->sum(function ($record) {
                    $startTime = \Carbon\Carbon::parse($record->start_time);
                    $endTime = \Carbon\Carbon::parse($record->end_time);

                    return $startTime->diffInHours($endTime);
                });
            }, 0);

            $data["data_highlights"]["single_day_leaves"] = $leaves->filter(function ($leave) {
                return $leave->leave_duration == "single_day";
            })->count();

            $data["data_highlights"]["multiple_day_leaves"] = $leaves->filter(function ($leave) {
                return $leave->leave_duration == "multiple_day";
            })->count();


            if (!empty($request->response_type) && in_array(strtoupper($request->response_type), ['PDF', 'CSV'])) {
                if (strtoupper($request->response_type) == 'PDF') {
                    $pdf = PDF::loadView('pdf.leaves', ["leaves" => $leaves]);
                    return $pdf->download(((!empty($request->file_name) ? $request->file_name : 'employee') . '.pdf'));
                } elseif (strtoupper($request->response_type) === 'CSV') {
                    return Excel::download(new LeavesExport($leaves), ((!empty($request->file_name) ? $request->file_name : 'leave') . '.csv'));
                }
            } else {
                return response()->json($data, 200);
            }


            return response()->json($data, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }



    public function getLeavesV2(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('leave_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $all_manager_department_ids = $this->departmentComponent->get_all_departments_of_manager();


            $leavesQuery =  Leave::with([
                "employee" => function ($query) {
                    $query->select(
                        'users.id',
                        'users.first_Name',
                        'users.middle_Name',
                        'users.last_Name',
                        'users.image'
                    );
                },
                "employee.departments" => function ($query) {
                    // You can select specific fields from the departments table if needed
                    $query->select(
                        'departments.id',
                        'departments.name',
                        "departments.description"
                    );
                },
                "leave_type" => function ($query) {
                    $query->select(
                        'setting_leave_types.id',
                        'setting_leave_types.name',
                        'setting_leave_types.type',
                        'setting_leave_types.amount',

                    );
                },

            ]);


            $leavesQuery =   $this->leaveComponent->updateLeavesQuery($all_manager_department_ids, $leavesQuery);

            $leaves = $this->retrieveData($leavesQuery, "leaves.id");





            foreach ($leaves as $leave) {
                $leave->total_leave_hours = $leave->records->sum(function ($record) {
                    $startTime = Carbon::parse($record->start_time);
                    $endTime = Carbon::parse($record->end_time);
                    return $startTime->diffInHours($endTime);
                });
            }
            $data["data"] = $leaves;


            $data["data_highlights"] = [];

            $data["data_highlights"]["employees_on_leave"] = $leaves->count();

            $data["data_highlights"]["total_leave_hours"] = $leaves->reduce(function ($carry, $leave) {
                return $carry + $leave->records->sum(function ($record) {
                    $startTime = \Carbon\Carbon::parse($record->start_time);
                    $endTime = \Carbon\Carbon::parse($record->end_time);

                    return $startTime->diffInHours($endTime);
                });
            }, 0);

            $data["data_highlights"]["single_day_leaves"] = $leaves->filter(function ($leave) {
                return $leave->leave_duration == "single_day";
            })->count();

            $data["data_highlights"]["multiple_day_leaves"] = $leaves->filter(function ($leave) {
                return $leave->leave_duration == "multiple_day";
            })->count();


            if (!empty($request->response_type) && in_array(strtoupper($request->response_type), ['PDF', 'CSV'])) {
                if (strtoupper($request->response_type) == 'PDF') {
                    $pdf = PDF::loadView('pdf.leaves', ["leaves" => $leaves]);
                    return $pdf->download(((!empty($request->file_name) ? $request->file_name : 'employee') . '.pdf'));
                } elseif (strtoupper($request->response_type) === 'CSV') {
                    return Excel::download(new LeavesExport($leaves), ((!empty($request->file_name) ? $request->file_name : 'leave') . '.csv'));
                }
            } else {
                return response()->json($data, 200);
            }


            return response()->json($data, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }


    public function getLeavesV3(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            if (!$request->user()->hasPermissionTo('leave_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $all_manager_department_ids = $this->departmentComponent->get_all_departments_of_manager();
            $business_id =  $request->user()->business_id;
            $employees = User::with(
                [
                    'leaves' => function ($query) use ($request) {
                        $query->when(!empty($request->start_date), function ($query) use ($request) {
                            return $query->where('start_date', '>=', ($request->start_date . ' 00:00:00'));
                        })
                            ->when(!empty($request->end_date), function ($query) use ($request) {
                                return $query->where('end_date', '<=', ($request->end_date . ' 23:59:59'));
                            });
                    },
                    'departments' => function ($query) use ($request) {
                        $query->select("departments.name");
                    },




                ]
            )

                ->when(!empty($all_manager_department_ids), function ($query) use ($all_manager_department_ids) {
                    $query->whereHas("department_user.department", function ($query) use ($all_manager_department_ids) {
                        $query->whereIn("departments.id", $all_manager_department_ids);
                    })
                        // ->whereNotIn('users.id', [auth()->user()->id])
                    ;
                }, function ($query) {
                    $query->where('users.id', auth()->user()->id);
                })

                ->whereHas("leaves", function ($q) use ($request) {
                    $q->whereNotNull("user_id")
                        ->when(!empty($request->user_id), function ($q) use ($request) {
                            $q->where('user_id', $request->user_id);
                        })
                        ->when(!empty($request->start_date), function ($q) use ($request) {
                            $q->where('start_date', '>=', $request->start_date . ' 00:00:00');
                        })
                        ->when(!empty($request->end_date), function ($q) use ($request) {
                            $q->where('end_date', '<=', ($request->end_date . ' 23:59:59'));
                        });
                })
                ->where(
                    [
                        "users.business_id" => $business_id
                    ]
                )
                ->when(!empty($request->status), function ($query) use ($request) {
                    return $query->where('leaves.status', $request->status);
                })
                ->when(!empty($request->search_key), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        $term = $request->search_key;
                        // $query->where("leaves.name", "like", "%" . $term . "%")
                        //     ->orWhere("leaves.description", "like", "%" . $term . "%");
                    });
                })
                //    ->when(!empty($request->product_category_id), function ($query) use ($request) {
                //        return $query->where('product_category_id', $request->product_category_id);
                //    })
                ->when(!empty($request->user_id), function ($query) use ($request) {
                    return $query->whereHas("leaves", function ($q) use ($request) {
                        $q->where('user_id', $request->user_id);
                    });
                })
                // ->when(empty($request->user_id), function ($query) use ($request) {
                //     $query->whereNotIn("users.id", [auth()->user()->id]);
                // })

                ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                    return $query->orderBy("users.id", $request->order_by);
                }, function ($query) {
                    return $query->orderBy("users.id", "DESC");
                })
                ->select(
                    "users.id",
                    "users.first_Name",
                    "users.middle_Name",
                    "users.last_Name",
                    "users.image",
                )
                ->when(!empty($request->per_page), function ($query) use ($request) {
                    return $query->paginate($request->per_page);
                }, function ($query) {
                    return $query->get();
                });


            if ((!empty($request->start_date) && !empty($request->end_date))) {

                $startDate = Carbon::parse(($request->start_date . ' 00:00:00'));
                $endDate = Carbon::parse(($request->end_date . ' 23:59:59'));
                $dateArray = [];



                for ($date = $startDate; $date->lte($endDate); $date->addDay()) {
                    $dateArray[] = $date->format('Y-m-d');
                }

                // while ($startDate->lte($endDate)) {
                //     $dateArray[] = $startDate->toDateString();
                //     $startDate->addDay();
                // }




                $employees->each(function ($employee) use ($dateArray) {
                    // Get leaves for the current employee


                    $total_leave_hours = 0;

                    $employee->datewise_leave = collect($dateArray)->map(function ($date) use ($employee, &$total_leave_hours) {


                        $leave_record = LeaveRecord::whereHas(
                            "leave.employee",
                            function ($query) use ($employee, $date) {
                                $query->where([
                                    "users.id" => $employee->id,
                                    "leave_records.date" => $date
                                ]);
                            }
                        )
                            ->first();

                        $leave_hours = 0;
                        if ($leave_record) {
                            $startTime = Carbon::parse($leave_record->start_time);
                            $endTime = Carbon::parse($leave_record->end_time);
                            $leave_hours = $startTime->diffInHours($endTime);
                            $total_leave_hours += $leave_hours;
                        }

                        if ($leave_record) {
                            return [
                                'date' => Carbon::parse($date)->format("d-m-Y"),
                                'is_on_leave' => $leave_record ? 1 : 0,
                                'leave_hours' => $leave_hours,

                            ];
                        }
                        return null;
                    })->filter()->values();

                    $employee->total_leave_hours = $total_leave_hours;
                    $employee->unsetRelation('leaves');
                    return $employee;
                });
            }







            return response()->json($employees, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }




    public function getLeavesV4(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('leave_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $data = $this->leaveComponent->getLeaveV4Func();


            return response()->json($data, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }





    public function getLeaveById($id, Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('leave_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $all_manager_department_ids = $this->departmentComponent->get_all_departments_of_manager();
            $business_id =  $request->user()->business_id;
            $leave =  Leave::where([
                "id" => $id,
                "business_id" => $business_id
            ])
                ->whereHas("employee.department_user.department", function ($query) use ($all_manager_department_ids) {
                    $query->whereIn("departments.id", $all_manager_department_ids);
                })
                ->first();
            if (!$leave) {

                return response()->json([
                    "message" => "no data found"
                ], 404);
            }

            return response()->json($leave, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }


    public function getLeaveCurrentHourlyRate(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            $user_id = intval($request->user_id);
            $request_user_id = auth()->user()->id;
            if (!$request->user()->hasPermissionTo('leave_create') && ($request_user_id !== $user_id)) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $all_manager_department_ids = $this->get_all_departments_of_manager();
            $user =    $this->validateUserQuery($user_id, $all_manager_department_ids);


            $salary_info = $this->get_salary_info((!empty($user_id) ? $user_id : auth()->user()->id), (!empty($request->date) ? $request->date : today()));
            $salary_info["hourly_salary"] =  number_format($salary_info["hourly_salary"], 2);
            $salary_info["overtime_salary_per_hour"] = number_format($salary_info["overtime_salary_per_hour"], 2);
            $salary_info["holiday_considered_hours"] = number_format($salary_info["holiday_considered_hours"], 2);


            return response()->json($salary_info, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }

 

    public function deleteLeavesByIds(Request $request, $ids)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('leave_delete')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $all_manager_department_ids = $this->departmentComponent->get_all_departments_of_manager();
            $business_id =  $request->user()->business_id;
            $idsArray = explode(',', $ids);
            $existingIds = Leave::where([
                "business_id" => $business_id
            ])
                ->whereHas("employee.department_user.department", function ($query) use ($all_manager_department_ids) {
                    $query->whereIn("departments.id", $all_manager_department_ids);
                })

                ->whereHas("employee", function ($query) {
                    $query->whereNotIn("users.id", [auth()->user()->id]);
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



            $leaves =  Leave::whereIn("id", $existingIds)->get();

            foreach ($leaves as $leave) {
                $leave_history_data = $leave->toArray();
                $leave_history_data['leave_id'] = $leave->id;
                $leave_history_data['actor_id'] = auth()->user()->id;
                $leave_history_data['action'] = "delete";
                $leave_history_data['is_approved'] = NULL;
                $leave_history_data['leave_created_at'] = $leave->created_at;
                $leave_history_data['leave_updated_at'] = $leave->updated_at;
            }





            $recordsToDelete = LeaveRecord::whereHas("leave", function ($query) use ($existingIds) {
                $query->whereIn("leaves.id", $existingIds);
            })
                ->pluck("leave_records.id");

            $payrolls = Payroll::whereHas("payroll_leave_records", function ($query) use ($recordsToDelete) {
                $query->whereIn("payroll_leave_records.leave_record_id", $recordsToDelete);
            })->get();

            PayrollLeaveRecord::whereIn("leave_record_id", $recordsToDelete)
                ->delete();
            $this->recalculate_payrolls($payrolls);

            Leave::destroy($existingIds);
            $this->send_notification($leaves, $leaves->first()->employee, "Leave Request Deleted", "delete", "leave");

            return response()->json(["message" => "data deleted sussfully", "deleted_ids" => $existingIds], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
}
