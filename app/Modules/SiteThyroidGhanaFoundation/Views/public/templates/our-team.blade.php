@extends($cmsLayout ?? 'site-thyroid-ghana-foundation::public.layouts.website')

@section('seo-title', 'Our Team — ' . $theme->siteName())

@section('seo')
    @include('cms-core::components.seo', [
        'title' => 'Our Team — ' . $theme->siteName(),
        'description' => 'Meet the dedicated leadership team behind the Thyroid Ghana Foundation.',
        'canonical' => $cmsUrl->route('pages.show', 'our-team'),
    ])
@endsection

@section('content')
    {{-- Header --}}
    <section style="background: linear-gradient(135deg, var(--tgf-dark) 0%, #134E4A 100%);">
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8 lg:py-24">
            <nav class="mb-6 flex items-center gap-2 text-sm text-slate-400">
                <a href="{{ $cmsUrl->route('home') }}" class="transition hover:text-white">Home</a>
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-white">Our Team</span>
            </nav>
            <h1 class="text-3xl font-extrabold text-white sm:text-4xl lg:text-5xl">Our Team</h1>
            <p class="mt-4 max-w-2xl text-lg text-slate-300">Meet the dedicated leadership team driving thyroid health advocacy across Ghana.</p>
        </div>
    </section>

    {{-- Management Team --}}
    <section class="py-16 lg:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-12">
                <p class="text-sm font-semibold uppercase tracking-widest" style="color: var(--tgf-primary);">Leadership</p>
                <h2 class="mt-2 text-2xl font-bold" style="color: var(--tgf-dark);">Management Team</h2>
            </div>

            @php
                $team = [
                    [
                        'name' => 'Nana Adwoa Konadu Dsane (Mrs.)',
                        'role' => 'Founder & President',
                        'photo' => 'cms/team/nana-adjoa.jpg',
                        'bio' => 'Established the foundation in 2018 following her personal battle with hyperthyroidism. A seasoned administrator with over 20 years of experience across research, health, and education.',
                    ],
                    [
                        'name' => 'Rev. Prof. Patrick F. Ayeh-Kumi',
                        'role' => 'Management Board Chair',
                        'photo' => 'cms/team/prof-patrick.jpg',
                        'bio' => 'A distinguished academic and researcher who brings decades of medical expertise to the foundation\'s governance and strategic direction.',
                    ],
                    [
                        'name' => 'Mr. Leslie Chartey Kumahlor',
                        'role' => 'Head of Operations',
                        'photo' => 'cms/team/lesley-chartey.jpg',
                        'bio' => 'Oversees the day-to-day operations and ensures our programs reach communities across Ghana effectively.',
                    ],
                    [
                        'name' => 'Dr. Joyce Emefa Addo-Klah',
                        'role' => 'Public Relations Officer',
                        'photo' => 'cms/team/joyce-emefa.jpg',
                        'bio' => 'Leads our communications strategy and public engagement initiatives across media platforms.',
                    ],
                    [
                        'name' => 'Frank Anyimadu',
                        'role' => 'Consultant Dietician',
                        'photo' => 'cms/team/frank-anyimedu.jpg',
                        'bio' => 'Provides nutritional guidance and develops dietary programs tailored for thyroid patients.',
                    ],
                    [
                        'name' => 'Justice Kwesi Baah',
                        'role' => 'Research Coordinator',
                        'photo' => 'cms/team/justice.jpg',
                        'bio' => 'Coordinates research initiatives and partnerships with academic institutions across Ghana.',
                    ],
                ];
            @endphp

            <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($team as $member)
                    <div class="group overflow-hidden rounded-xl bg-white shadow-sm border transition hover:shadow-lg" style="border-color: var(--tgf-border);">
                        <div class="aspect-square overflow-hidden bg-slate-100">
                            <img
                                src="{{ Storage::disk('public')->url($member['photo']) }}"
                                alt="{{ $member['name'] }}"
                                class="h-full w-full object-cover object-top transition group-hover:scale-105"
                                loading="lazy"
                            />
                        </div>
                        <div class="p-6">
                            <h3 class="text-lg font-semibold" style="color: var(--tgf-dark);">{{ $member['name'] }}</h3>
                            <p class="mt-1 text-sm font-medium" style="color: var(--tgf-primary);">{{ $member['role'] }}</p>
                            <p class="mt-3 text-sm leading-relaxed" style="color: var(--tgf-muted);">{{ $member['bio'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Advisory Board --}}
    <section class="border-t py-16" style="background: var(--tgf-light); border-color: var(--tgf-border);">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-12">
                <p class="text-sm font-semibold uppercase tracking-widest" style="color: var(--tgf-primary);">Governance</p>
                <h2 class="mt-2 text-2xl font-bold" style="color: var(--tgf-dark);">Advisory Board</h2>
            </div>

            @php
                $advisors = [
                    ['name' => 'Dr. Josephine Akpalu', 'role' => 'Advisory Board Chair'],
                    ['name' => 'Dr. Alfred Tetteh', 'role' => 'Member, Management Board'],
                    ['name' => 'Dr. Matilda Asante', 'role' => 'Member, Management Board'],
                ];
            @endphp

            <div class="grid gap-6 sm:grid-cols-3">
                @foreach($advisors as $advisor)
                    <div class="rounded-xl bg-white p-6 shadow-sm border" style="border-color: var(--tgf-border);">
                        <div class="flex h-14 w-14 items-center justify-center rounded-full" style="background: var(--tgf-primary-light);">
                            <span class="text-lg font-bold" style="color: var(--tgf-primary);">{{ collect(explode(' ', $advisor['name']))->map(fn($w) => mb_substr($w, 0, 1))->slice(0, 2)->join('') }}</span>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold" style="color: var(--tgf-dark);">{{ $advisor['name'] }}</h3>
                        <p class="mt-1 text-sm" style="color: var(--tgf-primary);">{{ $advisor['role'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="py-14" style="background: var(--tgf-primary);">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-6">
                <div>
                    <h3 class="text-xl font-bold text-white">Join our team of volunteers</h3>
                    <p class="mt-1 text-teal-100">Help us reach more communities and make thyroid health a priority in Ghana.</p>
                </div>
                <div class="flex gap-3">
                    <a href="{{ $cmsUrl->route('pages.show', 'volunteer') }}" class="tgf-btn-accent">Become a Volunteer</a>
                    <a href="{{ $cmsUrl->route('pages.show', 'partner-us') }}" class="rounded-md border-2 border-white px-6 py-3 text-sm font-semibold text-white transition hover:bg-white hover:text-teal-700">Partner With Us</a>
                </div>
            </div>
        </div>
    </section>
@endsection
