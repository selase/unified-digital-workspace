@extends('layouts.metronic.app')

@section('title', 'Edit Post')

@section('content')
<div class="flex flex-col gap-6">
    {{-- Page Header --}}
    <x-metronic.page-header
        title="Edit Post: {{ $post->title }}"
        description="Update publishing status, taxonomy assignments, and content body."
    >
        <x-slot:actions>
            <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.posts.show', $post) }}">
                View Post
            </a>
            <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.posts.index') }}">
                Back to Library
            </a>
        </x-slot:actions>
    </x-metronic.page-header>

    <div class="kt-card p-6">
        <form action="{{ route('cms-core.posts.update', $post) }}" class="kt-form" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('cms-core::partials.post-form', ['post' => $post])

            <div class="mt-8 flex items-center justify-end gap-3 pt-4 border-t border-border">
                <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.posts.show', $post) }}">Cancel</a>
                <button class="kt-btn kt-btn-primary" type="submit">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection
