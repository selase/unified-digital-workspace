<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantEmailDomain;
use App\Modules\CmsCore\Mail\SiteEnquiryReceived;
use App\Services\Email\EmailDnsRecords;
use App\Services\Email\Route53Dns;
use App\Services\Email\SesDomainIdentity;
use App\Services\Email\TenantSender;
use Aws\MockHandler;
use Aws\Result;
use Aws\Route53\Route53Client;
use Aws\SesV2\SesV2Client;
use Illuminate\Support\Facades\Mail;

function emailDomain(array $overrides = []): TenantEmailDomain
{
    $tenant = Tenant::factory()->create(['slug' => 'client-'.fake()->unique()->numerify('###'), 'isolation_mode' => 'shared']);

    return TenantEmailDomain::query()->withoutGlobalScopes()->create($overrides + [
        'tenant_id' => $tenant->id, 'domain' => 'example.org', 'mailbox_provider' => 'google', 'from_address' => 'info@example.org',
        'mail_from_domain' => 'bounce.example.org', 'dkim_tokens' => ['aaa', 'bbb', 'ccc'],
    ]);
}

function awsClient(string $class, array $results): object
{
    return new $class(['region' => 'us-east-1', 'version' => 'latest', 'credentials' => ['key' => 'k', 'secret' => 's'], 'handler' => new MockHandler($results)]);
}

describe('DNS records', function (): void {
    test('Google gets one MX, SPF, SES DKIM, a bounce subdomain and DMARC', function (): void {
        $rows = collect((new EmailDnsRecords)->for(emailDomain(), 'us-east-1'));

        expect($rows->where('type', 'MX')->pluck('value', 'name')->all())->toBe(['example.org' => '1 smtp.google.com', 'bounce.example.org' => '10 feedback-smtp.us-east-1.amazonses.com'])
            ->and($rows->firstWhere(fn (array $row): bool => $row['type'] === 'TXT' && $row['name'] === 'example.org')['value'])->toBe('v=spf1 include:_spf.google.com ~all')
            ->and($rows->where('type', 'CNAME')->pluck('name')->all())->toBe(['aaa._domainkey.example.org', 'bbb._domainkey.example.org', 'ccc._domainkey.example.org'])
            ->and($rows->firstWhere('name', '_dmarc.example.org')['value'])->toBe('v=DMARC1; p=none; rua=mailto:info@example.org')
            ->and($rows->firstWhere('name', 'bounce.example.org' ) !== null)->toBeTrue();
    });

    test('Zoho gets three MX records, other providers none, and the mailbox DKIM is added only when supplied', function (): void {
        $zoho = collect((new EmailDnsRecords)->for(emailDomain(['mailbox_provider' => 'zoho']), 'us-east-1'));
        $other = collect((new EmailDnsRecords)->for(emailDomain(['domain' => 'other.org', 'mailbox_provider' => 'other', 'mail_from_domain' => 'bounce.other.org', 'from_address' => 'info@other.org']), 'us-east-1'));
        $dkim = collect((new EmailDnsRecords)->for(emailDomain(['domain' => 'dk.org', 'mail_from_domain' => 'bounce.dk.org', 'from_address' => 'info@dk.org', 'mailbox_dkim_value' => 'v=DKIM1; k=rsa; p=abc']), 'us-east-1'));

        expect($zoho->where('type', 'MX')->where('name', 'example.org')->count())->toBe(3)
            ->and($other->where('name', 'other.org')->count())->toBe(0)
            ->and($dkim->firstWhere('name', 'google._domainkey.dk.org')['value'])->toBe('v=DKIM1; k=rsa; p=abc');
    });
});

describe('SES identity', function (): void {
    test('registers a domain and returns the DKIM tokens', function (): void {
        $client = awsClient(SesV2Client::class, [new Result(['DkimAttributes' => ['Tokens' => ['t1', 't2', 't3']]]), new Result([])]);

        expect((new SesDomainIdentity($client))->register('example.org', 'bounce.example.org'))->toBe(['t1', 't2', 't3']);
    });

    test('reads the tokens of an identity that already exists', function (): void {
        $exists = new Aws\Exception\AwsException('exists', new Aws\Command('CreateEmailIdentity'), ['code' => 'AlreadyExistsException']);
        $client = awsClient(SesV2Client::class, [$exists, new Result(['DkimAttributes' => ['Tokens' => ['x1']]]), new Result([])]);

        expect((new SesDomainIdentity($client))->register('example.org', 'bounce.example.org'))->toBe(['x1']);
    });

    test('reports verification status', function (): void {
        $client = awsClient(SesV2Client::class, [new Result(['VerifiedForSendingStatus' => true, 'DkimAttributes' => ['Status' => 'SUCCESS'], 'MailFromAttributes' => ['MailFromDomainStatus' => 'SUCCESS']])]);

        expect((new SesDomainIdentity($client))->status('example.org'))->toBe(['verified' => true, 'dkim' => 'SUCCESS', 'mailFrom' => 'SUCCESS']);
    });
});

describe('Route 53 changes', function (): void {
    $dns = new Route53Dns(awsClient(Route53Client::class, []));

    test('a new record is created, an identical one is left alone', function () use ($dns): void {
        expect($dns->decide('MX', null, ['1 smtp.google.com'], false)[0])->toBe('created')
            ->and($dns->decide('MX', ['1 smtp.google.com.'], ['1 smtp.google.com'], false)[0])->toBe('same');
    });

    test('a different MX or CNAME is skipped unless forced', function () use ($dns): void {
        expect($dns->decide('MX', ['10 mail.old.example'], ['1 smtp.google.com'], false)[0])->toBe('skipped')
            ->and($dns->decide('MX', ['10 mail.old.example'], ['1 smtp.google.com'], true)[0])->toBe('replaced');
    });

    test('TXT values already on the name are kept, and a second SPF or DMARC is never added', function () use ($dns): void {
        [$action, $values] = $dns->decide('TXT', ['"google-site-verification=abc"'], ['v=spf1 include:_spf.google.com ~all'], false);

        expect($action)->toBe('merged')->and($values)->toBe(['"google-site-verification=abc"', 'v=spf1 include:_spf.google.com ~all'])
            ->and($dns->decide('TXT', ['"v=spf1 include:other.example ~all"'], ['v=spf1 include:_spf.google.com ~all'], false)[0])->toBe('skipped')
            ->and($dns->decide('TXT', ['"v=DMARC1; p=reject"'], ['v=DMARC1; p=none; rua=mailto:a@b.c'], false)[0])->toBe('skipped');
    });

    test('a dry run changes nothing and a real run upserts quoted, dotted values', function (): void {
        $existsNone = new Result(['ResourceRecordSets' => []]);
        $records = [['type' => 'MX', 'name' => 'example.org', 'value' => '1 smtp.google.com', 'purpose' => ''], ['type' => 'TXT', 'name' => 'example.org', 'value' => 'v=spf1 include:_spf.google.com ~all', 'purpose' => '']];
        $sent = [];
        $capture = function (Aws\CommandInterface $command) use (&$sent): Result {
            $sent[] = $command->toArray();

            return new Result([]);
        };
        $handler = new MockHandler([$existsNone, $capture, $existsNone, $capture]);
        $client = new Route53Client(['region' => 'us-east-1', 'version' => 'latest', 'credentials' => ['key' => 'k', 'secret' => 's'], 'handler' => $handler]);

        $report = (new Route53Dns($client))->apply('Z123', $records, false, false);

        expect(collect($report)->pluck('action')->all())->toBe(['created', 'created'])
            ->and($sent[0]['ChangeBatch']['Changes'][0]['ResourceRecordSet']['ResourceRecords'][0]['Value'])->toBe('1 smtp.google.com.')
            ->and($sent[1]['ChangeBatch']['Changes'][0]['ResourceRecordSet']['ResourceRecords'][0]['Value'])->toBe('"v=spf1 include:_spf.google.com ~all"');

        $dryHandler = new MockHandler([$existsNone, $existsNone]);
        $dry = new Route53Client(['region' => 'us-east-1', 'version' => 'latest', 'credentials' => ['key' => 'k', 'secret' => 's'], 'handler' => $dryHandler]);
        (new Route53Dns($dry))->apply('Z123', $records, false, true);

        expect($dryHandler->count())->toBe(0);
    });
});

describe('Sending as the client domain', function (): void {
    test('a verified domain supplies the from address, an unverified one does not', function (): void {
        $email = emailDomain();
        $tenant = Tenant::query()->findOrFail($email->tenant_id);

        expect((new TenantSender)->for($tenant, 'Client Site'))->toBeNull();

        $email->forceFill(['status' => TenantEmailDomain::VERIFIED])->save();

        expect((new TenantSender)->for($tenant, 'Client Site'))->toBe(['address' => 'info@example.org', 'name' => 'Client Site'])
            ->and((new TenantSender)->for(null, 'x'))->toBeNull();
    });

    test('the setup command registers the domain, prints records and the check command verifies it', function (): void {
        $tenant = Tenant::factory()->create(['slug' => 'cmd-client', 'isolation_mode' => 'shared']);
        app()->instance(SesV2Client::class, awsClient(SesV2Client::class, [new Result(['DkimAttributes' => ['Tokens' => ['a', 'b', 'c']]]), new Result([]), new Result(['VerifiedForSendingStatus' => true, 'DkimAttributes' => ['Status' => 'SUCCESS'], 'MailFromAttributes' => ['MailFromDomainStatus' => 'SUCCESS']])]));

        $this->artisan('email:domain', ['action' => 'setup', 'tenant' => 'cmd-client', 'domain' => 'Cmd.Example.org', '--provider' => 'google'])->expectsOutputToContain('1 smtp.google.com')->assertSuccessful();
        $this->artisan('email:domain', ['action' => 'check', 'tenant' => 'cmd-client'])->expectsOutputToContain('verified')->assertSuccessful();

        $row = TenantEmailDomain::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();

        expect($row->domain)->toBe('cmd.example.org')->and($row->from_address)->toBe('info@cmd.example.org')->and($row->isVerified())->toBeTrue()->and($row->dkim_tokens)->toBe(['a', 'b', 'c']);
    });

    test('setup refuses an address outside the domain and a domain owned by another tenant', function (): void {
        $taken = emailDomain(['domain' => 'taken.org', 'mail_from_domain' => 'bounce.taken.org', 'from_address' => 'info@taken.org']);
        $tenant = Tenant::factory()->create(['slug' => 'other-client', 'isolation_mode' => 'shared']);

        $this->artisan('email:domain', ['action' => 'setup', 'tenant' => 'other-client', 'domain' => 'mine.org', '--from' => 'info@elsewhere.org'])->assertFailed();
        $this->artisan('email:domain', ['action' => 'setup', 'tenant' => 'other-client', 'domain' => $taken->domain])->assertFailed();
    });
});
