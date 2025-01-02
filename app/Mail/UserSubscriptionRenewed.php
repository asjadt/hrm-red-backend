<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserSubscriptionRenewed extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $renewalAmount;

    /**
     * Create a new message instance.
     */

    public function __construct($user, $renewalAmount)
    {
        $this->user = $user;
        $this->renewalAmount = $renewalAmount;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $business = $this->user->business;
        $reseller = $business->reseller;

        $reseller_name  = trim($reseller->first_Name . " " . $reseller->middle_Name . " " . $reseller->last_Name);
        $user_name  = trim($this->user->first_Name . " " . $this->user->middle_Name . " " . $this->user->last_Name);

        return $this
            ->subject('Subscription Renewal Notification')
            ->view('email.user_subscription_renewed', [
                'resellerName' => $reseller_name,
                'userName' => $user_name,
                'userEmail' => $this->user->email,
                'registrationDate' => $this->user->created_at->format('Y-m-d'),
                'businessName' => $business->name ?? 'N/A',
                'subscriptionName' => $business->service_plan->name ?? 'N/A',
                'discountCode' => $business->discount_code ?? 'N/A',
                'renewalAmount' => $this->renewalAmount,
            ]);
    }
}
