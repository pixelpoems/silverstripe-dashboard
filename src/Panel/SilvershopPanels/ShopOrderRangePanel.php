<?php

namespace Pixelpoems\Dashboard\Panel\SilvershopPanels;

use Pixelpoems\Dashboard\Panel\KpiPanel;
use SilverShop\Admin\OrdersAdmin;

/**
 * Orders within the selected date range (without cancellations), split by customers / resellers
 * and compared with the previous period of the same length
 */
class ShopOrderRangePanel extends KpiPanel
{
    use ShopOrderPanelTrait;

    private static string $title = 'Orders';

    private static string $date_field = 'Placed';

    private static int $cache_lifetime = 300;

    private static bool $filter_locale = true;

    private static array $required_permission_codes = ['CMS_ACCESS_' . OrdersAdmin::class];

    public function getData(): array
    {
        $orders = $this->filterByDateRange($this->getValidOrders());
        $previous = $this->filterByDateRange(
            $this->getValidOrders(),
            null,
            $this->getDateRange()->getPreviousRange()
        );

        $count = $orders->count();

        return [
            'Value' => $count,
            'Compare' => $this->formatTrend($count, $previous->count()),
        ];
    }
}
