<?php

namespace App\Http\Utils;

use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\EmployeeAddressHistory;

use App\Models\EmployeePassportDetailHistory;

use App\Models\EmployeePensionHistory;
use App\Models\EmployeeProjectHistory;

use App\Models\EmployeeRightToWorkHistory;

use App\Models\EmployeeSponsorshipHistory;
use App\Models\EmployeeUserWorkShiftHistory;
use App\Models\EmployeeVisaDetailHistory;
use App\Models\Leave;
use App\Models\LeaveRecord;
use App\Models\Project;
use App\Models\UserAssetHistory;
use App\Models\UserRecruitmentProcess;
use App\Models\UserWorkShift;
use App\Models\WorkShift;
use App\Models\WorkShiftHistory;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;

trait UserDetailsUtil
{
    use BasicUtil;





    public function store_work_shift($request_data,$user) {
        if (!empty($request_data["work_shift_id"])) {
            $work_shift =  WorkShift::where([
                "id" => $request_data["work_shift_id"],

            ])
                ->first();
            if (!$work_shift) {
                throw new Exception("Work shift validation failed");
            }
            if (!$work_shift->is_active) {

                throw new Exception(("Please activate the work shift named '" . $work_shift->name . "'"), 400);
                // return response()->json(["message" => ("Please activate the work shift named '" . $work_shift->name . "'")], 400);
            }
            $work_shift->users()->attach($user->id);


            $employee_work_shift_history_data["work_shift_id"] = $work_shift->id;

            $work_shift_history =  WorkShiftHistory::where([
                "to_date" => NULL,
                "work_shift_id" => $work_shift->id
            ])
                ->first();
            if (!$work_shift_history) {
                throw new Exception("No work shift history found");
            }

            $work_shift_history->users()->attach($user->id, ['from_date' => auth()->user()->business->start_date, 'to_date' => NULL]);
        } else {
            $default_work_shift = WorkShift::where([
                "business_id" => auth()->user()->business_id,
                "is_business_default" => 1
            ])
                ->first();
            if (!$default_work_shift) {
                throw new Exception("There is no default workshift for this business");
            }

            if (!$default_work_shift->is_active) {

                $error =  [
                    "message" => ("Please activate the work shift named '" . $default_work_shift->name . "'"),

                ];
                throw new Exception(json_encode($error), 400);
                // return response()->json(["message" => ("Please activate the work shift named '" . $default_work_shift->name . "'")], 400);
            }

            $default_work_shift->users()->attach($user->id);
            $employee_work_shift_history_data["work_shift_id"] = $default_work_shift->id;

            $work_shift_history =  WorkShiftHistory::where([
                "to_date" => NULL,
                "work_shift_id" => $default_work_shift->id
            ])
                ->first();
            if (!$work_shift_history) {
                throw new Exception("No work shift history found");
            }

            $work_shift_history->users()->attach($user->id, ['from_date' => auth()->user()->business->start_date, 'to_date' => NULL]);
        }
    }


    public function delete_old_histories() {
        $ten_years_ago = Carbon::now()->subYears(10);
           EmployeePensionHistory::where('pension_re_enrollment_due_date', '<=', $ten_years_ago)->delete();
           $ten_years_ago = Carbon::now()->subYears(10);
            EmployeeRightToWorkHistory::where('right_to_work_expiry_date', '<=', $ten_years_ago)->delete();
            $ten_years_ago = Carbon::now()->subYears(10);
            EmployeeVisaDetailHistory::where('visa_expiry_date', '<=', $ten_years_ago)->delete();
            $ten_years_ago = Carbon::now()->subYears(10);
            EmployeePassportDetailHistory::where('passport_expiry_date', '<=', $ten_years_ago)->delete();
            $ten_years_ago = Carbon::now()->subYears(10);
            EmployeeSponsorshipHistory::where('expiry_date', '<=', $ten_years_ago)->delete();


            $three_years_ago = Carbon::now()->subYears(3);
            EmployeeAddressHistory::where('to_date', '<=', $three_years_ago)->delete();

    }

