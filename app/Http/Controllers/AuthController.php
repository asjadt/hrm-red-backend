<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuthRegenerateTokenRequest;


use App\Http\Requests\AuthRegisterRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\EmailVerifyTokenRequest;
use App\Http\Requests\ForgetPasswordRequest;
use App\Http\Requests\ForgetPasswordV2Request;
use App\Http\Requests\PasswordChangeRequest;
use App\Http\Requests\UserInfoUpdateRequest;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\EmailLogUtil;
use App\Http\Utils\UserActivityUtil;
use App\Mail\ForgetPasswordMail;
use App\Mail\VerifyMail;
use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    use ErrorUtil, BusinessUtil, UserActivityUtil, EmailLogUtil;

    public function register(AuthRegisterRequest $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            $request_data = $request->validated();

            $request_data['password'] = Hash::make($request['password']);
            $request_data['remember_token'] = Str::random(10);
            $request_data['is_active'] = true;





            $user =  User::create($request_data);

              // verify email starts
              $email_token = Str::random(30);
              $user->email_verify_token = $email_token;
              $user->email_verify_token_expires = Carbon::now()->subDays(-1);
              $user->save();


             $user->assignRole("customer");

            $user->token = $user->createToken('Laravel Password Grant Client')->accessToken;
            $user->permissions = $user->getAllPermissions()->pluck('name');
            $user->roles = $user->roles->pluck('name');


            if(env("SEND_EMAIL") == true) {

                $this->checkEmailSender($user->id,0);

                Mail::to($user->email)->send(new VerifyMail($user));

                $this->storeEmailSender($user->id,0);
            }

// verify email ends

            return response($user, 201);
        } catch (Exception $e) {

            return $this->sendError($e, 500,$request);
        }
    }





    public function login(Request $request)
    {


        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            $loginData = $request->validate([
                'email' => 'email|required',
                'password' => 'required'
            ]);
            $user = User::where('email', $loginData['email'])->first();

            if ($user && $user->login_attempts >= 5) {
                $now = Carbon::now();
                $lastFailedAttempt = Carbon::parse($user->last_failed_login_attempt_at);
                $diffInMinutes = $now->diffInMinutes($lastFailedAttempt);

                if ($diffInMinutes < 15) {
                    return response(['message' => 'You have 5 failed attempts. Reset your password or wait for 15 minutes to access your account.'], 403);
                } else {
                    $user->login_attempts = 0;
                    $user->last_failed_login_attempt_at = null;
                    $user->save();
                }
            }


            if (!auth()->attempt($loginData)) {
                if ($user) {
                    $user->login_attempts++;
                    $user->last_failed_login_attempt_at = Carbon::now();
                    $user->save();

                    if ($user->login_attempts >= 5) {
                        $now = Carbon::now();
                        $lastFailedAttempt = Carbon::parse($user->last_failed_login_attempt_at);
                        $diffInMinutes = $now->diffInMinutes($lastFailedAttempt);

                        if ($diffInMinutes < 15) {

                            return response(['message' => 'You have 5 failed attempts. Reset your password or wait for 15 minutes to access your account.'], 403);
                        } else {
                            $user->login_attempts = 0;
                            $user->last_failed_login_attempt_at = null;
                            $user->save();
                        }
                    }
                }

                return response(['message' => 'Invalid Credentials'], 401);
            }



            if(!$user->is_active) {

                return response(['message' => 'User not active'], 403);
            }

            if($user->business_id) {
                 $business = Business::where([
                    "id" =>$user->business_id
                 ])
                 ->first();
                 if(empty($business)) {


                    return response(['message' => 'Your business not found'], 403);
                 }
                 if(!$business->is_active) {

                    return response(['message' => 'Business not active'], 403);
                }


                // if(!$user->manages_department) {
                //     return response(['message' => 'You are not a manager or admin of any department. Currently login is not available for normal users'], 403);
                // }

            }






            $now = time(); // or your date as well
$user_created_date = strtotime($user->created_at);
$datediff = $now - $user_created_date;

            if(!$user->email_verified_at && (($datediff / (60 * 60 * 24))>1)){
                $email_token = Str::random(30);
                $user->email_verify_token = $email_token;
                $user->email_verify_token_expires = Carbon::now()->subDays(-1);
                if(env("SEND_EMAIL") == true) {


                    $this->checkEmailSender($user->id,0);

                    Mail::to($user->email)->send(new VerifyMail($user));

                    $this->storeEmailSender($user->id,0);
                }
                $user->save();

                return response(['message' => 'please activate your email first'], 409);
            }


            $user->login_attempts = 0;
            $user->last_failed_login_attempt_at = null;


            $site_redirect_token = Str::random(30);
            $site_redirect_token_data["created_at"] = $now;
            $site_redirect_token_data["token"] = $site_redirect_token;
            $user->site_redirect_token = json_encode($site_redirect_token_data);
            $user->save();

            $user->redirect_token = $site_redirect_token;

            $user->token = auth()->user()->createToken('authToken')->accessToken;
            $user->permissions = $user->getAllPermissions()->pluck('name');
            $user->roles = $user->roles->pluck('name');
            $user->business = $user->business;

            Auth::login($user);
            $this->storeActivity($request, "logged in", "User successfully logged into the system.");




            return response()->json(['data' => $user,   "ok" => true], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500,$request);
        }
    }


    public function loginV2(Request $request)
    {


        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");





            $loginData = $request->validate([
                'email' => 'email|required',
                'password' => 'required'
            ]);
            $user = User::where('email', $loginData['email'])->first();

            if ($user && $user->login_attempts >= 5) {
                $now = Carbon::now();
                $lastFailedAttempt = Carbon::parse($user->last_failed_login_attempt_at);
                $diffInMinutes = $now->diffInMinutes($lastFailedAttempt);

                if ($diffInMinutes < 15) {
                    return response(['message' => 'You have 5 failed attempts. Reset your password or wait for 15 minutes to access your account.'], 403);
                } else {
                    $user->login_attempts = 0;
                    $user->last_failed_login_attempt_at = null;
                    $user->save();
                }
            }


            if (!auth()->attempt($loginData)) {
                if ($user) {
                    $user->login_attempts++;
                    $user->last_failed_login_attempt_at = Carbon::now();
                    $user->save();

                    if ($user->login_attempts >= 5) {
                        $now = Carbon::now();
                        $lastFailedAttempt = Carbon::parse($user->last_failed_login_attempt_at);
                        $diffInMinutes = $now->diffInMinutes($lastFailedAttempt);

                        if ($diffInMinutes < 15) {

                            return response(['message' => 'You have 5 failed attempts. Reset your password or wait for 15 minutes to access your account.'], 403);
                        } else {
                            $user->login_attempts = 0;
                            $user->last_failed_login_attempt_at = null;
                            $user->save();
                        }
                    }
                }

                return response(['message' => 'Invalid Credentials'], 401);
            }



            if(!$user->is_active) {

                return response(['message' => 'User not active'], 403);
            }

            if($user->business_id) {
                 $business = Business::where([
                    "id" =>$user->business_id
                 ])
                 ->first();
                 if(empty($business)) {


                    return response(['message' => 'Your business not found'], 403);
                 }
                 if(!$business->is_active) {

                    return response(['message' => 'Business not active'], 403);
                }


                // if(!$user->manages_department) {
                //     return response(['message' => 'You are not a manager or admin of any department. Currently login is not available for normal users'], 403);
                // }






            }






            $now = time(); // or your date as well
$user_created_date = strtotime($user->created_at);
$datediff = $now - $user_created_date;

            if(!$user->email_verified_at && (($datediff / (60 * 60 * 24))>1)){
                $email_token = Str::random(30);
                $user->email_verify_token = $email_token;
                $user->email_verify_token_expires = Carbon::now()->subDays(-1);
                if(env("SEND_EMAIL") == true) {

                    $this->checkEmailSender($user->id,0);

                    Mail::to($user->email)->send(new VerifyMail($user));

                    $this->storeEmailSender($user->id,0);
                }
                $user->save();

                return response(['message' => 'please activate your email first'], 409);
            }


            $user->login_attempts = 0;
            $user->last_failed_login_attempt_at = null;


            $site_redirect_token = Str::random(30);
            $site_redirect_token_data["created_at"] = $now;
            $site_redirect_token_data["token"] = $site_redirect_token;
            $user->site_redirect_token = json_encode($site_redirect_token_data);
            $user->save();


            $user->roles = $user->roles->map(function ($role) {
                return [
                    'name' => $role->name,
                    'permissions' => $role->permissions->pluck('name'),
                ];
            });
            $user->permissions = $user->permissions->pluck("name");

            $business = $user->business;


            // Extracting only the required data
$responseData = [
    'id' => $user->id,
    "token" =>  $user->createToken('Laravel Password Grant Client')->accessToken,
    'business_id' => $user->business_id,
    'first_Name' => $user->first_Name,
    'middle_Name' => $user->middle_Name,
    'last_Name' => $user->last_Name,
    'image' => $user->image,
    'roles' => $user->roles,
    'permissions' => $user->permissions,
    'manages_department' => $user->manages_department,
    'color_theme_name' => $user->color_theme_name,
    'business' => [
        'is_subscribed' => $business ? $business->is_subscribed : null,
        'is_active' => $business ? $business->is_active : null,
        'name' => $business ? $business->name : null,
        'logo' => $business ? $business->logo : null,
        'start_date' => $business ? $business->start_date : null,
        'currency' => $business ? $business->currency : null,
        'is_self_registered_businesses' => $business ? $business->is_self_registered_businesses : 0,
        'trail_end_date' => $business ? $business->trail_end_date : "",
        'current_subscription' =>  $business ? $business->current_subscription:"",
        "stripe_subscription_enabled" =>   $business ? $business->stripe_subscription_enabled:0,
        'reseller_id' => $business ? $business->reseller_id : null,
    ]
];

            Auth::login($user);
            $this->storeActivity($request, "logged in", "User successfully logged into the system.");




            return response()->json(['data' => $responseData,   "ok" => true], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500,$request);
        }
    }






    public function logout(Request $request)
    {


        try {
            $this->storeActivity($request, "logged out", "User logged out of the system.");


            $request->user()->token()->revoke();
            return response()->json(["ok" => true], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500,$request);
        }
    }

    public function regenerateToken(AuthRegenerateTokenRequest $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            $request_data = $request->validated();
            $user = User::where([
                "id" => $request_data["user_id"],
            ])
            ->first();



            $site_redirect_token_db = (json_decode($user->site_redirect_token,true));

            if($site_redirect_token_db["token"] !== $request_data["site_redirect_token"]) {

               return response()
               ->json([
                  "message" => "invalid token"
               ],409);
            }

            $now = time(); // or your date as well

            $timediff = $now - $site_redirect_token_db["created_at"];

            if ($timediff > 20){

                return response(['message' => 'token expired'], 409);
            }



            $user->tokens()->delete();
            $user->token = $user->createToken('authToken')->accessToken;
            $user->permissions = $user->getAllPermissions()->pluck('name');
            $user->roles = $user->roles->pluck('name');
            $user->a = ($timediff);




            return response()->json(['data' => $user,   "ok" => true], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500,$request);
        }
    }

    public function storeToken(ForgetPasswordRequest $request) {

        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");

                $request_data = $request->validated();

            $user = User::where(["email" => $request_data["email"]])->first();
            if (!$user) {

                return response()->json(["message" => "no user found"], 404);
            }

            $token = Str::random(30);

            $user->resetPasswordToken = $token;
            $user->resetPasswordExpires = Carbon::now()->subDays(-1);
            $user->save();



            if(env("SEND_EMAIL") == true) {
            $this->checkEmailSender($user->id,1);

            $result = Mail::to($request_data["email"])->send(new ForgetPasswordMail($user, $request_data["client_site"]));

            $this->storeEmailSender($user->id,1);
            }

            if (count(Mail::failures()) > 0) {
                // Handle failed recipients and log the error messages
                foreach (Mail::failures() as $emailFailure) {

                }
                throw new Exception("Failed to send email to:" . $emailFailure);
            }

            DB::commit();
            return response()->json([
                "message" => "Please check your email."
            ],200);









        } catch (Exception $e) {
            DB::rollBack();

            return $this->sendError($e, 500,$request);
        }

    }

     public function storeTokenV2(ForgetPasswordV2Request $request) {

        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");

                $request_data = $request->validated();

            $user = User::where(["id" => $request_data["id"]])->first();
            if (!$user) {

                return response()->json(["message" => "no user found"], 404);
            }

            $token = Str::random(30);

            $user->resetPasswordToken = $token;
            $user->resetPasswordExpires = Carbon::now()->subDays(-1);
            $user->save();



            if(env("SEND_EMAIL") == true) {

                $this->checkEmailSender($user->id,1);

                $result = Mail::to($user->email)->send(new ForgetPasswordMail($user, $request_data["client_site"]));

                $this->storeEmailSender($user->id,1);



            }

            if (count(Mail::failures()) > 0) {
                // Handle failed recipients and log the error messages
                foreach (Mail::failures() as $emailFailure) {
                }
                throw new Exception("Failed to send email to:" . $emailFailure);
            }

            DB::commit();

            return response()->json([
                "message" => "Please check your email."
            ],200);







        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError($e, 500,$request);
        }

    }




    public function resendEmailVerifyToken(EmailVerifyTokenRequest $request) {

        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");

                $request_data = $request->validated();

            $user = User::where(["email" => $request_data["email"]])->first();
            if (!$user) {

                return response()->json(["message" => "no user found"], 404);
            }



            $email_token = Str::random(30);
            $user->email_verify_token = $email_token;
            $user->email_verify_token_expires = Carbon::now()->subDays(-1);
            if(env("SEND_EMAIL") == true) {



                $this->checkEmailSender($user->id,0);

                Mail::to($user->email)->send(new VerifyMail($user));

                $this->storeEmailSender($user->id,0);



            }

            $user->save();

            DB::commit();
            return response()->json([
                "message" => "please check email"
            ]);





        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError($e, 500,$request);
        }

    }





    public function changePasswordByToken($token, ChangePasswordRequest $request)
    {
        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");

                $request_data = $request->validated();
                $user = User::where([
                    "resetPasswordToken" => $token,
                ])
                    ->where("resetPasswordExpires", ">", now())
                    ->first();
                if (!$user) {

                    return response()->json([
                        "message" => "Invalid Token Or Token Expired"
                    ], 400);
                }

                $password = Hash::make($request_data["password"]);
                $user->password = $password;

                $user->login_attempts = 0;
                $user->last_failed_login_attempt_at = null;


                $user->save();

                DB::commit();
                return response()->json([
                    "message" => "password changed"
                ], 200);





        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError($e, 500,$request);
        }

    }








