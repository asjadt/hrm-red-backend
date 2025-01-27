<?php

namespace App\Http\Controllers;

use App\Http\Components\AuthorizationComponent;
use App\Http\Components\DepartmentComponent;
use App\Http\Utils\BasicUtil;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\Department;
use App\Models\Designation;
use App\Models\EmploymentStatus;
use App\Models\Role;
use App\Models\WorkLocation;
use App\Models\WorkShift;
use Exception;
use Illuminate\Http\Request;

class DropdownOptionsController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil, BasicUtil;



    protected $authorizationComponent;
    protected $departmentComponent;


    public function __construct(AuthorizationComponent $authorizationComponent,   DepartmentComponent $departmentComponent, )
    {
        $this->authorizationComponent = $authorizationComponent;
        $this->departmentComponent = $departmentComponent;

    }

 private function get_work_locations($business_created_by,$fields=[]) {
   $work_locations = WorkLocation::where(function($query) use($business_created_by) {
    $query->where('work_locations.business_id', NULL)
    ->where('work_locations.is_default', 1)
    ->where('work_locations.is_active', 1)
    ->whereDoesntHave("disabled", function($q) use($business_created_by) {
        $q->whereIn("disabled_work_locations.created_by", [$business_created_by]);
    })
    ->whereDoesntHave("disabled", function($q) use($business_created_by) {
        $q->whereIn("disabled_work_locations.business_id",[auth()->user()->business_id]);
    })



    ->orWhere(function ($query) use( $business_created_by){
        $query->where('work_locations.business_id', NULL)
            ->where('work_locations.is_default', 0)
            ->where('work_locations.created_by', $business_created_by)
            ->where('work_locations.is_active', 1)
            ->whereDoesntHave("disabled", function($q) {
                $q->whereIn("disabled_work_locations.business_id",[auth()->user()->business_id]);
            });
    })
    ->orWhere(function ($query)  {
        $query->where('work_locations.business_id', auth()->user()->business_id)
        ->where('work_locations.is_active', 1);

    });
})
->when(!empty($fields), function($query) use($fields) {
    $query->select($fields);
 })
        ->get();

        return $work_locations;
 }

 private function get_designations($business_created_by,$fields=[]) {
    $designations = Designation::where(function($query) use($business_created_by) {
     $query->where('designations.business_id', NULL)
     ->where('designations.is_default', 1)
     ->where('designations.is_active', 1)
     ->whereDoesntHave("disabled", function($q) use($business_created_by) {
         $q->whereIn("disabled_designations.created_by", [$business_created_by]);
     })
     ->whereDoesntHave("disabled", function($q) use($business_created_by) {
         $q->whereIn("disabled_designations.business_id",[auth()->user()->business_id]);
     })



     ->orWhere(function ($query) use( $business_created_by){
         $query->where('designations.business_id', NULL)
             ->where('designations.is_default', 0)
             ->where('designations.created_by', $business_created_by)
             ->where('designations.is_active', 1)
             ->whereDoesntHave("disabled", function($q) {
                 $q->whereIn("disabled_designations.business_id",[auth()->user()->business_id]);
             });
     })
     ->orWhere(function ($query)  {
         $query->where('designations.business_id', auth()->user()->business_id)
         ->where('designations.is_active', 1);

     });
 })
 ->when(!empty($fields), function($query) use($fields) {
    $query->select($fields);
 })
         ->get();

         return $designations;
  }

  private function get_employment_statuses($business_created_by,$fields=[]) {
    $employment_statuses = EmploymentStatus::where(function($query) use($business_created_by) {
     $query->where('employment_statuses.business_id', NULL)
     ->where('employment_statuses.is_default', 1)
     ->where('employment_statuses.is_active', 1)
     ->whereDoesntHave("disabled", function($q) use($business_created_by) {
         $q->whereIn("disabled_employment_statuses.created_by", [$business_created_by]);
     })
     ->whereDoesntHave("disabled", function($q) use($business_created_by) {
         $q->whereIn("disabled_employment_statuses.business_id",[auth()->user()->business_id]);
     })



     ->orWhere(function ($query) use( $business_created_by){
         $query->where('employment_statuses.business_id', NULL)
             ->where('employment_statuses.is_default', 0)
             ->where('employment_statuses.created_by', $business_created_by)
             ->where('employment_statuses.is_active', 1)
             ->whereDoesntHave("disabled", function($q) {
                 $q->whereIn("disabled_employment_statuses.business_id",[auth()->user()->business_id]);
             });
     })
     ->orWhere(function ($query)  {
         $query->where('employment_statuses.business_id', auth()->user()->business_id)
         ->where('employment_statuses.is_active', 1);

     });
 })
 ->when(!empty($fields), function($query) use($fields) {
    $query->select($fields);
 })
         ->get();

         return $employment_statuses;
  }


  private function get_work_shifts($all_manager_department_ids,$fields=[]) {
          $work_shifts = WorkShift::where(function($query) use($all_manager_department_ids) {
                    $query
                    ->where([
                        "work_shifts.business_id" => auth()->user()->business_id,
                        "work_shifts.is_active" => 1,
                    ])
                    ->whereHas("departments", function ($query) use ($all_manager_department_ids) {
                        $query->whereIn("departments.id", $all_manager_department_ids);
                    });

                })
                ->orWhere(function($query)  {
                    $query->where([
                        "is_active" => 1,
                        "business_id" => NULL,
                        "is_default" => 1
                    ]);

                })
                ->when(!empty($fields), function($query) use($fields) {
                    $query->select($fields);
                 })
            ->get();



         return $work_shifts;
  }
  private function get_roles($fields=[]) {

    $roles = Role::where("id",">",$this->getMainRoleId())
    ->where('business_id', auth()->user()->business_id)
    ->when(!empty($fields), function($query) use($fields) {
        $query->select($fields);
     })

                  ->get();

         return $roles;


  }


  private function get_departments($all_manager_department_ids,$fields=[]) {


    $departments = Department::where(
        [
            "business_id" => auth()->user()->business_id
        ]
    )
    ->whereIn("id",$all_manager_department_ids)
        ->where('departments.is_active', 1)
        ->when(!empty($fields), function($query) use($fields) {
           $query->select($fields);
        })
        ->get()
        ->map(function($record,$index) {
            if ($index === 0) {
                $record->is_current = true;
            }
            return $record;

        });

         return $departments;
  }





     public function getEmployeeFormDropdownData(Request $request)
     {
         try {
             $this->storeActivity($request, "DUMMY activity","DUMMY description");
             if (!$request->user()->hasPermissionTo('user_view')) {
                 return response()->json([
                     "message" => "You can not perform this action"
                 ], 401);
             }
             $user =  auth()->user();

             $business = $user->business;

             $business_created_by = $business->created_by;

             $all_manager_department_ids = $this->departmentComponent->get_all_departments_of_manager();

             $data["work_locations"] = $this->get_work_locations($business_created_by);
             $data["designations"] = $this->get_designations($business_created_by);
             $data["employment_statuses"] = $this->get_employment_statuses($business_created_by);

             $data["work_shifts"] = $this->get_work_shifts($all_manager_department_ids);
             $data["roles"] = $this->get_roles();
             $data["departments"] = $this->get_departments($all_manager_department_ids);



             return response()->json($data, 200);
         } catch (Exception $e) {

             return $this->sendError($e, 500, $request);
         }
     }


     public function getEmployeeFormDropdownDataV2(Request $request)
     {
         try {
             $this->storeActivity($request, "DUMMY activity","DUMMY description");
             if (!$request->user()->hasPermissionTo('user_view')) {
                 return response()->json([
                     "message" => "You can not perform this action"
                 ], 401);
             }
             $user =  auth()->user();
             $business = $user->business;
             $business_id =  $business->id;
             $business_created_by = $business->created_by;

             $all_manager_department_ids = $this->departmentComponent->get_all_departments_of_manager();


             $data["work_locations"] = $this->get_work_locations($business_created_by,[
                "id","name","business_id","is_active","is_default",
             ]);

             $data["designations"] = $this->get_designations($business_created_by,[
                 "designations.id","designations.name","designations.business_id","designations.is_active","designations.is_default",
             ]);

             $data["employment_statuses"] = $this->get_employment_statuses($business_created_by,[
                "employment_statuses.id",
                "employment_statuses.name",
                "employment_statuses.business_id",
                "employment_statuses.is_active",
                "employment_statuses.is_default",
                "employment_statuses.color",
             ]);

             $data["work_shifts"] = $this->get_work_shifts($all_manager_department_ids,[
                "id", "name", "business_id", "is_active", "is_default",
             ]);
             $data["roles"] = $this->get_roles([
                "roles.id","roles.name","roles.business_id","roles.is_active","roles.is_default","roles.is_default_for_business",
             ]);


             $data["departments"] = $this->get_departments($all_manager_department_ids,
             [
                "departments.id", "departments.name", 'departments.is_active', "departments.is_current", "departments.manager_id", "departments.parent_id", "departments.work_location_id"
             ]
            );



             return response()->json($data, 200);
         } catch (Exception $e) {

             return $this->sendError($e, 500, $request);
         }
     }



}
