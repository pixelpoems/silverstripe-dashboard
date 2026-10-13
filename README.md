# Dashboard

Configurable dashboard for the Silverstripe CMS 6. The module provides the CMS section, a grid layout, a date range filter and base classes for panels – **which panels are shown is configured per project via YAML.**

## Setup

1. Register panels in `app/_config/dashboard.yml` (see below).
2. Publish the assets and flush: `composer vendor-expose`, then `?flush=1`.
3. Optional: add the menu item to a menu group (`app/_config/app.yml`):
   ```yaml
   SilverStripe\Admin\LeftAndMain:
     menu_groups:
       Dashboard:
         icon: 'chart-line'
         items:
           - Pixelpoems-Dashboard-Admin-DashboardAdmin
   ```
   **Attention**: The Dashboard Admin will only be displayed if at least one panel is registered!

4. Grant groups the permission `CMS_ACCESS_DashboardAdmin` where needed.

The dashboard is available at `/admin/dashboard`. The menu item only shows if the member can see at least one panel.

> Sources are in `client/src` (JS / SCSS), the compiled files in `client/dist` are built with webpack: `yarn build` in the module folder. Run `composer vendor-expose` again after building or after moving/renaming the module folder.

## Registering panels

Panels are registered as a map `key => config`. Every key except `class` overrides the panel's config, `false` disables a panel registered elsewhere.

```yaml
---
Name: app-dashboard
After: dashboard
---
Pixelpoems\Dashboard\Admin\DashboardAdmin:
  default_range_days: 30
  date_range_presets: [7, 30, 90, 365]
  show_legend: true   # legend explaining the colour markers below the filter
  panels:
    members:
      class: Pixelpoems\Dashboard\Panel\BasicPanels\MemberCountPanel
      sort: 10
      width: 4
    orders: false
```

### Panel config

