<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Stripe\Stripe;

class Business extends Model
{
    use HasFactory;
    protected $appends = ['is_subscribed'];

    protected $fillable = [
        "name",
        "start_date",
        "trail_end_date",
        "about",
        "web_page",
        // "identifier_prefix",
        // "pin_code",
        // 'enable_auto_business_setup',
        "phone",
        "email",
        "additional_information",
        "address_line_1",
        "address_line_2",
        "lat",
        "long",
        "country",
        "city",
        "currency",
        "postcode",
        "logo",
        "image",
        "background_image",
        "status",
        "is_active",
        "is_self_registered_businesses",

        "service_plan_id",
        "service_plan_discount_code",
        "service_plan_discount_amount",


        "pension_scheme_registered",
        "pension_scheme_name",
        "pension_scheme_letters",
        "number_of_employees_allowed",


        "owner_id",
        'created_by',
        "reseller_id"

    ];

    protected $casts = [
        'pension_scheme_letters' => 'array',
    ];

    protected $hidden = [
        'pin_code'
    ];

    // public function emailSettings()
    // {
    //     return $this->hasOne(BusinessEmailSetting::class);
    // }

    public function reseller()
    {
        return $this->hasOne(User::class,"id","reseller_id");
    }

    private function isValidSubscription($subscription)
    {
        if (!$subscription) return false; // No subscription

        // Return false if start_date or end_date is empty
        if (empty($subscription->start_date) || empty($subscription->end_date)) return false;

        $startDate = Carbon::parse($subscription->start_date)->startOfDay();
        $endDate = Carbon::parse($subscription->end_date)->endOfDay();
        $today = Carbon::today(); // Get today's date (start of day)

     // Return false if the subscription hasn't started
    if ($startDate->isFuture()){
        return false;
    };

    // Return false if the subscription has expired (end_date is before today)
    if ($endDate->isPast() && !$endDate->isSameDay($today)){
        return false;
    };

     return true;
    }

    private function isTrailDateValid($trail_end_date)
    {
        // Return false if trail_end_date is empty or null
        if (empty($trail_end_date)) {
            return false;
        }

        // Parse the date and check validity
        $parsedDate = Carbon::parse($trail_end_date);
        return !( $parsedDate->isPast() && !$parsedDate->isToday() );
    }

    public function getIsSubscribedAttribute($value)
    {

        $user = auth()->user();
        if (empty($user)) {
            return 0;
        }


        // Return 0 if the business is not active
        if (!$this->is_active) {
            return 0;
        }

        // Check for self-registered businesses
        if ($this->is_self_registered_businesses) {
            $validTrailDate = $this->isTrailDateValid($this->trail_end_date);
            $latest_subscription = BusinessSubscription::where('business_id', $this->id)
                ->where('service_plan_id', $this->service_plan_id)
                ->orderByDesc("business_subscriptions.id")
                ->first();

            // If no valid subscription and no valid trail date, return 0
            if (!$this->isValidSubscription($latest_subscription) && !$validTrailDate) {
                return 0;
            }

        } else {
            // For non-self-registered businesses
            // If the trail date is empty or invalid, return 0
            if (!$this->isTrailDateValid($this->trail_end_date)) {
                return 0;
            }
        }

        return 1;
    }




    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id', 'id');
    }
    public function users()
    {
        return $this->hasMany(User::class, 'business_id', 'id');
    }

    public function service_plan()
    {
        return $this->belongsTo(ServicePlan::class, 'service_plan_id', 'id');
    }

    public function subscription()
    {
        return $this->hasOne(BusinessSubscription::class, 'business_id', 'id')
        ->orderByDesc("business_subscriptions.id")
            ;
    }

    public function current_subscription()
    {
        return $this->hasOne(BusinessSubscription::class, 'business_id', 'id')
         ->where('business_subscriptions.service_plan_id', $this->service_plan_id)
         ->orderByDesc("business_subscriptions.id")
            ;
    }

    public function getStripeSubscriptionEnabledAttribute()
    {
        $systemSetting = SystemSetting::
        // where("reseller_id", $this->reseller_id)
            first();

        if (empty($systemSetting)) {
            return false;
        }
        if (empty($systemSetting->self_registration_enabled)) {
            return false;
        }

        Stripe::setApiKey($systemSetting->STRIPE_SECRET);
        Stripe::setClientId($systemSetting->STRIPE_KEY);


        if (!empty($this->owner->stripe_id)) {
             // Fetch active subscriptions
    $subscriptions = \Stripe\Subscription::all([
        'customer' => $this->owner->stripe_id,
        'status' => 'active',
    ]);
            $subscriptions_not_ending = [];
             // Loop through subscriptions and check cancel_at_period_end
    foreach ($subscriptions->data as $subscription) {
        if ($subscription->cancel_at_period_end === false) {
            // Add the subscription to the list if it's not set to cancel
            $subscriptions_not_ending[] = $subscription;
        }
    }

    // Return the count of subscriptions that will not end
    return count($subscriptions_not_ending) > 0;
        }

        return false;
    }


    public function default_work_shift()
    {
        return $this->hasOne(WorkShift::class, 'business_id', 'id')->where('is_business_default', 1);
    }


    public function creator()
    {
        return $this->belongsTo(User::class, "created_by", "id");
    }


    public function times()
    {
        return $this->hasMany(BusinessTime::class, 'business_id', 'id');
    }


    public function active_modules()
    {
        return $this->hasMany(BusinessModule::class, 'business_id', 'id');
    }





    // Define your model properties and relationships here

    protected static function boot()
    {
        parent::boot();

        // Listen for the "deleting" event on the Candidate model
        static::deleting(function ($item) {
            // Call the deleteFiles method to delete associated files
            $item->deleteFiles();
        });
    }

    /**
     * Delete associated files.
     *
     * @return void
     */



    public function deleteFiles()
    {
        // Get the file paths associated with the candidate
        $filePaths = $this->pension_scheme_letters;

        // Iterate over each file and delete it
        foreach ($filePaths as $filePath) {
            if (File::exists(public_path($filePath->file))) {
                File::delete(public_path($filePath->file));
            }
        }
    }
}
