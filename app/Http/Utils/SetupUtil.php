<?php

namespace App\Http\Utils;

use App\Models\AssetType;
use App\Models\Attendance;
use App\Models\AttendanceHistory;
use App\Models\Bank;
use App\Models\Business;
use App\Models\CandidateJobPlatform;
use App\Models\CandidateRecruitmentProcess;
use App\Models\Department;
use App\Models\Designation;
use App\Models\EmailTemplate;
use App\Models\EmploymentStatus;
use App\Models\JobListing;
use App\Models\JobListingJobPlatforms;
use App\Models\JobPlatform;
use App\Models\JobType;
use App\Models\Leave;
use App\Models\LeaveHistory;
use App\Models\LeaveTypeEmploymentStatus;
use App\Models\LetterTemplate;
use App\Models\Module;
use App\Models\Payslip;
use App\Models\RecruitmentProcess;
use App\Models\Role;
use App\Models\ServicePlan;
use App\Models\ServicePlanModule;
use App\Models\SettingLeaveType;
use App\Models\SettingPaidLeaveEmploymentStatus;
use App\Models\SettingUnpaidLeaveEmploymentStatus;
use App\Models\SocialSite;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\Termination;
use App\Models\TerminationReason;
use App\Models\TerminationType;
use App\Models\User;
use App\Models\UserRecruitmentProcess;
use App\Models\UserWorkLocation;
use App\Models\WorkLocation;
use App\Models\WorkShiftLocation;
use Exception;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;

trait SetupUtil
{
    use BasicEmailUtil;

    public function storeEmailTemplates()
    {
        $email_templates = [
            $this->prepareEmailTemplateData("business_welcome_mail", NULL),
            $this->prepareEmailTemplateData("email_verification_mail", NULL),
            $this->prepareEmailTemplateData("reset_password_mail", NULL),
            $this->prepareEmailTemplateData("send_password_mail", NULL),
            $this->prepareEmailTemplateData("job_application_received_mail", NULL),
        ];
        error_log("template creating 4");
        EmailTemplate::insert($email_templates);
    }

    public function setupRoles()
    {

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
            if (!Role::where([
                'name' => $role,
                'guard_name' => 'api',
                "is_system_default" => 1,
                "business_id" => NULL,
                "is_default" => 1,
            ])
                ->exists()) {
                Role::create([
                    'guard_name' => 'api',
                    'name' => $role,
                    "is_system_default" => 1,
                    "business_id" => NULL,
                    "is_default" => 1,
                    "is_default_for_business" => (in_array($role, [
                        "business_owner",
                        "business_admin",
                        "business_manager",
                        "business_employee"
                    ]) ? 1 : 0)


                ]);
            }
        }

