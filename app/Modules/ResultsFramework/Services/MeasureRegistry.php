<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Services;

use App\Models\Tenant;
use App\Modules\ResultsFramework\Contracts\ProvidesMeasureFilterOptions;
use App\Modules\ResultsFramework\Contracts\ResultMeasureProvider;
use App\Modules\ResultsFramework\Support\MeasureDefinition;
use App\Modules\ResultsFramework\Support\MeasureQuery;
use App\Modules\ResultsFramework\Support\MeasureUnavailable;
use App\Services\ModuleManager;
use DomainException;
use Illuminate\Container\Attributes\Tag;
use LogicException;

final class MeasureRegistry
{
    /** @var array<string, array{MeasureDefinition, ResultMeasureProvider}> */
    private array $measures = [];

    /** @param iterable<ResultMeasureProvider> $providers */
    public function __construct(
        #[Tag('results.measure-providers')] iterable $providers,
        private ModuleManager $modules,
    ) {
        foreach ($providers as $provider) {
            foreach ($provider->measures() as $definition) {
                if (isset($this->measures[$definition->key])) {
                    throw new LogicException('Duplicate result measure: '.$definition->key);
                }
                $this->measures[$definition->key] = [$definition, $provider];
            }
        }
    }

    /** @return array<string, MeasureDefinition> */
    public function definitions(Tenant $tenant): array
    {
        $definitions = [];
        $enabled = [];
        foreach ($this->measures as $key => [$definition, $provider]) {
            $enabled[$definition->module] ??= $this->modules->isEnabledForTenant($definition->module, $tenant);
            if ($enabled[$definition->module]) {
                $definitions[$key] = clone $definition;
                $definitions[$key]->filters = $this->filterOptions($tenant, $definition, $provider);
            }
        }

        return $definitions;
    }

    public function label(string $key): string
    {
        return isset($this->measures[$key]) ? $this->measures[$key][0]->label : 'Unavailable measure';
    }

    /** @param array<string, int|string> $filters */
    public function validateSelection(Tenant $tenant, string $key, array $filters, bool $allowDisabled = false): MeasureDefinition
    {
        [$definition, $provider] = $this->measures[$key] ?? throw new MeasureUnavailable('Choose where the figure comes from.');
        if (! $allowDisabled && ! $this->modules->isEnabledForTenant($definition->module, $tenant)) {
            throw new MeasureUnavailable('The module for this measure is unavailable.');
        }

        if ($filters !== []) {
            $options = $this->filterOptions($tenant, $definition, $provider);
            foreach ($filters as $name => $value) {
                if ((! is_string($value) && ! is_int($value)) || ! array_key_exists($value, $options[$name] ?? [])) {
                    throw new MeasureUnavailable('Choose an available option for each measure filter.');
                }
            }
        }

        return $definition;
    }

    public function value(Tenant $tenant, string $key, MeasureQuery $query): float
    {
        $this->validateSelection($tenant, $key, $query->filters);
        if ($query->from->toDateString() > $query->to->toDateString()) {
            return 0.0;
        }

        return $this->measures[$key][1]->value((string) $tenant->id, $key, $query);
    }

    /** @return array<string, float> */
    public function breakdown(Tenant $tenant, string $key, string $dimension, MeasureQuery $query): array
    {
        $definition = $this->validateSelection($tenant, $key, $query->filters);
        if (! in_array($dimension, $definition->dimensions, true)) {
            throw new DomainException('This measure does not offer that breakdown.');
        }

        return $this->measures[$key][1]->breakdown((string) $tenant->id, $key, $dimension, $query);
    }

    /** @return array<string, array<int|string, string>> */
    private function filterOptions(Tenant $tenant, MeasureDefinition $definition, ResultMeasureProvider $provider): array
    {
        return $provider instanceof ProvidesMeasureFilterOptions
            ? array_replace($definition->filters, $provider->filterOptions((string) $tenant->id, $definition->key))
            : $definition->filters;
    }
}
