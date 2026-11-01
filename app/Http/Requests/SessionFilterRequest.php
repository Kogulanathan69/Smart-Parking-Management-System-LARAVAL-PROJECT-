<?php

namespace App\Http\Requests;

use App\Enums\FuelType;
use App\Models\ParkingSession;
use App\Support\SessionFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validated parking-record filters (category, fuel, status, date range + plate, zone, package, payment status).
 * `status` accepts one value or a comma-separated list.
 */
class SessionFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->query('status'))) {
            $this->merge(['status' => array_values(array_filter(explode(',', $this->query('status'))))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'vehicle_category_id' => ['nullable', 'integer', 'exists:vehicle_categories,id'],
            'vehicle_category' => ['nullable', 'string', 'exists:vehicle_categories,code'],
            'fuel_type' => ['nullable', Rule::enum(FuelType::class)],
            'status' => ['nullable', 'array', 'max:4'],
            'status.*' => [Rule::in([ParkingSession::ACTIVE, ParkingSession::EXIT_AUTHORIZED, ParkingSession::COMPLETED, ParkingSession::CANCELLED])],
            'date' => ['nullable', 'date_format:Y-m-d', 'prohibits:from,to'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'date_field' => ['nullable', Rule::in(SessionFilters::DATE_FIELDS)],
            'plate' => ['nullable', 'string', 'max:30'],
            'q' => ['nullable', 'string', 'max:30'],
            'zone_id' => ['nullable', 'integer', 'exists:parking_zones,id'],
            'package_id' => ['nullable', 'integer', 'exists:parking_packages,id'],
            'payment_status' => ['nullable', Rule::in(SessionFilters::PAYMENT_STATUSES)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
