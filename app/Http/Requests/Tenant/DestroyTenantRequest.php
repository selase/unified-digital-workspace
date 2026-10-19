<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DestroyTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('admin.tenants.delete');
    }

    /**
     * `backup` (default) dumps a dedicated tenant database before dropping it;
     * `delete` drops it with no backup. Shared tenants have no database of their own.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'database_action' => ['nullable', Rule::in(['backup', 'delete'])],
        ];
    }

    public function messages(): array
    {
        return [
            'database_action.in' => 'The database action must be either "backup" or "delete".',
        ];
    }

    public function shouldBackupDatabase(): bool
    {
        return $this->input('database_action', 'backup') !== 'delete';
    }
}
