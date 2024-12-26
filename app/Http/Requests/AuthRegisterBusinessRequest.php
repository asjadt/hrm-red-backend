<?php

namespace App\Http\Requests;

use App\Models\Department;
use App\Models\User;
use App\Rules\DayValidation;
use App\Rules\SomeTimes;
use App\Rules\TimeOrderRule;
use App\Rules\TimeValidation;
use App\Rules\ValidateModuleIds;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;

class AuthRegisterBusinessRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {

        $rules = [
            'user.first_Name' => 'required|string|max:255',
            'user.middle_Name' => 'nullable|string|max:255',
            'user.last_Name' => 'required|string|max:255',
            // 'user.email' => 'required|string|email|indisposable|max:255|unique:users,email',
            'user.email' => 'required|string|email|max:255|unique:users,email',
            'user.password' => 'nullable|string|min:6',
            'user.send_password' => 'required|boolean',

            'user.phone' => 'nullable|string',
            'user.image' => 'nullable|string',


            'user.gender' => 'nullable|string|in:male,female,other',


            // 'user.address_line_1' => 'nullable|string',
            // 'user.address_line_2' => 'nullable|string',
            // 'user.country' => 'nullable|string',
            // 'user.city' => 'nullable|string',
            // 'user.postcode' => 'nullable|string',

            // 'user.lat' => 'nullable|string',
            // 'user.long' => 'nullable|string',

            'business.name' => 'required|string|max:255',
            'business.start_date' => 'required|date|before_or_equal:today',

            'business.trail_end_date' => 'nullable|date',






            'business.about' => 'nullable|string',
            'business.web_page' => 'nullable|string',
            'business.identifier_prefix' => 'nullable|string',
            'business.pin_code' => 'nullable|string',



            'business.phone' => 'nullable|string',
            // 'business.email' => 'required|string|email|indisposable|max:255|unique:businesses,email',
            'business.email' => 'nullable|string|email|max:255|unique:businesses,email',
            'business.additional_information' => 'nullable|string',

            'business.lat' => 'nullable|string',
            'business.long' => 'nullable|string',
            'business.country' => 'required|string',
            'business.city' => 'required|string',

            'business.currency' => 'nullable|string',

            'business.postcode' => 'required|string',
            'business.address_line_1' => 'required|string',
            'business.address_line_2' => 'nullable|string',


            // 'business.logo' => 'nullable|string',

            // 'business.image' => 'nullable|string',

            // 'business.images' => 'nullable|array',
            // 'business.images.*' => 'nullable|string',



            'business.pension_scheme_registered' => 'required|boolean',
            'business.pension_scheme_name' => 'nullable|required_if:business.pension_scheme_registered,1|string',
            'business.pension_scheme_letters' => 'present|array',

            'business.is_self_registered_businesses' => 'required|boolean',


            'business.number_of_employees_allowed' => 'nullable|integer',

            // "business.active_module_ids" => "present|array",
            // "business.active_module_ids.*" => [
            //     "numeric",
            //     new ValidateModuleIds()
            // ],





            // 'work_shift.name' => 'required|string',
            // 'work_shift.description' => 'nullable|string',
            // 'work_shift.type' => 'required|string|in:regular,scheduled',
            // 'work_shift.start_date' => 'nullable|date',
            // 'work_shift.end_date' => 'nullable|date|after_or_equal:start_date',
            // 'work_shift.break_type' => 'required|string|in:paid,unpaid',
            // 'work_shift.break_hours' => 'required|numeric',
            // 'work_shift.details' => 'required|array|min:7|max:7',
            // 'work_shift.details.*.day' => 'required|numeric|between:0,6',
            // 'work_shift.details.*.is_weekend' => 'required|boolean',
            // 'work_shift.details.*.start_at' => [
            //     'nullable',
            //     'date_format:H:i:s',
            //     function ($attribute, $value, $fail) {
            //         $index = explode('.', $attribute)[1]; // Extract the index from the attribute name
            //         $isWeekend = request('details')[$index]['is_weekend'] ?? false;

            //         if (request('type') === 'scheduled' && $isWeekend == 0 && empty($value)) {
            //             $fail("The $attribute field is required when type is scheduled and is_weekend is 0.");
            //         }
            //     },
            // ],
            // 'work_shift.details.*.end_at' => [
            //     'nullable',
            //     'date_format:H:i:s',
            //     function ($attribute, $value, $fail) {
            //         $index = explode('.', $attribute)[1]; // Extract the index from the attribute name
            //         $isWeekend = request('details')[$index]['is_weekend'] ?? false;

            //         if (request('type') === 'scheduled' && $isWeekend == 0 && empty($value)) {
            //             $fail("The $attribute field is required when type is scheduled and is_weekend is 0.");
            //         }
            //     },
            // ],




            "times" => "required|array",
             "times.*.day" => 'required|numeric',
             "times.*.is_weekend" => ['required',"boolean"],

             'times.*.start_at' => [
                'nullable',
                'date_format:H:i:s',
                function ($attribute, $value, $fail) {
                    $index = explode('.', $attribute)[1]; // Extract the index from the attribute name
                    $isWeekend = request('details')[$index]['is_weekend'] ?? false;

                    if (request('type') === 'scheduled' && $isWeekend == 0 && empty($value)) {
                        $fail("The $attribute field is required when type is scheduled and is_weekend is 0.");
                    }
                },
            ],
            'times.*.end_at' => [
                'nullable',
                'date_format:H:i:s',
                function ($attribute, $value, $fail) {
                    $index = explode('.', $attribute)[1]; // Extract the index from the attribute name
                    $isWeekend = request('details')[$index]['is_weekend'] ?? false;

                    if (request('type') === 'scheduled' && $isWeekend == 0 && empty($value)) {
                        $fail("The $attribute field is required when type is scheduled and is_weekend is 0.");
                    }
                },
            ],

            'business.service_plan_id' => 'required|numeric|exists:service_plans,id',

            'business.reseller_id'=> "nullable|numeric|exists:users,id"



        ];


