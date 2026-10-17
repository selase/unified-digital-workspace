<?php

declare(strict_types=1);

namespace App\Modules\Forms\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Forms\Models\FormDefinition;
use App\Modules\Forms\Models\FormField;
use App\Modules\Forms\Models\FormSubmission;
use App\Modules\Forms\Models\PollVote;
use App\Modules\HrmsCore\Models\Organization\Department;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class FormsWebController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('forms.view'), 403);

        $type = $request->query('type');
        $status = $request->query('status');
        $search = $request->query('search');

        $query = FormDefinition::query()
            ->with(['creator:uuid,first_name,last_name', 'department:id,name'])
            ->withCount(['submissions', 'pollVotes']);

        if ($type) {
            $query->where('type', $type);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $forms = $query->latest()->paginate(15)->withQueryString();

        $metrics = [
            'total_forms' => FormDefinition::query()->count(),
            'published_forms' => FormDefinition::query()->where('status', 'published')->count(),
            'total_submissions' => FormSubmission::query()->count(),
            'pending_reviews' => FormSubmission::query()->where('status', 'submitted')->count(),
        ];

        return view('forms::index', [
            'forms' => $forms,
            'metrics' => $metrics,
            'currentType' => $type,
            'currentStatus' => $status,
            'search' => $search,
        ]);
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()?->can('forms.create'), 403);

        $departments = Department::query()->orderBy('name')->get(['id', 'name']);

        return view('forms::create', [
            'departments' => $departments,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('forms.create'), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'string', Rule::in(['general', 'hr_request', 'survey', 'live_poll'])],
            'target_audience' => ['required', 'string', Rule::in(['public', 'internal_only', 'departmental'])],
            'target_department_id' => ['nullable', 'integer', Rule::exists('hrms_departments', 'id')],
            'allow_anonymous' => ['nullable', 'boolean'],
            'single_response_per_user' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.field_name' => ['nullable', 'string', 'max:100'],
            'fields.*.field_type' => ['required', 'string', Rule::in(['text', 'textarea', 'number', 'select', 'radio', 'checkbox', 'date', 'file'])],
            'fields.*.is_required' => ['nullable', 'boolean'],
            'fields.*.options' => ['nullable', 'string'],
        ]);

        $slug = $validated['slug'] ?: Str::slug($validated['title']);

        // Prevent duplicate slug within tenant
        $originalSlug = $slug;
        $counter = 1;
        while (FormDefinition::where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$counter}";
            $counter++;
        }

        $form = DB::transaction(function () use ($validated, $slug, $user): FormDefinition {
            $formDefinition = FormDefinition::create([
                'title' => $validated['title'],
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
                'type' => $validated['type'],
                'target_audience' => $validated['target_audience'],
                'target_department_id' => $validated['target_department_id'] ?? null,
                'status' => 'published', // default published on creation
                'allow_anonymous' => (bool) ($validated['allow_anonymous'] ?? false),
                'single_response_per_user' => (bool) ($validated['single_response_per_user'] ?? false),
                'expires_at' => $validated['expires_at'] ?? null,
                'created_by' => (string) $user->uuid,
            ]);

            $order = 1;
            foreach ($validated['fields'] as $fieldData) {
                $fieldName = ! empty($fieldData['field_name'])
                    ? Str::slug($fieldData['field_name'], '_')
                    : Str::slug($fieldData['label'], '_');

                $options = null;
                if (! empty($fieldData['options'])) {
                    $options = array_map('trim', explode("\n", (string) $fieldData['options']));
                    $options = array_values(array_filter($options));
                }

                FormField::create([
                    'tenant_id' => $formDefinition->tenant_id,
                    'form_definition_id' => $formDefinition->id,
                    'label' => $fieldData['label'],
                    'field_name' => $fieldName,
                    'field_type' => $fieldData['field_type'],
                    'is_required' => (bool) ($fieldData['is_required'] ?? false),
                    'options_json' => $options,
                    'sort_order' => $order++,
                ]);
            }

            return $formDefinition;
        });

        return redirect()
            ->route('forms.show', $form)
            ->with('status', 'success')
            ->with('message', "Form [{$form->title}] published successfully.");
    }

    public function show(Request $request, FormDefinition $form): View
    {
        abort_if(! $request->user()?->can('forms.view'), 403);

        $form->load([
            'fields',
            'department:id,name',
            'creator:uuid,first_name,last_name,email',
            'submissions.user:uuid,first_name,last_name,email',
            'submissions.reviewer:uuid,first_name,last_name',
        ]);

        $pollTally = $form->type === 'live_poll' ? $form->getPollTally() : [];

        return view('forms::show', [
            'form' => $form,
            'pollTally' => $pollTally,
        ]);
    }

    public function publish(Request $request, FormDefinition $form): RedirectResponse
    {
        abort_if(! $request->user()?->can('forms.manage'), 403);

        $form->update(['status' => 'published']);

        return back()->with('status', 'success')->with('message', 'Form published for submissions.');
    }

    public function close(Request $request, FormDefinition $form): RedirectResponse
    {
        abort_if(! $request->user()?->can('forms.manage'), 403);

        $form->update(['status' => 'closed']);

        return back()->with('status', 'success')->with('message', 'Form closed to further submissions.');
    }

    public function reviewSubmission(Request $request, FormDefinition $form, FormSubmission $submission): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('forms.review'), 403);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['under_review', 'approved', 'rejected'])],
            'reviewer_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $submission->update([
            'status' => $validated['status'],
            'reviewer_notes' => $validated['reviewer_notes'] ?? null,
            'reviewed_by' => (string) $user->uuid,
            'reviewed_at' => now(),
        ]);

        return back()->with('status', 'success')->with('message', "Submission updated to [{$validated['status']}].");
    }

    public function publicShow(Request $request, string $slug): View
    {
        $form = FormDefinition::query()
            ->where('slug', $slug)
            ->with(['fields', 'department:id,name'])
            ->firstOrFail();

        $this->authorizeAudience($request, $form);

        $user = $request->user();
        $hasAlreadySubmitted = false;

        if ($user && $form->single_response_per_user) {
            if ($form->type === 'live_poll') {
                $hasAlreadySubmitted = PollVote::where('form_definition_id', $form->id)
                    ->where('user_id', (string) $user->uuid)
                    ->exists();
            } else {
                $hasAlreadySubmitted = FormSubmission::where('form_definition_id', $form->id)
                    ->where('user_id', (string) $user->uuid)
                    ->exists();
            }
        }

        $pollTally = $form->type === 'live_poll' ? $form->getPollTally() : [];

        return view('forms::public-form', [
            'form' => $form,
            'hasAlreadySubmitted' => $hasAlreadySubmitted,
            'pollTally' => $pollTally,
        ]);
    }

    public function publicSubmit(Request $request, string $slug): RedirectResponse
    {
        $form = FormDefinition::query()
            ->where('slug', $slug)
            ->with('fields')
            ->firstOrFail();

        $this->authorizeAudience($request, $form);

        if (! $form->isAcceptingSubmissions()) {
            abort(422, 'This form is no longer accepting submissions.');
        }

        $user = $request->user();

        if (! $user && ! $form->allow_anonymous) {
            abort(403, 'Authentication required to submit this form.');
        }

        $userUuid = $user ? (string) $user->uuid : null;

        // Check single response per user
        if ($userUuid && $form->single_response_per_user) {
            if ($form->type === 'live_poll') {
                $alreadyVoted = PollVote::where('form_definition_id', $form->id)
                    ->where('user_id', $userUuid)
                    ->exists();

                if ($alreadyVoted) {
                    abort(422, 'You have already cast a vote in this poll.');
                }
            } else {
                $alreadySubmitted = FormSubmission::where('form_definition_id', $form->id)
                    ->where('user_id', $userUuid)
                    ->exists();

                if ($alreadySubmitted) {
                    abort(422, 'You have already submitted a response to this form.');
                }
            }
        }

        if ($form->type === 'live_poll') {
            $validated = $request->validate([
                'selected_option' => ['required', 'string'],
            ]);

            PollVote::create([
                'tenant_id' => $form->tenant_id,
                'form_definition_id' => $form->id,
                'user_id' => $userUuid,
                'ip_address' => $request->ip(),
                'selected_option' => $validated['selected_option'],
            ]);

            return back()->with('status', 'success')->with('message', 'Your vote has been recorded.');
        }

        // Process standard form/survey/HR request
        $rules = [];
        foreach ($form->fields as $field) {
            $fieldRules = [];
            if ($field->is_required) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            if ($field->field_type === 'number') {
                $fieldRules[] = 'numeric';
            } elseif ($field->field_type === 'date') {
                $fieldRules[] = 'date';
            } else {
                $fieldRules[] = 'string';
            }

            $rules[$field->field_name] = $fieldRules;
        }

        $validatedData = $request->validate($rules);

        $submission = FormSubmission::create([
            'tenant_id' => $form->tenant_id,
            'form_definition_id' => $form->id,
            'user_id' => $userUuid,
            'ip_address' => $request->ip(),
            'responses_json' => $validatedData,
            'status' => 'submitted',
        ]);

        \App\Modules\WorkflowAutomation\Events\WorkflowDomainEvent::dispatch(
            (string) $form->tenant_id,
            'form.submitted',
            [
                'submission_id' => $submission->id,
                'form_id' => $form->id,
                'form_slug' => $form->slug,
                'form_title' => $form->title,
                'responses' => $validatedData,
            ]
        );

        return back()->with('status', 'success')->with('message', 'Your submission has been received successfully.');
    }

    private function authorizeAudience(Request $request, FormDefinition $form): void
    {
        if ($form->target_audience === 'public') {
            return;
        }

        $user = $request->user();

        if (! $user) {
            abort(403, 'Authentication required to access this form.');
        }

        if ($form->target_audience === 'departmental' && $form->target_department_id) {
            if ($user->can('forms.manage')) {
                return;
            }

            $isInDepartment = false;

            if (isset($user->department_id) && (int) $user->department_id === (int) $form->target_department_id) {
                $isInDepartment = true;
            } elseif (class_exists(\App\Modules\HrmsCore\Models\Employees\Employee::class)) {
                $employee = \App\Modules\HrmsCore\Models\Employees\Employee::where('user_id', $user->id)->first();
                if ($employee) {
                    $isInDepartment = $employee->departments()
                        ->where('hrms_departments.id', $form->target_department_id)
                        ->exists();
                }
            }

            if (! $isInDepartment) {
                abort(403, 'Access restricted to members of the designated department.');
            }
        }
    }
}
