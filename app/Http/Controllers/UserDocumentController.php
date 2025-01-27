<?php

namespace App\Http\Controllers;

use App\Http\Requests\SingleFileUploadRequest;
use App\Http\Requests\UserDocumentCreateRequest;
use App\Http\Requests\UserDocumentUpdateRequest;
use App\Http\Utils\BasicUtil;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\Department;
use App\Models\UserDocument;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserDocumentController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil, BasicUtil;


    public function createUserDocument(UserDocumentCreateRequest $request)
    {
        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");

                if (!$request->user()->hasPermissionTo('employee_document_create')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }

                $request_data = $request->validated();

                if(!empty($request_data["file_name"])) {
                    $request_data["file_name"] = $this->storeUploadedFiles([$request_data["file_name"]],"","documents")[0];
                    $this->makeFilePermanent($request_data["file_name"],"");
                }




                $request_data["created_by"] = $request->user()->id;

                $user_document =  UserDocument::create($request_data);





                // $this->moveUploadedFiles($request_data["file_name"],"documents");






                DB::commit();

                return response($user_document, 201);

        } catch (Exception $e) {
           DB::rollBack();




        try {
            if(!empty($request_data["file_name"])) {
                $request_data["file_name"] = $this->moveUploadedFilesBack([$request_data["file_name"]],"","documents")[0];
            }
        } catch (Exception $innerException) {
            error_log("Failed to move document files back: " . $innerException->getMessage());
        }



            return $this->sendError($e, 500, $request);
        }
    }


    public function updateUserDocument(UserDocumentUpdateRequest $request)
    {

        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");

                if (!$request->user()->hasPermissionTo('employee_document_update')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }

                $request_data = $request->validated();
                $user_document_query_params = [
                    "id" => $request_data["id"],
                ];
             $user_document = UserDocument::where($user_document_query_params)->first();


                if(!empty($user_document->file_name)) {
                    $this->moveUploadedFilesBack([$user_document->file_name],"","documents");
                }

                if(!empty($request_data["file_name"])) {
                    $request_data["file_name"] = $this->storeUploadedFiles([$request_data["file_name"]],"","documents")[0];
                    $this->makeFilePermanent($request_data["file_name"],"");
                }






if($user_document) {
    $user_document->fill(  collect($request_data)->only([
        'user_id',
       'name',
       'file_name',
       // 'created_by',

   ])->toArray());
    $user_document->save();
} else{
            return response()->json([
                        "message" => "something went wrong."
       ], 500);
                }

                // $this->moveUploadedFiles($request_data["file_name"],"documents");
                DB::commit();

                return response($user_document, 201);

        } catch (Exception $e) {

            DB::rollBack();

            try {
                if(!empty($request_data["file_name"])) {
                    $request_data["file_name"] = $this->moveUploadedFilesBack([$request_data["file_name"]],"","documents")[0];
                }
            } catch (Exception $innerException) {
                error_log("Failed to move document files back: " . $innerException->getMessage());
            }


            return $this->sendError($e, 500, $request);
        }
    }




    public function getUserDocuments(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('employee_document_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;


            $all_manager_department_ids = $this->get_all_departments_of_manager();


            $user_documents = UserDocument::with([
                "creator" => function ($query) {
                    $query->select('users.id', 'users.first_Name','users.middle_Name',
                    'users.last_Name');
                },

            ])
            ->whereHas("user.department_user.department", function($query) use($all_manager_department_ids) {
                $query->whereIn("departments.id",$all_manager_department_ids);
             })
            ->when(!empty($request->search_key), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        $term = $request->search_key;
                        $query->where("user_documents.name", "like", "%" . $term . "%");
                        //     ->orWhere("user_documents.description", "like", "%" . $term . "%");
                    });
                })
                //    ->when(!empty($request->product_category_id), function ($query) use ($request) {
                //        return $query->where('product_category_id', $request->product_category_id);
                //    })

                ->when(!empty($request->user_id), function ($query) use ($request) {
                    return $query->where('user_documents.user_id', $request->user_id);
                })
                ->when(empty($request->user_id), function ($query) use ($request) {
                    return $query->where('user_documents.user_id', $request->user()->id);
                })
                ->when(!empty($request->start_date), function ($query) use ($request) {
                    return $query->where('user_documents.created_at', ">=", $request->start_date);
                })
                ->when(!empty($request->end_date), function ($query) use ($request) {
                    return $query->where('user_documents.created_at', "<=", ($request->end_date . ' 23:59:59'));
                })
                ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                    return $query->orderBy("user_documents.id", $request->order_by);
                }, function ($query) {
                    return $query->orderBy("user_documents.id", "DESC");
                })
                ->when(!empty($request->per_page), function ($query) use ($request) {
                    return $query->paginate($request->per_page);
                }, function ($query) {
                    return $query->get();
                });;



            return response()->json($user_documents, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }

    


    public function getUserDocumentById($id, Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('employee_document_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;


           $all_manager_department_ids = $this->get_all_departments_of_manager();


            $user_document =  UserDocument::where([
                "id" => $id,

            ])
            ->whereHas("user.department_user.department", function($query) use($all_manager_department_ids) {
                $query->whereIn("departments.id",$all_manager_department_ids);
             })
                ->first();
            if (!$user_document) {

                return response()->json([
                    "message" => "no data found"
                ], 404);
            }

            return response()->json($user_document, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }



    public function deleteUserDocumentsByIds(Request $request, $ids)
    {

        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('employee_document_delete')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;


             $all_manager_department_ids = $this->get_all_departments_of_manager();


            $idsArray = explode(',', $ids);
            $existingIds = UserDocument::whereIn('id', $idsArray)
                ->select('id')
                ->whereHas("user.department_user.department", function($query) use($all_manager_department_ids) {
                    $query->whereIn("departments.id",$all_manager_department_ids);
                 })
                ->get()
                ->pluck('id')
                ->toArray();
            $nonExistingIds = array_diff($idsArray, $existingIds);

            if (!empty($nonExistingIds)) {

                return response()->json([
                    "message" => "Some or all of the specified data do not exist."
                ], 404);
            }
            UserDocument::destroy($existingIds);


            return response()->json(["message" => "data deleted sussfully","deleted_ids" => $existingIds], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
}
