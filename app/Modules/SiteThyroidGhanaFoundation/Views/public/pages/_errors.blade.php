@if(session('error') || $errors->any())
    <div class="mb-6 rounded-lg bg-red-50 p-4 text-sm text-red-700" role="alert">
        @if(session('error'))
            <p>{{ session('error') }}</p>
        @else
            <p class="font-semibold">Please check the form and try again.</p>
            <ul class="mt-1 list-disc pl-5">
                @foreach($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
