<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Services;

use App\Models\Tenant;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ResultsFramework\Models\ResultNode;
use App\Modules\ResultsFramework\Models\ResultProgramme;
use App\Services\ModuleManager;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

final class ResultChainService
{
    public function add(ResultProgramme $programme, string $level, string $title, ?ResultNode $parent = null, array $details = []): ResultNode
    {
        $title = mb_trim($title);
        $position = (int) ($details['sort_order'] ?? 0);
        if ($title === '' || mb_strlen($title) > 255) {
            throw new DomainException('Enter a result title of up to 255 characters.');
        }

        $this->validatePosition($programme->tenant_id, $programme->id, $level, $parent, $position);
        $projectId = $details['project_id'] ?? null;
        if ($projectId !== null && ($level !== 'activity' || ! $this->projects($programme)->contains('id', (int) $projectId))) {
            throw new DomainException('Choose an enabled project in your organisation for an activity.');
        }

        return ResultNode::query()->create(Arr::only($details, ['description', 'assumptions', 'risks']) + [
            'tenant_id' => $programme->tenant_id,
            'programme_id' => $programme->id,
            'parent_id' => $parent?->id,
            'level' => $level,
            'title' => $title,
            'project_id' => $projectId,
            'sort_order' => $position,
        ]);
    }

    public function move(ResultNode $node, ?ResultNode $newParent, int $position): void
    {
        $this->validatePosition($node->tenant_id, $node->programme_id, $node->level, $newParent, $position);
        $node->update(['parent_id' => $newParent?->id, 'sort_order' => $position]);
    }

    public function remove(ResultNode $node): void
    {
        if ($node->children()->where('tenant_id', $node->tenant_id)->exists()) {
            throw new DomainException('Move or remove the children before deleting this result.');
        }

        if ($node->indicators()->where('tenant_id', $node->tenant_id)->exists()) {
            throw new DomainException('Detach the indicators before deleting this result.');
        }

        $node->delete();
    }

    /** @return Collection<int, ResultNode> */
    public function tree(ResultProgramme $programme): Collection
    {
        $nodes = $this->nodes($programme)->get();
        $groups = $nodes->groupBy('parent_id');
        $projects = $nodes->whereNotNull('project_id')->isEmpty() ? new Collection : $this->projects($programme)->keyBy('id');

        foreach ($nodes as $node) {
            $node->setRelation('children', $groups->get($node->id, new Collection));
            $node->setRelation('linkedProject', $projects->get($node->project_id));
        }

        return new Collection($groups->get('', new Collection)->all());
    }

    /** @return Builder<ResultNode> */
    public function nodes(ResultProgramme $programme): Builder
    {
        return ResultNode::query()->where('tenant_id', $programme->tenant_id)->where('programme_id', $programme->id)->orderBy('sort_order')->orderBy('id');
    }

    /** @return Collection<int, Project> */
    public function projects(ResultProgramme $programme): Collection
    {
        $tenant = Tenant::query()->findOrFail($programme->tenant_id);
        if (! app(ModuleManager::class)->isEnabledForTenant('project-management', $tenant)) {
            return new Collection;
        }

        return Project::query()->where('tenant_id', $tenant->id)->orderBy('name')->get(['id', 'name', 'status']);
    }

    private function validatePosition(string $tenantId, int $programmeId, string $level, ?ResultNode $parent, int $position): void
    {
        if (! array_key_exists($level, ResultNode::LEVELS) || $position < 0) {
            throw new DomainException('Choose a valid result level and position.');
        }

        if ($parent && ($parent->tenant_id !== $tenantId || $parent->programme_id !== $programmeId)) {
            throw new DomainException('The parent must belong to the same programme and organisation.');
        }

        if ($parent && ResultNode::LEVELS[$parent->level] >= ResultNode::LEVELS[$level]) {
            throw new DomainException('Choose a parent at a higher level; a result cannot move under its own branch.');
        }
    }
}
