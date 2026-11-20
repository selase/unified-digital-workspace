<?php

declare(strict_types=1);

namespace App\Services\Email;

use Aws\Exception\AwsException;
use Aws\SesV2\SesV2Client;

/** Registers a client domain with SES (DKIM plus a custom MAIL FROM subdomain) and reports whether SES has verified it. */
final class SesDomainIdentity
{
    public function __construct(private readonly SesV2Client $client) {}

    /**
     * Creates the identity if it does not exist yet and returns its DKIM tokens.
     *
     * @return list<string>
     */
    public function register(string $domain, string $mailFromDomain): array
    {
        try {
            $created = $this->client->createEmailIdentity([
                'EmailIdentity' => $domain,
                'DkimSigningAttributes' => ['NextSigningKeyLength' => 'RSA_2048_BIT'],
            ]);
            $tokens = $created['DkimAttributes']['Tokens'] ?? [];
        } catch (AwsException $exception) {
            if ($exception->getAwsErrorCode() !== 'AlreadyExistsException') {
                throw $exception;
            }

            $tokens = $this->client->getEmailIdentity(['EmailIdentity' => $domain])['DkimAttributes']['Tokens'] ?? [];
        }

        $this->client->putEmailIdentityMailFromAttributes([
            'EmailIdentity' => $domain,
            'MailFromDomain' => $mailFromDomain,
            'BehaviorOnMxFailure' => 'USE_DEFAULT_VALUE',
        ]);

        return array_values(array_map('strval', $tokens));
    }

    /**
     * @return array{verified: bool, dkim: string, mailFrom: string}
     */
    public function status(string $domain): array
    {
        $identity = $this->client->getEmailIdentity(['EmailIdentity' => $domain]);

        return [
            'verified' => (bool) ($identity['VerifiedForSendingStatus'] ?? false),
            'dkim' => (string) ($identity['DkimAttributes']['Status'] ?? 'NOT_STARTED'),
            'mailFrom' => (string) ($identity['MailFromAttributes']['MailFromDomainStatus'] ?? 'NOT_STARTED'),
        ];
    }
}
