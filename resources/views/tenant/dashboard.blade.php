@extends('layouts.admin.master')

@section('title', __('Tenant Dashboard'))

@section('content')
    <div class="post d-flex flex-column-fluid" id="kt_post">
        <div id="kt_content_container" class="container-xxl">
            <div class="card">
                <div class="card-body p-lg-17">
                    <div class="text-center mb-17">
                        <h3 class="fs-2hx text-dark mb-5">Welcome to
                            {{ app(\App\Services\Tenancy\TenantContext::class)->getTenant()->name }}!
                        </h3>
                        <div class="fs-5 text-muted fw-bold">This is your tenant-specific dashboard, accessed via subdomain.
                        </div>
                    </div>

                    <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
                        @if(!$checklist['onboarding'])
                            <div class="col-xl-4 mb-5 mb-xl-10">
                                <!--begin::Getting Started Checklist-->
                                <div class="card card-flush shadow-sm h-100" id="kt_getting_started_widget">
                                    <div class="card-header border-0 pt-5">
                                        <h3 class="card-title align-items-start flex-column">
                                            <span class="card-label fw-boldest text-dark">GETTING STARTED</span>
                                            <span class="text-gray-400 mt-1 fw-bold fs-7">Complete these to set up your
                                                org</span>
                                        </h3>
                                    </div>
                                    <div class="card-body pt-2">
                                        <!-- Item 1: Branding -->
                                        <div class="d-flex align-items-center mb-7">
                                            <div class="symbol symbol-30px symbol-circle me-3">
                                                <span
                                                    class="symbol-label {{ $checklist['branding'] ? 'bg-light-success text-success' : 'bg-light-primary text-primary' }}">
                                                    @if ($checklist['branding'])
                                                        <i class="fas fa-check"></i>
                                                    @else
                                                        1
                                                    @endif
                                                </span>
                                            </div>
                                            <div class="flex-grow-1">
                                                <a href="{{ route('tenant.settings.index') }}"
                                                    class="text-dark fw-bolder text-hover-primary fs-6 {{ $checklist['branding'] ? 'text-decoration-line-through text-muted' : '' }}">
                                                    Customize Branding
                                                </a>
                                                <span class="text-muted d-block fw-bold fs-7">Logo & primary color</span>
                                            </div>
                                        </div>

                                        <!-- Item 2: Team -->
                                        <div class="d-flex align-items-center mb-7">
                                            <div class="symbol symbol-30px symbol-circle me-3">
                                                <span
                                                    class="symbol-label {{ $checklist['team'] ? 'bg-light-success text-success' : 'bg-light-primary text-primary' }}">
                                                    @if ($checklist['team'])
                                                        <i class="fas fa-check"></i>
                                                    @else
                                                        2
                                                    @endif
                                                </span>
                                            </div>
                                            <div class="flex-grow-1">
                                                <a href="{{ route('tenant.users.index') }}"
                                                    class="text-dark fw-bolder text-hover-primary fs-6 {{ $checklist['team'] ? 'text-decoration-line-through text-muted' : '' }}">
                                                    Add Your Team
                                                </a>
                                                <span class="text-muted d-block fw-bold fs-7">Invite your colleagues</span>
                                            </div>
                                        </div>

                                        <!-- Item 3: Done -->
                                        <div class="d-flex align-items-center mb-7">
                                            <div class="symbol symbol-30px symbol-circle me-3">
                                                <span
                                                    class="symbol-label {{ $checklist['onboarding'] ? 'bg-light-success text-success' : 'bg-light-primary text-primary' }}">
                                                    3
                                                </span>
                                            </div>
                                            <div class="flex-grow-1">
                                                <form
                                                    action="{{ route(request()->route('subdomain') ? 'tenant.onboarding.finish' : 'onboarding.finish') }}"
                                                    method="POST">
                                                    @csrf
                                                    <button type="submit"
                                                        class="btn btn-link p-0 text-dark fw-bolder text-hover-primary fs-6 border-0 align-baseline">
                                                        Mark as Setup Complete
                                                    </button>
                                                </form>
                                                <span class="text-muted d-block fw-bold fs-7">Dismiss this checklist</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!--end::Getting Started Checklist-->
                            </div>
                        @endif

                        <div
                            class="{{ $checklist['onboarding'] ? 'col-md-6 col-lg-6 col-xl-6 col-xxl-3' : 'col-xl-4' }} mb-md-5 mb-xl-10">
                            <div class="card card-flush h-md-50 mb-5 mb-xl-10">
                                <div class="card-header pt-5">
                                    <div class="card-title d-flex flex-column">
                                        <span
                                            class="fs-2hx fw-boldest text-dark me-2 lh-1 ls-n2">{{ auth()->user()->tenants()->count() }}</span>
                                        <span class="text-gray-400 pt-1 fw-bold fs-6">Connected Tenants</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @forelse($usages as $usage)
                            <div class="col-md-6 col-lg-6 col-xl-6 col-xxl-3 mb-md-5 mb-xl-10">
                                <div class="card card-flush h-md-50 mb-5 mb-xl-10">
                                    <div class="card-header pt-5">
                                        <div class="card-title d-flex flex-column">
                                            <div class="d-flex align-items-center mb-2">
                                                <span
                                                    class="fs-2hx fw-boldest text-dark me-2 lh-1 ls-n2">{{ $usage['used'] }}</span>
                                                <span class="text-gray-400 fw-bold fs-7">/ {{ $usage['limit'] }}</span>
                                            </div>
                                            <span
                                                class="text-gray-400 pt-1 fw-bold fs-6">{{ ucfirst(str_replace('_', ' ', $usage['slug'])) }}
                                                Usage</span>
                                        </div>
                                    </div>
                                    <div class="card-body d-flex flex-column justify-content-end pe-0">
                                        <div class="d-flex flex-stack mb-2">
                                            <span
                                                class="text-gray-400 fw-boldest fs-7">{{ number_format(($usage['used'] / max(1, $usage['limit'])) * 100, 1) }}%
                                                Consumed</span>
                                        </div>
                                        <div class="h-8px bg-light-primary rounded">
                                            <div class="bg-primary rounded h-8px" role="progressbar"
                                                style="width: {{ ($usage['used'] / max(1, $usage['limit'])) * 100 }}%"
                                                aria-valuenow="{{ $usage['used'] }}" aria-valuemin="0"
                                                aria-valuemax="{{ $usage['limit'] }}"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            @if($checklist['onboarding'])
                                <div class="col-xl-8 mb-5 mb-xl-10">
                                    <div class="card h-100 bg-light border-dashed d-flex flex-center p-10">
                                        <div class="text-center">
                                            <i class="fas fa-chart-line fs-3x text-gray-300 mb-5"></i>
                                            <h3 class="fw-bolder text-gray-600">No active usage tracked</h3>
                                            <p class="text-gray-500">Enable metered features in your plan to see consumption here.</p>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('custom-scripts')
    @if(session('onboarding_just_completed'))
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@1.0.1/dist/driver.css" />
        <script src="https://cdn.jsdelivr.net/npm/driver.js@1.0.1/dist/driver.js.iife.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const driver = window.driver.js.driver;
                const steps = [
                    { element: '#kt_aside', popover: { title: 'Navigation', description: 'Access all your organization settings and tools from here.', side: 'right', align: 'start' } }
                ];

                if (document.querySelector('#kt_getting_started_widget')) {
                    steps.push({ element: '#kt_getting_started_widget', popover: { title: 'Getting Started', description: 'Follow this checklist to fully set up your organization.', side: 'left', align: 'start' } });
                }

                if (document.querySelector('.card-flush:not(#kt_getting_started_widget)')) {
                    steps.push({ element: '.card-flush:not(#kt_getting_started_widget)', popover: { title: 'Usage Metrics', description: 'Monitor your tenant usage and limits in real-time.', side: 'bottom', align: 'start' } });
                }

                const driverObj = driver({
                    showProgress: true,
                    steps: steps
                });

                setTimeout(() => {
                    driverObj.drive();
                }, 500);
            });
        </script>
    @endif
@endpush