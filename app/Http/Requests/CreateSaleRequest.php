<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSaleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // User must be authenticated and have permission to create sales
        return $this->user() && (
            $this->user()->hasRole('owner') ||
            $this->user()->hasRole('pharmacist') ||
            $this->user()->hasRole('cashier')
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'items' => 'required|array|min:1',
            'items.*.medicine_id' => 'required|exists:medicines,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'payment_method' => 'required|in:cash,mpesa,card,mixed',
            'amount_tendered' => 'nullable|numeric|min:0',
            'change_given' => 'nullable|numeric|min:0',
            'mpesa_transaction_id' => 'nullable|string|max:255',
            'mpesa_phone' => 'nullable|string|max:20',
            'mpesa_response' => 'nullable|array',
            'card_last_four' => 'nullable|string|max:4',
            'card_type' => 'nullable|string|max:50',
            'card_transaction_id' => 'nullable|string|max:255',
            'reference_number' => 'nullable|string|max:255',
            'payments' => 'required_if:payment_method,mixed|array',
            'payments.*.method' => 'required_with:payments|in:cash,mpesa,card',
            'payments.*.amount' => 'required_with:payments|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'items.required' => 'At least one item is required to create a sale.',
            'items.*.medicine_id.required' => 'Medicine ID is required for each item.',
            'items.*.medicine_id.exists' => 'Selected medicine does not exist.',
            'items.*.quantity.required' => 'Quantity is required for each item.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
            'payment_method.required' => 'Payment method is required.',
            'payment_method.in' => 'Invalid payment method selected.',
            'payments.required_if' => 'Payment details are required when using mixed payment method.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_id' => $this->user()->id,
        ]);
    }
}
