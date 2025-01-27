<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleRequest;
use App\Http\Requests\RoleUpdateRequest;
use App\Http\Utils\BasicUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use Exception;
use Illuminate\Http\Request;
use App\Models\Role;
use Carbon\Carbon;

class RolesController extends Controller
{
    use ErrorUtil,UserActivityUtil,  BasicUtil;

    public function createRole(RoleRequest $request)
    {
        try{
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if( !$request->user()->hasPermissionTo('role_create'))
            {

               return response()->json([
                  "message" => "You can not perform this action"
               ],401);
          }
           $insertableData = $request->validated();
           $insertableRole = [
            "name" => $insertableData["name"],
            "guard_name" => "api",
           ];

           if(empty($request->user()->business_id))
           {
            $insertableRole["business_id"] = NULL;
            $insertableRole["is_default"] = 1;
         } else {
            $insertableRole["business_id"] = $request->user()->business_id;
            $insertableRole["is_default"] = 0;
            $insertableRole["is_default_for_business"] = 0;

         }
           $role = Role::create($insertableRole);
           $role->syncPermissions($insertableData["permissions"]);



           return response()->json([
               "role" =>  $role,
           ], 201);
        } catch(Exception $e){

        return $this->sendError($e,500,$request);
        }





    }

    public function updateRole(RoleUpdateRequest $request) {
        try{
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
        if( !$request->user()->hasPermissionTo('role_update') )
        {

           return response()->json([
              "message" => "You can not perform this action"
           ],401);
      }
        $request_data = $request->validated();

        $role = Role::where(["id" => $request_data["id"]])
        ->when((empty($request->user()->business_id)), function ($query) use ($request) {
            return $query->where('business_id', NULL)->where('is_default', 1);
        })
        ->when(!empty($request->user()->business_id), function ($query) use ($request) {
            // return $query->where('business_id', $request->user()->business_id)->where('is_default', 0);
            return $query->where('business_id', $request->user()->business_id);
        })
        ->first();

        if(!$role)
        {

           return response()->json([
              "message" => "No role found"
           ],404);
      }
        if($role->name == "superadmin" )
        {
           return response()->json([
              "message" => "You can not perform this action"
           ],401);
      }

      if(!empty($request_data['description'])) {
        $role->description = $request_data['description'];
        $role->save();
      }

        $role->syncPermissions($request_data["permissions"]);


        return response()->json([
            "role" =>  $role,
        ], 201);
        } catch(Exception $e){

        return $this->sendError($e,500,$request);
        }


    }