    public function store_right_to_works($request_data,$user) {
        if (!empty($request_data["right_to_works"]) && $request_data["is_active_right_to_works"]) {
            $request_data["right_to_works"]["user_id"] = $user->id;
            $request_data["right_to_works"]["business_id"] = $user->business_id;

            $request_data["right_to_works"]["from_date"] = now();

            EmployeeRightToWorkHistory::create($request_data["right_to_works"]);



        }
    }


    public function store_visa_details($request_data,$user) {
        if (!empty($request_data["visa_details"]) && $request_data["is_active_visa_details"]) {
            $request_data["visa_details"]["user_id"] = $user->id;
            $request_data["visa_details"]["business_id"] = $user->business_id;


            $request_data["visa_details"]["from_date"] = now();
            $employee_visa_details_history  =  EmployeeVisaDetailHistory::create($request_data["visa_details"]);


        }
    }




    public function store_passport_details($request_data,$user) {
        if (!empty($request_data["passport_details"])) {
            $request_data["passport_details"]["user_id"] = $user->id;
            $request_data["passport_details"]["business_id"] = $user->business_id;

            $request_data["passport_details"]["from_date"] = now();

            $employee_passport_details_history  =  EmployeePassportDetailHistory::create($request_data["passport_details"]);


        }
    }


    public function store_sponsorship_details($request_data,$user) {
        if (!empty($request_data["sponsorship_details"])) {
            $request_data["sponsorship_details"]["user_id"] = $user->id;
            $request_data["sponsorship_details"]["business_id"] = $user->business_id;


            $request_data["sponsorship_details"]["from_date"] = now();

            $employee_sponsorship_history  =  EmployeeSponsorshipHistory::create($request_data["sponsorship_details"]);


        }
    }


     public function store_recruitment_processes($request_data,$user) {
        if (!empty($request_data["recruitment_processes"])) {
            foreach($request_data["recruitment_processes"] as $recruitment_process){


                if(!empty($recruitment_process["description"])){
    $user->recruitment_processes()->create($recruitment_process);
                }

}

        }
    }

    public function store_pension($request_data,$user) {


        EmployeePensionHistory::create([
            'user_id' => $user->id,
            'pension_eligible' => false,
            'pension_enrollment_issue_date' => NULL,
            'pension_letters' => [],
            'pension_scheme_status' => NULL,
            'pension_scheme_opt_out_date'=> NULL,
            'pension_re_enrollment_due_date' => NULL,
            "is_manual" => 0,
            "from_date" => now(),
            "to_date" => NULL,
            "business_id"=> auth()->user()->business_id,
            'created_by' => auth()->user()->id

        ]);

    }

    public function store_project($request_data,$user) {
        $project = Project::where([
            "business_id" => $user->business_id,
            "is_default" => 1
          ])
          ->first();
          if(!$project) {
            throw new Exception("no project defined for this business");
          }
          $employee_project_history_data = $project->toArray();
          $employee_project_history_data["project_id"] = $employee_project_history_data["id"];
          $employee_project_history_data["user_id"] = $user->id;
          $employee_project_history_data["from_date"] = now();
          $employee_project_history_data["to_date"] = NULL;
          EmployeeProjectHistory::create($employee_project_history_data);
          $user->projects()->attach([$project->id]);
    }





