<?php

namespace App\Http\Controllers;

use App\Mail\UserPaymentFailed;
use App\Mail\UserRegistered;
use App\Models\Business;
use App\Models\ServicePlan;
use App\Models\SystemSetting;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Stripe\Checkout\Session;
use Stripe\Stripe;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stripe\WebhookEndpoint;

class SubscriptionController extends Controller
{
    public function redirectUserToStripe(Request $request)
    {
        $id = $request->id;

        // Check if the string is at least 20 characters long to ensure it has enough characters to remove
        if (strlen($id) >= 20) {
            // Remove the first ten characters and the last ten characters
            $trimmed_id = substr($id, 10, -10);
            // $trimmedId now contains the string with the first ten and last ten characters removed
        } else {
            throw new Exception("invalid id");
        }
        $business = Business::findOrFail($trimmed_id);
        $user = User::findOrFail($business->owner_id);
        Auth::login($user);

        $systemSetting = SystemSetting::first();

        if (empty($systemSetting)) {
            return response()->json([
                "message" => "self registration is not supported"
            ], 403);
        }
        if (empty($systemSetting->self_registration_enabled)) {
            return response()->json([
                "message" => "self registration is not supported"
            ], 403);
        }

        Stripe::setApiKey($systemSetting->STRIPE_SECRET);
        Stripe::setClientId($systemSetting->STRIPE_KEY);

        // Retrieve all webhook endpoints from Stripe
        $webhookEndpoints = WebhookEndpoint::all();

        // Check if a webhook endpoint with the desired URL already exists
        $existingEndpoint = collect($webhookEndpoints->data)->first(function ($endpoint) {
            return $endpoint->url === route('stripe.webhook'); // Replace with your actual endpoint URL
        });
        if (!$existingEndpoint) {
            // Create the webhook endpoint
            $webhookEndpoint = WebhookEndpoint::create([
                'url' => route('stripe.webhook'),
                'enabled_events' => [
                    'checkout.session.completed', // One-time payments
                    'invoice.payment_succeeded', // Subscription payments
                ], // Specify the events you want to listen to
            ]);
        }





        $service_plan = ServicePlan::where([
            "id" => $business->service_plan_id
        ])
            ->first();


        if (!$service_plan) {
            return response()->json([
                "message" => "no service plan found"
            ], 404);
        }


        if (empty($user->stripe_id)) {
            $stripe_customer = \Stripe\Customer::create([
                'email' => $user->email,
            ]);

            $user->stripe_id = $stripe_customer->id;
            $user->save();
        }



        $session_data = [
            'payment_method_types' => ['card'],
            'metadata' => [
                'our_url' => route('stripe.webhook'),
                'service_plan_id' => $service_plan->id, // Add service plan ID
                'service_plan_name' => $service_plan->name, // Add service plan name

            ],
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => 'GBP',
                        'product_data' => [
                            'name' => 'Your Service set up amount',
                        ],
                        'unit_amount' => $service_plan->set_up_amount * 100, // Amount in cents
                    ],
                    'quantity' => 1,
                ],
                [
                    'price_data' => [
                        'currency' => 'GBP',
                        'product_data' => [
                            'name' => 'Your Service monthly amount',
                        ],
                        'unit_amount' => $service_plan->price * 100, // Amount in cents
                        'recurring' => [
                            'interval' => "month", // Recur monthly
                            'interval_count' => $service_plan->duration_months, // Adjusted duration
                        ],
                    ],
                    'quantity' => 1,
                ]

            ],
            'subscription_data' => [
                'metadata' => [
                    'our_url' => route('stripe.webhook'),
                    'service_plan_id' => $service_plan->id,
                    'service_plan_name' => $service_plan->name,
                ],
            ],
            'customer' => $user->stripe_id  ?? null,

            'mode' => 'subscription',
            'success_url' => route('subscription.success_payment', ['user_id' => base64_encode($user->id)]),
            'cancel_url' => route('subscription.failed_payment', ['user_id' => base64_encode($user->id)]),
        ];




        // Add discount line item only if discount amount is greater than 0 and not null
        if (!empty($business->service_plan_discount_amount) && $business->service_plan_discount_amount > 0) {

            // try {
            //     $coupon = \Stripe\Coupon::retrieve($business->service_plan_discount_code, []);
            //     $coupon->amount_off = $business->service_plan_discount_amount * 100;
            //     $coupon->save();
            // } catch (\Stripe\Exception\InvalidRequestException $e) {
            //    return $e->getMessage();
            //     $coupon = \Stripe\Coupon::create([
            //         'amount_off' => $business->service_plan_discount_amount * 100, // Amount in cents
            //         'currency' => 'GBP', // The currency
            //         'duration' => 'once', // Can be once, forever, or repeating
            //         'name' => $business->service_plan_discount_code, // Coupon name
            //         'id' => $business->service_plan_discount_code, // Coupon code
            //     ]);
            // }

            $coupon = \Stripe\Coupon::create([
                'amount_off' => $business->service_plan_discount_amount * 100, // Amount in cents
                'currency' => 'GBP', // The currency
                'duration' => 'once', // Can be once, forever, or repeating
                'name' => $business->service_plan_discount_code, // Coupon name
            ]);

            $session_data["discounts"] =  [ // Add the discount information here
                [
                    'coupon' => $coupon, // Use coupon ID if created
                ],
            ];
        }

        $session = Session::create($session_data);

        return redirect()->to($session->url);
    }



    public function stripePaymentSuccess(Request $request)
    {
        // $user_id = base64_decode($request->query('user_id'));

        // // Validate the decoded user_id
        // $user = User::find($user_id);
        // if (!$user) {
        //     return response()->json(['message' => 'User not found'], 404);
        // }
        // $reseller = $user->business->reseller;
        // try {
        //     Mail::to(['kids20acc@gmail.com', 'ralashwad@gmail.com', $reseller->email])->send(new UserRegistered($user));
        // } catch (\Exception $e) {
        //     // Log the error with stack trace for debugging
        //     Log::error("Failed to send email: " . $e->getMessage(), ['exception' => $e]);
        //     // Optionally, handle specific actions if email fails (e.g., notify admin)
        // }

        return redirect()->to(env("FRONT_END_URL") . "/verify/business?status=success");
    }


    public function stripePaymentFailed(Request $request)
    {
        $user_id = base64_decode($request->query('user_id'));

        // Validate the decoded user_id
        $user = User::find($user_id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        $reseller = $user->business->reseller;

        if (env("SEND_EMAIL") == true) {
            try {
                Mail::to(['kids20acc@gmail.com', 'ralashwad@gmail.com', $reseller->email])->send(new UserPaymentFailed($user));
            } catch (\Exception $e) {
                // Log the error with stack trace for debugging
                Log::error("Failed to send email: " . $e->getMessage(), ['exception' => $e]);
            }
        }


        return redirect()->to(env("FRONT_END_URL") . "/verify/business?status=failed");
    }
}
