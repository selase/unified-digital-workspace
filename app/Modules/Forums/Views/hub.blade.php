@extends('layouts.admin.master')

@section('title', 'Forums Hub')

@section('content')
    <div class="post d-flex flex-column-fluid" id="kt_post">
        <div id="kt_content_container" class="container-xxl">
            <div class="card mb-7">
                <div class="card-body d-flex flex-stack flex-wrap gap-4">
                    <div>
                        <h2 class="fw-bolder mb-1">Forums Hub</h2>
                        <div class="text-muted">Channels, live discussions, and moderation overview.</div>
                    </div>
                    <a href="{{ route('api.forums.v1.channels.index') }}" class="btn btn-light-primary">Open Channels API</a>
                </div>
            </div>

            <div class="row g-5 g-xl-8 mb-7">
                <div class="col-xl-4">
                    <div class="card h-100">
                        <div class="card-header border-0">
                            <h3 class="card-title fw-bold">Top Channels</h3>
                        </div>
                        <div class="card-body pt-0">
                            @forelse($channels as $channel)
                                <div class="d-flex justify-content-between align-items-center py-3 border-bottom border-gray-100">
                                    <div>
                                        <div class="fw-semibold">{{ $channel->name }}</div>
                                        <div class="text-muted fs-7">{{ $channel->slug }}</div>
                                    </div>
                                    <span class="badge badge-light-primary">{{ $channel->threads_count }} threads</span>
                                </div>
                            @empty
                                <div class="text-muted">No channels yet.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="col-xl-8">
                    <div class="card h-100">
                        <div class="card-header border-0">
                            <h3 class="card-title fw-bold">Latest Threads</h3>
                        </div>
                        <div class="card-body pt-0">
                            @forelse($latestThreads as $thread)
                                <div class="py-3 border-bottom border-gray-100">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="fw-semibold">{{ $thread->title }}</div>
                                        <span class="badge badge-light">{{ $thread->status }}</span>
                                    </div>
                                    <div class="text-muted fs-7">
                                        Channel: {{ $thread->channel?->name ?? 'N/A' }} - Updated {{ $thread->updated_at?->diffForHumans() }}
                                    </div>
                                </div>
                            @empty
                                <div class="text-muted">No threads yet.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-5 g-xl-8">
                <div class="col-xl-6">
                    <div class="card h-100">
                        <div class="card-header border-0">
                            <h3 class="card-title fw-bold">Flagged Threads</h3>
                        </div>
                        <div class="card-body pt-0">
                            @forelse($flaggedThreads as $thread)
                                <div class="d-flex justify-content-between align-items-center py-3 border-bottom border-gray-100">
                                    <span class="fw-semibold">{{ $thread->title }}</span>
                                    <span class="badge badge-light-danger">flagged</span>
                                </div>
                            @empty
                                <div class="text-muted">No flagged threads.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="col-xl-6">
                    <div class="card h-100">
                        <div class="card-header border-0">
                            <h3 class="card-title fw-bold">Moderation Activity</h3>
                        </div>
                        <div class="card-body pt-0">
                            @forelse($latestModerationLogs as $log)
                                <div class="py-3 border-bottom border-gray-100">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-semibold text-capitalize">{{ $log->action }}</span>
                                        <span class="text-muted fs-7">{{ $log->created_at?->diffForHumans() }}</span>
                                    </div>
                                    <div class="text-muted fs-7">{{ $log->reason ?: 'No reason provided.' }}</div>
                                </div>
                            @empty
                                <div class="text-muted">No moderation logs yet.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
