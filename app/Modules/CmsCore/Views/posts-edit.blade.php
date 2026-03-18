@extends('layouts.metronic.app')

@section('title', 'Edit Post')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">CMS Core</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Edit Post</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Update publishing status, taxonomy assignments, and content body.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.posts.show', $post) }}">View Post</a>
                    <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.posts.index') }}">Back to Library</a>
                </div>
            </div>
        </div>

        <div class="kt-card p-6">
            <form action="{{ route('cms-core.posts.update', $post) }}" class="kt-form" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('cms-core::partials.post-form', ['post' => $post])

                <div class="mt-8 flex items-center justify-end gap-3">
                    <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.posts.show', $post) }}">Cancel</a>
                    <button class="kt-btn kt-btn-primary" type="submit">Save Changes</button>
                </div>
            </form>
        </div>
    </section>
@endsection
