<?php

namespace Pixelpoems\Dashboard\Panel\SilvershopPanels;

use Pixelpoems\Dashboard\Panel\TablePanel;
use SilverShop\Admin\OrdersAdmin;
use SilverShop\Model\Order;

/**
 * Latest orders within the selected date range
 */
class ShopOrderLatestPanel extends TablePanel
{
    use ShopOrderPanelTrait;

    private static string $title = 'Latest orders';

    private static int $width = 12;

    private static string $date_field = 'Placed';

    private static int $cache_lifetime = 60;

    private static bool $filter_locale = true;

    private static array $required_permission_codes = ['CMS_ACCESS_' . OrdersAdmin::class];

    /**
     * Max. number of orders shown
     */
    private static int $limit = 15;

    public function getData(): array
    {
        $allOrders = $this->filterByDateRange($this->getPlacedOrders());
        $orders = $allOrders
            ->sort('Placed', 'DESC')
            ->limit((int) $this->getOption('limit'));

        $rows = [];
        /** @var Order $order */
        foreach ($orders as $order) {
            $rows[] = [
                [
                    'Value' => '#' . $order->ID,
                    'Link' => $this->getOrderEditLink($order),
                ],
                $order->dbObject('Placed')->Nice(),
                $order->FirstName . ' ' . $order->Surname,
                $order->Locale,
                $order->Status,
                $order->dbObject('Total')->Nice(),
            ];
        }

        return [
            'Columns' => [
                _t(self::class . '.ORDER_NR', 'Order no.'),
                _t(self::class . '.DATE', 'Date'),
                _t(self::class . '.ACCOUNT', 'Account'),
                _t(self::class . '.LOCALE', 'Locale'),
                _t(self::class . '.STATE', 'State'),
                _t(self::class . '.VALUE', 'Value'),
            ],
            'Rows' => $rows,
            'Total' => $allOrders->count(),
            'MoreLink' => $this->getOrderAdmin()->Link(),
            'MoreTitle' => _t(self::class . '.SHOW_ALL', 'All orders in the order manager'),
        ];
    }
}
