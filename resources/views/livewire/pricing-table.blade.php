<div id="pricing" class="mt-20 md:mt-28">
    <div class="mx-auto max-w-6xl px-6">
        <div class="text-center">
            <h2
                class="text-[40px] leading-[40px] font-medium tracking-[-0.01em] text-white/95 md:text-[48px] md:leading-[48px]">
                Simple, transparent pricing
            </h2>
            <p class="mx-auto mt-4 max-w-2xl text-[18px] leading-[28px] font-light text-white/70">
                Choose the plan that's right for your business. All plans include a 14-day free trial.
            </p>

            {{-- Toggle --}}
            <div class="mt-10 flex items-center justify-center gap-4">
                <span class="text-sm {{ $billingCycle === 'monthly' ? 'text-white' : 'text-white/40' }}">Monthly</span>
                <button wire:click="setBillingCycle('{{ $billingCycle === 'monthly' ? 'yearly' : 'monthly' }}')"
                    class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none bg-white/10">
                    <span aria-hidden="true"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-blue-600 shadow ring-0 transition duration-200 ease-in-out {{ $billingCycle === 'yearly' ? 'translate-x-5' : 'translate-x-0' }}"></span>
                </button>
                <div class="flex items-center gap-2">
                    <span
                        class="text-sm {{ $billingCycle === 'yearly' ? 'text-white' : 'text-white/40' }}">Yearly</span>
                    <span
                        class="inline-flex items-center rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-400 border border-emerald-500/20">
                        2 months free
                    </span>
                </div>
            </div>
        </div>

        <div class="mt-16 grid gap-8 lg:grid-cols-3">
            @foreach ($plans as $plan)
                <div data-most-popular="{{ $plan['most_popular'] ? 'true' : 'false' }}"
                    class="relative flex flex-col rounded-3xl border {{ $plan['most_popular'] ? 'border-blue-500/50 bg-blue-500/5 shadow-2xl shadow-blue-500/10' : 'border-white/10 bg-white/[0.03]' }} p-8 transition-all hover:bg-white/[0.05]">
                    @if ($plan['most_popular'])
                        <div class="absolute -top-4 left-1/2 -translate-x-1/2">
                            <span
                                class="inline-flex items-center rounded-full bg-blue-600 px-3 py-1 text-xs font-bold uppercase tracking-wider text-white">
                                Most Popular
                            </span>
                        </div>
                    @endif

                    <div class="flex-1">
                        <h3 class="text-2xl font-medium text-white/95">{{ $plan['name'] }}</h3>
                        <p class="mt-4 text-sm font-light text-white/55">{{ $plan['description'] }}</p>

                        <div class="mt-8 flex items-baseline gap-1">
                            <span class="text-4xl font-semibold text-white tracking-tight">
                                ${{ $billingCycle === 'monthly' ? $plan['monthly_price'] : $plan['yearly_price'] }}
                            </span>
                            <span
                                class="text-sm font-light text-white/40">/{{ $billingCycle === 'monthly' ? 'mo' : 'yr' }}</span>
                        </div>

                        <ul class="mt-8 space-y-4">
                            @foreach ($plan['features'] as $feature)
                                <li class="flex items-start gap-3 text-sm font-light text-white/70">
                                    <svg class="h-5 w-5 flex-shrink-0 text-emerald-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    {{ $feature }}
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <a href="{{ $plan['cta']['href'] }}"
                        class="mt-8 inline-flex items-center justify-center rounded-xl {{ $plan['most_popular'] ? 'bg-blue-600 shadow-lg shadow-blue-600/30 hover:bg-blue-500' : 'bg-white/10 hover:bg-white/20' }} px-4 py-3 text-sm font-medium text-white transition-all">
                        {{ $plan['cta']['label'] }}
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</div>