@extends('layouts.metronic.app')

@section('title', 'Create Post')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">CMS Core</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Create Post</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Compose, classify, and publish content with structured taxonomy.</p>
                </div>
                <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.posts.index') }}">Back to Library</a>
            </div>
        </div>

        <div class="kt-card p-6">
            <form action="{{ route('cms-core.posts.store') }}" class="kt-form" method="POST" enctype="multipart/form-data">
                @csrf
                @include('cms-core::partials.post-form', ['post' => null])

                <div class="mt-8 flex items-center justify-end gap-3">
                    <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.posts.index') }}">Cancel</a>
                    <button class="kt-btn kt-btn-primary" type="submit">Create Post</button>
                </div>
            </form>
        </div>
    </section>
@endsection
