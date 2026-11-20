<?php

declare(strict_types=1);

namespace App\Services\Email;

use App\Models\TenantEmailDomain;

/**
 * Builds the DNS records a client domain needs: the mailbox provider's records, SES sending records
 * (DKIM and a custom MAIL FROM subdomain) and a DMARC policy. Pure, so it is safe to show to a client
 * as a copy-and-paste table or to hand to the Route 53 applier.
 */
final class EmailDnsRecords
{
    public const PROVIDERS = ['google', 'zoho', 'other'];

    /**
     * @return list<array{type: string, name: string, value: string, purpose: string}>
     */
    public function for(TenantEmailDomain $email, string $sesRegion): array
    {
        $domain = $email->domain;
        $records = [];

        foreach ($this->mailboxMx($email->mailbox_provider) as $mx) {
            $records[] = ['type' => 'MX', 'name' => $domain, 'value' => $mx, 'purpose' => 'Delivers incoming mail to the mailbox provider'];
        }

        $spf = $this->mailboxSpf($email->mailbox_provider);
        if ($spf !== null) {
            $records[] = ['type' => 'TXT', 'name' => $domain, 'value' => $spf, 'purpose' => 'Allows the mailbox provider to send as the domain'];
        }

        if (filled($email->mailbox_dkim_value)) {
            $records[] = ['type' => 'TXT', 'name' => 'google._domainkey.'.$domain, 'value' => (string) $email->mailbox_dkim_value, 'purpose' => 'Signs mail from the mailbox provider'];
        }

        foreach ($email->dkim_tokens ?? [] as $token) {
            $records[] = ['type' => 'CNAME', 'name' => "{$token}._domainkey.{$domain}", 'value' => "{$token}.dkim.amazonses.com", 'purpose' => 'Lets the platform send as the domain (SES DKIM)'];
        }

        $records[] = ['type' => 'MX', 'name' => $email->mail_from_domain, 'value' => "10 feedback-smtp.{$sesRegion}.amazonses.com", 'purpose' => 'Receives bounces for platform mail'];
        $records[] = ['type' => 'TXT', 'name' => $email->mail_from_domain, 'value' => 'v=spf1 include:amazonses.com ~all', 'purpose' => 'Allows SES to send for the bounce subdomain'];
        $records[] = ['type' => 'TXT', 'name' => '_dmarc.'.$domain, 'value' => 'v=DMARC1; p=none; rua=mailto:'.$email->from_address, 'purpose' => 'Reports on mail sent as the domain'];

        return $records;
    }

    /**
     * @return list<string>
     */
    private function mailboxMx(string $provider): array
    {
        return match ($provider) {
            'google' => ['1 smtp.google.com'],
            'zoho' => ['10 mx.zoho.com', '20 mx2.zoho.com', '50 mx3.zoho.com'],
            default => [],
        };
    }

    private function mailboxSpf(string $provider): ?string
    {
        return match ($provider) {
            'google' => 'v=spf1 include:_spf.google.com ~all',
            'zoho' => 'v=spf1 include:zoho.com ~all',
            default => null,
        };
    }
}
