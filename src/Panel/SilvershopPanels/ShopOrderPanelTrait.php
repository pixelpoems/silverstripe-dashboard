<?php

namespace Pixelpoems\Dashboard\Panel\SilvershopPanels;

use SilverShop\Admin\OrdersAdmin;
use SilverShop\Model\Order;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataList;
use TractorCow\Fluent\Model\Locale;

/**
 * Shared order queries for the dashboard order panels.
 * Panels using this trait should set `date_field: Placed` and `required_permission_codes: ['CMS_ACCESS_SilverShop\Admin\OrdersAdmin']`.
 * Set `filter_locale: true` (class config or panel registration) to only show orders of the current CMS locale.
 * Set `order_admin` to an {@link OrdersAdmin} subclass the order links should point to (defaults to OrdersAdmin).
 */
trait ShopOrderPanelTrait
{
    /**
     * Orders admin used for order links, see the "order_admin" config
     */
    protected function getOrderAdmin(): OrdersAdmin
    {
        $class = (string) $this->getOption('order_admin');

        return Injector::inst()->get(
            $class && is_a($class, OrdersAdmin::class, true) ? $class : OrdersAdmin::class
        );
    }

    protected function getOrderEditLink(Order $order): string
    {
        return $this->getOrderAdmin()->getCMSEditLinkForManagedDataObject($order);
    }

    /**
     * Whether the panel is limited to the orders of the current CMS locale
     */
    protected function filtersByLocale(): bool
    {
        return (bool) $this->getOption('filter_locale');
    }

    /**
     * Locale filtered panels need a cache entry per locale
     */
    protected function getCacheKeyParts(): array
    {
        $parts = parent::getCacheKeyParts();

        if ($this->filtersByLocale()) {
            $parts[] = $this->getCurrentLocaleCode() ?? 'none';
        }

        return $parts;
    }

    /**
     * Current Fluent locale, null without Fluent (no locale filtering then)
     */
    protected function getCurrentLocaleCode(): ?string
    {
        return class_exists(Locale::class) ? Locale::getCurrentLocale()?->Locale : null;
    }

    /**
     * Orders that have actually been placed - CART orders are still in the checkout.
     *
     * @param bool|null $filterLocale Limit to the current locale, defaults to {@link filtersByLocale()}
     */
    protected function getPlacedOrders(?bool $filterLocale = null): DataList
    {
        $orders = Order::get()
            ->exclude('Status', 'Cart');

        $locale = $this->getCurrentLocaleCode();
        if (($filterLocale ?? $this->filtersByLocale()) && $locale) {
            $orders = $orders->filter('Locale', $locale);
        }

        return $orders;
    }

    /**
     * Placed orders without cancellations
     *
     * @param bool|null $filterLocale Limit to the current locale, defaults to {@link filtersByLocale()}
     */
    protected function getValidOrders(?bool $filterLocale = null): DataList
    {
        return $this->getPlacedOrders($filterLocale)
            ->exclude('Status', ['AdminCancelled', 'MemberCancelled']);
    }
}
