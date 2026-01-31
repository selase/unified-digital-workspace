<div class="toolbar" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack">
        <div data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_content_container', 'lg': '#kt_toolbar_container'}" class="page-title d-flex align-items-center flex-wrap me-3 mb-5 mb-lg-0">
            <h1 class="d-flex align-items-center text-dark fw-bolder fs-3 my-1">@yield('title')</h1>
            <span class="h-20px border-gray-300 border-start mx-4"></span>
            @if (isset($breadcrumbs))
                <ul class="breadcrumb breadcrumb-separatorless fw-bold fs-7 my-1">
                    @foreach ($breadcrumbs as $breadcrumb)
                        <li class="breadcrumb-item text-muted">
                            @if(isset($breadcrumb['link']))
                            <a href="{{ $breadcrumb['link'] }}" class="text-muted text-hover-primary">
                                @endif
                                {{ $breadcrumb['name'] }}
                                @if(isset($breadcrumb['link']))
                            </a>
                            @endif
                        </li>
                        <li class="breadcrumb-item">
                            <span class="bullet bg-gray-300 w-5px h-2px"></span>
                        </li>
                    @endforeach
                    <li class="breadcrumb-item text-dark">@yield('title')</li>
                </ul>
            @endif

        </div>
        <div class="d-flex align-items-center gap-2 gap-lg-3">
            @yield('breadcrumb-actions')
            
            {{-- Superadmin Tenant Context Return --}}
            @if (Auth::check() && Auth::user()->isGlobalSuperAdmin() && app(\App\Services\Tenancy\TenantContext::class)->getTenant())
                <a href="{{ route('tenants.reset') }}" class="btn btn-light-primary me-2">
                    <span class="svg-icon svg-icon-muted svg-icon-2">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path opacity="0.3" d="M21 22H3C2.4 22 2 21.6 2 21V3C2 2.4 2.4 2 3 2H21C21.6 2 22 2.4 22 3V21C22 21.6 21.6 22 21 22Z" fill="currentColor"/>
                            <path d="M19 11H8.60001L13.3 6.29999C13.7 5.89999 13.7 5.3 13.3 4.9C12.9 4.5 12.3 4.5 11.9 4.9L5.50001 11.3C5.10001 11.7 5.10001 12.3 5.50001 12.7L11.9 19.1C12.3 19.5 12.9 19.5 13.3 19.1C13.7 18.7 13.7 18.1 13.3 17.7L8.60001 13H19C19.6 13 20 12.6 20 12C20 11.4 19.6 11 19 11Z" fill="currentColor"/>
                        </svg>
                    </span>
                    Return to Global View
                </a>
            @endif

             {{--  user impersonation  --}}
            @if (Session::has('original_user'))
                <a href="{{ route('impersonation.stop') }}" class="btn btn-light-danger">
                    <span class="svg-icon svg-icon-muted svg-icon-2">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path opacity="0.3" d="M3 6C2.4 6 2 5.6 2 5V3C2 2.4 2.4 2 3 2H5C5.6 2 6 2.4 6 3C6 3.6 5.6 4 5 4H4V5C4 5.6 3.6 6 3 6ZM22 5V3C22 2.4 21.6 2 21 2H19C18.4 2 18 2.4 18 3C18 3.6 18.4 4 19 4H20V5C20 5.6 20.4 6 21 6C21.6 6 22 5.6 22 5ZM6 21C6 20.4 5.6 20 5 20H4V19C4 18.4 3.6 18 3 18C2.4 18 2 18.4 2 19V21C2 21.6 2.4 22 3 22H5C5.6 22 6 21.6 6 21ZM22 21V19C22 18.4 21.6 18 21 18C20.4 18 20 18.4 20 19V20H19C18.4 20 18 20.4 18 21C18 21.6 18.4 22 19 22H21C21.6 22 22 21.6 22 21ZM16 11V9C16 6.8 14.2 5 12 5C9.8 5 8 6.8 8 9V11C7.2 11 6.5 11.7 6.5 12.5C6.5 13.3 7.2 14 8 14V15C8 17.2 9.8 19 12 19C14.2 19 16 17.2 16 15V14C16.8 14 17.5 13.3 17.5 12.5C17.5 11.7 16.8 11 16 11ZM13.4 15C13.7 15 14 15.3 13.9 15.6C13.6 16.4 12.9 17 12 17C11.1 17 10.4 16.5 10.1 15.7C10 15.4 10.2 15 10.6 15H13.4Z" fill="currentColor"/>
                            <path d="M9.2 12.9C9.1 12.8 9.10001 12.7 9.10001 12.6C9.00001 12.2 9.3 11.7 9.7 11.6C10.1 11.5 10.6 11.8 10.7 12.2C10.7 12.3 10.7 12.4 10.7 12.5L9.2 12.9ZM14.8 12.9C14.9 12.8 14.9 12.7 14.9 12.6C15 12.2 14.7 11.7 14.3 11.6C13.9 11.5 13.4 11.8 13.3 12.2C13.3 12.3 13.3 12.4 13.3 12.5L14.8 12.9ZM16 7.29998C16.3 6.99998 16.5 6.69998 16.7 6.29998C16.3 6.29998 15.8 6.30001 15.4 6.20001C15 6.10001 14.7 5.90001 14.4 5.70001C13.8 5.20001 13 5.00002 12.2 4.90002C9.9 4.80002 8.10001 6.79997 8.10001 9.09997V11.4C8.90001 10.7 9.40001 9.8 9.60001 9C11 9.1 13.4 8.69998 14.5 8.29998C14.7 9.39998 15.3 10.5 16.1 11.4V9C16.1 8.5 16 8 15.8 7.5C15.8 7.5 15.9 7.39998 16 7.29998Z" fill="currentColor"/>
                        </svg>
                    </span>
                    Return To Main Account
                </a>
            @endif
        </div>
    </div>
</div>