        if (request()->input('business.is_self_registered_businesses')) {
            $rules['business.service_plan_discount_code'] = 'nullable|string';
            unset($rules['business.trail_end_date']);

        }


        return $rules;


    }



    public function messages()
    {
        return [
            'user.first_Name.required' => 'The first name field is required.',
            'user.last_Name.required' => 'The last name field is required.',
            'user.email.required' => 'The email field is required.',
            'user.email.email' => 'The email must be a valid email address.',
            'user.email.unique' => 'The email has already been taken.',
            'user.password.min' => 'The password must be at least :min characters.',
            'user.send_password.required' => 'The send password field is required.',
            // 'user.phone.required' => 'The phone field is required.',
            'user.image.string' => 'The image must be a string.',
            // Add custom messages for other fields as needed
            'user.gender.in' => 'The :attribute field must be in "male","female","other".',

            'business.name.required' => 'The name field is required.',
            'business.about.string' => 'The about must be a string.',
            'business.web_page.string' => 'The web page must be a string.',
            'business.identifier_prefix.string' => 'The identifier prefix field must be a string.',
              'business.pin_code.string' => 'The pin code field must be a string.',



            'business.phone.string' => 'The phone must be a string.',
            // 'business.email.required' => 'The email field is required.',
            'business.email.email' => 'The email must be a valid email address.',

            'business.email.unique' => 'The email has already been taken.',
            'business.lat.required' => 'The latitude field is required.',
            'business.long.required' => 'The longitude field is required.',
            'business.country.required' => 'The country field is required.',
            'business.city.required' => 'The city field is required.',
            'business.currency.required' => 'The currency field is required.',
            'business.currency.string' => 'The currency must be a string.',
            'business.postcode.required' => 'The postcode field is required.',
            'business.address_line_1.required' => 'The address line 1 field is required.',
            'business.address_line_2.string' => 'The address line 2 must be a string.',
            'business.logo.string' => 'The logo must be a string.',
            'business.image.string' => 'The image must be a string.',
            'business.images.array' => 'The images must be an array.',
            'business.images.*.string' => 'The image must be a string.',



            'work_shift.name.required' => 'The work shift name field is required.',
            'work_shift.description.string' => 'The work shift description must be a string.',
            'work_shift.type.required' => 'The work shift type field is required.',
            'work_shift.type.string' => 'The work shift type must be a string.',
            'work_shift.type.in' => 'The work shift type must be either "regular" or "scheduled".',
            'work_shift.start_date.required' => 'The work shift start date field is required.',
            'work_shift.start_date.date' => 'The work shift start date must be a valid date.',
            'work_shift.end_date.required' => 'The work shift end date field is required.',
            'work_shift.end_date.date' => 'The work shift end date must be a valid date.',
            'work_shift.end_date.after_or_equal' => 'The work shift end date must be equal to or after the start date.',
            'work_shift.departments.present' => 'The work shift departments must be present and in array format.',

            'work_shift.users.array' => 'The work shift users must be in array format.',
            'work_shift.users.*.numeric' => 'Each user ID must be a numeric value.',
            'work_shift.details.required' => 'The work shift details field is required.',
            'work_shift.details.array' => 'The work shift details must be in array format.',
            'work_shift.details.min' => 'The work shift details must have at least 7 items.',
            'work_shift.details.max' => 'The work shift details must not exceed 7 items.',
            'work_shift.details.*.day.required' => 'Each work shift detail must have a day value.',
            'work_shift.details.*.day.numeric' => 'The day value must be a numeric value.',
            'work_shift.details.*.day.between' => 'The day value must be between 0 and 6.',
            'work_shift.details.*.is_weekend.required' => 'Each work shift detail must have an is_weekend value.',
            'work_shift.details.*.is_weekend.boolean' => 'The is_weekend value must be a boolean.',
            'work_shift.details.*.start_at.nullable' => 'The start_at field must be nullable.',
            'work_shift.details.*.start_at.date_format' => 'The start_at value must be a valid time format (H:i:s).',
            'work_shift.details.*.end_at.nullable' => 'The end_at field must be nullable.',
            'work_shift.details.*.end_at.date_format' => 'The end_at value must be a valid time format (H:i:s).',


        ];
    }


}
