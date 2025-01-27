<?php

namespace App\Http\Controllers;

use App\Http\Requests\BankCreateRequest;
use App\Http\Requests\BankUpdateRequest;
use App\Http\Requests\GetIdRequest;
use App\Http\Utils\BusinessUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\Bank;
use App\Models\DisabledBank;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BankController extends Controller
{
    use ErrorUtil, UserActivityUtil, BusinessUtil;



    public function createBank(BankCreateRequest $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            return DB::transaction(function () use ($request) {
                if (!$request->user()->hasPermissionTo('bank_create')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }

                $request_data = $request->validated();

                $request_data["is_active"] = 1;
                $request_data["is_default"] = 0;
                $request_data["created_by"] = $request->user()->id;
                $request_data["business_id"] = $request->user()->business_id;

                if (empty($request->user()->business_id)) {
                    $request_data["business_id"] = NULL;
                    if ($request->user()->hasRole('superadmin')) {
                        $request_data["is_default"] = 1;
                    }
                }




                $bank =  Bank::create($request_data);




                return response($bank, 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }



    public function updateBank(BankUpdateRequest $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            return DB::transaction(function () use ($request) {
                if (!$request->user()->hasPermissionTo('bank_update')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }
                $request_data = $request->validated();



                $bank_query_params = [
                    "id" => $request_data["id"],
                ];

                $bank  =  tap(Bank::where($bank_query_params))->update(
                    collect($request_data)->only([
                        'name',
                        'description',
                        // "is_default",
                        // "is_active",
                        // "business_id",
                        // "created_by"

                    ])->toArray()
                )
                    // ->with("somthing")

                    ->first();
                if (!$bank) {
                    return response()->json([
                        "message" => "something went wrong."
                    ], 500);
                }




                return response($bank, 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }


    public function toggleActiveBank(GetIdRequest $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('bank_activate')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $request_data = $request->validated();

            $bank =  Bank::where([
                "id" => $request_data["id"],
            ])
                ->first();
            if (!$bank) {

                return response()->json([
                    "message" => "no data found"
                ], 404);
            }
            $should_update = 0;
            $should_disable = 0;
            if (empty(auth()->user()->business_id)) {

                if (auth()->user()->hasRole('superadmin')) {
                    if (($bank->business_id != NULL || $bank->is_default != 1)) {

                        return response()->json([
                            "message" => "You do not have permission to update this bank due to role restrictions."
                        ], 403);
                    } else {
                        $should_update = 1;
                    }
                } else {
                    if ($bank->business_id != NULL) {

                        return response()->json([
                            "message" => "You do not have permission to update this bank due to role restrictions."
                        ], 403);
                    } else if ($bank->is_default == 0) {

                        if($bank->created_by != auth()->user()->id) {

                            return response()->json([
                                "message" => "You do not have permission to update this bank due to role restrictions."
                            ], 403);
                        }
                        else {
                            $should_update = 1;
                        }



                    }
                    else {
                     $should_disable = 1;

                    }
                }
            } else {
                if ($bank->business_id != NULL) {
                    if (($bank->business_id != auth()->user()->business_id)) {

                        return response()->json([
                            "message" => "You do not have permission to update this bank due to role restrictions."
                        ], 403);
                    } else {
                        $should_update = 1;
                    }
                } else {
                    if ($bank->is_default == 0) {
                        if ($bank->created_by != auth()->user()->created_by) {

                            return response()->json([
                                "message" => "You do not have permission to update this bank due to role restrictions."
                            ], 403);
                        } else {
                            $should_disable = 1;

                        }
                    } else {
                        $should_disable = 1;

                    }
                }
            }

            if ($should_update) {
                $bank->update([
                    'is_active' => !$bank->is_active
                ]);
            }

            if($should_disable) {

                $disabled_bank =    DisabledBank::where([
                    'bank_id' => $bank->id,
                    'business_id' => auth()->user()->business_id,
                    'created_by' => auth()->user()->id,
                ])->first();
                if(!$disabled_bank) {
                    DisabledBank::create([
                        'bank_id' => $bank->id,
                        'business_id' => auth()->user()->business_id,
                        'created_by' => auth()->user()->id,
                    ]);
                } else {
                    $disabled_bank->delete();
                }
            }


            return response()->json(['message' => 'Bank status updated successfully'], 200);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }


    public function getBanks(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('bank_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $created_by  = NULL;
            if(auth()->user()->business) {
                $created_by = auth()->user()->business->created_by;
            }



            $banks = Bank::when(empty($request->user()->business_id), function ($query) use ($request, $created_by) {
                if (auth()->user()->hasRole('superadmin')) {
                    return $query->where('banks.business_id', NULL)
                        ->where('banks.is_default', 1)
                        ->when(isset($request->is_active), function ($query) use ($request) {
                            return $query->where('banks.is_active', intval($request->is_active));
                        });
                } else {
                    return $query

                    ->where(function($query) use($request) {
                        $query->where('banks.business_id', NULL)
                        ->where('banks.is_default', 1)
                        ->where('banks.is_active', 1)
                        ->when(isset($request->is_active), function ($query) use ($request) {
                            if(intval($request->is_active)) {
                                return $query->whereDoesntHave("disabled", function($q) {
                                    $q->whereIn("disabled_banks.created_by", [auth()->user()->id]);
                                });
                            }

                        })
                        ->orWhere(function ($query) use ($request) {
                            $query->where('banks.business_id', NULL)
                                ->where('banks.is_default', 0)
                                ->where('banks.created_by', auth()->user()->id)
                                ->when(isset($request->is_active), function ($query) use ($request) {
                                    return $query->where('banks.is_active', intval($request->is_active));
                                });
                        });

                    });
                }
            })
                ->when(!empty($request->user()->business_id), function ($query) use ($request, $created_by) {
                    return $query
                    ->where(function($query) use($request, $created_by) {


                        $query->where('banks.business_id', NULL)
                        ->where('banks.is_default', 1)
                        ->where('banks.is_active', 1)
                        ->whereDoesntHave("disabled", function($q) use($created_by) {
                            $q->whereIn("disabled_banks.created_by", [$created_by]);
                        })
                        ->when(isset($request->is_active), function ($query) use ($request, $created_by)  {
                            if(intval($request->is_active)) {
                                return $query->whereDoesntHave("disabled", function($q) use($created_by) {
                                    $q->whereIn("disabled_banks.business_id",[auth()->user()->business_id]);
                                });
                            }

                        })


                        ->orWhere(function ($query) use($request, $created_by){
                            $query->where('banks.business_id', NULL)
                                ->where('banks.is_default', 0)
                                ->where('banks.created_by', $created_by)
                                ->where('banks.is_active', 1)

                                ->when(isset($request->is_active), function ($query) use ($request) {
                                    if(intval($request->is_active)) {
                                        return $query->whereDoesntHave("disabled", function($q) {
                                            $q->whereIn("disabled_banks.business_id",[auth()->user()->business_id]);
                                        });
                                    }

                                })


                                ;
                        })
                        ->orWhere(function ($query) use($request) {
                            $query->where('banks.business_id', auth()->user()->business_id)
                                ->where('banks.is_default', 0)
                                ->when(isset($request->is_active), function ($query) use ($request) {
                                    return $query->where('banks.is_active', intval($request->is_active));
                                });;
                        });
                    });


                })
                ->when(!empty($request->search_key), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        $term = $request->search_key;
                        $query->where("banks.name", "like", "%" . $term . "%")
                            ->orWhere("banks.description", "like", "%" . $term . "%");
                    });
                })
                //    ->when(!empty($request->product_category_id), function ($query) use ($request) {
                //        return $query->where('product_category_id', $request->product_category_id);
                //    })
                ->when(!empty($request->start_date), function ($query) use ($request) {
                    return $query->where('banks.created_at', ">=", $request->start_date);
                })
                ->when(!empty($request->end_date), function ($query) use ($request) {
                    return $query->where('banks.created_at', "<=", ($request->end_date . ' 23:59:59'));
                })
                ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                    return $query->orderBy("banks.id", $request->order_by);
                }, function ($query) {
                    return $query->orderBy("banks.id", "DESC");
                })
                ->when(!empty($request->per_page), function ($query) use ($request) {
                    return $query->paginate($request->per_page);
                }, function ($query) {
                    return $query->get();
                });;



            return response()->json($banks, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }



    public function getBankById($id, Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('bank_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $bank =  Bank::where([
                "banks.id" => $id,
            ])

                ->first();

                if (!$bank) {

                    return response()->json([
                        "message" => "no data found"
                    ], 404);
                }

                if (empty(auth()->user()->business_id)) {

                    if (auth()->user()->hasRole('superadmin')) {
                        if (($bank->business_id != NULL || $bank->is_default != 1)) {

                            return response()->json([
                                "message" => "You do not have permission to update this bank due to role restrictions."
                            ], 403);
                        }
                    } else {
                        if ($bank->business_id != NULL) {

                            return response()->json([
                                "message" => "You do not have permission to update this bank due to role restrictions."
                            ], 403);
                        } else if ($bank->is_default == 0 && $bank->created_by != auth()->user()->id) {

                                return response()->json([
                                    "message" => "You do not have permission to update this bank due to role restrictions."
                                ], 403);

                        }
                    }
                } else {
                    if ($bank->business_id != NULL) {
                        if (($bank->business_id != auth()->user()->business_id)) {

                            return response()->json([
                                "message" => "You do not have permission to update this bank due to role restrictions."
                            ], 403);
                        }
                    } else {
                        if ($bank->is_default == 0) {
                            if ($bank->created_by != auth()->user()->created_by) {

                                return response()->json([
                                    "message" => "You do not have permission to update this bank due to role restrictions."
                                ], 403);
                            }
                        }
                    }
                }



            return response()->json($bank, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }




    public function deleteBanksByIds(Request $request, $ids)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('bank_delete')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $idsArray = explode(',', $ids);
            $existingIds = Bank::whereIn('id', $idsArray)
                ->when(empty($request->user()->business_id), function ($query) use ($request) {
                    if ($request->user()->hasRole("superadmin")) {
                        return $query->where('banks.business_id', NULL)
                            ->where('banks.is_default', 1);
                    } else {
                        return $query->where('banks.business_id', NULL)
                            ->where('banks.is_default', 0)
                            ->where('banks.created_by', $request->user()->id);
                    }
                })
                ->when(!empty($request->user()->business_id), function ($query) use ($request) {
                    return $query->where('banks.business_id', $request->user()->business_id)
                        ->where('banks.is_default', 0);
                })
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

            $conflictingUsers = User::whereIn("bank_id", $existingIds)->get([
                'id', 'first_Name',
                'last_Name',
            ]);

            if ($conflictingUsers->isNotEmpty()) {
                return response()->json([
                    "message" => "Some users are associated with the specified banks",
                    "conflicting_users" => $conflictingUsers
                ], 409);
            }



            Bank::destroy($existingIds);


            return response()->json(["message" => "data deleted sussfully", "deleted_ids" => $existingIds], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
}
