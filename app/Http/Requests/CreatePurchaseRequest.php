<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePurchaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.medicine_id' => 'required|exists:medicines,id',
            'items.*.batch_number' => 'required|string|max:100',
            'items.*.expiry_date' => 'required|date|after:today',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.selling_price' => 'required|numeric|min:0|gte:items.*.unit_cost',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Please select a supplier',
            'supplier_id.exists' => 'Selected supplier does not exist',
            'purchase_date.required' => 'Purchase date is required',
            'items.required' => 'At least one item is required',
            'items.min' => 'At least one item is required',
            'items.*.medicine_id.required' => 'Medicine is required for each item',
            'items.*.medicine_id.exists' => 'Selected medicine does not exist',
            'items.*.batch_number.required' => 'Batch number is required for each item',
            'items.*.expiry_date.required' => 'Expiry date is required for each item',
            'items.*.expiry_date.after' => 'Expiry date must be in the future',
            'items.*.quantity.required' => 'Quantity is required for each item',
            'items.*.quantity.min' => 'Quantity must be at least 1',
            'items.*.unit_cost.required' => 'Unit cost is required for each item',
            'items.*.unit_cost.min' => 'Unit cost cannot be negative',
            'items.*.selling_price.required' => 'Selling price is required for each item',
            'items.*.selling_price.min' => 'Selling price cannot be negative',
            'items.*.selling_price.gte' => 'Selling price should be greater than or equal to unit cost',
        ];
    }
}
