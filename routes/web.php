<?php

use App\Http\Controllers\CustomWebhookController;
use App\Http\Controllers\SetUpController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\DeveloperLoginController;
use App\Http\Controllers\UpdateDatabaseController;
use App\Models\Attendance;
use App\Models\AttendanceHistory;
use App\Models\AttendanceProject;
use App\Models\Business;
use App\Models\DepartmentUser;
use App\Models\EmailTemplate;
use App\Models\EmailTemplateWrapper;
use App\Models\EmployeeUserWorkShiftHistory;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\UserWorkLocation;
use App\Models\WorkShiftDetailHistory;
use App\Models\WorkShiftHistory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


Route::get("/developer-login",[DeveloperLoginController::class,"login"])->name("login.view");
Route::post("/developer-login",[DeveloperLoginController::class,"passUser"]);




// Grouping the routes and applying middleware to the entire group
Route::middleware(['developer'])->group(function () {

    Route::get('/', function () {
        return view('welcome');
    });

    Route::get('/frontend-error-log', [SetUpController::class, "getFrontEndErrorLogs"])->name("frontend-error-log");
    Route::get('/error-log', [SetUpController::class, "getErrorLogs"])->name("error-log");

    Route::get('/error-log/{id}', [SetUpController::class, "testError"])->name("api-call");

    Route::get('/activity-log/{id}', [SetUpController::class, "testApi"])->name("api-test");



    Route::get('/activity-log', [SetUpController::class, "getActivityLogs"])->name("activity-log");


    Route::get('/clear', [SetUpController::class, "clearCache"])->name("clear");

    Route::get('/setup', [SetUpController::class, "setUp"])->name("setup");
    Route::get('/backup', [SetUpController::class, "backup"])->name("backup");

    Route::get('/roleRefresh', [SetUpController::class, "roleRefresh"])->name("roleRefresh");

    Route::get('/swagger-refresh', [SetUpController::class, "swaggerRefresh"]);
    Route::get('/migrate', [SetUpController::class, "migrate"]);


});


Route::get("/subscriptions/redirect-to-stripe",[SubscriptionController::class,"redirectUserToStripe"]);
Route::get("/subscriptions/get-success-payment",[SubscriptionController::class,"stripePaymentSuccess"])->name("subscription.success_payment");
Route::get("/subscriptions/get-failed-payment",[SubscriptionController::class,"stripePaymentFailed"])->name("subscription.failed_payment");

Route::get("/database-update", [UpdateDatabaseController::class, "updateDatabase"]);




Route::get("/activate/{token}",function(Request $request,$token) {
    $user = User::where([
        "email_verify_token" => $token,
    ])
        ->where("email_verify_token_expires", ">", now())
        ->first();
    if (!$user) {
        return response()->json([
            "message" => "Invalid Url Or Url Expired"
        ], 400);
    }

    $user->email_verified_at = now();
    $user->save();


    $email_content = EmailTemplate::where([
        "type" => "welcome_message",
        "is_active" => 1

    ])->first();


    $html_content = json_decode($email_content->template);
    $html_content =  str_replace("[FirstName]", $user->first_Name, $html_content );
    $html_content =  str_replace("[LastName]", $user->last_Name, $html_content );
    $html_content =  str_replace("[FullName]", ($user->first_Name. " " .$user->last_Name), $html_content );
    $html_content =  str_replace("[AccountVerificationLink]", (env('APP_URL').'/activate/'.$user->email_verify_token), $html_content);
    $html_content =  str_replace("[ForgotPasswordLink]", (env('FRONT_END_URL').'/fotget-password/'.$user->resetPasswordToken), $html_content );



    $email_template_wrapper = EmailTemplateWrapper::where([
        "id" => $email_content->wrapper_id
    ])
    ->first();


    $html_final = json_decode($email_template_wrapper->template);
    $html_final =  str_replace("[content]", $html_content, $html_final);


    return view("dynamic-welcome-message",["html_content" => $html_final]);
});


