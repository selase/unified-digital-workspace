<?php

declare(strict_types=1);

namespace App\Services\Alerts;

use App\Models\AlertDelivery;
use App\Models\AlertRule;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AlertNotification;
use App\Services\Tenancy\TenantContext;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

final class AlertDispatcher
{
    /**
     * Notifies everyone the tenant's active rules for this event name, once per
     * occurrence. Returns the number of recipients notified, so callers with a
     * built-in fallback notification know whether a rule handled it.
     *
     * @param  string  $dedupKey  identifies this occurrence, e.g. "invoice-123"; the same key is never sent twice to a rule's recipient
     */
    public function dispatch(Tenant $tenant, string $event, string $title, string $body, string $dedupKey, ?string $url = null): int
    {
        $rules = AlertRule::query()->where('tenant_id', $tenant->id)->where('event', $event)->where('is_active', true)->get();
        $notified = 0;
        app(TenantContext::class)->setTenant($tenant);

        foreach ($rules as $rule) {
            $channels = $this->channelsFor($tenant, $rule);
            if ($channels === []) {
                continue;
            }

            foreach ($this->recipients($tenant, $rule) as $key => $recipient) {
                $deliverable = $recipient instanceof User ? $channels : array_values(array_diff($channels, ['database']));
                if ($deliverable === [] || ! $this->claim($tenant, $rule, $event, $dedupKey, $key, $deliverable)) {
                    continue;
                }

                try {
                    Notification::send($recipient, new AlertNotification($event, $title, $body, $deliverable, $url));
                    $notified++;
                } catch (Throwable $error) {
                    Log::warning('Alert could not be sent', ['tenant' => $tenant->id, 'event' => $event, 'error' => $error->getMessage()]);
                }
            }
        }

        return $notified;
    }

    /**
     * Same as dispatch(), for call sites inside other features: an alert problem must
     * never break the action that raised it.
     */
    public function dispatchSafely(Tenant $tenant, string $event, string $title, string $body, string $dedupKey, ?string $url = null): int
    {
        try {
            return $this->dispatch($tenant, $event, $title, $body, $dedupKey, $url);
        } catch (Throwable $error) {
            report($error);

            return 0;
        }
    }

    /**
     * The rule's channels that the organisation has switched on.
     *
     * @return list<string>
     */
    public function channelsFor(Tenant $tenant, AlertRule $rule): array
    {
        $enabled = $this->enabledChannels($tenant);

        return array_values(array_filter($rule->channels, fn (string $channel): bool => in_array($channel, AlertEvent::channels(), true) && ($enabled[$channel] ?? false)));
    }

    /** @return array<string, bool> */
    public function enabledChannels(Tenant $tenant): array
    {
        return ['mail' => true, 'database' => true, 'sms' => false, ...(array) ($tenant->meta['alert_channels'] ?? [])];
    }

    /** @return array<string, User|AnonymousNotifiable> keyed by a stable recipient identifier */
    private function recipients(Tenant $tenant, AlertRule $rule): array
    {
        $found = [];
        $users = $tenant->users()->get();
        $admins = $users->filter(fn (User $user): bool => $user->can('core.settings.manage') || $user->can('core.billing.manage'));

        foreach ($admins->isEmpty() ? $users->take(3) : $admins as $user) {
            $found['user:'.$user->id] = $user;
        }
        foreach ((array) ($rule->recipients['emails'] ?? []) as $email) {
            $found['mail:'.mb_strtolower($email)] = Notification::route('mail', $email);
        }
        foreach ((array) ($rule->recipients['phones'] ?? []) as $phone) {
            $found['sms:'.$phone] = Notification::route('sms', $phone);
        }

        return $found;
    }

    /** @param list<string> $channels */
    private function claim(Tenant $tenant, AlertRule $rule, string $event, string $dedupKey, string $recipient, array $channels): bool
    {
        return AlertDelivery::query()->firstOrCreate(
            ['alert_rule_id' => $rule->id, 'dedup_key' => $dedupKey, 'recipient' => $recipient],
            ['tenant_id' => $tenant->id, 'event' => $event, 'channels' => $channels],
        )->wasRecentlyCreated;
    }
}
