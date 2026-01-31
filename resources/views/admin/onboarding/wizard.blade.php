@extends('layouts.admin.master')

@section('title', 'Welcome to Your Organization')

@section('content')
    <div class="post d-flex flex-column-fluid" id="kt_post">
        <!--begin::Container-->
        <div id="kt_content_container" class="container-xxl">
            @php
                $routePrefix = request()->route('subdomain') ? 'tenant.onboarding.' : 'onboarding.';
            @endphp
            <div class="card shadow-sm border-0">
                <div class="card-body p-10">
                    <div class="text-center mb-10">
                        <div class="mb-5">
                            <i class="fas fa-rocket text-primary fs-5hx"></i>
                        </div>
                        <h1 class="text-dark mb-3">Welcome to {{ $tenant->name }}!</h1>
                        <p class="text-muted fw-bold fs-5">We're excited to have you on board. Let's get your organization
                            set up.</p>
                    </div>

                    <div class="separator separator-dashed my-10"></div>

                    <div class="row g-10">
                        <!-- Branding Option -->
                        <div class="col-md-6">
                            <div class="card h-100 bg-light-primary border-primary border-dashed p-8">
                                <form action="{{ route($routePrefix . 'branding.update') }}" method="POST"
                                    enctype="multipart/form-data">
                                    @csrf
                                    <h3 class="text-primary mb-5"><i class="fas fa-paint-brush me-3"></i>Quick Branding</h3>

                                    <div class="fv-row mb-5">
                                        <label class="form-label fw-bold">Organization Name</label>
                                        <input class="form-control form-control-solid" type="text" name="name"
                                            value="{{ $tenant->name }}" required />
                                    </div>

                                    <div class="fv-row mb-5">
                                        <label class="form-label fw-bold">Logo</label>
                                        <input class="form-control form-control-solid" type="file" name="logo"
                                            accept="image/*" />
                                    </div>

                                    <button type="submit" class="btn btn-primary w-100">Update & Continue</button>
                                </form>
                            </div>
                        </div>

                        <!-- Direct to Dashboard -->
                        <div class="col-md-6">
                            <div
                                class="card h-100 bg-light-success border-success border-dashed p-8 d-flex flex-column justify-content-center text-center">
                                <h3 class="text-success mb-5"><i class="fas fa-tasks me-3"></i>Go to Dashboard</h3>
                                <p class="text-gray-600 mb-8">Prefer to explore on your own? You can configure everything
                                    from the dashboard later.</p>

                                <form action="{{ route($routePrefix . 'finish') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-lg w-100">
                                        Start Exploring
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-10">
                        <p class="text-muted">You can always find more settings under <strong>Organization Settings</strong>
                            in the sidebar.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection