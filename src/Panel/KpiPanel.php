<?php

namespace Pixelpoems\Dashboard\Panel;

/**
 * Panel showing a single key figure.
 *
 * getData() is expected to return:
 * - Value: the key figure (int|float|string)
 * - Label: optional caption below the value
 * - Compare: optional secondary info, e.g. "+12 in the last 30 days"
 */
abstract class KpiPanel extends DashboardPanel
{
    private static int $width = 3;
}
