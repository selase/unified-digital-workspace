<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'comment_id' => ['nullable', 'integer', 'exists:incident_comments,id'],
            'progress_report_id' => ['nullable', 'integer', 'exists:incident_progress_reports,id'],
        ];
    }
}
