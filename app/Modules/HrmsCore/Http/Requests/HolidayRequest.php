<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests;

use App\Modules\HrmsCore\Models\Leave\Holiday;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class HolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('hrms.leave.manage_holidays');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();
        $tenantId = (string) (session('active_tenant_id') ?? $user?->tenant_id);
        $holiday = $this->route('holiday');
        $holidayId = $holiday instanceof Holiday ? $holiday->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Holiday::class, 'name')
                    ->where('tenant_id', $tenantId)
                    ->where('date', (string) $this->input('date'))
                    ->ignore($holidayId),
            ],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_recurring' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Holiday name is required.',
            'name.unique' => 'A holiday with this name on the selected date already exists.',
            'date.required' => 'Holiday date is required.',
            'date.date' => 'Holiday date must be a valid calendar date.',
        ];
    }
}
