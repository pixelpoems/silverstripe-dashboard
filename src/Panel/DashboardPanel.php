<?php

namespace Pixelpoems\Dashboard\Panel;

use Pixelpoems\Dashboard\Admin\DashboardAdmin;
use Pixelpoems\Dashboard\Filter\DateRange;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Control\Controller;
use SilverStripe\Model\ModelData;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\View\SSViewer;

/**
 * Base class for all dashboard panels.
 *
 * Subclasses implement {@link getData()} and optionally provide their own template, resolved by class
 * hierarchy (e.g. templates/Pixelpoems/Dashboard/Panel/KpiPanel.ss). The array returned by getData()
 * is available as top level variables in the panel template.
 *
 * Every config value below can be overridden per project in the panel registration of {@link DashboardAdmin}.
 */
abstract class DashboardPanel extends ModelData
{
    private static string $title = '';

    private static string $description = '';

    /**
     * Grid columns (1-12) the panel spans
     */
    private static int $width = 4;

    private static int $sort = 100;

    /**
     * Load panel content asynchronously after the dashboard has been rendered
     */
    private static bool $lazy = true;

    /**
     * Seconds to cache the result of getData(), 0 disables caching.
     * Note: the cache is shared across members - don't cache member specific data.
     */
    private static int $cache_lifetime = 0;

    /**
     * Permission codes the member needs (all of them) to see this panel
     */
    private static array $required_permission_codes = [];

    /**
     * Whether the panel follows the date range of the filter bar. Panels that don't are marked as "total".
     */
    private static bool $uses_date_range = true;

    /**
     * Default field used by {@link filterByDateRange()}
     */
    private static string $date_field = 'Created';

    private static array $dependencies = [
        'cache' => '%$' . CacheInterface::class . '.DashboardPanel',
    ];

    protected string $key = '';

    protected array $overrides = [];

    protected ?DashboardAdmin $admin = null;

    protected ?CacheInterface $cache = null;

    protected ?DateRange $dateRange = null;

    /**
     * Raw panel data, rendered with the panel template
     */
    abstract public function getData(): array;

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): static
    {
        $this->key = $key;
        return $this;
    }

    /**
     * Config overrides from the panel registration
     */
    public function setOverrides(array $overrides): static
    {
        $this->overrides = $overrides;
        return $this;
    }

    public function setAdmin(DashboardAdmin $admin): static
    {
        $this->admin = $admin;
        return $this;
    }

    public function setCache(CacheInterface $cache): static
    {
        $this->cache = $cache;
        return $this;
    }

    /**
     * Get a setting, preferring the project override over the class config
     */
    public function getOption(string $name): mixed
    {
        return array_key_exists($name, $this->overrides)
            ? $this->overrides[$name]
            : static::config()->get($name);
    }

    public function getTitle(): string
    {
        return $this->getTranslatedOption('title', 'TITLE');
    }

    public function getDescription(): string
    {
        return $this->getTranslatedOption('description', 'DESCRIPTION');
    }

    /**
     * Project overrides are used as is, class config values are translatable via <class>.<entity>
     */
    protected function getTranslatedOption(string $name, string $entity): string
    {
        if (array_key_exists($name, $this->overrides)) {
            return (string) $this->overrides[$name];
        }

        $default = (string) static::config()->get($name);

        // _t() requires a default, empty values would trigger a "missing default" warning
        return $default !== '' ? _t(static::class . '.' . $entity, $default) : '';
    }

    public function getWidth(): int
    {
        return max(1, min(12, (int) $this->getOption('width')));
    }

    public function getSort(): int
    {
        return (int) $this->getOption('sort');
    }

    public function isLazy(): bool
    {
        return (bool) $this->getOption('lazy');
    }

    public function getLazyLink(): ?string
    {
        if (!$this->admin) {
            return null;
        }

        $link = $this->admin->Link('panel/' . $this->getKey());
        if (!$this->usesDateRange()) {
            return $link;
        }

        return Controller::join_links($link, '?' . http_build_query($this->getDateRange()->getQueryParams()));
    }

    public function usesDateRange(): bool
    {
        return (bool) $this->getOption('uses_date_range');
    }

    public function setDateRange(DateRange $dateRange): static
    {
        $this->dateRange = $dateRange;
        return $this;
    }

    /**
     * Date range of the dashboard filter (falls back to the admin's default range)
     */
    public function getDateRange(): DateRange
    {
        return $this->dateRange ??= $this->admin?->getDateRange()
            ?? DateRange::lastDays(DashboardAdmin::config()->get('default_range_days'));
    }

    public function getDateRangeNice(): string
    {
        return $this->getDateRange()->Nice();
    }

    /**
     * Limits the list to the selected date range
     *
     * @param string|null $field Date field to filter on, defaults to the "date_field" config
     */
    public function filterByDateRange(DataList $list, ?string $field = null, ?DateRange $range = null): DataList
    {
        $range ??= $this->getDateRange();

        return $range->apply($list, $field ?? (string) $this->getOption('date_field'));
    }

    /**
     * Relative change between two values, e.g. "+12 % vs. previous period"
     */
    public function formatTrend(int|float $current, int|float $previous): string
    {
        if ($previous == 0) {
            return $current > 0
                ? _t(self::class . '.TREND_NEW', 'previous period: 0')
                : _t(self::class . '.TREND_NONE', 'no change vs. previous period');
        }

        $percent = (int) round(($current - $previous) / $previous * 100);

        return _t(
            self::class . '.TREND',
            '{change} % vs. previous period',
            ['change' => ($percent > 0 ? '+' : '') . $percent]
        );
    }

    public function canView(?Member $member = null): bool
    {
        $member ??= Security::getCurrentUser();

        // Same semantics as DataObject::extendedCan(): any false denies, otherwise any true allows
        $results = array_filter($this->extend('canView', $member), fn ($result) => $result !== null);
        if ($results) {
            return (bool) min($results);
        }

        foreach ((array) $this->getOption('required_permission_codes') as $code) {
            if (!$member || !Permission::checkMember($member, $code)) {
                return false;
            }
        }

        return true;
    }

    /**
     * getData() incl. extension hook and caching
     */
    public function getCachedData(): array
    {
        $lifetime = (int) $this->getOption('cache_lifetime');
        $cacheKey = md5(implode('_', [
            static::class,
            $this->getKey(),
            $this->usesDateRange() ? $this->getDateRange()->getCacheKey() : 'total',
        ]));

        if ($lifetime > 0 && $this->cache && $this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        $data = $this->getData();
        $this->extend('updateData', $data);

        if ($lifetime > 0 && $this->cache) {
            $this->cache->set($cacheKey, $data, $lifetime);
        }

        return $data;
    }

    /**
     * Renders the panel body with the most specific template of the class hierarchy
     */
    public function renderContent(): DBHTMLText
    {
        $data = $this->prepareTemplateData($this->getCachedData());

        return $this->customise($data)->renderWith(
            SSViewer::get_templates_by_class(static::class, '', self::class)
        );
    }

    /**
     * Hook for subclasses to convert raw data into template friendly structures (e.g. ArrayList)
     */
    protected function prepareTemplateData(array $data): array
    {
        return $data;
    }

    public function forTemplate(): string
    {
        return (string) $this->renderContent();
    }
}
