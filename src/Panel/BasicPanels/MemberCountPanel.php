<?php

namespace Pixelpoems\Dashboard\Panel\BasicPanels;

use Pixelpoems\Dashboard\Panel\KpiPanel;
use Pixelpoems\Dashboard\Panel\SilvershopPanels\ShopOrderPanelTrait;
use SilverStripe\ORM\DataList;
use SilverStripe\Security\Member;

/**
 * Example panel - number of members incl. new members within the selected date range
 */
class MemberCountPanel extends KpiPanel
{
    use ShopOrderPanelTrait;

    private static string $title = 'Members';

    private static int $cache_lifetime = 300;

    private static bool $filter_locale = true;

    private static array $required_permission_codes = ['CMS_ACCESS_SecurityAdmin'];

    protected function getMembers(): DataList
    {
        $members = Member::get();

        if (!$this->filtersByLocale() || !$this->getCurrentLocaleCode()) {
            return $members;
        }

        $memberIDs = $this->getPlacedOrders(true)
            ->exclude('MemberID', 0)
            ->columnUnique('MemberID');

        // Empty IN () would match everything
        return $members->filter('ID', $memberIDs ?: [0]);
    }

    public function getData(): array
    {
        $label = $this->filtersByLocale() && $this->getCurrentLocaleCode()
            ? _t(self::class . '.LABEL_LOCALE', 'Members with orders in {locale}', [
                'locale' => $this->getCurrentLocaleCode(),
            ])
            : _t(MemberCountPanel::class . '.LABEL', 'Registered members');

        return [
            'Value' => $this->getMembers()->count(),
            'Label' => $label,
            'Compare' => _t(
                MemberCountPanel::class . '.COMPARE',
                '+{count} in the selected period',
                ['count' => $this->filterByDateRange($this->getMembers())->count()]
            ),
        ];
    }
}
