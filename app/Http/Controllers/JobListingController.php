<?php

namespace App\Http\Controllers;

use App\Http\Requests\JobListingCreateRequest;
use App\Http\Requests\JobListingUpdateeRequest;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\JobListing;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JobListingController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil;



    public function createJobListing(JobListingCreateRequest $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            return DB::transaction(function () use ($request) {
                if (!$request->user()->hasPermissionTo('job_listing_create')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }

                $request_data = $request->validated();




                $request_data["business_id"] = $request->user()->business_id;
                $request_data["is_active"] = true;
                $request_data["created_by"] = $request->user()->id;

                $job_listing =  JobListing::create($request_data);
                $job_listing->job_platforms()->sync($request_data['job_platforms']);

                return response($job_listing, 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }


    public function updateJobListing(JobListingUpdateeRequest $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            return DB::transaction(function () use ($request) {
                if (!$request->user()->hasPermissionTo('job_listing_update')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }
                $business_id =  $request->user()->business_id;
                $request_data = $request->validated();




                $job_listing_query_params = [
                    "id" => $request_data["id"],
                    "business_id" => $business_id
                ];

                // $job_listing_prev = JobListing::where($job_listing_query_params)
                //     ->first();
                // if (!$job_listing_prev) {
                //     return response()->json([
                //         "message" => "no job listing found"
                //     ], 404);
                // }

                $job_listing  =  tap(JobListing::where($job_listing_query_params))->update(
                    collect($request_data)->only([
        'title',
        'description',
        'required_skills',
        'application_deadline',
        'posted_on',
        'department_id',
        'minimum_salary',
        'maximum_salary',
        'experience_level',
        'job_type_id',
        'work_location_id',
                        // "is_active",
                        // "business_id",
                        // "created_by"

                    ])->toArray()
                )
                    // ->with("somthing")

                    ->first();

                if (!$job_listing) {
                    return response()->json([
                        "message" => "something went wrong."
                    ], 500);
                }
                $job_listing->job_platforms()->sync($request_data['job_platforms']);

                return response($job_listing, 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }



    public function getJobListings(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('job_listing_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;
            $job_listings = JobListing::with("job_platforms","job_type","work_location","department")
            ->where(
                [
                    "business_id" => $business_id
                ]
            )

                ->when(!empty($request->search_key), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        $term = $request->search_key;
                        $query->where("title", "like", "%" . $term . "%")
                            ->orWhere("description", "like", "%" . $term . "%");


                    });
                })



                ->when(!empty($request->title), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        $term = $request->title;
                        $query->where("title", "like", "%" . $term . "%");
                    });
                })


                ->when(!empty($request->description), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        $term = $request->description;
                        $query->where("description", "like", "%" . $term . "%");
                    });
                })

                ->when(!empty($request->work_location_id), function ($query) use ($request) {
                    $work_location_ids = explode(',', $request->work_location_id);
                    $query->whereIn("work_location_id", $work_location_ids);
                })

                ->when(!empty($request->experience_level), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        $term = $request->experience_level;
                        $query->where("experience_level", "like", "%" . $term . "%");
                    });
                })

                ->when(!empty($request->salary), function ($query) use ($request) {
                    return $query->where('minimum_salary', "<=", $request->salary)
                    ->where('maximum_salary', ">=", $request->salary);
                })

                ->when(!empty($request->post_on), function ($query) use ($request) {
                    return $query->where('posted_on', $request->post_on);
                })
                ->when(!empty($request->deadline), function ($query) use ($request) {
                    return $query->where('application_deadline', $request->deadline);
                })

                ->when(!empty($request->job_platform_id), function ($query) use ($request) {
                    $job_platform_ids = explode(',', $request->job_platform_id);
                    $query->whereHas("job_platforms",function($query) use($job_platform_ids){
                        $query->whereIn("job_platforms.id", $job_platform_ids);
                    });
                })

                ->when(!empty($request->number_of_candidates), function ($query) use ($request) {

                    $number_query = explode(',', str_replace(' ', ',', $request->worked_hour));

                    $query->whereHas("candidates", function ($query) use ($request, $number_query) {

                        $query->havingRaw('COUNT(*) ' . $number_query[0] .' ?', [$number_query[1]]);

                    });

                })




                //    ->when(!empty($request->product_category_id), function ($query) use ($request) {
                //        return $query->where('product_category_id', $request->product_category_id);
                //    })

                ->when(!empty($request->start_date), function ($query) use ($request) {
                    return $query->where('created_at', ">=", $request->start_date);
                })
                ->when(!empty($request->end_date), function ($query) use ($request) {
                    return $query->where('created_at', "<=", ($request->end_date . ' 23:59:59'));
                })
                ->when(isset($request->is_open_roles), function ($query) use ($request) {
                    if( intval($request->is_open_roles) == 1) {
                        $query->where("application_deadline",">=", today());
                    } else {
                        $query->where("application_deadline","<", today());
                    }

                })
                ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                    return $query->orderBy("job_listings.id", $request->order_by);
                }, function ($query) {
                    return $query->orderBy("job_listings.id", "DESC");
                })
                ->select('job_listings.*',

                 )
                ->when(!empty($request->per_page), function ($query) use ($request) {
                    return $query->paginate($request->per_page);
                }, function ($query) {
                    return $query->get();
                });



            return response()->json($job_listings, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }



     public function getJobListingsClient(Request $request)
     {
         try {
             $this->storeActivity($request, "DUMMY activity","DUMMY description");


             $business_id =  $request->business_id;
             if(!$business_id) {
                $error = [ "message" => "The given data was invalid.",
                "errors" => ["business_id"=>["The business id field is required."]]
                ];
                    throw new Exception(json_encode($error),422);
             }

             $job_listings = JobListing::with("job_platforms","job_type","work_location","department")
             ->where(
                 [
                     "business_id" => $business_id
                 ]
             )
                 ->when(!empty($request->search_key), function ($query) use ($request) {
                     return $query->where(function ($query) use ($request) {
                         $term = $request->search_key;
                         $query->where("title", "like", "%" . $term . "%")
                             ->orWhere("description", "like", "%" . $term . "%");


                     });
                 })
                 //    ->when(!empty($request->product_category_id), function ($query) use ($request) {
                 //        return $query->where('product_category_id', $request->product_category_id);
                 //    })
                 ->when(!empty($request->start_date), function ($query) use ($request) {
                     return $query->where('created_at', ">=", $request->start_date);
                 })
                 ->when(!empty($request->end_date), function ($query) use ($request) {
                     return $query->where('created_at', "<=", ($request->end_date . ' 23:59:59'));
                 })
                 ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                     return $query->orderBy("job_listings.id", $request->order_by);
                 }, function ($query) {
                     return $query->orderBy("job_listings.id", "DESC");
                 })
                 ->select('job_listings.*',

                  )
                 ->when(!empty($request->per_page), function ($query) use ($request) {
                     return $query->paginate($request->per_page);
                 }, function ($query) {
                     return $query->get();
                 });



             return response()->json($job_listings, 200);
         } catch (Exception $e) {

             return $this->sendError($e, 500, $request);
         }
     }


    public function getJobListingById($id, Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('job_listing_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;
            $job_listing =  JobListing::with("job_platforms","job_type","work_location","department")
            ->where([
                "id" => $id,
                "business_id" => $business_id
            ])
            ->select('job_listings.*'
             )
                ->first();
            if (!$job_listing) {

                return response()->json([
                    "message" => "no job listing found"
                ], 404);
            }

            return response()->json($job_listing, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }




     public function getJobListingByIdClient($id, Request $request)
     {
         try {
             $this->storeActivity($request, "DUMMY activity","DUMMY description");


             $job_listing =  JobListing::with("job_platforms","job_type","work_location","department")
             ->where([
                 "id" => $id,
             ])
             ->select('job_listings.*')
             ->first();
             if (!$job_listing) {

                 return response()->json([
                     "message" => "no job listing found"
                 ], 404);
             }

             return response()->json($job_listing, 200);
         } catch (Exception $e) {

             return $this->sendError($e, 500, $request);
         }
     }

     

    public function deleteJobListingsByIds(Request $request, $ids)
    {

        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('job_listing_delete')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $business_id =  $request->user()->business_id;
            $idsArray = explode(',', $ids);
            $existingIds = JobListing::where([
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
            JobListing::destroy($existingIds);


            return response()->json(["message" => "data deleted sussfully","deleted_ids" => $existingIds], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
}