    public function update_address_history($request_data, $user)
    {


        $address_history_data = [
            'user_id' => $user->id,
            'from_date' => now(),
            'created_by' => auth()->user()->id,
            'address_line_1' => $request_data["address_line_1"],
            'address_line_2' => $request_data["address_line_2"],
            'country' => $request_data["country"],
            'city' => $request_data["city"],
            'postcode' => $request_data["postcode"],
            'lat' => $request_data["lat"],
            'long' => $request_data["long"]
        ];

        $employee_address_history  =  EmployeeAddressHistory::where([
            "user_id" =>   $user->id,
            "to_date" => NULL
        ])
        ->orderByDesc("employee_address_histories.id")
            ->first();

        if ($employee_address_history) {

                   $fields_to_check = [
                    "address_line_1", "address_line_2", "country", "city", "postcode"

                    ];
                    $date_fields = [
                    ];


                    $fields_changed = $this->fieldsHaveChanged($fields_to_check, $employee_address_history, $request_data, $date_fields);

                    if (
                        $fields_changed
                    ) {
                        $employee_address_history->to_date = now();
                        $employee_address_history->save();
                        EmployeeAddressHistory::create($address_history_data);

                    }


        } else {
            EmployeeAddressHistory::create($address_history_data);
        }
    }









    public function update_recruitment_processes($request_data,$user) {
        if (!empty($request_data["recruitment_processes"])) {
            $user->recruitment_processes()->delete();
            foreach($request_data["recruitment_processes"] as $recruitment_process){
if(!empty($recruitment_process["description"])){
    $user->recruitment_processes()->create($recruitment_process);
}
            }

        }
    }





    public function update_recruitment_processes_v2($request_data,$user) {
        if (!empty($request_data["recruitment_processes"]) ) {



            foreach($request_data["recruitment_processes"] as $recruitment_process){
                if(!empty($recruitment_process["description"])){
                    $userRecruitmentProcess =   UserRecruitmentProcess::where([
                        "id" => $recruitment_process["id"],

                        "user_id"  => $user->id
                    ])
                    ->first();

                    $userRecruitmentProcess->fill($recruitment_process);

                    $userRecruitmentProcess->save();

                }





            }








        }
    }



    public function update_work_shift($request_data,$user) {
          if (!empty($request_data["work_shift_id"])) {
                    $work_shift =  WorkShift::where([
                        "id" => $request_data["work_shift_id"],
                    ])
                    ->where(function ($query) {
                        $query->where([

                            "business_id" => auth()->user()->business_id
                        ]) ->orWhere(function($query)  {
                            $query->where([
                                "is_active" => 1,
                                "business_id" => NULL,
                                "is_default" => 1
                            ]);
                        });
                    })
                    ->orderByDesc("id")
                        ->first();
                    if (!$work_shift) {


                        throw new Exception("no work shift found", 403);

                    }

                    if (!$work_shift->is_active) {

                        throw new Exception("Please activate the work shift named '" . $work_shift->name . "'", 400);
                    }


                    $current_workshift = $user->work_shifts->last();

                    $current_workshift_id = NULL;
                    if ($current_workshift) {
                        $current_workshift_id = $current_workshift->id;
                    }

                    if ($work_shift->id != $current_workshift_id) {
                        UserWorkShift::where([
                            "user_id" => $user->id
                        ])
                            ->delete();

                        $work_shift->users()->attach($user->id);

                        EmployeeUserWorkShiftHistory::where([
                            "to_date" => NULL,
                            "user_id" => $user->id
                        ])
                            ->whereHas("work_shift_history", function ($query) use ($current_workshift_id) {
                                $query->where("work_shift_histories.work_shift_id", $current_workshift_id);
                            })
                            // ->where("work_shift_id",$current_workshift->id)
                            ->update([
                                "to_date" => now()
                            ]);

                        $work_shift_history =  WorkShiftHistory::where([
                            "to_date" => NULL,
                            "work_shift_id" => $work_shift->id
                        ])
                            ->first();

                        $work_shift_history->users()->attach($user->id, ['from_date' => now(), 'to_date' => NULL]);
                    }
                }
    }




