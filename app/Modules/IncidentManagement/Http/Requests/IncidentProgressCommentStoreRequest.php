<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class IncidentProgressCommentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string'],
        ];
    }
}
