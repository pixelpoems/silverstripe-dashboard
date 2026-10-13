<?php

namespace Pixelpoems\Dashboard\Panel;

use DateTimeImmutable;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBField;

/**
 * Panel showing a single series as line chart, rendered client side as SVG (no chart library needed).
 *
 * getData() is expected to return:
 * - Points: list of points, each ['Label' => string, 'Value' => int|float]
 * - SeriesTitle: optional name of the series, shown in the tooltip
 *
 * {@link countByInterval()} builds the points for "records per day / week / month" within the date range.
 */
abstract class LineChartPanel extends DashboardPanel
{
    private static int $width = 12;

    /**
     * Interval of {@link countByInterval()}: day, week, month or auto (depending on the length of the date range)
     */
    private static string $interval = 'auto';

    /**
     * Max. number of days shown per day / per week with interval "auto"
     */
    private static int $auto_max_days = 92;

    private static int $auto_max_weeks_days = 730;

    /**
     * Counts the records of the list per interval within the date range, intervals without records are filled with 0
     *
     * @param string|null $field Date field to group by, defaults to the "date_field" config
     * @throws \Exception
     */
    public function countByInterval(DataList $list, ?string $field = null): array
    {
        $field ??= (string) $this->getOption('date_field');
        $range = $this->getDateRange();
        $interval = $this->getInterval();

        $counts = [];
        foreach ($this->filterByDateRange($list, $field)->column($field) as $value) {
            if (!$value) {
                continue;
            }
            $key = $this->getIntervalStart(new DateTimeImmutable($value), $interval)->format('Y-m-d');
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        $points = [];
        $date = $this->getIntervalStart($range->getFrom(), $interval);
        while ($date <= $range->getTo()) {
            $key = $date->format('Y-m-d');
            $points[] = [
                'Label' => $this->getIntervalLabel($date, $interval),
                'Value' => $counts[$key] ?? 0,
            ];
            $date = $date->modify('+1 ' . $interval);
        }

        return $points;
    }

    /**
     * Resolved interval (day, week or month)
     */
    public function getInterval(): string
    {
        $interval = (string) $this->getOption('interval');
        if (in_array($interval, ['day', 'week', 'month'], true)) {
            return $interval;
        }

        $days = $this->getDateRange()->getDays();

        return match (true) {
            $days <= (int) $this->getOption('auto_max_days') => 'day',
            $days <= (int) $this->getOption('auto_max_weeks_days') => 'week',
            default => 'month',
        };
    }

    protected function getIntervalStart(DateTimeImmutable $date, string $interval): DateTimeImmutable
    {
        return match ($interval) {
            'week' => $date->modify('monday this week')->setTime(0, 0),
            'month' => $date->modify('first day of this month')->setTime(0, 0),
            default => $date->setTime(0, 0),
        };
    }

    protected function getIntervalLabel(DateTimeImmutable $date, string $interval): string
    {
        /** @var DBDate $field */
        $field = DBField::create_field('Date', $date->format('Y-m-d'));

        return match ($interval) {
            'week' => _t(self::class . '.WEEK_OF', 'Week of {date}', ['date' => $field->Format('dd.MM.y')]),
            'month' => $field->Format('MMM y'),
            default => $field->Format('EE dd.MM.y'),
        };
    }

    protected function prepareTemplateData(array $data): array
    {
        $points = array_values($data['Points'] ?? []);

        $data['SeriesTitle'] = ($data['SeriesTitle'] ?? '') ?: $this->getTitle();
        $data['ChartData'] = json_encode([
            'series' => $data['SeriesTitle'],
            'labels' => array_map(fn (array $point) => (string) $point['Label'], $points),
            'values' => array_map(fn (array $point) => (float) $point['Value'], $points),
        ]);
        $data['Points'] = ArrayList::create(array_map(fn (array $point) => ArrayData::create($point), $points));
        $data['HasValues'] = array_sum(array_column($points, 'Value')) > 0;

        return $data;
    }
}
