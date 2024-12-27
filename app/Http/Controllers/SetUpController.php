<?php

namespace App\Http\Controllers;

use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\ActivityLog;
use App\Models\Bank;
use App\Models\Business;
use App\Models\Designation;
use App\Models\EmploymentStatus;
use App\Models\ErrorLog;
use App\Models\JobPlatform;
use App\Models\JobType;
use App\Models\RecruitmentProcess;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use App\Models\Role;
use App\Models\ServicePlan;
use App\Models\SettingAttendance;
use App\Models\SettingLeave;
use App\Models\SettingLeaveType;
use App\Models\SettingPaymentDate;
use App\Models\SettingPayrun;
use App\Models\SocialSite;
use App\Models\TaskCategory;
use App\Models\WorkLocation;
use App\Models\WorkShift;
use App\Models\WorkShiftHistory;
use Illuminate\Support\Facades\Log;

class SetUpController extends Controller
{
    use ErrorUtil, UserActivityUtil;

    public function getFrontEndErrorLogs(Request $request) {
        $this->storeActivity($request, "DUMMY activity","DUMMY description");
        $error_logs = ErrorLog::
        whereIn("status_code",[422,403,400,404,409])
        ->when(!empty($request->status), function ($query) use($request){
            $query->where("status_code",$request->status);
        })
        ->orderbyDesc("id")->paginate(10);
        return view("error-log",compact("error_logs"));
    }

  public function getErrorLogs(Request $request) {
        $this->storeActivity($request, "DUMMY activity","DUMMY description");
        $error_logs = ErrorLog::
        when(!empty($request->status_code), function ($query) use($request){
            $query->where("status_code",$request->status);
        })
        ->when(!empty($request->ip_address), function ($query) use($request){
            $query->where("ip_address",$request->ip_address);
        })
        ->when(!empty($request->request_method), function ($query) use($request){
            $query->where("request_method",$request->request_method);
        })
        ->when(!empty($request->id), function ($query) use($request){
            $query->where("id",$request->id);
        })
        ->orderbyDesc("id")->paginate(10);
        return view("error-log",compact("error_logs"));
    }

    public function testError($id,Request $request) {
        $this->storeActivity($request, "DUMMY activity","DUMMY description");
        $error_log = ErrorLog::where("id",$request->id)

        ->first();
        return view("test-error",compact("error_log"));
    }

    public function testApi($id,Request $request) {
        $this->storeActivity($request, "DUMMY activity","DUMMY description");
        $error_log = ActivityLog::where("id",$request->id)

        ->first();
        return view("test-api",compact("error_log"));
    }



   public function getActivityLogs(Request $request) {
        $this->storeActivity($request, "DUMMY activity","DUMMY description");
        $activity_logs = ActivityLog::
        when(!empty($request->status_code), function ($query) use($request){
            $query->where("status_code",$request->status);
        })
        ->when(!empty($request->user_id), function ($query) use($request){
            $query->where("user_id",$request->user_id);
        })
        -> when(!empty($request->api_url), function ($query) use($request){
            $query->where("api_url",$request->api_url);
        })
        ->when(!empty($request->ip_address), function ($query) use($request){
            $query->where("ip_address",$request->ip_address);
        })
        ->when(!empty($request->request_method), function ($query) use($request){
            $query->where("request_method",$request->request_method);
        })
        ->when(!empty($request->id), function ($query) use($request){
            $query->where("id",$request->id);
        })

        ->orderbyDesc("id")
        ->paginate(100);
        return view("user-activity-log",compact("activity_logs"));
    }

    public function migrate(Request $request) {
        $this->storeActivity($request, "DUMMY activity","DUMMY description");
        Artisan::call('migrate');
        return "migrated";
            }

    public function swaggerRefresh(Request $request) {
        $this->storeActivity($request, "DUMMY activity","DUMMY description");
        Artisan::call('optimize:clear');
Artisan::call('l5-swagger:generate');
return "swagger generated";
    }

    public function clearCache() {
        Artisan::call('key:generate');
        Artisan::call('optimize:clear');
        Artisan::call('passport:install');
        Artisan::call('l5-swagger:generate');
        return "ok";
    }


