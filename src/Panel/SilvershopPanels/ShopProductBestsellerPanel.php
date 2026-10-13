<?php

namespace Pixelpoems\Dashboard\Panel\SilvershopPanels;

use Pixelpoems\Dashboard\Panel\TablePanel;
use SilverShop\Admin\OrdersAdmin;
use SilverShop\Model\OrderAttribute;
use SilverShop\Model\OrderItem;
use SilverShop\Model\Product\OrderItem as ProductOrderItem;
use SilverShop\Page\Product;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;

/**
 * Best selling products (by quantity) within the selected date range, without carts and cancellations
 */
class ShopProductBestsellerPanel extends TablePanel
{
    use ShopOrderPanelTrait;

    private static string $title = 'Bestsellers';

    private static string $date_field = 'Order.Placed';

    private static int $cache_lifetime = 300;

    private static bool $filter_locale = true;

    private static array $required_permission_codes = ['CMS_ACCESS_' . OrdersAdmin::class];

    /**
     * Max. number of products shown
     */
    private static int $limit = 10;

    public function getData(): array
    {
        $schema = DataObject::getSchema();
        $productId = sprintf('"%s"."ProductID"', $schema->tableName(ProductOrderItem::class));
        $quantity = sprintf('"%s"."Quantity"', $schema->tableName(OrderItem::class));
        $orderId = sprintf('"%s"."OrderID"', $schema->tableName(OrderAttribute::class));

        $items = $this->getOrderItems();

        $query = $items->dataQuery()->getFinalisedQuery()
            ->setSelect([
                'ProductID' => $productId,
                'Quantity' => 'SUM(' . $quantity . ')',
                'Orders' => 'COUNT(DISTINCT ' . $orderId . ')',
            ])
            ->setDistinct(false)
            ->setGroupBy($productId)
            ->setOrderBy(['"Quantity"' => 'DESC', '"Orders"' => 'DESC'])
            ->setLimit((int) $this->getOption('limit'));

        $results = [];
        foreach ($query->execute() as $result) {
            $results[] = $result;
        }

        $products = [];
        foreach (Product::get()->byIDs(array_column($results, 'ProductID') ?: [0]) as $product) {
            $products[$product->ID] = $product;
        }

        $rows = [];
        foreach ($results as $index => $result) {
            /** @var Product|null $product */
            $product = $products[$result['ProductID']] ?? null;
            $rows[] = [
                $index + 1,
                $product?->InternalItemID,
                $product
                    ? ['Value' => $product->Title, 'Link' => $product->CMSEditLink()]
                    : _t(self::class . '.DELETED', '(deleted product)'),
                $result['Quantity'],
                $result['Orders'],
            ];
        }

        $total = $items->dataQuery()->getFinalisedQuery()
            ->setSelect(['Total' => 'COUNT(DISTINCT ' . $productId . ')'])
            ->setDistinct(false)
            ->setOrderBy([])
            ->execute()
            ->value();

        return [
            'Columns' => [
                '#',
                _t(self::class . '.ARTICLE_NR', 'Article no.'),
                _t(self::class . '.PRODUCT', 'Product'),
                _t(self::class . '.QUANTITY', 'Quantity sold'),
                _t(self::class . '.ORDERS', 'Orders'),
            ],
            'Rows' => $rows,
            'Total' => (int) $total,
        ];
    }

    /**
     * Product order items of valid orders within the selected date range (see {@link getValidOrders()})
     */
    protected function getOrderItems(): DataList
    {
        $items = ProductOrderItem::get()
            ->exclude('Order.Status', ['Cart', 'AdminCancelled', 'MemberCancelled']);

        $locale = $this->getCurrentLocaleCode();
        if ($this->filtersByLocale() && $locale) {
            $items = $items->filter('Order.Locale', $locale);
        }

        return $this->filterByDateRange($items);
    }
}
