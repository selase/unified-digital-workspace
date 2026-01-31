@extends('layouts.admin.master')

@section('title', 'Pricing Plans')

@section('content')
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        <div class="card" id="kt_pricing">
            <div class="card-body p-lg-17">
                <div class="d-flex flex-column">
                    <div class="mb-13 text-center">
                        <h1 class="fs-2hx fw-bolder mb-5">Choose Your Plan</h1>
                        <div class="text-gray-400 fw-bold fs-5">
                            Whether you're just starting out or scaling globally, we have the right plan for you.
                        </div>
                    </div>

                    <div class="row g-10">
                        @foreach($packages as $package)
                        <div class="col-xl-4">
                            <div class="d-flex h-100 align-items-center">
                                <div class="w-100 d-flex flex-column flex-center rounded-3 bg-light bg-opacity-75 py-15 px-10 {{ $currentPackage?->id === $package->id ? 'border border-primary border-dashed' : '' }}">
                                    @if($currentPackage?->id === $package->id)
                                        <span class="badge badge-primary mb-5 uppercase">Current Plan</span>
                                    @endif
                                    
                                    <div class="mb-7 text-center">
                                        <h1 class="text-dark mb-5 fw-boldest">{{ $package->name }}</h1>
                                        <div class="text-gray-400 fw-bold mb-5">{{ $package->description }}</div>
                                        <div class="text-center">
                                            <span class="mb-2 text-primary">$</span>
                                            <span class="fs-3x fw-boldest text-primary">{{ number_format($package->price, 0) }}</span>
                                            <span class="fs-7 fw-bold opacity-50">/ {{ $package->interval }}</span>
                                        </div>
                                    </div>

                                    <div class="w-100 mb-10">
                                        @foreach($package->features as $feature)
                                            <div class="d-flex align-items-center mb-5">
                                                <span class="svg-icon svg-icon-2 svg-icon-success me-3">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                        <rect opacity="0.3" x="2" y="2" width="20" height="20" rx="10" fill="currentColor"></rect>
                                                        <path d="M10.4343 12.4343L8.75 10.75C8.33579 10.3358 7.66421 10.3358 7.25 10.75C6.83579 11.1642 6.83579 11.8358 7.25 12.25L10.2929 15.2929C10.6834 15.6834 11.3166 15.6834 11.7071 15.2929L17.25 9.75C17.6642 9.33579 17.6642 8.66421 17.25 8.25C16.8358 7.83579 16.1642 7.83579 15.75 8.25L10.4343 12.4343Z" fill="currentColor"></path>
                                                    </svg>
                                                </span>
                                                <div class="text-gray-800 fw-boldest fs-6">{{ $feature->name }}</div>
                                            </div>
                                        @endforeach
                                    </div>

                                    @if($currentPackage?->id === $package->id)
                                        <button class="btn btn-sm btn-light-primary disabled w-100">Active</button>
                                    @else
                                        <form action="{{ route('billing.checkout') }}" method="POST" class="w-100">
                                            @csrf
                                            <input type="hidden" name="plan" value="{{ $package->slug }}">
                                            <button type="submit" class="btn btn-sm btn-primary w-100">Upgrade to {{ $package->name }}</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
