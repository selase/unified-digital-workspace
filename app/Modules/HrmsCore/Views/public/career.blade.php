@extends($cmsLayout ?? 'cms-core::public.layouts.website')

@section('seo-title', $posting->title . ' — ' . $theme->siteName())

@section('seo')
    @include('cms-core::components.seo', [
        'title' => $posting->title . ' — ' . $theme->siteName(),
        'description' => \Illuminate\Support\Str::limit(strip_tags((string) $posting->description), 160),
        'canonical' => $careersUrl('careers.show', ['slug' => $posting->slug]),
        'ogImage' => null,
    ])
@endsection

@section('content')
    <section class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
        <a href="{{ $careersUrl('careers.index') }}" class="text-sm text-gray-500 hover:text-gray-800">&larr; All jobs</a>
        <h1 class="mt-3 text-3xl font-bold text-gray-900 sm:text-4xl">{{ $posting->title }}</h1>
        <p class="mt-2 text-sm text-gray-500">
            @if($posting->closing_date) Apply by {{ $posting->closing_date->format('j F Y') }} @else Open until filled @endif
        </p>

        <div class="mt-6 flex flex-col gap-6 text-gray-700">
            @if($posting->description)<div class="whitespace-pre-line">{{ $posting->description }}</div>@endif
            @if($posting->requirements)<div><h2 class="text-lg font-semibold text-gray-900">Requirements</h2><div class="mt-1 whitespace-pre-line">{{ $posting->requirements }}</div></div>@endif
            @if($posting->benefits)<div><h2 class="text-lg font-semibold text-gray-900">Benefits</h2><div class="mt-1 whitespace-pre-line">{{ $posting->benefits }}</div></div>@endif
        </div>

        <div class="mt-10 rounded-xl bg-white p-6 shadow-sm" id="apply">
            <h2 class="text-xl font-semibold text-gray-900">Apply for this job</h2>

            @if(session('applied'))
                <div class="mt-4 rounded-lg bg-green-50 p-4 text-green-800">{{ session('applied') }}</div>
            @else
                @if($errors->any())
                    <div class="mt-4 rounded-lg bg-red-50 p-4 text-sm text-red-700">
                        @foreach($errors->all() as $message)<div>{{ $message }}</div>@endforeach
                    </div>
                @endif

                <form method="POST" action="{{ $careersUrl('careers.apply', ['slug' => $posting->slug]) }}" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <input type="hidden" name="form_token" value="{{ $formToken }}">
                    <div class="hidden" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

                    <label class="text-sm text-gray-700">First name
                        <input class="mt-1 w-full rounded-lg bg-gray-50 px-3 py-2" name="first_name" value="{{ old('first_name') }}" required>
                    </label>
                    <label class="text-sm text-gray-700">Last name
                        <input class="mt-1 w-full rounded-lg bg-gray-50 px-3 py-2" name="last_name" value="{{ old('last_name') }}" required>
                    </label>
                    <label class="text-sm text-gray-700">Email
                        <input class="mt-1 w-full rounded-lg bg-gray-50 px-3 py-2" type="email" name="email" value="{{ old('email') }}" required>
                    </label>
                    <label class="text-sm text-gray-700">Phone
                        <input class="mt-1 w-full rounded-lg bg-gray-50 px-3 py-2" name="phone" value="{{ old('phone') }}">
                    </label>
                    <label class="text-sm text-gray-700">Current employer
                        <input class="mt-1 w-full rounded-lg bg-gray-50 px-3 py-2" name="current_employer" value="{{ old('current_employer') }}">
                    </label>
                    <label class="text-sm text-gray-700">Current position
                        <input class="mt-1 w-full rounded-lg bg-gray-50 px-3 py-2" name="current_position" value="{{ old('current_position') }}">
                    </label>
                    <label class="text-sm text-gray-700">Years of experience
                        <input class="mt-1 w-full rounded-lg bg-gray-50 px-3 py-2" type="number" min="0" max="60" name="years_of_experience" value="{{ old('years_of_experience') }}">
                    </label>
                    <label class="text-sm text-gray-700">Highest qualification
                        <input class="mt-1 w-full rounded-lg bg-gray-50 px-3 py-2" name="highest_qualification" value="{{ old('highest_qualification') }}">
                    </label>
                    <label class="text-sm text-gray-700 sm:col-span-2">Why are you a good fit?
                        <textarea class="mt-1 w-full rounded-lg bg-gray-50 px-3 py-2" rows="6" name="cover_letter" maxlength="5000">{{ old('cover_letter') }}</textarea>
                    </label>
                    <div class="sm:col-span-2"><button type="submit" class="rounded-lg bg-gray-900 px-5 py-2.5 text-white hover:bg-gray-700">Submit application</button></div>
                </form>
            @endif
        </div>
    </section>
@endsection
