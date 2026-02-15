@extends('layouts.metronic.app')

@section('title', __('LLM Configurations'))

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <p class="text-xs uppercase tracking-wide text-muted-foreground">AI Configuration</p>
            <h1 class="mt-2 text-2xl font-semibold text-foreground">{{ __('LLM Configurations') }}</h1>
            <p class="mt-2 text-sm text-muted-foreground">Manage BYOK provider credentials and activation per provider.</p>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <form action="{{ route('tenant.llm-config.update') }}" method="POST" class="grid gap-5">
                @csrf
                @method('PUT')

                @include('admin.tenant.llm-config.partials.provider-form', [
                    'provider' => 'openai',
                    'label' => 'OpenAI',
                    'config' => $configs['openai'] ?? null,
                ])

                @include('admin.tenant.llm-config.partials.provider-form', [
                    'provider' => 'anthropic',
                    'label' => 'Anthropic',
                    'config' => $configs['anthropic'] ?? null,
                ])

                @include('admin.tenant.llm-config.partials.provider-form', [
                    'provider' => 'google',
                    'label' => 'Google Gemini',
                    'config' => $configs['google'] ?? null,
                ])

                <div class="flex justify-end pt-2">
                    <button type="submit" class="kt-btn kt-btn-primary">{{ __('Save Changes') }}</button>
                </div>
            </form>
        </div>
    </section>
@endsection
