<?php

namespace App\Http\Controllers;

use App\Http\Utils\AttendanceUtil;
use App\Http\Utils\BasicUtil;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\EmployeePassportDetailHistory;
use App\Models\EmployeePensionHistory;
use App\Models\EmployeeRightToWorkHistory;
use App\Models\EmployeeSponsorshipHistory;
use App\Models\EmployeeUserWorkShiftHistory;
use App\Models\EmployeeVisaDetailHistory;
use App\Models\EmploymentStatus;
use App\Models\Holiday;
use App\Models\JobListing;
use App\Models\LeaveRecord;
use App\Models\User;
use App\Models\WorkShiftDetailHistory;
use App\Models\WorkShiftHistory;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardManagementControllerV2 extends Controller
{
    use ErrorUtil, BusinessUtil, UserActivityUtil, BasicUtil, AttendanceUtil;


    //   function getLast12MonthsDates() {
    //     $dates = [];
    //     $currentDate = Carbon::now();

    //     // Start from the previous month to avoid adding the current month twice
    //     $currentDate->subMonth();

    //     for ($i = 0; $i < 12; $i++) {
    //         $startOfMonth = $currentDate->copy()->startOfMonth()->toDateString();
    //         $endOfMonth = $currentDate->copy()->endOfMonth()->toDateString();
    //         $monthName = $currentDate->copy()->format('F');

    //         $dates[] = [
    //             'month' => $monthName,
    //             'start_date' => $startOfMonth,
    //             'end_date' => $endOfMonth,
    //         ];

    //         // Move to the previous month
    //         $currentDate->subMonth();
    //     }

    //     return $dates;
    // }



    public function getLeaveData($data_query, $start_date = "", $end_date = "")
    {
        $updated_data_query_old = clone $data_query;
        $updated_data_query = $updated_data_query_old->when(
            (!empty($start_date) && !empty($end_date)),
            function ($query) use ($start_date, $end_date) {
                $query->whereBetween("leave_records.date", [$start_date, $end_date . ' 23:59:59']);
            }
        );

        $data["total_requested"] = clone $updated_data_query;
        $data["total_requested"] = $data["total_requested"]
            ->count();




        $data["total_pending"] = clone $updated_data_query;
        $data["total_pending"] = $data["total_pending"]
            ->whereHas("leave", function ($query) {
                $query->where([
                    "leaves.status" => "pending_approval"
                ]);
            })
            ->count();

        $data["total_approved"] = clone $updated_data_query;
        $data["total_approved"] = $data["total_approved"]
            ->whereHas("leave", function ($query) {
                $query->where([
                    "leaves.status" => "approved"
                ]);
            })
            ->count();

        $data["total_rejected"] = clone $updated_data_query;
        $data["total_rejected"] = $data["total_rejected"]
            ->whereHas("leave", function ($query) {
                $query->where([
                    "leaves.status" => "rejected"
                ]);
            })


            ->count();




        return $data;
    }


    public function getHolidayData($data_query, $start_date = "", $end_date = "")
    {
        $updated_data_query_old = clone $data_query;
        $updated_data_query = $updated_data_query_old->when(
            (!empty($start_date) && !empty($end_date)),
            function ($query) use ($start_date, $end_date) {
                $query->where("holidays.end_date", ">=", $start_date)
                    ->where("holidays.start_date", "<=", ($end_date . ' 23:59:59'));
            }
        );

        $data["total_requested"] = clone $updated_data_query;
        $data["total_requested"] = $data["total_requested"]
            ->count();




        $data["total_pending"] = clone $updated_data_query;
        $data["total_pending"] = $data["total_pending"]
            ->where([
                "holidays.status" => "pending_approval"
            ])
            ->count();

        $data["total_approved"] = clone $updated_data_query;
        $data["total_approved"] = $data["total_approved"]
            ->where([
                "leaves.status" => "approved"
            ])

            ->count();

        $data["total_rejected"] = clone $updated_data_query;
        $data["total_rejected"] = $data["total_rejected"]

            ->where([
                "leaves.status" => "rejected"
            ])


            ->count();




        return $data;
    }
    public function leaves(
        $today,
        $start_date_of_next_month,
        $end_date_of_next_month,
        $start_date_of_this_month,
        $end_date_of_this_month,
        $start_date_of_previous_month,
        $end_date_of_previous_month,
        $start_date_of_next_week,
        $end_date_of_next_week,
        $start_date_of_this_week,
        $end_date_of_this_week,
        $start_date_of_previous_week,
        $end_date_of_previous_week,
        $all_manager_department_ids,
        $show_my_data = false
    ) {

        $data_query  = LeaveRecord::whereHas("leave", function ($query) use ($all_manager_department_ids) {
            $query->where([
                "leaves.business_id" => auth()->user()->business_id,
            ])->whereIn("leaves.user_id", $all_manager_department_ids);
        });


        $data["individual_total"] = $this->getLeaveData($data_query);

        if (!request()->input("year")) {
            throw new Exception("year is required", 400);
        }

        $last12MonthsDates = $this->getLast12MonthsDates(request()->input("year"));

        foreach ($last12MonthsDates as $month) {
            $leaveData =  $this->getLeaveData($data_query, $month['start_date'], $month['end_date']);
            $data["data"][] = array_merge(
                ["month" => $month['month']],
                $leaveData
            );
        }

        return $data;
    }
    public function employeeLeaves(
        $today,
        $start_date_of_next_month,
        $end_date_of_next_month,
        $start_date_of_this_month,
        $end_date_of_this_month,
        $start_date_of_previous_month,
        $end_date_of_previous_month,
        $start_date_of_next_week,
        $end_date_of_next_week,
        $start_date_of_this_week,
        $end_date_of_this_week,
        $start_date_of_previous_week,
        $end_date_of_previous_week,
        $all_manager_department_ids,
        $show_my_data = false
    ) {
        $year = request()->input("year");
        if (!$year) {
            throw new Exception("year is required", 400);
        }

        $data_query  = LeaveRecord::whereHas("leave", function ($query) {
            $query->where([
                "leaves.business_id" => auth()->user()->business_id,
            ])->whereIn("leaves.user_id", [auth()->user()->id]);
        })
            ->whereYear("leave_records.date", $year);


        //             // Clone the query if year is provided
        // $data_query_year = clone $data_query;
        // $data_query_year->whereYear("leave_records.date", $year);


        // $data["individual_total"] = $this->getLeaveData($data_query_year);

        // Clone the query if year is provided


        $data["individual_total"] = $this->getLeaveData($data_query);



        $last12MonthsDates = $this->getLast12MonthsDates(request()->input("year"));

        foreach ($last12MonthsDates as $month) {
            $leaveData =  $this->getLeaveData($data_query, $month['start_date'], $month['end_date']);
            $data["data"][] = array_merge(
                ["month" => $month['month']],
                $leaveData
            );
        }


        return $data;
    }





    public function holidays(
        $today,
        $start_date_of_next_month,
        $end_date_of_next_month,
        $start_date_of_this_month,
        $end_date_of_this_month,
        $start_date_of_previous_month,
        $end_date_of_previous_month,
        $start_date_of_next_week,
        $end_date_of_next_week,
        $start_date_of_this_week,
        $end_date_of_this_week,
        $start_date_of_previous_week,
        $end_date_of_previous_week,
        $all_manager_department_ids,
        $show_my_data = false
    ) {
        $all_user_of_manager = $this->get_all_user_of_manager($all_manager_department_ids);
        $data_query  = Holiday::where([
            "holidays.business_id" => auth()->user()->business_id,
        ])
            ->where(function ($query) use ($all_manager_department_ids, $all_user_of_manager) {
                $query->whereHas("departments", function ($query) use ($all_manager_department_ids) {
                    $query->whereIn("departments.id", $all_manager_department_ids);
                })
                    ->orWhereHas("users", function ($query) use ($all_user_of_manager) {
                        $query->whereIn(
                            "users.id",
                            $all_user_of_manager
                        );
                    })
                    ->when(auth()->user()->hasRole("business_owner"), function ($query) {
                        $query->orWhere(function ($query) {
                            $query->whereDoesntHave("users")
                                ->whereDoesntHave("departments");
                        });
                    });
            });


        $data["individual_total"] = $this->getHolidayData($data_query);

        if (!request()->input("year")) {
            throw new Exception("year is required", 400);
        }

        $last12MonthsDates = $this->getLast12MonthsDates(request()->input("year"));

        foreach ($last12MonthsDates as $month) {
            $leaveData =  $this->getLeaveData($data_query, $month['start_date'], $month['end_date']);
            $data["data"][] = array_merge(
                ["month" => $month['month']],
                $leaveData
            );
        }

        foreach ($last12MonthsDates as $month) {
            $leaveData =  $this->getHolidayData($data_query, $month['start_date'], $month['end_date']);
            $data["data"][] = array_merge(
                ["month" => $month['month']],
                $leaveData
            );
        }

        return $data;
    }


    public function leavesStructure2(
        $today,
        $start_date_of_next_month,
        $end_date_of_next_month,
        $start_date_of_this_month,
        $end_date_of_this_month,
        $start_date_of_previous_month,
        $end_date_of_previous_month,
        $start_date_of_next_week,
        $end_date_of_next_week,
        $start_date_of_this_week,
        $end_date_of_this_week,
        $start_date_of_previous_week,
        $end_date_of_previous_week,
        $all_manager_department_ids,
    ) {

        $leaves_query  = LeaveRecord::whereHas("leave", function ($query) use ($all_manager_department_ids) {
            $query->where([
                "leaves.business_id" => auth()->user()->business_id,
            ])
                ->whereIn("leaves.user_id", $all_manager_department_ids);
        });

        $leave_statuses = ['pending_approval', 'in_progress', 'approved', 'rejected'];
        foreach ($leave_statuses as $leave_status) {

            $updated_query = clone $leaves_query;
            $updated_query = $updated_query->whereHas("leave", function ($query) use ($leave_status) {
                $query->where([
                    "leaves.status" => $leave_status
                ]);
            });
            $data[($leave_status . "_leaves")]["total"] = $updated_query->count();


            $data[($leave_status . "_leaves")]["monthly"] = $this->getData(
                $updated_query,
                "date",
                [
                    "start_date" => $start_date_of_this_month,
                    "end_date" => $end_date_of_this_month,
                    "previous_start_date" => $start_date_of_previous_month,
                    "previous_end_date" => $end_date_of_previous_month,
                ]
            );

            $data[($leave_status . "_leaves")]["weekly"] = $this->getData(
                $updated_query,
                "date",
                [
                    "start_date" => $start_date_of_this_week,
                    "end_date" => $end_date_of_this_week,
                    "previous_start_date" => $start_date_of_previous_week,
                    "previous_end_date" => $end_date_of_previous_week,
                ]
            );
        }

        return $data;
    }

    public function getLeavesStructure3(
        $today,
        $start_date_of_next_month,
        $end_date_of_next_month,
        $start_date_of_this_month,
        $end_date_of_this_month,
        $start_date_of_previous_month,
        $end_date_of_previous_month,
        $start_date_of_next_week,
        $end_date_of_next_week,
        $start_date_of_this_week,
        $end_date_of_this_week,
        $start_date_of_previous_week,
        $end_date_of_previous_week,
        $all_manager_department_ids,
        $leave_status,
        $duration
    ) {


        $leave_statuses = ['pending_approval', 'in_progress', 'approved', 'rejected'];
        if (!in_array($leave_status, $leave_statuses)) {
            $error =  [
                "message" => "The given data was invalid.",
                "errors" => ["status" => ["Valid Statuses are 'pending_approval','in_progress', 'approved','rejected' "]]
            ];
            throw new Exception(json_encode($error), 422);
        }


        $leaves_query  = LeaveRecord::whereHas("leave", function ($query) use ($all_manager_department_ids) {
            $query->where([
                "leaves.business_id" => auth()->user()->business_id,
            ])
                ->whereIn("leaves.user_id", $all_manager_department_ids);
        });



        $leaves_query = $leaves_query->whereHas("leave", function ($query) use ($leave_status) {
            $query->where([
                "leaves.status" => $leave_status
            ]);
        });



        if ($duration == "total") {
            $data["total"] = $leaves_query->count();
        }

        if ($duration == "today") {
            $data["total"] = $leaves_query->where("date", today())->count();
        }

        if ($duration == "this_month") {
            $data["data"] = $this->getData(
                $leaves_query,
                "date",
                [
                    "start_date" => $start_date_of_this_month,
                    "end_date" => $end_date_of_this_month,
                    "previous_start_date" => $start_date_of_previous_month,
                    "previous_end_date" => $end_date_of_previous_month,
                ]
            );
            $data["total"] = $data["data"]["current_amount"];
        }

        if ($duration == "this_week") {
            $data["data"] = $this->getData(
                $leaves_query,
                "date",
                [
                    "start_date" => $start_date_of_this_week,
                    "end_date" => $end_date_of_this_week,
                    "previous_start_date" => $start_date_of_previous_week,
                    "previous_end_date" => $end_date_of_previous_week,
                ]
            );
            $data["total"] = $data["data"]["current_amount"];
        }









        return $data;
    }


    public function getLeavesStructure4(
        $today,
        $start_date_of_next_month,
        $end_date_of_next_month,
        $start_date_of_this_month,
        $end_date_of_this_month,
        $start_date_of_previous_month,
        $end_date_of_previous_month,
        $start_date_of_next_week,
        $end_date_of_next_week,
        $start_date_of_this_week,
        $end_date_of_this_week,
        $start_date_of_previous_week,
        $end_date_of_previous_week,
        $all_manager_department_ids,

    ) {

        $leaves_query  = LeaveRecord::whereHas("leave", function ($query) use ($all_manager_department_ids) {
            $query->where([
                "leaves.business_id" => auth()->user()->business_id,
            ])
                ->whereIn("leaves.user_id", $all_manager_department_ids);
        });
        $leave_statuses = ['pending_approval', 'in_progress', 'approved', 'rejected'];

        foreach ($leave_statuses as $leave_status) {
            $updated_query = clone $leaves_query;
            $updated_query = $updated_query->whereHas("leave", function ($query) use ($leave_status) {
                $query->where([
                    "leaves.status" => $leave_status
                ]);
            });
            $data[("total_" . $leave_status)] = $updated_query->count();
        }







        return $data;
    }


    public function getHolidaysStructure4(
        $today,
        $start_date_of_next_month,
        $end_date_of_next_month,
        $start_date_of_this_month,
        $end_date_of_this_month,
        $start_date_of_previous_month,
        $end_date_of_previous_month,
        $start_date_of_next_week,
        $end_date_of_next_week,
        $start_date_of_this_week,
        $end_date_of_this_week,
        $start_date_of_previous_week,
        $end_date_of_previous_week,
        $all_manager_department_ids,

    ) {

        $all_user_of_manager = $this->get_all_user_of_manager($all_manager_department_ids);
        $holiday_query  = Holiday::where([
            "holidays.business_id" => auth()->user()->business_id,
        ])
            ->where(function ($query) use ($all_manager_department_ids, $all_user_of_manager) {
                $query->whereHas("departments", function ($query) use ($all_manager_department_ids) {
                    $query->whereIn("departments.id", $all_manager_department_ids);
                })
                    ->orWhereHas("users", function ($query) use ($all_user_of_manager) {
                        $query->whereIn(
                            "users.id",
                            $all_user_of_manager
                        );
                    })
                    ->when(auth()->user()->hasRole("business_owner"), function ($query) {
                        $query->orWhere(function ($query) {
                            $query->whereDoesntHave("users")
                                ->whereDoesntHave("departments");
                        });
                    });
            });
        $holiday_statuses = ['pending_approval', 'in_progress', 'approved', 'rejected'];

        foreach ($holiday_statuses as $holiday_status) {
            $updated_query = clone $holiday_query;
            $updated_query = $updated_query->where([
                "holidays.status" => $holiday_status
            ]);
            $data[("total_" . $holiday_status)] = $updated_query->count();
        }







        return $data;
    }




    public function pensionsStructure2(
        $today,
        $start_date_of_next_month,
        $end_date_of_next_month,
        $start_date_of_this_month,
        $end_date_of_this_month,
        $start_date_of_previous_month,
        $end_date_of_previous_month,
        $start_date_of_next_week,
        $end_date_of_next_week,
        $start_date_of_this_week,
        $end_date_of_this_week,
        $start_date_of_previous_week,
        $end_date_of_previous_week,
        $all_manager_department_ids,
    ) {


        $issue_date_column = 'pension_enrollment_issue_date';
        $expiry_date_column = 'pension_re_enrollment_due_date';


        $employee_pension_history_ids = EmployeePensionHistory::select('id', 'user_id')
            ->whereHas("employee.department_user.department", function ($query) use ($all_manager_department_ids) {
                $query->whereIn("departments.id", $all_manager_department_ids);
            })
            ->whereHas("employee", function ($query) use ($all_manager_department_ids) {
                $query->where("users.pension_eligible", ">", 0);
            })
            ->whereNotIn('user_id', [auth()->user()->id])
            ->where($issue_date_column, '<', now())
            ->whereNotNull($expiry_date_column)
            ->groupBy('user_id')
            ->get()
            ->map(function ($record) use ($issue_date_column, $expiry_date_column) {


                $current_data = EmployeePensionHistory::where('user_id', $record->user_id)
                    ->where("pension_eligible", 1)
                    ->where($issue_date_column, '<', now())
                    ->orderByDesc("id")
                    ->first();

                if (empty($current_data)) {
                    return NULL;
                }


                return $current_data->id;
            })
            ->filter()->values();




        $pension_query  = EmployeePensionHistory::whereIn('id', $employee_pension_history_ids)->where($expiry_date_column, ">=", today());

        $pension_statuses = ["opt_in", "opt_out"];
        foreach ($pension_statuses as $pension_status) {

            $updated_query = clone $pension_query;
            $updated_query = $updated_query->where("pension_scheme_status", $pension_status);
            $data[($pension_status . "_pension")]["total"] = $updated_query->count();


            $data[($pension_status . "_pension")]["monthly"] = $this->getData(
                $updated_query,
                "pension_enrollment_issue_date",
                [
                    "start_date" => $start_date_of_this_month,
                    "end_date" => $end_date_of_this_month,
                    "previous_start_date" => $start_date_of_previous_month,
                    "previous_end_date" => $end_date_of_previous_month,
                ]
            );

            $data[($pension_status . "_pension")]["weekly"] = $this->getData(
                $updated_query,
                "pension_enrollment_issue_date",
                [
                    "start_date" => $start_date_of_this_week,
                    "end_date" => $end_date_of_this_week,
                    "previous_start_date" => $start_date_of_previous_week,
                    "previous_end_date" => $end_date_of_previous_week,
                ]
            );
        }

        return $data;
    }


    public function getPensionsStructure3(
        $today,
        $start_date_of_next_month,
        $end_date_of_next_month,
        $start_date_of_this_month,
        $end_date_of_this_month,
        $start_date_of_previous_month,
        $end_date_of_previous_month,
        $start_date_of_next_week,
        $end_date_of_next_week,
        $start_date_of_this_week,
        $end_date_of_this_week,
        $start_date_of_previous_week,
        $end_date_of_previous_week,
        $all_manager_department_ids,
        $pension_status,
        $duration
    ) {

        $pension_statuses = ["opt_in", "opt_out"];
        if (!in_array($pension_status, $pension_statuses)) {
            $error =  [
                "message" => "The given data was invalid.",
                "errors" => ["status" => ["Valid Statuses are \"\opt_in\"\, \"\opt_out\"\ "]]
            ];
            throw new Exception(json_encode($error), 422);
        }


        $issue_date_column = 'pension_enrollment_issue_date';
        $expiry_date_column = 'pension_re_enrollment_due_date';


        $employee_pension_history_ids = EmployeePensionHistory::select('id', 'user_id')
            ->whereHas("employee.department_user.department", function ($query) use ($all_manager_department_ids) {
                $query->whereIn("departments.id", $all_manager_department_ids);
            })
            ->whereHas("employee", function ($query) use ($all_manager_department_ids) {
                $query->where("users.pension_eligible", ">", 0);
            })
            ->whereNotIn('user_id', [auth()->user()->id])
            ->where($issue_date_column, '<', now())
            ->whereNotNull($expiry_date_column)
            ->groupBy('user_id')
            ->get()
            ->map(function ($record) use ($issue_date_column, $expiry_date_column) {


                $current_data = EmployeePensionHistory::where('user_id', $record->user_id)
                    ->where("pension_eligible", 1)
                    ->where($issue_date_column, '<', now())
                    ->orderByDesc("id")
                    ->first();

                if (empty($current_data)) {
                    return NULL;
                }


                return $current_data->id;
            })
            ->filter()->values();




        $pension_query  = EmployeePensionHistory::whereIn('id', $employee_pension_history_ids);




        $pension_query = $pension_query->where("pension_scheme_status", $pension_status);


        if ($duration == "total") {
            $data["total"] = $pension_query->count();
        }

        if ($duration == "today") {
            $data["total"] = $pension_query->where("pension_enrollment_issue_date", today())->count();
        }

        if ($duration == "this_month") {
            $data["data"] = $this->getData(
                $pension_query,
                "pension_enrollment_issue_date",
                [
                    "start_date" => $start_date_of_this_month,
                    "end_date" => $end_date_of_this_month,
                    "previous_start_date" => $start_date_of_previous_month,
                    "previous_end_date" => $end_date_of_previous_month,
                ]
            );
            $data["total"] = $data["data"]["current_amount"];
        }

        if ($duration == "this_week") {
            $data["data"] = $this->getData(
                $pension_query,
                "pension_enrollment_issue_date",
                [
                    "start_date" => $start_date_of_this_week,
                    "end_date" => $end_date_of_this_week,
                    "previous_start_date" => $start_date_of_previous_week,
                    "previous_end_date" => $end_date_of_previous_week,
                ]
            );
            $data["total"] = $data["data"]["current_amount"];
        }








        return $data;
    }

    public function getPensionsStructure4(
        $today,
        $start_date_of_next_month,
        $end_date_of_next_month,
        $start_date_of_this_month,
        $end_date_of_this_month,
        $start_date_of_previous_month,
        $end_date_of_previous_month,
        $start_date_of_next_week,
        $end_date_of_next_week,
        $start_date_of_this_week,
        $end_date_of_this_week,
        $start_date_of_previous_week,
        $end_date_of_previous_week,
        $all_manager_department_ids,

    ) {

        $pension_statuses = ["opt_in", "opt_out"];


        $issue_date_column = 'pension_enrollment_issue_date';
        $expiry_date_column = 'pension_re_enrollment_due_date';


        $employee_pension_history_ids = EmployeePensionHistory::select('id', 'user_id')
            ->whereHas("employee.department_user.department", function ($query) use ($all_manager_department_ids) {
                $query->whereIn("departments.id", $all_manager_department_ids);
            })
            ->whereHas("employee", function ($query) use ($all_manager_department_ids) {
                $query->where("users.pension_eligible", ">", 0);
            })
            ->whereNotIn('user_id', [auth()->user()->id])
            ->where($issue_date_column, '<', now())
            ->whereNotNull($expiry_date_column)
            ->groupBy('user_id')
            ->get()
            ->map(function ($record) use ($issue_date_column, $expiry_date_column) {


                $current_data = EmployeePensionHistory::where('user_id', $record->user_id)
                    ->where("pension_eligible", 1)
                    ->where($issue_date_column, '<', now())
                    ->orderByDesc("id")
                    ->first();

                if (empty($current_data)) {
                    return NULL;
                }


                return $current_data->id;
            })
            ->filter()->values();




        $pension_query  = EmployeePensionHistory::whereIn('id', $employee_pension_history_ids);


        foreach ($pension_statuses as $pension_status) {
            $updated_query = clone $pension_query;
            $updated_query = $updated_query->where("pension_scheme_status", $pension_status);
            $data[("total_" . $pension_status)] = $updated_query->count();
        }


        return $data;
    }


    public function getPensionExpiries(
        $all_manager_department_ids,
        $start_date_of_next_month,
        $end_date_of_next_month,
        $start_date_of_this_month,
        $end_date_of_this_month,
        $start_date_of_previous_month,
        $end_date_of_previous_month,
        $start_date_of_next_week,
        $end_date_of_next_week,
        $start_date_of_this_week,
        $end_date_of_this_week,
        $start_date_of_previous_week,
        $end_date_of_previous_week,
        $duration,
        $expires_in_days = 0


    ) {

        $issue_date_column = 'pension_enrollment_issue_date';
        $expiry_date_column = 'pension_re_enrollment_due_date';


        $employee_pension_history_ids = EmployeePensionHistory::select('id', 'user_id')
            ->whereHas("employee.department_user.department", function ($query) use ($all_manager_department_ids) {
                $query->whereIn("departments.id", $all_manager_department_ids);
            })
            ->whereHas("employee", function ($query) {
                $query->where("users.pension_eligible", ">", 0);
            })
            ->whereNotIn('user_id', [auth()->user()->id])
            ->where($issue_date_column, '<', now())
            ->whereNotNull($expiry_date_column)
            ->groupBy('user_id')
            ->get()
            ->map(function ($record) use ($issue_date_column) {
                $current_data = EmployeePensionHistory::where('user_id', $record->user_id)
                    ->where("pension_eligible", 1)
                    ->where($issue_date_column, '<', now())
                    ->orderByDesc("id")
                    ->first();

                if (empty($current_data)) {
                    return NULL;
                }


                return $current_data->id;
            })
            ->filter()->values();

        $data_query  = EmployeePensionHistory::whereIn('id', $employee_pension_history_ids)->where($expiry_date_column, ">=", today());





        if ($duration == "today") {
            $data["total"] = $data_query->whereBetween($expiry_date_column, [today()->startOfDay(), today()->endOfDay()])->count();
        }

        if ($duration == "next_week") {
            $data["total"] = $data_query->whereBetween($expiry_date_column, [$start_date_of_next_week, ($end_date_of_next_week . ' 23:59:59')])->count();
        }

        if ($duration == "this_week") {
            $data["total"] = $data_query->whereBetween($expiry_date_column, [$start_date_of_this_week, ($end_date_of_this_week . ' 23:59:59')])->count();
        }

        if ($duration == "previous_week") {
            $data["total"] = $data_query->whereBetween($expiry_date_column, [$start_date_of_previous_week, ($end_date_of_previous_week . ' 23:59:59')])->count();
        }


        if ($duration == "next_month") {
            $data["total"] = $data_query->whereBetween($expiry_date_column, [$start_date_of_next_month, ($end_date_of_next_month . ' 23:59:59')])->count();
        }


        if ($duration == "this_month") {
            $data["total"] = $data_query->whereBetween($expiry_date_column, [$start_date_of_this_month, ($end_date_of_this_month . ' 23:59:59')])->count();
        }


        if ($duration == "previous_month") {
            $data["total"] = $data_query->whereBetween($expiry_date_column, [$start_date_of_previous_month, ($end_date_of_previous_month . ' 23:59:59')])->count();
        }






        if ($expires_in_days) {
            $expires_in_days = [15, 30, 60];
            foreach ($expires_in_days as $expires_in_day) {
                $query_day = Carbon::now()->addDays($expires_in_day);
                $data[("expires_in_" . $expires_in_day . "_days")] = clone $data_query;
                $data[("expires_in_" . $expires_in_day . "_days")] = $data[("expires_in_" . $expires_in_day . "_days")]->whereBetween($expiry_date_column, [today(), ($query_day->endOfDay() . ' 23:59:59')])->count();
            }
        }


        return $data;
    }


    public function getPassportExpiries(
        $all_manager_department_ids,
        $start_date_of_next_month,
        $end_date_of_next_month,
        $start_date_of_this_month,
        $end_date_of_this_month,
        $start_date_of_previous_month,
        $end_date_of_previous_month,
        $start_date_of_next_week,
        $end_date_of_next_week,
        $start_date_of_this_week,
        $end_date_of_this_week,
        $start_date_of_previous_week,
        $end_date_of_previous_week,
        $duration,
        $expires_in_days = 0
    ) {

        $issue_date_column = 'passport_issue_date';
        $expiry_date_column = 'passport_expiry_date';


        $employee_passport_history_ids = EmployeePassportDetailHistory::select('user_id')
            ->where("business_id", auth()->user()->business_id)
            ->whereHas("employee.department_user.department", function ($query) use ($all_manager_department_ids) {
                $query->whereIn("departments.id", $all_manager_department_ids);
            })
            ->whereNotIn('user_id', [auth()->user()->id])
            ->where($issue_date_column, '<', now())
            ->groupBy('user_id')
            ->get()
            ->map(function ($record) use ($issue_date_column, $expiry_date_column) {

                $latest_expired_record = EmployeePassportDetailHistory::where('user_id', $record->user_id)
                    ->where($issue_date_column, '<', now())
                    ->orderByDesc($expiry_date_column)
                    // ->latest()
                    ->first();

                if ($latest_expired_record) {
                    $current_data = EmployeePassportDetailHistory::where('user_id', $record->user_id)
                        ->where($expiry_date_column, $latest_expired_record[$expiry_date_column])
                        ->where($issue_date_column, '<', now())
                        ->orderByDesc("id")
                        // ->orderByDesc($issue_date_column)
                        ->first();
                } else {
                    return NULL;
                }
                return $current_data ? $current_data->id : NULL;
            })
            ->filter()->values();



        $data_query  = EmployeePassportDetailHistory::whereIn('id', $employee_passport_history_ids)
            ->where($expiry_date_column, ">=", today());





        if ($duration == "today") {
            $data["total"] = $data_query->whereBetween($expiry_date_column, [today()->startOfDay(), today()->endOfDay()])->count();
        }

        if ($duration == "next_week") {
            $data["total"] = $data_query->whereBetween($expiry_date_column, [$start_date_of_next_week, ($end_date_of_next_week . ' 23:59:59')])->count();
        }

        if ($duration == "this_week") {
            $data["total"] = $data_query->whereBetween($expiry_date_column, [$start_date_of_this_week, ($end_date_of_this_week . ' 23:59:59')])->count();
        }

        if ($duration == "previous_week") {
            $data["total"] = $data_query->whereBetween($expiry_date_column, [$start_date_of_previous_week, ($end_date_of_previous_week . ' 23:59:59')])->count();
        }


        if ($duration == "next_month") {
            $data["total"] = $data_query->whereBetween($expiry_date_column, [$start_date_of_next_month, ($end_date_of_next_month . ' 23:59:59')])->count();
        }


        if ($duration == "this_month") {
            $data["total"] = $data_query->whereBetween($expiry_date_column, [$start_date_of_this_month, ($end_date_of_this_month . ' 23:59:59')])->count();
        }


        if ($duration == "previous_month") {
            $data["total"] = $data_query->whereBetween($expiry_date_column, [$start_date_of_previous_month, ($end_date_of_previous_month . ' 23:59:59')])->count();
        }



        if ($expires_in_days) {
            $expires_in_days = [15, 30, 60];
            foreach ($expires_in_days as $expires_in_day) {
                $query_day = Carbon::now()->addDays($expires_in_day);
                $data[("expires_in_" . $expires_in_day . "_days")] = clone $data_query;
                $data[("expires_in_" . $expires_in_day . "_days")] = $data[("expires_in_" . $expires_in_day . "_days")]->whereBetween($expiry_date_column, [today(), ($query_day->endOfDay() . ' 23:59:59')])->count();
            }
        }
        return $data;
    }


    public function getVisaExpiries(

        $all_manager_department_ids,
        $start_date_of_next_month,
        $end_date_of_next_month,
        $start_date_of_this_month,
        $end_date_of_this_month,
        $start_date_of_previous_month,
        $end_date_of_previous_month,
        $start_date_of_next_week,
        $end_date_of_next_week,
        $start_date_of_this_week,
        $end_date_of_this_week,
        $start_date_of_previous_week,
        $end_date_of_previous_week,
        $duration,
        $expires_in_days = 0

    ) {

        $issue_date_column = 'visa_issue_date';
        $expiry_date_column = 'visa_expiry_date';


        $employee_visa_history_ids = EmployeeVisaDetailHistory::select('user_id')
            ->where("business_id", auth()->user()->business_id)
            ->whereHas("employee.department_user.department", function ($query) use ($all_manager_department_ids) {
                $query->whereIn("departments.id", $all_manager_department_ids);
            })
            ->whereNotIn('user_id', [auth()->user()->id])
            ->where($issue_date_column, '<', now())
            ->groupBy('user_id')
            ->get()
            ->map(function ($record) use ($issue_date_column, $expiry_date_column) {

                $latest_expired_record = EmployeeVisaDetailHistory::where('user_id', $record->user_id)
                    ->where($issue_date_column, '<', now())
                    ->orderByDesc($expiry_date_column)
                    // ->latest()
                    ->first();

                if ($latest_expired_record) {
                    $current_data = EmployeeVisaDetailHistory::where('user_id', $record->user_id)
                        ->where($expiry_date_column, $latest_expired_record[$expiry_date_column])
                        ->where($issue_date_column, '<', now())
                        ->orderByDesc("id")
                        // ->orderByDesc($issue_date_column)
                        ->first();
                } else {
                    return NULL;
                }


                return $current_data ? $current_data->id : NULL;
            })
            ->filter()->values();


        $data_query  = EmployeeVisaDetailHistory::whereIn('id', $employee_visa_history_ids)
            ->where($expiry_date_column, ">=", today());




        if ($duration == "today") {
            $data["today"] = $data_query->whereBetween($expiry_date_column, [today()->startOfDay(), today()->endOfDay()])->count();
        }

        if ($duration == "next_week") {
            $data["total"] = $data_query->whereBetween($expiry_date_column, [$start_date_of_next_week, ($end_date_of_next_week . ' 23:59:59')])->count();
        }

        if ($duration == "this_week") {
            $data["total"] = $data_query->whereBetween($expiry_date_column, [$start_date_of_this_week, ($end_date_of_this_week . ' 23:59:59')])->count();
        }

        if ($duration == "previous_week") {
            $data["total"] = $data_query->whereBetween($expiry_date_column, [$start_date_of_previous_week, ($end_date_of_previous_week . ' 23:59:59')])->count();
        }


        if ($duration == "next_month") {
            $data["total"] = $data_query->whereBetween($expiry_date_column, [$start_date_of_next_month, ($end_date_of_next_month . ' 23:59:59')])->count();
        }


        if ($duration == "this_month") {
            $data["total"] = $data_query->whereBetween($expiry_date_column, [$start_date_of_this_month, ($end_date_of_this_month . ' 23:59:59')])->count();
        }


        if ($duration == "previous_month") {
            $data["total"] = $data_query->whereBetween($expiry_date_column, [$start_date_of_previous_month, ($end_date_of_previous_month . ' 23:59:59')])->count();
        }


        if ($expires_in_days) {
            $expires_in_days = [15, 30, 60];
            foreach ($expires_in_days as $expires_in_day) {
                $query_day = Carbon::now()->addDays($expires_in_day);
                $data[("expires_in_" . $expires_in_day . "_days")] = clone $data_query;
                $data[("expires_in_" . $expires_in_day . "_days")] = $data[("expires_in_" . $expires_in_day . "_days")]->whereBetween($expiry_date_column, [today(), ($query_day->endOfDay() . ' 23:59:59')])->count();
            }
        }



        return $data;
    }



    public function getRightToWorkExpiries(
        $all_manager_department_ids,
        $start_date_of_next_month,
        $end_date_of_next_month,
        $start_date_of_this_month,
        $end_date_of_this_month,
        $start_date_of_previous_month,
        $end_date_of_previous_month,
        $start_date_of_next_week,
        $end_date_of_next_week,
        $start_date_of_this_week,
        $end_date_of_this_week,
        $start_date_of_previous_week,
        $end_date_of_previous_week,
        $duration,
        $expires_in_days = 0
    ) {

        $issue_date_column = 'right_to_work_check_date';
        $expiry_date_column = 'right_to_work_expiry_date';


        $employee_right_to_work_history_ids = EmployeeRightToWorkHistory::select('user_id')
            ->where("business_id", auth()->user()->business_id)
            ->whereHas("employee.department_user.department", function ($query) use ($all_manager_department_ids) {
                $query->whereIn("departments.id", $all_manager_department_ids);
            })
            ->whereNotIn('user_id', [auth()->user()->id])
            ->where($issue_date_column, '<', now())
            ->groupBy('user_id')
            ->get()
            ->map(function ($record) use ($issue_date_column, $expiry_date_column) {

                $latest_expired_record = EmployeeRightToWorkHistory::where('user_id', $record->user_id)
                    ->where($issue_date_column, '<', now())
                    ->orderByDesc($expiry_date_column)
                    // ->latest()
                    ->first();

                if ($latest_expired_record) {
                    $current_data = EmployeeRightToWorkHistory::where('user_id', $record->user_id)
                        ->where($expiry_date_column, $latest_expired_record[$expiry_date_column])
                        ->where($issue_date_column, '<', now())
                        ->orderByDesc("id")
                        // ->orderByDesc($issue_date_column)
                        ->first();
                } else {
                    return NULL;
                }


                return $current_data ? $current_data->id : NULL;
            })
            ->filter()->values();



        $data_query  = EmployeeRightToWorkHistory::whereIn('id', $employee_right_to_work_history_ids)
            ->where($expiry_date_column, ">=", today());




        if ($duration == "today") {
            $data["total"] = $data_query->whereBetween($expiry_date_column, [today()->startOfDay(), today()->endOfDay()])->count();
        }

        if ($duration == "next_week") {
            $data["total"] = $data_query->whereBetween($expiry_date_column, [$start_date_of_next_week, ($end_date_of_next_week . ' 23:59:59')])->count();
        }

        if ($duration == "this_week") {
            $data["total"] = $data_query->whereBetween($expiry_date_column, [$start_date_of_this_week, ($end_date_of_this_week . ' 23:59:59')])->count();
        }

        if ($duration == "previous_week") {
            $data["total"] = $data_query->whereBetween($expiry_date_column, [$start_date_of_previous_week, ($end_date_of_previous_week . ' 23:59:59')])->count();
        }


        if ($duration == "next_month") {
            $data["total"] = $data_query->whereBetween($expiry_date_column, [$start_date_of_next_month, ($end_date_of_next_month . ' 23:59:59')])->count();
        }


        if ($duration == "this_month") {
            $data["total"] = $data_query->whereBetween($expiry_date_column, [$start_date_of_this_month, ($end_date_of_this_month . ' 23:59:59')])->count();
        }


        if ($duration == "previous_month") {
            $data["total"] = $data_query->whereBetween($expiry_date_column, [$start_date_of_previous_month, ($end_date_of_previous_month . ' 23:59:59')])->count();
        }


        if ($expires_in_days) {
            $expires_in_days = [15, 30, 60];
            foreach ($expires_in_days as $expires_in_day) {
                $query_day = Carbon::now()->addDays($expires_in_day);
                $data[("expires_in_" . $expires_in_day . "_days")] = clone $data_query;
                $data[("expires_in_" . $expires_in_day . "_days")] = $data[("expires_in_" . $expires_in_day . "_days")]->whereBetween($expiry_date_column, [today(), ($query_day->endOfDay() . ' 23:59:59')])->count();
            }
        }



        return $data;
    }


    public function getSponsorshipExpiries(
        $all_manager_department_ids,
        $start_date_of_next_month,
        $end_date_of_next_month,
        $start_date_of_this_month,
        $end_date_of_this_month,
        $start_date_of_previous_month,
        $end_date_of_previous_month,
        $start_date_of_next_week,
        $end_date_of_next_week,
        $start_date_of_this_week,
        $end_date_of_this_week,
        $start_date_of_previous_week,
        $end_date_of_previous_week,
        $duration,
        $expires_in_days = 0
    ) {

        $issue_date_column = 'date_assigned';
        $expiry_date_column = 'expiry_date';


        $employee_sponsorship_history_ids = EmployeeSponsorshipHistory::select('user_id')
            ->where("business_id", auth()->user()->business_id)
            ->whereHas("employee.department_user.department", function ($query) use ($all_manager_department_ids) {
                $query->whereIn("departments.id", $all_manager_department_ids);
            })
            ->whereNotIn('user_id', [auth()->user()->id])
            ->where($issue_date_column, '<', now())
            ->groupBy('user_id')
            ->get()
            ->map(function ($record) use ($issue_date_column, $expiry_date_column) {
                $latest_expired_record = EmployeeSponsorshipHistory::where('user_id', $record->user_id)
                    ->where($issue_date_column, '<', now())
                    ->orderByDesc($expiry_date_column)
                    // ->latest()
                    ->first();

                if ($latest_expired_record) {
                    $current_data = EmployeeSponsorshipHistory::where('user_id', $record->user_id)
                        ->where($expiry_date_column, $latest_expired_record[$expiry_date_column])
                        ->where($issue_date_column, '<', now())
                        ->orderByDesc("id")
                        // ->orderByDesc($issue_date_column)
                        ->first();
                } else {
                    return NULL;
                }
                return $current_data ? $current_data->id : NULL;
            })
            ->filter()->values();



        $data_query  = EmployeeSponsorshipHistory::whereIn('id', $employee_sponsorship_history_ids)
            ->where($expiry_date_column, ">=", today());



        if ($duration == "today") {
            $data["today"] = $data_query->whereBetween($expiry_date_column, [today()->startOfDay(), today()->endOfDay()])->count();
        }

        if ($duration == "next_week") {
            $data["total"] = $data_query->whereBetween($expiry_date_column, [$start_date_of_next_week, ($end_date_of_next_week . ' 23:59:59')])->count();
        }

        if ($duration == "this_week") {
            $data["total"] = $data_query->whereBetween($expiry_date_column, [$start_date_of_this_week, ($end_date_of_this_week . ' 23:59:59')])->count();
        }

        if ($duration == "previous_week") {
            $data["total"] = $data_query->whereBetween($expiry_date_column, [$start_date_of_previous_week, ($end_date_of_previous_week . ' 23:59:59')])->count();
        }


        if ($duration == "next_month") {
            $data["total"] = $data_query->whereBetween($expiry_date_column, [$start_date_of_next_month, ($end_date_of_next_month . ' 23:59:59')])->count();
        }


        if ($duration == "this_month") {
            $data["total"] = $data_query->whereBetween($expiry_date_column, [$start_date_of_this_month, ($end_date_of_this_month . ' 23:59:59')])->count();
        }


        if ($duration == "previous_month") {
            $data["total"] = $data_query->whereBetween($expiry_date_column, [$start_date_of_previous_month, ($end_date_of_previous_month . ' 23:59:59')])->count();
        }




        if ($expires_in_days) {
            $expires_in_days = [15, 30, 60];
            foreach ($expires_in_days as $expires_in_day) {
                $query_day = Carbon::now()->addDays($expires_in_day);
                $data[("expires_in_" . $expires_in_day . "_days")] = clone $data_query;
                $data[("expires_in_" . $expires_in_day . "_days")] = $data[("expires_in_" . $expires_in_day . "_days")]->whereBetween($expiry_date_column, [today(), ($query_day->endOfDay() . ' 23:59:59')])->count();
            }
        }




        return $data;
    }

    public function expiries(
        $today,
        $start_date_of_next_month,
        $end_date_of_next_month,
        $start_date_of_this_month,
        $end_date_of_this_month,
        $start_date_of_previous_month,
        $end_date_of_previous_month,
        $start_date_of_next_week,
        $end_date_of_next_week,
        $start_date_of_this_week,
        $end_date_of_this_week,
        $start_date_of_previous_week,
        $end_date_of_previous_week,
        $all_manager_department_ids,

    ) {

        $data["pension"] = $this->getPensionExpiries(
            $all_manager_department_ids,
            $start_date_of_next_month,
            $end_date_of_next_month,
            $start_date_of_this_month,
            $end_date_of_this_month,
            $start_date_of_previous_month,
            $end_date_of_previous_month,
            $start_date_of_next_week,
            $end_date_of_next_week,
            $start_date_of_this_week,
            $end_date_of_this_week,
            $start_date_of_previous_week,
            $end_date_of_previous_week,
            "today"
        );


        $data["passport"] = $this->getPassportExpiries(
            $all_manager_department_ids,
            $start_date_of_next_month,
            $end_date_of_next_month,
            $start_date_of_this_month,
            $end_date_of_this_month,
            $start_date_of_previous_month,
            $end_date_of_previous_month,
            $start_date_of_next_week,
            $end_date_of_next_week,
            $start_date_of_this_week,
            $end_date_of_this_week,
            $start_date_of_previous_week,
            $end_date_of_previous_week,
            "today"
        );

        $data["visa"] = $this->getVisaExpiries(
            $all_manager_department_ids,
            $start_date_of_next_month,
            $end_date_of_next_month,
            $start_date_of_this_month,
            $end_date_of_this_month,
            $start_date_of_previous_month,
            $end_date_of_previous_month,
            $start_date_of_next_week,
            $end_date_of_next_week,
            $start_date_of_this_week,
            $end_date_of_this_week,
            $start_date_of_previous_week,
            $end_date_of_previous_week,
            "today"
        );

        $data["right_to_work"] = $this->getRightToWorkExpiries(
            $all_manager_department_ids,
            $start_date_of_next_month,
            $end_date_of_next_month,
            $start_date_of_this_month,
            $end_date_of_this_month,
            $start_date_of_previous_month,
            $end_date_of_previous_month,
            $start_date_of_next_week,
            $end_date_of_next_week,
            $start_date_of_this_week,
            $end_date_of_this_week,
            $start_date_of_previous_week,
            $end_date_of_previous_week,
            "today"
        );





        $data["sponsorship"] = $this->getSponsorshipExpiries(
            $all_manager_department_ids,
            $start_date_of_next_month,
            $end_date_of_next_month,
            $start_date_of_this_month,
            $end_date_of_this_month,
            $start_date_of_previous_month,
            $end_date_of_previous_month,
            $start_date_of_next_week,
            $end_date_of_next_week,
            $start_date_of_this_week,
            $end_date_of_this_week,
            $start_date_of_previous_week,
            $end_date_of_previous_week,

            "today"
        );








        return $data;
    }



    public function getData($data_query, $dateField, $dates)
    {

        $data["current_amount"] = clone $data_query;
        $data["current_amount"] = $data["current_amount"]->whereBetween($dateField, [$dates["start_date"], ($dates["end_date"] . ' 23:59:59')])->count();



        $data["last_amount"] = clone $data_query;
        $data["last_amount"] = $data["last_amount"]->whereBetween($dateField, [$dates["previous_start_date"], ($dates["previous_end_date"] . ' 23:59:59')])->count();



        $all_data = clone $data_query;
        $all_data = $all_data->whereBetween($dateField, [$dates["start_date"], ($dates["end_date"] . ' 23:59:59')])->get();

        $start_date = Carbon::parse($dates["start_date"]);
        $end_date = Carbon::parse(($dates["end_date"]));
        // Initialize an array to hold the counts for each date
        $data["data"] = [];

        // Loop through each day in the date range
        for ($date = $start_date->copy(); $date->lte($end_date); $date->addDay()) {
            // Filter the data for the current date
            $filtered_data = $all_data->filter(function ($item) use ($dateField, $date) {
                return Carbon::parse($item[$dateField])->isSameDay($date);
            });

            // Store the count of records for the current date
            $data["data"][] = [
                "date" => $date->toDateString(),
                "total" => $filtered_data->count()
            ];
        }

        return $data;
    }


    public function getDataV2($data_query, $startDateField, $endDateField, $dates)
    {

        $data["current_amount"] = clone $data_query;
        $data["current_amount"] = $data["current_amount"]
            ->where($startDateField, ">", $dates["start_date"])
            ->where($endDateField, "<=", $dates["end_date"])
            ->count();



        $data["last_amount"] = clone $data_query;
        $data["last_amount"] = $data["last_amount"]
            ->where($startDateField, ">", $dates["previous_start_date"])
            ->where($endDateField, "<=", $dates["previous_end_date"])->count();



        $all_data = clone $data_query;
        $all_data = $all_data
            ->where($startDateField, ">", $dates["start_date"])
            ->where($endDateField, "<=", $dates["end_date"])
            ->select("id",)
            ->get();

        $start_date = Carbon::parse($dates["start_date"]);
        $end_date = Carbon::parse(($dates["end_date"]));
        // Initialize an array to hold the counts for each date
        $data["data"] = [];

        // Loop through each day in the date range
        for ($date = $start_date->copy(); $date->lte($end_date); $date->addDay()) {
            // Filter the data for the current date
            $filtered_data = $all_data->filter(function ($item) use ($date, $startDateField, $endDateField) {
                return Carbon::parse($item[$startDateField])->greaterThan($date) &&
                    Carbon::parse($item[$endDateField])->lessThanOrEqualTo($date);
            });

            // Store the count of records for the current date
            $data["data"][] = [
                "date" => $date->toDateString(),
                "total" => $filtered_data->count()
            ];
        }
        return $data;
    }








    public function total_employee(
        $today,
        $start_date_of_next_month,
        $end_date_of_next_month,
        $start_date_of_this_month,
        $end_date_of_this_month,
        $start_date_of_previous_month,
        $end_date_of_previous_month,
        $start_date_of_next_week,
        $end_date_of_next_week,
        $start_date_of_this_week,
        $end_date_of_this_week,
        $start_date_of_previous_week,
        $end_date_of_previous_week,
        $all_manager_department_ids,
        $duration
    ) {

        $data_query  = User::whereHas("department_user.department", function ($query) use ($all_manager_department_ids) {
            $query->whereIn("departments.id", $all_manager_department_ids);
        })
            ->whereNotIn('id', [auth()->user()->id])


            // ->where('is_in_employee', 1)

            ->where('is_active', 1);

        if ($duration == "total") {
            $data["total"] = $data_query
                ->count();
        }

        if ($duration == "today") {
            $data["total"] = $data_query
                ->where("joining_date", today())
                ->count();
        }

        if ($duration == "this_month") {
            $data["data"] = $this->getData(
                $data_query,
                "joining_date",
                [
                    "start_date" => $start_date_of_this_month,
                    "end_date" => $end_date_of_this_month,
                    "previous_start_date" => $start_date_of_previous_month,
                    "previous_end_date" => $end_date_of_previous_month,
                ]
            );
            $data["total"] = $data["data"]["current_amount"];
        }

        if ($duration == "this_week") {
            $data["data"] = $this->getData(
                $data_query,
                "joining_date",
                [
                    "start_date" => $start_date_of_this_week,
                    "end_date" => $end_date_of_this_week,
                    "previous_start_date" => $start_date_of_previous_week,
                    "previous_end_date" => $end_date_of_previous_week,
                ]
            );
            $data["total"] = $data["data"]["current_amount"];
        }



        return $data;
    }


    public function open_roles(
        $today,
        $start_date_of_next_month,
        $end_date_of_next_month,
        $start_date_of_this_month,
        $end_date_of_this_month,
        $start_date_of_previous_month,
        $end_date_of_previous_month,
        $start_date_of_next_week,
        $end_date_of_next_week,
        $start_date_of_this_week,
        $end_date_of_this_week,
        $start_date_of_previous_week,
        $end_date_of_previous_week,
        $all_manager_department_ids,
        $duration
    ) {

        $data_query  = JobListing::where("business_id", auth()->user()->business_id);



        // $data["total"] = $data_query->count();

        if ($duration == "today") {
            $data["total"] = $data_query
                ->where("application_deadline", ">", today())
                ->where("posted_on", "<=", today())
                ->count();
        }

        if ($duration == "this_month") {
            $data["data"] = $this->getDataV2(
                $data_query,
                "posted_on",
                "application_deadline",
                [
                    "start_date" => $start_date_of_this_month,
                    "end_date" => $end_date_of_this_month,
                    "previous_start_date" => $start_date_of_previous_month,
                    "previous_end_date" => $end_date_of_previous_month,
                ]
            );
            $data["total"] = $data["data"]["current_amount"];
        }

        if ($duration == "this_week") {
            $data["data"] = $this->getDataV2(
                $data_query,
                "posted_on",
                "application_deadline",
                [
                    "start_date" => $start_date_of_this_week,
                    "end_date" => $end_date_of_this_week,
                    "previous_start_date" => $start_date_of_previous_week,
                    "previous_end_date" => $end_date_of_previous_week,
                ]
            );

            $data["total"] = $data["data"]["current_amount"];
        }







        return $data;
    }

    public function checkHoliday($date, $user_id)
    {
        // Get all parent department IDs of the employee
        $all_parent_department_ids = $this->all_parent_departments_of_user($user_id);
        // Retrieve work shift history for the user and date
        $work_shift_history =  $this->get_work_shift_history($date, $user_id);
        // Retrieve work shift details based on work shift history and date
        $work_shift_details =  $this->get_work_shift_details($work_shift_history, $date);



        if (!$work_shift_details->start_at || !$work_shift_details->end_at || $work_shift_details->is_weekend) {
            return true;
        }

        // Retrieve holiday details for the user and date
        $holiday = $this->get_holiday_details($date, $user_id, $all_parent_department_ids);

        if (!empty($holiday) && $holiday->is_active) {
            return true;
        }
        // Retrieve leave record details for the user and date
        $leave_record = $this->get_leave_record_details($date, $user_id, [], true);

        if (!empty($leave_record)) {
            return true;
        }


        return false;
    }


    public function calculateAbsent($all_manager_user_ids, $date, $data_query)
    {


        $current_date = Carbon::parse($date);
        $users = User::whereIn("id", $all_manager_user_ids)->select("id", "joining_date")->get();
        $absent_count = 0;
        foreach ($users as $user) {

            $joining_date = Carbon::parse($user->joining_date);

            if ($joining_date->gt($current_date)) {
                continue;
            }


            if (!$this->checkHoliday($date, $user->id)) {
                $data_query = clone $data_query;
                $attendance = $data_query->where("in_date", $date)->first();
                if (empty($attendance)) {
                    $absent_count++;
                }
            }
        }
        return $absent_count;
    }

    public function getAbsentData($all_manager_user_ids, $data_query, $dates)
    {

        $data["current_amount"] = 0;
        $data["last_amount"] = 0;
        $data["data"] = [];

        $start_date = Carbon::parse($dates["start_date"]);
        $end_date = Carbon::parse(($dates["end_date"]));
        // Loop through each day in the date range
        for ($date = $start_date->copy(); $date->lte($end_date); $date->addDay()) {


            $absent_count = $this->calculateAbsent($all_manager_user_ids, $date, $data_query);


            $data["data"][] = [
                "date" => $date->toDateString(),
                "total" => $absent_count
            ];



            $data["current_amount"] = $data["current_amount"] + $absent_count;
        }


        $previous_start_date = Carbon::parse($dates["previous_start_date"]);
        $previous_end_date = Carbon::parse(($dates["previous_end_date"]));

        // Loop through each day in the date range
        for ($date = $previous_start_date->copy(); $date->lte($previous_end_date); $date->addDay()) {
            // Store the count of records for the current date
            $previous_data = $this->calculateAbsent($all_manager_user_ids, $date, $data_query);
            $data["last_amount"] = $data["last_amount"] + $previous_data;
        }

        return $data;
    }


    public function absent(
        $today,
        $start_date_of_next_month,
        $end_date_of_next_month,
        $start_date_of_this_month,
        $end_date_of_this_month,
        $start_date_of_previous_month,
        $end_date_of_previous_month,
        $start_date_of_next_week,
        $end_date_of_next_week,
        $start_date_of_this_week,
        $end_date_of_this_week,
        $start_date_of_previous_week,
        $end_date_of_previous_week,
        $all_manager_department_ids


    ) {

        $all_manager_user_ids = $this->get_all_user_of_manager($all_manager_department_ids);


        $data_query  = Attendance::where([
            "is_present" => 1
        ]);


        $data["total"] = $this->calculateAbsent($all_manager_user_ids, $today, $data_query);

        $data["monthly"] = $this->getAbsentData(
            $all_manager_user_ids,
            $data_query,
            [
                "start_date" => $start_date_of_this_week,
                "end_date" => $end_date_of_this_week,
                "previous_start_date" => $start_date_of_previous_week,
                "previous_end_date" => $end_date_of_previous_week,
            ]
        );

        $data["weekly"] = $this->getAbsentData(
            $all_manager_user_ids,
            $data_query,
            [
                "start_date" => $start_date_of_this_week,
                "end_date" => $end_date_of_this_week,
                "previous_start_date" => $start_date_of_previous_week,
                "previous_end_date" => $end_date_of_previous_week,
            ]
        );


        return $data;
    }


    public function presentAbsent(
        $today,
        $start_date_of_next_month,
        $end_date_of_next_month,
        $start_date_of_this_month,
        $end_date_of_this_month,
        $start_date_of_previous_month,
        $end_date_of_previous_month,
        $start_date_of_next_week,
        $end_date_of_next_week,
        $start_date_of_this_week,
        $end_date_of_this_week,
        $start_date_of_previous_week,
        $end_date_of_previous_week,
        $all_manager_department_ids


    ) {

        $all_manager_user_ids = $this->get_all_user_of_manager($all_manager_department_ids);





        $today_present_expectation = WorkShiftDetailHistory::where([
            "day" =>  Carbon::parse(today())->dayOfWeek,
            "is_weekend" => 0

        ])
            ->whereHas("work_shift.users", function ($query) use ($all_manager_user_ids) {
                $query->whereIn("users.id", $all_manager_user_ids)

                    ->where("employee_user_work_shift_histories.from_date", "<=", today())

                    ->where(function ($query) {
                        $query

                            ->where("employee_user_work_shift_histories.to_date", ">", today())
                            ->orWhereNull("employee_user_work_shift_histories.to_date");
                    });
            })
            ->count();




        $data["today_present"] = Attendance::where([
            "is_present" => 1
        ])
            ->whereIn("user_id", $all_manager_user_ids)
            ->whereBetween("in_date", [$start_date_of_this_week, ($end_date_of_this_week . ' 23:59:59')])
            ->count();




        $data["this_week_present"] = $today_present_expectation -  $data["today_present"];



        return $data;
    }


    public function presentAbsentHours(
        $today,
        $start_date_of_next_month,
        $end_date_of_next_month,
        $start_date_of_this_month,
        $end_date_of_this_month,
        $start_date_of_previous_month,
        $end_date_of_previous_month,
        $start_date_of_next_week,
        $end_date_of_next_week,
        $start_date_of_this_week,
        $end_date_of_this_week,
        $start_date_of_previous_week,
        $end_date_of_previous_week,
        $all_manager_department_ids


    ) {

        $all_manager_user_ids = $this->get_all_user_of_manager($all_manager_department_ids);

        $start_date = Carbon::parse($start_date_of_this_week);
        $end_date = Carbon::parse(($end_date_of_this_week));
        // Initialize an array to hold the counts for each date


        $total_present_expectation = 0;



        // $employeeUserWorkShiftHistories = EmployeeUserWorkShiftHistory::with("work_shift_history.details")

        //     ->where('work_shift_histories.from_date', '<=', $end_date)
        //     ->whereHas('work_shift_history', function ($query) use ($end_date, $start_date) {
        //         $query->where('from_date', '<=', $end_date)
        //             ->where(function ($query) use ($start_date) {
        //                 $query->where('to_date', '>', $start_date)
        //                     ->orWhereNull('to_date');
        //             });
        //     })
        //     ->whereIn('employee_user_work_shift_histories.user_id', $all_manager_user_ids)
        //     ->where('employee_user_work_shift_histories.from_date', '<=', $end_date)
        //     ->where(function ($query) use ($start_date) {
        //         $query->where('employee_user_work_shift_histories.to_date', '>', $start_date)
        //             ->orWhereNull('employee_user_work_shift_histories.to_date');
        //     })
        //     ->where([

        //         ['work_shift_detail_histories.is_weekend', '=', 0]
        //     ])
        //     ->get();



        // Loop through each day in the date range
        for ($date = $start_date->copy(); $date->lte($end_date); $date->addDay()) {


            // $itemInDate = Carbon::parse($date);


            // $totalSeconds =     $employeeUserWorkShiftHistories->map(function ($employeeHistory) use ($itemInDate) {
            //     $fromEmployeeDate = Carbon::parse($employeeHistory->from_date);
            //     $toEmployeeDate = $employeeHistory->to_date ? Carbon::parse($employeeHistory->to_date) : null;

            //     $fromWorkShiftDate = Carbon::parse($employeeHistory->work_shift_history->from_date);
            //     $toWorkShiftDate = $employeeHistory->work_shift_history->to_date ? Carbon::parse($employeeHistory->work_shift_history->to_date) : null;

            //     if (
            //         $itemInDate->greaterThanOrEqualTo($fromEmployeeDate)
            //         && ($toEmployeeDate === null || $itemInDate->lessThan($toEmployeeDate)) && (
            //             $itemInDate->greaterThanOrEqualTo($fromWorkShiftDate)
            //             && ($toWorkShiftDate === null || $itemInDate->lessThan($toWorkShiftDate))
            //         )
            //     ) {
            //         $detail = $employeeHistory->work_shift_history->details->first(function ($detail) use ($itemInDate) {
            //             return ($detail->day == $itemInDate->dayOfWeek) && ($detail->is_weekend == 0);
            //         });

            //         if (!empty($detail)) {
            //             // Calculate the difference in seconds
            //             $startAt = Carbon::parse($detail->start_at);
            //             $endAt = Carbon::parse($detail->end_at);
            //             $differenceInSeconds = $startAt->diffInSeconds($endAt);

            //             return $differenceInSeconds;
            //         }
            //     }

            //     return 0;
            // })->sum();

            // Raw SQL query for calculating total time
            $query = DB::table('employee_user_work_shift_histories')
                ->join('work_shift_histories', 'employee_user_work_shift_histories.work_shift_id', '=', 'work_shift_histories.id')
                ->join('work_shift_detail_histories', 'work_shift_histories.id', '=', 'work_shift_detail_histories.work_shift_id')
                ->select(DB::raw('SUM(TIMESTAMPDIFF(SECOND, work_shift_detail_histories.start_at, work_shift_detail_histories.end_at)) as total_seconds'))
                ->where('work_shift_histories.from_date', '<=', $date)
                ->where(function ($query) use ($date) {
                    $query->where('work_shift_histories.to_date', '>', $date)
                          ->orWhereNull('work_shift_histories.to_date');
                })
                ->whereIn('employee_user_work_shift_histories.user_id', $all_manager_user_ids)
                ->where('employee_user_work_shift_histories.from_date', '<=', $date)
                ->where(function ($query) use ($date) {
                    $query->where('employee_user_work_shift_histories.to_date', '>', $date)
                          ->orWhereNull('employee_user_work_shift_histories.to_date');
                })
                ->where([
                    ['work_shift_detail_histories.day', '=', $date->dayOfWeek],
                    ['work_shift_detail_histories.is_weekend', '=', 0]
                ])
                ->first();

                // Extract total seconds from the query result
             $totalSeconds = $query->total_seconds ?? 0;

            // Convert total seconds to hours directly
            $totalHours = $totalSeconds / 3600;

            $total_present_expectation += $totalHours;
        }

        $total_present =  Attendance::where([
            "is_present" => 1
        ])
            ->whereIn("user_id", $all_manager_user_ids)
            ->where("in_date", ">=", $start_date)
            ->where("in_date", "<=", ($end_date . ' 23:59:59'))
            ->sum(DB::raw('total_paid_hours - overtime_hours'));


        return [
            "total_present_expectation" =>  $total_present_expectation,
            "total_present" =>  $total_present,
            "total_absent" => $total_present_expectation - $total_present,
        ];
    }


    public function calculatePresent($all_manager_user_ids, $date, $data_query)
    {

        $present_count = 0;
        foreach ($all_manager_user_ids as $user_id) {

            if (!$this->checkHoliday($date, $user_id)) {

                $data_query = clone $data_query;
                $attendance = $data_query->where("in_date", $date)->first();
                if (!empty($attendance)) {
                    $present_count++;
                }
            }
        }
        return $present_count;
    }
    public function getPresentData($all_manager_user_ids, $data_query, $dates)
    {
        $data["current_amount"] = 0;
        $data["last_amount"] = 0;
        $data["data"] = [];

        $start_date = Carbon::parse($dates["start_date"]);
        $end_date = Carbon::parse(($dates["end_date"]));
        // Loop through each day in the date range
        for ($date = $start_date->copy(); $date->lte($end_date); $date->addDay()) {


            $present_count = $this->calculatePresent($all_manager_user_ids, $date, $data_query);
            $data["data"][] = [
                "date" => $date->toDateString(),
                "total" => $present_count
            ];

            $data["current_amount"] = $data["current_amount"] + $present_count;
        }


        $previous_start_date = Carbon::parse($dates["previous_start_date"]);
        $previous_end_date = Carbon::parse(($dates["previous_end_date"]));

        // Loop through each day in the date range
        for ($date = $previous_start_date->copy(); $date->lte($previous_end_date); $date->addDay()) {
            // Store the count of records for the current date
            $previous_data = $this->calculatePresent($all_manager_user_ids, $date, $data_query);
            $data["last_amount"] = $data["last_amount"] + $previous_data;
        }

        return $data;
    }
    public function present(
        $today,
        $start_date_of_next_month,
        $end_date_of_next_month,
        $start_date_of_this_month,
        $end_date_of_this_month,
        $start_date_of_previous_month,
        $end_date_of_previous_month,
        $start_date_of_next_week,
        $end_date_of_next_week,
        $start_date_of_this_week,
        $end_date_of_this_week,
        $start_date_of_previous_week,
        $end_date_of_previous_week,
        $all_manager_department_ids


    ) {

        $all_manager_user_ids = $this->get_all_user_of_manager($all_manager_department_ids);


        $data_query  = Attendance::where([
            "is_present" => 1
        ]);


        $data["total"] = $this->calculatePresent($all_manager_user_ids, $today, $data_query);

        $data["monthly"] = $this->getPresentData(
            $all_manager_user_ids,
            $data_query,
            [
                "start_date" => $start_date_of_this_week,
                "end_date" => $end_date_of_this_week,
                "previous_start_date" => $start_date_of_previous_week,
                "previous_end_date" => $end_date_of_previous_week,
            ]
        );

        $data["weekly"] = $this->getPresentData(
            $all_manager_user_ids,
            $data_query,
            [
                "start_date" => $start_date_of_this_week,
                "end_date" => $end_date_of_this_week,
                "previous_start_date" => $start_date_of_previous_week,
                "previous_end_date" => $end_date_of_previous_week,
            ]
        );


        return $data;
    }

    public function employee_on_holiday(
        $today,
        $start_date_of_next_month,
        $end_date_of_next_month,
        $start_date_of_this_month,
        $end_date_of_this_month,
        $start_date_of_previous_month,
        $end_date_of_previous_month,
        $start_date_of_next_week,
        $end_date_of_next_week,
        $start_date_of_this_week,
        $end_date_of_this_week,
        $start_date_of_previous_week,
        $end_date_of_previous_week,
        $all_manager_department_ids

    ) {
        $total_departments = Department::where([
            "business_id" => auth()->user()->business_id,
            "is_active" => 1
        ])->count();

        $data_query  = User::whereHas("department_user.department", function ($query) use ($all_manager_department_ids) {
            $query->whereIn("departments.id", $all_manager_department_ids);
        })
            ->whereNotIn('id', [auth()->user()->id])

            // ->where('is_in_employee', 1)

            ->where('is_active', 1)
            ->where("business_id", auth()->user()->id);


        // $data["total_data_count"] = $data_query->count();

        $data["today"] = clone $data_query;
        $data["today"] = $data["today"]

            ->where(function ($query) use ($today, $total_departments) {
                $query->where(function ($query) use ($today, $total_departments) {

                    $query->where(function ($query) use ($today, $total_departments) {
                        $query->whereHas('holidays', function ($query) use ($today) {
                            $query->where('holidays.start_date', "<=",  $today->copy()->startOfDay())
                                ->where('holidays.end_date', ">=",  $today->copy()->endOfDay());
                        })
                            ->orWhere(function ($query) use ($today, $total_departments) {
                                $query->whereHasRecursiveHolidays($today, $total_departments);
                            });

                        // ->whereHas('departments.holidays', function ($query) use ($today) {
                        //     $query->where('holidays.start_date', "<=",  $today->copy()->startOfDay())
                        //     ->where('holidays.end_date', ">=",  $today->copy()->endOfDay());
                        // });

                    })
                        ->where(function ($query) use ($today) {
                            $query->orWhereDoesntHave('holidays', function ($query) use ($today) {
                                $query->where('holidays.start_date', "<=",  $today->copy()->startOfDay())
                                    ->where('holidays.end_date', ">=",  $today->copy()->endOfDay())
                                    ->orWhere(function ($query) {
                                        $query->whereDoesntHave("users")
                                            ->whereDoesntHave("departments");
                                    });
                            });
                        });
                })
                    ->orWhere(
                        function ($query) use ($today) {
                            $query->orWhereDoesntHave('holidays', function ($query) use ($today) {
                                $query->where('holidays.start_date', "<=",  $today->copy()->startOfDay());
                                $query->where('holidays.end_date', ">=",  $today->copy()->endOfDay());
                                $query->doesntHave('users');
                            });
                        }
                    );
            })



            ->count();

        // $data["next_week"] = clone $data_query;
        // $data["next_week"] = $data["next_week"]

        // ->where(function($query) use ($start_date_of_next_week,$end_date_of_next_week) {
        //     $query->whereHas('departments.holidays', function ($query) use ($start_date_of_next_week,$end_date_of_next_week) {
        //         $query->where('holidays.start_date', "<=",  $start_date_of_next_week);
        //         $query->where('holidays.end_date', ">=",  $end_date_of_next_week . ' 23:59:59');
        //     })->orWhereDoesntHave('departments.holidays', function ($query) use ($start_date_of_next_week,$end_date_of_next_week) {
        //         $query->where('holidays.start_date', "<=",  $start_date_of_next_week);
        //         $query->where('holidays.end_date', ">=",  $end_date_of_next_week . ' 23:59:59');
        //         $query->doesntHave('departments');

        //     });
        // })
        // ->orWhere(function($query) use ($start_date_of_next_week,$end_date_of_next_week) {
        //     $query->whereHas('holidays', function ($query) use ($start_date_of_next_week,$end_date_of_next_week) {
        //         $query->where('holidays.start_date', "<=",  $start_date_of_next_week);
        //         $query->where('holidays.end_date', ">=",  $end_date_of_next_week . ' 23:59:59');
        //     })->orWhereDoesntHave('holidays', function ($query) use ($start_date_of_next_week,$end_date_of_next_week) {
        //         $query->where('holidays.start_date', "<=",  $start_date_of_next_week);
        //         $query->where('holidays.end_date', ">=",  $end_date_of_next_week . ' 23:59:59');
        //         $query->doesntHave('users');

        //     });
        // })

        // ->count();

        // $data["this_week"] = clone $data_query;
        // $data["this_week"] = $data["this_week"]

        // ->where(function($query) use ( $start_date_of_this_week,$end_date_of_this_week) {
        //     $query->whereHas('departments.holidays', function ($query) use ( $start_date_of_this_week,$end_date_of_this_week) {
        //         $query->where('holidays.start_date', "<=",  $start_date_of_this_week);
        //         $query->where('holidays.end_date', ">=",  $end_date_of_this_week . ' 23:59:59');
        //     })->orWhereDoesntHave('departments.holidays', function ($query) use ( $start_date_of_this_week,$end_date_of_this_week) {
        //         $query->where('holidays.start_date', "<=",  $start_date_of_this_week);
        //         $query->where('holidays.end_date', ">=",  $end_date_of_this_week . ' 23:59:59');
        //         $query->doesntHave('departments');
        //     });
        // })

        // ->orWhere(function($query) use ( $start_date_of_this_week,$end_date_of_this_week) {
        //     $query->whereHas('holidays', function ($query) use ( $start_date_of_this_week,$end_date_of_this_week) {
        //         $query->where('holidays.start_date', "<=",  $start_date_of_this_week);
        //         $query->where('holidays.end_date', ">=",  $end_date_of_this_week . ' 23:59:59');
        //     })->orWhereDoesntHave('holidays', function ($query) use ( $start_date_of_this_week,$end_date_of_this_week) {

        //         $query->where('holidays.start_date', "<=",  $start_date_of_this_week);
        //         $query->where('holidays.end_date', ">=",  $end_date_of_this_week . ' 23:59:59');
        //         $query->doesntHave('users');


        //     });
        // })



        // ->count();

        // $data["previous_week_data_count"] = clone $data_query;
        // $data["previous_week_data_count"] = $data["previous_week_data_count"]
        // ->where(function($query) use ($start_date_of_previous_week,$end_date_of_previous_week) {
        //     $query->whereHas('departments.holidays', function ($query) use ($start_date_of_previous_week,$end_date_of_previous_week) {
        //         $query->where('holidays.start_date', "<=",  $start_date_of_previous_week);
        //         $query->where('holidays.end_date', ">=",  $end_date_of_previous_week . ' 23:59:59');
        //     })->orWhereDoesntHave('departments.holidays', function ($query) use ($start_date_of_previous_week,$end_date_of_previous_week) {

        //         $query->where('holidays.start_date', "<=",  $start_date_of_previous_week);
        //         $query->where('holidays.end_date', ">=",  $end_date_of_previous_week . ' 23:59:59');
        //         $query->doesntHave('departments');

        //     });
        // })
        // ->orWhere(function($query) use ($start_date_of_previous_week,$end_date_of_previous_week) {
        //     $query->whereHas('holidays', function ($query) use ($start_date_of_previous_week,$end_date_of_previous_week) {
        //         $query->where('holidays.start_date', "<=",  $start_date_of_previous_week);
        //         $query->where('holidays.end_date', ">=",  $end_date_of_previous_week . ' 23:59:59');
        //     })->orWhereDoesntHave('holidays', function ($query) use ($start_date_of_previous_week,$end_date_of_previous_week) {

        //         $query->where('holidays.start_date', "<=",  $start_date_of_previous_week);
        //         $query->where('holidays.end_date', ">=",  $end_date_of_previous_week . ' 23:59:59');
        //         $query->doesntHave('users');

        //     });
        // })



        // ->count();

        // $data["next_month"] = clone $data_query;
        // $data["next_month"] = $data["next_month"]
        // ->where(function($query) use ($start_date_of_next_month,$end_date_of_next_month) {
        //     $query->whereHas('departments.holidays', function ($query) use ( $start_date_of_next_month,$end_date_of_next_month) {
        //         $query->where('holidays.start_date', "<=",  $start_date_of_next_month);
        //         $query->where('holidays.end_date', ">=",  $end_date_of_next_month . ' 23:59:59');
        //     })->orWhereDoesntHave('departments.holidays', function ($query) use ($start_date_of_next_month,$end_date_of_next_month) {

        //          $query->where('holidays.start_date', "<=",  $start_date_of_next_month);
        //         $query->where('holidays.end_date', ">=",  $end_date_of_next_month . ' 23:59:59');
        //         $query->doesntHave('departments');


        //     });
        // })
        // ->orWhere(function($query) use ($start_date_of_next_month,$end_date_of_next_month) {
        //     $query->whereHas('holidays', function ($query) use ( $start_date_of_next_month,$end_date_of_next_month) {
        //         $query->where('holidays.start_date', "<=",  $start_date_of_next_month);
        //         $query->where('holidays.end_date', ">=",  $end_date_of_next_month . ' 23:59:59');
        //     })->orWhereDoesntHave('holidays', function ($query) use ($start_date_of_next_month,$end_date_of_next_month) {

        //          $query->where('holidays.start_date', "<=",  $start_date_of_next_month);
        //         $query->where('holidays.end_date', ">=",  $end_date_of_next_month . ' 23:59:59');
        //         $query->doesntHave('users');


        //     });
        // })


        // ->count();

        // $data["this_month"] = clone $data_query;
        // $data["this_month"] = $data["this_month"]
        // ->where(function($query) use ( $start_date_of_this_month,$end_date_of_this_month) {
        //     $query->whereHas('departments.holidays', function ($query) use ( $start_date_of_this_month,$end_date_of_this_month) {


        //         $query->where('holidays.start_date', "<=",  $start_date_of_this_month);
        //         $query->where('holidays.end_date', ">=",  $end_date_of_this_month . ' 23:59:59');


        //     })->orWhereDoesntHave('departments.holidays', function ($query) use ( $start_date_of_this_month,$end_date_of_this_month) {

        //         $query->where('holidays.start_date', "<=",  $start_date_of_this_month);
        //         $query->where('holidays.end_date', ">=",  $end_date_of_this_month . ' 23:59:59');
        //         $query->doesntHave('departments');


        //     });

        // })

        // ->orWhere(function($query) use ( $start_date_of_this_month,$end_date_of_this_month) {
        //     $query->whereHas('holidays', function ($query) use ( $start_date_of_this_month,$end_date_of_this_month) {


        //         $query->where('holidays.start_date', "<=",  $start_date_of_this_month);
        //         $query->where('holidays.end_date', ">=",  $end_date_of_this_month . ' 23:59:59');


        //     })->orWhereDoesntHave('holidays', function ($query) use ( $start_date_of_this_month,$end_date_of_this_month) {

        //         $query->where('holidays.start_date', "<=",  $start_date_of_this_month);
        //         $query->where('holidays.end_date', ">=",  $end_date_of_this_month . ' 23:59:59');
        //         $query->doesntHave('users');


        //     });

        // })

        // ->count();


        // $data["previous_month_data_count"] = clone $data_query;
        // $data["previous_month_data_count"] = $data["previous_month_data_count"]

        // ->where(function($query) use ($start_date_of_previous_month,$end_date_of_previous_month) {
        //     $query ->whereHas('departments.holidays', function ($query) use ($start_date_of_previous_month,$end_date_of_previous_month) {

        //         $query->where('holidays.start_date', "<=",  $start_date_of_previous_month);
        //         $query->where('holidays.end_date', ">=",  $end_date_of_previous_month . ' 23:59:59');


        //     })->orWhereDoesntHave('departments.holidays', function ($query) use ($start_date_of_previous_month,$end_date_of_previous_month) {

        //         $query->where('holidays.start_date', "<=",  $start_date_of_previous_month);
        //         $query->where('holidays.end_date', ">=",  $end_date_of_previous_month . ' 23:59:59');
        //         $query->doesntHave('departments');

        //     });



        // })

        // ->orWhere(function($query) use ($start_date_of_previous_month,$end_date_of_previous_month) {
        //     $query ->whereHas('holidays', function ($query) use ($start_date_of_previous_month,$end_date_of_previous_month) {

        //         $query->where('holidays.start_date', "<=",  $start_date_of_previous_month);
        //         $query->where('holidays.end_date', ">=",  $end_date_of_previous_month . ' 23:59:59');




        //     })->orWhereDoesntHave('holidays', function ($query) use ($start_date_of_previous_month,$end_date_of_previous_month) {

        //         $query->where('holidays.start_date', "<=",  $start_date_of_previous_month);
        //         $query->where('holidays.end_date', ">=",  $end_date_of_previous_month . ' 23:59:59');
        //         $query->doesntHave('users');

        //     });



        // })




        // ->count();


        $data["date_ranges"] = [
            "today_date_range" => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
            "yesterday_data_count_date_range" => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            "next_week_date_range" => [$start_date_of_next_week, ($end_date_of_next_week)],
            "this_week_date_range" => [$start_date_of_this_week, ($end_date_of_this_week)],
            "previous_week_data_count_date_range" => [$start_date_of_previous_week, ($end_date_of_previous_week)],
            "next_month_date_range" => [$start_date_of_next_month, ($end_date_of_next_month)],
            "this_month_date_range" => [$start_date_of_this_month, ($end_date_of_this_month)],
            "previous_month_data_count_date_range" => [$start_date_of_previous_month, ($end_date_of_previous_month)],
        ];

        return $data;
    }
    public function upcoming_passport_expiries(
        $today,
        $start_date_of_next_month,
        $end_date_of_next_month,
        $start_date_of_this_month,
        $end_date_of_this_month,
        $start_date_of_previous_month,
        $end_date_of_previous_month,
        $start_date_of_next_week,
        $end_date_of_next_week,
        $start_date_of_this_week,
        $end_date_of_this_week,
        $start_date_of_previous_week,
        $end_date_of_previous_week,
        $all_manager_department_ids
    ) {

        $issue_date_column = 'passport_issue_date';
        $expiry_date_column = 'passport_expiry_date';


        $employee_passport_history_ids = EmployeePassportDetailHistory::select('user_id')
            ->where("business_id", auth()->user()->business_id)
            ->whereHas("employee.department_user.department", function ($query) use ($all_manager_department_ids) {
                $query->whereIn("departments.id", $all_manager_department_ids);
            })
            ->whereNotIn('user_id', [auth()->user()->id])
            ->where($issue_date_column, '<', now())
            ->groupBy('user_id')
            ->get()
            ->map(function ($record) use ($issue_date_column, $expiry_date_column) {

                $latest_expired_record = EmployeePassportDetailHistory::where('user_id', $record->user_id)
                    ->where($issue_date_column, '<', now())
                    ->orderByDesc($expiry_date_column)
                    // ->latest()
                    ->first();

                if ($latest_expired_record) {
                    $current_data = EmployeePassportDetailHistory::where('user_id', $record->user_id)
                        ->where($expiry_date_column, $latest_expired_record[$expiry_date_column])
                        ->where($issue_date_column, '<', now())
                        ->orderByDesc("id")
                        // ->orderByDesc($issue_date_column)
                        ->first();
                } else {
                    return NULL;
                }


                return $current_data ? $current_data->id : NULL;
            })
            ->filter()->values();



        $data_query  = EmployeePassportDetailHistory::whereIn('id', $employee_passport_history_ids)
            ->where($expiry_date_column, ">=", today());



        $data["total_data_count"] = $data_query->count();

        $data["today"] = clone $data_query;
        $data["today"] = $data["today"]->whereBetween('passport_expiry_date', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->count();

        $data["yesterday_data_count"] = clone $data_query;
        $data["yesterday_data_count"] = $data["yesterday_data_count"]->whereBetween('passport_expiry_date', [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()])->count();

        $data["next_week"] = clone $data_query;
        $data["next_week"] = $data["next_week"]->whereBetween('passport_expiry_date', [$start_date_of_next_week, ($end_date_of_next_week . ' 23:59:59')])->count();

        $data["this_week"] = clone $data_query;
        $data["this_week"] = $data["this_week"]->whereBetween('passport_expiry_date', [$start_date_of_this_week, ($end_date_of_this_week . ' 23:59:59')])->count();



        $data["next_month"] = clone $data_query;
        $data["next_month"] = $data["next_month"]->whereBetween('passport_expiry_date', [$start_date_of_next_month, ($end_date_of_next_month . ' 23:59:59')])->count();

        $data["this_month"] = clone $data_query;
        $data["this_month"] = $data["this_month"]->whereBetween('passport_expiry_date', [$start_date_of_this_month, ($end_date_of_this_month . ' 23:59:59')])->count();


        $expires_in_days = [15, 30, 60];
        foreach ($expires_in_days as $expires_in_day) {
            $query_day = Carbon::now()->addDays($expires_in_day);
            $data[("expires_in_" . $expires_in_day . "_days")] = clone $data_query;
            $data[("expires_in_" . $expires_in_day . "_days")] = $data[("expires_in_" . $expires_in_day . "_days")]->whereBetween('passport_expiry_date', [$today, ($query_day->endOfDay() . ' 23:59:59')])->count();
        }
        $data["date_ranges"] = [
            "today_date_range" => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
            "yesterday_data_count_date_range" => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            "next_week_date_range" => [$start_date_of_next_week, ($end_date_of_next_week)],
            "this_week_date_range" => [$start_date_of_this_week, ($end_date_of_this_week)],
            "previous_week_data_count_date_range" => [$start_date_of_previous_week, ($end_date_of_previous_week)],
            "next_month_date_range" => [$start_date_of_next_month, ($end_date_of_next_month)],
            "this_month_date_range" => [$start_date_of_this_month, ($end_date_of_this_month)],
            "previous_month_data_count_date_range" => [$start_date_of_previous_month, ($end_date_of_previous_month)],
        ];

        return $data;
    }
    public function upcoming_visa_expiries(
        $today,
        $start_date_of_next_month,
        $end_date_of_next_month,
        $start_date_of_this_month,
        $end_date_of_this_month,
        $start_date_of_previous_month,
        $end_date_of_previous_month,
        $start_date_of_next_week,
        $end_date_of_next_week,
        $start_date_of_this_week,
        $end_date_of_this_week,
        $start_date_of_previous_week,
        $end_date_of_previous_week,
        $all_manager_department_ids
    ) {

        $issue_date_column = 'visa_issue_date';
        $expiry_date_column = 'visa_expiry_date';


        $employee_visa_history_ids = EmployeeVisaDetailHistory::select('user_id')
            ->where("business_id", auth()->user()->business_id)
            ->whereHas("employee.department_user.department", function ($query) use ($all_manager_department_ids) {
                $query->whereIn("departments.id", $all_manager_department_ids);
            })
            ->whereNotIn('user_id', [auth()->user()->id])
            ->where($issue_date_column, '<', now())
            ->groupBy('user_id')
            ->get()
            ->map(function ($record) use ($issue_date_column, $expiry_date_column) {

                $latest_expired_record = EmployeeVisaDetailHistory::where('user_id', $record->user_id)
                    ->where($issue_date_column, '<', now())
                    ->orderByDesc($expiry_date_column)
                    // ->latest()
                    ->first();

                if ($latest_expired_record) {
                    $current_data = EmployeeVisaDetailHistory::where('user_id', $record->user_id)
                        ->where($expiry_date_column, $latest_expired_record[$expiry_date_column])
                        ->where($issue_date_column, '<', now())
                        ->orderByDesc("id")
                        // ->orderByDesc($issue_date_column)
                        ->first();
                } else {
                    return NULL;
                }


                return $current_data ? $current_data->id : NULL;
            })
            ->filter()->values();




        $data_query  = EmployeeVisaDetailHistory::whereIn('id', $employee_visa_history_ids)
            ->where($expiry_date_column, ">=", today());

        $data["total_data_count"] = $data_query->count();

        $data["today"] = clone $data_query;
        $data["today"] = $data["today"]->whereBetween('visa_expiry_date', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->count();

        $data["yesterday_data_count"] = clone $data_query;
        $data["yesterday_data_count"] = $data["yesterday_data_count"]->whereBetween('visa_expiry_date', [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()])->count();

        $data["next_week"] = clone $data_query;
        $data["next_week"] = $data["next_week"]->whereBetween('visa_expiry_date', [$start_date_of_next_week, ($end_date_of_next_week . ' 23:59:59')])->count();

        $data["this_week"] = clone $data_query;
        $data["this_week"] = $data["this_week"]->whereBetween('visa_expiry_date', [$start_date_of_this_week, ($end_date_of_this_week . ' 23:59:59')])->count();


        $data["next_month"] = clone $data_query;
        $data["next_month"] = $data["next_month"]->whereBetween('visa_expiry_date', [$start_date_of_next_month, ($end_date_of_next_month . ' 23:59:59')])->count();

        $data["this_month"] = clone $data_query;
        $data["this_month"] = $data["this_month"]->whereBetween('visa_expiry_date', [$start_date_of_this_month, ($end_date_of_this_month . ' 23:59:59')])->count();


        $expires_in_days = [15, 30, 60];
        foreach ($expires_in_days as $expires_in_day) {
            $query_day = Carbon::now()->addDays($expires_in_day);
            $data[("expires_in_" . $expires_in_day . "_days")] = clone $data_query;
            $data[("expires_in_" . $expires_in_day . "_days")] = $data[("expires_in_" . $expires_in_day . "_days")]->whereBetween('visa_expiry_date', [$today, ($query_day->endOfDay() . ' 23:59:59')])->count();
        }

        $data["date_ranges"] = [
            "today_date_range" => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
            "yesterday_data_count_date_range" => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            "next_week_date_range" => [$start_date_of_next_week, ($end_date_of_next_week)],
            "this_week_date_range" => [$start_date_of_this_week, ($end_date_of_this_week)],
            "previous_week_data_count_date_range" => [$start_date_of_previous_week, ($end_date_of_previous_week)],
            "next_month_date_range" => [$start_date_of_next_month, ($end_date_of_next_month)],
            "this_month_date_range" => [$start_date_of_this_month, ($end_date_of_this_month)],
            "previous_month_data_count_date_range" => [$start_date_of_previous_month, ($end_date_of_previous_month)],
        ];

        return $data;
    }

    public function upcoming_right_to_work_expiries(
        $today,
        $start_date_of_next_month,
        $end_date_of_next_month,
        $start_date_of_this_month,
        $end_date_of_this_month,
        $start_date_of_previous_month,
        $end_date_of_previous_month,
        $start_date_of_next_week,
        $end_date_of_next_week,
        $start_date_of_this_week,
        $end_date_of_this_week,
        $start_date_of_previous_week,
        $end_date_of_previous_week,
        $all_manager_department_ids
    ) {

        $issue_date_column = 'right_to_work_check_date';
        $expiry_date_column = 'right_to_work_expiry_date';


        $employee_right_to_work_history_ids = EmployeeRightToWorkHistory::select('user_id')
            ->where("business_id", auth()->user()->business_id)
            ->whereHas("employee.department_user.department", function ($query) use ($all_manager_department_ids) {
                $query->whereIn("departments.id", $all_manager_department_ids);
            })
            ->whereNotIn('user_id', [auth()->user()->id])
            ->where($issue_date_column, '<', now())
            ->groupBy('user_id')
            ->get()
            ->map(function ($record) use ($issue_date_column, $expiry_date_column) {

                $latest_expired_record = EmployeeRightToWorkHistory::where('user_id', $record->user_id)
                    ->where($issue_date_column, '<', now())
                    ->orderByDesc($expiry_date_column)
                    // ->latest()
                    ->first();

                if ($latest_expired_record) {
                    $current_data = EmployeeRightToWorkHistory::where('user_id', $record->user_id)
                        ->where($expiry_date_column, $latest_expired_record[$expiry_date_column])
                        ->where($issue_date_column, '<', now())
                        ->orderByDesc("id")
                        // ->orderByDesc($issue_date_column)
                        ->first();
                } else {
                    return NULL;
                }


                return $current_data ? $current_data->id : NULL;
            })
            ->filter()->values();



        $data_query  = EmployeeRightToWorkHistory::whereIn('id', $employee_right_to_work_history_ids)
            ->where($expiry_date_column, ">=", today());


        $data["total_data_count"] = $data_query->count();

        $data["today"] = clone $data_query;
        $data["today"] = $data["today"]->whereBetween('right_to_work_expiry_date', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->count();

        $data["yesterday_data_count"] = clone $data_query;
        $data["yesterday_data_count"] = $data["yesterday_data_count"]->whereBetween('right_to_work_expiry_date', [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()])->count();

        $data["next_week"] = clone $data_query;
        $data["next_week"] = $data["next_week"]->whereBetween('right_to_work_expiry_date', [$start_date_of_next_week, ($end_date_of_next_week . ' 23:59:59')])->count();

        $data["this_week"] = clone $data_query;
        $data["this_week"] = $data["this_week"]->whereBetween('right_to_work_expiry_date', [$start_date_of_this_week, ($end_date_of_this_week . ' 23:59:59')])->count();



        $data["next_month"] = clone $data_query;
        $data["next_month"] = $data["next_month"]->whereBetween('right_to_work_expiry_date', [$start_date_of_next_month, ($end_date_of_next_month . ' 23:59:59')])->count();

        $data["this_month"] = clone $data_query;
        $data["this_month"] = $data["this_month"]->whereBetween('right_to_work_expiry_date', [$start_date_of_this_month, ($end_date_of_this_month . ' 23:59:59')])->count();


        $expires_in_days = [15, 30, 60];
        foreach ($expires_in_days as $expires_in_day) {
            $query_day = Carbon::now()->addDays($expires_in_day);
            $data[("expires_in_" . $expires_in_day . "_days")] = clone $data_query;
            $data[("expires_in_" . $expires_in_day . "_days")] = $data[("expires_in_" . $expires_in_day . "_days")]->whereBetween('right_to_work_expiry_date', [$today, ($query_day->endOfDay() . ' 23:59:59')])->count();
        }

        $data["date_ranges"] = [
            "today_date_range" => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
            "yesterday_data_count_date_range" => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            "next_week_date_range" => [$start_date_of_next_week, ($end_date_of_next_week)],
            "this_week_date_range" => [$start_date_of_this_week, ($end_date_of_this_week)],
            "previous_week_data_count_date_range" => [$start_date_of_previous_week, ($end_date_of_previous_week)],
            "next_month_date_range" => [$start_date_of_next_month, ($end_date_of_next_month)],
            "this_month_date_range" => [$start_date_of_this_month, ($end_date_of_this_month)],
            "previous_month_data_count_date_range" => [$start_date_of_previous_month, ($end_date_of_previous_month)],
        ];

        return $data;
    }

    public function upcoming_sponsorship_expiries(
        $today,
        $start_date_of_next_month,
        $end_date_of_next_month,
        $start_date_of_this_month,
        $end_date_of_this_month,
        $start_date_of_previous_month,
        $end_date_of_previous_month,
        $start_date_of_next_week,
        $end_date_of_next_week,
        $start_date_of_this_week,
        $end_date_of_this_week,
        $start_date_of_previous_week,
        $end_date_of_previous_week,
        $all_manager_department_ids
    ) {

        $issue_date_column = 'date_assigned';
        $expiry_date_column = 'expiry_date';


        $employee_sponsorship_history_ids = EmployeeSponsorshipHistory::select('user_id')
            ->where("business_id", auth()->user()->business_id)
            ->whereHas("employee.department_user.department", function ($query) use ($all_manager_department_ids) {
                $query->whereIn("departments.id", $all_manager_department_ids);
            })
            ->whereNotIn('user_id', [auth()->user()->id])
            ->where($issue_date_column, '<', now())
            ->groupBy('user_id')
            ->get()
            ->map(function ($record) use ($issue_date_column, $expiry_date_column) {
                $latest_expired_record = EmployeeSponsorshipHistory::where('user_id', $record->user_id)
                    ->where($issue_date_column, '<', now())
                    ->orderByDesc($expiry_date_column)
                    // ->latest()
                    ->first();

                if ($latest_expired_record) {
                    $current_data = EmployeeSponsorshipHistory::where('user_id', $record->user_id)
                        ->where($expiry_date_column, $latest_expired_record[$expiry_date_column])
                        ->where($issue_date_column, '<', now())
                        ->orderByDesc("id")
                        // ->orderByDesc($issue_date_column)
                        ->first();
                } else {
                    return NULL;
                }
                return $current_data ? $current_data->id : NULL;
            })
            ->filter()->values();



        $data_query  = EmployeeSponsorshipHistory::whereIn('id', $employee_sponsorship_history_ids)
            ->where($expiry_date_column, ">=", today());


        $data["total_data_count"] = $data_query->count();

        $data["today"] = clone $data_query;
        $data["today"] = $data["today"]->whereBetween('expiry_date', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->count();

        $data["yesterday_data_count"] = clone $data_query;
        $data["yesterday_data_count"] = $data["yesterday_data_count"]->whereBetween('expiry_date', [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()])->count();

        $data["next_week"] = clone $data_query;
        $data["next_week"] = $data["next_week"]->whereBetween('expiry_date', [$start_date_of_next_week, ($end_date_of_next_week . ' 23:59:59')])->count();

        $data["this_week"] = clone $data_query;
        $data["this_week"] = $data["this_week"]->whereBetween('expiry_date', [$start_date_of_this_week, ($end_date_of_this_week . ' 23:59:59')])->count();



        $data["next_month"] = clone $data_query;
        $data["next_month"] = $data["next_month"]->whereBetween('expiry_date', [$start_date_of_next_month, ($end_date_of_next_month . ' 23:59:59')])->count();

        $data["this_month"] = clone $data_query;
        $data["this_month"] = $data["this_month"]->whereBetween('expiry_date', [$start_date_of_this_month, ($end_date_of_this_month . ' 23:59:59')])->count();


        $expires_in_days = [15, 30, 60];
        foreach ($expires_in_days as $expires_in_day) {
            $query_day = Carbon::now()->addDays($expires_in_day);
            $data[("expires_in_" . $expires_in_day . "_days")] = clone $data_query;
            $data[("expires_in_" . $expires_in_day . "_days")] = $data[("expires_in_" . $expires_in_day . "_days")]->whereBetween('expiry_date', [$today, ($query_day->endOfDay() . ' 23:59:59')])->count();
        }


        $data["date_ranges"] = [
            "today_date_range" => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
            "yesterday_data_count_date_range" => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            "next_week_date_range" => [$start_date_of_next_week, ($end_date_of_next_week)],
            "this_week_date_range" => [$start_date_of_this_week, ($end_date_of_this_week)],
            "previous_week_data_count_date_range" => [$start_date_of_previous_week, ($end_date_of_previous_week)],
            "next_month_date_range" => [$start_date_of_next_month, ($end_date_of_next_month)],
            "this_month_date_range" => [$start_date_of_this_month, ($end_date_of_this_month)],
            "previous_month_data_count_date_range" => [$start_date_of_previous_month, ($end_date_of_previous_month)],
        ];

        return $data;
    }

    public function sponsorships(
        $today,
        $start_date_of_next_month,
        $end_date_of_next_month,
        $start_date_of_this_month,
        $end_date_of_this_month,
        $start_date_of_previous_month,
        $end_date_of_previous_month,
        $start_date_of_next_week,
        $end_date_of_next_week,
        $start_date_of_this_week,
        $end_date_of_this_week,
        $start_date_of_previous_week,
        $end_date_of_previous_week,
        $all_manager_department_ids,
        $current_certificate_status
    ) {


        $issue_date_column = 'date_assigned';
        $expiry_date_column = 'expiry_date';

        $employee_sponsorship_history_ids = EmployeeSponsorshipHistory::select('user_id')
            ->where("business_id", auth()->user()->business_id)
            ->whereHas("employee.department_user.department", function ($query) use ($all_manager_department_ids) {
                $query->whereIn("departments.id", $all_manager_department_ids);
            })
            ->whereNotIn('user_id', [auth()->user()->id])
            ->where($issue_date_column, '<', now())
            ->groupBy('user_id')
            ->get()
            ->map(function ($record) use ($issue_date_column, $expiry_date_column) {
                $latest_expired_record = EmployeeSponsorshipHistory::where('user_id', $record->user_id)
                    ->where($issue_date_column, '<', now())
                    ->orderByDesc($expiry_date_column)
                    // ->latest()
                    ->first();

                if ($latest_expired_record) {
                    $current_data = EmployeeSponsorshipHistory::where('user_id', $record->user_id)
                        ->where($expiry_date_column, $latest_expired_record[$expiry_date_column])
                        ->where($issue_date_column, '<', now())
                        ->orderByDesc("id")
                        // ->orderByDesc($issue_date_column)
                        ->first();
                } else {
                    return NULL;
                }
                return $current_data ? $current_data->id : NULL;
            })
            ->filter()->values();



        $data_query  = EmployeeSponsorshipHistory::whereIn('id', $employee_sponsorship_history_ids)
            ->where([
                "current_certificate_status" => $current_certificate_status,
                "business_id" => auth()->user()->business_id
            ]);




        $data["total_data_count"] = $data_query->count();

        $data["today"] = clone $data_query;
        $data["today"] = $data["today"]->whereBetween('expiry_date', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->count();

        $data["next_week"] = clone $data_query;
        $data["next_week"] = $data["next_week"]->whereBetween('expiry_date', [$start_date_of_next_week, ($end_date_of_next_week . ' 23:59:59')])->count();

        $data["this_week"] = clone $data_query;
        $data["this_week"] = $data["this_week"]->whereBetween('expiry_date', [$start_date_of_this_week, ($end_date_of_this_week . ' 23:59:59')])->count();

        $data["previous_week_data_count"] = clone $data_query;
        $data["previous_week_data_count"] = $data["previous_week_data_count"]->whereBetween('expiry_date', [$start_date_of_previous_week, ($end_date_of_previous_week . ' 23:59:59')])->count();

        $data["next_month"] = clone $data_query;
        $data["next_month"] = $data["next_month"]->whereBetween('expiry_date', [$start_date_of_next_month, ($end_date_of_next_month . ' 23:59:59')])->count();

        $data["this_month"] = clone $data_query;
        $data["this_month"] = $data["this_month"]->whereBetween('expiry_date', [$start_date_of_this_month, ($end_date_of_this_month . ' 23:59:59')])->count();

        $data["previous_month_data_count"] = clone $data_query;
        $data["previous_month_data_count"] = $data["previous_month_data_count"]->whereBetween('expiry_date', [$start_date_of_previous_month, ($end_date_of_previous_month . ' 23:59:59')])->count();
        $data["date_ranges"] = [
            "today_date_range" => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
            "yesterday_data_count_date_range" => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            "next_week_date_range" => [$start_date_of_next_week, ($end_date_of_next_week)],
            "this_week_date_range" => [$start_date_of_this_week, ($end_date_of_this_week)],
            "previous_week_data_count_date_range" => [$start_date_of_previous_week, ($end_date_of_previous_week)],
            "next_month_date_range" => [$start_date_of_next_month, ($end_date_of_next_month)],
            "this_month_date_range" => [$start_date_of_this_month, ($end_date_of_this_month)],
            "previous_month_data_count_date_range" => [$start_date_of_previous_month, ($end_date_of_previous_month)],
        ];


        return $data;
    }


    public function upcoming_pension_expiries(
        $today,
        $start_date_of_next_month,
        $end_date_of_next_month,
        $start_date_of_this_month,
        $end_date_of_this_month,
        $start_date_of_previous_month,
        $end_date_of_previous_month,
        $start_date_of_next_week,
        $end_date_of_next_week,
        $start_date_of_this_week,
        $end_date_of_this_week,
        $start_date_of_previous_week,
        $end_date_of_previous_week,
        $all_manager_department_ids


    ) {


        $issue_date_column = 'pension_enrollment_issue_date';
        $expiry_date_column = 'pension_re_enrollment_due_date';


        $employee_pension_history_ids = EmployeePensionHistory::select('id', 'user_id')
            ->whereHas("employee.department_user.department", function ($query) use ($all_manager_department_ids) {
                $query->whereIn("departments.id", $all_manager_department_ids);
            })
            ->whereHas("employee", function ($query) use ($all_manager_department_ids) {
                $query->where("users.pension_eligible", ">", 0);
            })
            ->whereNotIn('user_id', [auth()->user()->id])
            ->where($issue_date_column, '<', now())
            ->whereNotNull($expiry_date_column)
            ->groupBy('user_id')
            ->get()
            ->map(function ($record) use ($issue_date_column, $expiry_date_column) {


                $current_data = EmployeePensionHistory::where('user_id', $record->user_id)
                    ->where("pension_eligible", 1)
                    ->where($issue_date_column, '<', now())
                    ->orderByDesc("id")
                    ->first();

                if (empty($current_data)) {
                    return NULL;
                }


                return $current_data->id;
            })
            ->filter()->values();

        $data_query  = EmployeePensionHistory::whereIn('id', $employee_pension_history_ids)->where($expiry_date_column, ">=", today());




        $data["total_data_count"] = $data_query->count();


        $data["today"] = clone $data_query;
        $data["today"] = $data["today"]->whereBetween($expiry_date_column, [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->count();

        $data["yesterday_data_count"] = clone $data_query;
        $data["yesterday_data_count"] = $data["yesterday_data_count"]->whereBetween($expiry_date_column, [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()])->count();

        $data["next_week"] = clone $data_query;
        $data["next_week"] = $data["next_week"]->whereBetween($expiry_date_column, [$start_date_of_next_week, ($end_date_of_next_week . ' 23:59:59')])->count();

        $data["this_week"] = clone $data_query;
        $data["this_week"] = $data["this_week"]->whereBetween($expiry_date_column, [$start_date_of_this_week, ($end_date_of_this_week . ' 23:59:59')])->count();



        $data["next_month"] = clone $data_query;
        $data["next_month"] = $data["next_month"]->whereBetween($expiry_date_column, [$start_date_of_next_month, ($end_date_of_next_month . ' 23:59:59')])->count();

        $data["this_month"] = clone $data_query;
        $data["this_month"] = $data["this_month"]->whereBetween($expiry_date_column, [$start_date_of_this_month, ($end_date_of_this_month . ' 23:59:59')])->count();


        $expires_in_days = [15, 30, 60];
        foreach ($expires_in_days as $expires_in_day) {
            $query_day = Carbon::now()->addDays($expires_in_day);
            $data[("expires_in_" . $expires_in_day . "_days")] = clone $data_query;
            $data[("expires_in_" . $expires_in_day . "_days")] = $data[("expires_in_" . $expires_in_day . "_days")]->whereBetween($expiry_date_column, [$today, ($query_day->endOfDay() . ' 23:59:59')])->count();
        }


        $data["date_ranges"] = [
            "today_date_range" => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
            "yesterday_data_count_date_range" => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            "next_week_date_range" => [$start_date_of_next_week, ($end_date_of_next_week)],
            "this_week_date_range" => [$start_date_of_this_week, ($end_date_of_this_week)],
            "previous_week_data_count_date_range" => [$start_date_of_previous_week, ($end_date_of_previous_week)],
            "next_month_date_range" => [$start_date_of_next_month, ($end_date_of_next_month)],
            "this_month_date_range" => [$start_date_of_this_month, ($end_date_of_this_month)],
            "previous_month_data_count_date_range" => [$start_date_of_previous_month, ($end_date_of_previous_month)],
        ];

        return $data;
    }
    public function pensions(
        $today,
        $start_date_of_next_month,
        $end_date_of_next_month,
        $start_date_of_this_month,
        $end_date_of_this_month,
        $start_date_of_previous_month,
        $end_date_of_previous_month,
        $start_date_of_next_week,
        $end_date_of_next_week,
        $start_date_of_this_week,
        $end_date_of_this_week,
        $start_date_of_previous_week,
        $end_date_of_previous_week,
        $all_manager_department_ids,
        $status_column,
        $status_value
    ) {


        $issue_date_column = 'pension_enrollment_issue_date';
        $expiry_date_column = 'pension_re_enrollment_due_date';


        $employee_pension_history_ids = EmployeePensionHistory::select('user_id')
            ->whereHas("employee.department_user.department", function ($query) use ($all_manager_department_ids) {
                $query->whereIn("departments.id", $all_manager_department_ids);
            })
            ->whereHas("employee", function ($query) {
                $query->where("users.pension_eligible", ">", 0)
                    ->where("is_active", 1);
            })
            ->whereNotIn('user_id', [auth()->user()->id])
            ->where($issue_date_column, '<', now())
            ->whereNotNull($expiry_date_column)
            ->groupBy('user_id')
            ->get()
            ->map(function ($record) use ($issue_date_column, $expiry_date_column) {

                // $latest_expired_record = EmployeePensionHistory::where('user_id', $record->user_id)
                // ->where($issue_date_column, '<', now())
                // ->where(function($query) use($expiry_date_column) {
                //    $query->whereNotNull($expiry_date_column)
                //    ->orWhereNull($expiry_date_column);
                // })
                // ->orderByRaw("ISNULL($expiry_date_column), $expiry_date_column DESC")
                // ->orderBy('id', 'DESC')
                // // ->orderByDesc($expiry_date_column)
                // // ->latest()
                // ->first();

                // if($latest_expired_record->expiry_date_column) {
                //      $current_data = EmployeePensionHistory::where('user_id', $record->user_id)
                //     ->where($expiry_date_column, $latest_expired_record->expiry_date_column)
                //     ->orderByDesc($issue_date_column)
                //     ->first();
                // } else {
                //    return NULL;
                // }

                $current_data = EmployeePensionHistory::where('user_id', $record->user_id)
                    ->where("pension_eligible", 1)
                    ->where($issue_date_column, '<', now())
                    ->orderByDesc("id")
                    ->first();

                if ($current_data) {
                    return NULL;
                }



                return $current_data->id;
            })
            ->filter()->values();




        $data_query  = EmployeePensionHistory::whereIn('id', $employee_pension_history_ids)
            ->when(!empty($status_column), function ($query) use ($status_column, $status_value) {
                $query->where($status_column, $status_value);
            });





        $data["total_data_count"] = $data_query->count();

        $data["today"] = clone $data_query;
        $data["today"] = $data["today"]->whereBetween($expiry_date_column, [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->count();

        $data["next_week"] = clone $data_query;
        $data["next_week"] = $data["next_week"]->whereBetween($expiry_date_column, [$start_date_of_next_week, ($end_date_of_next_week . ' 23:59:59')])->count();

        $data["this_week"] = clone $data_query;
        $data["this_week"] = $data["this_week"]->whereBetween($expiry_date_column, [$start_date_of_this_week, ($end_date_of_this_week . ' 23:59:59')])->count();

        $data["previous_week_data_count"] = clone $data_query;
        $data["previous_week_data_count"] = $data["previous_week_data_count"]->whereBetween($expiry_date_column, [$start_date_of_previous_week, ($end_date_of_previous_week . ' 23:59:59')])->count();

        $data["next_month"] = clone $data_query;
        $data["next_month"] = $data["next_month"]->whereBetween($expiry_date_column, [$start_date_of_next_month, ($end_date_of_next_month . ' 23:59:59')])->count();

        $data["this_month"] = clone $data_query;
        $data["this_month"] = $data["this_month"]->whereBetween($expiry_date_column, [$start_date_of_this_month, ($end_date_of_this_month . ' 23:59:59')])->count();

        $data["previous_month_data_count"] = clone $data_query;
        $data["previous_month_data_count"] = $data["previous_month_data_count"]->whereBetween($expiry_date_column, [$start_date_of_previous_month, ($end_date_of_previous_month . ' 23:59:59')])->count();

        $data["date_ranges"] = [
            "today_date_range" => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
            "yesterday_data_count_date_range" => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            "next_week_date_range" => [$start_date_of_next_week, ($end_date_of_next_week)],
            "this_week_date_range" => [$start_date_of_this_week, ($end_date_of_this_week)],
            "previous_week_data_count_date_range" => [$start_date_of_previous_week, ($end_date_of_previous_week)],
            "next_month_date_range" => [$start_date_of_next_month, ($end_date_of_next_month)],
            "this_month_date_range" => [$start_date_of_this_month, ($end_date_of_this_month)],
            "previous_month_data_count_date_range" => [$start_date_of_previous_month, ($end_date_of_previous_month)],
        ];


        return $data;
    }


    public function getEmploymentStatuses()
    {
        $created_by  = NULL;
        if (auth()->user()->business) {
            $created_by = auth()->user()->business->created_by;
        }
        $employmentStatuses = EmploymentStatus::when(empty(auth()->user()->business_id), function ($query) use ($created_by) {
                if (auth()->user()->hasRole('superadmin')) {
                    return $query->where('employment_statuses.business_id', NULL)
                        ->where('employment_statuses.is_default', 1)
                        ->where('employment_statuses.is_active', 1);
                } else {
                    return $query->where('employment_statuses.business_id', NULL)
                        ->where('employment_statuses.is_default', 1)
                        ->where('employment_statuses.is_active', 1)
                        ->whereDoesntHave("disabled", function ($q) {
                            $q->whereIn("disabled_employment_statuses.created_by", [auth()->user()->id]);
                        })

                        ->orWhere(function ($query) {
                            $query->where('employment_statuses.business_id', NULL)
                                ->where('employment_statuses.is_default', 0)
                                ->where('employment_statuses.created_by', auth()->user()->id)
                                ->where('employment_statuses.is_active', 1);
                        });
                }
            })
            ->when(!empty(auth()->user()->business_id), function ($query) use ($created_by) {
                return $query->where('employment_statuses.business_id', NULL)
                    ->where('employment_statuses.is_default', 1)
                    ->where('employment_statuses.is_active', 1)
                    ->whereDoesntHave("disabled", function ($q) use ($created_by) {
                        $q->whereIn("disabled_employment_statuses.created_by", [$created_by]);
                    })
                    ->whereDoesntHave("disabled", function ($q) {
                        $q->whereIn("disabled_employment_statuses.business_id", [auth()->user()->business_id]);
                    })

                    ->orWhere(function ($query) use ($created_by) {
                        $query->where('employment_statuses.business_id', NULL)
                            ->where('employment_statuses.is_default', 0)
                            ->where('employment_statuses.created_by', $created_by)
                            ->where('employment_statuses.is_active', 1)
                            ->whereDoesntHave("disabled", function ($q) {
                                $q->whereIn("disabled_employment_statuses.business_id", [auth()->user()->business_id]);
                            });
                    })
                    ->orWhere(function ($query) {
                        $query->where('employment_statuses.business_id', auth()->user()->business_id)
                            ->where('employment_statuses.is_default', 0)
                            ->where('employment_statuses.is_active', 1);
                    });
            })->get();

        return $employmentStatuses;
    }

    public function employees_by_employment_status(
        $today,
        $start_date_of_next_month,
        $end_date_of_next_month,
        $start_date_of_this_month,
        $end_date_of_this_month,
        $start_date_of_previous_month,
        $end_date_of_previous_month,
        $start_date_of_next_week,
        $end_date_of_next_week,
        $start_date_of_this_week,
        $end_date_of_this_week,
        $start_date_of_previous_week,
        $end_date_of_previous_week,
        $all_manager_department_ids,
        $employment_status_id
    ) {

        $data_query  = User::whereHas("department_user.department", function ($query) use ($all_manager_department_ids) {
            $query->whereIn("departments.id", $all_manager_department_ids);
        })
            ->whereNotIn('id', [auth()->user()->id])
            ->where([
                "employment_status_id" => $employment_status_id
            ])
            // ->where('is_in_employee', 1)
            // ->where('is_active', 1)
        ;
        $data["total_data"] = $data_query->get();

        $data["total_data_count"] = $data_query->count();

        $data["today"] = clone $data_query;
        $data["today"] = $data["today"]->whereBetween('users.created_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->count();


        $data["yesterday_data_count"] = clone $data_query;
        $data["yesterday_data_count"] = $data["yesterday_data_count"]->whereBetween('users.created_at', [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()])->count();



        $data["this_week"] = clone $data_query;
        $data["this_week"] = $data["this_week"]->whereBetween('created_at', [$start_date_of_this_week, ($end_date_of_this_week . ' 23:59:59')])->count();




        $data["previous_week_data_count"] = clone $data_query;
        $data["previous_week_data_count"] = $data["previous_week_data_count"]->whereBetween('created_at', [$start_date_of_previous_week, ($end_date_of_previous_week . ' 23:59:59')])->count();


        $data["this_month"] = clone $data_query;
        $data["this_month"] = $data["this_month"]->whereBetween('created_at', [$start_date_of_this_month, ($end_date_of_this_month . ' 23:59:59')])->count();


        $data["previous_month_data_count"] = clone $data_query;
        $data["previous_month_data_count"] = $data["previous_month_data_count"]->whereBetween('created_at', [$start_date_of_previous_month, ($end_date_of_previous_month . ' 23:59:59')])->count();


        $data["date_ranges"] = [
            "today_date_range" => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
            "yesterday_data_count_date_range" => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            "next_week_date_range" => [$start_date_of_next_week, ($end_date_of_next_week)],
            "this_week_date_range" => [$start_date_of_this_week, ($end_date_of_this_week)],
            "previous_week_data_count_date_range" => [$start_date_of_previous_week, ($end_date_of_previous_week)],
            "next_month_date_range" => [$start_date_of_next_month, ($end_date_of_next_month)],
            "this_month_date_range" => [$start_date_of_this_month, ($end_date_of_this_month)],
            "previous_month_data_count_date_range" => [$start_date_of_previous_month, ($end_date_of_previous_month)],
        ];

        return $data;
    }


    public function getBusinessManagerDashboardData(Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            $business_id = auth()->user()->business_id;


            if (!$business_id) {
                return response()->json([
                    "message" => "You are not a business user"
                ], 401);
            }
            $today = today();



            $start_date_of_next_month = Carbon::now()->startOfMonth()->addMonth(1);
            $end_date_of_next_month = Carbon::now()->endOfMonth()->addMonth(1);
            $start_date_of_this_month = Carbon::now()->startOfMonth();
            $end_date_of_this_month = Carbon::now()->endOfMonth();
            $start_date_of_previous_month = Carbon::now()->startOfMonth()->subMonth(1);
            $end_date_of_previous_month = Carbon::now()->startOfMonth()->subDay(1);

            $start_date_of_next_week = Carbon::now()->startOfWeek()->addWeek(1);
            $end_date_of_next_week = Carbon::now()->endOfWeek()->addWeek(1);
            $start_date_of_this_week = Carbon::now()->startOfWeek();
            $end_date_of_this_week = Carbon::now()->endOfWeek();
            $start_date_of_previous_week = Carbon::now()->startOfWeek()->subWeek(1);
            $end_date_of_previous_week = Carbon::now()->endOfWeek()->subWeek(1);



            $all_manager_department_ids = $this->get_all_departments_of_manager();




            //  $data["total_employee"] = $this->total_employee(
            //      $today,
            //      $start_date_of_next_month,
            //      $end_date_of_next_month,
            //      $start_date_of_this_month,
            //      $end_date_of_this_month,
            //      $start_date_of_previous_month,
            //      $end_date_of_previous_month,
            //      $start_date_of_next_week,
            //      $end_date_of_next_week,
            //      $start_date_of_this_week,
            //      $end_date_of_this_week,
            //      $start_date_of_previous_week,
            //      $end_date_of_previous_week,
            //      $all_manager_department_ids
            //  );





            //  $data["open_roles"] = $this->open_roles(
            //     $today,
            //     $start_date_of_next_month,
            //     $end_date_of_next_month,
            //     $start_date_of_this_month,
            //     $end_date_of_this_month,
            //     $start_date_of_previous_month,
            //     $end_date_of_previous_month,
            //     $start_date_of_next_week,
            //     $end_date_of_next_week,
            //     $start_date_of_this_week,
            //     $end_date_of_this_week,
            //     $start_date_of_previous_week,
            //     $end_date_of_previous_week,
            //     $all_manager_department_ids
            // );



            $data["absent"] = $this->absent(
                $today,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                $all_manager_department_ids,

            );


            $data["present"] = $this->present(
                $today,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                $all_manager_department_ids,

            );


            $data["leaves"] = $this->leaves(
                $today,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                $all_manager_department_ids,
            );


            $data =    array_merge($data, $this->leavesStructure2(
                $today,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                $all_manager_department_ids
            ));





            $data =    array_merge($data, $this->pensionsStructure2(
                $today,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                $all_manager_department_ids
            ));






            $data["holidays"] = $this->holidays(
                $today,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                $all_manager_department_ids,
            );




            $data["expiries"] = $this->expiries(
                $today,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                $all_manager_department_ids,
            );





            $data["widgets"]["employee_on_holiday"] = $this->employee_on_holiday(
                $today,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                $all_manager_department_ids,

            );



            $data["widgets"]["employee_on_holiday"]["id"] = 2;


            $data["widgets"]["employee_on_holiday"]["widget_name"] = "employee_on_holiday";
            $data["widgets"]["employee_on_holiday"]["widget_type"] = "default";
            $data["widgets"]["employee_on_holiday"]["route"] =  '/employee/all-employees?is_on_holiday=1&';






            $data["widgets"]["upcoming_passport_expiries"] = $this->upcoming_passport_expiries(
                $today,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                $all_manager_department_ids
            );










            $data["widgets"]["upcoming_passport_expiries"]["widget_name"] = "upcoming_passport_expiries";
            $data["widgets"]["upcoming_passport_expiries"]["widget_type"] = "multiple_upcoming_days";
            $data["widgets"]["upcoming_passport_expiries"]["route"] = "/employee/all-employees?upcoming_expiries=passport&";







            $data["widgets"]["upcoming_visa_expiries"] = $this->upcoming_visa_expiries(
                $today,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                $all_manager_department_ids
            );







            $data["widgets"]["upcoming_visa_expiries"]["widget_name"] = "upcoming_visa_expiries";
            $data["widgets"]["upcoming_visa_expiries"]["widget_type"] = "multiple_upcoming_days";


            $data["widgets"]["upcoming_visa_expiries"]["route"] = "/employee/all-employees?upcoming_expiries=visa&";





            $data["widgets"]["upcoming_right_to_work_expiries"] = $this->upcoming_right_to_work_expiries(
                $today,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                $all_manager_department_ids
            );




            $data["widgets"]["upcoming_right_to_work_expiries"]["widget_name"] = "upcoming_right_to_work_expiries";
            $data["widgets"]["upcoming_right_to_work_expiries"]["widget_type"] = "multiple_upcoming_days";
            $data["widgets"]["upcoming_right_to_work_expiries"]["route"] = "/employee/all-employees?upcoming_expiries=right_to_work&";



            $data["widgets"]["upcoming_sponsorship_expiries"] = $this->upcoming_sponsorship_expiries(
                $today,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                $all_manager_department_ids
            );




            $data["widgets"]["upcoming_sponsorship_expiries"]["widget_name"] = "upcoming_sponsorship_expiries";
            $data["widgets"]["upcoming_sponsorship_expiries"]["widget_type"] = "multiple_upcoming_days";
            $data["widgets"]["upcoming_sponsorship_expiries"]["route"] = "/employee/all-employees?upcoming_expiries=sponsorship&";



            $sponsorship_statuses = ['unassigned', 'assigned', 'visa_applied', 'visa_rejected', 'visa_grantes', 'withdrawal'];
            foreach ($sponsorship_statuses as $sponsorship_status) {
                $data["widgets"][($sponsorship_status . "_sponsorships")] = $this->sponsorships(
                    $today,
                    $start_date_of_next_month,
                    $end_date_of_next_month,
                    $start_date_of_this_month,
                    $end_date_of_this_month,
                    $start_date_of_previous_month,
                    $end_date_of_previous_month,
                    $start_date_of_next_week,
                    $end_date_of_next_week,
                    $start_date_of_this_week,
                    $end_date_of_this_week,
                    $start_date_of_previous_week,
                    $end_date_of_previous_week,
                    $all_manager_department_ids,
                    $sponsorship_status
                );





                $data["widgets"][($sponsorship_status . "_sponsorships")]["widget_name"] = ($sponsorship_status . "_sponsorships");

                $data["widgets"][($sponsorship_status . "_sponsorships")]["widget_type"] = "default";
                $data["widgets"][($sponsorship_status . "_sponsorships")]["route"] = '/employee/all-employees?sponsorship_status=' . $sponsorship_status . "&";
            }




            $data["widgets"]["upcoming_pension_expiries"] = $this->upcoming_pension_expiries(
                $today,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                $all_manager_department_ids,

            );



            $data["widgets"]["upcoming_pension_expiries"]["widget_name"] = "upcoming_pension_expiries";

            $data["widgets"]["upcoming_pension_expiries"]["widget_type"] = "multiple_upcoming_days";

            $data["widgets"]["upcoming_pension_expiries"]["route"] = "/employee/all-employees?upcoming_expiries=pension&";


            $pension_statuses = ["opt_in", "opt_out"];
            foreach ($pension_statuses as $pension_status) {
                $data["widgets"][($pension_status . "_pensions")] = $this->pensions(
                    $today,
                    $start_date_of_next_month,
                    $end_date_of_next_month,
                    $start_date_of_this_month,
                    $end_date_of_this_month,
                    $start_date_of_previous_month,
                    $end_date_of_previous_month,
                    $start_date_of_next_week,
                    $end_date_of_next_week,
                    $start_date_of_this_week,
                    $end_date_of_this_week,
                    $start_date_of_previous_week,
                    $end_date_of_previous_week,
                    $all_manager_department_ids,
                    "pension_scheme_status",
                    $pension_status
                );



                $data["widgets"][($pension_status . "_pensions")]["widget_name"] = ($pension_status . "_pensions");
                $data["widgets"][($pension_status . "_pensions")]["widget_type"] = "default";
                $data["widgets"][($pension_status . "_pensions")]["route"] = '/employee/all-employees?pension_scheme_status=' . $pension_status . "&";
            }

            $employment_statuses = $this->getEmploymentStatuses();

            foreach ($employment_statuses as $employment_status) {
                $data["widgets"]["emplooyment_status_wise"]["data"][($employment_status->name . "_employees")] = $this->employees_by_employment_status(
                    $today,
                    $start_date_of_next_month,
                    $end_date_of_next_month,
                    $start_date_of_this_month,
                    $end_date_of_this_month,
                    $start_date_of_previous_month,
                    $end_date_of_previous_month,
                    $start_date_of_next_week,
                    $end_date_of_next_week,
                    $start_date_of_this_week,
                    $end_date_of_this_week,
                    $start_date_of_previous_week,
                    $end_date_of_previous_week,
                    $all_manager_department_ids,
                    $employment_status->id
                );


                $data["widgets"]["emplooyment_status_wise"]["widget_name"] = "employment_status_wise_employee";
                $data["widgets"]["emplooyment_status_wise"]["widget_type"] = "graph";

                $data["widgets"]["emplooyment_status_wise"]["route"] = ('/employee/?status=' . $employment_status->name . "&");
            }


            return response()->json($data, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }




    public function getBusinessManagerDashboardDataTotalEmployee($duration, Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");


            $durations = ['total', 'today', 'this_month', 'this_week'];
            if (!in_array($duration, $durations)) {
                $error =  [
                    "message" => "The given data was invalid.",
                    "errors" => ["duration" => ["Valid Durations are 'total',today,'this_month', 'this_week' "]]
                ];
                throw new Exception(json_encode($error), 422);
            }






            $business_id = auth()->user()->business_id;


            if (!$business_id) {
                return response()->json([
                    "message" => "You are not a business user"
                ], 401);
            }
            $today = today();



            $start_date_of_next_month = Carbon::now()->startOfMonth()->addMonth(1);
            $end_date_of_next_month = Carbon::now()->endOfMonth()->addMonth(1);
            $start_date_of_this_month = Carbon::now()->startOfMonth();
            $end_date_of_this_month = Carbon::now()->endOfMonth();
            $start_date_of_previous_month = Carbon::now()->startOfMonth()->subMonth(1);
            $end_date_of_previous_month = Carbon::now()->startOfMonth()->subDay(1);

            $start_date_of_next_week = Carbon::now()->startOfWeek()->addWeek(1);
            $end_date_of_next_week = Carbon::now()->endOfWeek()->addWeek(1);
            $start_date_of_this_week = Carbon::now()->startOfWeek();
            $end_date_of_this_week = Carbon::now()->endOfWeek();
            $start_date_of_previous_week = Carbon::now()->startOfWeek()->subWeek(1);
            $end_date_of_previous_week = Carbon::now()->endOfWeek()->subWeek(1);



            $all_manager_department_ids = $this->get_all_departments_of_manager();




            $data["total_employee"] = $this->total_employee(
                $today,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                $all_manager_department_ids,
                $duration
            );


            return response()->json($data, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }






    public function getBusinessManagerDashboardDataOpenRoles($duration, Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            $business_id = auth()->user()->business_id;

            $durations = ['today', 'this_month', 'this_week'];
            if (!in_array($duration, $durations)) {
                $error =  [
                    "message" => "The given data was invalid.",
                    "errors" => ["duration" => ["Valid Durations are 'total',today,'this_month', 'this_week' "]]
                ];
                throw new Exception(json_encode($error), 422);
            }


            if (!$business_id) {
                return response()->json([
                    "message" => "You are not a business user"
                ], 401);
            }
            $today = today();



            $start_date_of_next_month = Carbon::now()->startOfMonth()->addMonth(1);
            $end_date_of_next_month = Carbon::now()->endOfMonth()->addMonth(1);
            $start_date_of_this_month = Carbon::now()->startOfMonth();
            $end_date_of_this_month = Carbon::now()->endOfMonth();
            $start_date_of_previous_month = Carbon::now()->startOfMonth()->subMonth(1);
            $end_date_of_previous_month = Carbon::now()->startOfMonth()->subDay(1);

            $start_date_of_next_week = Carbon::now()->startOfWeek()->addWeek(1);
            $end_date_of_next_week = Carbon::now()->endOfWeek()->addWeek(1);
            $start_date_of_this_week = Carbon::now()->startOfWeek();
            $end_date_of_this_week = Carbon::now()->endOfWeek();
            $start_date_of_previous_week = Carbon::now()->startOfWeek()->subWeek(1);
            $end_date_of_previous_week = Carbon::now()->endOfWeek()->subWeek(1);



            $all_manager_department_ids = $this->get_all_departments_of_manager();



            $data["open_roles"] = $this->open_roles(
                $today,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                $all_manager_department_ids,
                $duration
            );









            return response()->json($data, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }


    public function getBusinessManagerDashboardDataOpenRolesAndTotalEmployee(Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            $business_id = auth()->user()->business_id;


            if (!$business_id) {
                return response()->json([
                    "message" => "You are not a business user"
                ], 401);
            }
            $today = today();



            $start_date_of_next_month = Carbon::now()->startOfMonth()->addMonth(1);
            $end_date_of_next_month = Carbon::now()->endOfMonth()->addMonth(1);
            $start_date_of_this_month = Carbon::now()->startOfMonth();
            $end_date_of_this_month = Carbon::now()->endOfMonth();
            $start_date_of_previous_month = Carbon::now()->startOfMonth()->subMonth(1);
            $end_date_of_previous_month = Carbon::now()->startOfMonth()->subDay(1);

            $start_date_of_next_week = Carbon::now()->startOfWeek()->addWeek(1);
            $end_date_of_next_week = Carbon::now()->endOfWeek()->addWeek(1);
            $start_date_of_this_week = Carbon::now()->startOfWeek();
            $end_date_of_this_week = Carbon::now()->endOfWeek();
            $start_date_of_previous_week = Carbon::now()->startOfWeek()->subWeek(1);
            $end_date_of_previous_week = Carbon::now()->endOfWeek()->subWeek(1);



            $all_manager_department_ids = $this->get_all_departments_of_manager();



            $data["open_roles"] = $this->open_roles(
                $today,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                $all_manager_department_ids,
                "today"
            );

            $data["total_employee"] = $this->total_employee(
                $today,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                $all_manager_department_ids,
                "today"
            );




            return response()->json($data, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }



    public function getBusinessManagerDashboardDataAbsent(Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            $business_id = auth()->user()->business_id;


            if (!$business_id) {
                return response()->json([
                    "message" => "You are not a business user"
                ], 401);
            }
            $today = today();



            $start_date_of_next_month = Carbon::now()->startOfMonth()->addMonth(1);
            $end_date_of_next_month = Carbon::now()->endOfMonth()->addMonth(1);
            $start_date_of_this_month = Carbon::now()->startOfMonth();
            $end_date_of_this_month = Carbon::now()->endOfMonth();
            $start_date_of_previous_month = Carbon::now()->startOfMonth()->subMonth(1);
            $end_date_of_previous_month = Carbon::now()->startOfMonth()->subDay(1);

            $start_date_of_next_week = Carbon::now()->startOfWeek()->addWeek(1);
            $end_date_of_next_week = Carbon::now()->endOfWeek()->addWeek(1);
            $start_date_of_this_week = Carbon::now()->startOfWeek();
            $end_date_of_this_week = Carbon::now()->endOfWeek();
            $start_date_of_previous_week = Carbon::now()->startOfWeek()->subWeek(1);
            $end_date_of_previous_week = Carbon::now()->endOfWeek()->subWeek(1);



            $all_manager_department_ids = $this->get_all_departments_of_manager();






            $data["absent"] = $this->absent(
                $today,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                $all_manager_department_ids,

            );



            return response()->json($data, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }







    public function getBusinessManagerDashboardDataPresent(Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            $business_id = auth()->user()->business_id;


            if (!$business_id) {
                return response()->json([
                    "message" => "You are not a business user"
                ], 401);
            }
            $today = today();



            $start_date_of_next_month = Carbon::now()->startOfMonth()->addMonth(1);
            $end_date_of_next_month = Carbon::now()->endOfMonth()->addMonth(1);
            $start_date_of_this_month = Carbon::now()->startOfMonth();
            $end_date_of_this_month = Carbon::now()->endOfMonth();
            $start_date_of_previous_month = Carbon::now()->startOfMonth()->subMonth(1);
            $end_date_of_previous_month = Carbon::now()->startOfMonth()->subDay(1);

            $start_date_of_next_week = Carbon::now()->startOfWeek()->addWeek(1);
            $end_date_of_next_week = Carbon::now()->endOfWeek()->addWeek(1);
            $start_date_of_this_week = Carbon::now()->startOfWeek();
            $end_date_of_this_week = Carbon::now()->endOfWeek();
            $start_date_of_previous_week = Carbon::now()->startOfWeek()->subWeek(1);
            $end_date_of_previous_week = Carbon::now()->endOfWeek()->subWeek(1);



            $all_manager_department_ids = $this->get_all_departments_of_manager();

            $data["present"] = $this->present(
                $today,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                $all_manager_department_ids,

            );

            return response()->json($data, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }


    public function getBusinessManagerDashboardDataLeavesByStatus($status, $duration, Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            $business_id = auth()->user()->business_id;



            if (!$business_id) {
                return response()->json([
                    "message" => "You are not a business user"
                ], 401);
            }
            $today = today();

            $durations = ['today', 'this_month', 'this_week'];
            if (!in_array($duration, $durations)) {
                $error =  [
                    "message" => "The given data was invalid.",
                    "errors" => ["duration" => ["Valid Durations are 'total',today,'this_month', 'this_week' "]]
                ];
                throw new Exception(json_encode($error), 422);
            }



            $start_date_of_next_month = Carbon::now()->startOfMonth()->addMonth(1);
            $end_date_of_next_month = Carbon::now()->endOfMonth()->addMonth(1);
            $start_date_of_this_month = Carbon::now()->startOfMonth();
            $end_date_of_this_month = Carbon::now()->endOfMonth();
            $start_date_of_previous_month = Carbon::now()->startOfMonth()->subMonth(1);
            $end_date_of_previous_month = Carbon::now()->startOfMonth()->subDay(1);

            $start_date_of_next_week = Carbon::now()->startOfWeek()->addWeek(1);
            $end_date_of_next_week = Carbon::now()->endOfWeek()->addWeek(1);
            $start_date_of_this_week = Carbon::now()->startOfWeek();
            $end_date_of_this_week = Carbon::now()->endOfWeek();
            $start_date_of_previous_week = Carbon::now()->startOfWeek()->subWeek(1);
            $end_date_of_previous_week = Carbon::now()->endOfWeek()->subWeek(1);



            $all_manager_department_ids = $this->get_all_departments_of_manager();






            $data =    $this->getLeavesStructure3(
                $today,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                $all_manager_department_ids,
                $status,
                $duration


            );


            return response()->json($data, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }


    public function getBusinessManagerDashboardDataHolidaysByStatus($status, $duration, Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            $business_id = auth()->user()->business_id;


            if (!$business_id) {
                return response()->json([
                    "message" => "You are not a business user"
                ], 401);
            }
            $today = today();

            $durations = ['today', 'this_month', 'this_week'];
            if (!in_array($duration, $durations)) {
                $error =  [
                    "message" => "The given data was invalid.",
                    "errors" => ["duration" => ["Valid Durations are 'total',today,'this_month', 'this_week' "]]
                ];
                throw new Exception(json_encode($error), 422);
            }

            $start_date_of_next_month = Carbon::now()->startOfMonth()->addMonth(1);
            $end_date_of_next_month = Carbon::now()->endOfMonth()->addMonth(1);
            $start_date_of_this_month = Carbon::now()->startOfMonth();
            $end_date_of_this_month = Carbon::now()->endOfMonth();
            $start_date_of_previous_month = Carbon::now()->startOfMonth()->subMonth(1);
            $end_date_of_previous_month = Carbon::now()->startOfMonth()->subDay(1);

            $start_date_of_next_week = Carbon::now()->startOfWeek()->addWeek(1);
            $end_date_of_next_week = Carbon::now()->endOfWeek()->addWeek(1);
            $start_date_of_this_week = Carbon::now()->startOfWeek();
            $end_date_of_this_week = Carbon::now()->endOfWeek();
            $start_date_of_previous_week = Carbon::now()->startOfWeek()->subWeek(1);
            $end_date_of_previous_week = Carbon::now()->endOfWeek()->subWeek(1);



            $all_manager_department_ids = $this->get_all_departments_of_manager();






            $data =    $this->getLeavesStructure3(
                $today,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                $all_manager_department_ids,
                $status,
                $duration


            );


            return response()->json($data, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }










    public function getBusinessManagerDashboardDataHolidays(Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            $business_id = auth()->user()->business_id;


            if (!$business_id) {
                return response()->json([
                    "message" => "You are not a business user"
                ], 401);
            }
            $today = today();



            $start_date_of_next_month = Carbon::now()->startOfMonth()->addMonth(1);
            $end_date_of_next_month = Carbon::now()->endOfMonth()->addMonth(1);
            $start_date_of_this_month = Carbon::now()->startOfMonth();
            $end_date_of_this_month = Carbon::now()->endOfMonth();
            $start_date_of_previous_month = Carbon::now()->startOfMonth()->subMonth(1);
            $end_date_of_previous_month = Carbon::now()->startOfMonth()->subDay(1);

            $start_date_of_next_week = Carbon::now()->startOfWeek()->addWeek(1);
            $end_date_of_next_week = Carbon::now()->endOfWeek()->addWeek(1);
            $start_date_of_this_week = Carbon::now()->startOfWeek();
            $end_date_of_this_week = Carbon::now()->endOfWeek();
            $start_date_of_previous_week = Carbon::now()->startOfWeek()->subWeek(1);
            $end_date_of_previous_week = Carbon::now()->endOfWeek()->subWeek(1);



            $all_manager_department_ids = $this->get_all_departments_of_manager();



            $data["holidays"] = $this->holidays(
                $today,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                $all_manager_department_ids,
            );



            return response()->json($data, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }


    public function getBusinessManagerDashboardDataLeaves(Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            $business_id = auth()->user()->business_id;


            if (!$business_id) {
                return response()->json([
                    "message" => "You are not a business user"
                ], 401);
            }
            $today = today();



            $start_date_of_next_month = Carbon::now()->startOfMonth()->addMonth(1);
            $end_date_of_next_month = Carbon::now()->endOfMonth()->addMonth(1);
            $start_date_of_this_month = Carbon::now()->startOfMonth();
            $end_date_of_this_month = Carbon::now()->endOfMonth();
            $start_date_of_previous_month = Carbon::now()->startOfMonth()->subMonth(1);
            $end_date_of_previous_month = Carbon::now()->startOfMonth()->subDay(1);

            $start_date_of_next_week = Carbon::now()->startOfWeek()->addWeek(1);
            $end_date_of_next_week = Carbon::now()->endOfWeek()->addWeek(1);
            $start_date_of_this_week = Carbon::now()->startOfWeek();
            $end_date_of_this_week = Carbon::now()->endOfWeek();
            $start_date_of_previous_week = Carbon::now()->startOfWeek()->subWeek(1);
            $end_date_of_previous_week = Carbon::now()->endOfWeek()->subWeek(1);



            $all_manager_department_ids = $this->get_all_departments_of_manager();



            $data["leaves"] = $this->leaves(
                $today,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                $all_manager_department_ids,
            );



            return response()->json($data, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }





    public function getBusinessManagerDashboardDataLeavesAndHolidays(Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            $business_id = auth()->user()->business_id;


            if (!$business_id) {
                return response()->json([
                    "message" => "You are not a business user"
                ], 401);
            }
            $today = today();



            $start_date_of_next_month = Carbon::now()->startOfMonth()->addMonth(1);
            $end_date_of_next_month = Carbon::now()->endOfMonth()->addMonth(1);
            $start_date_of_this_month = Carbon::now()->startOfMonth();
            $end_date_of_this_month = Carbon::now()->endOfMonth();
            $start_date_of_previous_month = Carbon::now()->startOfMonth()->subMonth(1);
            $end_date_of_previous_month = Carbon::now()->startOfMonth()->subDay(1);

            $start_date_of_next_week = Carbon::now()->startOfWeek()->addWeek(1);
            $end_date_of_next_week = Carbon::now()->endOfWeek()->addWeek(1);
            $start_date_of_this_week = Carbon::now()->startOfWeek();
            $end_date_of_this_week = Carbon::now()->endOfWeek();
            $start_date_of_previous_week = Carbon::now()->startOfWeek()->subWeek(1);
            $end_date_of_previous_week = Carbon::now()->endOfWeek()->subWeek(1);



            $all_manager_department_ids = $this->get_all_departments_of_manager();



            $data["leaves"] = $this->getLeavesStructure4(
                $today,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                $all_manager_department_ids,
            );

            $data["holidays"] = $this->getHolidaysStructure4(
                $today,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                $all_manager_department_ids,
            );



            return response()->json($data, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }





    public function getBusinessManagerDashboardDataPensionExpiries($duration, Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            $business_id = auth()->user()->business_id;

            $durations = ['today', 'this_month', 'previous_month', 'next_month', 'this_week', 'previous_week', 'next_week'];
            if (!in_array($duration, $durations)) {
                $error =  [
                    "message" => "The given data was invalid.",
                    "errors" => ["duration" => ["Valid Durations are 'today','this_month', 'previous_month', 'next_month' ,'this_week', 'previous_week','next_week' "]]
                ];
                throw new Exception(json_encode($error), 422);
            }



            if (!$business_id) {
                return response()->json([
                    "message" => "You are not a business user"
                ], 401);
            }
            $today = today();



            $start_date_of_next_month = Carbon::now()->startOfMonth()->addMonth(1);
            $end_date_of_next_month = Carbon::now()->endOfMonth()->addMonth(1);
            $start_date_of_this_month = Carbon::now()->startOfMonth();
            $end_date_of_this_month = Carbon::now()->endOfMonth();
            $start_date_of_previous_month = Carbon::now()->startOfMonth()->subMonth(1);
            $end_date_of_previous_month = Carbon::now()->startOfMonth()->subDay(1);

            $start_date_of_next_week = Carbon::now()->startOfWeek()->addWeek(1);
            $end_date_of_next_week = Carbon::now()->endOfWeek()->addWeek(1);
            $start_date_of_this_week = Carbon::now()->startOfWeek();
            $end_date_of_this_week = Carbon::now()->endOfWeek();
            $start_date_of_previous_week = Carbon::now()->startOfWeek()->subWeek(1);
            $end_date_of_previous_week = Carbon::now()->endOfWeek()->subWeek(1);


            $all_manager_department_ids = $this->get_all_departments_of_manager();



            $data["pension"] = $this->getPensionExpiries(
                $all_manager_department_ids,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                $duration

            );






            return response()->json($data, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }



    public function getBusinessManagerDashboardDataCombinedExpiries(Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            $business_id = auth()->user()->business_id;


            if (!$business_id) {
                return response()->json([
                    "message" => "You are not a business user"
                ], 401);
            }
            $today = today();



            $start_date_of_next_month = Carbon::now()->startOfMonth()->addMonth(1);
            $end_date_of_next_month = Carbon::now()->endOfMonth()->addMonth(1);
            $start_date_of_this_month = Carbon::now()->startOfMonth();
            $end_date_of_this_month = Carbon::now()->endOfMonth();
            $start_date_of_previous_month = Carbon::now()->startOfMonth()->subMonth(1);
            $end_date_of_previous_month = Carbon::now()->startOfMonth()->subDay(1);

            $start_date_of_next_week = Carbon::now()->startOfWeek()->addWeek(1);
            $end_date_of_next_week = Carbon::now()->endOfWeek()->addWeek(1);
            $start_date_of_this_week = Carbon::now()->startOfWeek();
            $end_date_of_this_week = Carbon::now()->endOfWeek();
            $start_date_of_previous_week = Carbon::now()->startOfWeek()->subWeek(1);
            $end_date_of_previous_week = Carbon::now()->endOfWeek()->subWeek(1);


            $all_manager_department_ids = $this->get_all_departments_of_manager();




            $data["pension"] = $this->getPensionExpiries(
                $all_manager_department_ids,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                "today",
                1
            );

            $data["passport"] = $this->getPassportExpiries(
                $all_manager_department_ids,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                "today",
                1

            );



            $data["visa"] = $this->getVisaExpiries(
                $all_manager_department_ids,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                "today",
                1
            );



            $data["right_to_work"] = $this->getRightToWorkExpiries(
                $all_manager_department_ids,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                "today",
                1
            );

            $data["sponsorship"] = $this->getSponsorshipExpiries(
                $all_manager_department_ids,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                "today",
                1
            );


            return response()->json($data, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }






    public function getBusinessManagerDashboardDataPassportExpiries($duration, Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");


            $durations = ['today', 'this_month', 'previous_month', 'next_month', 'this_week', 'previous_week', 'next_week'];
            if (!in_array($duration, $durations)) {
                $error =  [
                    "message" => "The given data was invalid.",
                    "errors" => ["duration" => ["Valid Durations are 'today','this_month', 'previous_month', 'next_month' ,'this_week', 'previous_week','next_week' "]]
                ];
                throw new Exception(json_encode($error), 422);
            }

            $business_id = auth()->user()->business_id;


            if (!$business_id) {
                return response()->json([
                    "message" => "You are not a business user"
                ], 401);
            }
            $today = today();



            $start_date_of_next_month = Carbon::now()->startOfMonth()->addMonth(1);
            $end_date_of_next_month = Carbon::now()->endOfMonth()->addMonth(1);
            $start_date_of_this_month = Carbon::now()->startOfMonth();
            $end_date_of_this_month = Carbon::now()->endOfMonth();
            $start_date_of_previous_month = Carbon::now()->startOfMonth()->subMonth(1);
            $end_date_of_previous_month = Carbon::now()->startOfMonth()->subDay(1);

            $start_date_of_next_week = Carbon::now()->startOfWeek()->addWeek(1);
            $end_date_of_next_week = Carbon::now()->endOfWeek()->addWeek(1);
            $start_date_of_this_week = Carbon::now()->startOfWeek();
            $end_date_of_this_week = Carbon::now()->endOfWeek();
            $start_date_of_previous_week = Carbon::now()->startOfWeek()->subWeek(1);
            $end_date_of_previous_week = Carbon::now()->endOfWeek()->subWeek(1);


            $all_manager_department_ids = $this->get_all_departments_of_manager();





            $data["passport"] = $this->getPassportExpiries(
                $all_manager_department_ids,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                $duration
            );






            return response()->json($data, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }


    public function getBusinessManagerDashboardDataVisaExpiries($duration, Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            $business_id = auth()->user()->business_id;

            $durations = ['today', 'this_month', 'previous_month', 'next_month', 'this_week', 'previous_week', 'next_week'];
            if (!in_array($duration, $durations)) {
                $error =  [
                    "message" => "The given data was invalid.",
                    "errors" => ["duration" => ["Valid Durations are 'today','this_month', 'previous_month', 'next_month' ,'this_week', 'previous_week','next_week' "]]
                ];
                throw new Exception(json_encode($error), 422);
            }



            if (!$business_id) {
                return response()->json([
                    "message" => "You are not a business user"
                ], 401);
            }
            $today = today();

            $start_date_of_next_month = Carbon::now()->startOfMonth()->addMonth(1);
            $end_date_of_next_month = Carbon::now()->endOfMonth()->addMonth(1);
            $start_date_of_this_month = Carbon::now()->startOfMonth();
            $end_date_of_this_month = Carbon::now()->endOfMonth();
            $start_date_of_previous_month = Carbon::now()->startOfMonth()->subMonth(1);
            $end_date_of_previous_month = Carbon::now()->startOfMonth()->subDay(1);

            $start_date_of_next_week = Carbon::now()->startOfWeek()->addWeek(1);
            $end_date_of_next_week = Carbon::now()->endOfWeek()->addWeek(1);
            $start_date_of_this_week = Carbon::now()->startOfWeek();
            $end_date_of_this_week = Carbon::now()->endOfWeek();
            $start_date_of_previous_week = Carbon::now()->startOfWeek()->subWeek(1);
            $end_date_of_previous_week = Carbon::now()->endOfWeek()->subWeek(1);


            $all_manager_department_ids = $this->get_all_departments_of_manager();



            $data["visa"] = $this->getVisaExpiries(
                $all_manager_department_ids,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                $duration

            );




            return response()->json($data, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }



    public function getBusinessManagerDashboardDataRightToWorkExpiries($duration, Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            $business_id = auth()->user()->business_id;


            $durations = ['today', 'this_month', 'previous_month', 'next_month', 'this_week', 'previous_week', 'next_week'];
            if (!in_array($duration, $durations)) {
                $error =  [
                    "message" => "The given data was invalid.",
                    "errors" => ["duration" => ["Valid Durations are 'today','this_month', 'previous_month', 'next_month' ,'this_week', 'previous_week','next_week' "]]
                ];
                throw new Exception(json_encode($error), 422);
            }



            if (!$business_id) {
                return response()->json([
                    "message" => "You are not a business user"
                ], 401);
            }
            $today = today();



            $start_date_of_next_month = Carbon::now()->startOfMonth()->addMonth(1);
            $end_date_of_next_month = Carbon::now()->endOfMonth()->addMonth(1);
            $start_date_of_this_month = Carbon::now()->startOfMonth();
            $end_date_of_this_month = Carbon::now()->endOfMonth();
            $start_date_of_previous_month = Carbon::now()->startOfMonth()->subMonth(1);
            $end_date_of_previous_month = Carbon::now()->startOfMonth()->subDay(1);

            $start_date_of_next_week = Carbon::now()->startOfWeek()->addWeek(1);
            $end_date_of_next_week = Carbon::now()->endOfWeek()->addWeek(1);
            $start_date_of_this_week = Carbon::now()->startOfWeek();
            $end_date_of_this_week = Carbon::now()->endOfWeek();
            $start_date_of_previous_week = Carbon::now()->startOfWeek()->subWeek(1);
            $end_date_of_previous_week = Carbon::now()->endOfWeek()->subWeek(1);


            $all_manager_department_ids = $this->get_all_departments_of_manager();




            $data["right_to_work"] = $this->getRightToWorkExpiries(
                $all_manager_department_ids,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                $duration

            );





            return response()->json($data, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }


    public function getBusinessManagerDashboardDataSponsorshipExpiries($duration, Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            $business_id = auth()->user()->business_id;

            $durations = ['today', 'this_month', 'previous_month', 'next_month', 'this_week', 'previous_week', 'next_week'];
            if (!in_array($duration, $durations)) {
                $error =  [
                    "message" => "The given data was invalid.",
                    "errors" => ["duration" => ["Valid Durations are 'today','this_month', 'previous_month', 'next_month' ,'this_week', 'previous_week','next_week' "]]
                ];
                throw new Exception(json_encode($error), 422);
            }


            if (!$business_id) {
                return response()->json([
                    "message" => "You are not a business user"
                ], 401);
            }
            $today = today();



            $start_date_of_next_month = Carbon::now()->startOfMonth()->addMonth(1);
            $end_date_of_next_month = Carbon::now()->endOfMonth()->addMonth(1);
            $start_date_of_this_month = Carbon::now()->startOfMonth();
            $end_date_of_this_month = Carbon::now()->endOfMonth();
            $start_date_of_previous_month = Carbon::now()->startOfMonth()->subMonth(1);
            $end_date_of_previous_month = Carbon::now()->startOfMonth()->subDay(1);

            $start_date_of_next_week = Carbon::now()->startOfWeek()->addWeek(1);
            $end_date_of_next_week = Carbon::now()->endOfWeek()->addWeek(1);
            $start_date_of_this_week = Carbon::now()->startOfWeek();
            $end_date_of_this_week = Carbon::now()->endOfWeek();
            $start_date_of_previous_week = Carbon::now()->startOfWeek()->subWeek(1);
            $end_date_of_previous_week = Carbon::now()->endOfWeek()->subWeek(1);


            $all_manager_department_ids = $this->get_all_departments_of_manager();



            $data["sponsorship"] = $this->getSponsorshipExpiries(
                $all_manager_department_ids,
                $start_date_of_next_month,
                $end_date_of_next_month,
                $start_date_of_this_month,
                $end_date_of_this_month,
                $start_date_of_previous_month,
                $end_date_of_previous_month,
                $start_date_of_next_week,
                $end_date_of_next_week,
                $start_date_of_this_week,
                $end_date_of_this_week,
                $start_date_of_previous_week,
                $end_date_of_previous_week,
                $duration
            );




            return response()->json($data, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }










}
