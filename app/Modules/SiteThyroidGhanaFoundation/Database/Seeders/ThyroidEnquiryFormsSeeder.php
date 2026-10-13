<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Database\Seeders;

use App\Models\Tenant;
use App\Modules\CmsCore\Models\Post;
use App\Modules\CmsCore\Models\PostMeta;
use App\Modules\CmsCore\Models\Setting;
use App\Modules\CmsCore\Support\EnquiryType;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Database\Seeder;

/**
 * Turns on the email enquiry forms for the Thyroid Ghana Foundation action
 * pages and sets the address the enquiries are sent to. Safe to re-run.
 */
final class ThyroidEnquiryFormsSeeder extends Seeder
{
    public const RECIPIENT = 'info@thyroidghanafoundation.org';

    /**
     * @var array<string, EnquiryType>
     */
    private const PAGES = [
        'volunteer' => EnquiryType::Volunteer,
        'membership' => EnquiryType::Membership,
        'partner-us' => EnquiryType::Partnership,
        'donate' => EnquiryType::Donation,
    ];

    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'thyroid-ghana-foundation')->firstOrFail();

        app(TenantContext::class)->setTenant($tenant);
        app(TenantDatabaseManager::class)->configureShared();
        setPermissionsTeamId($tenant->id);

        Setting::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'group' => 'theme', 'key' => 'enquiry_email'],
            ['value' => self::RECIPIENT],
        );

        foreach (self::PAGES as $slug => $type) {
            $page = Post::query()->where('slug', $slug)->first();

            if ($page === null) {
                continue;
            }

            PostMeta::query()->updateOrCreate(
                ['post_id' => $page->id, 'key' => 'enquiry_form'],
                ['value' => $type->value, 'tenant_id' => $tenant->id],
            );
        }
    }
}
