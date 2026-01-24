<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockAdjustmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('adjust_stock');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'medicine_id' => 'required|exists:medicines,id',
            'batch_id' => 'nullable|exists:medicine_batches,id',
            'quantity' => 'required|integer|not_in:0',
            'type' => 'required|in:adjustment,damage,expiry,return,purchase',
            'notes' => 'nullable|string|max:500',
            
            // Batch data (required when adding new stock without batch_id)
            'batch_data' => 'required_without:batch_id|array',
            'batch_data.batch_number' => 'required_with:batch_data|string|max:100',
            'batch_data.expiry_date' => 'required_with:batch_data|date|after:today',
            'batch_data.cost_price_per_unit' => 'required_with:batch_data|numeric|min:0|max:999999.99',
            'batch_data.selling_price_per_unit' => 'required_with:batch_data|numeric|min:0|max:999999.99',
            'batch_data.manufacture_date' => 'nullable|date|before_or_equal:today',
            'batch_data.supplier_id' => 'nullable|exists:suppliers,id',
            'batch_data.notes' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'medicine_id.required' => 'Please select a medicine',
            'medicine_id.exists' => 'Selected medicine does not exist',
            'quantity.required' => 'Quantity is required',
            'quantity.not_in' => 'Quantity cannot be zero',
            'type.required' => 'Adjustment type is required',
            'type.in' => 'Invalid adjustment type',
            'batch_data.required_without' => 'Batch information is required when adding new stock',
            'batch_data.batch_number.required_with' => 'Batch number is required',
            'batch_data.expiry_date.required_with' => 'Expiry date is required',
            'batch_data.expiry_date.after' => 'Expiry date must be in the future',
            'batch_data.cost_price_per_unit.required_with' => 'Cost price is required',
            'batch_data.selling_price_per_unit.required_with' => 'Selling price is required',
            'batch_data.manufacture_date.before_or_equal' => 'Manufacture date cannot be in the future',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // If deducting stock, ensure batch_id is provided
            if ($this->quantity < 0 && !$this->batch_id) {
                $validator->errors()->add('batch_id', 'Batch must be specified when deducting stock');
            }

            // Validate selling price is greater than cost price
            if (isset($this->batch_data['cost_price_per_unit']) && isset($this->batch_data['selling_price_per_unit'])) {
                if ($this->batch_data['selling_price_per_unit'] < $this->batch_data['cost_price_per_unit']) {
                    $validator->errors()->add('batch_data.selling_price_per_unit', 'Selling price should be greater than cost price');
                }
            }
        });
    }
}
