<?php

namespace Pixelpoems\Dashboard\Admin;

use Pixelpoems\Dashboard\Filter\DateRange;
use Pixelpoems\Dashboard\Panel\DashboardPanel;
use InvalidArgumentException;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Control\Controller;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Security\Security;
use SilverStripe\View\Requirements;

/**
 * Dashboard section of the CMS.
 *
 * Panels are registered per project via the `panels` config, keyed by a unique panel key:
 *
 * ```yaml
 * Pixelpoems\Dashboard\Admin\DashboardAdmin:
 *   panels:
 *     members:
 *       class: Pixelpoems\Dashboard\Panel\Example\MemberCountPanel
 *       sort: 10
 *       width: 4
 *     orders: false # disable a panel registered by another module
 * ```
 *
 * All keys except `class` override the panel's own config (title, width, sort, lazy, ...).
 */
class DashboardAdmin extends LeftAndMain
{
    private static $url_segment = 'dashboard';

    private static $menu_title = 'Dashboard';

    private static $menu_icon_class = 'font-icon-chart-line';

    private static $menu_priority = 1000;

    private static $required_permission_codes = 'CMS_ACCESS_DashboardAdmin';

    private static $allowed_actions = [
        'panel',
    ];

    private static $url_handlers = [
        'panel/$Key!' => 'panel',
    ];

    /**
     * Panel registry: panel key => ['class' => DashboardPanel subclass, ...overrides] or false to disable
     */
    private static array $panels = [];

    /**
     * Days of the date range used if none has been selected yet (ending today)
     */
    private static int $default_range_days = 30;

    /**
     * Quick selection buttons of the date range filter, in days (ending today)
     */
    private static array $date_range_presets = [7, 30, 90, 365];

    /**
     * Show the legend explaining the colour markers ("follows the selected period" / "total")
     */
    private static bool $show_legend = true;

    protected ?DateRange $dateRange = null;

    /**
     * Instantiated panels, keyed by panel key (includes panels the current member can't view)
     *
     * @var array<string, DashboardPanel>|null
     */
    protected ?array $panelInstances = null;

    protected function init()
    {
        parent::init();

        Requirements::css('pixelpoems/silverstripe-dashboard: client/dist/css/dashboard.min.css');
        Requirements::javascript('pixelpoems/silverstripe-dashboard: client/dist/js/dashboard.min.js');
    }

    /**
     * Only show the section if at least one panel is visible to the member
     */
    public function canView($member = null)
    {
        if (!parent::canView($member)) {
            return false;
        }

        if ($member === null) {
            $member = Security::getCurrentUser();
        }

        foreach ($this->getAllPanels() as $panel) {
            if ($panel->canView($member)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Panels visible to the current member, sorted for display
     */
    public function getPanels(): ArrayList
    {
        $panels = ArrayList::create();
        foreach ($this->getAllPanels() as $panel) {
            if ($panel->canView()) {
                $panels->push($panel);
            }
        }

        return $panels->sort(['Sort' => 'ASC', 'Key' => 'ASC']);
    }

    /**
     * Date range selected in the filter bar, see {@link DateRange::fromRequest()}
     */
    public function getDateRange(): DateRange
    {
        return $this->dateRange ??= DateRange::fromRequest(
            $this->getRequest(),
            static::config()->get('default_range_days')
        );
    }

    public function getShowLegend(): bool
    {
        return (bool) static::config()->get('show_legend');
    }

    public function getFilterFrom(): string
    {
        return $this->getDateRange()->getFromValue();
    }

    public function getFilterTo(): string
    {
        return $this->getDateRange()->getToValue();
    }

    public function getDateRangePresets(): ArrayList
    {
        $current = $this->getDateRange();
        $presets = ArrayList::create();

        foreach (static::config()->get('date_range_presets') as $days) {
            $range = DateRange::lastDays((int) $days);
            $presets->push(ArrayData::create([
                'Title' => _t(self::class . '.PRESET_DAYS', '{count} days', ['count' => (int) $days]),
                'Link' => $this->getFilterLink($range),
                'IsCurrent' => $range->equals($current),
            ]));
        }

        return $presets;
    }

    /**
     * Dashboard link for the given date range
     */
    public function getFilterLink(?DateRange $range = null): string
    {
        $range ??= $this->getDateRange();

        return Controller::join_links($this->Link(), '?' . http_build_query($range->getQueryParams()));
    }

    public function getPanel(string $key): ?DashboardPanel
    {
        return $this->getAllPanels()[$key] ?? null;
    }

    /**
     * Renders a single panel - used to lazy load panel content
     */
    public function panel(HTTPRequest $request): DBHTMLText|HTTPResponse
    {
        $panel = $this->getPanel((string) $request->param('Key'));
        if (!$panel) {
            return $this->httpError(404);
        }

        if (!$panel->canView()) {
            return Security::permissionFailure($this);
        }

        return $panel->renderContent();
    }

    /**
     * @return array<string, DashboardPanel>
     */
    protected function getAllPanels(): array
    {
        if ($this->panelInstances !== null) {
            return $this->panelInstances;
        }

        $this->panelInstances = [];
        foreach (static::config()->get('panels') as $key => $config) {
            if (!$config) {
                continue;
            }

            $this->panelInstances[$key] = $this->createPanel((string) $key, $config);
        }

        $this->extend('updatePanels', $this->panelInstances);

        return $this->panelInstances;
    }

    protected function createPanel(string $key, array|string $config): DashboardPanel
    {
        if (is_string($config)) {
            $config = ['class' => $config];
        }

        $class = $config['class'] ?? null;
        if (!$class || !is_a($class, DashboardPanel::class, true)) {
            throw new InvalidArgumentException(sprintf(
                'Dashboard panel "%s" must define a "class" extending %s',
                $key,
                DashboardPanel::class
            ));
        }
        unset($config['class']);

        /** @var DashboardPanel $panel */
        $panel = Injector::inst()->create($class);
        $panel->setKey($key);
        $panel->setOverrides($config);
        $panel->setAdmin($this);

        return $panel;
    }
}