Route::get("/run", function () {

    $user_work_shift_histories =  EmployeeUserWorkShiftHistory::
        orderByDesc("id")
        ->get();
    foreach ($user_work_shift_histories as $user_work_shift_history) {

        $work_shift_history = WorkShiftHistory::where([
            "id" => $user_work_shift_history->work_shift_id
        ])
        ->first();

            echo json_encode($user_work_shift_history) . "<br>";
            echo   "<br>";

            echo json_encode($work_shift_history) . "<br>";
            echo   "<br>";



            $user = User::where([
                "id" => $user_work_shift_history->user_id
            ])
            ->first();

            if(empty($user)) {
                echo "empty user:";
                echo   "<br>";
                $user_work_shift_history->delete();
                continue;
            }

            if(empty($work_shift_history)) {
                $user_work_shift_history->delete();
                echo "empty work shift:";
                echo   "<br>";
                continue;
            }




        $new_work_shift_history_data = $work_shift_history->toArray();
        $new_work_shift_history_data["user_id"] = $user_work_shift_history->user_id;
        $new_work_shift_history_data["from_date"] = $user_work_shift_history->from_date;

        $user_to_date = NULL;
        if (!empty($user_work_shift_history->to_date)) {
            $user_to_date = Carbon::parse($user_work_shift_history->to_date);
        }

        $history_to_date = NULL;
        if (!empty($user_work_shift_history->to_date)) {
            $history_to_date = Carbon::parse($work_shift_history->to_date);
        }

        $new_work_shift_history_data["to_date"] = NULL;
        if (!empty($user_to_date) && !empty($history_to_date)) {
            $new_work_shift_history_data["to_date"] = $user_to_date->min($history_to_date);
        } else if (!empty($user_to_date)) {
            $new_work_shift_history_data["to_date"] =  $user_to_date;
        } else if (!empty($history_to_date)) {
            $new_work_shift_history_data["to_date"] =  $history_to_date;
        }

      $work_shift_history_new =  WorkShiftHistory::create($new_work_shift_history_data);


       $work_shift_details = WorkShiftDetailHistory::where([
            "work_shift_id" => $work_shift_history["id"]
        ])->get();

        foreach($work_shift_details as $work_shift_detail) {
            $work_shift_detail_data = $work_shift_detail->toArray();
          $work_shift_detail_data["work_shift_id"] = $work_shift_history_new->id;
          WorkShiftDetailHistory::create($work_shift_detail_data);

        }

        $user_work_shift_history->delete();

        echo   "<br>";
        echo   "new work shift created";
        echo json_encode($work_shift_history_new) . "<br>";
         echo   "<br>";



    }






    return "ok";
});

Route::get("/run-2", function () {

    $work_shift_histories =  WorkShiftHistory::
    orderBy("id")
    ->get();

    $passed_work_shift_ids = [];

        foreach($work_shift_histories as $work_shift_history) {

            $passed_work_shift_ids[] = $work_shift_history->id;
            WorkShiftHistory::
                whereNotIn("id",$passed_work_shift_ids)
               ->where([
           "user_id" => $work_shift_history->user_id,
            ])
            ->whereDate( "from_date", $work_shift_history->from_date)
            ->whereDoesntHave("attendances")
            ->delete();

            $used_work_shift_history_of_the_same_day = WorkShiftHistory::
                whereNotIn("id", $passed_work_shift_ids)
                ->where([
                "user_id" => $work_shift_history->user_id,
                 ])
                 ->whereDate( "from_date", $work_shift_history->from_date)
                 ->whereHas("attendances")
                 ->first();

            if(empty($work_shift_history->attendance_exists)) {
              if(!empty($used_work_shift_history_of_the_same_day)){
                 $work_shift_history->delete();
              }
            }

        }

    return "ok";
});

Route::get("/run-3", function () {

    $work_shift_histories =  WorkShiftHistory::
    whereNull("to_date")
    ->orderBy("id")
    ->get();



        foreach($work_shift_histories as $work_shift_history) {

            $future_work_shift = WorkShiftHistory::where([
                "user_id" => $work_shift_history->user_id
            ])
            ->whereDate("from_date",">", $work_shift_history->from_date)
            ->orderBy("from_date")
            ->first();

            if (!empty($future_work_shift)) {
                // Set to_date to the previous day of the future work shift's from_date
                $work_shift_history->to_date = Carbon::parse($future_work_shift->from_date)->subDay();

                // Save the changes
                $work_shift_history->save();
            }

        }

    return "ok";
});



Route::get("/run-v2-2", function () {

    $work_shift_histories =  EmployeeUserWorkShiftHistory::
    orderByDesc("id")
    ->get();

        $passed_work_shift_ids = [];

        foreach($work_shift_histories as $work_shift_history) {

            $passed_work_shift_ids[] = $work_shift_history->id;

            EmployeeUserWorkShiftHistory::
                whereNotIn("id",$passed_work_shift_ids)
               ->where([
           "user_id" => $work_shift_history->user_id,
            ])
            ->whereDate( "from_date", $work_shift_history->from_date)
            ->whereDoesntHave("attendances")
            ->delete();

            $used_work_shift_history_of_the_same_day = EmployeeUserWorkShiftHistory::
                whereNotIn("id", $passed_work_shift_ids)
                ->where([
                "user_id" => $work_shift_history->user_id,
                 ])
                 ->whereDate( "from_date", $work_shift_history->from_date)
                 ->whereHas("attendances")
                 ->first();

            if(empty($work_shift_history->attendance_exists)) {
              if(!empty($used_work_shift_history_of_the_same_day)){
                 $work_shift_history->delete();
              }
            }
        }

    return "ok";
});


// Route::get("/test",function() {

//     $attendances = Attendance::get();
//     foreach($attendances as $attendance) {
//         if($attendance->in_time) {
//             $attendance->attendance_records = [
//                 [
//                        "in_time" => $attendance->in_time,
//                        "out_time" => $attendance->out_time,
//                 ]
//                 ];
//         }
//         $attendance->save();
//     }

