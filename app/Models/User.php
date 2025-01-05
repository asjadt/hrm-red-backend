<?php

namespace App\Models;

use App\Http\Utils\BasicUtil;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Permission\Traits\HasRoles;
class User extends Authenticatable
{
    use   Billable, HasApiTokens, HasFactory, Notifiable, HasRoles, BasicUtil;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */

    protected $connection = 'mysql';


    protected $appends = ['has_this_project',"manages_department"];
    protected $guard_name = "api";
    protected $fillable = [
        'first_Name',
        'last_Name',
        'middle_Name',

        "NI_number",
        "pension_eligible",
        "color_theme_name",
        'emergency_contact_details',
        'gender',
        'is_in_employee',
        'designation_id',
        'employment_status_id',
        'joining_date',



        'salary_per_annum',
        'weekly_contractual_hours',
        'minimum_working_days_per_week',
        'overtime_rate',
        'phone',
        'image',
        'address_line_1',
        'address_line_2',
        'country',
        'city',
        'postcode',
        "lat",
        "long",
        'email',
        'password',
        'is_sponsorship_offered',
        "date_of_birth",

        "immigration_status",

        "is_active_visa_details",
        "is_active_right_to_works",

        'bank_id',
        'sort_code',
        'account_number',
        'account_name',


        'business_id',
        'user_id',
        "created_by",
         'is_active'
    ];



  public function getHasThisProjectAttribute($value) {
    $request = request();
    // You can now use $currentRequest as the request object
    $has_this_project = $request->input('has_this_project');


    if(empty($has_this_project)) {
        return NULL;
    }
    $project = Project::
    whereHas("users",function($query) {
      $query->where("users.id",$this->id);
   })
     ->where([
      "id" => $has_this_project
     ])
      ->first();

      return $project?1:0;
    }


    public function getManagesDepartmentAttribute($value) {

$all_departments = $this->get_all_departments_of_manager();

return count($all_departments) > 0;

        }


        public function bank()
        {
            return $this->hasOne(Bank::class, "id" ,'bank_id');
        }



    public function payrun_user()
    {
        return $this->hasOne(PayrunUser::class, "user_id" ,'id');
    }


    public function payrolls()
    {
        return $this->hasMany(Payroll::class, "user_id" ,'id');
    }



    public function projects() {
        return $this->belongsToMany(Project::class, 'user_projects', 'user_id', 'project_id');
    }


    public function work_locations() {
        return $this->belongsToMany(WorkLocation::class, 'user_work_locations', 'user_id', 'work_location_id');
    }


    public function resold_businesses()
    {
        return $this->hasMany(Business::class, 'reseller_id', 'id');
    }


    public function holidays() {
        return $this->belongsToMany(Holiday::class, 'user_holidays', 'user_id', 'holiday_id');
    }





    public function business() {
        return $this->belongsTo(Business::class, 'business_id', 'id');
    }


    public function all_users() {
        return $this->hasMany(User::class, 'business_id', 'business_id');
    }


    public function departments() {
        return $this->belongsToMany(Department::class, 'department_users', 'user_id', 'department_id');
    }

    public function department_user() {
        return $this->belongsTo(DepartmentUser::class,  'id', 'user_id');
    }


    public function recruitment_processes() {
        return $this->hasMany(UserRecruitmentProcess::class, 'user_id', 'id');
    }


    public function designation() {
        return $this->belongsTo(Designation::class, 'designation_id', 'id');
    }

    public function employment_status() {
        return $this->belongsTo(EmploymentStatus::class, 'employment_status_id', 'id');
    }

    public function work_shifts() {
        return $this->belongsToMany(WorkShift::class, 'user_work_shifts', 'user_id', 'work_shift_id');
    }


    public function employee_rota() {
        return $this->hasOne(EmployeeRota::class, 'user_id', 'id');
    }

