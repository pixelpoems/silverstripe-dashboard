<?php

namespace Pixelpoems\Dashboard\Panel\SilvershopPanels;

use Pixelpoems\Dashboard\Panel\LineChartPanel;
use SilverShop\Admin\OrdersAdmin;
use TractorCow\Fluent\Model\Locale;

/**
 * Orders per day (per week / month for long date ranges) within the selected date range, without cancellations.
 * Shows the orders of the current locale and / or of all countries, see the "series" config.
 */
class ShopOrderChartPanel extends LineChartPanel
{
    use ShopOrderPanelTrait;

    private static string $title = 'Orders over time';

    private static string $date_field = 'Placed';

    private static int $cache_lifetime = 300;

    private static array $required_permission_codes = ['CMS_ACCESS_' . OrdersAdmin::class];

    /**
     * Series shown in the chart, in this order: "locale" (orders of the current CMS locale) and / or "all" (all countries)
     */
    private static array $series = ['locale', 'all'];

    /**
     * Cache per locale as soon as a locale series is shown
     */
    protected function filtersByLocale(): bool
    {
        return in_array('locale', $this->getSeriesTypes(), true);
    }

    protected function getSeriesTypes(): array
    {
        return array_values((array) $this->getOption('series'));
    }

    public function getData(): array
    {
        $series = [];
        foreach ($this->getSeriesTypes() as $type) {
            $series[] = match ($type) {
                'locale' => [
                    'Title' => _t(self::class . '.SERIES_LOCALE', 'Orders ({locale})', [
                        'locale' => (class_exists(Locale::class) ? Locale::getCurrentLocale()?->getTitle() : null)
                            ?? $this->getCurrentLocaleCode(),
                    ]),
                    'Points' => $this->countByInterval($this->getValidOrders(true)),
                ],
                'all' => [
                    'Title' => _t(self::class . '.SERIES_ALL', 'Orders (all countries)'),
                    'Points' => $this->countByInterval($this->getValidOrders(false)),
                ],
                default => null,
            };
        }

        return ['Series' => array_values(array_filter($series))];
    }
}
