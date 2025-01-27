<?php

namespace App\Http\Controllers;

use App\Http\Requests\CandidateCreateRequest;
use App\Http\Requests\CandidateUpdateRequest;
use App\Http\Requests\MultipleFileUploadRequest;
use App\Http\Utils\BasicUtil;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\Candidate;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CandidateController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil, BasicUtil;






    public function createCandidate(CandidateCreateRequest $request)
    {

        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");


                if (!$request->user()->hasPermissionTo('candidate_create')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }

                $request_data = $request->validated();

                if (!empty($request_data["recruitment_processes"])) {
                    $request_data["recruitment_processes"] = $this->storeUploadedFiles($request_data["recruitment_processes"],"attachments","recruitment_processes",[]);
                    $this->makeFilePermanent($request_data["recruitment_processes"],"attachments",[]);

                }

                $request_data["attachments"] = $this->storeUploadedFiles($request_data["attachments"],"","candidate_files");
                $this->makeFilePermanent($request_data["attachments"],"");

                $request_data["business_id"] = $request->user()->business_id;
                $request_data["is_active"] = true;
                $request_data["created_by"] = $request->user()->id;
                $candidate =  Candidate::create($request_data);



                if (!empty($request_data["recruitment_processes"])) {

                    foreach($request_data["recruitment_processes"] as $recruitment_process){

                        if(!empty($recruitment_process["description"])){
            $candidate->recruitment_processes()->create($recruitment_process);
                        }
        }

                }



                $candidate->job_platforms()->sync($request_data['job_platforms']);






                DB::commit();

                return response($candidate, 201);

        } catch (Exception $e) {
            DB::rollBack();
            try {
                $this->moveUploadedFilesBack($request_data["recruitment_processes"], "attachments", "recruitment_processes", []);
            } catch (Exception $innerException) {
                error_log("Failed to move recruitment processes files back: " . $innerException->getMessage());
            }

            try {
                $this->moveUploadedFilesBack($request_data["attachments"],"","candidate_files");
            } catch (Exception $innerException) {
                error_log("Failed to move candidate files back: " . $innerException->getMessage());
            }


            return $this->sendError($e, 500, $request);
        }
    }



     public function createCandidateClient(CandidateCreateRequest $request)
     {
        DB::beginTransaction();
         try {
             $this->storeActivity($request, "DUMMY activity","DUMMY description");

                 if (!$request->user()->hasPermissionTo('candidate_create')) {
                     return response()->json([
                         "message" => "You can not perform this action"
                     ], 401);
                 }

                 $request_data = $request->validated();


       $request_data["attachments"] = $this->storeUploadedFiles($request_data["attachments"],"","candidate_files");
       $this->makeFilePermanent($request_data["attachments"],"");

               if (!empty($request_data["recruitment_processes"])) {
                    $request_data["recruitment_processes"] = $this->storeUploadedFiles($request_data["recruitment_processes"],"attachments","recruitment_processes",[]);
                    $this->makeFilePermanent($request_data["recruitment_processes"],"attachments",[]);
                }


                 $request_data["business_id"] = $request->user()->business_id;
                 $request_data["is_active"] = true;
                 $request_data["created_by"] = $request->user()->id;

                 $candidate =  Candidate::create($request_data);

                 $candidate->job_platforms()->sync($request_data['job_platforms']);


                 if (!empty($request_data["recruitment_processes"])) {

                    foreach($request_data["recruitment_processes"] as $recruitment_process){

                        if(!empty($recruitment_process["description"])){
            $candidate->recruitment_processes()->create($recruitment_process);
                        }
        }

                }

                //  $this->moveUploadedFiles($request_data["attachments"],"candidate_files");

                DB::commit();
                 return response($candidate, 201);

         } catch (Exception $e) {
            DB::rollBack();

            try {
                $this->moveUploadedFilesBack($request_data["recruitment_processes"], "attachments", "recruitment_processes", []);
            } catch (Exception $innerException) {
                error_log("Failed to move recruitment processes files back: " . $innerException->getMessage());
            }


            try {
                $this->moveUploadedFilesBack($request_data["attachments"],"","candidate_files");
            } catch (Exception $innerException) {
                error_log("Failed to move candidate files back: " . $innerException->getMessage());
            }

             return $this->sendError($e, 500, $request);
         }
     }



    public function updateCandidate(CandidateUpdateRequest $request)
    {

        DB::beginTransaction();
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");



                if (!$request->user()->hasPermissionTo('candidate_update')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }

                $request_data = $request->validated();

                $candidate_query_params = [
                    "id" => $request_data["id"],
                    "business_id" => auth()->user()->business_id
                ];

                $candidate  =     Candidate::where($candidate_query_params)->first();


                $this->moveUploadedFilesBack($candidate->attachments,"","candidate_files");


                $request_data["attachments"] = $this->storeUploadedFiles($request_data["attachments"],"","candidate_files");
                $this->makeFilePermanent($request_data["attachments"],"");


                if (!empty($request_data["recruitment_processes"])) {
                    $request_data["recruitment_processes"] = $this->storeUploadedFiles($request_data["recruitment_processes"],"attachments","recruitment_processes",[]);
                    $this->makeFilePermanent($request_data["recruitment_processes"],"attachments",[]);
                }


             if($candidate) {
                $candidate->fill( collect($request_data)->only([
                    'name',
                    'email',
                    'phone',
                    'experience_years',
                    'education_level',

                    'cover_letter',
                    'application_date',
                    'interview_date',
                    'feedback',
                    'status',
                    'job_listing_id',
                    'attachments',

                    // "is_active",
                    // "business_id",
                    // "created_by"

                ])->toArray());
                $candidate->save();
             } else {
                    return response()->json([
                        "message" => "something went wrong."
                    ], 500);
                }






                $candidate->job_platforms()->sync($request_data['job_platforms']);

                if (!empty($request_data["recruitment_processes"])) {
                    $candidate->recruitment_processes()->delete();
                    foreach($request_data["recruitment_processes"] as $recruitment_process){
        if(!empty($recruitment_process["description"])){
            $candidate->recruitment_processes()->create($recruitment_process);
        }
                    }

                }


                DB::commit();
                return response($candidate, 201);





        } catch (Exception $e) {





            // try {
            //     $this->moveUploadedFilesBack($request_data["attachments"],"","candidate_files");
            // } catch (Exception $innerException) {
            //     error_log("Failed to move candidate files back: " . $innerException->getMessage());
            // }




            DB::rollBack();
            return $this->sendError($e, 500, $request);
        }
    }


    public function getCandidates(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('candidate_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;
            $candidates = Candidate::
            with("job_listing","job_platforms")

            ->where(
                [
                    "candidates.business_id" => $business_id
                ]
            )

                ->when(!empty($request->search_key), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        $term = $request->search_key;
                        // $query->where("candidates.name", "like", "%" . $term . "%")
                        //     ->orWhere("candidates.description", "like", "%" . $term . "%");
                    });
                })
                ->when(!empty($request->name), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        $term = $request->name;
                        $query->where("candidates.name", "like", "%" . $term . "%");
                        //     ->orWhere("candidates.description", "like", "%" . $term . "%");
                    });
                })



                //    ->when(!empty($request->product_category_id), function ($query) use ($request) {
                //        return $query->where('product_category_id', $request->product_category_id);
                //    })
                ->when(!empty($request->start_date), function ($query) use ($request) {
                    return $query->where('candidates.created_at', ">=", $request->start_date);
                })
                ->when(!empty($request->end_date), function ($query) use ($request) {
                    return $query->where('candidates.created_at', "<=", ($request->end_date . ' 23:59:59'));
                })


                ->when(!empty($request->job_listing_id), function ($query) use ($request) {
                    $idsArray = explode(',', $request->job_listing_id);
                    return $query->whereIn('candidates.job_listing_id',$idsArray);
                })


                ->when(!empty($request->job_platform_id), function ($query) use ($request) {
                    $job_platform_ids = explode(',', $request->job_platform_id);
                    $query->whereHas("job_platforms",function($query) use($job_platform_ids){
                        $query->whereIn("job_platforms.id", $job_platform_ids);
                    });
                })




                ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                    return $query->orderBy("candidates.id", $request->order_by);
                }, function ($query) {
                    return $query->orderBy("candidates.id", "DESC");
                })
                ->when(!empty($request->per_page), function ($query) use ($request) {
                    return $query->paginate($request->per_page);
                }, function ($query) {
                    return $query->get();
                });;



            return response()->json($candidates, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }



    public function getCandidateById($id, Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('candidate_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;
            $candidate =  Candidate:: with("job_listing","job_platforms","recruitment_processes")
            ->where([
                "id" => $id,
                "business_id" => $business_id
            ])
                ->first();
            if (!$candidate) {

                return response()->json([
                    "message" => "no data found"
                ], 404);
            }

            return response()->json($candidate, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }




    public function deleteCandidatesByIds(Request $request, $ids)
    {

        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('candidate_delete')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;
            $idsArray = explode(',', $ids);
            $existingIds = Candidate::where([
                "business_id" => $business_id
            ])
                ->whereIn('id', $idsArray)
                ->select('id')
                ->get()
                ->pluck('id')
                ->toArray();
            $nonExistingIds = array_diff($idsArray, $existingIds);

            if (!empty($nonExistingIds)) {

                return response()->json([
                    "message" => "Some or all of the specified data do not exist."
                ], 404);
            }
            Candidate::destroy($existingIds);


            return response()->json(["message" => "data deleted sussfully","deleted_ids" => $existingIds], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
}
