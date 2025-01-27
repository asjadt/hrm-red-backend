<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentTypeCreateRequest;
use App\Http\Requests\PaymentTypeUpdateRequest;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\PaymentType;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentTypeController extends Controller
{
    use ErrorUtil,UserActivityUtil;


    public function createPaymentType(PaymentTypeCreateRequest $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            return DB::transaction(function () use ($request) {
                if (!$request->user()->hasPermissionTo('payment_type_create')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }

                $insertableData = $request->validated();

                $payment_type =  PaymentType::create($insertableData);


                return response($payment_type, 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500,$request);
        }
    }

    public function updatePaymentType(PaymentTypeUpdateRequest $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            return  DB::transaction(function () use ($request) {
                if (!$request->user()->hasPermissionTo('payment_type_update')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }
                $updatableData = $request->validated();



                $payment_type  =  tap(PaymentType::where(["id" => $updatableData["id"]]))->update(
                    collect($updatableData)->only([
        "name",
        "description",
        "is_active",
                    ])->toArray()
                )
                    // ->with("somthing")

                    ->first();
                    if(!$payment_type) {

                        return response()->json([
                            "message" => "no payment type found"
                            ],404);

                }

                return response($payment_type, 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500,$request);
        }
    }


    public function getPaymentTypes($perPage, Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('payment_type_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }



            $paymentTypeQuery = new PaymentType();

            if (!empty($request->search_key)) {
                $paymentTypeQuery = $paymentTypeQuery->where(function ($query) use ($request) {
                    $term = $request->search_key;
                    $query->where("name", "like", "%" . $term . "%");
                });
            }

            if (!empty($request->start_date)) {
                $paymentTypeQuery = $paymentTypeQuery->where('created_at', ">=", $request->start_date);
            }
            if (!empty($request->end_date)) {
                $paymentTypeQuery = $paymentTypeQuery->where('created_at', "<=", ($request->end_date . ' 23:59:59'));
            }
            $payment_types = $paymentTypeQuery->orderByDesc("id")->paginate($perPage);
            return response()->json($payment_types, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500,$request);
        }
    }

   

    public function deletePaymentTypeById($id, Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if (!$request->user()->hasPermissionTo('payment_type_delete')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            PaymentType::where([
                "id" => $id
            ])
            ->delete();

            return response()->json(["ok" => true], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500,$request);
        }
    }
}
