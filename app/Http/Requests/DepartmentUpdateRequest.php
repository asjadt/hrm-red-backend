<?php

namespace App\Http\Requests;

use App\Http\Utils\BasicUtil;
use App\Models\Department;
use App\Models\User;
use App\Models\WorkLocation;
use App\Rules\ValidateDepartment;
use App\Rules\ValidateDepartmentName;
use App\Rules\ValidateParentDepartmentId;
use App\Rules\ValidUserId;
use App\Rules\ValidWorkLocationId;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepartmentUpdateRequest extends BaseFormRequest
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
            'id' => [
                'required',
                'numeric',
            new   ValidateDepartment($all_manager_department_ids)

            ],
            'name' => [
                "required",
                "string",
                new ValidateDepartmentName($this->id)


            ],
            'work_location_id' => [
                "nullable",
                'numeric',
                new ValidWorkLocationId()
            ],
            'description' => 'nullable|string',
            'manager_id' => 'nullable|numeric',
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
