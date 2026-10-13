<?php

namespace Pixelpoems\Dashboard\Panel;

use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;

/**
 * Panel showing tabular data.
 *
 * getData() is expected to return:
 * - Columns: list of column headings
 * - Rows: list of rows, each a list of cells in column order. A cell is either a plain value
 *   or ['Value' => mixed, 'Link' => ?string] to link the cell
 * - Total: optional total number of records, shown as "x of y" if more records exist than rows shown
 * - MoreLink / MoreTitle: optional link to the complete list
 */
abstract class TablePanel extends DashboardPanel
{
    private static int $width = 6;

    protected function prepareTemplateData(array $data): array
    {
        $shown = count($data['Rows'] ?? []);
        $total = isset($data['Total']) ? (int) $data['Total'] : null;

        $data['Summary'] = $total !== null && $total > $shown
            ? _t(self::class . '.SHOWN_OF_TOTAL', 'Showing {shown} of {total}', ['shown' => $shown, 'total' => $total])
            : null;
        $data['MoreTitle'] = ($data['MoreTitle'] ?? '') ?: _t(self::class . '.SHOW_ALL', 'Show all');

        $data['Columns'] = ArrayList::create(array_map(
            fn ($title) => ArrayData::create(['Title' => $title]),
            array_values($data['Columns'] ?? [])
        ));

        $data['Rows'] = ArrayList::create(array_map(
            fn (array $row) => ArrayData::create([
                'Cells' => ArrayList::create(array_map(
                    fn ($cell) => ArrayData::create(is_array($cell)
                        ? ['Value' => $cell['Value'] ?? '', 'Link' => $cell['Link'] ?? null]
                        : ['Value' => $cell]),
                    array_values($row)
                )),
            ]),
            array_values($data['Rows'] ?? [])
        ));

        return $data;
    }
}
