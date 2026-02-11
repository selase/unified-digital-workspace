<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Http\Requests;

use App\Modules\IncidentManagement\Models\IncidentComment;
use App\Modules\IncidentManagement\Models\IncidentProgressReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IncidentAttachmentStoreRequest extends FormRequest
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
            'file' => ['required', 'file', 'max:10240'],
            'comment_id' => ['nullable', 'integer', Rule::exists(IncidentComment::class, 'id')],
            'progress_report_id' => ['nullable', 'integer', Rule::exists(IncidentProgressReport::class, 'id')],
        ];
    }
}
