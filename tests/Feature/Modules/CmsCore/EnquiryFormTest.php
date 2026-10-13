<?php

declare(strict_types=1);

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\TenantModule;
use App\Modules\CmsCore\Database\Factories\PostFactory;
use App\Modules\CmsCore\Database\Factories\PostTypeFactory;
use App\Modules\CmsCore\Mail\SiteEnquiryReceived;
use App\Modules\CmsCore\Models\PostMeta;
use App\Modules\CmsCore\Models\Setting;
use App\Modules\CmsCore\Support\EnquiryToken;
use App\Modules\CmsCore\Support\EnquiryType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

const ENQUIRY_URL = 'https://client-site.example/enquiries';

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function enquiryPayload(array $overrides = []): array
{
    return $overrides + [
        'type' => 'volunteer',
        'name' => 'Jane Mensah',
        'email' => 'jane@example.test',
        'phone' => '+233 24 000 0000',
        'message' => 'I would like to help at the next screening day.',
        'form_token' => EnquiryToken::issue(time() - 10),
        'website' => '',
    ];
}

beforeEach(function (): void {
    Mail::fake();

    $this->tenant = setActiveTenantForTest(null, [
        'slug' => 'client-site',
        'email' => 'tenant-contact@client-site.example',
        'custom_domain' => 'client-site.example',
        'custom_domain_status' => 'active',
    ]);

    TenantModule::query()->create([
        'tenant_id' => $this->tenant->id,
        'module_slug' => 'cms-core',
        'is_enabled' => true,
        'version' => '1.0.0',
    ]);

    Setting::query()->updateOrCreate(
        ['tenant_id' => $this->tenant->id, 'group' => 'theme', 'key' => 'enquiry_email'],
        ['value' => 'owner@client-site.example'],
    );
});

test('the anti-spam token rejects instant posts, expired, forged and malformed tokens', function (): void {
    expect(EnquiryToken::isValid(EnquiryToken::issue()))->toBeFalse()
        ->and(EnquiryToken::isValid(EnquiryToken::issue(time() - 10)))->toBeTrue()
        ->and(EnquiryToken::isValid(EnquiryToken::issue(time() - 8000)))->toBeFalse()
        ->and(EnquiryToken::isValid((time() - 10).'.forged'))->toBeFalse()
        ->and(EnquiryToken::isValid('not-a-token'))->toBeFalse()
        ->and(EnquiryToken::isValid(''))->toBeFalse();
});

test('a valid enquiry is emailed to the site owner with the visitor as reply-to', function (): void {
    $this->postJson(ENQUIRY_URL, enquiryPayload())
        ->assertOk()
        ->assertJson(['sent' => true]);

    Mail::assertSent(SiteEnquiryReceived::class, function (SiteEnquiryReceived $mail): bool {
        return $mail->hasTo('owner@client-site.example')
            && $mail->hasReplyTo('jane@example.test')
            && $mail->envelope()->subject === '[Test Tenant] Volunteer enquiry from Jane Mensah'
            && $mail->enquiryMessage === 'I would like to help at the next screening day.';
    });
    Mail::assertSentCount(1);
});

test('the email shows the visitor details and escapes what they typed', function (): void {
    $html = (new SiteEnquiryReceived(
        EnquiryType::Partnership,
        'Client Site',
        'Jane <b>Mensah</b>',
        'jane@example.test',
        '+233 24 000 0000',
        'Acme Ltd',
        'We would like to sponsor <script>alert(1)</script> your events.',
    ))->render();

    expect($html)->toContain('Partnership enquiry')
        ->toContain('jane@example.test')
        ->toContain('Acme Ltd')
        ->not->toContain('<script>alert(1)</script>')
        ->not->toContain('<b>Mensah</b>');
});

test('the honeypot is answered like a success but nothing is sent', function (): void {
    $this->postJson(ENQUIRY_URL, enquiryPayload(['website' => 'https://spam.example']))
        ->assertOk()
        ->assertJson(['sent' => true]);

    Mail::assertNothingSent();
});

test('instant, forged and expired tokens are refused', function (): void {
    foreach ([EnquiryToken::issue(), (time() - 10).'.forged', EnquiryToken::issue(time() - 9000)] as $token) {
        $this->postJson(ENQUIRY_URL, enquiryPayload(['form_token' => $token]))
            ->assertStatus(422)
            ->assertJson(['sent' => false]);
    }

    Mail::assertNothingSent();
});