    public function setUp(Request $request)
    {
        $this->storeActivity($request, "DUMMY activity","DUMMY description");
        // @@@@@@@@@@@@@@@@@@@
        // clear everything
        // @@@@@@@@@@@@@@@@@@@

        Artisan::call('optimize:clear');
        Artisan::call('migrate:fresh');
        Artisan::call('migrate', ['--path' => 'vendor/laravel/passport/database/migrations']);
        Artisan::call('passport:install');
        Artisan::call('l5-swagger:generate');



        // ##########################################
        // user
        // #########################################
      $admin =  User::create([
        'first_Name' => "super",
        'last_Name'=> "admin",
        'phone'=> "01771034383",
        'address_line_1',
        'address_line_2',
        'country'=> "Bangladesh",
        'city'=> "Dhaka",
        'postcode'=> "1207",
        'email'=> "asjadtariq@gmail.com",
        'password'=>Hash::make("12345678@We"),
        "email_verified_at"=>now(),
        'is_active' => 1
        ]);
        $admin->email_verified_at = now();
        $admin->save();

        $reseller =  User::create([
            'first_Name' => "Shahbaz",
            'last_Name'=> "Khan",
            'phone'=> "01771034383",
            'address_line_1',
            'address_line_2',
            'country'=> "Bangladesh",
            'city'=> "Dhaka",
            'postcode'=> "1207",
            'email'=> "shahbaz.scm@gmail.com",
            'password'=>Hash::make("12345678@We"),
            "email_verified_at"=>now(),
            'is_active' => 1
            ]);
            $reseller->email_verified_at = now();
            $reseller->save();

            $specialReseller =  User::create([
                'first_Name' => "Shahbaz",
                'last_Name'=> "Khan",
                'phone'=> "01771034383",
                'address_line_1',
                'address_line_2',
                'country'=> "Bangladesh",
                'city'=> "Dhaka",
                'postcode'=> "1207",
                'email'=> "kids20acc@gmail.com",
                'password' => Hash::make("12345678@We"),
                "email_verified_at"=>now(),
                'is_active' => 1
                ]);
                $specialReseller->email_verified_at = now();
                $specialReseller->save();

                $permissions = Permission::whereIn('name', ["handle_self_registered_businesses"])->get();
                $specialReseller->givePermissionTo($permissions);


        // ###############################
        // permissions
        // ###############################
        $permissions =  config("setup-config.permissions");
        // setup permissions
        foreach ($permissions as $permission) {
            if(!Permission::where([
            'name' => $permission,
            'guard_name' => 'api'
            ])
            ->exists()){
                Permission::create(['guard_name' => 'api', 'name' => $permission]);
            }

        }
        // setup roles
        $roles = config("setup-config.roles");
        foreach ($roles as $role) {
            if(!Role::where([
            'name' => $role,
            'guard_name' => 'api',
            "is_system_default" => 1,
            "business_id" => NULL,
            "is_default" => 1,
            ])
            ->exists()){
             Role::create(['guard_name' => 'api', 'name' => $role,"is_system_default"=> 1, "business_id" => NULL,
             "is_default" => 1,
             "is_default_for_business" => (in_array($role ,["business_owner",
             "business_admin",
             "business_manager",
             "business_employee"])?1:0)


            ]);
            }

        }

        // setup roles and permissions
        $role_permissions = config("setup-config.roles_permission");
        foreach ($role_permissions as $role_permission) {
            $role = Role::where(["name" => $role_permission["role"]])->first();
            error_log($role_permission["role"]);
            $permissions = $role_permission["permissions"];
            $role->syncPermissions($permissions);
            // foreach ($permissions as $permission) {
            //     if(!$role->hasPermissionTo($permission)){
            //         $role->givePermissionTo($permission);
            //     }


            // }
        }






        $specialReseller->assignRole("reseller");
        $reseller->assignRole("reseller");
        $admin->assignRole("superadmin");


        $social_media_platforms = [
            ['id' => 1, 'name' => 'Linkedin', 'icon' => 'FaLinkedin', 'link' => 'https://www.linkedin.com/'],
            ['id' => 2, 'name' => 'Github', 'icon' => 'FaGithub', 'link' => 'https://github.com/'],
            ['id' => 3, 'name' => 'Gitlab', 'icon' => 'FaGitlab', 'link' => 'https://gitlab.com/'],
            ['id' => 4, 'name' => 'Facebook', 'icon' => 'FaSquareFacebook', 'link' => 'https://www.facebook.com/'],
            ['id' => 5, 'name' => 'Instagram', 'icon' => 'FaInstagram', 'link' => 'https://www.instagram.com/'],
            ['id' => 6, 'name' => 'Youtube', 'icon' => 'FaYoutube', 'link' => 'https://www.youtube.com/'],
            ['id' => 7, 'name' => 'Twitter', 'icon' => 'FaSquareTwitter', 'link' => 'https://twitter.com/'],
            ['id' => 8, 'name' => 'Dribbble', 'icon' => 'FaSquareDribbble', 'link' => 'https://dribbble.com/'],
            ['id' => 9, 'name' => 'Behance', 'icon' => 'FaSquareBehance', 'link' => 'https://www.behance.net/'],
            ['id' => 10, 'name' => 'Twitch', 'icon' => 'FaTwitch', 'link' => 'https://www.twitch.tv/'],
            ['id' => 11, 'name' => 'Stack Overflow', 'icon' => 'FaStackOverflow', 'link' => 'https://stackoverflow.com/'],
            ['id' => 12, 'name' => 'Slack', 'icon' => 'FaSlack', 'link' => 'https://slack.com/'],
            ['id' => 13, 'name' => 'Other', 'icon' => 'FaGlobe', 'link' => ''],
        ];


        // Iterate through the array and create records
        foreach ($social_media_platforms as $data) {
            SocialSite::create([
                'name' => $data['name'],
                'icon' => $data['icon'],
                'link' => $data['link'],
                "is_active" => 1,
                "is_default" => 1,
                "business_id" => NULL,
                "created_by" => $admin->id
            ]);
        }

        $banks = [
            ['id' => 1, 'name' => 'Barclays'],
            ['id' => 2, 'name' => 'HSBC'],
            ['id' => 3, 'name' => 'Lloyds Banking Group'],
            ['id' => 4, 'name' => 'Nationwide'],
            ['id' => 5, 'name' => 'NatWest Group'],
            ['id' => 6, 'name' => 'Santander UK'],
            ['id' => 7, 'name' => 'Standard Chartered'],
        ];

        foreach ($banks as $data) {
            Bank::create([
                'name' => $data['name'],
                'is_active' => 1,
                'is_default' => 1,
                'business_id' => NULL,
                'created_by' => $admin->id
            ]);
        }











        $default_designations = [
            [
                'name' => "CEO",
                'description' => "Chief Executive Officer",
            ],
            [
                'name' => "HR Manager",
                'description' => "Human Resources Manager",
            ],
            [
                'name' => "Finance Manager",
                'description' => "Finance Manager",
            ],
            [
                'name' => "Sales Representative",
                'description' => "Sales Representative",
            ],
            [
                'name' => "IT Specialist",
                'description' => "Information Technology Specialist",
            ],
            [
                'name' => "Marketing Coordinator",
                'description' => "Marketing Coordinator",
            ],
            [
                'name' => "Customer Service Representative",
                'description' => "Customer Service Representative",
            ],
        ];

        // Iterate through the array and create records
        foreach ($default_designations as $data) {
            Designation::create([
                'name' => $data['name'],
                'description' => $data['description'],
                "is_active" => 1,
                "is_default" => 1,
                "business_id" => NULL,
                "created_by" => $admin->id
            ]);
        }
        $default_job_type = [
            [
                'name' => "Full Time Employee",
                'description' => "An employee who works a standard number of hours per week as defined by the organization."
            ],
            [
                'name' => "Part Time Employee",
                'description' => "An employee who works fewer hours than a full-time employee, often with a set schedule."
            ],
            [
                'name' => "Contractor",
                'description' => "An individual hired on a contract basis for a specific project or period, not considered a permanent employee."
            ],
            [
                'name' => "Temporary Employee",
                'description' => "An employee hired for a short-term period to cover a specific workload or project."
            ],
            [
                'name' => "Freelancer",
                'description' => "A self-employed individual who provides services to the organization on a project-by-project basis."
            ],
            [
                'name' => "Intern",
                'description' => "A student or recent graduate gaining practical experience in a specific field, often for a limited duration."
            ],
            [
                'name' => "Remote Worker",
                'description' => "An employee who works primarily from a location outside the office, such as from home or another remote location."
            ],
            // Add more job types as needed
        ];

        // Iterate through the array and create records
        foreach ($default_job_type as $data) {
            JobType::create([
                'name' => $data['name'],
                'description' => $data['description'],
                "is_active" => 1,
                "is_default" => 1,
                "business_id" => NULL,
                "created_by" => $admin->id
            ]);
        }






        $default_work_location = [
            [
                'name' => "Office-Based",
                'description' => "Employees who work primarily at the company's physical office location."
            ],
            [
                'name' => "Remote",
                'description' => "Employees who work from a location outside the office, such as from home or another remote setting."
            ],
            [
                'name' => "Hybrid",
                'description' => "Employees who split their work time between the office and remote locations, following a flexible schedule."
            ],
            [
                'name' => "Client Site",
                'description' => "Employees who work primarily at the location of a client or customer."
            ],
            [
                'name' => "Field-Based",
                'description' => "Employees whose work involves traveling to various locations, such as sales representatives or field service technicians."
            ],
            [
                'name' => "On-Site",
                'description' => "Employees who work at a specific site or project location, but not necessarily the main office."
            ],
            [
                'name' => "Shop or Warehouse",
                'description' => "Employees working in a physical location where products are stored, manufactured, or distributed."
            ],
            [
                'name' => "Flexible Location",
                'description' => "Employees with the flexibility to choose their work location based on the nature of their tasks or projects."
            ],
            // Add more work location types as needed
        ];





        // Iterate through the array and create records
        foreach ($default_work_location as $data) {
            WorkLocation::create([
                'name' => $data['name'],
                'description' => $data['description'],
                "is_active" => 1,
                "is_default" => 1,
                "business_id" => NULL,
                "created_by" => $admin->id
            ]);
        }

        $default_job_platform = [
            [
                'name' => "LinkedIn",
                'description' => "A professional networking platform widely used for job postings, networking, and recruitment."
            ],
            [
                'name' => "Indeed",
                'description' => "A popular job search engine that aggregates job listings from various sources, including company websites and job boards."
            ],
            [
                'name' => "Monster",
                'description' => "An online job portal that connects employers with job seekers, offering a wide range of job postings."
            ],
            [
                'name' => "Reed",
                'description' => "One of the largest job sites in the UK, providing a platform for employers and job seekers across various industries."
            ],
            [
                'name' => "Glassdoor",
                'description' => "A platform that not only provides job listings but also offers company reviews, salary information, and interview insights."
            ],
            [
                'name' => "Totaljobs",
                'description' => "A UK-based job board that features a variety of job listings and career resources for both employers and job seekers."
            ],
            [
                'name' => "Jobsite",
                'description' => "An online recruitment platform that connects employers with job seekers, offering a range of job opportunities."
            ],
            [
                'name' => "CareerBuilder",
                'description' => "A global job board and recruitment platform that connects employers with qualified candidates."
            ],
            [
                'name' => "CWJobs",
                'description' => "Specialized in IT and tech jobs, CWJobs is a platform catering to employers and job seekers in the technology sector."
            ],
            // Add more job platforms as needed
        ];

        // Iterate through the array and create records
        foreach ($default_job_platform as $data) {
            JobPlatform::create([
                'name' => $data['name'],
                'description' => $data['description'],
                "is_active" => 1,
                "is_default" => 1,
                "business_id" => NULL,
                "created_by" => $admin->id
            ]);
        }


        $default_task_categories = [
            [
                'name' => "To Do",
                'description' => "Tasks that are yet to be started."
            ],
            [
                'name' => "In Progress",
                'description' => "Tasks that are currently being worked on."
            ],
            [
                'name' => "Done",
                'description' => "Tasks that have been completed."
            ],
            [
                'name' => "Backlog",
                'description' => "Tasks that are pending and not scheduled for immediate action."
            ],
            [
                'name' => "Ready for Review",
                'description' => "Tasks that are completed and ready for review by stakeholders or team members."
            ],
            [
                'name' => "Blocked",
                'description' => "Tasks that cannot progress due to some external dependencies or issues."
            ],
            [
                'name' => "Testing/QA",
                'description' => "Tasks that are undergoing testing or quality assurance processes."
            ],
            [
                'name' => "Waiting for Approval",
                'description' => "Tasks that are completed and awaiting approval from stakeholders."
            ],
            [
                'name' => "On Hold",
                'description' => "Tasks that are temporarily suspended or put on hold."
            ],
            [
                'name' => "Urgent",
                'description' => "Tasks that require immediate attention or action."
            ],
            [
                'name' => "Priority",
                'description' => "Tasks that are of high priority and need to be addressed before others."
            ],
            [
                'name' => "Needs Clarification",
                'description' => "Tasks that lack clarity and require further clarification before proceeding."
            ],
            [
                'name' => "Rejected",
                'description' => "Tasks that have been rejected or deemed unnecessary."
            ],
            [
                'name' => "Deferred",
                'description' => "Tasks that are postponed to a later time or date."
            ],
            [
                'name' => "Feedback Received",
                'description' => "Tasks for which feedback has been received and need to be updated accordingly."
            ],
            [
                'name' => "In Review",
                'description' => "Tasks that are under review by stakeholders or team members."
            ],
            [
                'name' => "Pending",
                'description' => "Tasks that are pending action or awaiting further instructions."
            ],
            [
                'name' => "Scheduled",
                'description' => "Tasks that are scheduled for future action or completion."
            ],
            [
                'name' => "In Maintenance",
                'description' => "Tasks that are part of ongoing maintenance activities."
            ],
            [
                'name' => "Closed",
                'description' => "Tasks that have been completed and formally closed."
            ],
            [
                'name' => "Archived",
                'description' => "Tasks that are no longer active but are retained for reference or historical purposes."
            ],
        ];

         // Iterate through the array and create records
         foreach ($default_task_categories as $data) {
            TaskCategory::create([
                'name' => $data['name'],
                'description' => $data['description'],
                "is_active" => 1,
                "is_default" => 1,
                "business_id" => NULL,
                "created_by" => $admin->id
            ]);
        }



        $default_recruitment_process = [
            [
                'name' => "Job Requisition",
                'description' => "Identify the need for a new position or replacement and create a job requisition specifying the role's requirements."
            ],
            [
                'name' => "Job Posting",
                'description' => "Advertise the job opening through various channels, including the company's website, job boards, and social media."
            ],
            [
                'name' => "Application Screening",
                'description' => "Review resumes and applications to shortlist candidates who meet the basic qualifications for the position."
            ],
            [
                'name' => "Initial Interview",
                'description' => "Conduct a preliminary interview to assess candidates' skills, experience, and cultural fit with the organization."
            ],
            [
                'name' => "Skills Assessment",
                'description' => "Administer tests or exercises to evaluate candidates' technical or job-specific skills relevant to the role."
            ],
            [
                'name' => "Second Interview",
                'description' => "Invite shortlisted candidates for a more in-depth interview, often involving key team members or department heads."
            ],
            [
                'name' => "Reference Check",
                'description' => "Contact previous employers or references to verify the candidate's work history, performance, and reliability."
            ],
            [
                'name' => "Job Offer",
                'description' => "Extend a formal job offer to the selected candidate, including details about compensation, benefits, and start date."
            ],
            [
                'name' => "Negotiation",
                'description' => "Engage in negotiations with the candidate regarding salary, benefits, and other terms of employment."
            ],
            [
                'name' => "Onboarding",
                'description' => "Facilitate the onboarding process, including orientation, paperwork, and introductions to team members and company policies."
            ],
            [
                'name' => "Job Contract",
                'description' => "Ensure the job contract is provided and understood by the new employee."
            ],



        ];


        foreach ($default_recruitment_process as $data) {
            RecruitmentProcess::create([
                'name' => $data['name'],
                'description' => $data['description'],
                "is_active" => 1,
                "is_default" => 1,
                "business_id" => NULL,
                "created_by" => $admin->id
            ]);
        }







        $default_employment_statuses = [
            [
                'name' => "Full-Time",
                'color' => "#22c55e",
                'description' => "Employee works the standard number of hours for a full-time position.",
            ],
            [
                'name' => "Part-Time",
                'color' => "#3b82f6",
                'description' => "Employee works fewer hours than a full-time position.",
            ],
            [
                'name' => "Contractor",
                'color' => "#f97316",
                'description' => "Employee is hired on a contractual basis for a specific project or duration.",
            ],
            [
                'name' => "Temporary",
                'color' => "#06b6d4",
                'description' => "Employee is hired for a temporary period, often to cover a specific absence or workload.",
            ],
            [
                'name' => "Intern",
                'color' => "#a855f7",
                'description' => "Employee is engaged in a temporary position for gaining practical work experience.",
            ],
        ];

        // Iterate through the array and create records
        foreach ($default_employment_statuses as $data) {
            EmploymentStatus::create([
                'name' => $data['name'],
                'color' => $data["color"],
                'description' => $data['description'],
                "is_active" => 1,
                "is_default" => 1,
                "business_id" => NULL,
                "created_by" => $admin->id
            ]);
        }


        $default_setting_leave_types = [
            [
                'name' => "Vacation Leave",
                'type' => "paid",
                'amount' => 80,
            ],
            [
                'name' => "Sick Leave",
                'type' => "paid",
                'amount' => 40,
            ],
            [
                'name' => "Personal Leave",
                'type' => "unpaid",
                'amount' => 30,
            ],
            [
                'name' => "Maternity Leave",
                'type' => "paid",
                'amount' => 120,
            ],
            [
                'name' => "Paternity Leave",
                'type' => "paid",
                'amount' => 80,
            ],
            [
                'name' => "Bereavement Leave",
                'type' => "paid",
                'amount' => 24,
            ],
        ];

        // Iterate through the array and create records
        foreach ($default_setting_leave_types as $data) {
            SettingLeaveType::create([
                'name' => $data['name'],
                'type' => $data["type"],
                'amount' => $data['amount'],
                "is_active" => 1,
                "is_default" => 1,
                "business_id" => NULL,
                "created_by" => $admin->id
            ]);
        }


        SettingLeave::create([
            'start_month' => 1,
            'approval_level' => "multiple",
            'allow_bypass' => 1,
          "business_id" => NULL,
          "is_active" => 1,
          "is_default" => 1,
          "created_by" => $admin->id,
        ]);

        SettingAttendance::create([
            'punch_in_time_tolerance' => 0.25,
            'work_availability_definition' => 80,
            'punch_in_out_alert' => 0,
            'punch_in_out_interval' => 0.5,
            'alert_area' => (["web","system"]),
            'auto_approval' => false,
            "is_geolocation_enabled" => 0,

            "business_id" => NULL,
            "is_active" => 1,
            "is_default" => 1,
            "created_by" => $admin->id,
        ]);


        SettingPayrun::create([
            'payrun_period' => "weekly",
            'consider_type' => "daily_log",
            'consider_overtime' => 1,
            "business_id" => NULL,
            "is_active" => 1,
            "is_default" => 1,
            "created_by" => $admin->id,
        ]);

        SettingPaymentDate::create([
            'payment_type' => 'weekly',
            'day_of_week' => 2,
            'day_of_month' => null,
            'custom_frequency_interval' => null,
            'custom_frequency_unit' => null,
            'is_active' => 1,
            'is_default' => 1,
            'business_id' => null,
            'created_by' => $admin->id,
            'role_specific_settings' => null,
        ]);


        $default_work_shift_data_1 = [
            'name' => 'Main Work Shift',
            'type' => 'regular',
            'description' => '',
            'is_personal' => false,
            'break_type' => 'unpaid',
            'break_hours' => 1,

            "is_active" => 1,
            "is_default"=> 1,
            'details' => [
                [
                    'day' => '0',
                    'start_at' => '',
                    'end_at' => '',
                    'is_weekend' => 1,
                ],
                [
                    'day' => '1',
                    'start_at' => '10:00:00',
                    'end_at' => '18:00:00',
                    'is_weekend' => 0,
                ],
                [
                    'day' => '2',
                    'start_at' => '10:00:00',
                    'end_at' => '18:00:00',
                    'is_weekend' => 0,
                ],
                [
                    'day' => '3',
                    'start_at' => '10:00:00',
                    'end_at' => '18:00:00',
                    'is_weekend' => 0,
                ],
                [
                    'day' => '4',
                    'start_at' => '10:00:00',
                    'end_at' => '18:00:00',
                    'is_weekend' => 0,
                ],
                [
                    'day' => '5',
                    'start_at' => '10:00:00',
                    'end_at' => '18:00:00',
                    'is_weekend' => 0,
                ],
                [
                    'day' => '6',
                    'start_at' => '',
                    'end_at' => '',
                    'is_weekend' => 1,
                ],
            ],
        ];


        $default_work_shift_1 = WorkShift::create($default_work_shift_data_1);




        $default_work_shift_1->details()->createMany($default_work_shift_data_1['details']);

        $employee_work_shift_history_data = $default_work_shift_1->toArray();
        $employee_work_shift_history_data["work_shift_id"] = $default_work_shift_1->id;
        $employee_work_shift_history_data["from_date"] = now();
        $employee_work_shift_history_data["to_date"] = NULL;
         $employee_work_shift_history =  WorkShiftHistory::create($employee_work_shift_history_data);
         $employee_work_shift_history->details()->createMany($default_work_shift_data_1['details']);

        $default_work_shift_data_2 = [
            'name' => 'main work shift',
            'type' => 'regular',
            'description' => '',
            'is_personal' => false,
            'break_type' => 'unpaid',
            'break_hours' => 1,

            "is_active" => 1,
            "is_default"=> 1,
            'details' => [
                [
                    'day' => '0',
                    'start_at' => '',
                    'end_at' => '',
                    'is_weekend' => 1,
                ],
                [
                    'day' => '1',
                    'start_at' => '',
                    'end_at' => '',
                    'is_weekend' => 1,
                ],
                [
                    'day' => '2',
                    'start_at' => '10:00:00',
                    'end_at' => '18:00:00',
                    'is_weekend' => 0,
                ],
                [
                    'day' => '3',
                    'start_at' => '10:00:00',
                    'end_at' => '18:00:00',
                    'is_weekend' => 0,
                ],
                [
                    'day' => '4',
                    'start_at' => '10:00:00',
                    'end_at' => '18:00:00',
                    'is_weekend' => 0,
                ],
                [
                    'day' => '5',
                    'start_at' => '10:00:00',
                    'end_at' => '18:00:00',
                    'is_weekend' => 0,
                ],
                [
                    'day' => '6',
                    'start_at' => '10:00:00',
                    'end_at' => '18:00:00',
                    'is_weekend' => 0,
                ],
            ],
        ];


                // $default_work_shift_2 = WorkShift::create($default_work_shift_data_2);
                // $default_work_shift_2->details()->createMany($default_work_shift_data_2['details']);

                ServicePlan::create([
                    "name" => "Standard Plan",
                    "description" => "",
                      'set_up_amount' => 100,
                      'duration_months' => 1,
                      'price' => 20,
                      'business_tier_id' => 1,
                      "created_by" => 1
                ]);

        return "You are done with setup";
    }


