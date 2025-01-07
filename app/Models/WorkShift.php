<?php

namespace App\Models;







use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkShift extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        "break_type",
        "break_hours",
        'type',
        "description",

        'is_business_default',
        'is_personal',

        "is_default",
        "is_active",
        "business_id",
        "created_by"
    ];

    protected $dates = ['start_date',
    'end_date',];




    public function details(){
        return $this->hasMany(WorkShiftDetail::class,'work_shift_id', 'id');
    }


    public function departments() {
        return $this->belongsToMany(Department::class, 'department_work_shifts', 'work_shift_id', 'department_id');
    }

    public function work_locations() {
        return $this->belongsToMany(WorkLocation::class, 'work_shift_locations', 'work_shift_id', 'work_location_id');
    }


    public function users() {
        return $this->belongsToMany(User::class, 'user_work_shifts', 'work_shift_id', 'user_id');
    }




}
