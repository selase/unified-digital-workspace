@extends('layouts.metronic.app')

@section('title', 'Create Post')

@section('content')
<div class="flex flex-col gap-6">
    {{-- Page Header --}}
    <x-metronic.page-header
        title="Create Post"
        description="Compose, classify, and publish content with structured taxonomy and media assets."
    >
        <x-slot:actions>
            <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.posts.index') }}">
                Back to Library
            </a>
        </x-slot:actions>
    </x-metronic.page-header>

    <div class="kt-card p-6">
        <form action="{{ route('cms-core.posts.store') }}" class="kt-form" method="POST" enctype="multipart/form-data">
            @csrf
            @include('cms-core::partials.post-form', ['post' => null])

            <div class="mt-8 flex items-center justify-end gap-3 pt-4 border-t border-border">
                <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.posts.index') }}">Cancel</a>
                <button class="kt-btn kt-btn-primary" type="submit">Create Post</button>
            </div>
        </form>
    </div>
</div>
@endsection