        // setup roles and permissions
        $role_permissions = config("setup-config.roles_permission");
        foreach ($role_permissions as $role_permission) {
            $role = Role::where(["name" => $role_permission["role"]])->first();
            // error_log($role_permission["role"]);
            $permissions = $role_permission["permissions"];
            $role->syncPermissions($permissions);
            // foreach ($permissions as $permission) {
            //     if(!$role->hasPermissionTo($permission)){
            //         $role->givePermissionTo($permission);
            //     }


            // }
        }
    }

    public function setupAssetTypes()
    {

        $asset_types = [
            ['name' => 'Mobile Phone'],
            ['name' => 'Laptop']
        ];

        // Iterate through the array and create records only if they do not already exist
        foreach ($asset_types as $data) {
            // Check if a record with all the specified attributes exists
            $exists = AssetType::where([
                'name' => $data['name'],
                'is_active' => 1,
                'is_default' => 1,
                'business_id' => NULL,
                'created_by' => 1
            ])->exists();

            // Create the record if it does not exist
            if (!$exists) {
                AssetType::create([
                    'name' => $data['name'],
                    'is_active' => 1,
                    'is_default' => 1,
                    'business_id' => NULL,
                    'created_by' => 1
                ]);
            }
        }
    }

    public function setUpSocialMedia()
    {
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
                "created_by" => 1
            ]);
        }
    }
    public function roleRefreshFunc()
    {


        // ###############################
        // permissions
        // ###############################
        $permissions =  config("setup-config.permissions");

        // setup permissions
        foreach ($permissions as $permission) {
            if (!Permission::where([
                'name' => $permission,
                'guard_name' => 'api'
            ])
                ->exists()) {
                Permission::create(['guard_name' => 'api', 'name' => $permission]);
            }
        }
        // setup roles
        $roles = config("setup-config.roles");
        foreach ($roles as $role) {
            if (!Role::where([
                'name' => $role,
                'guard_name' => 'api',
                "is_system_default" => 1,
                "business_id" => NULL,
                "is_default" => 1,
            ])
                ->exists()) {
                Role::create([
                    'guard_name' => 'api',
                    'name' => $role,
                    "is_system_default" => 1,
                    "business_id" => NULL,
                    "is_default" => 1,
                    "is_default_for_business" => (in_array($role, [
                        "business_owner",
                        "business_admin",
                        "business_manager",
                        "business_employee"
                    ]) ? 1 : 0)


                ]);
            }
        }


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


        $business_ids = Business::get()->pluck("id");

        foreach ($role_permissions as $role_permission) {

            // if($role_permission["role"] == "business_manager"){

                foreach($business_ids as $business_id){

                    $role = Role::where(["name" => $role_permission["role"] . "#" . $business_id])->first();

                   if(empty($role)){

                    continue;
                   }

                        $permissions = $role_permission["permissions"];

                        // Assign permissions from the configuration
            $role->syncPermissions($permissions);

                }

            // }



        }
    }



    public function setupServicePlan()
    {
        $modules = Module::where('is_enabled', 1)->pluck('id');

        $service_plan = ServicePlan::create([
            'name' => 'Standard Plan',
            'description' => '',
            'set_up_amount' => 100,
            'number_of_employees_allowed' => 100,
            'duration_months' => 1,
            'price' => 20,
            'business_tier_id' => 1,
            'created_by' => 1,
        ]);

        $service_plan_modules = $modules->map(function ($module_id) use ($service_plan) {
            return [
                'is_enabled' => 1,
                'service_plan_id' => $service_plan->id,
                'module_id' => $module_id,
                'created_by' => auth()->id(),
            ];
        })->toArray();

        // ServicePlanModule::insert($service_plan_modules);
    }

    public function storeWorkLocation()
    {
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
                "created_by" => 1
            ]);
        }
    }




    public function loadDefaultData($business, $defaultData, $modelClass)
    {
        if($modelClass == "App\Models\JobPlatform#"){
            echo $modelClass . " -----------------<br>";
        }

        foreach ($defaultData as $data) {
            if($modelClass == "App\Models\JobPlatform#"){
                echo " $$$$$$$$$$$$$$$$$$$$$$$$<br>" . json_encode($data) . "<br>" ;
            }
            $existingData = $modelClass::where([
                "business_id" => $business->id,
            ])
                ->where(function ($query) use ($data) {
                    $query->where("parent_id", $data->id)
                    ->orWhere(function($query) use($data) {
                        $query->where("parent_id", NULL)
                        ->where("name", $data->name);
                    });
                })
                ->first();

                if($modelClass == "App\Models\JobPlatform#"){
                    echo " #######################<br>" . json_encode($existingData) . "<br>" ;
                }

            if (empty($existingData)) {
                $data = $data->toArray();
                $data['is_active'] = 1;
                $data['is_default'] = 0; // Example modification
                $data['business_id'] = $business->id;
                $data['created_by'] = $business->owner_id;
                $data['parent_id'] = $data["id"];

                // Another example modification

                // Create the model with modified data
                $modelClass::create($data);
            } else {
                $existingData->parent_id = $data->id;
                $existingData->save();
            }
        }
    }

    public function getDefaultData($modelClass)
    {
        return $modelClass::where([
            "is_active" => 1,
            "is_default" => 1,
            "business_id" => NULL,
            "parent_id" => NULL
        ])->get();
    }

    public $defaultModels = [
        AssetType::class,
        Bank::class,
        Designation::class,
        EmploymentStatus::class,
        JobPlatform::class,
        JobType::class,
        // LetterTemplate::class,
        RecruitmentProcess::class,
        TaskCategory::class,
        // TerminationReason::class,
        // TerminationType::class,
        WorkLocation::class,
        SettingLeaveType::class,
    ];

    public function defaultDataSetupForBusiness($businesses)
    {

        foreach ($businesses as $business) {
            Log::info("business loop->" . $business->id);

            foreach ($this->defaultModels as $model) {
                $defaultData = $this->getDefaultData($model);

                $this->loadDefaultData($business, $defaultData, $model);

            }

        }



    }




    public function defaultDataSetupForBusinessV2($businesses, $service_plan)
    {

        foreach ($businesses as $business) {
            Log::info("business loop->" . $business->id);

            foreach ($this->defaultModels as $model) {
                $defaultData = $this->getDefaultData($model);

                $this->loadDefaultData($business, $defaultData, $model);

                // Handle updates for each model
                $this->updateModelData($business, $defaultData, $model);
            }



            if(empty($business->service_plan_id)) {
                $business->service_plan_id = $service_plan->id;
            }
            if(empty($business->reseller_id)){
                $business->reseller_id = $business->created_by;
            }

            $business->save();


        }



    }

    protected function updateModelData($business, $defaultData, $modelClass)
    {
        switch ($modelClass) {
            case AssetType::class:
                $this->updateAssetTypeData($business);
                break;

            case Bank::class:
                $this->updateBankData($business, $defaultData);
                break;

            case Designation::class:
                $this->updateDesignationData($business, $defaultData);
                break;

            case EmploymentStatus::class:
                $this->updateEmploymentStatusData($business, $defaultData);
                break;

            case JobPlatform::class:
                $this->updateJobPlatformData($business, $defaultData);
                break;

            case JobType::class:
                $this->updateJobTypeData($business, $defaultData);
                break;

            // case LetterTemplate::class:
            //     $this->updateLetterTemplateData($business);
            //     break;

            case RecruitmentProcess::class:
                $this->updateRecruitmentProcessData($business, $defaultData);
                break;

            case TaskCategory::class:
                $this->updateTaskCategoryData($business, $defaultData);
                break;

            // case TerminationReason::class:
            //     $this->updateTerminationReasonData($business, $defaultData);
            //     break;

            // case TerminationType::class:
            //     $this->updateTerminationTypeData($business, $defaultData);
            //     break;

            case WorkLocation::class:
                $this->updateWorkLocationData($business, $defaultData);
                break;

            case SettingLeaveType::class:
                $this->updateSettingLeaveTypeData($business, $defaultData);
                break;

            default:
                // Handle default case if necessary
                break;
        }
    }


    protected function updateAssetTypeData($business)
    {
        // Implement the logic to update AssetType data


    }

    protected function updateBankData($business, $defaultData)
    {

        // Retrieve all banks related to the business
        $business_data = Bank::where("business_id", $business->id)->pluck('id', 'parent_id')->toArray();

        // Retrieve users with bank_ids that exist in the $defaultData
        $default_data_ids = collect($defaultData)->pluck("id")->toArray();

        $users = User::where("business_id", $business->id)
            ->whereIn("bank_id", $default_data_ids)
            ->get(["id", "bank_id"]);

        // Update bank_id for each user
        foreach ($users as $user) {
            // Get the new bank_id based on the parent_id
            $newDataId = $business_data[$user->bank_id] ?? null;

            if ($newDataId === null) {
                throw new Exception("Bank ID for parent ID " . $user->bank_id . " not found.");
            }

            $user->bank_id = $newDataId;
            $user->save();
        }

        $payslips = Payslip::whereHas("user", function ($query) use ($business) {
            $query->where("users.business_id", $business->id);
        })
            ->whereIn("bank_id", $default_data_ids)
            ->get();

        // Update bank_id for each payslip
        foreach ($payslips as $payslip) {
            // Get the new bank_id for the payslip
            $newDataId = $business_data[$payslip->bank_id] ?? null;

            if ($newDataId === null) {
                throw new Exception("Bank ID for payslip with bank_id " . $payslip->bank_id . " not found.");
            }

            // Update bank_id in the Payslip model
            $payslip->bank_id = $newDataId;
            $payslip->save();
        }
    }



    protected function updateDesignationData($business, $defaultData)
    {
        // Retrieve all banks related to the business
        $business_data = Designation::where("business_id", $business->id)->pluck('id', 'parent_id')->toArray();

        // Retrieve users with bank_ids that exist in the $defaultData
        $default_data_ids = collect($defaultData)->pluck("id")->toArray();

        $users = User::where("business_id", $business->id)
            ->whereIn("designation_id", $default_data_ids)
            ->get(["id", "designation_id"]);

        // Update bank_id for each user
        foreach ($users as $user) {
            // Get the new bank_id based on the parent_id
            $newDataId = $business_data[$user->designation_id] ?? null;

            if ($newDataId === null) {
                throw new Exception("Designation ID for parent ID " . $user->designation_id . " not found.");
            }

            $user->designation_id = $newDataId;
            $user->save();
        }
    }

    protected function updateEmploymentStatusData($business, $defaultData)
    {
        // Retrieve all banks related to the business
        $business_data = EmploymentStatus::where("business_id", $business->id)->pluck('id', 'parent_id')->toArray();

        // Retrieve users with bank_ids that exist in the $defaultData
        $default_data_ids = collect($defaultData)->pluck("id")->toArray();


        $users = User::where("business_id", $business->id)
            ->whereIn("employment_status_id", $default_data_ids)
            ->get(["id", "employment_status_id"]);

        // Update bank_id for each user
        foreach ($users as $user) {
            // Get the new bank_id based on the parent_id
            $newDataId = $business_data[$user->employment_status_id] ?? null;

            if ($newDataId === null) {
                throw new Exception("Employment Status ID for parent ID " . $user->employment_status_id . " not found.");
            }

            $user->employment_status_id = $newDataId;
            $user->save();
        }

        $settingPaidLeaveEmploymentStatuses = SettingPaidLeaveEmploymentStatus::whereHas("setting_leave", function ($query) use ($business) {
            $query->where("setting_leaves.business_id", $business->id);
        })
            ->whereIn("employment_status_id", $default_data_ids)
            ->get(["id", "employment_status_id"]);

        // Update bank_id for each user
        foreach ($settingPaidLeaveEmploymentStatuses as $settingPaidLeaveEmploymentStatus) {
            // Get the new bank_id based on the parent_id
            $newDataId = $business_data[$settingPaidLeaveEmploymentStatus->employment_status_id] ?? null;

            if ($newDataId === null) {
                throw new Exception("Employment Status ID for parent ID " . $settingPaidLeaveEmploymentStatus->employment_status_id . " not found.");
            }

            $settingPaidLeaveEmploymentStatus->employment_status_id = $newDataId;
            $settingPaidLeaveEmploymentStatus->save();
        }

        $settingUnpaidLeaveEmploymentStatuses = SettingUnpaidLeaveEmploymentStatus::whereHas("setting_leave", function ($query) use ($business) {
            $query->where("setting_leaves.business_id", $business->id);
        })
            ->whereIn("employment_status_id", $default_data_ids)
            ->get(["id", "employment_status_id"]);

        // Update bank_id for each user
        foreach ($settingUnpaidLeaveEmploymentStatuses as $settingUnpaidLeaveEmploymentStatus) {
            // Get the new bank_id based on the parent_id
            $newDataId = $business_data[$settingUnpaidLeaveEmploymentStatus->employment_status_id] ?? null;

            if ($newDataId === null) {
                throw new Exception("Employment Status ID for parent ID " . $settingUnpaidLeaveEmploymentStatus->employment_status_id . " not found.");
            }

            $settingUnpaidLeaveEmploymentStatus->employment_status_id = $newDataId;
            $settingUnpaidLeaveEmploymentStatus->save();
        }

        $leaveTypeEmploymentStatuses = LeaveTypeEmploymentStatus::whereHas("setting_leave_type", function ($query) use ($business) {
            $query->where("setting_leave_types.business_id", $business->id);
        })
            ->whereIn("employment_status_id", $default_data_ids)
            ->get(["id", "employment_status_id"]);

        // Update bank_id for each user
        foreach ($leaveTypeEmploymentStatuses as $leaveTypeEmploymentStatus) {
            // Get the new bank_id based on the parent_id
            $newDataId = $business_data[$leaveTypeEmploymentStatus->employment_status_id] ?? null;

            if ($newDataId === null) {
                throw new Exception("Employment Status ID for parent ID " . $leaveTypeEmploymentStatus->employment_status_id . " not found.");
            }

            $leaveTypeEmploymentStatus->employment_status_id = $newDataId;
            $leaveTypeEmploymentStatus->save();
        }
    }

    protected function updateJobPlatformData($business, $defaultData)
    {

        // job_listing_job_platforms, candidate_job_platforms,


        $business_data = JobPlatform::where("business_id", $business->id)->pluck('id', 'parent_id')->toArray();


        $default_data_ids = collect($defaultData)->pluck("id")->toArray();

        $job_listing_job_platforms = JobListingJobPlatforms::whereHas("job_listing", function ($query) use ($business) {
                $query->where("job_listings.business_id", $business->id);
            })
            ->whereIn("job_platform_id", $default_data_ids)
            ->get(["id", "job_platform_id"]);



        foreach ($job_listing_job_platforms as $job_listing_job_platform) {

            $newDataId = $business_data[$job_listing_job_platform->job_platform_id] ?? null;

            if ($newDataId === null) {
                throw new Exception("job platform ID for parent ID " . $job_listing_job_platform->job_platform_id . " not found.");
            }

            $job_listing_job_platform->job_platform_id = $newDataId;
            $job_listing_job_platform->save();
        }


        $candidate_job_platforms = CandidateJobPlatform::whereHas("candidate", function ($query) use ($business) {
                $query->where("candidates.business_id", $business->id);
            })
            ->whereIn("job_platform_id", $default_data_ids)
            ->get(["id", "job_platform_id"]);


        foreach ($candidate_job_platforms as $candidate_job_platform) {

            $newDataId = $business_data[$candidate_job_platform->job_platform_id] ?? null;

            if ($newDataId === null) {
                throw new Exception("job platform ID for parent ID " . $candidate_job_platform->job_platform_id . " not found.");
            }

            $candidate_job_platform->job_platform_id = $newDataId;
            $candidate_job_platform->save();
        }
    }

    protected function updateJobTypeData($business, $defaultData)
    {
        // job_listings,


        $business_data = JobType::where("business_id", $business->id)->pluck('id', 'parent_id')->toArray();


        $default_data_ids = collect($defaultData)->pluck("id")->toArray();

        $job_listings = JobListing::where("business_id", $business->id)
            ->whereIn("job_type_id", $default_data_ids)
            ->get(["id", "job_type_id"]);


        foreach ($job_listings as $job_listing) {

            $newDataId = $business_data[$job_listing->job_type_id] ?? null;

            if ($newDataId === null) {
                throw new Exception("job type ID for parent ID " . $job_listing->job_type_id . " not found.");
            }

            $job_listing->job_type_id = $newDataId;
            $job_listing->save();
        }
    }

    protected function updateLetterTemplateData($business) {}

    protected function updateRecruitmentProcessData($business, $defaultData)
    {
        // users * recruitment_process_id,
        // user_recruitment_processes *recruitment_process_id,
        // candidate_recruitment_processes *recruitment_process_id,

        $business_data = RecruitmentProcess::where("business_id", $business->id)->pluck('id', 'parent_id')->toArray();


        $default_data_ids = collect($defaultData)->pluck("id")->toArray();

        $users = User::where("business_id", $business->id)
            ->whereIn("recruitment_process_id", $default_data_ids)
            ->get(["id", "recruitment_process_id"]);


        foreach ($users as $user) {

            $newDataId = $business_data[$user->recruitment_process_id] ?? null;

            if ($newDataId === null) {
                throw new Exception("recruitment process ID for parent ID " . $user->recruitment_process_id . " not found.");
            }

            $user->recruitment_process_id = $newDataId;
            $user->save();
        }

        $user_recruitment_processes = UserRecruitmentProcess::whereHas("user", function ($query) use ($business) {
                $query->where("users.business_id", $business->id);
            })
            ->whereIn("recruitment_process_id", $default_data_ids)
            ->get(["id", "recruitment_process_id"]);


        foreach ($user_recruitment_processes as $user_recruitment_process) {

            $newDataId = $business_data[$user_recruitment_process->recruitment_process_id] ?? null;

            if ($newDataId === null) {
                throw new Exception("recruitment process ID for parent ID " . $user_recruitment_process->recruitment_process_id . " not found.");
            }

            $user_recruitment_process->recruitment_process_id = $newDataId;
            $user_recruitment_process->save();
        }



        $candidate_recruitment_processes = CandidateRecruitmentProcess::whereHas("candidate", function ($query) use ($business) {
                $query->where("candidates.business_id", $business->id);
            })
            ->whereIn("recruitment_process_id", $default_data_ids)
            ->get(["id", "recruitment_process_id"]);


        foreach ($candidate_recruitment_processes as $candidate_recruitment_process) {

            $newDataId = $business_data[$candidate_recruitment_process->recruitment_process_id] ?? null;

            if ($newDataId === null) {
                throw new Exception("recruitment process ID for parent ID " . $candidate_recruitment_process->recruitment_process_id . " not found.");
            }

            $candidate_recruitment_process->recruitment_process_id = $newDataId;
            $candidate_recruitment_process->save();
        }
    }








    protected function updateTaskCategoryData($business, $defaultData)
    {
        // tasks * task_category_id ,


        $business_data = TaskCategory::where("business_id", $business->id)->pluck('id', 'parent_id')->toArray();


        $default_data_ids = collect($defaultData)->pluck("id")->toArray();

        $tasks = Task::where("business_id", $business->id)
            ->whereIn("task_category_id", $default_data_ids)
            ->get(["id", "task_category_id"]);


        foreach ($tasks as $task) {

            $newDataId = $business_data[$task->task_category_id] ?? null;

            if ($newDataId === null) {
                throw new Exception("task category ID for parent ID " . $task->task_category_id . " not found.");
            }

            $task->task_category_id = $newDataId;
            $task->save();
        }
    }

    // protected function updateTerminationReasonData($business, $defaultData)
    // {
    //     // terminations * termination_reason_id ,


    //     $business_data = TerminationReason::where("business_id", $business->id)->pluck('id', 'parent_id')->toArray();

    //     $default_data_ids = collect($defaultData)->pluck("id")->toArray();

    //     $terminations = Termination::whereHas("user", function ($query) use ($business) {
    //             $query->where("users.business_id", $business->id);
    //         })
    //         ->whereIn("termination_reason_id", $default_data_ids)
    //         ->get(["id", "termination_reason_id"]);


    //     foreach ($terminations as $termination) {

    //         $newDataId = $business_data[$termination->termination_reason_id] ?? null;

    //         if ($newDataId === null) {
    //             throw new Exception("termination reason ID for parent ID " . $termination->termination_reason_id . " not found.");
    //         }

    //         $termination->termination_reason_id = $newDataId;
    //         $termination->save();
    //     }
    // }


    // protected function updateTerminationTypeData($business, $defaultData)
    // {
    //     // terminations * termination_type_id ,

    //     $business_data = TerminationType::where("business_id", $business->id)->pluck('id', 'parent_id')->toArray();

    //     $default_data_ids = collect($defaultData)->pluck("id")->toArray();

    //     $terminations = Termination::whereHas("user", function ($query) use ($business) {
    //             $query->where("users.business_id", $business->id);
    //         })
    //         ->whereIn("termination_type_id", $default_data_ids)
    //         ->get(["id", "termination_type_id"]);


    //     foreach ($terminations as $termination) {

    //         $newDataId = $business_data[$termination->termination_type_id] ?? null;

    //         if ($newDataId === null) {
    //             throw new Exception("termination type ID for parent ID " . $termination->termination_type_id . " not found.");
    //         }

    //         $termination->termination_type_id = $newDataId;
    //         $termination->save();
    //     }
    // }

    protected function updateWorkLocationData($business, $defaultData)
    {
        // departments * work_location_id ,
        // attendances * work_location_id ,
        // attendance_histories * work_location_id ,
        // job_listings * work_location_id ,
        // user_work_locations * work_location_id ,
        // work_shift_locations * work_location_id ,


        $business_data = WorkLocation::where("business_id", $business->id)->pluck('id', 'parent_id')->toArray();

        $default_data_ids = collect($defaultData)->pluck("id")->toArray();

        $departments = Department::where("business_id", $business->id)
            ->whereIn("work_location_id", $default_data_ids)
            ->get(["id", "work_location_id"]);


        foreach ($departments as $department) {

            $newDataId = $business_data[$department->work_location_id] ?? null;

            if ($newDataId === null) {
                throw new Exception("Work Location ID for parent ID " . $department->work_location_id . " not found.");
            }

            $department->work_location_id = $newDataId;
            $department->save();
        }

        $attendances = Attendance::where(function ($query) use ($business) {
                $query->where("business_id", $business->id)
                    ->orWhereHas("employee", function ($query) use ($business) {
                        $query->where("users.business_id", $business->id);
                    });
            })

            ->whereIn("work_location_id", $default_data_ids)
            ->get(["id", "work_location_id"]);


        foreach ($attendances as $attendance) {

            $newDataId = $business_data[$attendance->work_location_id] ?? null;

            if ($newDataId === null) {
                throw new Exception("Work Location ID for parent ID " . $attendance->work_location_id . " not found.");
            }

            $attendance->work_location_id = $newDataId;
            $attendance->save();
        }

        $attendance_histories = AttendanceHistory::where(function ($query) use ($business) {
                $query->where("business_id", $business->id)
                    ->orWhereHas("employee", function ($query) use ($business) {
                        $query->where("users.business_id", $business->id);
                    });
            })

            ->whereIn("work_location_id", $default_data_ids)
            ->get(["id", "work_location_id"]);


        foreach ($attendance_histories as $attendance_history) {

            $newDataId = $business_data[$attendance_history->work_location_id] ?? null;

            if ($newDataId === null) {
                throw new Exception("Work Location ID for parent ID " . $attendance_history->work_location_id . " not found.");
            }

            $attendance_history->work_location_id = $newDataId;
            $attendance_history->save();
        }

        $job_listings = JobListing::where("business_id", $business->id)
            ->whereIn("work_location_id", $default_data_ids)
            ->get(["id", "work_location_id"]);


        foreach ($job_listings as $job_listing) {

            $newDataId = $business_data[$job_listing->work_location_id] ?? null;

            if ($newDataId === null) {
                throw new Exception("job type ID for parent ID " . $job_listing->work_location_id . " not found.");
            }

            $job_listing->work_location_id = $newDataId;
            $job_listing->save();
        }

        $user_work_locations = UserWorkLocation::whereHas("user", function ($query) use ($business) {
                $query->where("users.business_id", $business->id);
            })
            ->whereIn("work_location_id", $default_data_ids)
            ->get(["id", "work_location_id"]);


        foreach ($user_work_locations as $user_work_location) {

            $newDataId = $business_data[$user_work_location->work_location_id] ?? null;

            if ($newDataId === null) {
                throw new Exception("termination type ID for parent ID " . $user_work_location->work_location_id . " not found.");
            }

            $user_work_location->work_location_id = $newDataId;
            $user_work_location->save();
        }




        $work_shift_locations = WorkShiftLocation::whereHas("work_shift", function ($query) use ($business) {
                $query->where("work_shifts.business_id", $business->id);
            })
            ->whereIn("work_location_id", $default_data_ids)
            ->get(["id", "work_location_id"]);


        foreach ($work_shift_locations as $work_shift_location) {

            $newDataId = $business_data[$work_shift_location->work_location_id] ?? null;

            if ($newDataId === null) {
                throw new Exception("termination type ID for parent ID " . $work_shift_location->work_location_id . " not found.");
            }

            $work_shift_location->work_location_id = $newDataId;
            $work_shift_location->save();
        }
    }

    protected function updateSettingLeaveTypeData($business, $defaultData)
    {
        // leaves * leave_type_id ,
        // leave_histories * leave_type_id ,
        // leave_type_employment_statuses * setting_leave_type_id ,


        $business_data = SettingLeaveType::where("business_id", $business->id)->pluck('id', 'parent_id')->toArray();

        $default_data_ids = collect($defaultData)->pluck("id")->toArray();

        $leaves = Leave::where(function ($query) use ($business) {
                $query->where("business_id", $business->id)
                    ->orWhereHas("employee", function ($query) use ($business) {
                        $query->where("users.business_id", $business->id);
                    });
            })

            ->whereIn("leave_type_id", $default_data_ids)
            ->get(["id", "leave_type_id"]);


        foreach ($leaves as $leave) {

            $newDataId = $business_data[$leave->leave_type_id] ?? null;

            if ($newDataId === null) {
                throw new Exception("Leave type ID for parent ID " . $leave->leave_type_id . " not found.");
            }

            $leave->leave_type_id = $newDataId;
            $leave->save();
        }

        $leave_histories = LeaveHistory::where(function ($query) use ($business) {
                $query->where("business_id", $business->id)
                    ->orWhereHas("employee", function ($query) use ($business) {
                        $query->where("users.business_id", $business->id);
                    });
            })

            ->whereIn("leave_type_id", $default_data_ids)
            ->get(["id", "leave_type_id"]);


        foreach ($leave_histories as $leave_history) {

            $newDataId = $business_data[$leave_history->leave_type_id] ?? null;

            if ($newDataId === null) {
                throw new Exception("Leave type ID for parent ID " . $leave_history->leave_type_id . " not found.");
            }

            $leave_history->leave_type_id = $newDataId;
            $leave_history->save();
        }




        $leaveTypeEmploymentStatuses = LeaveTypeEmploymentStatus::whereHas("setting_leave_type", function ($query) use ($business) {
            $query->where("setting_leave_types.business_id", $business->id);
        })
            ->whereIn("setting_leave_type_id", $default_data_ids)
            ->get(["id", "setting_leave_type_id"]);

        // Update bank_id for each user
        foreach ($leaveTypeEmploymentStatuses as $leaveTypeEmploymentStatus) {
            // Get the new bank_id based on the parent_id
            $newDataId = $business_data[$leaveTypeEmploymentStatus->setting_leave_type_id] ?? null;

            if ($newDataId === null) {
                throw new Exception("leave type ID for parent ID " . $leaveTypeEmploymentStatus->setting_leave_type_id . " not found.");
            }

            $leaveTypeEmploymentStatus->setting_leave_type_id = $newDataId;
            $leaveTypeEmploymentStatus->save();
        }
    }
}
