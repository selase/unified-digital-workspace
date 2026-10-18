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
use Symfony\Component\HttpFoundation\StreamedResponse;

final class FormsWebController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('forms.view'), 403);

        $type = $request->query('type');
        $status = $request->query('status');
        $audience = $request->query('audience');
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

        if ($audience) {
            $query->where('target_audience', $audience);
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
            'currentAudience' => $audience,
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
            'status' => ['nullable', 'string', Rule::in(['draft', 'published', 'closed'])],
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

        // Prevent duplicate slug within tenant with bounded retry
        $originalSlug = $slug;
        $foundUnique = false;
        if (! FormDefinition::where('slug', $slug)->exists()) {
            $foundUnique = true;
        } else {
            for ($counter = 1; $counter <= 50; $counter++) {
                $candidate = "{$originalSlug}-{$counter}";
                if (! FormDefinition::where('slug', $candidate)->exists()) {
                    $slug = $candidate;
                    $foundUnique = true;
                    break;
                }
            }
        }

        if (! $foundUnique) {
            $slug = "{$originalSlug}-".Str::lower(Str::random(6));
            if (FormDefinition::where('slug', $slug)->exists()) {
                throw new \RuntimeException('Unable to generate unique slug for form after bounded retries.');
            }
        }

        $form = DB::transaction(function () use ($validated, $slug, $user): FormDefinition {
            $formDefinition = FormDefinition::create([
                'title' => $validated['title'],
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
                'type' => $validated['type'],
                'target_audience' => $validated['target_audience'],
                'target_department_id' => $validated['target_department_id'] ?? null,
                'status' => $validated['status'] ?? 'published',
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

        $actionText = $form->status === 'draft' ? 'saved as draft' : 'published';

        return redirect()
            ->route('forms.show', $form)
            ->with('status', 'success')
            ->with('message', "Form [{$form->title}] {$actionText} successfully.");
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

    public function edit(Request $request, FormDefinition $form): View
    {
        abort_if(! $request->user()?->can('forms.manage'), 403);
        abort_if($form->status !== 'draft', 422, 'Only draft forms can be edited.');

        $form->load('fields');
        $departments = Department::query()->orderBy('name')->get(['id', 'name']);

        return view('forms::edit', [
            'form' => $form,
            'departments' => $departments,
        ]);
    }

    public function update(Request $request, FormDefinition $form): RedirectResponse
    {
        abort_if(! $request->user()?->can('forms.manage'), 403);
        abort_if($form->status !== 'draft', 422, 'Only draft forms can be edited.');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'string', Rule::in(['general', 'hr_request', 'survey', 'live_poll'])],
            'target_audience' => ['required', 'string', Rule::in(['public', 'internal_only', 'departmental'])],
            'target_department_id' => ['nullable', 'integer', Rule::exists('hrms_departments', 'id')],
            'status' => ['nullable', 'string', Rule::in(['draft', 'published', 'closed'])],
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

        DB::transaction(function () use ($form, $validated): void {
            $form->update([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'type' => $validated['type'],
                'target_audience' => $validated['target_audience'],
                'target_department_id' => $validated['target_department_id'] ?? null,
                'status' => $validated['status'] ?? $form->status,
                'allow_anonymous' => (bool) ($validated['allow_anonymous'] ?? false),
                'single_response_per_user' => (bool) ($validated['single_response_per_user'] ?? false),
                'expires_at' => $validated['expires_at'] ?? null,
            ]);

            $form->fields()->delete();

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
                    'tenant_id' => $form->tenant_id,
                    'form_definition_id' => $form->id,
                    'label' => $fieldData['label'],
                    'field_name' => $fieldName,
                    'field_type' => $fieldData['field_type'],
                    'is_required' => (bool) ($fieldData['is_required'] ?? false),
                    'options_json' => $options,
                    'sort_order' => $order++,
                ]);
            }
        });

        return redirect()
            ->route('forms.show', $form)
            ->with('status', 'success')
            ->with('message', "Draft form [{$form->title}] updated successfully.");
    }

    public function destroy(Request $request, FormDefinition $form): RedirectResponse
    {
        abort_if(! $request->user()?->can('forms.manage'), 403);
        abort_if($form->status !== 'draft', 422, 'Only draft forms can be deleted.');

        $title = $form->title;
        $form->delete();

        return redirect()
            ->route('forms.index')
            ->with('status', 'success')
            ->with('message', "Draft form [{$title}] deleted successfully.");
    }

    public function results(Request $request, FormDefinition $form): View
    {
        abort_if(! $request->user()?->can('forms.view'), 403);

        $form->load(['fields', 'department:id,name', 'creator:uuid,first_name,last_name']);

        $pollTally = $form->type === 'live_poll' ? $form->getPollTally() : [];

        $submissions = $form->submissions()->get();
        $totalSubmissions = $submissions->count();

        $aggregates = [];

        foreach ($form->fields as $field) {
            $fieldName = (string) $field->field_name;
            $fieldType = (string) $field->field_type;

            if (in_array($fieldType, ['select', 'radio', 'checkbox'], true)) {
                $optionsCounts = [];
                /** @var array<int, string> $definedOptions */
                $definedOptions = is_array($field->options_json) ? $field->options_json : [];
                foreach ($definedOptions as $opt) {
                    $optionsCounts[$opt] = 0;
                }

                foreach ($submissions as $sub) {
                    $responses = $sub->responses_json;
                    $val = $responses[$fieldName] ?? null;
                    if ($val === null) {
                        continue;
                    }
                    if (is_array($val)) {
                        foreach ($val as $singleVal) {
                            $singleVal = (string) $singleVal;
                            $optionsCounts[$singleVal] = ($optionsCounts[$singleVal] ?? 0) + 1;
                        }
                    } else {
                        $strVal = (string) $val;
                        $optionsCounts[$strVal] = ($optionsCounts[$strVal] ?? 0) + 1;
                    }
                }

                $optionsStats = [];
                foreach ($optionsCounts as $optName => $count) {
                    $percentage = $totalSubmissions > 0 ? round(($count / $totalSubmissions) * 100, 1) : 0.0;
                    $optionsStats[$optName] = [
                        'count' => $count,
                        'percentage' => $percentage,
                    ];
                }

                $aggregates[$fieldName] = [
                    'label' => $field->label,
                    'type' => $fieldType,
                    'options' => $optionsStats,
                ];
            } elseif ($fieldType === 'number') {
                $numbers = [];
                foreach ($submissions as $sub) {
                    $responses = $sub->responses_json;
                    if (isset($responses[$fieldName]) && is_numeric($responses[$fieldName])) {
                        $numbers[] = (float) $responses[$fieldName];
                    }
                }

                $count = count($numbers);
                $aggregates[$fieldName] = [
                    'label' => $field->label,
                    'type' => 'number',
                    'count' => $count,
                    'min' => $count > 0 ? min($numbers) : null,
                    'max' => $count > 0 ? max($numbers) : null,
                    'avg' => $count > 0 ? round(array_sum($numbers) / $count, 2) : null,
                ];
            } else {
                $textAnswers = [];
                foreach ($submissions as $sub) {
                    $responses = $sub->responses_json;
                    if (! empty($responses[$fieldName])) {
                        $textAnswers[] = (string) $responses[$fieldName];
                    }
                }

                $aggregates[$fieldName] = [
                    'label' => $field->label,
                    'type' => $fieldType,
                    'count' => count($textAnswers),
                    'samples' => array_slice($textAnswers, 0, 20),
                ];
            }
        }

        return view('forms::results', [
            'form' => $form,
            'pollTally' => $pollTally,
            'totalSubmissions' => $totalSubmissions,
            'aggregates' => $aggregates,
        ]);
    }

    public function exportCsv(Request $request, FormDefinition $form): StreamedResponse
    {
        abort_if(! $request->user()?->can('forms.view'), 403);

        $slug = $form->slug;

        if ($form->type === 'live_poll') {
            $votes = $form->pollVotes()
                ->with('user:uuid,first_name,last_name,email')
                ->latest()
                ->get();

            return response()->streamDownload(function () use ($votes): void {
                $handle = fopen('php://output', 'w');
                if ($handle === false) {
                    return;
                }

                fputcsv($handle, ['Vote UUID', 'Voted At', 'User Name', 'User Email', 'IP Address', 'Selected Option']);

                foreach ($votes as $vote) {
                    fputcsv($handle, [
                        $vote->uuid,
                        $vote->created_at?->toIso8601String() ?? '',
                        $vote->user ? "{$vote->user->first_name} {$vote->user->last_name}" : 'Anonymous Guest',
                        $vote->user ? (string) $vote->user->email : '',
                        $vote->ip_address ?? '',
                        $vote->selected_option,
                    ]);
                }

                fclose($handle);
            }, "poll-results-{$slug}.csv", [
                'Content-Type' => 'text/csv',
            ]);
        }

        $fields = $form->fields()->orderBy('sort_order')->get();
        $submissions = $form->submissions()
            ->with(['user:uuid,first_name,last_name,email', 'reviewer:uuid,first_name,last_name'])
            ->latest()
            ->get();

        return response()->streamDownload(function () use ($fields, $submissions): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            $headers = ['Submission UUID', 'Submitted At', 'User Name', 'User Email', 'IP Address', 'Status', 'Reviewer', 'Reviewed At'];
            foreach ($fields as $field) {
                $headers[] = $field->label;
            }

            fputcsv($handle, $headers);

            foreach ($submissions as $sub) {
                $row = [
                    $sub->uuid,
                    $sub->created_at?->toIso8601String() ?? '',
                    $sub->user ? "{$sub->user->first_name} {$sub->user->last_name}" : 'Anonymous Guest',
                    $sub->user ? (string) $sub->user->email : '',
                    $sub->ip_address ?? '',
                    $sub->status,
                    $sub->reviewer ? "{$sub->reviewer->first_name} {$sub->reviewer->last_name}" : '',
                    $sub->reviewed_at?->toIso8601String() ?? '',
                ];

                $responses = $sub->responses_json;
                foreach ($fields as $field) {
                    $val = $responses[$field->field_name] ?? '';
                    $row[] = is_array($val) ? implode(', ', $val) : (string) $val;
                }

                fputcsv($handle, $row);
            }

            fclose($handle);
        }, "form-results-{$slug}.csv", [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function showSubmission(Request $request, FormDefinition $form, FormSubmission $submission): View
    {
        abort_if(! ($request->user()?->can('forms.view') || $request->user()?->can('forms.review')), 403);
        abort_if((int) $submission->form_definition_id !== (int) $form->id, 404);

        $submission->load([
            'user:uuid,first_name,last_name,email',
            'reviewer:uuid,first_name,last_name',
        ]);
        $form->load('fields');

        return view('forms::submission-show', [
            'form' => $form,
            'submission' => $submission,
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

        if ($form->single_response_per_user) {
            if ($user) {
                if ($form->type === 'live_poll') {
                    $hasAlreadySubmitted = PollVote::where('form_definition_id', $form->id)
                        ->where('user_id', (string) $user->uuid)
                        ->exists();
                } else {
                    $hasAlreadySubmitted = FormSubmission::where('form_definition_id', $form->id)
                        ->where('user_id', (string) $user->uuid)
                        ->exists();
                }
            } else {
                $sessionKey = "submitted_form_{$form->id}";
                $voterHash = hash('sha256', $form->id.'|'.($request->ip() ?? '').'|'.($request->header('User-Agent') ?? ''));
                if ($form->type === 'live_poll') {
                    $hasAlreadySubmitted = $request->session()->has($sessionKey)
                        || PollVote::where('form_definition_id', $form->id)
                            ->where('voter_hash', $voterHash)
                            ->exists();
                } else {
                    $hasAlreadySubmitted = $request->session()->has($sessionKey)
                        || FormSubmission::where('form_definition_id', $form->id)
                            ->where('voter_hash', $voterHash)
                            ->exists();
                }
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
        $ipAddress = (string) $request->ip();
        $userAgent = (string) $request->header('User-Agent');
        $voterHash = hash('sha256', $form->id.'|'.$ipAddress.'|'.$userAgent);

        // Check single response per user (authenticated or anonymous anti-abuse)
        if ($form->single_response_per_user) {
            if ($userUuid) {
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
            } else {
                // Anonymous user anti-abuse
                $sessionKey = "submitted_form_{$form->id}";
                if ($form->type === 'live_poll') {
                    $alreadyVoted = $request->session()->has($sessionKey)
                        || PollVote::where('form_definition_id', $form->id)
                            ->where('voter_hash', $voterHash)
                            ->exists();

                    if ($alreadyVoted) {
                        abort(422, 'You have already cast a vote in this poll.');
                    }
                } else {
                    $alreadySubmitted = $request->session()->has($sessionKey)
                        || FormSubmission::where('form_definition_id', $form->id)
                            ->where('voter_hash', $voterHash)
                            ->exists();

                    if ($alreadySubmitted) {
                        abort(422, 'You have already submitted a response to this form.');
                    }
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
                'ip_address' => $ipAddress,
                'voter_hash' => $voterHash,
                'selected_option' => $validated['selected_option'],
            ]);

            $request->session()->put("submitted_form_{$form->id}", true);

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
            'ip_address' => $ipAddress,
            'voter_hash' => $voterHash,
            'responses_json' => $validatedData,
            'status' => 'submitted',
        ]);

        $request->session()->put("submitted_form_{$form->id}", true);

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

        if ($form->target_audience === 'departmental') {
            if ($user->can('forms.manage')) {
                return;
            }

            if (! $form->target_department_id) {
                abort(403, 'Access restricted: no designated department configured.');
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
