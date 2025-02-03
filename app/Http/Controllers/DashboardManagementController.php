<?php

namespace App\Http\Controllers;

use App\Http\Components\UserManagementComponent;
use App\Http\Components\WorkShiftHistoryComponent;
use App\Http\Requests\WidgetCreateRequest;
use App\Http\Utils\BasicUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Business;
use App\Models\Candidate;
use App\Models\DashboardWidget;
use App\Models\Department;
use App\Models\EmployeePassportDetailHistory;
use App\Models\EmployeePensionHistory;
use App\Models\EmployeeRightToWorkHistory;
use App\Models\EmployeeSponsorshipHistory;
use App\Models\EmployeeVisaDetailHistory;
use App\Models\EmploymentStatus;
use App\Models\Holiday;
use App\Models\JobListing;
use App\Models\LeaveRecord;
use App\Models\Notification;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardManagementController extends Controller
{
    use ErrorUtil, BusinessUtil, UserActivityUtil, BasicUtil;



    protected $userManagementComponent;
    protected $workShiftHistoryComponent;

    public function __construct(UserManagementComponent $userManagementComponent, WorkShiftHistoryComponent $workShiftHistoryComponent)
    {
        $this->userManagementComponent = $userManagementComponent;
        $this->workShiftHistoryComponent = $workShiftHistoryComponent;


    }



    public function employees(
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

        $data_query  = User::whereHas("department_user.department", function ($query) use ($all_manager_department_ids) {
            $query->whereIn("departments.id", $all_manager_department_ids);
        })
            ->whereNotIn('id', [auth()->user()->id])


            // ->where('is_in_employee', 1)

            ->where('is_active', 1);

        $data["total_data"] = $data_query->get();

        $data["total_data_count"] = $data_query->count();

        $data["today_data_count"] = clone $data_query;
        $data["today_data_count"] = $data["today_data_count"]->whereBetween('users.created_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->count();


        $data["yesterday_data_count"] = clone $data_query;
        $data["yesterday_data_count"] = $data["yesterday_data_count"]->whereBetween('users.created_at', [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()])->count();



        $data["this_week_data_count"] = clone $data_query;
        $data["this_week_data_count"] = $data["this_week_data_count"]->whereBetween('created_at', [$start_date_of_this_week, ($end_date_of_this_week . ' 23:59:59')])->count();




        $data["previous_week_data_count"] = clone $data_query;
        $data["previous_week_data_count"] = $data["previous_week_data_count"]->whereBetween('created_at', [$start_date_of_previous_week, ($end_date_of_previous_week . ' 23:59:59')])->count();


        $data["this_month_data_count"] = clone $data_query;
        $data["this_month_data_count"] = $data["this_month_data_count"]->whereBetween('created_at', [$start_date_of_this_month, ($end_date_of_this_month . ' 23:59:59')])->count();


        $data["previous_month_data_count"] = clone $data_query;
        $data["previous_month_data_count"] = $data["previous_month_data_count"]->whereBetween('created_at', [$start_date_of_previous_month, ($end_date_of_previous_month . ' 23:59:59')])->count();




        $data["date_ranges"] = [
            "today_data_count_date_range" => [$today->copy()->startOfDay(), $today->copy()->endOfDay() ],
            "yesterday_data_count_date_range" => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            "next_week_data_count_date_range" => [$start_date_of_next_week, ($end_date_of_next_week )],
            "this_week_data_count_date_range" => [$start_date_of_this_week, ($end_date_of_this_week )],
            "previous_week_data_count_date_range" => [$start_date_of_previous_week, ($end_date_of_previous_week )],
            "next_month_data_count_date_range" => [$start_date_of_next_month, ($end_date_of_next_month )],
            "this_month_data_count_date_range" => [$start_date_of_this_month, ($end_date_of_this_month )],
            "previous_month_data_count_date_range" => [$start_date_of_previous_month, ($end_date_of_previous_month)],
          ];


        return $data;
    }

    // public function approved_leaves(
    //     $today,
    //     $start_date_of_this_month,
    //     $end_date_of_this_month,
    //     $start_date_of_previous_month,
    //     $end_date_of_previous_month,
    //     $start_date_of_this_week,
    //     $end_date_of_this_week,
    //     $start_date_of_previous_week,
    //     $end_date_of_previous_week,
    //     $all_manager_department_ids
    // )
    // {



    //     $data_query  = LeaveRecord::whereHas("leave.employee.departments", function($query) use($all_manager_department_ids) {
    //        $query->whereIn("departments.id",$all_manager_department_ids);
    //     })
    //     ->whereHas("leave", function($query) use($all_manager_department_ids) {
    //         $query->where([
    //             "leaves.business_id" => auth()->user()->business_id,
    //             "leaves.status" => "approved"
    //             ]);
    //      })
    //         ->whereNotIn('id', [auth()->user()->id])
    //         ->where('is_in_employee', 1)
    //         ->where('is_active', 1);

    //     $data["total_data_count"] = $data_query->count();
    //     $data["today_data_count"] = $data_query->whereBetween('date', [$today, ($today . ' 23:59:59')])->count();
    //     $data["this_week_data_count"] = $data_query->whereBetween('date', [$start_date_of_this_week, ($end_date_of_this_week . ' 23:59:59')])->count();
    //     $data["previous_week_data_count"] = $data_query->whereBetween('date', [$start_date_of_previous_week, ($end_date_of_previous_week . ' 23:59:59')])->count();
    //     $data["this_month_data_count"] = $data_query->whereBetween('date', [$start_date_of_this_month, ($end_date_of_this_month . ' 23:59:59')])->count();
    //     $data["previous_month_data_count"] = $data_query->whereBetween('date', [$start_date_of_previous_month, ($end_date_of_previous_month . ' 23:59:59')])->count();

    //     return $data;
    // }

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
            ->where("business_id",auth()->user()->id);


        // $data["total_data_count"] = $data_query->count();

        $data["today_data_count"] = clone $data_query;
        $data["today_data_count"] = $data["today_data_count"]

        ->where(function($query) use ($today, $total_departments)  {
                 $query->where(function($query) use ($today, $total_departments) {

                    $query->where(function($query) use ($today,$total_departments) {
                        $query->whereHas('holidays', function ($query) use ($today) {
                            $query->where('holidays.start_date', "<=",  $today->copy()->startOfDay())
                            ->where('holidays.end_date', ">=",  $today->copy()->endOfDay());

                        })
                        ->orWhere(function($query) use($today, $total_departments) {
                              $query->whereHasRecursiveHolidays($today,$total_departments);
                        });

                        // ->whereHas('departments.holidays', function ($query) use ($today) {
                        //     $query->where('holidays.start_date', "<=",  $today->copy()->startOfDay())
                        //     ->where('holidays.end_date', ">=",  $today->copy()->endOfDay());
                        // });

                    })
                    ->where(function($query) use ($today) {
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
                    function($query) use ($today) {
                    $query->orWhereDoesntHave('holidays', function ($query) use ($today) {
                        $query->where('holidays.start_date', "<=",  $today->copy()->startOfDay());
                        $query->where('holidays.end_date', ">=",  $today->copy()->endOfDay());
                        $query->doesntHave('users');

                    });

                }
            );
        })



       ->count();

        // $data["next_week_data_count"] = clone $data_query;
        // $data["next_week_data_count"] = $data["next_week_data_count"]

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

        // $data["this_week_data_count"] = clone $data_query;
        // $data["this_week_data_count"] = $data["this_week_data_count"]

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

        // $data["next_month_data_count"] = clone $data_query;
        // $data["next_month_data_count"] = $data["next_month_data_count"]
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

        // $data["this_month_data_count"] = clone $data_query;
        // $data["this_month_data_count"] = $data["this_month_data_count"]
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
            "today_data_count_date_range" => [$today->copy()->startOfDay(), $today->copy()->endOfDay() ],
            "yesterday_data_count_date_range" => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            "next_week_data_count_date_range" => [$start_date_of_next_week, ($end_date_of_next_week )],
            "this_week_data_count_date_range" => [$start_date_of_this_week, ($end_date_of_this_week )],
            "previous_week_data_count_date_range" => [$start_date_of_previous_week, ($end_date_of_previous_week )],
            "next_month_data_count_date_range" => [$start_date_of_next_month, ($end_date_of_next_month )],
            "this_month_data_count_date_range" => [$start_date_of_this_month, ($end_date_of_this_month )],
            "previous_month_data_count_date_range" => [$start_date_of_previous_month, ($end_date_of_previous_month)],
          ];

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
        $status,
        $show_my_data = false
    ) {

        $data_query  = LeaveRecord::whereHas("leave", function ($query) use ($status, $show_my_data, $all_manager_department_ids) {
                $query->where([
                    "leaves.business_id" => auth()->user()->business_id,
                    "leaves.status" => $status
                ])
                ->when(
                    $show_my_data,
                    function ($query)  {
                        $query->where('leaves.user_id', auth()->user()->id);
                    },
                    function ($query) use ($all_manager_department_ids) {

                        $query->whereHas("employee.department_user.department", function ($query) use ($all_manager_department_ids) {
                            $query->whereIn("departments.id", $all_manager_department_ids);

                        })
                        ->whereNotIn('leaves.user_id', [auth()->user()->id]);
                        ;;

                    }
                );
            });

        $data["total_data_count"] = $data_query->count();

        $data["today_data_count"] = clone $data_query;
        $data["today_data_count"] = $data["today_data_count"]->whereBetween('date', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->count();

        $data["yesterday_data_count"] = clone $data_query;
        $data["yesterday_data_count"] = $data["yesterday_data_count"]->whereBetween('date', [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()])->count();

        $data["next_week_data_count"] = clone $data_query;
        $data["next_week_data_count"] = $data["next_week_data_count"]->whereBetween('date', [$start_date_of_next_week, ($end_date_of_next_week . ' 23:59:59')])->count();

        $data["this_week_data_count"] = clone $data_query;
        $data["this_week_data_count"] = $data["this_week_data_count"]->whereBetween('date', [$start_date_of_this_week, ($end_date_of_this_week . ' 23:59:59')])->count();

        $data["previous_week_data_count"] = clone $data_query;
        $data["previous_week_data_count"] = $data["previous_week_data_count"]->whereBetween('date', [$start_date_of_previous_week, ($end_date_of_previous_week . ' 23:59:59')])->count();

        $data["next_month_data_count"] = clone $data_query;
        $data["next_month_data_count"] = $data["next_month_data_count"]->whereBetween('date', [$start_date_of_next_month, ($end_date_of_next_month . ' 23:59:59')])->count();

        $data["this_month_data_count"] = clone $data_query;
        $data["this_month_data_count"] = $data["this_month_data_count"]->whereBetween('date', [$start_date_of_this_month, ($end_date_of_this_month . ' 23:59:59')])->count();

        $data["previous_month_data_count"] = clone $data_query;
        $data["previous_month_data_count"] = $data["previous_month_data_count"]->whereBetween('date', [$start_date_of_previous_month, ($end_date_of_previous_month . ' 23:59:59')])->count();

        $data["date_ranges"] = [
            "today_data_count_date_range" => [$today->copy()->startOfDay(), $today->copy()->endOfDay() ],
            "yesterday_data_count_date_range" => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            "next_week_data_count_date_range" => [$start_date_of_next_week, ($end_date_of_next_week )],
            "this_week_data_count_date_range" => [$start_date_of_this_week, ($end_date_of_this_week )],
            "previous_week_data_count_date_range" => [$start_date_of_previous_week, ($end_date_of_previous_week )],
            "next_month_data_count_date_range" => [$start_date_of_next_month, ($end_date_of_next_month )],
            "this_month_data_count_date_range" => [$start_date_of_this_month, ($end_date_of_this_month )],
            "previous_month_data_count_date_range" => [$start_date_of_previous_month, ($end_date_of_previous_month)],
          ];
        return $data;
    }

    public function attendances(
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

        $data_query  = Attendance::
        where("is_present",1)
        ->when(
            $show_my_data,
            function ($query)  {
                $query->where('attendances.user_id', auth()->user()->id);
            },
            function ($query) use ($all_manager_department_ids,) {

                $query->whereHas("employee.department_user.department", function ($query) use ($all_manager_department_ids) {
                    $query->whereIn("departments.id", $all_manager_department_ids);

                })      ->whereNotIn('attendances.user_id', [auth()->user()->id]);
                ;

            }
        );







        $data["total_data_count"] = $data_query->count();

        $data["today_data_count"] = clone $data_query;
        $data["today_data_count"] = $data["today_data_count"]->whereBetween('in_date', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->count();

        $data["yesterday_data_count"] = clone $data_query;
        $data["yesterday_data_count"] = $data["yesterday_data_count"]->whereBetween('in_date', [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()])->count();

        $data["next_week_data_count"] = clone $data_query;
        $data["next_week_data_count"] = $data["next_week_data_count"]->whereBetween('in_date', [$start_date_of_next_week, ($end_date_of_next_week . ' 23:59:59')])->count();

        $data["this_week_data_count"] = clone $data_query;
        $data["this_week_data_count"] = $data["this_week_data_count"]->whereBetween('in_date', [$start_date_of_this_week, ($end_date_of_this_week . ' 23:59:59')])->count();

        $data["previous_week_data_count"] = clone $data_query;
        $data["previous_week_data_count"] = $data["previous_week_data_count"]->whereBetween('in_date', [$start_date_of_previous_week, ($end_date_of_previous_week . ' 23:59:59')])->count();

        $data["next_month_data_count"] = clone $data_query;
        $data["next_month_data_count"] = $data["next_month_data_count"]->whereBetween('in_date', [$start_date_of_next_month, ($end_date_of_next_month . ' 23:59:59')])->count();

        $data["this_month_data_count"] = clone $data_query;
        $data["this_month_data_count"] = $data["this_month_data_count"]->whereBetween('in_date', [$start_date_of_this_month, ($end_date_of_this_month . ' 23:59:59')])->count();

        $data["previous_month_data_count"] = clone $data_query;
        $data["previous_month_data_count"] = $data["previous_month_data_count"]->whereBetween('in_date', [$start_date_of_previous_month, ($end_date_of_previous_month . ' 23:59:59')])->count();

        $data["date_ranges"] = [
            "today_data_count_date_range" => [$today->copy()->startOfDay(), $today->copy()->endOfDay() ],
            "yesterday_data_count_date_range" => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            "next_week_data_count_date_range" => [$start_date_of_next_week, ($end_date_of_next_week )],
            "this_week_data_count_date_range" => [$start_date_of_this_week, ($end_date_of_this_week )],
            "previous_week_data_count_date_range" => [$start_date_of_previous_week, ($end_date_of_previous_week )],
            "next_month_data_count_date_range" => [$start_date_of_next_month, ($end_date_of_next_month )],
            "this_month_data_count_date_range" => [$start_date_of_this_month, ($end_date_of_this_month )],
            "previous_month_data_count_date_range" => [$start_date_of_previous_month, ($end_date_of_previous_month)],
          ];
        return $data;
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

            $data["today_data_count"] = clone $data_query;
            $data["today_data_count"] = $data["today_data_count"]->whereBetween('users.created_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->count();


            $data["yesterday_data_count"] = clone $data_query;
            $data["yesterday_data_count"] = $data["yesterday_data_count"]->whereBetween('users.created_at', [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()])->count();



            $data["this_week_data_count"] = clone $data_query;
            $data["this_week_data_count"] = $data["this_week_data_count"]->whereBetween('created_at', [$start_date_of_this_week, ($end_date_of_this_week . ' 23:59:59')])->count();




            $data["previous_week_data_count"] = clone $data_query;
            $data["previous_week_data_count"] = $data["previous_week_data_count"]->whereBetween('created_at', [$start_date_of_previous_week, ($end_date_of_previous_week . ' 23:59:59')])->count();


            $data["this_month_data_count"] = clone $data_query;
            $data["this_month_data_count"] = $data["this_month_data_count"]->whereBetween('created_at', [$start_date_of_this_month, ($end_date_of_this_month . ' 23:59:59')])->count();


            $data["previous_month_data_count"] = clone $data_query;
            $data["previous_month_data_count"] = $data["previous_month_data_count"]->whereBetween('created_at', [$start_date_of_previous_month, ($end_date_of_previous_month . ' 23:59:59')])->count();


            $data["date_ranges"] = [
                "today_data_count_date_range" => [$today->copy()->startOfDay(), $today->copy()->endOfDay() ],
                "yesterday_data_count_date_range" => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
                "next_week_data_count_date_range" => [$start_date_of_next_week, ($end_date_of_next_week )],
                "this_week_data_count_date_range" => [$start_date_of_this_week, ($end_date_of_this_week )],
                "previous_week_data_count_date_range" => [$start_date_of_previous_week, ($end_date_of_previous_week )],
                "next_month_data_count_date_range" => [$start_date_of_next_month, ($end_date_of_next_month )],
                "this_month_data_count_date_range" => [$start_date_of_this_month, ($end_date_of_this_month )],
                "previous_month_data_count_date_range" => [$start_date_of_previous_month, ($end_date_of_previous_month)],
              ];

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
        $all_manager_department_ids
    ) {

        $data_query  = JobListing::where("application_deadline",">=", today())
        ->where("business_id",auth()->user()->business_id);

        $data["total_data_count"] = $data_query->count();

        $data["today_data_count"] = clone $data_query;
        $data["today_data_count"] = $data["today_data_count"]->whereBetween('application_deadline', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->count();

        $data["next_week_data_count"] = clone $data_query;
        $data["next_week_data_count"] = $data["next_week_data_count"]->whereBetween('application_deadline', [$start_date_of_next_week, ($end_date_of_next_week . ' 23:59:59')])->count();

        $data["this_week_data_count"] = clone $data_query;
        $data["this_week_data_count"] = $data["this_week_data_count"]->whereBetween('application_deadline', [$start_date_of_this_week, ($end_date_of_this_week . ' 23:59:59')])->count();



        $data["next_month_data_count"] = clone $data_query;
        $data["next_month_data_count"] = $data["next_month_data_count"]->whereBetween('application_deadline', [$start_date_of_next_month, ($end_date_of_next_month . ' 23:59:59')])->count();

        $data["this_month_data_count"] = clone $data_query;
        $data["this_month_data_count"] = $data["this_month_data_count"]->whereBetween('application_deadline', [$start_date_of_this_month, ($end_date_of_this_month . ' 23:59:59')])->count();


        $data["date_ranges"] = [
            "today_data_count_date_range" => [$today->copy()->startOfDay(), $today->copy()->endOfDay() ],
            "yesterday_data_count_date_range" => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            "next_week_data_count_date_range" => [$start_date_of_next_week, ($end_date_of_next_week )],
            "this_week_data_count_date_range" => [$start_date_of_this_week, ($end_date_of_this_week )],
            "previous_week_data_count_date_range" => [$start_date_of_previous_week, ($end_date_of_previous_week )],
            "next_month_data_count_date_range" => [$start_date_of_next_month, ($end_date_of_next_month )],
            "this_month_data_count_date_range" => [$start_date_of_this_month, ($end_date_of_this_month )],
            "previous_month_data_count_date_range" => [$start_date_of_previous_month, ($end_date_of_previous_month)],
          ];

        return $data;
    }




    public function self_passport_expiries_in(
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
        ->where("business_id",auth()->user()->business_id)

        ->whereIn('user_id', [auth()->user()->id])
        ->where($issue_date_column, '<', now())
        ->groupBy('user_id')
        ->get()
        ->map(function ($record) use ($issue_date_column, $expiry_date_column) {

            $latest_expired_record = EmployeePassportDetailHistory::where('user_id', $record->user_id)
            ->where($issue_date_column, '<', now())
            ->orderByDesc($expiry_date_column)
            // ->latest()
            ->first();

            if($latest_expired_record) {
                 $current_data = EmployeePassportDetailHistory::where('user_id', $record->user_id)
                ->where($expiry_date_column, $latest_expired_record[$expiry_date_column])
                ->where($issue_date_column, '<', now())
                ->orderByDesc("id")
                // ->orderByDesc($issue_date_column)
                ->first();
            } else {
               return NULL;
            }


                return $current_data?$current_data->id:NULL;
        })
        ->filter()->values();



        $data_query  = EmployeePassportDetailHistory::whereIn('id', $employee_passport_history_ids)
        ->where($expiry_date_column,">=", today());



        $data["total_data_count"] = $data_query->count();

//         $data["today_data_count"] = clone $data_query;
//         $data["today_data_count"] = $data["today_data_count"]->whereBetween('passport_expiry_date', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->count();

//         $data["yesterday_data_count"] = clone $data_query;
// $data["yesterday_data_count"] = $data["yesterday_data_count"]->whereBetween('passport_expiry_date', [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()])->count();

//         $data["next_week_data_count"] = clone $data_query;
//         $data["next_week_data_count"] = $data["next_week_data_count"]->whereBetween('passport_expiry_date', [$start_date_of_next_week, ($end_date_of_next_week . ' 23:59:59')])->count();

//         $data["this_week_data_count"] = clone $data_query;
//         $data["this_week_data_count"] = $data["this_week_data_count"]->whereBetween('passport_expiry_date', [$start_date_of_this_week, ($end_date_of_this_week . ' 23:59:59')])->count();



//         $data["next_month_data_count"] = clone $data_query;
//         $data["next_month_data_count"] = $data["next_month_data_count"]->whereBetween('passport_expiry_date', [$start_date_of_next_month, ($end_date_of_next_month . ' 23:59:59')])->count();

//         $data["this_month_data_count"] = clone $data_query;
//         $data["this_month_data_count"] = $data["this_month_data_count"]->whereBetween('passport_expiry_date', [$start_date_of_this_month, ($end_date_of_this_month . ' 23:59:59')])->count();


//         $expires_in_days = [15,30,60];
//         foreach($expires_in_days as $expires_in_day){
//             $query_day = Carbon::now()->addDays($expires_in_day);
//             $data[("expires_in_". $expires_in_day ."_days")] = clone $data_query;
//             $data[("expires_in_". $expires_in_day ."_days")] = $data[("expires_in_". $expires_in_day ."_days")]->whereBetween('passport_expiry_date', [$today, ($query_day->endOfDay() . ' 23:59:59')])->count();
//         }
//         $data["date_ranges"] = [
//             "today_data_count_date_range" => [$today->copy()->startOfDay(), $today->copy()->endOfDay() ],
//             "yesterday_data_count_date_range" => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
//             "next_week_data_count_date_range" => [$start_date_of_next_week, ($end_date_of_next_week )],
//             "this_week_data_count_date_range" => [$start_date_of_this_week, ($end_date_of_this_week )],
//             "previous_week_data_count_date_range" => [$start_date_of_previous_week, ($end_date_of_previous_week )],
//             "next_month_data_count_date_range" => [$start_date_of_next_month, ($end_date_of_next_month )],
//             "this_month_data_count_date_range" => [$start_date_of_this_month, ($end_date_of_this_month )],
//             "previous_month_data_count_date_range" => [$start_date_of_previous_month, ($end_date_of_previous_month)],
//           ];

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
        ->where("business_id",auth()->user()->business_id)
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

            if($latest_expired_record) {
                 $current_data = EmployeePassportDetailHistory::where('user_id', $record->user_id)
                ->where($expiry_date_column, $latest_expired_record[$expiry_date_column])
                ->where($issue_date_column, '<', now())
                ->orderByDesc("id")
                // ->orderByDesc($issue_date_column)
                ->first();
            } else {
               return NULL;
            }


                return $current_data?$current_data->id:NULL;
        })
        ->filter()->values();



        $data_query  = EmployeePassportDetailHistory::whereIn('id', $employee_passport_history_ids)
        ->where($expiry_date_column,">=", today());



        $data["total_data_count"] = $data_query->count();

        $data["today_data_count"] = clone $data_query;
        $data["today_data_count"] = $data["today_data_count"]->whereBetween('passport_expiry_date', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->count();

        $data["yesterday_data_count"] = clone $data_query;
$data["yesterday_data_count"] = $data["yesterday_data_count"]->whereBetween('passport_expiry_date', [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()])->count();

        $data["next_week_data_count"] = clone $data_query;
        $data["next_week_data_count"] = $data["next_week_data_count"]->whereBetween('passport_expiry_date', [$start_date_of_next_week, ($end_date_of_next_week . ' 23:59:59')])->count();

        $data["this_week_data_count"] = clone $data_query;
        $data["this_week_data_count"] = $data["this_week_data_count"]->whereBetween('passport_expiry_date', [$start_date_of_this_week, ($end_date_of_this_week . ' 23:59:59')])->count();



        $data["next_month_data_count"] = clone $data_query;
        $data["next_month_data_count"] = $data["next_month_data_count"]->whereBetween('passport_expiry_date', [$start_date_of_next_month, ($end_date_of_next_month . ' 23:59:59')])->count();

        $data["this_month_data_count"] = clone $data_query;
        $data["this_month_data_count"] = $data["this_month_data_count"]->whereBetween('passport_expiry_date', [$start_date_of_this_month, ($end_date_of_this_month . ' 23:59:59')])->count();


        $expires_in_days = [15,30,60];
        foreach($expires_in_days as $expires_in_day){
            $query_day = Carbon::now()->addDays($expires_in_day);
            $data[("expires_in_". $expires_in_day ."_days")] = clone $data_query;
            $data[("expires_in_". $expires_in_day ."_days")] = $data[("expires_in_". $expires_in_day ."_days")]->whereBetween('passport_expiry_date', [$today, ($query_day->endOfDay() . ' 23:59:59')])->count();
        }
        $data["date_ranges"] = [
            "today_data_count_date_range" => [$today->copy()->startOfDay(), $today->copy()->endOfDay() ],
            "yesterday_data_count_date_range" => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            "next_week_data_count_date_range" => [$start_date_of_next_week, ($end_date_of_next_week )],
            "this_week_data_count_date_range" => [$start_date_of_this_week, ($end_date_of_this_week )],
            "previous_week_data_count_date_range" => [$start_date_of_previous_week, ($end_date_of_previous_week )],
            "next_month_data_count_date_range" => [$start_date_of_next_month, ($end_date_of_next_month )],
            "this_month_data_count_date_range" => [$start_date_of_this_month, ($end_date_of_this_month )],
            "previous_month_data_count_date_range" => [$start_date_of_previous_month, ($end_date_of_previous_month)],
          ];

        return $data;
    }


    public function self_visa_expiries_in(
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
        ->where("business_id",auth()->user()->business_id)
        ->whereHas("employee.department_user.department", function ($query) use ($all_manager_department_ids) {
            $query->whereIn("departments.id", $all_manager_department_ids);
        })
        ->whereIn('user_id', [auth()->user()->id])
        ->where($issue_date_column, '<', now())
        ->groupBy('user_id')
        ->get()
        ->map(function ($record) use ($issue_date_column, $expiry_date_column) {

            $latest_expired_record = EmployeeVisaDetailHistory::where('user_id', $record->user_id)
            ->where($issue_date_column, '<', now())
            ->orderByDesc($expiry_date_column)
            // ->latest()
            ->first();

            if($latest_expired_record) {
                 $current_data = EmployeeVisaDetailHistory::where('user_id', $record->user_id)
                ->where($expiry_date_column, $latest_expired_record[$expiry_date_column])
                ->where($issue_date_column, '<', now())
                ->orderByDesc("id")
                // ->orderByDesc($issue_date_column)
                ->first();
            } else {
               return NULL;
            }


                return $current_data?$current_data->id:NULL;
        })
        ->filter()->values();




        $data_query  = EmployeeVisaDetailHistory::whereIn('id', $employee_visa_history_ids)
        ->where($expiry_date_column,">=", today());

        $data["total_data_count"] = $data_query->count();

        // $data["today_data_count"] = clone $data_query;
        // $data["today_data_count"] = $data["today_data_count"]->whereBetween('visa_expiry_date', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->count();

        // $data["yesterday_data_count"] = clone $data_query;
        // $data["yesterday_data_count"] = $data["yesterday_data_count"]->whereBetween('visa_expiry_date', [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()])->count();

        // $data["next_week_data_count"] = clone $data_query;
        // $data["next_week_data_count"] = $data["next_week_data_count"]->whereBetween('visa_expiry_date', [$start_date_of_next_week, ($end_date_of_next_week . ' 23:59:59')])->count();

        // $data["this_week_data_count"] = clone $data_query;
        // $data["this_week_data_count"] = $data["this_week_data_count"]->whereBetween('visa_expiry_date', [$start_date_of_this_week, ($end_date_of_this_week . ' 23:59:59')])->count();


        // $data["next_month_data_count"] = clone $data_query;
        // $data["next_month_data_count"] = $data["next_month_data_count"]->whereBetween('visa_expiry_date', [$start_date_of_next_month, ($end_date_of_next_month . ' 23:59:59')])->count();

        // $data["this_month_data_count"] = clone $data_query;
        // $data["this_month_data_count"] = $data["this_month_data_count"]->whereBetween('visa_expiry_date', [$start_date_of_this_month, ($end_date_of_this_month . ' 23:59:59')])->count();


        // $expires_in_days = [15,30,60];
        // foreach($expires_in_days as $expires_in_day){
        //     $query_day = Carbon::now()->addDays($expires_in_day);
        //     $data[("expires_in_". $expires_in_day ."_days")] = clone $data_query;
        //     $data[("expires_in_". $expires_in_day ."_days")] = $data[("expires_in_". $expires_in_day ."_days")]->whereBetween('visa_expiry_date', [$today, ($query_day->endOfDay() . ' 23:59:59')])->count();
        // }

        // $data["date_ranges"] = [
        //     "today_data_count_date_range" => [$today->copy()->startOfDay(), $today->copy()->endOfDay() ],
        //     "yesterday_data_count_date_range" => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
        //     "next_week_data_count_date_range" => [$start_date_of_next_week, ($end_date_of_next_week )],
        //     "this_week_data_count_date_range" => [$start_date_of_this_week, ($end_date_of_this_week )],
        //     "previous_week_data_count_date_range" => [$start_date_of_previous_week, ($end_date_of_previous_week )],
        //     "next_month_data_count_date_range" => [$start_date_of_next_month, ($end_date_of_next_month )],
        //     "this_month_data_count_date_range" => [$start_date_of_this_month, ($end_date_of_this_month )],
        //     "previous_month_data_count_date_range" => [$start_date_of_previous_month, ($end_date_of_previous_month)],
        //   ];

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
        ->where("business_id",auth()->user()->business_id)
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

            if($latest_expired_record) {
                 $current_data = EmployeeVisaDetailHistory::where('user_id', $record->user_id)
                ->where($expiry_date_column, $latest_expired_record[$expiry_date_column])
                ->where($issue_date_column, '<', now())
                ->orderByDesc("id")
                // ->orderByDesc($issue_date_column)
                ->first();
            } else {
               return NULL;
            }


                return $current_data?$current_data->id:NULL;
        })
        ->filter()->values();




        $data_query  = EmployeeVisaDetailHistory::whereIn('id', $employee_visa_history_ids)
        ->where($expiry_date_column,">=", today());

        $data["total_data_count"] = $data_query->count();

        $data["today_data_count"] = clone $data_query;
        $data["today_data_count"] = $data["today_data_count"]->whereBetween('visa_expiry_date', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->count();

        $data["yesterday_data_count"] = clone $data_query;
        $data["yesterday_data_count"] = $data["yesterday_data_count"]->whereBetween('visa_expiry_date', [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()])->count();

        $data["next_week_data_count"] = clone $data_query;
        $data["next_week_data_count"] = $data["next_week_data_count"]->whereBetween('visa_expiry_date', [$start_date_of_next_week, ($end_date_of_next_week . ' 23:59:59')])->count();

        $data["this_week_data_count"] = clone $data_query;
        $data["this_week_data_count"] = $data["this_week_data_count"]->whereBetween('visa_expiry_date', [$start_date_of_this_week, ($end_date_of_this_week . ' 23:59:59')])->count();


        $data["next_month_data_count"] = clone $data_query;
        $data["next_month_data_count"] = $data["next_month_data_count"]->whereBetween('visa_expiry_date', [$start_date_of_next_month, ($end_date_of_next_month . ' 23:59:59')])->count();

        $data["this_month_data_count"] = clone $data_query;
        $data["this_month_data_count"] = $data["this_month_data_count"]->whereBetween('visa_expiry_date', [$start_date_of_this_month, ($end_date_of_this_month . ' 23:59:59')])->count();


        $expires_in_days = [15,30,60];
        foreach($expires_in_days as $expires_in_day){
            $query_day = Carbon::now()->addDays($expires_in_day);
            $data[("expires_in_". $expires_in_day ."_days")] = clone $data_query;
            $data[("expires_in_". $expires_in_day ."_days")] = $data[("expires_in_". $expires_in_day ."_days")]->whereBetween('visa_expiry_date', [$today, ($query_day->endOfDay() . ' 23:59:59')])->count();
        }

        $data["date_ranges"] = [
            "today_data_count_date_range" => [$today->copy()->startOfDay(), $today->copy()->endOfDay() ],
            "yesterday_data_count_date_range" => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            "next_week_data_count_date_range" => [$start_date_of_next_week, ($end_date_of_next_week )],
            "this_week_data_count_date_range" => [$start_date_of_this_week, ($end_date_of_this_week )],
            "previous_week_data_count_date_range" => [$start_date_of_previous_week, ($end_date_of_previous_week )],
            "next_month_data_count_date_range" => [$start_date_of_next_month, ($end_date_of_next_month )],
            "this_month_data_count_date_range" => [$start_date_of_this_month, ($end_date_of_this_month )],
            "previous_month_data_count_date_range" => [$start_date_of_previous_month, ($end_date_of_previous_month)],
          ];

        return $data;
    }



    public function self_right_to_work_expiries_in(
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
        ->where("business_id",auth()->user()->business_id)
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

            if($latest_expired_record) {
                 $current_data = EmployeeRightToWorkHistory::where('user_id', $record->user_id)
                ->where($expiry_date_column, $latest_expired_record[$expiry_date_column])
                ->where($issue_date_column, '<', now())
                ->orderByDesc("id")
                // ->orderByDesc($issue_date_column)
                ->first();
            } else {
               return NULL;
            }


            return $current_data?$current_data->id:NULL;
        })
        ->filter()->values();



        $data_query  = EmployeeRightToWorkHistory::whereIn('id', $employee_right_to_work_history_ids)
        ->where($expiry_date_column,">=", today());


        $data["total_data_count"] = $data_query->count();



        // $data["today_data_count"] = clone $data_query;
        // $data["today_data_count"] = $data["today_data_count"]->whereBetween('right_to_work_expiry_date', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->count();

        // $data["yesterday_data_count"] = clone $data_query;
        // $data["yesterday_data_count"] = $data["yesterday_data_count"]->whereBetween('right_to_work_expiry_date', [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()])->count();

        // $data["next_week_data_count"] = clone $data_query;
        // $data["next_week_data_count"] = $data["next_week_data_count"]->whereBetween('right_to_work_expiry_date', [$start_date_of_next_week, ($end_date_of_next_week . ' 23:59:59')])->count();

        // $data["this_week_data_count"] = clone $data_query;
        // $data["this_week_data_count"] = $data["this_week_data_count"]->whereBetween('right_to_work_expiry_date', [$start_date_of_this_week, ($end_date_of_this_week . ' 23:59:59')])->count();



        // $data["next_month_data_count"] = clone $data_query;
        // $data["next_month_data_count"] = $data["next_month_data_count"]->whereBetween('right_to_work_expiry_date', [$start_date_of_next_month, ($end_date_of_next_month . ' 23:59:59')])->count();

        // $data["this_month_data_count"] = clone $data_query;
        // $data["this_month_data_count"] = $data["this_month_data_count"]->whereBetween('right_to_work_expiry_date', [$start_date_of_this_month, ($end_date_of_this_month . ' 23:59:59')])->count();


        // $expires_in_days = [15,30,60];
        // foreach($expires_in_days as $expires_in_day){
        //     $query_day = Carbon::now()->addDays($expires_in_day);
        //     $data[("expires_in_". $expires_in_day ."_days")] = clone $data_query;
        //     $data[("expires_in_". $expires_in_day ."_days")] = $data[("expires_in_". $expires_in_day ."_days")]->whereBetween('right_to_work_expiry_date', [$today, ($query_day->endOfDay() . ' 23:59:59')])->count();
        // }

        // $data["date_ranges"] = [
        //     "today_data_count_date_range" => [$today->copy()->startOfDay(), $today->copy()->endOfDay() ],
        //     "yesterday_data_count_date_range" => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
        //     "next_week_data_count_date_range" => [$start_date_of_next_week, ($end_date_of_next_week )],
        //     "this_week_data_count_date_range" => [$start_date_of_this_week, ($end_date_of_this_week )],
        //     "previous_week_data_count_date_range" => [$start_date_of_previous_week, ($end_date_of_previous_week )],
        //     "next_month_data_count_date_range" => [$start_date_of_next_month, ($end_date_of_next_month )],
        //     "this_month_data_count_date_range" => [$start_date_of_this_month, ($end_date_of_this_month )],
        //     "previous_month_data_count_date_range" => [$start_date_of_previous_month, ($end_date_of_previous_month)],
        //   ];






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
        ->where("business_id",auth()->user()->business_id)
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

            if($latest_expired_record) {
                 $current_data = EmployeeRightToWorkHistory::where('user_id', $record->user_id)
                ->where($expiry_date_column, $latest_expired_record[$expiry_date_column])
                ->where($issue_date_column, '<', now())
                ->orderByDesc("id")
                // ->orderByDesc($issue_date_column)
                ->first();
            } else {
               return NULL;
            }


            return $current_data?$current_data->id:NULL;
        })
        ->filter()->values();



        $data_query  = EmployeeRightToWorkHistory::whereIn('id', $employee_right_to_work_history_ids)
        ->where($expiry_date_column,">=", today());


        $data["total_data_count"] = $data_query->count();

        $data["today_data_count"] = clone $data_query;
        $data["today_data_count"] = $data["today_data_count"]->whereBetween('right_to_work_expiry_date', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->count();

        $data["yesterday_data_count"] = clone $data_query;
        $data["yesterday_data_count"] = $data["yesterday_data_count"]->whereBetween('right_to_work_expiry_date', [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()])->count();

        $data["next_week_data_count"] = clone $data_query;
        $data["next_week_data_count"] = $data["next_week_data_count"]->whereBetween('right_to_work_expiry_date', [$start_date_of_next_week, ($end_date_of_next_week . ' 23:59:59')])->count();

        $data["this_week_data_count"] = clone $data_query;
        $data["this_week_data_count"] = $data["this_week_data_count"]->whereBetween('right_to_work_expiry_date', [$start_date_of_this_week, ($end_date_of_this_week . ' 23:59:59')])->count();



        $data["next_month_data_count"] = clone $data_query;
        $data["next_month_data_count"] = $data["next_month_data_count"]->whereBetween('right_to_work_expiry_date', [$start_date_of_next_month, ($end_date_of_next_month . ' 23:59:59')])->count();

        $data["this_month_data_count"] = clone $data_query;
        $data["this_month_data_count"] = $data["this_month_data_count"]->whereBetween('right_to_work_expiry_date', [$start_date_of_this_month, ($end_date_of_this_month . ' 23:59:59')])->count();


        $expires_in_days = [15,30,60];
        foreach($expires_in_days as $expires_in_day){
            $query_day = Carbon::now()->addDays($expires_in_day);
            $data[("expires_in_". $expires_in_day ."_days")] = clone $data_query;
            $data[("expires_in_". $expires_in_day ."_days")] = $data[("expires_in_". $expires_in_day ."_days")]->whereBetween('right_to_work_expiry_date', [$today, ($query_day->endOfDay() . ' 23:59:59')])->count();
        }

        $data["date_ranges"] = [
            "today_data_count_date_range" => [$today->copy()->startOfDay(), $today->copy()->endOfDay() ],
            "yesterday_data_count_date_range" => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            "next_week_data_count_date_range" => [$start_date_of_next_week, ($end_date_of_next_week )],
            "this_week_data_count_date_range" => [$start_date_of_this_week, ($end_date_of_this_week )],
            "previous_week_data_count_date_range" => [$start_date_of_previous_week, ($end_date_of_previous_week )],
            "next_month_data_count_date_range" => [$start_date_of_next_month, ($end_date_of_next_month )],
            "this_month_data_count_date_range" => [$start_date_of_this_month, ($end_date_of_this_month )],
            "previous_month_data_count_date_range" => [$start_date_of_previous_month, ($end_date_of_previous_month)],
          ];

        return $data;
    }





    public function self_sponsorship_expiries_in(
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
        ->where("business_id",auth()->user()->business_id)
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

            if($latest_expired_record) {
                 $current_data = EmployeeSponsorshipHistory::where('user_id', $record->user_id)
                ->where($expiry_date_column, $latest_expired_record[$expiry_date_column])
                ->where($issue_date_column, '<', now())
                ->orderByDesc("id")
                // ->orderByDesc($issue_date_column)
                ->first();
            } else {
               return NULL;
            }
            return $current_data?$current_data->id:NULL;
        })
        ->filter()->values();


        $data_query  = EmployeeSponsorshipHistory::whereIn('id', $employee_sponsorship_history_ids)
        ->where($expiry_date_column,">=", today());


        $data["total_data_count"] = $data_query->count();

        // $data["today_data_count"] = clone $data_query;
        // $data["today_data_count"] = $data["today_data_count"]->whereBetween('expiry_date', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->count();

        // $data["yesterday_data_count"] = clone $data_query;
        // $data["yesterday_data_count"] = $data["yesterday_data_count"]->whereBetween('expiry_date', [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()])->count();

        // $data["next_week_data_count"] = clone $data_query;
        // $data["next_week_data_count"] = $data["next_week_data_count"]->whereBetween('expiry_date', [$start_date_of_next_week, ($end_date_of_next_week . ' 23:59:59')])->count();

        // $data["this_week_data_count"] = clone $data_query;
        // $data["this_week_data_count"] = $data["this_week_data_count"]->whereBetween('expiry_date', [$start_date_of_this_week, ($end_date_of_this_week . ' 23:59:59')])->count();



        // $data["next_month_data_count"] = clone $data_query;
        // $data["next_month_data_count"] = $data["next_month_data_count"]->whereBetween('expiry_date', [$start_date_of_next_month, ($end_date_of_next_month . ' 23:59:59')])->count();

        // $data["this_month_data_count"] = clone $data_query;
        // $data["this_month_data_count"] = $data["this_month_data_count"]->whereBetween('expiry_date', [$start_date_of_this_month, ($end_date_of_this_month . ' 23:59:59')])->count();


        // $expires_in_days = [15,30,60];
        // foreach($expires_in_days as $expires_in_day){
        //     $query_day = Carbon::now()->addDays($expires_in_day);
        //     $data[("expires_in_". $expires_in_day ."_days")] = clone $data_query;
        //     $data[("expires_in_". $expires_in_day ."_days")] = $data[("expires_in_". $expires_in_day ."_days")]->whereBetween('expiry_date', [$today, ($query_day->endOfDay() . ' 23:59:59')])->count();
        // }


        // $data["date_ranges"] = [
        //     "today_data_count_date_range" => [$today->copy()->startOfDay(), $today->copy()->endOfDay() ],
        //     "yesterday_data_count_date_range" => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
        //     "next_week_data_count_date_range" => [$start_date_of_next_week, ($end_date_of_next_week )],
        //     "this_week_data_count_date_range" => [$start_date_of_this_week, ($end_date_of_this_week )],
        //     "previous_week_data_count_date_range" => [$start_date_of_previous_week, ($end_date_of_previous_week )],
        //     "next_month_data_count_date_range" => [$start_date_of_next_month, ($end_date_of_next_month )],
        //     "this_month_data_count_date_range" => [$start_date_of_this_month, ($end_date_of_this_month )],
        //     "previous_month_data_count_date_range" => [$start_date_of_previous_month, ($end_date_of_previous_month)],
        //   ];

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
        ->where("business_id",auth()->user()->business_id)
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

            if($latest_expired_record) {
                 $current_data = EmployeeSponsorshipHistory::where('user_id', $record->user_id)
                ->where($expiry_date_column, $latest_expired_record[$expiry_date_column])
                ->where($issue_date_column, '<', now())
                ->orderByDesc("id")
                // ->orderByDesc($issue_date_column)
                ->first();
            } else {
               return NULL;
            }
            return $current_data?$current_data->id:NULL;
        })
        ->filter()->values();



        $data_query  = EmployeeSponsorshipHistory::whereIn('id', $employee_sponsorship_history_ids)
        ->where($expiry_date_column,">=", today());


        $data["total_data_count"] = $data_query->count();

        $data["today_data_count"] = clone $data_query;
        $data["today_data_count"] = $data["today_data_count"]->whereBetween('expiry_date', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->count();

        $data["yesterday_data_count"] = clone $data_query;
        $data["yesterday_data_count"] = $data["yesterday_data_count"]->whereBetween('expiry_date', [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()])->count();

        $data["next_week_data_count"] = clone $data_query;
        $data["next_week_data_count"] = $data["next_week_data_count"]->whereBetween('expiry_date', [$start_date_of_next_week, ($end_date_of_next_week . ' 23:59:59')])->count();

        $data["this_week_data_count"] = clone $data_query;
        $data["this_week_data_count"] = $data["this_week_data_count"]->whereBetween('expiry_date', [$start_date_of_this_week, ($end_date_of_this_week . ' 23:59:59')])->count();



        $data["next_month_data_count"] = clone $data_query;
        $data["next_month_data_count"] = $data["next_month_data_count"]->whereBetween('expiry_date', [$start_date_of_next_month, ($end_date_of_next_month . ' 23:59:59')])->count();

        $data["this_month_data_count"] = clone $data_query;
        $data["this_month_data_count"] = $data["this_month_data_count"]->whereBetween('expiry_date', [$start_date_of_this_month, ($end_date_of_this_month . ' 23:59:59')])->count();


        $expires_in_days = [15,30,60];
        foreach($expires_in_days as $expires_in_day){
            $query_day = Carbon::now()->addDays($expires_in_day);
            $data[("expires_in_". $expires_in_day ."_days")] = clone $data_query;
            $data[("expires_in_". $expires_in_day ."_days")] = $data[("expires_in_". $expires_in_day ."_days")]->whereBetween('expiry_date', [$today, ($query_day->endOfDay() . ' 23:59:59')])->count();
        }


        $data["date_ranges"] = [
            "today_data_count_date_range" => [$today->copy()->startOfDay(), $today->copy()->endOfDay() ],
            "yesterday_data_count_date_range" => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            "next_week_data_count_date_range" => [$start_date_of_next_week, ($end_date_of_next_week )],
            "this_week_data_count_date_range" => [$start_date_of_this_week, ($end_date_of_this_week )],
            "previous_week_data_count_date_range" => [$start_date_of_previous_week, ($end_date_of_previous_week )],
            "next_month_data_count_date_range" => [$start_date_of_next_month, ($end_date_of_next_month )],
            "this_month_data_count_date_range" => [$start_date_of_this_month, ($end_date_of_this_month )],
            "previous_month_data_count_date_range" => [$start_date_of_previous_month, ($end_date_of_previous_month)],
          ];

        return $data;
    }



    public function self_pension_expiries_in(
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


        $employee_pension_history_ids = EmployeePensionHistory::select('id','user_id')
        ->whereHas("employee.department_user.department", function ($query) use ($all_manager_department_ids) {
            $query->whereIn("departments.id", $all_manager_department_ids);
        })
        ->whereHas("employee", function ($query) use ($all_manager_department_ids) {
            $query->where("users.pension_eligible",">",0);
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

                if(empty($current_data))
                {
                    return NULL;
                }


                return $current_data->id;
        })
        ->filter()->values();

        $data_query  = EmployeePensionHistory::whereIn('id', $employee_pension_history_ids)->where($expiry_date_column,">=", today());














        $data["total_data_count"] = $data_query->count();

        // $data["today_data_count"] = clone $data_query;
        // $data["today_data_count"] = $data["today_data_count"]->whereBetween($expiry_date_column, [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->count();

        // $data["yesterday_data_count"] = clone $data_query;
        // $data["yesterday_data_count"] = $data["yesterday_data_count"]->whereBetween($expiry_date_column, [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()])->count();

        // $data["next_week_data_count"] = clone $data_query;
        // $data["next_week_data_count"] = $data["next_week_data_count"]->whereBetween($expiry_date_column, [$start_date_of_next_week, ($end_date_of_next_week . ' 23:59:59')])->count();

        // $data["this_week_data_count"] = clone $data_query;
        // $data["this_week_data_count"] = $data["this_week_data_count"]->whereBetween($expiry_date_column, [$start_date_of_this_week, ($end_date_of_this_week . ' 23:59:59')])->count();



        // $data["next_month_data_count"] = clone $data_query;
        // $data["next_month_data_count"] = $data["next_month_data_count"]->whereBetween($expiry_date_column, [$start_date_of_next_month, ($end_date_of_next_month . ' 23:59:59')])->count();

        // $data["this_month_data_count"] = clone $data_query;
        // $data["this_month_data_count"] = $data["this_month_data_count"]->whereBetween($expiry_date_column, [$start_date_of_this_month, ($end_date_of_this_month . ' 23:59:59')])->count();


        // $expires_in_days = [15,30,60];
        // foreach($expires_in_days as $expires_in_day){
        //     $query_day = Carbon::now()->addDays($expires_in_day);
        //     $data[("expires_in_". $expires_in_day ."_days")] = clone $data_query;
        //     $data[("expires_in_". $expires_in_day ."_days")] = $data[("expires_in_". $expires_in_day ."_days")]->whereBetween($expiry_date_column, [$today, ($query_day->endOfDay() . ' 23:59:59')])->count();
        // }


        // $data["date_ranges"] = [
        //     "today_data_count_date_range" => [$today->copy()->startOfDay(), $today->copy()->endOfDay() ],
        //     "yesterday_data_count_date_range" => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
        //     "next_week_data_count_date_range" => [$start_date_of_next_week, ($end_date_of_next_week )],
        //     "this_week_data_count_date_range" => [$start_date_of_this_week, ($end_date_of_this_week )],
        //     "previous_week_data_count_date_range" => [$start_date_of_previous_week, ($end_date_of_previous_week )],
        //     "next_month_data_count_date_range" => [$start_date_of_next_month, ($end_date_of_next_month )],
        //     "this_month_data_count_date_range" => [$start_date_of_this_month, ($end_date_of_this_month )],
        //     "previous_month_data_count_date_range" => [$start_date_of_previous_month, ($end_date_of_previous_month)],
        //   ];

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


        $employee_pension_history_ids = EmployeePensionHistory::select('id','user_id')
        ->whereHas("employee.department_user.department", function ($query) use ($all_manager_department_ids) {
            $query->whereIn("departments.id", $all_manager_department_ids);
        })
        ->whereHas("employee", function ($query) use ($all_manager_department_ids) {
            $query->where("users.pension_eligible",">",0);
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

                if(empty($current_data))
                {
                    return NULL;
                }


                return $current_data->id;
        })
        ->filter()->values();

        $data_query  = EmployeePensionHistory::whereIn('id', $employee_pension_history_ids)->where($expiry_date_column,">=", today());














        $data["total_data_count"] = $data_query->count();

        $data["today_data_count"] = clone $data_query;
        $data["today_data_count"] = $data["today_data_count"]->whereBetween($expiry_date_column, [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->count();

        $data["yesterday_data_count"] = clone $data_query;
        $data["yesterday_data_count"] = $data["yesterday_data_count"]->whereBetween($expiry_date_column, [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()])->count();

        $data["next_week_data_count"] = clone $data_query;
        $data["next_week_data_count"] = $data["next_week_data_count"]->whereBetween($expiry_date_column, [$start_date_of_next_week, ($end_date_of_next_week . ' 23:59:59')])->count();

        $data["this_week_data_count"] = clone $data_query;
        $data["this_week_data_count"] = $data["this_week_data_count"]->whereBetween($expiry_date_column, [$start_date_of_this_week, ($end_date_of_this_week . ' 23:59:59')])->count();



        $data["next_month_data_count"] = clone $data_query;
        $data["next_month_data_count"] = $data["next_month_data_count"]->whereBetween($expiry_date_column, [$start_date_of_next_month, ($end_date_of_next_month . ' 23:59:59')])->count();

        $data["this_month_data_count"] = clone $data_query;
        $data["this_month_data_count"] = $data["this_month_data_count"]->whereBetween($expiry_date_column, [$start_date_of_this_month, ($end_date_of_this_month . ' 23:59:59')])->count();


        $expires_in_days = [15,30,60];
        foreach($expires_in_days as $expires_in_day){
            $query_day = Carbon::now()->addDays($expires_in_day);
            $data[("expires_in_". $expires_in_day ."_days")] = clone $data_query;
            $data[("expires_in_". $expires_in_day ."_days")] = $data[("expires_in_". $expires_in_day ."_days")]->whereBetween($expiry_date_column, [$today, ($query_day->endOfDay() . ' 23:59:59')])->count();
        }


        $data["date_ranges"] = [
            "today_data_count_date_range" => [$today->copy()->startOfDay(), $today->copy()->endOfDay() ],
            "yesterday_data_count_date_range" => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            "next_week_data_count_date_range" => [$start_date_of_next_week, ($end_date_of_next_week )],
            "this_week_data_count_date_range" => [$start_date_of_this_week, ($end_date_of_this_week )],
            "previous_week_data_count_date_range" => [$start_date_of_previous_week, ($end_date_of_previous_week )],
            "next_month_data_count_date_range" => [$start_date_of_next_month, ($end_date_of_next_month )],
            "this_month_data_count_date_range" => [$start_date_of_this_month, ($end_date_of_this_month )],
            "previous_month_data_count_date_range" => [$start_date_of_previous_month, ($end_date_of_previous_month)],
          ];

        return $data;
    }



    public function absent_today(
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

        $day_number = Carbon::parse(today())->dayOfWeek;

        $data_query  = User::whereHas("department_user.department", function ($query) use ($all_manager_department_ids) {
            $query->whereIn("departments.id", $all_manager_department_ids);
        })
            ->whereNotIn('id', [auth()->user()->id])
           ->whereDoesntHave("employee_rota.details", function($query) use($day_number) {
            $query->where([
                "employee_rota_details.day" => $day_number,

            ]);
           })
           ->whereDoesntHave("attendances", function($query) {
            $query->where("attendances.in_date",today())
            ->where([
                "is_present" => 1
            ]);
           });

           $data["total_data_count"] = $data_query->count();

        // $data["date_ranges"] = [
        //     "today_data_count_date_range" => [$today->copy()->startOfDay(), $today->copy()->endOfDay() ],
        //     "yesterday_data_count_date_range" => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
        //     "next_week_data_count_date_range" => [$start_date_of_next_week, ($end_date_of_next_week )],
        //     "this_week_data_count_date_range" => [$start_date_of_this_week, ($end_date_of_this_week )],
        //     "previous_week_data_count_date_range" => [$start_date_of_previous_week, ($end_date_of_previous_week )],
        //     "next_month_data_count_date_range" => [$start_date_of_next_month, ($end_date_of_next_month )],
        //     "this_month_data_count_date_range" => [$start_date_of_this_month, ($end_date_of_this_month )],
        //     "previous_month_data_count_date_range" => [$start_date_of_previous_month, ($end_date_of_previous_month)],
        //   ];

        return $data;
    }

    public function present_today(
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

        $day_number = Carbon::parse(today())->dayOfWeek;

        $data_query  = User::whereHas("department_user.department", function ($query) use ($all_manager_department_ids) {
            $query->whereIn("departments.id", $all_manager_department_ids);
        })
            ->whereNotIn('id', [auth()->user()->id])
        //    ->whereHas("employee_rota.details", function($query) use($day_number) {
        //     $query->where([
        //         "employee_rota_details.is_weekend" => 0,
        //         "employee_rota_details.day" => $day_number,

        //     ]);
        //    })
           ->whereHas("attendances", function($query) {
            $query->where("attendances.in_date",today())
            ->where([
                "is_present" => 1
            ]);
           });

           $data["total_data_count"] = $data_query->count();

        // $data["date_ranges"] = [
        //     "today_data_count_date_range" => [$today->copy()->startOfDay(), $today->copy()->endOfDay() ],
        //     "yesterday_data_count_date_range" => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
        //     "next_week_data_count_date_range" => [$start_date_of_next_week, ($end_date_of_next_week )],
        //     "this_week_data_count_date_range" => [$start_date_of_this_week, ($end_date_of_this_week )],
        //     "previous_week_data_count_date_range" => [$start_date_of_previous_week, ($end_date_of_previous_week )],
        //     "next_month_data_count_date_range" => [$start_date_of_next_month, ($end_date_of_next_month )],
        //     "this_month_data_count_date_range" => [$start_date_of_this_month, ($end_date_of_this_month )],
        //     "previous_month_data_count_date_range" => [$start_date_of_previous_month, ($end_date_of_previous_month)],
        //   ];

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
        ->where("business_id",auth()->user()->business_id)
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

            if($latest_expired_record) {
                 $current_data = EmployeeSponsorshipHistory::where('user_id', $record->user_id)
                ->where($expiry_date_column, $latest_expired_record[$expiry_date_column])
                ->where($issue_date_column, '<', now())
                ->orderByDesc("id")
                // ->orderByDesc($issue_date_column)
                ->first();
            } else {
               return NULL;
            }
            return $current_data?$current_data->id:NULL;
        })
        ->filter()->values();



        $data_query  = EmployeeSponsorshipHistory::whereIn('id', $employee_sponsorship_history_ids)
        ->where([
            "current_certificate_status"=>$current_certificate_status,
            "business_id"=>auth()->user()->business_id
        ]);




        $data["total_data_count"] = $data_query->count();

        $data["today_data_count"] = clone $data_query;
        $data["today_data_count"] = $data["today_data_count"]->whereBetween('expiry_date', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->count();

        $data["next_week_data_count"] = clone $data_query;
        $data["next_week_data_count"] = $data["next_week_data_count"]->whereBetween('expiry_date', [$start_date_of_next_week, ($end_date_of_next_week . ' 23:59:59')])->count();

        $data["this_week_data_count"] = clone $data_query;
        $data["this_week_data_count"] = $data["this_week_data_count"]->whereBetween('expiry_date', [$start_date_of_this_week, ($end_date_of_this_week . ' 23:59:59')])->count();

        $data["previous_week_data_count"] = clone $data_query;
        $data["previous_week_data_count"] = $data["previous_week_data_count"]->whereBetween('expiry_date', [$start_date_of_previous_week, ($end_date_of_previous_week . ' 23:59:59')])->count();

        $data["next_month_data_count"] = clone $data_query;
        $data["next_month_data_count"] = $data["next_month_data_count"]->whereBetween('expiry_date', [$start_date_of_next_month, ($end_date_of_next_month . ' 23:59:59')])->count();

        $data["this_month_data_count"] = clone $data_query;
        $data["this_month_data_count"] = $data["this_month_data_count"]->whereBetween('expiry_date', [$start_date_of_this_month, ($end_date_of_this_month . ' 23:59:59')])->count();

        $data["previous_month_data_count"] = clone $data_query;
        $data["previous_month_data_count"] = $data["previous_month_data_count"]->whereBetween('expiry_date', [$start_date_of_previous_month, ($end_date_of_previous_month . ' 23:59:59')])->count();
        $data["date_ranges"] = [
            "today_data_count_date_range" => [$today->copy()->startOfDay(), $today->copy()->endOfDay() ],
            "yesterday_data_count_date_range" => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            "next_week_data_count_date_range" => [$start_date_of_next_week, ($end_date_of_next_week )],
            "this_week_data_count_date_range" => [$start_date_of_this_week, ($end_date_of_this_week )],
            "previous_week_data_count_date_range" => [$start_date_of_previous_week, ($end_date_of_previous_week )],
            "next_month_data_count_date_range" => [$start_date_of_next_month, ($end_date_of_next_month )],
            "this_month_data_count_date_range" => [$start_date_of_this_month, ($end_date_of_this_month )],
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
        ->whereHas("employee", function ($query)  {
            $query->where("users.pension_eligible",">",0)
            ->where("is_active",1);
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

                if($current_data)
                {
                    return NULL;
                }



                return $current_data->id;
        })
        ->filter()->values();




        $data_query  = EmployeePensionHistory::whereIn('id', $employee_pension_history_ids)
        ->when(!empty($status_column), function($query) use ($status_column,$status_value) {
         $query->where($status_column, $status_value);
        });





        $data["total_data_count"] = $data_query->count();

        $data["today_data_count"] = clone $data_query;
        $data["today_data_count"] = $data["today_data_count"]->whereBetween($expiry_date_column, [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->count();

        $data["next_week_data_count"] = clone $data_query;
        $data["next_week_data_count"] = $data["next_week_data_count"]->whereBetween($expiry_date_column, [$start_date_of_next_week, ($end_date_of_next_week . ' 23:59:59')])->count();

        $data["this_week_data_count"] = clone $data_query;
        $data["this_week_data_count"] = $data["this_week_data_count"]->whereBetween($expiry_date_column, [$start_date_of_this_week, ($end_date_of_this_week . ' 23:59:59')])->count();

        $data["previous_week_data_count"] = clone $data_query;
        $data["previous_week_data_count"] = $data["previous_week_data_count"]->whereBetween($expiry_date_column, [$start_date_of_previous_week, ($end_date_of_previous_week . ' 23:59:59')])->count();

        $data["next_month_data_count"] = clone $data_query;
        $data["next_month_data_count"] = $data["next_month_data_count"]->whereBetween($expiry_date_column, [$start_date_of_next_month, ($end_date_of_next_month . ' 23:59:59')])->count();

        $data["this_month_data_count"] = clone $data_query;
        $data["this_month_data_count"] = $data["this_month_data_count"]->whereBetween($expiry_date_column, [$start_date_of_this_month, ($end_date_of_this_month . ' 23:59:59')])->count();

        $data["previous_month_data_count"] = clone $data_query;
        $data["previous_month_data_count"] = $data["previous_month_data_count"]->whereBetween($expiry_date_column, [$start_date_of_previous_month, ($end_date_of_previous_month . ' 23:59:59')])->count();

        $data["date_ranges"] = [
            "today_data_count_date_range" => [$today->copy()->startOfDay(), $today->copy()->endOfDay() ],
            "yesterday_data_count_date_range" => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            "next_week_data_count_date_range" => [$start_date_of_next_week, ($end_date_of_next_week )],
            "this_week_data_count_date_range" => [$start_date_of_this_week, ($end_date_of_this_week )],
            "previous_week_data_count_date_range" => [$start_date_of_previous_week, ($end_date_of_previous_week )],
            "next_month_data_count_date_range" => [$start_date_of_next_month, ($end_date_of_next_month )],
            "this_month_data_count_date_range" => [$start_date_of_this_month, ($end_date_of_this_month )],
            "previous_month_data_count_date_range" => [$start_date_of_previous_month, ($end_date_of_previous_month)],
          ];


        return $data;
    }


    public function self_holidays(
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


        $data["total_data"] = $this->userManagementComponent->getHolodayDetails(
        auth()->user()->id,
        request()->start_date,
        request()->end_date,
        true,
        false)
        ;



        return $data;
    }



    public function self_tasks(
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


        $data["total_data"] = Project::
        with([
             "tasks"
        ])
        ->whereHas("tasks.assignees" , function($query) {

            $query->where("users.id",auth()->user()->id);

        })
        ;



        return $data;
    }


    public function self_work_shift(
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

        $work_shift_history =  $this->workShiftHistoryComponent->get_work_shift_history(today(), auth()->user()->id,false);

        $work_shift_history->details = $work_shift_history->details();


        $data["total_data"] = $work_shift_history;


        return $data;
    }




    public function getBusinessUserDashboardData(Request $request)
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













      $dashboard_widgets =  DashboardWidget::where([
                "user_id" => auth()->user()->id
            ])
            ->get()
            ->keyBy('widget_name');




            $all_manager_department_ids = $this->get_all_departments_of_manager();




            $data["employees"] = $this->employees(
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

            $widget = $dashboard_widgets->get("employees");

            $data["employees"]["id"] = 1;
            if($widget) {
                $data["employees"]["widget_id"] = $widget->id;
                $data["employees"]["widget_order"] = $widget->widget_order;
            }
            else {
                $data["employees"]["widget_id"] = 0;
                $data["employees"]["widget_order"] = 0;
            }

            $data["employees"]["widget_name"] = "employees";
            $data["employees"]["widget_type"] = "default";
            $data["employees"]["route"] =  '/employee/all-employees?';



            $data["employee_on_holiday"] = $this->employee_on_holiday(
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
            $widget = $dashboard_widgets->get("employee_on_holiday");


            $data["employee_on_holiday"]["id"] = 2;
            if($widget) {
                $data["employee_on_holiday"]["widget_id"] = $widget->id;
                $data["employee_on_holiday"]["widget_order"] = $widget->widget_order;
            }
            else {
                $data["employee_on_holiday"]["widget_id"] = 0;
                $data["employee_on_holiday"]["widget_order"] = 0;
            }

            $data["employee_on_holiday"]["widget_name"] = "employee_on_holiday";
            $data["employee_on_holiday"]["widget_type"] = "default";
            $data["employee_on_holiday"]["route"] =  '/employee/all-employees?is_on_holiday=1&';

            $start_id = 3;
            $leave_statuses = ['pending_approval','in_progress', 'approved','rejected'];
            foreach ($leave_statuses as $leave_status) {
                $data[($leave_status . "_leaves")] = $this->leaves(
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
                    $leave_status
                );
                $widget = $dashboard_widgets->get(($leave_status . "_leaves"));



                $data[($leave_status . "_leaves")]["id"] = $start_id++;
                if($widget) {
                    $data[($leave_status . "_leaves")]["widget_id"] = $widget->id;
                    $data[($leave_status . "_leaves")]["widget_order"] = $widget->widget_order;
                }
                else {
                    $data[($leave_status . "_leaves")]["widget_id"] = 0;
                    $data[($leave_status . "_leaves")]["widget_order"] = 0;
                }


                $data[($leave_status . "_leaves")]["widget_name"] = ($leave_status . "_leaves");

                $data[($leave_status . "_leaves")]["widget_type"] = "default";


                $data[($leave_status . "_leaves")]["route"] = ('/leave/leaves?status=' . $leave_status . "&");
            }



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
                $all_manager_department_ids
            );
            $widget = $dashboard_widgets->get("open_roles");


            $data["open_roles"]["id"] = $start_id++;
            if($widget) {
                $data["open_roles"]["widget_id"] = $widget->id;
                $data["open_roles"]["widget_order"] = $widget->widget_order;
            }
            else {
                $data["open_roles"]["widget_id"] = 0;
                $data["open_roles"]["widget_order"] = 0;
            }


            $data["open_roles"]["widget_name"] = "open_roles";

              $data["open_roles"]["widget_type"] = "default";

            $data["open_roles"]["route"] = "/job-desk/job-list?is_open_roles=1&";


            $data["upcoming_passport_expiries"] = $this->upcoming_passport_expiries(
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
            $widget = $dashboard_widgets->get("upcoming_passport_expiries");


            $data["upcoming_passport_expiries"]["id"] =$start_id++;
            if($widget) {
                $data["upcoming_passport_expiries"]["widget_id"] = $widget->id;
                $data["upcoming_passport_expiries"]["widget_order"] = $widget->widget_order;
            }
            else {
                $data["upcoming_passport_expiries"]["widget_id"] = 0;
                $data["upcoming_passport_expiries"]["widget_order"] = 0;
            }





            $data["upcoming_passport_expiries"]["widget_name"] = "upcoming_passport_expiries";
            $data["upcoming_passport_expiries"]["widget_type"] = "multiple_upcoming_days";
            $data["upcoming_passport_expiries"]["route"] = "/employee/all-employees?upcoming_expiries=passport&";

            $data["upcoming_visa_expiries"] = $this->upcoming_visa_expiries(
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
            $widget = $dashboard_widgets->get("upcoming_visa_expiries");


            $data["upcoming_visa_expiries"]["id"] = $start_id++;
            if($widget) {
                $data["upcoming_visa_expiries"]["widget_id"] = $widget->id;
                $data["upcoming_visa_expiries"]["widget_order"] = $widget->widget_order;
            }
            else {
                $data["upcoming_visa_expiries"]["widget_id"] = 0;
                $data["upcoming_visa_expiries"]["widget_order"] = 0;
            }


            $data["upcoming_visa_expiries"]["widget_name"] = "upcoming_visa_expiries";
            $data["upcoming_visa_expiries"]["widget_type"] = "multiple_upcoming_days";


            $data["upcoming_visa_expiries"]["route"] = "/employee/all-employees?upcoming_expiries=visa&";



            $data["upcoming_right_to_work_expiries"] = $this->upcoming_right_to_work_expiries(
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
            $widget = $dashboard_widgets->get("upcoming_right_to_work_expiries");


            $data["upcoming_right_to_work_expiries"]["id"] = $start_id++;
            if($widget) {
                $data["upcoming_right_to_work_expiries"]["widget_id"] = $widget->id;
                $data["upcoming_right_to_work_expiries"]["widget_order"] = $widget->widget_order;
            }
            else {
                $data["upcoming_right_to_work_expiries"]["widget_id"] = 0;
                $data["upcoming_right_to_work_expiries"]["widget_order"] = 0;
            }


            $data["upcoming_right_to_work_expiries"]["widget_name"] = "upcoming_right_to_work_expiries";
            $data["upcoming_right_to_work_expiries"]["widget_type"] = "multiple_upcoming_days";
            $data["upcoming_right_to_work_expiries"]["route"] = "/employee/all-employees?upcoming_expiries=right_to_work&";











            $data["upcoming_sponsorship_expiries"] = $this->upcoming_sponsorship_expiries(
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
            $widget = $dashboard_widgets->get("upcoming_sponsorship_expiries");



            $data["upcoming_sponsorship_expiries"]["id"] = $start_id++;
            if($widget) {
                $data["upcoming_sponsorship_expiries"]["widget_id"] = $widget->id;
                $data["upcoming_sponsorship_expiries"]["widget_order"] = $widget->widget_order;
            }
            else {
                $data["upcoming_sponsorship_expiries"]["widget_id"] = 0;
                $data["upcoming_sponsorship_expiries"]["widget_order"] = 0;
            }


            $data["upcoming_sponsorship_expiries"]["widget_name"] = "upcoming_sponsorship_expiries";
            $data["upcoming_sponsorship_expiries"]["widget_type"] = "multiple_upcoming_days";
            $data["upcoming_sponsorship_expiries"]["route"] = "/employee/all-employees?upcoming_expiries=sponsorship&";





            $sponsorship_statuses = ['unassigned', 'assigned', 'visa_applied','visa_rejected','visa_grantes','withdrawal'];
            foreach ($sponsorship_statuses as $sponsorship_status) {
                $data[($sponsorship_status . "_sponsorships")] = $this->sponsorships(
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
                $widget = $dashboard_widgets->get(($sponsorship_status . "_sponsorships"));


                $data[($sponsorship_status . "_sponsorships")]["id"] = $start_id++;
                if($widget) {
                    $data[($sponsorship_status . "_sponsorships")]["widget_id"] = $widget->id;
                    $data[($sponsorship_status . "_sponsorships")]["widget_order"] = $widget->widget_order;
                }
                else {
                    $data[($sponsorship_status . "_sponsorships")]["widget_id"] = 0;
                    $data[($sponsorship_status . "_sponsorships")]["widget_order"] = 0;
                }


                $data[($sponsorship_status . "_sponsorships")]["widget_name"] = ($sponsorship_status . "_sponsorships");

                $data[($sponsorship_status . "_sponsorships")]["widget_type"] = "default";
                $data[($sponsorship_status . "_sponsorships")]["route"] = '/employee/all-employees?sponsorship_status=' . $sponsorship_status . "&";

            }









            $data["upcoming_pension_expiries"] = $this->upcoming_pension_expiries(
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


            $widget = $dashboard_widgets->get("upcoming_pension_expiries");


            $data["upcoming_pension_expiries"]["id"] = $start_id++;
            if($widget) {
                $data["upcoming_pension_expiries"]["widget_id"] = $widget->id;
                $data["upcoming_pension_expiries"]["widget_order"] = $widget->widget_order;
            }
            else {
                $data["upcoming_pension_expiries"]["widget_id"] = 0;
                $data["upcoming_pension_expiries"]["widget_order"] = 0;
            }



            $data["upcoming_pension_expiries"]["widget_name"] = "upcoming_pension_expiries";

            $data["upcoming_pension_expiries"]["widget_type"] = "multiple_upcoming_days";

            $data["upcoming_pension_expiries"]["route"] = "/employee/all-employees?upcoming_expiries=pension&";


            $pension_statuses = ["opt_in", "opt_out"];
            foreach ($pension_statuses as $pension_status) {
                $data[($pension_status . "_pensions")] = $this->pensions(
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
                $widget = $dashboard_widgets->get(($pension_status . "_pensions"));


                $data[($pension_status . "_pensions")]["id"] = $start_id++;
                if($widget) {
                    $data[($pension_status . "_pensions")]["widget_id"] = $widget->id;
                    $data[($pension_status . "_pensions")]["widget_order"] = $widget->widget_order;
                }
                else {
                    $data[($pension_status . "_pensions")]["widget_id"] = 0;
                    $data[($pension_status . "_pensions")]["widget_order"] = 0;
                }


                $data[($pension_status . "_pensions")]["widget_name"] = ($pension_status . "_pensions");
                $data[($pension_status . "_pensions")]["widget_type"] = "default";
                $data[($pension_status . "_pensions")]["route"] = '/employee/all-employees?pension_scheme_status=' . $pension_status . "&";
            }


            $employment_statuses = $this->getEmploymentStatuses();

            foreach ($employment_statuses as $employment_status) {
                $data["emplooyment_status_wise"]["data"][($employment_status->name . "_employees")] = $this->employees_by_employment_status(
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
                $widget = $dashboard_widgets->get(("employment_status_wise_employee"));


                $data["emplooyment_status_wise"]["id"] = $start_id++;
                if($widget) {
                    $data["emplooyment_status_wise"]["widget_id"] = $widget->id;
                    $data["emplooyment_status_wise"]["widget_order"] = $widget->widget_order;
                }
                else {
                    $data["emplooyment_status_wise"]["widget_id"] = 0;
                    $data["emplooyment_status_wise"]["widget_order"] = 0;
                }


                $data["emplooyment_status_wise"]["widget_name"] = "employment_status_wise_employee";
                $data["emplooyment_status_wise"]["widget_type"] = "graph";

                $data["emplooyment_status_wise"]["route"] = ('/employee/?status=' . $employment_status->name . "&");
            }













            $data["absent_today"] = $this->absent_today(
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


            $widget = $dashboard_widgets->get("absent_today");


            $data["absent_today"]["id"] = $start_id++;
            if($widget) {
                $data["absent_today"]["widget_id"] = $widget->id;
                $data["absent_today"]["widget_order"] = $widget->widget_order;
            }
            else {
                $data["absent_today"]["widget_id"] = 0;
                $data["absent_today"]["widget_order"] = 0;
            }



            $data["absent_today"]["widget_name"] = "absent_today";
            $data["absent_today"]["widget_type"] = "default";
            $data["absent_today"]["route"] = "/employee/all-employees?absent_today=1&";








            $data["present_today"] = $this->present_today(
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


            $widget = $dashboard_widgets->get("present_today");


            $data["present_today"]["id"] = $start_id++;
            if($widget) {
                $data["present_today"]["widget_id"] = $widget->id;
                $data["present_today"]["widget_order"] = $widget->widget_order;
            }
            else {
                $data["present_today"]["widget_id"] = 0;
                $data["present_today"]["widget_order"] = 0;
            }



            $data["present_today"]["widget_name"] = "present_today";
            $data["present_today"]["widget_type"] = "default";
            $data["present_today"]["route"] = "/employee/all-employees?present_today=1&";


            return response()->json($data, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }







     public function getHolidayData($all_parent_department_ids) {

      $data =  Holiday::where(
                [
                    "holidays.business_id" => auth()->user()->business_id
                ]
            )
            ->where(function ($query) use ($all_parent_department_ids) {
                $query->whereHas("departments", function ($query) use ($all_parent_department_ids) {
                    $query->whereIn("departments.id", $all_parent_department_ids);
                })
                    ->orWhereHas("users", function ($query) {
                        $query->whereIn(
                            "users.id",
                            [auth()->user()->id]
                        );
                    })
                    ->orWhere(function ($query) {
                        $query->whereDoesntHave("users")
                            ->whereDoesntHave("departments");
                    });
            })
            ->where("start_date", ">", today())
            ->orderBy('start_date',"ASC")


            ->first();
            return $data;

     }
     public function getNotifications() {
        $data = Notification::with("sender","business")->where([
            "receiver_id" => auth()->user()->id
        ]
    )
    ->orderBy("notifications.id", "DESC")
    ->take(6)->get();



            return $data;

     }




     public function getAnnouncements($all_parent_department_ids) {



        $this->addAnnouncementIfMissing($all_parent_department_ids);

           $data = Announcement::with([
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
          "announcements.business_id" => auth()->user()->business_id
               ]
           )
           ->whereHas("users", function($query)  {
              $query->where("user_announcements.user_id",auth()->user()->id);
          })
          ->orderBy("created_at","DESC")

->take(7)->get();



            return $data;

     }






     public function getOngoingProjects() {



        $data = Project::with([
            "creator" => function ($query) {
                $query->select('users.id',
                 'users.first_Name','users.middle_Name',
                'users.last_Name');
            },
            "users" => function ($query) {
                $query->select(
                    'users.id',
                 'users.first_Name','users.middle_Name',
                'users.last_Name'
            );
            },


        ])

        ->withCount(['tasks', 'completed_tasks'])

        ->where(
            [
                "business_id" => auth()->user()->business_id
            ]
        )
        ->whereHas('users', function($query)  {
            $query->where("users.id",auth()->user()->id);
    })
    // ->whereHas("tasks.assignees" , function($query) {

    //     $query->where("users.id",auth()->user()->id);

    // })

    ->select("id","name","end_date","status")

        ->get();


        return $data;


     }











     public function presentHours() {


$authUserId = auth()->user()->id;




if(request()->input("duration") == "this_week") {

// Define start and end dates for the week
$start_date_of_this_week = Carbon::now()->startOfWeek();
$end_date_of_this_week = Carbon::now()->endOfWeek();

// Fetch weekly attendance data
$weeklyAttendance = Attendance::where('is_present', 1)
    ->where('user_id', $authUserId)
    ->whereBetween('in_date', [$start_date_of_this_week, $end_date_of_this_week->endOfDay()])
    ->select('id', 'total_paid_hours', 'break_hours', 'in_date')
    ->get();

// Initialize an array for week data
$weekData = [];

// Define an array of days for reference
$daysOfWeek = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

// Process each day of the week
foreach ($daysOfWeek as $index => $day) {
    $date = $start_date_of_this_week->copy()->addDays($index);
    $dateTitle = $date->format('d-m-Y');

    // Find the attendance record for the current day
    $attendanceRecord = $weeklyAttendance->firstWhere('in_date', $date->toDateString());

    if ($attendanceRecord) {
        $workingHours = $attendanceRecord->total_paid_hours;
        $breakHours = $attendanceRecord->break_hours;
    } else {
        $workingHours = 0;
        $breakHours = 0;
    }

    // Add data to weekData array
    $weekData[] = [
        'name' => $day,
        'working_hours' => $workingHours,
        'break_hours' => -number_format($breakHours, 2),
        'date_title' => $dateTitle,
    ];
}

    return $weekData;


}


if(request()->input("duration") == "this_month" ) {
// Define start and end dates for the month
$start_date_of_this_month = Carbon::now()->startOfMonth();
$end_date_of_this_month = Carbon::now()->endOfMonth();

// Fetch monthly attendance data
$monthlyAttendance = Attendance::where('is_present', 1)
    ->where('user_id', $authUserId)
    ->whereBetween('in_date', [$start_date_of_this_month, $end_date_of_this_month->endOfDay()])
    ->select('id', 'total_paid_hours', 'break_hours', 'in_date')
    ->get();


// Initialize an array for month data
$monthData = [];

// Process each day of the month
for ($date = clone $start_date_of_this_month; $date->lte($end_date_of_this_month); $date->addDay()) {
    $dateTitle = $date->format('d-m-Y');
    $dayName = $date->format('d'); // Get the three-letter day name

    // Find the attendance record for the current day
    $attendanceRecord = $monthlyAttendance->firstWhere('in_date', $date->toDateString());

    if ($attendanceRecord) {
        $workingHours = $attendanceRecord->total_paid_hours;
        $breakHours = $attendanceRecord->break_hours;
    } else {
        $workingHours = 0;
        $breakHours = 0;
    }

    // Adjust break hours to negative as per your example
    $adjustedBreakHours = -$breakHours;

    // Add data to monthData array
    $monthData[] = [
        'name' => $dayName,
        'working_hours' => $workingHours,
        'break_hours' => -number_format($adjustedBreakHours, 2),
        'date_title' => $dateTitle,
    ];
}

  return $monthData;

}


if(request()->input("duration") == "this_year" ) {

    if(empty(request()->input("year"))){
        throw new Exception("year is required",400);

    }

    $last12MonthsDates = $this->getLast12MonthsDates(request()->input("year"));
$data = [];

foreach ($last12MonthsDates as $month) {
    $monthlyAttendance = Attendance::where('is_present', 1)
        ->where('user_id', $authUserId)
        ->whereBetween('in_date', [$month['start_date'], $month['end_date'] . ' 23:59:59'])
        ->selectRaw('COALESCE(SUM(total_paid_hours), 0) as total_paid_hours_sum, COALESCE(SUM(break_hours), 0) as break_hours_sum')
        ->first();

    // The $monthlyAttendance will always have the sum results, defaulted to 0 if there were no matching records
    $attendanceData = [
        "working_hours" => $monthlyAttendance->total_paid_hours_sum,
        "break_hours" => -number_format($monthlyAttendance->break_hours_sum, 2)
    ];

    $data[] = array_merge(
        ["name" => $month['month']],
        $attendanceData
    );
}

    return  $data;

}







    }








     public function createDashboardWidget(WidgetCreateRequest $request)
     {
         try {
             $this->storeActivity($request, "DUMMY activity","DUMMY description");
             return DB::transaction(function () use ($request) {

                $request_data = $request->validated();

                foreach ($request_data["widgets"] as $widget) {
                    $widget["user_id"] = auth()->user()->id;

                    DashboardWidget::updateOrCreate(
                        [
                            "widget_name" => $widget["widget_name"],
                            "user_id" => $widget["user_id"],
                        ],
                        $widget
                    );
                }

                return response(["ok" => true], 201);
             });


         } catch (Exception $e) {
             error_log($e->getMessage());
             return $this->sendError($e, 500, $request);
         }
     }

     public function deleteDashboardWidgetsByIds(Request $request, $ids)
     {

         try {
             $this->storeActivity($request, "DUMMY activity","DUMMY description");

             $idsArray = explode(',', $ids);
             $existingIds = DashboardWidget::where([
                 "user_id" => auth()->user()->id
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
             DashboardWidget::destroy($existingIds);


             return response()->json(["message" => "data deleted sussfully","deleted_ids" => $existingIds], 200);
         } catch (Exception $e) {

             return $this->sendError($e, 500, $request);
         }
     }




















    public function businesses($created_by_filter = 0)
    {
        $startDateOfThisMonth = Carbon::now()->startOfMonth();
        $endDateOfThisMonth = Carbon::now()->endOfMonth();
        $startDateOfPreviousMonth = Carbon::now()->startOfMonth()->subMonth(1);
        $endDateOfPreviousMonth = Carbon::now()->startOfMonth()->subDay(1);

        $startDateOfThisWeek = Carbon::now()->startOfWeek();
        $endDateOfThisWeek = Carbon::now()->endOfWeek();
        $startDateOfPreviousWeek = Carbon::now()->startOfWeek()->subWeek(1);
        $endDateOfPreviousWeek = Carbon::now()->endOfWeek()->subWeek(1);



        $total_data_count_query = new Candidate();
        if ($created_by_filter) {
            $total_data_count_query =  $total_data_count_query->where([
                "created_by" => auth()->user()->id
            ]);
        }

        $data["total_data_count"] = $total_data_count_query->count();



        $this_week_data_query = Business::whereBetween('created_at', [$startDateOfThisWeek, $endDateOfThisWeek]);

        if ($created_by_filter) {
            $this_week_data_query =  $this_week_data_query->where([
                "created_by" => auth()->user()->id
            ]);
        }
        $data["this_week_data"] = $this_week_data_query->select("id", "created_at", "updated_at")->get();




        $previous_week_data_query = Business::whereBetween('created_at', [$startDateOfPreviousWeek, $endDateOfPreviousWeek]);

        if ($created_by_filter) {
            $previous_week_data_query =  $previous_week_data_query->where([
                "created_by" => auth()->user()->id
            ]);
        }

        $data["previous_week_data"] = $total_data_count_query->select("id", "created_at", "updated_at")->get();




        $this_month_data_query = Business::whereBetween('created_at', [$startDateOfThisMonth, $endDateOfThisMonth]);

        if ($created_by_filter) {
            $this_month_data_query =  $this_month_data_query->where([
                "created_by" => auth()->user()->id
            ]);
        }
        $data["this_month_data"] = $this_month_data_query->select("id", "created_at", "updated_at")->get();




        $previous_month_data_query = Business::whereBetween('created_at', [$startDateOfPreviousMonth, $endDateOfPreviousMonth]);

        if ($created_by_filter) {
            $previous_month_data_query =  $previous_month_data_query->where([
                "created_by" => auth()->user()->id
            ]);
        }
        $data["previous_month_data"] = $previous_month_data_query->select("id", "created_at", "updated_at")->get();



        $data["this_week_data_count"] = $data["this_week_data"]->count();
        $data["previous_week_data_count"] = $data["previous_week_data"]->count();
        $data["this_month_data_count"] = $data["this_month_data"]->count();
        $data["previous_month_data_count"] = $data["previous_month_data"]->count();
        return $data;
    }
    public function fuel_stations($created_by_filter = 0)
    {
        $startDateOfThisMonth = Carbon::now()->startOfMonth();
        $endDateOfThisMonth = Carbon::now()->endOfMonth();
        $startDateOfPreviousMonth = Carbon::now()->startOfMonth()->subMonth(1);
        $endDateOfPreviousMonth = Carbon::now()->startOfMonth()->subDay(1);

        $startDateOfThisWeek = Carbon::now()->startOfWeek();
        $endDateOfThisWeek = Carbon::now()->endOfWeek();
        $startDateOfPreviousWeek = Carbon::now()->startOfWeek()->subWeek(1);
        $endDateOfPreviousWeek = Carbon::now()->endOfWeek()->subWeek(1);


        $total_data_count_query = new Candidate();
        if ($created_by_filter) {
            $total_data_count_query =  $total_data_count_query->where([
                "created_by" => auth()->user()->id
            ]);
        }
        $data["total_data_count"] = $total_data_count_query->count();


        $this_week_data_query = Candidate::whereBetween('created_at', [$startDateOfThisWeek, $endDateOfThisWeek]);
        if ($created_by_filter) {
            $this_week_data_query =  $this_week_data_query->where([
                "created_by" => auth()->user()->id
            ]);
        }
        $data["this_week_data"] = $this_week_data_query->select("id", "created_at", "updated_at")
            ->get();


        $previous_week_data_query = Candidate::whereBetween('created_at', [$startDateOfPreviousWeek, $endDateOfPreviousWeek]);
        if ($created_by_filter) {
            $previous_week_data_query =  $previous_week_data_query->where([
                "created_by" => auth()->user()->id
            ]);
        }
        $data["previous_week_data"] = $previous_week_data_query->select("id", "created_at", "updated_at")
            ->get();


        $this_month_data_query =  Candidate::whereBetween('created_at', [$startDateOfThisMonth, $endDateOfThisMonth]);
        if ($created_by_filter) {
            $this_month_data_query =  $this_month_data_query->where([
                "created_by" => auth()->user()->id
            ]);
        }
        $data["this_month_data"] = $this_month_data_query->select("id", "created_at", "updated_at")
            ->get();

        $previous_month_data_query =  Candidate::whereBetween('created_at', [$startDateOfPreviousMonth, $endDateOfPreviousMonth]);
        if ($created_by_filter) {
            $previous_month_data_query =  $previous_month_data_query->where([
                "created_by" => auth()->user()->id
            ]);
        }
        $data["previous_month_data"] = $previous_month_data_query->select("id", "created_at", "updated_at")
            ->get();




        $data["this_week_data_count"] = $data["this_week_data"]->count();
        $data["previous_week_data_count"] = $data["previous_week_data"]->count();
        $data["this_month_data_count"] = $data["this_month_data"]->count();
        $data["previous_month_data_count"] = $data["previous_month_data"]->count();
        return $data;
    }

    public function customers()
    {
        $startDateOfThisMonth = Carbon::now()->startOfMonth();
        $endDateOfThisMonth = Carbon::now()->endOfMonth();
        $startDateOfPreviousMonth = Carbon::now()->startOfMonth()->subMonth(1);
        $endDateOfPreviousMonth = Carbon::now()->startOfMonth()->subDay(1);

        $startDateOfThisWeek = Carbon::now()->startOfWeek();
        $endDateOfThisWeek = Carbon::now()->endOfWeek();
        $startDateOfPreviousWeek = Carbon::now()->startOfWeek()->subWeek(1);
        $endDateOfPreviousWeek = Carbon::now()->endOfWeek()->subWeek(1);



        $data["total_data_count"] = User::with("roles")->whereHas("roles", function ($q) {
            $q->whereIn("name", ["customer"]);
        })->count();


        $data["this_week_data"] = User::with("roles")->whereHas("roles", function ($q) {
            $q->whereIn("name", ["customer"]);
        })->whereBetween('created_at', [$startDateOfThisWeek, $endDateOfThisWeek])
            ->select("id", "created_at", "updated_at")
            ->get();

        $data["previous_week_data"] = User::with("roles")->whereHas("roles", function ($q) {
            $q->whereIn("name", ["customer"]);
        })->whereBetween('created_at', [$startDateOfPreviousWeek, $endDateOfPreviousWeek])
            ->select("id", "created_at", "updated_at")
            ->get();



        $data["this_month_data"] = User::with("roles")->whereHas("roles", function ($q) {
            $q->whereIn("name", ["customer"]);
        })->whereBetween('created_at', [$startDateOfThisMonth, $endDateOfThisMonth])
            ->select("id", "created_at", "updated_at")
            ->get();
        $data["previous_month_data"] = User::with("roles")->whereHas("roles", function ($q) {
            $q->whereIn("name", ["customer"]);
        })->whereBetween('created_at', [$startDateOfPreviousMonth, $endDateOfPreviousMonth])
            ->select("id", "created_at", "updated_at")
            ->get();

        $data["this_week_data_count"] = $data["this_week_data"]->count();
        $data["previous_week_data_count"] = $data["previous_week_data"]->count();
        $data["this_month_data_count"] = $data["this_month_data"]->count();
        $data["previous_month_data_count"] = $data["previous_month_data"]->count();
        return $data;
    }
    public function overall_customer_jobs()
    {
        $startDateOfThisMonth = Carbon::now()->startOfMonth();
        $endDateOfThisMonth = Carbon::now()->endOfMonth();
        $startDateOfPreviousMonth = Carbon::now()->startOfMonth()->subMonth(1);
        $endDateOfPreviousMonth = Carbon::now()->startOfMonth()->subDay(1);

        $startDateOfThisWeek = Carbon::now()->startOfWeek();
        $endDateOfThisWeek = Carbon::now()->endOfWeek();
        $startDateOfPreviousWeek = Carbon::now()->startOfWeek()->subWeek(1);
        $endDateOfPreviousWeek = Carbon::now()->endOfWeek()->subWeek(1);



        $data["total_data_count"] = Candidate::count();


        $data["this_week_data"] = Candidate::whereBetween('created_at', [$startDateOfThisWeek, $endDateOfThisWeek])
            ->select("id", "created_at", "updated_at")
            ->get();

        $data["previous_week_data"] = Candidate::whereBetween('created_at', [$startDateOfPreviousWeek, $endDateOfPreviousWeek])
            ->select("id", "created_at", "updated_at")
            ->get();



        $data["this_month_data"] = Candidate::whereBetween('created_at', [$startDateOfThisMonth, $endDateOfThisMonth])
            ->select("id", "created_at", "updated_at")
            ->get();

        $data["previous_month_data"] = Candidate::whereBetween('created_at', [$startDateOfPreviousMonth, $endDateOfPreviousMonth])
            ->select("id", "created_at", "updated_at")
            ->get();

        $data["this_week_data_count"] = $data["this_week_data"]->count();
        $data["previous_week_data_count"] = $data["previous_week_data"]->count();
        $data["this_month_data_count"] = $data["this_month_data"]->count();
        $data["previous_month_data_count"] = $data["previous_month_data"]->count();
        return $data;
    }

    public function overall_bookings($created_by_filter = 0)
    {
        $startDateOfThisMonth = Carbon::now()->startOfMonth();
        $endDateOfThisMonth = Carbon::now()->endOfMonth();
        $startDateOfPreviousMonth = Carbon::now()->startOfMonth()->subMonth(1);
        $endDateOfPreviousMonth = Carbon::now()->startOfMonth()->subDay(1);

        $startDateOfThisWeek = Carbon::now()->startOfWeek();
        $endDateOfThisWeek = Carbon::now()->endOfWeek();
        $startDateOfPreviousWeek = Carbon::now()->startOfWeek()->subWeek(1);
        $endDateOfPreviousWeek = Carbon::now()->endOfWeek()->subWeek(1);


        $total_data_count_query =  Candidate::leftJoin('businesses', 'businesses.id', '=', 'bookings.business_id');
        if ($created_by_filter) {
            $total_data_count_query =  $total_data_count_query->where([
                "businesses.created_by" => auth()->user()->id
            ]);
        }
        $data["total_data_count"] = $total_data_count_query->count();



        $this_week_data_query =  Candidate::leftJoin('businesses', 'businesses.id', '=', 'bookings.business_id')
            ->whereBetween('bookings.created_at', [$startDateOfThisWeek, $endDateOfThisWeek]);
        if ($created_by_filter) {
            $this_week_data_query =  $this_week_data_query->where([
                "businesses.created_by" => auth()->user()->id
            ]);
        }
        $data["this_week_data"] = $this_week_data_query->select("bookings.id", "bookings.created_at", "bookings.updated_at")
            ->get();




        $previous_week_data_query =  Candidate::leftJoin('businesses', 'businesses.id', '=', 'bookings.business_id')
            ->whereBetween('bookings.created_at', [$startDateOfPreviousWeek, $endDateOfPreviousWeek]);
        if ($created_by_filter) {
            $previous_week_data_query =  $previous_week_data_query->where([
                "businesses.created_by" => auth()->user()->id
            ]);
        }
        $data["previous_week_data"] = $previous_week_data_query->select("bookings.id", "bookings.created_at", "bookings.updated_at")
            ->get();






        $this_month_data_query =  Candidate::leftJoin('businesses', 'businesses.id', '=', 'bookings.business_id')
            ->whereBetween('bookings.created_at', [$startDateOfThisMonth, $endDateOfThisMonth]);
        if ($created_by_filter) {
            $this_month_data_query =  $this_month_data_query->where([
                "businesses.created_by" => auth()->user()->id
            ]);
        }
        $data["this_month_data"] = $this_month_data_query->select("bookings.id", "bookings.created_at", "bookings.updated_at")
            ->get();


        $previous_month_data_query =  Candidate::leftJoin('businesses', 'businesses.id', '=', 'bookings.business_id')
            ->whereBetween('bookings.created_at', [$startDateOfPreviousMonth, $endDateOfPreviousMonth]);
        if ($created_by_filter) {
            $previous_month_data_query =  $previous_month_data_query->where([
                "businesses.created_by" => auth()->user()->id
            ]);
        }
        $data["previous_month_data"] = $previous_month_data_query->select("bookings.id", "bookings.created_at", "bookings.updated_at")
            ->get();


        $data["this_week_data_count"] = $data["this_week_data"]->count();
        $data["previous_week_data_count"] = $data["previous_week_data"]->count();
        $data["this_month_data_count"] = $data["this_month_data"]->count();
        $data["previous_month_data_count"] = $data["previous_month_data"]->count();
        return $data;
    }

    public function overall_jobs($created_by_filter = 0)
    {
        $startDateOfThisMonth = Carbon::now()->startOfMonth();
        $endDateOfThisMonth = Carbon::now()->endOfMonth();
        $startDateOfPreviousMonth = Carbon::now()->startOfMonth()->subMonth(1);
        $endDateOfPreviousMonth = Carbon::now()->startOfMonth()->subDay(1);

        $startDateOfThisWeek = Carbon::now()->startOfWeek();
        $endDateOfThisWeek = Carbon::now()->endOfWeek();
        $startDateOfPreviousWeek = Carbon::now()->startOfWeek()->subWeek(1);
        $endDateOfPreviousWeek = Carbon::now()->endOfWeek()->subWeek(1);


        $total_data_count_query =  Candidate::leftJoin('businesses', 'businesses.id', '=', 'jobs.business_id');
        if ($created_by_filter) {
            $total_data_count_query =  $total_data_count_query->where([
                "businesses.created_by" => auth()->user()->id
            ]);
        }
        $data["total_data_count"] = $total_data_count_query->count();





        $this_week_data_query =  Candidate::leftJoin('businesses', 'businesses.id', '=', 'jobs.business_id')
            ->whereBetween('jobs.created_at', [$startDateOfThisWeek, $endDateOfThisWeek]);
        if ($created_by_filter) {
            $this_week_data_query =  $this_week_data_query->where([
                "businesses.created_by" => auth()->user()->id
            ]);
        }
        $data["this_week_data"] = $this_week_data_query
            ->select("jobs.id", "jobs.created_at", "jobs.updated_at")
            ->get();




        $previous_week_data_query =  Candidate::leftJoin('businesses', 'businesses.id', '=', 'jobs.business_id')
            ->whereBetween('jobs.created_at', [$startDateOfPreviousWeek, $endDateOfPreviousWeek]);
        if ($created_by_filter) {
            $previous_week_data_query =  $previous_week_data_query->where([
                "businesses.created_by" => auth()->user()->id
            ]);
        }
        $data["previous_week_data"] = $previous_week_data_query
            ->select("jobs.id", "jobs.created_at", "jobs.updated_at")
            ->get();





        $this_month_data_query =  Candidate::leftJoin('businesses', 'businesses.id', '=', 'jobs.business_id')
            ->whereBetween('jobs.created_at', [$startDateOfThisMonth, $endDateOfThisMonth]);
        if ($created_by_filter) {
            $this_month_data_query =  $this_month_data_query->where([
                "businesses.created_by" => auth()->user()->id
            ]);
        }
        $data["this_month_data"] = $this_month_data_query
            ->select("jobs.id", "jobs.created_at", "jobs.updated_at")
            ->get();



        $previous_month_data_query =  Candidate::leftJoin('businesses', 'businesses.id', '=', 'jobs.business_id')
            ->whereBetween('jobs.created_at', [$startDateOfPreviousMonth, $endDateOfPreviousMonth]);
        if ($created_by_filter) {
            $previous_month_data_query =  $previous_month_data_query->where([
                "businesses.created_by" => auth()->user()->id
            ]);
        }
        $data["previous_month_data"] = $previous_month_data_query
            ->select("jobs.id", "jobs.created_at", "jobs.updated_at")
            ->get();



        $data["this_week_data_count"] = $data["this_week_data"]->count();
        $data["previous_week_data_count"] = $data["previous_week_data"]->count();
        $data["this_month_data_count"] = $data["this_month_data"]->count();
        $data["previous_month_data_count"] = $data["previous_month_data"]->count();
        return $data;
    }

public function getEmploymentStatuses () {
    $created_by  = NULL;
    if(auth()->user()->business) {
        $created_by = auth()->user()->business->created_by;
    }
    $employmentStatuses = EmploymentStatus::
    when(empty(auth()->user()->business_id), function ($query) use ( $created_by) {
        if (auth()->user()->hasRole('superadmin')) {
            return $query->where('employment_statuses.business_id', NULL)
                ->where('employment_statuses.is_default', 1)
                ->where('employment_statuses.is_active', 1);

        } else {
            return $query->where('employment_statuses.business_id', NULL)
                ->where('employment_statuses.is_default', 1)
                ->where('employment_statuses.is_active', 1)
                ->whereDoesntHave("disabled", function($q) {
                    $q->whereIn("disabled_employment_statuses.created_by", [auth()->user()->id]);
                })

                ->orWhere(function ($query)   {
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
                ->whereDoesntHave("disabled", function($q) use($created_by) {
                    $q->whereIn("disabled_employment_statuses.created_by", [$created_by]);
                })
                ->whereDoesntHave("disabled", function($q)  {
                    $q->whereIn("disabled_employment_statuses.business_id",[auth()->user()->business_id]);
                })

                ->orWhere(function ($query) use( $created_by){
                    $query->where('employment_statuses.business_id', NULL)
                        ->where('employment_statuses.is_default', 0)
                        ->where('employment_statuses.created_by', $created_by)
                        ->where('employment_statuses.is_active', 1)
                        ->whereDoesntHave("disabled", function($q) {
                            $q->whereIn("disabled_employment_statuses.business_id",[auth()->user()->business_id]);
                        });
                })
                ->orWhere(function ($query)   {
                    $query->where('employment_statuses.business_id', auth()->user()->business_id)
                        ->where('employment_statuses.is_default', 0)
                        ->where('employment_statuses.is_active', 1);

                });
        })->get();

        return $employmentStatuses;
}

    public function overall_services()
    {
        $startDateOfThisMonth = Carbon::now()->startOfMonth();
        $endDateOfThisMonth = Carbon::now()->endOfMonth();
        $startDateOfPreviousMonth = Carbon::now()->startOfMonth()->subMonth(1);
        $endDateOfPreviousMonth = Carbon::now()->startOfMonth()->subDay(1);

        $startDateOfThisWeek = Carbon::now()->startOfWeek();
        $endDateOfThisWeek = Carbon::now()->endOfWeek();
        $startDateOfPreviousWeek = Carbon::now()->startOfWeek()->subWeek(1);
        $endDateOfPreviousWeek = Carbon::now()->endOfWeek()->subWeek(1);



        $data["total_data_count"] = Candidate::count();


        $data["this_week_data"] = Candidate::whereBetween('created_at', [$startDateOfThisWeek, $endDateOfThisWeek])
            ->select("id", "created_at", "updated_at")
            ->get();

        $data["previous_week_data"] = Candidate::whereBetween('created_at', [$startDateOfPreviousWeek, $endDateOfPreviousWeek])
            ->select("id", "created_at", "updated_at")
            ->get();



        $data["this_month_data"] = Candidate::whereBetween('created_at', [$startDateOfThisMonth, $endDateOfThisMonth])
            ->select("id", "created_at", "updated_at")
            ->get();
        $data["previous_month_data"] = Candidate::whereBetween('created_at', [$startDateOfPreviousMonth, $endDateOfPreviousMonth])
            ->select("id", "created_at", "updated_at")
            ->get();

        $data["this_week_data_count"] = $data["this_week_data"]->count();
        $data["previous_week_data_count"] = $data["previous_week_data"]->count();
        $data["this_month_data_count"] = $data["this_month_data"]->count();
        $data["previous_month_data_count"] = $data["previous_month_data"]->count();
        return $data;
    }


    public function getSuperAdminDashboardData(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasRole('superadmin')) {
                return response()->json([
                    "message" => "You are not a superadmin"
                ], 401);
            }

            $data["businesses"] = $this->businesses();

            $data["fuel_stations"] = $this->fuel_stations();

            $data["customers"] = $this->customers();

            $data["overall_customer_jobs"] = $this->overall_customer_jobs();

            $data["overall_bookings"] = $this->overall_bookings();

            $data["overall_jobs"] = $this->overall_jobs();



            $data["overall_services"] = $this->overall_services();






            return response()->json($data, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }



    public function getDataCollectorDashboardData(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasRole('data_collector')) {
                return response()->json([
                    "message" => "You are not a superadmin"
                ], 401);
            }

            $data["businesses"] = $this->businesses(1);

            $data["fuel_stations"] = $this->fuel_stations(1);

            $data["overall_bookings"] = $this->overall_bookings(1);

            $data["overall_jobs"] = $this->overall_jobs(1);

            //  $data["customers"] = $this->customers();

            //  $data["overall_customer_jobs"] = $this->overall_customer_jobs();



            //  $data["overall_services"] = $this->overall_services();






            return response()->json($data, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }
}