    public function leaves() {
        return $this->hasMany(Leave::class, 'user_id', 'id');
    }
    public function attendances() {
        return $this->hasMany(Attendance::class, 'user_id', 'id');
    }

    public function attendance_histories() {
        return $this->hasMany(AttendanceHistory::class, 'user_id', 'id');
    }

    public function sponsorship_detail() {
        $issue_date_column = 'date_assigned';
        $expiry_date_column = 'expiry_date';
        $current_user_id = request()->id;

        $user = User::where([
            "id" => $current_user_id
        ])
        ->first();

        if(!$user) {
            return NULL;
          }

        $current_data = NULL;

           $latest_expired_record = EmployeeSponsorshipHistory::where('user_id', $current_user_id)
            ->where($issue_date_column, '<', now())
            ->orderBy($expiry_date_column, 'DESC')
            ->first();

            if($latest_expired_record) {
                $current_data = EmployeeSponsorshipHistory::where('user_id', $current_user_id)
                ->where($issue_date_column, '<', now())
                ->where($expiry_date_column, $latest_expired_record[$expiry_date_column])
                // ->orderByDesc($issue_date_column)
                ->orderByDesc('id')
                ->first();
            }

        return    $this->hasOne(EmployeeSponsorshipHistory::class, 'user_id', 'id')
        ->where("id",$current_data?$current_data->id:NULL);
    }



    public function passport_detail() {
        $issue_date_column = 'passport_issue_date';
        $expiry_date_column = 'passport_expiry_date';
        $current_user_id = request()->id;

        $user = User::where([
            "id" => $current_user_id
        ])
        ->first();

        if(!$user) {
            return NULL;
          }

        $current_data = NULL;

           $latest_expired_record = EmployeePassportDetailHistory::where('user_id', $current_user_id)
            ->where($issue_date_column, '<', now())
            ->orderBy($expiry_date_column, 'DESC')
            ->first();


            if($latest_expired_record) {
                $current_data = EmployeePassportDetailHistory::where('user_id', $current_user_id)
                ->where($issue_date_column, '<', now())
                ->where($expiry_date_column, $latest_expired_record[$expiry_date_column])
                // ->orderByDesc($issue_date_column)
                ->orderByDesc('id')
                ->first();
            }




        return    $this->hasOne(EmployeePassportDetailHistory::class, 'user_id', 'id')
        ->where("id",$current_data?$current_data->id:NULL);
    }
    public function visa_detail() {
        $issue_date_column = 'visa_issue_date';
        $expiry_date_column = 'visa_expiry_date';
        $current_user_id = request()->id;

        $user = User::where([
            "id" => $current_user_id
        ])
        ->first();

        if(!$user) {
            return NULL;
          }

        $current_data = NULL;

           $latest_expired_record = EmployeeVisaDetailHistory::where('user_id', $current_user_id)
            ->where($issue_date_column, '<', now())
            ->orderBy($expiry_date_column, 'DESC')
            ->first();


            if($latest_expired_record) {
                $current_data = EmployeeVisaDetailHistory::where('user_id', $current_user_id)
                ->where($issue_date_column, '<', now())
                ->where($expiry_date_column, $latest_expired_record[$expiry_date_column])
                // ->orderByDesc($issue_date_column)
                ->orderByDesc('id')
                ->first();
            }

        return    $this->hasOne(EmployeeVisaDetailHistory::class, 'user_id', 'id')
        ->where("id",$current_data?$current_data->id:NULL);
    }
    public function right_to_work() {
        $issue_date_column = 'right_to_work_check_date';
        $expiry_date_column = 'right_to_work_expiry_date';
        $current_user_id = request()->id;

        $user = User::where([
            "id" => $current_user_id
        ])
        ->first();

        if(!$user) {
            return NULL;
          }

        $current_data = NULL;

           $latest_expired_record = EmployeeRightToWorkHistory::where('user_id', $current_user_id)
            ->where($issue_date_column, '<', now())
            ->orderBy($expiry_date_column, 'DESC')
            ->first();

            if($latest_expired_record) {
                $current_data = EmployeeRightToWorkHistory::where('user_id', $current_user_id)
                ->where($issue_date_column, '<', now())
                ->where($expiry_date_column, $latest_expired_record[$expiry_date_column])
                // ->orderByDesc($issue_date_column)
                ->orderByDesc('id')
                ->first();
            }

        return    $this->hasOne(EmployeeRightToWorkHistory::class, 'user_id', 'id')
        ->where("id",$current_data?$current_data->id:NULL);
    }
    public function sponsorship_details() {
        $issue_date_column = 'date_assigned';
        $expiry_date_column = 'expiry_date';
        $current_user_id = request()->id;

        $user = User::where([
            "id" => $current_user_id
        ])
        ->first();

        if(!$user) {
            return NULL;
          }

        $current_data = NULL;

           $latest_expired_record = EmployeeSponsorshipHistory::where('user_id', $current_user_id)
            ->where($issue_date_column, '<', now())
            ->orderBy($expiry_date_column, 'DESC')
            ->first();

            if($latest_expired_record) {
                $current_data = EmployeeSponsorshipHistory::where('user_id', $current_user_id)
                ->where($issue_date_column, '<', now())
                ->where($expiry_date_column, $latest_expired_record[$expiry_date_column])
                // ->orderByDesc($issue_date_column)
                ->orderByDesc('id')
                ->first();
            }

        return    $this->hasOne(EmployeeSponsorshipHistory::class, 'user_id', 'id')
        ->where("id",$current_data?$current_data->id:NULL);
    }






