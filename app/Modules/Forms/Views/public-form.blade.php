<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $form->title }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-2xl mx-auto">
        {{-- Flash Messages --}}
        @if(session('status') === 'success')
            <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium">
                {{ session('message') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
            {{-- Header --}}
            <div class="p-8 bg-gradient-to-r from-slate-900 to-indigo-950 text-white">
                <span class="inline-block px-2.5 py-1 rounded text-xs font-bold uppercase tracking-wider bg-white/10 text-slate-200 mb-2">
                    {{ str_replace('_', ' ', $form->type) }}
                </span>
                <h1 class="text-2xl sm:text-3xl font-extrabold">{{ $form->title }}</h1>
                @if($form->description)
                    <p class="mt-2 text-sm text-slate-300 leading-relaxed">{{ $form->description }}</p>
                @endif
            </div>

            <div class="p-8">
                @if(! $form->isAcceptingSubmissions())
                    <div class="p-6 text-center bg-amber-50 border border-amber-200 rounded-xl text-amber-800 text-sm font-medium">
                        This form is currently closed or expired. Submissions are no longer being accepted.
                    </div>
                @elseif($hasAlreadySubmitted)
                    <div class="p-6 text-center bg-blue-50 border border-blue-200 rounded-xl text-blue-800 text-sm font-medium">
                        Thank you! You have already submitted a response to this form. Duplicate submissions are not allowed.
                    </div>

                    @if($form->type === 'live_poll')
                        <div class="mt-8">
                            <h3 class="text-base font-bold text-slate-900 mb-4">Current Live Poll Standings</h3>
                            <div class="space-y-4">
                                @foreach($pollTally as $option => $data)
                                    <div>
                                        <div class="flex justify-between items-center text-sm font-semibold mb-1">
                                            <span>{{ $option }}</span>
                                            <span class="text-indigo-600">{{ $data['percentage'] }}% ({{ $data['count'] }} votes)</span>
                                        </div>
                                        <div class="w-full bg-slate-100 rounded-full h-3 overflow-hidden">
                                            <div class="bg-indigo-600 h-3 rounded-full" style="width: {{ $data['percentage'] }}%"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @else
                    <form action="{{ route('forms.public.submit', $form->slug) }}" method="POST" class="space-y-6">
                        @csrf

                        @if($form->type === 'live_poll')
                            {{-- Live Poll Radio Choice --}}
                            <div>
                                <label class="block text-sm font-bold text-slate-900 mb-3">Cast Your Vote:</label>
                                @php
                                    $optionsField = $form->fields->whereIn('field_type', ['radio', 'select'])->first();
                                    $options = $optionsField?->options_json ?? ['Option A', 'Option B'];
                                @endphp
                                <div class="space-y-3">
                                    @foreach($options as $opt)
                                        <label class="flex items-center p-4 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition">
                                            <input type="radio" name="selected_option" value="{{ $opt }}" required class="h-4 w-4 text-indigo-600 border-slate-300">
                                            <span class="ml-3 text-sm font-semibold text-slate-800">{{ $opt }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            {{-- Standard Form / Survey / HR Request Fields --}}
                            @foreach($form->fields as $field)
                                <div>
                                    <label class="block text-sm font-semibold text-slate-800 mb-1">
                                        {{ $field->label }}
                                        @if($field->is_required)
                                            <span class="text-red-500">*</span>
                                        @endif
                                    </label>

                                    @if($field->field_type === 'textarea')
                                        <textarea
                                            name="{{ $field->field_name }}"
                                            rows="4"
                                            {{ $field->is_required ? 'required' : '' }}
                                            class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
                                            placeholder="Provide your response here..."
                                        >{{ old($field->field_name) }}</textarea>
                                    @elseif($field->field_type === 'select')
                                        <select
                                            name="{{ $field->field_name }}"
                                            {{ $field->is_required ? 'required' : '' }}
                                            class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
                                        >
                                            <option value="">-- Select Option --</option>
                                            @foreach($field->options_json ?? [] as $opt)
                                                <option value="{{ $opt }}" @selected(old($field->field_name) === $opt)>{{ $opt }}</option>
                                            @endforeach
                                        </select>
                                    @elseif($field->field_type === 'radio')
                                        <div class="space-y-2 mt-2">
                                            @foreach($field->options_json ?? [] as $opt)
                                                <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                                                    <input type="radio" name="{{ $field->field_name }}" value="{{ $opt }}" {{ $field->is_required ? 'required' : '' }} @checked(old($field->field_name) === $opt) class="text-indigo-600">
                                                    <span>{{ $opt }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    @elseif($field->field_type === 'checkbox')
                                        <div class="space-y-2 mt-2">
                                            @foreach($field->options_json ?? [] as $opt)
                                                <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                                                    <input type="checkbox" name="{{ $field->field_name }}[]" value="{{ $opt }}" class="rounded text-indigo-600">
                                                    <span>{{ $opt }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    @else
                                        <input
                                            type="{{ $field->field_type === 'number' ? 'number' : ($field->field_type === 'date' ? 'date' : 'text') }}"
                                            name="{{ $field->field_name }}"
                                            value="{{ old($field->field_name) }}"
                                            {{ $field->is_required ? 'required' : '' }}
                                            class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
                                        >
                                    @endif
                                </div>
                            @endforeach
                        @endif

                        <div class="pt-4 border-t border-slate-200">
                            <button type="submit" class="w-full py-3 px-6 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm shadow transition">
                                {{ $form->type === 'live_poll' ? 'Cast Official Vote' : 'Submit Form' }}
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</body>
</html>