test('invalid details return field errors and send nothing', function (): void {
    $this->postJson(ENQUIRY_URL, enquiryPayload(['name' => '', 'email' => 'nope', 'message' => 'short']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'message']);

    $this->postJson(ENQUIRY_URL, enquiryPayload(['type' => 'refund', 'phone' => 'call me maybe']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['type', 'phone']);

    Mail::assertNothingSent();
});

test('without an enquiry address the tenant email is used, and without either the form says so', function (): void {
    Setting::query()->where('key', 'enquiry_email')->delete();

    $this->postJson(ENQUIRY_URL, enquiryPayload())->assertOk();
    Mail::assertSent(SiteEnquiryReceived::class, fn (SiteEnquiryReceived $mail): bool => $mail->hasTo('tenant-contact@client-site.example'));

    $this->tenant->update(['email' => null]);

    $this->postJson(ENQUIRY_URL, enquiryPayload())->assertStatus(503)->assertJson(['sent' => false]);
    Mail::assertSentCount(1);
});

test('when the mail transport fails the visitor is told and the enquiry is logged for recovery', function (): void {
    Log::spy();
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('smtp down'));

    $this->postJson(ENQUIRY_URL, enquiryPayload())
        ->assertStatus(500)
        ->assertJson(['sent' => false])
        ->assertJsonFragment(['message' => 'We could not send your message just now. Please email us at owner@client-site.example instead.']);

    Log::shouldHaveReceived('error')->once()->with(
        'Site enquiry could not be emailed',
        Mockery::on(fn (array $context): bool => $context['enquiry']['email'] === 'jane@example.test' && $context['error'] === 'smtp down'),
    );
});

test('visitors without JavaScript are redirected back to the page with a status flag', function (): void {
    $referer = ['Referer' => 'https://client-site.example/volunteer'];

    $this->post(ENQUIRY_URL, enquiryPayload(), $referer)->assertRedirect('/volunteer?enquiry=sent#enquiry');
    $this->post(ENQUIRY_URL, enquiryPayload(['email' => 'nope']), $referer)->assertRedirect('/volunteer?enquiry=invalid#enquiry');

    // Only the path of the Referer is used, so the redirect cannot leave this site.
    $this->post(ENQUIRY_URL, enquiryPayload(), ['Referer' => 'https://evil.example/phish'])->assertRedirect('/phish?enquiry=sent#enquiry');
});

test('the endpoint is rate limited', function (): void {
    foreach (range(1, 5) as $attempt) {
        $this->postJson(ENQUIRY_URL, enquiryPayload())->assertOk();
    }

    $this->postJson(ENQUIRY_URL, enquiryPayload())->assertStatus(429);
});

test('pages with the enquiry_form meta show the form and others do not', function (): void {
    $tenantId = (string) $this->tenant->id;
    $pageType = PostTypeFactory::new()->forTenant($tenantId)->create(['slug' => 'page']);

    $withForm = PostFactory::new()->published()->forTenant($tenantId)->create(['post_type_id' => $pageType->id, 'slug' => 'volunteer', 'body' => '<p>Join us.</p>']);
    PostFactory::new()->published()->forTenant($tenantId)->create(['post_type_id' => $pageType->id, 'slug' => 'about', 'body' => '<p>About us.</p>']);

    PostMeta::query()->create(['post_id' => $withForm->id, 'tenant_id' => $tenantId, 'key' => 'enquiry_form', 'value' => 'volunteer']);

    $this->get('https://client-site.example/volunteer')
        ->assertOk()
        ->assertSee('id="enquiry"', false)
        ->assertSee('action="https://client-site.example/enquiries"', false)
        ->assertSee('name="form_token"', false)
        ->assertSee('name="website"', false)
        ->assertSee('Tell us how you would like to help');

    $this->get('https://client-site.example/about')
        ->assertOk()
        ->assertDontSee('id="enquiry"', false);
});

test('the public enquiry endpoints are exempt from CSRF because custom domains cannot hold the session cookie', function (): void {
    $except = (new ReflectionProperty(VerifyCsrfToken::class, 'except'))->getValue(new VerifyCsrfToken(app(), app('encrypter')));

    expect($except)->toContain('enquiries')->toContain('site/enquiries');
});