    public function pension_details() {
        $current_user_id = request()->id;
        if(!$current_user_id){
 return $this->hasOne(EmployeePensionHistory::class, 'user_id', 'id');
        }

            $issue_date_column = 'pension_enrollment_issue_date';
            $expiry_date_column = 'pension_re_enrollment_due_date';


            $user = User::where([
                "id" => $current_user_id
            ])
            ->first();

              $current_data = NULL;
            if(!$user->pension_eligible) {
                $current_data = EmployeePensionHistory::where('user_id', $current_user_id)
                ->where("pension_eligible",0)
                ->orderByDesc("employee_pension_histories.id")
                ->first();
            } else {


                $current_data = EmployeePensionHistory::where('user_id', $current_user_id)
                ->where("pension_eligible", 1)
                ->where($issue_date_column, '<', now())
                    ->orderByDesc("id")

                    ->first();
            }



            return     $this->hasOne(EmployeePensionHistory::class, 'user_id', 'id')
            ->where("id",$current_data?$current_data->id:NULL);



    }

    public function pension_detail() {
        $issue_date_column = 'pension_enrollment_issue_date';
        $expiry_date_column = 'pension_re_enrollment_due_date';
        $current_user_id = request()->id;

        $user = User::where([
            "id" => $current_user_id
        ])
        ->first();
        if(!$user) {
          return NULL;
        }



        $current_data = NULL;

        if(!$user->pension_eligible) {
            $current_data = EmployeePensionHistory::where('user_id', $current_user_id)
            ->where("pension_eligible",0)
            ->orderByDesc("employee_pension_histories.id")
            ->first();
        } else {
            $current_data = EmployeePensionHistory::where('user_id', $current_user_id)
            ->where("pension_eligible", 1)
            ->where($issue_date_column, '<', now())
                ->orderByDesc("id")

                ->first();
        }


        return    $this->hasOne(EmployeePensionHistory::class, 'user_id', 'id')
        ->where("id",$current_data?$current_data->id:NULL);
    }

