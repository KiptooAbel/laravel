<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMedicineRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('edit_medicine');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $medicineId = $this->route('medicine')->id;

        return [
            'name' => 'sometimes|required|string|max:255',
            'generic_name' => 'nullable|string|max:255',
            'barcode' => "nullable|string|unique:medicines,barcode,{$medicineId}|max:255",
            'category' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'manufacturer' => 'nullable|string|max:255',
            'unit_price' => 'sometimes|required|numeric|min:0|max:999999.99',
            'cost_price' => 'sometimes|required|numeric|min:0|max:999999.99',
            'reorder_level' => 'sometimes|required|integer|min:0|max:10000',
            'unit_of_measure' => 'sometimes|required|string|max:50|in:piece,bottle,box,strip,vial,tube,sachet,packet',
            'requires_prescription' => 'nullable|boolean',
            'is_controlled' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Medicine name is required',
            'unit_price.min' => 'Selling price must be greater than or equal to 0',
            'cost_price.min' => 'Cost price must be greater than or equal to 0',
            'unit_of_measure.in' => 'Invalid unit of measure selected',
            'barcode.unique' => 'This barcode is already in use',
        ];
    }
}
