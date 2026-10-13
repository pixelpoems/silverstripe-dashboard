<?php

namespace Pixelpoems\Dashboard\Panel\SilvershopPanels;

use Pixelpoems\Dashboard\Panel\KpiPanel;
use SilverShop\Admin\OrdersAdmin;
use SilverStripe\ORM\FieldType\DBField;

/**
 * Revenue (gross order totals) within the selected date range, without carts and cancellations,
 * compared with the previous period of the same length.
 * Only meaningful per locale (filter_locale: true) - locales may use different currencies, the currency symbol is
 * taken from the config of the current CMS locale.
 */
class ShopOrderRevenuePanel extends KpiPanel
{
    use ShopOrderPanelTrait;

    private static string $title = 'Revenue';

    private static string $date_field = 'Placed';

    private static int $cache_lifetime = 300;

    private static bool $filter_locale = true;

    private static array $required_permission_codes = ['CMS_ACCESS_' . OrdersAdmin::class];

    public function getData(): array
    {
        $current = (float) $this->filterByDateRange($this->getValidOrders())->sum('Total');
        $previous = (float) $this->filterByDateRange(
            $this->getValidOrders(),
            null,
            $this->getDateRange()->getPreviousRange()
        )->sum('Total');

        return [
            'Value' => DBField::create_field('Currency', $current)->Nice(),
            'Compare' => $this->formatTrend($current, $previous),
        ];
    }
}
