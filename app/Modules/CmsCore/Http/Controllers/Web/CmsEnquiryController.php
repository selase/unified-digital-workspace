<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\CmsCore\Http\Requests\EnquiryStoreRequest;
use App\Modules\CmsCore\Mail\SiteEnquiryReceived;
use App\Modules\CmsCore\Services\CmsThemeService;
use App\Modules\CmsCore\Support\EnquiryToken;
use App\Modules\CmsCore\Support\EnquiryType;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Receives public enquiry forms (volunteer, membership, partnership, donation)
 * and emails them to the site owner.
 *
 * Nothing is stored: if the mail transport fails, the submission is written to
 * the application log so it can be recovered, and the visitor is told to email
 * the owner directly. Spam is held back by a honeypot field, a signed
 * render-time token (see EnquiryToken) and the route's rate limit.
 */
final class CmsEnquiryController extends Controller
{
    public function __construct(
        private readonly CmsThemeService $theme,
        private readonly TenantContext $tenantContext,
    ) {}

    public function store(EnquiryStoreRequest $request): JsonResponse|RedirectResponse
    {
        $data = $request->validated();

        if (filled($data['website'] ?? null)) {
            return $this->respond($request, true, 'Thank you. Your message has been sent.');
        }

        if (! EnquiryToken::isValid($data['form_token'])) {
            return $this->respond($request, false, 'This form has expired. Please reload the page and try again.', 422);
        }

        $recipient = $this->recipient();

        if ($recipient === null) {
            Log::error('Site enquiry received but no recipient is configured', ['tenant_id' => $this->tenantContext->getTenant()?->id]);

            return $this->respond($request, false, 'We cannot take messages through this form right now. Please contact us directly.', 503);
        }

        $type = EnquiryType::from($data['type']);

        try {
            Mail::to($recipient)->send(new SiteEnquiryReceived(
                $type,
                $this->theme->siteName(),
                $data['name'],
                $data['email'],
                $data['phone'] ?? null,
                $data['organisation'] ?? null,
                $data['message'],
            ));
        } catch (Throwable $exception) {
            Log::error('Site enquiry could not be emailed', [
                'tenant_id' => $this->tenantContext->getTenant()?->id,
                'type' => $type->value,
                'error' => $exception->getMessage(),
                'enquiry' => Arr::only($data, ['name', 'email', 'phone', 'organisation', 'message']),
            ]);

            return $this->respond($request, false, "We could not send your message just now. Please email us at {$recipient} instead.", 500);
        }

        return $this->respond($request, true, 'Thank you. Your message has been sent and we will be in touch soon.');
    }

    private function recipient(): ?string
    {
        foreach ([$this->theme->get('enquiry_email'), $this->tenantContext->getTenant()?->email] as $candidate) {
            if (is_string($candidate) && ! Validator::make(['email' => $candidate], ['email' => 'email'])->fails()) {
                return $candidate;
            }
        }

        return null;
    }

    private function respond(EnquiryStoreRequest $request, bool $sent, string $message, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['sent' => $sent, 'message' => $message], $sent ? 200 : $status);
        }

        $path = '/'.mb_ltrim((string) parse_url((string) $request->headers->get('referer'), PHP_URL_PATH), '/');

        return redirect()->to($path.'?enquiry='.($sent ? 'sent' : 'failed').'#enquiry');
    }
}