public function getUser (Request $request) {
    try{
        $this->storeActivity($request, "DUMMY activity","DUMMY description");
        $user = $request->user();
        $user->token = auth()->user()->createToken('authToken')->accessToken;
        $user->permissions = $user->getAllPermissions()->pluck('name');
        $user->roles = $user->roles->pluck('name');
        $user->business = $user->business;



        return response()->json(
            $user,
            200
        );
    }catch(Exception $e) {
        return $this->sendError($e, 500,$request);
    }

}

 

     public function getUserV2 (Request $request) {
        try{
            $this->storeActivity($request, "DUMMY activity","DUMMY description");


            $user = $request->user();
            $user->token = auth()->user()->createToken('authToken')->accessToken;


            $user->roles = $user->roles->map(function ($role) {
                return [
                    'name' => $role->name,
                    'permissions' => $role->permissions->pluck('name'),
                ];
            });

            $user->permissions = $user->permissions->pluck("name");
            $business = $user->business;



            // Extracting only the required data
$responseData = [
    'id' => $user->id,
    "token" =>  $user->createToken('Laravel Password Grant Client')->accessToken,
    'business_id' => $user->business_id,
    'first_Name' => $user->first_Name,
    'middle_Name' => $user->middle_Name,
    'last_Name' => $user->last_Name,
    'image' => $user->image,
    'roles' => $user->roles,
    'permissions' => $user->permissions,
    'manages_department' => $user->manages_department,
    'color_theme_name' => $user->color_theme_name,
    'business' => [
        'is_subscribed' => $business ? $business->is_subscribed : null,
        'is_active' => $business ? $business->is_active : null,
        'name' => $business ? $business->name : null,
        'logo' => $business ? $business->logo : null,
        'start_date' => $business ? $business->start_date : null,
        'currency' => $business ? $business->currency : null,

        'is_self_registered_businesses' => $business ? $business->is_self_registered_businesses : 0,
        'trail_end_date' => $business ? $business->trail_end_date : "",
        'current_subscription' =>  $business ? $business->current_subscription:"",
        "stripe_subscription_enabled" =>  $business ? $business->stripe_subscription_enabled:0,
        'reseller_id' => $business ? $business->reseller_id : null,
    ]
];



            return response()->json(
                $responseData,
                200
            );
        }catch(Exception $e) {
            return $this->sendError($e, 500,$request);
        }

    }



    public function checkEmail(Request $request) {
        try{
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            $user = User::where([
                "email" => $request->email
               ])
               ->when(
                !empty($request->user_id),
                function($query) use($request){
                    $query->whereNotIn("id",[$request->user_id]);
                })

               ->first();
               if($user) {
       return response()->json(["data" => true],200);
               }
               return response()->json(["data" => false],200);
        }catch(Exception $e) {
            return $this->sendError($e, 500,$request);
        }

 }



     public function checkBusinessEmail(Request $request) {
        try{
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            $user = Business::where([
                "email" => $request->email
               ])
               ->when(
                !empty($request->business_id),
                function($query) use($request){
                    $query->whereNotIn("id",[$request->business_id]);
                })

               ->first();
               if($user) {
       return response()->json(["data" => true],200);
               }
               return response()->json(["data" => false],200);
        }catch(Exception $e) {
            return $this->sendError($e, 500,$request);
        }

 }






    public function changePassword(PasswordChangeRequest $request)
    {
try{
    $this->storeActivity($request, "DUMMY activity","DUMMY description");
    $client_request = $request->validated();

    $user = $request->user();



    if (!Hash::check($client_request["current_password"],$user->password)) {

        return response()->json([
            "message" => "Invalid password"
        ], 400);
    }

    $password = Hash::make($client_request["password"]);
    $user->password = $password;



    $user->login_attempts = 0;
    $user->last_failed_login_attempt_at = null;
    $user->save();



    return response()->json([
        "message" => "password changed"
    ], 200);
}catch(Exception $e) {
    return $this->sendError($e,500,$request);
}

    }






    public function updateUserInfo(UserInfoUpdateRequest $request)
    {

        try{
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            $request_data = $request->validated();


            if(!empty($request_data['password'])) {
                $request_data['password'] = Hash::make($request_data['password']);
            } else {
                unset($request_data['password']);
            }
            // $request_data['is_active'] = true;
            $request_data['remember_token'] = Str::random(10);

            $user  =  tap(User::where(["id" => $request->user()->id]))->update(collect($request_data)->only([
                'first_Name' ,
                'middle_Name',
                'last_Name',
                'password',
                'phone',
                'address_line_1',
                'address_line_2',
                'country',
                'city',
                'postcode',
                "lat",
                "long",
                'gender',
                "image"
            ])->toArray()
            )
                // ->with("somthing")

                ->first();
                if(!$user) {
                    return response()->json([
                        "message" => "no user found"
                        ]);

            }


            $user->roles = $user->roles->pluck('name');


            return response($user, 200);
        } catch(Exception $e){
            error_log($e->getMessage());
        return $this->sendError($e,500,$request);
        }
    }










}