    public function update_sponsorship($request_data,$user) {
        if (!empty($request_data["sponsorship_details"])) {

            $fields_to_check = [
                'date_assigned',
                'expiry_date',
                // 'status',
                'note',
                "certificate_number",
                "current_certificate_status",
                "is_sponsorship_withdrawn",
                ];

                $date_fields = [
                    'date_assigned',
                    'expiry_date',
                ];

                $fields_changed = true;

                if($user->sponsorship_details) {
                    $fields_changed = $this->fieldsHaveChanged($fields_to_check,  $user->sponsorship_details, $request_data["sponsorship_details"], $date_fields);
                }

                if ( $fields_changed ) {
                    $request_data["sponsorship_details"]["business_id"] = auth()->user()->business_id;
                    $request_data["sponsorship_details"]["user_id"] = $user->id;
                    $request_data["sponsorship_details"]["from_date"] = now();
                    EmployeeSponsorshipHistory::create($request_data["sponsorship_details"]);

                }






        }
    }
    public function update_passport_details($request_data,$user) {
        if (!empty($request_data["passport_details"])) {

            $fields_to_check = [
                'passport_number',
                "passport_issue_date",
                "passport_expiry_date",
                "place_of_issue",
                ];

                $date_fields = [
                    "passport_issue_date",
                    "passport_expiry_date",
                ];

                $fields_changed = true;

if($user->passport_details) {
    $fields_changed = $this->fieldsHaveChanged($fields_to_check,  $user->passport_details, $request_data["passport_details"], $date_fields);
}


                if ( $fields_changed  ) {


                    $request_data["passport_details"]["business_id"] = auth()->user()->business_id;
                    $request_data["passport_details"]["user_id"] = $user->id;
                    $request_data["passport_details"]["from_date"] = now();
                    EmployeePassportDetailHistory::create($request_data["passport_details"]);


                }





        }

    }

    public function update_visa_details($request_data,$user) {
        if (!empty($request_data["visa_details"]) && $request_data["is_active_visa_details"]) {


            $fields_to_check = [
                'BRP_number',
                "visa_issue_date",
                "visa_expiry_date",
                "place_of_issue",
                "visa_docs",

                ];
                $date_fields = [
                    "visa_issue_date",
                    "visa_expiry_date",
                ];

                $fields_changed = true;

                if($user->visa_details) {
                    $fields_changed = $this->fieldsHaveChanged($fields_to_check,  $user->visa_details, $request_data["visa_details"], $date_fields);
                }


                if (
                    $fields_changed
                ) {
                    $request_data["visa_details"]["business_id"] = auth()->user()->business_id;
                    $request_data["visa_details"]["user_id"] = $user->id;
                    $request_data["visa_details"]["from_date"] = now();
                    EmployeeVisaDetailHistory::create($request_data["visa_details"]);

                }

        }




    }

