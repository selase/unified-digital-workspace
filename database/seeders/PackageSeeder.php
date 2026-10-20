<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Package;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

final class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Ensure basic features exist
        // These are standard SaaS limits usually
        $features = [
            'projects-limit' => ['name' => 'Projects Limit', 'type' => 'limit', 'description' => 'Maximum number of projects'],
            'users-limit' => ['name' => 'Users Limit', 'type' => 'limit', 'description' => 'Maximum number of team members'],
            'analytics' => ['name' => 'Analytics', 'type' => 'boolean', 'description' => 'Access to analytics dashboard'],
            'priority-support' => ['name' => 'Priority Support', 'type' => 'boolean', 'description' => 'Manual priority email support service; arranged by the support team'],
            // custom-domains is handled by its own seeder or logic, but we make sure it exists here too if needed recursively
            'custom-domains' => ['name' => 'Custom Domains', 'type' => 'boolean', 'description' => 'Use your own domain'],
            'commerce' => ['name' => 'Commerce / Finance', 'type' => 'boolean', 'description' => 'Collect payments from your own customers'],
        ];

        foreach ($features as $slug => $data) {
            Feature::updateOrCreate(['slug' => $slug], $data);
        }

        // Preserve legacy plan pricing while maintaining their existing grants.
        $plans = [
            ['name' => 'Starter', 'description' => 'Legacy Starter plan.', 'monthly_price' => 10],
            ['name' => 'Pro', 'description' => 'Legacy Pro plan.', 'monthly_price' => 29],
            ['name' => 'Enterprise', 'description' => 'Legacy Enterprise plan.', 'monthly_price' => 99],
        ];

        foreach ($plans as $planData) {
            $slug = Str::slug($planData['name']);

            $package = Package::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $planData['name'],
                    'description' => $planData['description'],
                    'price' => $planData['monthly_price'], // Storing monthly price as base price
                    'interval' => 'month', // Default to monthly
                    'billing_model' => Package::BILLING_MODEL_FLAT_RATE, // Simplified assumption
                    'is_active' => true,
                ]
            );

            $packageFeatures = [];

            if ($slug === 'starter') {
                $packageFeatures['projects-limit'] = ['value' => 3];
                $packageFeatures['users-limit'] = ['value' => 5]; // Implicit limit
                $packageFeatures['analytics'] = ['value' => true]; // Basic
            } elseif ($slug === 'pro') {
                $packageFeatures['projects-limit'] = ['value' => -1]; // Unlimited
                $packageFeatures['users-limit'] = ['value' => 20];
                $packageFeatures['analytics'] = ['value' => true];
                $packageFeatures['priority-support'] = ['value' => true];
            } elseif ($slug === 'enterprise') {
                $packageFeatures['projects-limit'] = ['value' => -1];
                $packageFeatures['users-limit'] = ['value' => -1];
                $packageFeatures['analytics'] = ['value' => true];
                $packageFeatures['priority-support'] = ['value' => true];
                $packageFeatures['custom-domains'] = ['value' => true];
                $packageFeatures['commerce'] = ['value' => true];
            }

            // Sync features
            foreach ($packageFeatures as $featureSlug => $pivotData) {
                $feature = Feature::where('slug', $featureSlug)->first();
                if ($feature) {
                    // Check if already attached to avoid duplicates or use sync without detaching all others if needed
                    // For seeding, attach/sync is fine.
                    if (! $package->features()->where('feature_id', $feature->id)->exists()) {
                        $package->features()->attach($feature->id, $pivotData);
                    } else {
                        // Update existing pivot if needed
                        $package->features()->updateExistingPivot($feature->id, $pivotData);
                    }
                }
            }

            $this->command->info("Seeded Plan: {$planData['name']}");
        }

        $retiredIds = Feature::query()->whereIn('slug', config('packages.retired_features'))->pluck('id');
        foreach (Package::query()->whereIn('slug', array_keys(config('packages.legacy_aliases')))->get() as $legacyPackage) {
            $legacyPackage->features()->detach($retiredIds);
        }

        foreach (config('packages.plans') as $slug => $plan) {
            $package = Package::query()->firstOrCreate(['slug' => $slug], [
                'name' => $plan['name'],
                'description' => $plan['description'],
                'price' => 0,
                'price_ghs' => 0,
                'interval' => 'month',
                'billing_model' => Package::BILLING_MODEL_FLAT_RATE,
                'is_active' => true,
            ]);

            $grants = [];
            foreach ($plan['modules'] as $module) {
                $feature = Feature::query()->firstOrCreate(['slug' => 'module-'.$module], [
                    'name' => ucwords(str_replace('-', ' ', $module)).' Module',
                    'type' => 'boolean',
                    'description' => 'Allows enabling the '.$module.' module; dependencies still apply.',
                ]);
                $grants[$feature->id] = ['value' => '1'];
            }
            foreach ($plan['features'] as $featureSlug) {
                $feature = Feature::query()->where('slug', $featureSlug)->firstOrFail();
                $grants[$feature->id] = ['value' => '1'];
            }

            $managedIds = Feature::query()->where('slug', 'like', 'module-%')
                ->orWhereIn('slug', ['custom-domains', 'commerce', ...config('packages.retired_features')])
                ->pluck('id');
            $package->features()->detach($managedIds->diff(array_keys($grants)));
            $package->features()->syncWithoutDetaching($grants);
            $this->command->info("Seeded module bundle: {$plan['name']}");
        }
    }
}
