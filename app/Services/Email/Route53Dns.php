<?php

declare(strict_types=1);

namespace App\Services\Email;

use Aws\Route53\Route53Client;

/**
 * Applies DNS records to a Route 53 hosted zone the platform controls. It is deliberately cautious:
 * a record that already exists with a different value is skipped unless forced, and extra TXT values
 * (for example site-verification tokens) are kept when a TXT record is added next to them.
 */
final class Route53Dns
{
    private const TTL = 300;

    public function __construct(private readonly Route53Client $client) {}

    public function zoneIdFor(string $domain): ?string
    {
        $result = $this->client->listHostedZonesByName(['DNSName' => $domain, 'MaxItems' => '1']);
        $zone = $result['HostedZones'][0] ?? null;

        if ($zone === null || mb_rtrim((string) $zone['Name'], '.') !== mb_strtolower($domain) || ($zone['Config']['PrivateZone'] ?? false)) {
            return null;
        }

        return (string) $zone['Id'];
    }

    /**
     * @param  list<array{type: string, name: string, value: string, purpose: string}>  $records
     * @return list<array{type: string, name: string, action: string, reason: string}>
     */
    public function apply(string $zoneId, array $records, bool $force, bool $dryRun): array
    {
        $report = [];

        foreach ($this->grouped($records) as ['type' => $type, 'name' => $name, 'values' => $wanted]) {
            [$action, $values, $reason] = $this->decide($type, $this->existing($zoneId, $type, $name), $wanted, $force);

            if ($action !== 'skipped' && $action !== 'same' && ! $dryRun) {
                $this->upsert($zoneId, $type, $name, $values);
            }

            $report[] = ['type' => $type, 'name' => $name, 'action' => $action, 'reason' => $reason];
        }

        return $report;
    }

    /**
     * @param  list<string>|null  $existing  stored values, or null when there is no record
     * @param  list<string>  $wanted
     * @return array{0: string, 1: list<string>, 2: string}
     */
    public function decide(string $type, ?array $existing, array $wanted, bool $force): array
    {
        if ($existing === null) {
            return ['created', $wanted, 'new record'];
        }

        $have = array_map($this->normalise(...), $existing);
        $need = array_map($this->normalise(...), $wanted);

        if (array_diff($need, $have) === []) {
            return ['same', $existing, 'already present'];
        }

        if ($type === 'TXT') {
            $conflict = collect($need)->contains(fn (string $value): bool => str_starts_with($value, 'v=spf1') && collect($have)->contains(fn (string $other): bool => str_starts_with($other, 'v=spf1')))
                || collect($need)->contains(fn (string $value): bool => str_starts_with($value, 'v=DMARC1') && collect($have)->contains(fn (string $other): bool => str_starts_with($other, 'v=DMARC1')));

            if (! $conflict) {
                return ['merged', array_values(array_merge($existing, $wanted)), 'kept the existing TXT values'];
            }
        }

        return $force ? ['replaced', $wanted, 'replaced a different value'] : ['skipped', $existing, 'a different value already exists (use force to replace it)'];
    }

    /**
     * @param  list<array{type: string, name: string, value: string, purpose: string}>  $records
     * @return list<array{type: string, name: string, values: list<string>}>
     */
    private function grouped(array $records): array
    {
        $groups = [];

        foreach ($records as $record) {
            $key = $record['type'].'|'.mb_strtolower($record['name']);
            $groups[$key] ??= ['type' => $record['type'], 'name' => mb_strtolower($record['name']), 'values' => []];
            $groups[$key]['values'][] = $record['value'];
        }

        return array_values($groups);
    }

    /**
     * @return list<string>|null
     */
    private function existing(string $zoneId, string $type, string $name): ?array
    {
        $result = $this->client->listResourceRecordSets(['HostedZoneId' => $zoneId, 'StartRecordName' => $name, 'StartRecordType' => $type, 'MaxItems' => '1']);
        $set = $result['ResourceRecordSets'][0] ?? null;

        if ($set === null || $set['Type'] !== $type || mb_rtrim(mb_strtolower((string) $set['Name']), '.') !== $name) {
            return null;
        }

        return array_values(array_map(fn (array $record): string => (string) $record['Value'], $set['ResourceRecords'] ?? []));
    }

    /**
     * @param  list<string>  $values
     */
    private function upsert(string $zoneId, string $type, string $name, array $values): void
    {
        $this->client->changeResourceRecordSets([
            'HostedZoneId' => $zoneId,
            'ChangeBatch' => ['Changes' => [[
                'Action' => 'UPSERT',
                'ResourceRecordSet' => [
                    'Name' => $name,
                    'Type' => $type,
                    'TTL' => self::TTL,
                    'ResourceRecords' => array_map(fn (string $value): array => ['Value' => $this->format($type, $value)], $values),
                ],
            ]]],
        ]);
    }

    private function format(string $type, string $value): string
    {
        if ($type === 'TXT') {
            return str_starts_with($value, '"') ? $value : '"'.implode('" "', mb_str_split($value, 255)).'"';
        }

        $host = mb_strrchr($value, ' ');
        $prefix = $host === false ? '' : mb_substr($value, 0, mb_strlen($value) - mb_strlen($host) + 1);
        $target = $host === false ? $value : mb_ltrim($host);

        return $prefix.(str_ends_with($target, '.') ? $target : $target.'.');
    }

    private function normalise(string $value): string
    {
        return mb_rtrim(mb_trim((string) preg_replace('/"\s+"/', '', $value), '"'), '.');
    }
}
