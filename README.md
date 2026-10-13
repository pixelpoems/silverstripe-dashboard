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
4. Grant groups the permission `CMS_ACCESS_DashboardAdmin` where needed.

The dashboard is available at `/admin/dashboard`. The menu item only shows if the member can see at least one panel.

> CSS/JS in `client/dist` are edited directly (no build step). Run `composer vendor-expose` again after changing them or after moving/renaming the module folder.

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
      class: Pixelpoems\Dashboard\Panel\Example\MemberCountPanel
      sort: 10
      width: 4
    orders: false
```

### Panel config

| Config | Default | Description |
|---|---|---|
| `title` / `description` | `''` | Heading / subtitle, translatable via `<Class>.TITLE` / `<Class>.DESCRIPTION` |
| `width` | `4` | Columns out of 12 (`KpiPanel`: 3, `TablePanel`: 6, `LineChartPanel`: 12) |
| `sort` | `100` | Sort order, ascending |
| `lazy` | `true` | Load content asynchronously |
| `cache_lifetime` | `0` | Seconds to cache `getData()` – the cache is shared across members, `?flush=1` clears it |
| `required_permission_codes` | `[]` | Permission codes the member needs (all of them) |
| `uses_date_range` | `true` | Follow the date range filter, `false` marks the panel as "Total" |
| `date_field` | `'Created'` | Field used by `filterByDateRange()`, relations work too (`Order.OrderDate`) |

## Panel types

Extend one of these and implement `getData()`:

| Class | Data returned by `getData()` |
|---|---|
| `Panel\KpiPanel` | `Value`, optional `Label`, `Compare` |
| `Panel\TablePanel` | `Columns`, `Rows` (list of value lists), optional `Total` ("x of y shown"), `MoreLink` / `MoreTitle` |
| `Panel\LineChartPanel` | `Points` (list of `['Label' => …, 'Value' => …]`), optional `SeriesTitle` |
| `Panel\DashboardPanel` | anything – provide a template named after your class |

The line chart is drawn as SVG by `dashboard.js` (no chart library), with tooltip and table view. `countByInterval($list)` builds the points for "records per day/week/month" within the date range; config `interval`: `auto` (default), `day`, `week` or `month`.

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
| `canView(?Member $member)` | `DashboardPanel` | `false` denies, `true` allows, `null` = default check |

Templates (incl. `DashboardAdmin_Content.ss`) can be overridden in the project or theme as usual. Colours can be changed via the CSS variables `--dashboard-color-range(-bg)` and `--dashboard-color-total(-bg)`.