    public function roleRefresh(Request $request)
    {

        $this->storeActivity($request, "DUMMY activity","DUMMY description");




   // ###############################
        // permissions
        // ###############################
        $permissions =  config("setup-config.permissions");

        // setup permissions
        foreach ($permissions as $permission) {
            if(!Permission::where([
            'name' => $permission,
            'guard_name' => 'api'
            ])
            ->exists()){
                Permission::create(['guard_name' => 'api', 'name' => $permission]);
            }

        }
        // setup roles
        $roles = config("setup-config.roles");
        foreach ($roles as $role) {
            if(!Role::where([
            'name' => $role,
            'guard_name' => 'api',
            "is_system_default" => 1,
            "business_id" => NULL,
            "is_default" => 1,
            ])
            ->exists()){
             Role::create(['guard_name' => 'api', 'name' => $role,"is_system_default"=> 1, "business_id" => NULL,
             "is_default" => 1,
             "is_default_for_business" => (in_array($role ,["business_owner",
             "business_admin",
             "business_manager",
             "business_employee"])?1:0)


            ]);
            }
        }

// setup roles and permissions
// setup roles and permissions
$role_permissions = config("setup-config.roles_permission");
foreach ($role_permissions as $role_permission) {
    $role = Role::where(["name" => $role_permission["role"]])->first();

    $permissions = $role_permission["permissions"];

    // Get current permissions associated with the role
    $currentPermissions = $role->permissions()->pluck('name')->toArray();

    // Determine permissions to remove
    $permissionsToRemove = array_diff($currentPermissions, $permissions);

    // Deassign permissions not included in the configuration
    if (!empty($permissionsToRemove)) {
        foreach ($permissionsToRemove as $permission) {
            $role->revokePermissionTo($permission);
        }
    }

    // Assign permissions from the configuration
    $role->syncPermissions($permissions);
}

// $business_ids = Business::get()->pluck("id");


// foreach ($role_permissions as $role_permission) {

//     if($role_permission["role"] == "business_employee"){
//         foreach($business_ids as $business_id){

//             $role = Role::where(["name" => $role_permission["role"] . "#" . $business_id])->first();

//            if(empty($role)){

//             continue;
//            }

//                 $permissions = $role_permission["permissions"];

//                 // Assign permissions from the configuration
//     $role->syncPermissions($permissions);



//         }

//     }

//     if($role_permission["role"] == "business_manager"){
//         foreach($business_ids as $business_id){

//             $role = Role::where(["name" => $role_permission["role"] . "#" . $business_id])->first();

//            if(empty($role)){

//             continue;
//            }

//                 $permissions = $role_permission["permissions"];

//                 // Assign permissions from the configuration
//     $role->syncPermissions($permissions);



//         }

//     }



// }


        return "You are done with setup";
    }


