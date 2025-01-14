<?php

namespace App\Http\Requests;

use App\Http\Utils\BasicUtil;

use App\Rules\ValidateDepartmentName;
use App\Rules\ValidateParentDepartmentId;
use App\Rules\ValidUserId;
use App\Rules\ValidWorkLocationId;


class DepartmentCreateRequest extends BaseFormRequest
{
    use BasicUtil;
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
        $all_manager_department_ids = $this->get_all_departments_of_manager();
        return [
            'name' => [
                "required",
                "string",
                new ValidateDepartmentName(NULL)


            ],
            'work_location_id' => [
                "nullable",
                'numeric',
                new ValidWorkLocationId()
            ],
            'description' => 'nullable|string',

            'manager_id' => [
                'nullable',
                'numeric',
                  new ValidUserId($all_manager_department_ids)

            ],

            'parent_id' => [
                'required',
                'numeric',
                new ValidateParentDepartmentId($all_manager_department_ids)
            ],

        ];
    }
}