    public function passport_details() {
        $issue_date_column = 'passport_issue_date';
        $expiry_date_column = 'passport_expiry_date';
        $current_user_id = request()->id;

        $user = User::where([
            "id" => $current_user_id
        ])
        ->first();

        if(!$user) {
            return NULL;
          }

        $current_data = NULL;

           $latest_expired_record = EmployeePassportDetailHistory::where('user_id', $current_user_id)
            ->where($issue_date_column, '<', now())
            ->orderBy($expiry_date_column, 'DESC')
            ->first();


            if($latest_expired_record) {
                $current_data = EmployeePassportDetailHistory::where('user_id', $current_user_id)
                ->where($issue_date_column, '<', now())
                ->where($expiry_date_column, $latest_expired_record[$expiry_date_column])
                // ->orderByDesc($issue_date_column)
                ->orderByDesc('id')
                ->first();
            }




        return    $this->hasOne(EmployeePassportDetailHistory::class, 'user_id', 'id')
        ->where("id",$current_data?$current_data->id:NULL);
    }
    public function visa_details() {
        $issue_date_column = 'visa_issue_date';
        $expiry_date_column = 'visa_expiry_date';
        $current_user_id = request()->id;

        $user = User::where([
            "id" => $current_user_id
        ])
        ->first();

        if(!$user) {
            return NULL;
          }

        $current_data = NULL;

           $latest_expired_record = EmployeeVisaDetailHistory::where('user_id', $current_user_id)
            ->where($issue_date_column, '<', now())
            ->orderBy($expiry_date_column, 'DESC')
            ->first();


            if($latest_expired_record) {
                $current_data = EmployeeVisaDetailHistory::where('user_id', $current_user_id)
                ->where($issue_date_column, '<', now())
                ->where($expiry_date_column, $latest_expired_record[$expiry_date_column])
                // ->orderByDesc($issue_date_column)
                ->orderByDesc('id')
                ->first();
            }

        return    $this->hasOne(EmployeeVisaDetailHistory::class, 'user_id', 'id')
        ->where("id",$current_data?$current_data->id:NULL);
    }
    public function right_to_works() {
        $issue_date_column = 'right_to_work_check_date';
        $expiry_date_column = 'right_to_work_expiry_date';
        $current_user_id = request()->id;

        $user = User::where([
            "id" => $current_user_id
        ])
        ->first();

        if(!$user) {
            return NULL;
          }

        $current_data = NULL;

           $latest_expired_record = EmployeeRightToWorkHistory::where('user_id', $current_user_id)
            ->where($issue_date_column, '<', now())
            ->orderBy($expiry_date_column, 'DESC')
            ->first();

            if($latest_expired_record) {
                $current_data = EmployeeRightToWorkHistory::where('user_id', $current_user_id)
                ->where($issue_date_column, '<', now())
                ->where($expiry_date_column, $latest_expired_record[$expiry_date_column])
                // ->orderByDesc($issue_date_column)
                ->orderByDesc('id')
                ->first();
            }

        return    $this->hasOne(EmployeeRightToWorkHistory::class, 'user_id', 'id')
        ->where("id",$current_data?$current_data->id:NULL);
    }


    public function assets() {
        return $this->hasMany(UserAsset::class, 'user_id', 'id');
    }
    public function documents() {
        return $this->hasMany(UserDocument::class, 'user_id', 'id');
    }
    public function education_histories() {
        return $this->hasMany(UserEducationHistory::class, 'user_id', 'id');
    }
    public function job_histories() {
        return $this->hasMany(UserJobHistory::class, 'user_id', 'id');
    }

    public function notes() {
        return $this->hasMany(UserNote::class, 'user_id', 'id');
    }

    public function social_links() {
        return $this->hasMany(UserSocialSite::class, 'user_id', 'id');
    }


    public function scopeWhereHasRecursiveHolidays($query, $today,$depth = 5)
    {
        $query->whereHas('departments', function ($subQuery) use ($today,$depth) {
            $subQuery->whereHasRecursiveHolidays($today,$depth);
        });
    }


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        "site_redirect_token",

        "email_verify_token",
        "email_verify_token_expires",
        "resetPasswordToken",
        "resetPasswordExpires"
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'emergency_contact_details' => 'array',
    ];


















}