    public function backup(Request $request) {
        $this->storeActivity($request, "DUMMY activity","DUMMY description");
        foreach(DB::connection('backup_database')->table('users')->get() as $backup_data){

        $data_exists = DB::connection('mysql')->table('users')->where([
            "id" => $backup_data->id
           ])->first();
           if(!$data_exists) {
            DB::connection('mysql')->table('users')->insert(get_object_vars($backup_data));
           }
        }


        // foreach(DB::connection('backup_database')->table('automobile_categories')->get() as $backup_data){
        //     $data_exists = DB::connection('mysql')->table('automobile_categories')->where([
        //         "id" => $backup_data->id
        //        ])->first();
        //        if(!$data_exists) {
        //         DB::connection('mysql')->table('automobile_categories')->insert(get_object_vars($backup_data));
        //        }
        //     }

        //     foreach(DB::connection('backup_database')->table('automobile_makes')->get() as $backup_data){
        //         $data_exists = DB::connection('mysql')->table('automobile_makes')->where([
        //             "id" => $backup_data->id
        //            ])->first();
        //            if(!$data_exists) {
        //             DB::connection('mysql')->table('automobile_makes')->insert(get_object_vars($backup_data));
        //            }
        //         }

        //         foreach(DB::connection('backup_database')->table('automobile_models')->get() as $backup_data){
        //             $data_exists = DB::connection('mysql')->table('automobile_models')->where([
        //                 "id" => $backup_data->id
        //                ])->first();
        //                if(!$data_exists) {
        //                 DB::connection('mysql')->table('automobile_models')->insert(get_object_vars($backup_data));
        //                }
        //             }

        //             foreach(DB::connection('backup_database')->table('services')->get() as $backup_data){
        //                 $data_exists = DB::connection('mysql')->table('services')->where([
        //                     "id" => $backup_data->id
        //                    ])->first();
        //                    if(!$data_exists) {
        //                     DB::connection('mysql')->table('services')->insert(get_object_vars($backup_data));
        //                    }
        //                 }


        //                 foreach(DB::connection('backup_database')->table('sub_services')->get() as $backup_data){
        //                     $data_exists = DB::connection('mysql')->table('sub_services')->where([
        //                         "id" => $backup_data->id
        //                        ])->first();
        //                        if(!$data_exists) {
        //                         DB::connection('mysql')->table('sub_services')->insert(get_object_vars($backup_data));
        //                        }
        //                     }



                            foreach(DB::connection('backup_database')->table('businesses')->get() as $backup_data){
                                $data_exists = DB::connection('mysql')->table('businesses')->where([
                                    "id" => $backup_data->id
                                   ])->first();
                                   if(!$data_exists) {
                                    DB::connection('mysql')->table('businesses')->insert(get_object_vars($backup_data));
                                   }
                                }

                                foreach(DB::connection('backup_database')->table('business_automobile_makes')->get() as $backup_data){
                                    $data_exists = DB::connection('mysql')->table('business_automobile_makes')->where([
                                        "id" => $backup_data->id
                                       ])->first();
                                       if(!$data_exists) {
                                        DB::connection('mysql')->table('business_automobile_makes')->insert(get_object_vars($backup_data));
                                       }
                                    }

                                    foreach(DB::connection('backup_database')->table('business_automobile_models')->get() as $backup_data){
                                        $data_exists = DB::connection('mysql')->table('business_automobile_models')->where([
                                            "id" => $backup_data->id
                                           ])->first();
                                           if(!$data_exists) {
                                            DB::connection('mysql')->table('business_automobile_models')->insert(get_object_vars($backup_data));
                                           }
                                        }

                                        foreach(DB::connection('backup_database')->table('business_services')->get() as $backup_data){
                                            $data_exists = DB::connection('mysql')->table('business_services')->where([
                                                "id" => $backup_data->id
                                               ])->first();
                                               if(!$data_exists) {
                                                DB::connection('mysql')->table('business_services')->insert(get_object_vars($backup_data));
                                               }
                                            }

                                            foreach(DB::connection('backup_database')->table('business_sub_services')->get() as $backup_data){
                                                $data_exists = DB::connection('mysql')->table('business_sub_services')->where([
                                                    "id" => $backup_data->id
                                                   ])->first();
                                                   if(!$data_exists) {
                                                    DB::connection('mysql')->table('business_sub_services')->insert(get_object_vars($backup_data));
                                                   }
                                                }
                                                foreach(DB::connection('backup_database')->table('fuel_stations')->get() as $backup_data){
                                                    $data_exists = DB::connection('mysql')->table('fuel_stations')->where([
                                                        "id" => $backup_data->id
                                                       ])->first();
                                                       if(!$data_exists) {
                                                        DB::connection('mysql')->table('fuel_stations')->insert(get_object_vars($backup_data));
                                                       }
                                                    }

                                                return response()->json("done",200);
    }

}
