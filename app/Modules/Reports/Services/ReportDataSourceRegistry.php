<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Gate;

final class ReportDataSourceRegistry
{
    /**
     * @var array<string, array{
     *     label: string,
     *     table: string,
     *     model: string,
     *     permission: string,
     *     columns: array<string, string>,
     *     date_columns: list<string>
     * }>
     */
    private const SOURCES = [
        'incidents' => [
            'label' => 'Incidents & Safety Reports',
            'table' => 'incidents',
            'model' => \App\Modules\IncidentManagement\Models\Incident::class,
            'permission' => 'incidents.view',
            'columns' => [
                'id' => 'ID',
                'reference_code' => 'Reference Code',
                'title' => 'Title',
                'impact' => 'Impact',
                'reported_via' => 'Reported Via',
                'created_at' => 'Logged At',
            ],
            'date_columns' => ['created_at'],
        ],
        'crm_inquiries' => [
            'label' => 'CRM Leads & Inquiries',
            'table' => 'crm_leads',
            'model' => \App\Modules\Crm\Models\CrmLead::class,
            'permission' => 'crm.view',
            'columns' => [
                'id' => 'ID',
                'full_name' => 'Full Name',
                'email' => 'Email',
                'organisation' => 'Organisation',
                'source' => 'Source',
                'status' => 'Status',
                'created_at' => 'Submitted At',
            ],
            'date_columns' => ['created_at'],
        ],
        'facilities_requests' => [
            'label' => 'Facilities Work Orders',
            'table' => 'facility_work_orders',
            'model' => \App\Modules\FacilitiesManagement\Models\FacilityWorkOrder::class,
            'permission' => 'facilities.view',
            'columns' => [
                'id' => 'ID',
                'work_order_number' => 'Work Order Number',
                'title' => 'Title',
                'category' => 'Category',
                'priority' => 'Priority',
                'status' => 'Status',
                'created_at' => 'Requested At',
            ],
            'date_columns' => ['created_at'],
        ],
        'store_orders' => [
            'label' => 'Store Orders',
            'table' => 'store_orders',
            'model' => \App\Modules\Store\Models\StoreOrder::class,
            'permission' => 'store.orders.view',
            'columns' => [
                'id' => 'ID',
                'order_number' => 'Order Number',
                'customer_name' => 'Customer Name',
                'customer_email' => 'Customer Email',
                'total_amount_cents' => 'Total Amount (Cents)',
                'status' => 'Status',
                'payment_method' => 'Payment Method',
                'created_at' => 'Placed At',
            ],
            'date_columns' => ['created_at'],
        ],
        'real_estate_listings' => [
            'label' => 'Real Estate Land Listings',
            'table' => 'property_listings',
            'model' => \App\Modules\RealEstate\Models\PropertyListing::class,
            'permission' => 'real_estate.view',
            'columns' => [
                'id' => 'ID',
                'title' => 'Title',
                'location' => 'Location',
                'price_pesewas' => 'Price (Pesewas)',
                'tenure_type' => 'Tenure Type',
                'compliance_stage' => 'Compliance Stage',
                'created_at' => 'Listed At',
            ],
            'date_columns' => ['created_at'],
        ],
    ];

    /**
     * @return array<string, array{label: string, columns: array<string, string>, date_columns: list<string>}>
     */
    public function getAvailableSources(?User $user = null): array
    {
        $accessible = [];

        foreach (self::SOURCES as $key => $config) {
            if ($user !== null && ! $this->userCanAccessSource($user, $key)) {
                continue;
            }

            $accessible[$key] = [
                'label' => $config['label'],
                'columns' => $config['columns'],
                'date_columns' => $config['date_columns'],
            ];
        }

        return $accessible;
    }

    public function isValidSource(string $source): bool
    {
        return isset(self::SOURCES[$source]);
    }

    /**
     * @return array{label: string, table: string, model: string, permission: string, columns: array<string, string>, date_columns: list<string>}
     */
    public function getSourceConfig(string $source): array
    {
        if (! $this->isValidSource($source)) {
            throw new DomainException("Data source [{$source}] is not registered in the allowlist.");
        }

        return self::SOURCES[$source];
    }

    public function userCanAccessSource(User $user, string $source): bool
    {
        if (! $this->isValidSource($source)) {
            return false;
        }

        $permission = self::SOURCES[$source]['permission'];

        // If user is superadmin or has the explicit permission or can do everything
        return $user->hasRole('Super Admin') || Gate::forUser($user)->allows($permission);
    }

    /**
     * @param  list<string>  $requestedColumns
     * @return list<string>
     */
    public function filterAllowedColumns(string $source, array $requestedColumns): array
    {
        $config = $this->getSourceConfig($source);
        $allowed = array_keys($config['columns']);

        return array_values(array_intersect($requestedColumns, $allowed));
    }
}