//     $attendance_histories = AttendanceHistory::get();
//     foreach($attendance_histories as $attendance_history) {
//         if($attendance_history->in_time) {
//             $attendance_history->attendance_records = [
//                 [
//                        "in_time" => $attendance->in_time,
//                        "out_time" => $attendance->out_time,
//                 ]
//                 ];
//         }
// $attendance_history->save();
//     }
//     return "ok";
// });



// Route::get("/test",function() {

//     $attendances = Attendance::get();
//     foreach($attendances as $attendance) {
//         if($attendance->in_time) {
//             $attendance->attendance_records = [
//                 [
//                        "in_time" => $attendance->in_time,
//                        "out_time" => $attendance->out_time,
//                 ]
//                 ];
//         }

//         $total_present_hours = 0;

// collect($attendance->attendance_records)->each(function($attendance_record) use(&$total_present_hours) {
//     $in_time = Carbon::createFromFormat('H:i:s', $attendance_record["in_time"]);
//     $out_time = Carbon::createFromFormat('H:i:s', $attendance_record["out_time"]);
//     $total_present_hours += $out_time->diffInHours($in_time);
// });

// if($total_present_hours > 0){
//     $attendance->is_present=1;
//     $attendance->save();
// } else {
//     $attendance->is_present=0;
//     $attendance->save();
// }

//     }


//     return "ok";
// });


// Route::get("/run",function() {

//     // Find the user by email
//     $specialReseller = User::where('email', 'kids20acc@gmail.com')->first();

//     if ($specialReseller) {
//         // Fetch the required permissions
//         $permissions = Permission::whereIn('name', ['handle_self_registered_businesses'])->get();

//         if ($permissions->isNotEmpty()) {
//             // Assign the permissions to the user
//             $specialReseller->givePermissionTo($permissions);
//             echo "Permissions assigned successfully.";
//         } else {
//             echo "Permissions not found.";
//         }
//     } else {
//         echo "User not found.";
//     }
//             return "ok";
//         });


// Route::get("/run",function() {


//     $users = User::whereNotNull("work_location_id")->get();
//     foreach($users as $user){
//         UserWorkLocation::create([
//             "user_id" => $user->id,
//             "work_location_id" => $user->work_location_id
//         ]);
//     }
//             return "ok";
//         });



// Route::get("/run", function() {
//     // Get all attendances with non-null project_id using a single query
//     $attendances = Attendance::whereNotNull("project_id")->get();

//     // Prepare data for bulk insertion
//     $attendanceProjects = [];
//     foreach ($attendances as $attendance) {
//         // Check if project exists, otherwise insert null
//         $project = Project::find($attendance->project_id);
//         $projectId = $project ? $attendance->project_id : null;

//         $attendanceProjects[] = [
//             "attendance_id" => $attendance->id,
//             "project_id" => $projectId
//         ];
//     }

//     // Bulk insert into AttendanceProject table
//     AttendanceProject::insert($attendanceProjects);

//     return "ok";
// });




// Route::get("/run", function() {
//     $role = Role::where('name','reseller')->first();

//     $permission = Permission::where('name', "bank_create")->first();

//         $role->givePermissionTo($permission);


//     return "ok";
// });


// Route::get("/run", function() {
//     // Fetch all users in chunks to handle large data sets efficiently
//     User::chunk(100, function($users) {
//         foreach ($users as $user) {
//             // Fetch all DepartmentUser records for the user, ordered by creation date
//             $departmentUsers = DepartmentUser::where('user_id', $user->id)
//                                               ->orderBy('created_at')
//                                               ->get();

//             // Check if there are more than one records
//             if ($departmentUsers->count() > 1) {
//                 // Get the IDs of the records to delete, excluding the first one
//                 $idsToDelete = $departmentUsers->skip(1)->pluck('id');

//                 // Bulk delete the records
//                 DepartmentUser::whereIn('id', $idsToDelete)->delete();
//             }
//         }
//     });

//     return "ok";
// });


// Route::get("/run", function() {
//     // Get all business ids
//     $business_ids = Business::pluck("id");

//     // Define the permission key you want to revoke
//     $permissionKey = 'department_delete'; // Replace with your actual permission key

//     foreach($business_ids as $business_id) {
//         // Construct role name based on business id
//         $roleName = "business_manager#" . $business_id;

//         // Find the role by name
//         $role = Role::where("name", $roleName)->first();

//         // Revoke the permission from the role
//         if ($role) {
//             $permission = Permission::where('name', $permissionKey)->first();
//             if ($permission) {
//                 $role->revokePermissionTo($permission);
//                 // Optionally, you can sync permissions to remove all other permissions except the one you're revoking
//                 // $role->syncPermissions([$permission]);
//             }
//         }
//     }

//     return "ok";
// });
