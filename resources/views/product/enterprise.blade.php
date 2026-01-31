@extends('layouts.product')

@section('title', 'Talk to our sales team - ' . config('product-page.brand.name'))

@section('content')
    <section class="pt-20 md:pt-32 pb-20">
        <div class="mx-auto max-w-7xl px-6">
            <div class="grid gap-16 lg:grid-cols-2 lg:items-center">
                {{-- Left Side: Text & Quote --}}
                <div class="max-w-2xl">
                    <h1
                        class="text-[52px] leading-[52px] font-medium tracking-[-0.02em] text-white md:text-[72px] md:leading-[72px]">
                        Talk to our sales team
                    </h1>

                    <p class="mt-8 text-[20px] leading-[30px] font-normal text-white/55">
                        {{ config('product-page.brand.name') }} is purpose-built to help you scale your SaaS with
                        confidence.
                        We can help if you process billions of events, have specific security requirements, or need custom
                        enterprise support.
                    </p>

                    {{-- Quote and Visual --}}
                    <div class="mt-16 border-l-2 border-emerald-500 pl-8">
                        <div class="text-emerald-500 text-3xl leading-none mb-4"><i class="fas fa-quote-left"></i></div>
                        <p class="text-white/80 italic text-lg">
                            "The automated tenant isolation and KMS-backed encryption allowed us to meet our compliance requirements in weeks, not months. A game-changer for enterprise readiness."
                        </p>
                        <div class="mt-4 text-white font-medium">Marc G. <span class="text-white/40">· CTO at SecureLogix</span></div>
                    </div>

                </div>

                {{-- Right Side: Form Card --}}
                <div class="relative">
                    {{-- Glow behind card --}}
                    <div class="absolute -inset-4 bg-blue-500/10 blur-3xl rounded-3xl -z-10"></div>

                    <div class="rounded-3xl border border-white/10 bg-white/[0.03] p-8 md:p-12 shadow-2xl backdrop-blur-sm">
                        <h3 class="text-2xl font-medium text-white mb-8">Tell us how we can help</h3>

                        @if (session('success'))
                            <div class="mb-8 rounded-xl bg-emerald-500/10 border border-emerald-500/20 p-4 text-emerald-500 text-sm">
                                {{ session('success') }}
                            </div>
                        @endif

                        @if (session('error'))
                            <div class="mb-8 rounded-xl bg-red-500/10 border border-red-500/20 p-4 text-red-500 text-sm">
                                {{ session('error') }}
                            </div>
                        @endif

                        <form action="{{ route('product.enterprise.lead') }}" method="POST" class="space-y-6">
                            @csrf
                            <div>
                                <label for="name" class="block text-sm font-medium text-white/70 mb-2">Full name</label>
                                <input type="text" name="name" id="name" placeholder="Enter full name" value="{{ old('name') }}" required
                                    class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white placeholder:text-white/20 focus:outline-none focus:ring-2 focus:ring-blue-500/40 transition">
                                @error('name') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="email" class="block text-sm font-medium text-white/70 mb-2">Company
                                    email</label>
                                <input type="email" name="email" id="email" placeholder="Enter your email" value="{{ old('email') }}" required
                                    class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white placeholder:text-white/20 focus:outline-none focus:ring-2 focus:ring-blue-500/40 transition">
                                @error('email') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                            </div>

                             <div>
                                <label for="company" class="block text-sm font-medium text-white/70 mb-2">Company name</label>
                                <input type="text" name="company" id="company" placeholder="Your company" value="{{ old('company') }}"
                                    class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white placeholder:text-white/20 focus:outline-none focus:ring-2 focus:ring-blue-500/40 transition">
                                @error('company') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="message" class="block text-sm font-medium text-white/70 mb-2">How can we
                                    help?</label>
                                <textarea name="message" id="message" rows="4" required
                                    placeholder="I'm interested in {{ config('product-page.brand.name') }}. I'd like to learn more about..."
                                    class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white placeholder:text-white/20 focus:outline-none focus:ring-2 focus:ring-blue-500/40 transition">{{ old('message') }}</textarea>
                                @error('message') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                            </div>

                            <button type="submit"
                                class="w-full rounded-xl bg-blue-600 px-6 py-4 text-base font-semibold text-white shadow-lg shadow-blue-600/20 hover:bg-blue-500 transition-all hover:scale-[1.02] active:scale-[0.98]">
                                Send message
                            </button>

                            <p class="text-center text-xs text-white/30 mt-6">
                                By clicking send, you agree to our <a href="#"
                                    class="underline hover:text-white transition">Privacy Policy</a>.
                            </p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection