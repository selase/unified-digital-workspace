<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantEmailDomain;
use App\Services\Email\EmailDnsRecords;
use App\Services\Email\Route53Dns;
use App\Services\Email\SesDomainIdentity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/** Sets up, shows, checks and applies a client's email domain. See docs/superpowers/plans/2026-11-20-client-email-and-site-cleanup.md. */
final class EmailDomainCommand extends Command
{
    protected $signature = 'email:domain {action : setup, records, check or apply}
        {tenant : tenant slug}
        {domain? : the client domain (setup only)}
        {--provider=other : mailbox provider: google, zoho or other (setup only)}
        {--from= : address site mail is sent from, default info@domain (setup only)}
        {--mailbox-dkim= : the mailbox provider DKIM TXT value, from the client admin (setup only)}
        {--apply : actually change DNS (apply only; otherwise a dry run)}
        {--force : replace records that already exist with a different value (apply only)}';

    protected $description = 'Set up a client email domain: SES identity, DNS records, verification status';

    private function setup(Tenant $tenant, SesDomainIdentity $ses, EmailDnsRecords $records): int
    {
        $domain = mb_strtolower((string) $this->argument('domain'));
        $provider = (string) $this->option('provider');
        $from = (string) ($this->option('from') ?: "info@{$domain}");

        $validator = Validator::make(
            ['domain' => $domain, 'provider' => $provider, 'from' => $from],
            ['domain' => ['required', 'regex:/^(?!-)[a-z0-9-]+(\.[a-z0-9-]+)+$/'], 'provider' => ['in:'.implode(',', EmailDnsRecords::PROVIDERS)], 'from' => ['email']],
        );
        if ($validator->fails() || ! str_ends_with($from, '@'.$domain)) {
            $this->error('Invalid domain, provider or from address (the address must be on the domain).');

            return self::FAILURE;
        }

        $taken = TenantEmailDomain::query()->withoutGlobalScopes()->where('domain', $domain)->where('tenant_id', '!=', $tenant->id)->exists();
        if ($taken) {
            $this->error('That domain already belongs to another tenant.');

            return self::FAILURE;
        }

        $mailFrom = 'bounce.'.$domain;
        $email = TenantEmailDomain::query()->withoutGlobalScopes()->updateOrCreate(
            ['domain' => $domain],
            ['tenant_id' => $tenant->id, 'mailbox_provider' => $provider, 'from_address' => $from, 'mail_from_domain' => $mailFrom, 'mailbox_dkim_value' => $this->option('mailbox-dkim') ?: null, 'dkim_tokens' => $ses->register($domain, $mailFrom)],
        );
        $this->info("Registered {$domain} with SES. Add these DNS records, then run: email:domain check {$tenant->slug}");

        return $this->showRecords($records->for($email, $this->region()));
    }

    public function handle(SesDomainIdentity $ses, EmailDnsRecords $records, Route53Dns $dns): int
    {
        $tenant = Tenant::query()->where('slug', $this->argument('tenant'))->first();
        if ($tenant === null) {
            $this->error('Unknown tenant.');

            return self::FAILURE;
        }

        return match ($this->argument('action')) {
            'setup' => $this->setup($tenant, $ses, $records),
            'records' => $this->withDomain($tenant, fn (TenantEmailDomain $email): int => $this->showRecords($records->for($email, $this->region()))),
            'check' => $this->withDomain($tenant, fn (TenantEmailDomain $email): int => $this->check($email, $ses)),
            'apply' => $this->withDomain($tenant, fn (TenantEmailDomain $email): int => $this->applyDns($email, $records, $dns)),
            default => $this->usage(),
        };
    }

    /**
     * @param  callable(TenantEmailDomain): int  $then
     */
    private function withDomain(Tenant $tenant, callable $then): int
    {
        $email = TenantEmailDomain::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('created_at')->first();
        if ($email === null) {
            $this->error('No email domain set up for this tenant. Run setup first.');

            return self::FAILURE;
        }

        return $then($email);
    }

    /**
     * @param  list<array{type: string, name: string, value: string, purpose: string}>  $rows
     */
    private function showRecords(array $rows): int
    {
        $this->table(['Type', 'Name', 'Value', 'Why'], array_map(fn (array $row): array => [$row['type'], $row['name'], $row['value'], $row['purpose']], $rows));

        return self::SUCCESS;
    }

    private function check(TenantEmailDomain $email, SesDomainIdentity $ses): int
    {
        $status = $ses->status($email->domain);
        $email->forceFill([
            'status' => $status['verified'] ? TenantEmailDomain::VERIFIED : ($status['dkim'] === 'FAILED' ? TenantEmailDomain::FAILED : TenantEmailDomain::PENDING),
            'verified_at' => $status['verified'] ? ($email->verified_at ?? now()) : null,
            'last_checked_at' => now(),
        ])->save();
        $this->line("{$email->domain}: {$email->status} (DKIM {$status['dkim']}, MAIL FROM {$status['mailFrom']})");

        return self::SUCCESS;
    }

    private function applyDns(TenantEmailDomain $email, EmailDnsRecords $records, Route53Dns $dns): int
    {
        $zone = $dns->zoneIdFor($email->domain);
        if ($zone === null) {
            $this->error('No public Route 53 hosted zone for this domain in this AWS account. Add the records at the DNS host instead (email:domain records).');

            return self::FAILURE;
        }

        $dryRun = ! $this->option('apply');
        $report = $dns->apply($zone, $records->for($email, $this->region()), (bool) $this->option('force'), $dryRun);
        $this->table(['Type', 'Name', 'Result', 'Why'], array_map(fn (array $row): array => [$row['type'], $row['name'], $row['action'], $row['reason']], $report));
        $this->line($dryRun ? 'Dry run only. Nothing was changed. Add --apply to change DNS.' : 'DNS updated.');

        return self::SUCCESS;
    }

    private function usage(): int
    {
        $this->error('Action must be setup, records, check or apply.');

        return self::FAILURE;
    }

    private function region(): string
    {
        return (string) config('services.ses.region');
    }
}