    public function update_right_to_works($request_data,$user) {


        if (!empty($request_data["right_to_works"]) && $request_data["is_active_right_to_works"]) {

            $fields_to_check = [
                'right_to_work_code',
                'right_to_work_check_date',
                'right_to_work_expiry_date',
                'right_to_work_docs',

                ];
                $date_fields = [
                    'right_to_work_check_date',
                    'right_to_work_expiry_date',
                ];


                $fields_changed = true;

                if($user->right_to_works) {
                    $fields_changed = $this->fieldsHaveChanged($fields_to_check,  $user->right_to_works, $request_data["right_to_works"], $date_fields);
                }






                if (
                    $fields_changed
                ) {
                    $request_data["right_to_works"]["business_id"] = auth()->user()->business_id;
                    $request_data["right_to_works"]["user_id"] = $user->id;
                    $request_data["right_to_works"]["from_date"] = now();
                    EmployeeRightToWorkHistory::create($request_data["right_to_works"]);

                }



        }



    }

public function validateJoiningDate($joining_date,$user_id) {

if(!empty($joining_date)) {
    $attendance_exists = Attendance::where(
        "in_date" , "<" ,$joining_date,
      )
      ->where([
          "user_id" => $user_id
      ])->exists();

                 if($attendance_exists) {
                    throw new Exception(("Attendance exists before " . $joining_date),401);

              }

              $leave_exists = LeaveRecord::where(
                  "date" , "<" ,$joining_date,
                )
                ->whereHas("leave",function($query) use($user_id) {
                  $query->where("leaves.user_id",$user_id);
                })
               ->exists();

               if($leave_exists) {
                throw new Exception(($leave_exists ."Leave exists before " . $joining_date),401);


                        $asset_assigned = UserAssetHistory::where(
                          "from_date" , "<" ,$joining_date,
                        )
                        ->where([
                            "user_id" => $user_id
                        ])->exists();

                       if($asset_assigned) {
                        throw new Exception(("Asset assigned before " . $joining_date),401);

                      }
}
}
}




public function store_work_shift_history($work_shift_id, $user)
{

        $work_shift =  WorkShift::where([
            "id" => $work_shift_id,
        ])
            ->first();
        if (!$work_shift) {
            throw new Exception("Work shift validation failed");
        }
        if (!$work_shift->is_active) {

            throw new Exception(("Please activate the work shift named '" . $work_shift->name . "'"), 400);

            // return response()->json(["message" => ("Please activate the work shift named '" . $work_shift->name . "'")], 400);
        }
        $work_shift->users()->attach($user->id);



        $work_shift_history_data = $work_shift->toArray();
        $work_shift_history_data["work_shift_id"] = $work_shift_history_data["id"];
        // $employee_work_shift_history_data["from_date"] = $request_data["start_date"]?$request_data["start_date"]:now();
        $work_shift_history_data["from_date"] = auth()->user()->business->start_date;
        $work_shift_history_data["to_date"] = NULL;
        $work_shift_history_data["user_id"] =  $user->id;




        $work_shift_history =  WorkShiftHistory::create($work_shift_history_data);


        foreach ($work_shift->details as $details) {
            $details_data = $details->toArray();
            $details_data["work_shift_id"] = $work_shift_history_data["work_shift_id"];
            $work_shift_history->details()->create($details_data);
        }

}




public function update_work_shift_history($work_shift_id, $user)
{

        $work_shift =  WorkShift::where([
            "id" => $work_shift_id,
        ])
            ->first();
        if (!$work_shift) {
            throw new Exception("Work shift validation failed");
        }
        if (!$work_shift->is_active) {

            throw new Exception(("Please activate the work shift named '" . $work_shift->name . "'"), 400);

            // return response()->json(["message" => ("Please activate the work shift named '" . $work_shift->name . "'")], 400);
        }

        UserWorkShift::where([
            "user_id" => $user->id
        ])
        ->update([
            "work_shift_id" => $work_shift->id
        ]);




        $work_shift_history_data = $work_shift->toArray();
        $work_shift_history_data["work_shift_id"] = $work_shift_history_data["id"];
        // $employee_work_shift_history_data["from_date"] = $request_data["start_date"]?$request_data["start_date"]:now();


        $work_shift_history_data["user_id"] =  $user->id;


       $last_work_shift = WorkShiftHistory::where([
            "user_id" => $user->id
        ])
        ->whereDate("from_date","<",today())
        ->whereNull("to_date")
        ->first();

        if(!empty($last_work_shift)) {
            $last_work_shift->update([
                "to_date" => today()
            ]);
            $work_shift_history_data["from_date"] = today()->addDay(1);
        } else {
            $work_shift_history_data["from_date"] = auth()->user()->business->start_date;
        }

        $work_shift_history_data["to_date"] = NULL;

        $work_shift_history =  WorkShiftHistory::create($work_shift_history_data);


        foreach ($work_shift->details as $details) {
            $details_data = $details->toArray();
            $details_data["work_shift_id"] = $work_shift_history_data["work_shift_id"];
            $work_shift_history->details()->create($details_data);
        }

}


}
