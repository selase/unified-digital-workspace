{{--
    Public enquiry form that emails the site owner.

    Usage: @include('cms-core::components.enquiry-form', ['type' => 'volunteer'])
    Works without JavaScript (plain POST + redirect) and is progressively
    enhanced with Alpine so visitors get inline feedback.
--}}
@php
    $enquiry = $type instanceof \App\Modules\CmsCore\Support\EnquiryType
        ? $type
        : \App\Modules\CmsCore\Support\EnquiryType::tryFrom((string) $type);
    $initialStatus = in_array(request('enquiry'), ['sent', 'failed', 'invalid'], true) ? request('enquiry') : '';
@endphp

@if($enquiry)
    <section id="enquiry" class="mt-12 scroll-mt-24">
        <div
            class="rounded-xl bg-white p-6 shadow-sm sm:p-8"
            x-data="{
                sending: false,
                status: '{{ $initialStatus }}',
                message: '',
                errors: {},
                async submit(form) {
                    this.sending = true;
                    this.status = '';
                    this.errors = {};
                    try {
                        const response = await fetch(form.action, { method: 'POST', headers: { 'Accept': 'application/json' }, body: new FormData(form) });
                        const data = await response.json().catch(() => ({}));
                        if (response.ok) {
                            this.status = 'sent';
                            this.message = data.message || 'Thank you. Your message has been sent.';
                            form.reset();
                        } else if (response.status === 422) {
                            this.status = 'invalid';
                            this.errors = data.errors || {};
                            this.message = data.message || 'Please check the fields below.';
                        } else if (response.status === 429) {
                            this.status = 'failed';
                            this.message = 'Too many attempts. Please wait a few minutes and try again.';
                        } else {
                            this.status = 'failed';
                            this.message = data.message || 'Something went wrong. Please try again.';
                        }
                    } catch (error) {
                        this.status = 'failed';
                        this.message = 'We could not reach the server. Please check your connection and try again.';
                    }
                    this.sending = false;
                },
            }"
        >
            <h2 class="text-xl font-bold" style="color: var(--tgf-dark, #0f172a);">{{ $enquiry->heading() }}</h2>
            <p class="mt-1 text-sm" style="color: var(--tgf-muted, #64748b);">Fill in the form and we will get back to you by email.</p>

            <div class="mt-5" aria-live="polite">
                <p x-show.important="status === 'sent'" style="display: none;" class="rounded-md bg-emerald-50 p-4 text-sm font-medium text-emerald-800">
                    <span x-text="message"></span>
                </p>
                <p x-show.important="status === 'invalid' || status === 'failed'" style="display: none;" class="rounded-md bg-red-50 p-4 text-sm font-medium text-red-800">
                    <span x-text="message"></span>
                </p>

                @if($initialStatus === 'sent')
                    <noscript><p class="rounded-md bg-emerald-50 p-4 text-sm font-medium text-emerald-800">Thank you. Your message has been sent and we will be in touch soon.</p></noscript>
                @elseif($initialStatus !== '')
                    <noscript><p class="rounded-md bg-red-50 p-4 text-sm font-medium text-red-800">We could not send your message. Please check the fields and try again, or email us directly.</p></noscript>
                @endif
            </div>

            <form action="{{ $cmsUrl->route('enquiries.store') }}" method="POST" class="mt-6 grid gap-5 sm:grid-cols-2" @submit.prevent="submit($el)">
                <input type="hidden" name="type" value="{{ $enquiry->value }}">
                <input type="hidden" name="form_token" value="{{ \App\Modules\CmsCore\Support\EnquiryToken::issue() }}">
                <div style="position: absolute; left: -9999px;" aria-hidden="true">
                    <label for="enquiry-website">Leave this field empty</label>
                    <input id="enquiry-website" type="text" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div>
                    <label for="enquiry-name" class="block text-sm font-medium" style="color: var(--tgf-dark, #0f172a);">Your name</label>
                    <input id="enquiry-name" name="name" type="text" required maxlength="120" autocomplete="name" class="mt-1 block w-full rounded-md bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:outline-none focus:ring-2">
                    <p x-show.important="errors.name" style="display: none;" class="mt-1 text-xs text-red-700" x-text="errors.name && errors.name[0]"></p>
                </div>

                <div>
                    <label for="enquiry-email" class="block text-sm font-medium" style="color: var(--tgf-dark, #0f172a);">Email address</label>
                    <input id="enquiry-email" name="email" type="email" required maxlength="190" autocomplete="email" class="mt-1 block w-full rounded-md bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:outline-none focus:ring-2">
                    <p x-show.important="errors.email" style="display: none;" class="mt-1 text-xs text-red-700" x-text="errors.email && errors.email[0]"></p>
                </div>

                <div>
                    <label for="enquiry-phone" class="block text-sm font-medium" style="color: var(--tgf-dark, #0f172a);">Phone <span class="font-normal text-slate-500">(optional)</span></label>
                    <input id="enquiry-phone" name="phone" type="tel" maxlength="40" autocomplete="tel" class="mt-1 block w-full rounded-md bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:outline-none focus:ring-2">
                    <p x-show.important="errors.phone" style="display: none;" class="mt-1 text-xs text-red-700" x-text="errors.phone && errors.phone[0]"></p>
                </div>

                @if($enquiry->asksForOrganisation())
                    <div>
                        <label for="enquiry-organisation" class="block text-sm font-medium" style="color: var(--tgf-dark, #0f172a);">Organisation <span class="font-normal text-slate-500">(optional)</span></label>
                        <input id="enquiry-organisation" name="organisation" type="text" maxlength="160" autocomplete="organization" class="mt-1 block w-full rounded-md bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:outline-none focus:ring-2">
                    </div>
                @endif

                <div class="sm:col-span-2">
                    <label for="enquiry-message" class="block text-sm font-medium" style="color: var(--tgf-dark, #0f172a);">{{ $enquiry->messageLabel() }}</label>
                    <textarea id="enquiry-message" name="message" rows="5" required minlength="10" maxlength="4000" class="mt-1 block w-full rounded-md bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:outline-none focus:ring-2"></textarea>
                    <p x-show.important="errors.message" style="display: none;" class="mt-1 text-xs text-red-700" x-text="errors.message && errors.message[0]"></p>
                </div>

                <div class="sm:col-span-2">
                    <button type="submit" :disabled="sending" class="rounded-md px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:opacity-90 disabled:opacity-60" style="background: var(--tgf-primary, #0d9488);">
                        <span x-show.important="!sending">{{ $enquiry->submitLabel() }}</span>
                        <span x-show.important="sending" style="display: none;">Sending&hellip;</span>
                    </button>
                </div>
            </form>
        </div>
    </section>
@endif
