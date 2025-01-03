<?php

namespace App\Http\Controllers;

use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Mail\UserRegistered;
use App\Mail\UserSubscriptionRenewed;
use App\Models\ServicePlan;
use App\Models\BusinessSubscription;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Stripe\Event;

class CustomWebhookController extends WebhookController
{
    use UserActivityUtil, ErrorUtil;
    /**
     * Handle a Stripe webhook call.
     *
     * @param  Event  $event
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handleStripeWebhook(Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            // Retrieve the event data from the request body
            $payload = $request->all();

            // Log the entire payload for debugging purposes
            Log::info('Webhook Payload: ' . json_encode($payload));

            // Extract the event type
            $eventType = $payload['type'] ?? null;

            // Log the event type
            Log::info('Event Type: ' . $eventType);

          // Handle the event based on its type
        if ($eventType === 'checkout.session.completed') {
            $this->handleChargeSucceeded($payload['data']['object']);
        }
       // This handles the successful payment of a subscription invoice
   if ($eventType === 'invoice.payment_succeeded') {
    $this->handleSubscriptionPaymentSucceeded($payload['data']['object']);
   }

            // Return a response to Stripe to acknowledge receipt of the webhook
            return response()->json(['message' => 'Webhook received']);
        } catch (Exception $e) {

            DB::rollBack();
            return $this->sendError($e, 500, $request);
        }
    }

    /**
     * Handle payment succeeded webhook from Stripe.
     *
     * @param  array  $paymentCharge
     * @return void
     */
    protected function handleChargeSucceeded($data)
    {

        // Extract required data from payment charge
        $amount = $data['amount_total'] ?? null;
        $customerID = $data['customer'] ?? null;
        $metadata = $data["metadata"] ?? [];
        // Add more fields as needed

        if (!empty($metadata["our_url"]) && $metadata["our_url"] != route('stripe.webhook')) {
            return;
        }

        $user = User::where("stripe_id", $customerID)->first();


        if(!empty($metadata["service_plan_id"])) {
            $service_plan = ServicePlan::find($metadata["service_plan_id"]);
        } else {
            $service_plan = ServicePlan::find($user->business->service_plan_id);
        }

      $subscription = BusinessSubscription::create([
            'business_id' => $user->business->id,
            'service_plan_id' => $service_plan->id,
            'start_date' => now(),  // Start date of the subscription
            'end_date' => Carbon::now()->addDays($service_plan->duration_months * (env("IS_DEVELOPMENT_MODE") == "true" ? 1 : 30)),  // End date based on plan duration
            'amount' => ($amount / 100),
            'paid_at' => now(),
            'transaction_id' => $data['id'],

        ]);

            $reseller = $user->business->reseller;

            try {
                Mail::to(['kids20acc@gmail.com', 'ralashwad@gmail.com', $reseller->email])->send(new UserRegistered($user,$subscription));
            } catch (\Exception $e) {
                // Log the error with stack trace for debugging
                Log::error("Failed to send email: " . $e->getMessage(), ['exception' => $e]);
                // Optionally, handle specific actions if email fails (e.g., notify admin)
            }

    }

    protected function handleSubscriptionPaymentSucceeded($invoice)
    {
        // Check if this is a subscription payment
        if (isset($invoice['subscription'])) {
            // This is a subscription payment

            // Extract required data from the invoice
            $amount = $invoice['amount_paid'] ?? null; // Amount paid for the subscription
            $customerID = $invoice['customer'] ?? null; // Customer ID from Stripe
            $subscriptionID = $invoice['subscription'];  // Subscription ID
            $metadata = $invoice["subscription_details"]["metadata"] ?? []; // Metadata from the invoice

            // Ensure that the URL in the metadata matches, if provided
            if (!empty($metadata["our_url"]) && $metadata["our_url"] != route('stripe.webhook')) {
                return;
            }

         // Fetch the user based on the Stripe customer ID
            $user = User::where("stripe_id", $customerID)->first();

            if (!$user) {
                // If the user does not exist, log the error and stop processing
                Log::error("User not found for customer ID: $customerID");
                return;
            }

            // Fetch the service plan associated with the subscription
            if(!empty($metadata["service_plan_id"])) {
                $service_plan = ServicePlan::find($metadata["service_plan_id"]);
            } else {
                $service_plan = ServicePlan::find($user->business->service_plan_id);
            }


            if (!$service_plan) {
                // If the service plan is not found, log the error and stop processing
                Log::error("Service plan not found for user ID: $user->id");
                return;
            }


            $subscription_count = BusinessSubscription::where([
                'business_id' => $user->business->id
            ])
                ->count();
                $reseller = $user->business->reseller;

            if ($subscription_count > 1) {
                 // Create or update the business subscription
            $subscription = BusinessSubscription::create([
                'business_id' => $user->business->id,
                'service_plan_id' => $service_plan->id,
                'start_date' => now(), // Start date of the subscription
               'end_date' => Carbon::now()->addDays($service_plan->duration_months * (env("IS_DEVELOPMENT_MODE") == "true" ? 1 : 30)),  // End date based on plan
                'amount' => ($amount / 100), // Convert from cents to the full amount
                'paid_at' => now(), // Payment timestamp
                'transaction_id' => $invoice['id'], // Transaction ID from Stripe
                'subscription_id' => $subscriptionID // Store the subscription ID
            ]);
                // Send email
                try {
                    Mail::to(['kids20acc@gmail.com', 'ralashwad@gmail.com', $reseller->email])->send(new UserSubscriptionRenewed($user, $subscription));
                } catch (\Exception $e) {
                    // Log the error with stack trace for debugging
                    Log::error("Failed to send email: " . $e->getMessage(), ['exception' => $e]);
                }
            }

        } else {
            // Not a subscription payment, handle other events (e.g., one-time payment)
            Log::warning("Received non-subscription payment event.");
        }
    }

}