    public function getRoles(Request $request)
    {

        try{
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if(!$request->user()->hasPermissionTo('role_view')){
                return response()->json([
                   "message" => "You can not perform this action"
                ],401);
           }

           $roles = Role::with('permissions:name,id',"users")

           ->when((empty($request->user()->business_id)), function ($query) use ($request) {
            return $query->where('business_id', NULL)->where('is_default', 1)
            ->when(!($request->user()->hasRole('superadmin')), function ($query) use ($request) {
                return $query->where('name', '!=', 'superadmin')
                ->where("id",">",$this->getMainRoleId());
            });
        })
        ->when(!(empty($request->user()->business_id)), function ($query) use ($request) {
            return $query->where('business_id', $request->user()->business_id)
            ->where("id",">",$this->getMainRoleId());
        })

           ->when(!empty($request->search_key), function ($query) use ($request) {
               $term = $request->search_key;
               $query->where("name", "like", "%" . $term . "%");
           })
           ->when(!empty($request->start_date), function ($query) use ($request) {
            return $query->where('created_at', ">=", $request->start_date);
        })
        ->when(!empty($request->end_date), function ($query) use ($request) {
            return $query->where('created_at', "<=", ($request->end_date . ' 23:59:59'));
        })
        ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
            return $query->orderBy("id", $request->order_by);
        }, function ($query) {
            return $query->orderBy("id", "DESC");
        })
        ->when(!empty($request->per_page), function ($query) use ($request) {
            return $query->paginate($request->per_page);
        }, function ($query) {
            return $query->get();
        });
            return response()->json($roles, 200);
        } catch(Exception $e){

        return $this->sendError($e,500,$request);
        }


    }


    public function getRoleById($id,Request $request) {

        try{
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            $role = Role::with('permissions:name,id')
            ->where(["id" => $id])
            ->select("name", "id")->get();
            return response()->json($role, 200);
        } catch(Exception $e){

        return $this->sendError($e,500,$request);
        }

    }


    public function deleteRolesByIds($ids,Request $request) {

        try{
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if(!$request->user()->hasPermissionTo('role_delete'))
            {

            return response()->json([
               "message" => "You can not perform this action"
            ],401);
       }

            $idsArray = explode(',', $ids);
            $existingIds = Role::whereIn('id', $idsArray)
            ->where("is_system_default", "!=", 1)
            ->when(empty($request->user()->business_id), function ($query) use ($request) {
                return $query->where('business_id', NULL)->where('is_default', 1);
            })
            ->when(!empty($request->user()->business_id), function ($query) use ($request) {
                return $query->where('business_id', $request->user()->business_id)->where('is_default', 0);
            })
            ->when(!($request->user()->hasRole('superadmin')), function ($query) use ($request) {
                return $query->where('name', '!=', 'superadmin');
            })

                ->select('id')
                ->get()
                ->pluck('id')
                ->toArray();
            $nonExistingIds = array_diff($idsArray, $existingIds);

            if (!empty($nonExistingIds)) {

                return response()->json([
                    "message" => "Some or all of the data they can not be deleted or not exists."
                ], 404);
            }




            Role::destroy($existingIds);


            return response()->json(["message" => "data deleted sussfully","deleted_ids" => $existingIds], 200);









             return response()->json(["ok" => true], 200);
        } catch(Exception $e){

        return $this->sendError($e,500,$request);
        }




    }


    public function getInitialRolePermissions (Request $request) {

        try{
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if(!$request->user()->hasPermissionTo('role_view')){
                return response()->json([
                   "message" => "You can not perform this action"
                ],401);
           }
           $role_permissions_main = config("setup-config.roles_permission");
           $unchangeable_roles = config("setup-config.unchangeable_roles");
           $unchangeable_permissions = config("setup-config.unchangeable_permissions");
           $permissions_titles = config("setup-config.permissions_titles");

           $new_role_permissions = [];

           foreach ($role_permissions_main as $roleAndPermissions) {
               if (in_array($roleAndPermissions["role"], $unchangeable_roles)) {
                   // Skip unchangeable roles
                   continue;
               }

               if (!empty($request->user()->business_id)) {
                   if (in_array($roleAndPermissions["role"], ["superadmin", "reseller"])) {
                       // Skip specific roles
                       continue;
                   }
               }

               if (!($request->user()->hasRole('superadmin')) && $roleAndPermissions["role"] == "superadmin") {
                   // Skip superadmin role
                   continue;
               }

               $data = [
                   "role"        => $roleAndPermissions["role"],
                   "permissions" => [],
               ];

               foreach ($roleAndPermissions["permissions"] as $permission) {
                   if (in_array($permission, $unchangeable_permissions)) {
                       // Skip unchangeable permissions
                       continue;
                   }

                   $data["permissions"][] = [
                       "name"  => $permission,
                       "title" => $permissions_titles[$permission] ?? null,
                   ];
               }


                   array_push($new_role_permissions, $data);

           }

           return response()->json($new_role_permissions, 200);
        } catch(Exception $e){

        return $this->sendError($e,500,$request);
        }



    }

     
    public function getInitialPermissions (Request $request) {

        try{
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if(!$request->user()->hasPermissionTo('role_view')){
                return response()->json([
                   "message" => "You can not perform this action"
                ],401);
           }
           $permissions_main = config("setup-config.beautified_permissions");


           $permissions_titles = config("setup-config.beautified_permissions_titles");

           $new_permissions = [];

           foreach ($permissions_main as $permissions) {


               $data = [
                   "header"        => $permissions["header"],
                   "permissions" => [],
               ];

               foreach ($permissions["permissions"] as $permission) {


                   $data["permissions"][] = [
                       "name"  => $permission,
                       "title" => $permissions_titles[$permission] ?? null,
                   ];
               }


                   array_push($new_permissions, $data);

           }

           return response()->json($new_permissions, 200);
        } catch(Exception $e){

        return $this->sendError($e,500,$request);
        }



    }

}
