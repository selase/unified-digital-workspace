<?php

declare(strict_types=1);

namespace App\Modules\Memos\Http\Requests;

use App\Models\User;
use App\Modules\HrmsCore\Models\Organization\Department;
use App\Modules\HrmsCore\Models\Organization\Directorate;
use App\Modules\HrmsCore\Models\Organization\Unit;
use App\Modules\Memos\Models\MemoRecipient;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

final class MemoShareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('memos.share');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $types = [
            MemoRecipient::TYPE_USER,
            MemoRecipient::TYPE_UNIT,
            MemoRecipient::TYPE_DEPARTMENT,
            MemoRecipient::TYPE_DIRECTORATE,
            MemoRecipient::TYPE_TENANT,
        ];
        $roles = ['to', 'cc'];

        return [
            'recipients' => ['required', 'array', 'min:1'],
            'recipients.*.type' => ['required', 'string', Rule::in($types)],
            'recipients.*.role' => ['nullable', 'string', Rule::in($roles)],
            'recipients.*.id' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, callable $fail): void {
                    $index = (int) explode('.', $attribute)[1];
                    $type = $this->input("recipients.{$index}.type");

                    $this->validateRecipient($type, $value, $fail);
                },
            ],
        ];
    }

    private function validateRecipient(?string $type, mixed $value, callable $fail): void
    {
        if (! $type) {
            return;
        }

        if ($type === MemoRecipient::TYPE_TENANT) {
            return;
        }

        if ($value === null || $value === '') {
            $fail('Recipient id is required.');

            return;
        }

        if (! is_numeric($value)) {
            $fail('Recipient id must be numeric.');

            return;
        }

        $id = (int) $value;
        $tenant = app(TenantContext::class)->getTenant();

        if ($type === MemoRecipient::TYPE_USER) {
            if (! User::query()->whereKey($id)->exists()) {
                $fail('Recipient user not found.');

                return;
            }

            if ($tenant && ! $tenant->users()->where('users.id', $id)->exists()) {
                $fail('Recipient user does not belong to this tenant.');
            }

            return;
        }

        $tenantConnection = config('database.default_tenant_connection', 'tenant');
        $table = match ($type) {
            MemoRecipient::TYPE_UNIT => 'hrms_units',
            MemoRecipient::TYPE_DEPARTMENT => 'hrms_departments',
            MemoRecipient::TYPE_DIRECTORATE => 'hrms_directorates',
            default => null,
        };

        if (! $table) {
            return;
        }

        if (! Schema::connection($tenantConnection)->hasTable($table)) {
            $fail('Recipient type is not available.');

            return;
        }

        $exists = match ($type) {
            MemoRecipient::TYPE_UNIT => Unit::query()->whereKey($id)->exists(),
            MemoRecipient::TYPE_DEPARTMENT => Department::query()->whereKey($id)->exists(),
            MemoRecipient::TYPE_DIRECTORATE => Directorate::query()->whereKey($id)->exists(),
            default => true,
        };

        if (! $exists) {
            $fail('Recipient not found.');
        }
    }
}
