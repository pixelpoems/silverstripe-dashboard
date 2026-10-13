<?php

namespace Pixelpoems\Dashboard\Panel\BasicPanels;

use Pixelpoems\Dashboard\Panel\KpiPanel;
use SilverStripe\Security\Member;

/**
 * Example panel - number of members incl. new members within the selected date range
 */
class MemberCountPanel extends KpiPanel
{
    private static string $title = 'Members';

    private static int $cache_lifetime = 300;

    private static array $required_permission_codes = ['CMS_ACCESS_SecurityAdmin'];

    public function getData(): array
    {
        return [
            'Value' => Member::get()->count(),
            'Label' => _t(self::class . '.LABEL', 'Registered members'),
            'Compare' => _t(
                self::class . '.COMPARE',
                '+{count} in the selected period',
                ['count' => $this->filterByDateRange(Member::get())->count()]
            ),
        ];
    }
}
