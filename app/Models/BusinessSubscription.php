<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessSubscription extends Model
{
    use HasFactory;

    protected $fillable = ['business_id', 'service_plan_id', 'start_date', 'end_date', 'status', 'amount', 'paid_at', "transaction_id"];


    public function business()
    {
        return $this->belongsTo(Business::class,"business_id","id");
    }

    public function service_plan()
    {
        return $this->belongsTo(ServicePlan::class,"service_plan_id","id");
    }



}