| Config | Default | Description |
|---|---|---|
| `title` / `description` | `''` | Heading / subtitle, translatable – see [Translations](#translations) |
| `width` | `4` | Columns out of 12 (`KpiPanel`: 3, `TablePanel`: 6, `LineChartPanel`: 12) |
| `sort` | `100` | Sort order, ascending |
| `lazy` | `true` | Load content asynchronously |
| `cache_lifetime` | `0` | Seconds to cache `getData()` – see [Caching](#caching) |
| `required_permission_codes` | `[]` | Permission codes the member needs (all of them) |
| `uses_date_range` | `true` | Follow the date range filter, `false` marks the panel as "Total" |
| `date_field` | `'Created'` | Field used by `filterByDateRange()`, relations work too (`Order.OrderDate`) |

## Translations

Panel titles and descriptions are translated via `_t()`:

- **Class config** (`private static $title`): `<Class>.TITLE` / `<Class>.DESCRIPTION`
- **Panel registration** (`title` / `description` in YAML): `Pixelpoems\Dashboard\Admin\DashboardAdmin.PANEL_<KEY>_TITLE` / `..._DESCRIPTION`. `<KEY>` is the panel key in upper case, non alphanumeric characters replaced by `_` (`orders-range-all` → `PANEL_ORDERS_RANGE_ALL_TITLE`). Without translation the YAML value is shown.

```yaml
# app/lang/de.yml
de:
  Pixelpoems\Dashboard\Admin\DashboardAdmin:
    PANEL_ORDERS_RANGE_ALL_TITLE: 'Bestellungen (alle Länder)'
```

Texts within `getData()` (labels, column headings, series titles …) are translated with `_t()` in the panel class as usual.

## Caching

With `cache_lifetime` > 0 the result of `getData()` is cached, `?flush=1` clears the cache. The cache is shared across members – don't cache member specific data.

The cache key consists of the panel class, the panel key, the date range and the CMS UI language of the member (`i18n::get_locale()`, as the data usually contains translated texts and formatted dates). If the data depends on anything else (e.g. the Fluent locale), add it to the cache key by overriding `getCacheKeyParts()` or via the `updateCacheKeyParts` extension hook:

```php
protected function getCacheKeyParts(): array
{
    return [...parent::getCacheKeyParts(), Locale::getCurrentLocale()?->Locale];
}
```

## Panel types

Extend one of these and implement `getData()`:

| Class | Data returned by `getData()` |
|---|---|
| `Panel\KpiPanel` | `Value`, optional `Label`, `Compare` |
| `Panel\TablePanel` | `Columns`, `Rows` (list of cell lists, a cell is a value or `['Value' => …, 'Link' => …]`), optional `Total` ("x of y shown"), `MoreLink` / `MoreTitle` |
| `Panel\LineChartPanel` | `Points` (list of `['Label' => …, 'Value' => …]`), optional `SeriesTitle` – or `Series` (list of `['Title' => …, 'Points' => …]`) for multiple lines with legend |
| `Panel\DashboardPanel` | anything – provide a template named after your class |

The line chart is drawn as SVG by `dashboard.js` (no chart library), with tooltip and table view. `countByInterval($list)` builds the points for "records per day/week/month" within the date range; config `interval`: `auto` (default), `day`, `week` or `month`.

### Included panels

**`Panel\BasicPanels\MemberCountPanel`** – number of members, incl. new members within the date range (requires `CMS_ACCESS_SecurityAdmin`).

**`Panel\SilvershopPanels\*`** – panels for [SilverShop](https://github.com/silvershop/silvershop-core) in combination with [Fluent](https://github.com/tractorcow-farm/silverstripe-fluent) (orders are filtered by `Order.Locale`). Neither is a dependency of this module – install them in the project if you use these panels.

> **Note:** The SilverShop panels have only been tested with Fluent so far.

Carts (`Cart`) and cancellations (`AdminCancelled`, `MemberCancelled`) are excluded, the date range applies to `Order.Placed`. All of them require access to the SilverShop orders admin (`CMS_ACCESS_SilverShop\Admin\OrdersAdmin`).

| Class | Type | Content |
|---|---|---|
| `ShopOrderRangePanel` | KPI | Number of orders, trend vs. previous period |
| `ShopOrderRevenuePanel` | KPI | Revenue (sum of `Order.Total`, gross), trend vs. previous period – only meaningful per locale, as locales may use different currencies |
| `ShopOrderChartPanel` | Line chart | Orders per day / week / month |
| `ShopOrderLatestPanel` | Table | Latest orders, order no. linked to the order admin |
| `ShopProductBestsellerPanel` | Table | Best selling products by quantity, product linked to the CMS |

Additional config of the SilverShop panels:

| Config | Panels | Default | Description |
|---|---|---|---|
| `filter_locale` | all except chart | `true` | Only orders of the current CMS (Fluent) locale – `false` for all countries. Locale filtered panels are cached per locale |
| `series` | `ShopOrderChartPanel` | `[locale, all]` | Lines shown: `locale` (current locale) and / or `all` (all countries) |
| `order_admin` | `ShopOrderLatestPanel` | `SilverShop\Admin\OrdersAdmin` | `OrdersAdmin` subclass the order links point to, e.g. a locale filtered orders admin |
| `limit` | `ShopOrderLatestPanel`, `ShopProductBestsellerPanel` | `15` / `10` | Max. number of rows |

```yaml
Pixelpoems\Dashboard\Admin\DashboardAdmin:
  panels:
    orders-range:
      class: Pixelpoems\Dashboard\Panel\SilvershopPanels\ShopOrderRangePanel
    orders-range-all:
      class: Pixelpoems\Dashboard\Panel\SilvershopPanels\ShopOrderRangePanel
      title: 'Orders (All Countries)'
      filter_locale: false
    orders-count:
      class: Pixelpoems\Dashboard\Panel\SilvershopPanels\ShopOrderChartPanel
      series: [locale, all]
```

## Example

```php
namespace App\Dashboard;

use Pixelpoems\Dashboard\Panel\KpiPanel;
use SilverStripe\Security\Member;

class NewMembersPanel extends KpiPanel
{
    private static string $title = 'New members';

    private static int $cache_lifetime = 300;

    public function getData(): array
    {
        $current = $this->filterByDateRange(Member::get())->count();
        $previous = $this->filterByDateRange(Member::get(), null, $this->getDateRange()->getPreviousRange())->count();

        return [
            'Value' => $current,
            'Compare' => $this->formatTrend($current, $previous),
        ];
    }
}
```

```yaml
Pixelpoems\Dashboard\Admin\DashboardAdmin:
  panels:
    new-members:
      class: App\Dashboard\NewMembersPanel
      sort: 20
```

## Extension points

| Hook | Where | Purpose |
|---|---|---|
| `updatePanels(array &$panels)` | `DashboardAdmin` | Add/remove panels after creation |
| `updateData(array &$data)` | `DashboardPanel` | Modify panel data (before caching) |
| `updateCacheKeyParts(array &$parts)` | `DashboardPanel` | Add values the cached data depends on (e.g. the current locale) – alternatively override `getCacheKeyParts()` |
| `canView(?Member $member)` | `DashboardPanel` | `false` denies, `true` allows, `null` = default check |

Templates (incl. `DashboardAdmin_Content.ss`) can be overridden in the project or theme as usual. Colours can be changed via the CSS variables `--dashboard-color-range(-bg)` and `--dashboard-color-total(-bg)`, the line chart series colours via `--dashboard-chart-color-0` … `-3`.
