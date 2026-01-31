@extends('layouts.admin.master')

@section('title', 'Roles & Permissions')

@section('content')
    <div class="post d-flex flex-column-fluid" id="kt_post">
        <div id="kt_content_container" class="container-xxl">
            <div class="card">
                <div class="card-header border-0 pt-6">
                    <div class="card-title">
                        <h2>Organization Roles</h2>
                    </div>
                    <div class="card-toolbar">
                        <a href="{{ route('tenant.roles.create', ['subdomain' => request()->route('subdomain')]) }}"
                            class="btn btn-primary">
                            Add Custom Role
                        </a>
                    </div>
                </div>
                <div class="card-body py-4">
                    <!--begin::Row-->
                    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-5 g-xl-9">
                        @foreach($roles as $role)
                            <!--begin::Col-->
                            <div class="col-md-4">
                                <!--begin::Card-->
                                <div class="card card-flush h-md-100">
                                    <!--begin::Card header-->
                                    <div class="card-header">
                                        <!--begin::Card title-->
                                        <div class="card-title">
                                            <h2>{{ $role->name }}</h2>
                                        </div>
                                        <!--end::Card title-->
                                    </div>
                                    <!--end::Card header-->
                                    <!--begin::Card body-->
                                    <div class="card-body pt-1">
                                        <!--begin::Users-->
                                        <div class="fw-bold text-gray-600 mb-5">Total users with this role:
                                            {{ $role->users_count ?? 0 }}</div>
                                        <!--end::Users-->
                                        <!--begin::Permissions-->
                                        <div class="d-flex flex-column text-gray-600">
                                            @foreach($role->permissions->take(5) as $permission)
                                                <div class="d-flex align-items-center py-2">
                                                    <span class="bullet bg-primary me-3"></span>{{ $permission->name }}
                                                </div>
                                            @endforeach
                                            @if($role->permissions->count() > 5)
                                                <div class='d-flex align-items-center py-2'>
                                                    <span class='bullet bg-primary me-3'></span>
                                                    <em>and {{ $role->permissions->count() - 5 }} more...</em>
                                                </div>
                                            @endif
                                            @if($role->permissions->isEmpty())
                                                <div class="text-muted fs-7">No specific permissions assigned.</div>
                                            @endif
                                        </div>
                                        <!--end::Permissions-->
                                    </div>
                                    <!--end::Card body-->
                                    <!--begin::Card footer-->
                                    <div class="card-footer flex-wrap pt-0">
                                        <div class="d-flex justify-content-between align-items-center">
                                            @if(!$role->tenant_id)
                                                <span class="badge badge-light-success fw-bold">System Role</span>
                                            @else
                                                <span class="badge badge-light-primary fw-bold">Custom Role</span>
                                            @endif

                                            <div class="d-flex">
                                                <a href="{{ route('tenant.roles.show', ['subdomain' => request()->route('subdomain'), 'role' => $role->id]) }}"
                                                    class="btn btn-sm btn-light btn-active-primary me-2">View</a>

                                                @if($role->tenant_id && !$role->isSystemRole())
                                                    <a href="{{ route('tenant.roles.edit', ['subdomain' => request()->route('subdomain'), 'role' => $role->id]) }}"
                                                        class="btn btn-sm btn-light btn-active-light-primary me-2">Edit</a>
                                                    <form
                                                        action="{{ route('tenant.roles.destroy', ['subdomain' => request()->route('subdomain'), 'role' => $role->id]) }}"
                                                        method="POST" onsubmit="return confirm('Delete this role?');">
                                                        @csrf @method('DELETE')
                                                        <button type="submit"
                                                            class="btn btn-sm btn-light btn-active-light-danger">Delete</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Card footer-->
                                </div>
                                <!--end::Card-->
                            </div>
                            <!--end::Col-->
                        @endforeach

                        <!--begin::Add new card-->
                        <div class="col-md-4">
                            <!--begin::Card-->
                            <div class="card h-md-100">
                                <!--begin::Card body-->
                                <div class="card-body d-flex flex-center">
                                    <!--begin::Button-->
                                    <a href="{{ route('tenant.roles.create', ['subdomain' => request()->route('subdomain')]) }}"
                                        class="btn btn-clear d-flex flex-column flex-center">
                                        <!--begin::Illustration-->
                                        <img src="{{ asset('assets/media/illustrations/sketchy-1/4.png') }}" alt=""
                                            class="mw-100 mh-100px mb-7" />
                                        <!--end::Illustration-->
                                        <!--begin::Label-->
                                        <div class="fw-bold fs-3 text-gray-600 text-hover-primary">Add New Role</div>
                                        <!--end::Label-->
                                    </a>
                                    <!--begin::Button-->
                                </div>
                                <!--begin::Card body-->
                            </div>
                            <!--begin::Card-->
                        </div>
                        <!--begin::Add new card-->
                    </div>
                    <!--end::Row-->
                </div>
            </div>
        </div>
    </div>
@endsection