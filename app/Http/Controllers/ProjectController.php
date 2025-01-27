<?php

namespace App\Http\Controllers;

use App\Exports\ProjectsExport;
use App\Http\Components\ProjectComponent;
use App\Http\Requests\ProjectAssignToUserRequest;
use App\Http\Requests\ProjectCreateRequest;
use App\Http\Requests\ProjectUpdateRequest;
use App\Http\Requests\UserAssignToProjectRequest;
use App\Http\Utils\BasicUtil;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\ModuleUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\AttendanceProject;
use App\Models\Department;
use App\Models\EmployeeProjectHistory;
use App\Models\Project;
use App\Models\User;
use App\Models\UserProject;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use PDF;
use Maatwebsite\Excel\Facades\Excel;

class ProjectController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil,ModuleUtil, BasicUtil;


     protected $projectComponent;


    public function __construct(ProjectComponent $projectComponent)
    {
        $this->projectComponent = $projectComponent;

    }




    public function createProject(ProjectCreateRequest $request)
    {
        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");



                if (!$request->user()->hasPermissionTo('project_create')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }

                $request_data = $request->validated();


                $request_data["business_id"] = $request->user()->business_id;
                $request_data["is_active"] = true;
                $request_data["is_default"] = false;

                $request_data["created_by"] = auth()->user()->id;

                $project =  Project::create($request_data);

                if(empty($request_data['departments'])) {
                    $request_data['departments'] = [Department::where("business_id",auth()->user()->business_id)->whereNull("parent_id")->first()->id];
                }


                $project->departments()->sync($request_data['departments']);




                DB::commit();
                return response($project, 201);

        } catch (Exception $e) {
            DB::rollBack();
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }


     public function assignUser(UserAssignToProjectRequest $request)
     {

        DB::beginTransaction();
         try {
             $this->storeActivity($request, "DUMMY activity","DUMMY description");



                 if (!$request->user()->hasPermissionTo('project_update')) {
                     return response()->json([
                         "message" => "You can not perform this action"
                     ], 401);
                 }
                 $business_id =  $request->user()->business_id;
                 $request_data = $request->validated();




                 $project_query_params = [
                     "id" => $request_data["id"],
                     "business_id" => $business_id
                 ];


                 $project  =  Project::where($project_query_params)
                     ->first();


                 if (!$project) {
                     return response()->json([
                         "message" => "something went wrong."
                     ], 500);
                 }




                //  $discharged_users =  User::whereHas("projects",function($query) use($project){
                //     $query->where("users.id",$project->id);
                //  })
                //  ->whereIn("id",$request_data['users'])
                //  ->get();



                //  EmployeeProjectHistory::where([
                //     "project_id" => $project->id,
                //     "to_date" => NULL
                //  ])
                //  ->whereIn("project_id",$discharged_users->pluck("id"))
                //  ->update([
                //     "to_date" => now()
                //  ])
                //  ;


                 foreach($request_data['users'] as $index=>$user_id) {
                  $user = User::
                  whereHas("projects",function($query) use($project){
                    $query->where("projects.id",$project->id);
                 })
                   ->where([
                    "id" => $user_id
                   ])
                    ->first();

                    if($user) {

                            $error = [ "message" => "The given data was invalid.",
                                       "errors" => [("users.".$index)=>["The project is already belongs to that user."]]
                                       ];
                                           throw new Exception(json_encode($error),422);


                    }




                        $user = User::where([
                           "id" => $user_id
                        ])
                        ->first();

                        if(!$user) {
                            throw new Exception("some thing went wrong");
                        }

                        // UserProject::create([
                        //     "user_id" => $user->id,
                        //     "project_id" => $project->id
                        // ]);



          $employee_project_history_data = $project->toArray();
          $employee_project_history_data["user_id"] = $user->id;
          $employee_project_history_data["project_id"] = $employee_project_history_data["id"];
          $employee_project_history_data["from_date"] = now();
          $employee_project_history_data["to_date"] = NULL;

          EmployeeProjectHistory::create($employee_project_history_data);





                 }


                 $project->users()->attach($request_data['users']);

                 DB::commit();
                 return response($project, 201);


         } catch (Exception $e) {
            DB::rollBack();
             return $this->sendError($e, 500, $request);
         }
     }

     public function dischargeUser(UserAssignToProjectRequest $request)
     {

        DB::beginTransaction();
         try {
             $this->storeActivity($request, "DUMMY activity","DUMMY description");



                 if (!$request->user()->hasPermissionTo('project_update')) {
                     return response()->json([
                         "message" => "You can not perform this action"
                     ], 401);
                 }
                 $business_id =  $request->user()->business_id;
                 $request_data = $request->validated();




                 $project_query_params = [
                     "id" => $request_data["id"],
                     "business_id" => $business_id
                 ];


                 $project  =  Project::where($project_query_params)
                     ->first();


                 if (!$project) {
                     return response()->json([
                         "message" => "something went wrong."
                     ], 500);
                 }




                 $discharged_users =  User::whereHas("projects",function($query) use($project){
                    $query->where("users.id",$project->id);
                 })
                 ->whereIn("id",$request_data['users'])
                 ->get();



                 EmployeeProjectHistory::where([
                    "project_id" => $project->id,
                    "to_date" => NULL
                 ])
                 ->whereIn("project_id",$discharged_users->pluck("id"))
                 ->update([
                    "to_date" => now()
                 ])
                 ;


                 foreach($request_data['users'] as $index=>$user_id) {
                  $user = User::
                  whereHas("projects",function($query) use($project){
                    $query->where("projects.id",$project->id);
                 })
                   ->where([
                    "id" => $user_id
                   ])
                    ->first();

                    if(!$user) {

                        $error = [ "message" => "The given data was invalid.",
                                   "errors" => [("projects.".$index)=>["The project is already belongs to that user."]]
                                   ];
                                       throw new Exception(json_encode($error),422);

                           }



                 }


                 $project->users()->detach($request_data['users']);

                 DB::commit();
                 return response($project, 201);


         } catch (Exception $e) {
          DB::rollBack();
             return $this->sendError($e, 500, $request);
         }
     }

     public function assignProject(ProjectAssignToUserRequest $request)
     {

        DB::beginTransaction();
         try {
             $this->storeActivity($request, "DUMMY activity","DUMMY description");


                 if (!$request->user()->hasPermissionTo('project_update')) {
                     return response()->json([
                         "message" => "You can not perform this action"
                     ], 401);
                 }
                 $business_id =  $request->user()->business_id;
                 $request_data = $request->validated();

                 $user_query_params = [
                     "id" => $request_data["id"],
                 ];


                 $user  =  User::where($user_query_params)
                     ->first();


                 if (!$user) {
                     return response()->json([
                         "message" => "something went wrong."
                     ], 500);
                 }

              // need this in remove api
                //  $discharged_projects =  Project::whereHas("users",function($query) use($user){
                //     $query->where("users.id",$user->id);
                //  })
                //  ->whereNotIn("id",$request_data['projects'])
                //  ->get();
                //  EmployeeProjectHistory::where([
                //     "user_id" => $user->id,
                //     "to_date" => NULL
                //  ])
                //  ->whereIn("project_id",$discharged_projects->pluck("id"))
                //  ->update([
                //     "to_date" => now()
                //  ])
                //  ;


                 foreach($request_data['projects'] as $index=>$project_id) {
                  $project = Project::
                  whereHas("users",function($query) use($user){
                    $query->where("users.id",$user->id);
                 })
                   ->where([
                    "id" => $project_id
                   ])
                    ->first();

                    if($project) {
                            $error = [ "message" => "The given data was invalid.",
                                       "errors" => [("projects.".$index)=>["The project is already belongs to that user."]]
                                       ];
                                           throw new Exception(json_encode($error),422);

                    }




                        $project = Project::where([
                           "id" => $project_id
                        ])
                        ->first();

                        if(!$project) {
                            throw new Exception("some thing went wrong");
                        }

                        // UserProject::create([
                        //     "user_id" => $user->id,
                        //     "project_id" => $project->id
                        // ]);



          $employee_project_history_data = $project->toArray();
          $employee_project_history_data["project_id"] = $employee_project_history_data["id"];
          $employee_project_history_data["user_id"] = $user->id;
          $employee_project_history_data["from_date"] = now();
          $employee_project_history_data["to_date"] = NULL;

          EmployeeProjectHistory::create($employee_project_history_data);

                 }




                 $user->projects()->attach($request_data['projects']);



                 DB::commit();

                 return response($user, 201);


         } catch (Exception $e) {


            DB::rollBack();

             return $this->sendError($e, 500, $request);
         }
     }



     public function dischargeProject(ProjectAssignToUserRequest $request)
     {

        DB::beginTransaction();
         try {
             $this->storeActivity($request, "DUMMY activity","DUMMY description");



                 if (!$request->user()->hasPermissionTo('project_update')) {
                     return response()->json([
                         "message" => "You can not perform this action"
                     ], 401);
                 }
                 $request_data = $request->validated();
                 $user_query_params = [
                     "id" => $request_data["id"],
                 ];
                 $user  =  User::where($user_query_params)
                     ->first();
                 if (!$user) {
                     return response()->json([
                         "message" => "something went wrong."
                     ], 500);
                 }
                 $discharged_projects =  Project::whereHas("users",function($query) use($user){
                    $query->where("users.id",$user->id);
                 })
                 ->whereIn("id",$request_data['projects'])
                 ->get();
                 EmployeeProjectHistory::where([
                    "user_id" => $user->id,
                    "to_date" => NULL
                 ])
                 ->whereIn("project_id",$discharged_projects->pluck("id"))
                 ->update([
                    "to_date" => now()
                 ])
                 ;


                 foreach($request_data['projects'] as $index=>$project_id) {
                  $project = Project::
                  whereHas("users",function($query) use($user){
                    $query->where("users.id",$user->id);
                 })
                   ->where([
                    "id" => $project_id
                   ])
                    ->first();


                    if(!$project) {

                 $error = [ "message" => "The given data was invalid.",
                            "errors" => [("projects.".$index)=>["The project is not belongs to that user."]]
                            ];
                                throw new Exception(json_encode($error),422);

                    }

                 }


      $user->projects()->detach($request_data['projects']);
       DB::commit();

                 return response($user, 201);


         } catch (Exception $e) {
             DB::rollBack();
             return $this->sendError($e, 500, $request);
         }
     }



    public function updateProject(ProjectUpdateRequest $request)
    {

        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");



                if (!$request->user()->hasPermissionTo('project_update')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }
                $business_id =  $request->user()->business_id;
                $request_data = $request->validated();




                $project_query_params = [
                    "id" => $request_data["id"],
                    "business_id" => $business_id
                ];
                // $project_prev = Project::where($project_query_params)
                //     ->first();
                // if (!$project_prev) {
                //     return response()->json([
                //         "message" => "no project listing found"
                //     ], 404);
                // }

                $project = Project::where($project_query_params)->first();

                if (!$project) {
                    return response()->json([
                        "message" => "something went wrong."
                    ], 500);
                }

                if($project->is_default) {
                    $request_data["end_date"] = NULL;
                }


                $project->fill(collect($request_data)->only([
                    'name',
                    'description',
                    'start_date',
                    'end_date',
                    'status',
                    // "is_active",
                    // "business_id",
                    // "created_by"

                ])->toArray());
                $project->save( );




                if(empty($request_data['departments'])) {
                    $request_data['departments'] = [Department::where("business_id",auth()->user()->business_id)->whereNull("parent_id")->first()->id];
                }
                $project->departments()->sync($request_data['departments']);

                DB::commit();

                return response($project, 201);

        } catch (Exception $e) {
          DB::rollBack();
            return $this->sendError($e, 500, $request);
        }
    }


    public function getProjects(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");


            if (!$request->user()->hasPermissionTo('project_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }



$projects = $this->projectComponent->getProjects();

                if (!empty($request->response_type) && in_array(strtoupper($request->response_type), ['PDF', 'CSV'])) {
                    if (strtoupper($request->response_type) == 'PDF') {
                        $pdf = PDF::loadView('pdf.projects', ["projects" => $projects]);
                        return $pdf->download(((!empty($request->file_name) ? $request->file_name : 'employee') . '.pdf'));
                    } elseif (strtoupper($request->response_type) === 'CSV') {
                        return Excel::download(new ProjectsExport($projects), ((!empty($request->file_name) ? $request->file_name : 'employee') . '.csv'));
                    }
                } else {
                    return response()->json($projects, 200);
                }

// @@@@@@@@@@@@@@@@

            return response()->json($projects, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }

      

    public function getProjectById($id, Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");



            if (!$request->user()->hasPermissionTo('project_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $business_id =  $request->user()->business_id;
            $project =  Project::with("departments","users")
            ->where([
                "id" => $id,
                "business_id" => $business_id
            ])

            ->select('projects.*'
             )
                ->first();

            if (!$project) {


                return response()->json([
                    "message" => "no project listing found"
                ], 404);
            }

            return response()->json($project, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }



    public function deleteProjectsByIds(Request $request, $ids)
    {

        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");


            if (!$request->user()->hasPermissionTo('project_delete')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;
            $idsArray = explode(',', $ids);
            $projects = Project::where([
                "business_id" => $business_id
            ])
                ->whereIn('id', $idsArray)
                ->select('id')
                ->get();

                $canDeleteProjectIds = $projects->filter(function ($asset) {
                    return $asset->can_delete;
                })->pluck('id')->toArray();
              $nonExistingIds = array_diff($idsArray, $canDeleteProjectIds);


            if (!empty($nonExistingIds)) {
                return response()->json([
                    "message" => "Some or all of the specified data do not exist."
                ], 404);
            }

            $attendanceExists = AttendanceProject::whereIn("project_id",$idsArray)->exists();

            if (!empty($nonExistingIds)) {
                return response()->json([
                    "message" => "Attendance exists for this project."
                ], 404);
            }


            Project::destroy($idsArray);


            return response()->json(["message" => "data deleted sussfully","deleted_ids" => $canDeleteProjectIds], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
}
