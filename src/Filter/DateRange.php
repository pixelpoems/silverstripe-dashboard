<?php

namespace Pixelpoems\Dashboard\Filter;

use DateTimeImmutable;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;

/**
 * Date range the dashboard is filtered by (whole days, both ends inclusive)
 */
final class DateRange
{
    public const string SESSION_KEY = 'Pixelpoems\Dashboard.DateRange';

    private const string FORMAT = 'Y-m-d';

    private DateTimeImmutable $from;

    private DateTimeImmutable $to;

    public function __construct(DateTimeImmutable $from, DateTimeImmutable $to)
    {
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $this->from = $from->setTime(0, 0);
        $this->to = $to->setTime(23, 59, 59);
    }

    /**
     * Range ending today, spanning the given number of days (incl. today)
     */
    public static function lastDays(int $days): self
    {
        $today = self::today();

        return new self($today->modify(sprintf('-%d days', max(1, $days) - 1)), $today);
    }

    /**
     * Range from the request (?from=YYYY-MM-DD&to=YYYY-MM-DD), falling back to the last selection stored in the
     * session and finally to the last $defaultDays days. A valid range from the request is stored in the session.
     */
    public static function fromRequest(HTTPRequest $request, int $defaultDays): self
    {
        $session = $request->hasSession() ? $request->getSession() : null;

        $from = self::parseDate($request->getVar('from'));
        $to = self::parseDate($request->getVar('to'));

        if ($from || $to) {
            $range = new self($from ?? $to, $to ?? self::today());
            $session?->set(self::SESSION_KEY, $range->getQueryParams());

            return $range;
        }

        $stored = $session?->get(self::SESSION_KEY);
        if (is_array($stored)) {
            $from = self::parseDate($stored['from'] ?? null);
            $to = self::parseDate($stored['to'] ?? null);
            if ($from && $to) {
                return new self($from, $to);
            }
        }

        return self::lastDays($defaultDays);
    }

    public function getFrom(): DateTimeImmutable
    {
        return $this->from;
    }

    public function getTo(): DateTimeImmutable
    {
        return $this->to;
    }

    public function getDays(): int
    {
        return $this->from->diff($this->to)->days + 1;
    }

    /**
     * Range of the same length directly before this one, e.g. for trend comparisons
     */
    public function getPreviousRange(): self
    {
        $days = $this->getDays();

        return new self(
            $this->from->modify(sprintf('-%d days', $days)),
            $this->from->modify('-1 day')
        );
    }

    /**
     * Limits the list to records whose $field lies within the range
     */
    public function apply(DataList $list, string $field): DataList
    {
        return $list->filter([
            $field . ':GreaterThanOrEqual' => $this->from->format('Y-m-d H:i:s'),
            $field . ':LessThanOrEqual' => $this->to->format('Y-m-d H:i:s'),
        ]);
    }

    public function getFromValue(): string
    {
        return $this->from->format(self::FORMAT);
    }

    public function getToValue(): string
    {
        return $this->to->format(self::FORMAT);
    }

    /**
     * @return array{from: string, to: string}
     */
    public function getQueryParams(): array
    {
        return ['from' => $this->getFromValue(), 'to' => $this->getToValue()];
    }

    public function getCacheKey(): string
    {
        return $this->getFromValue() . '_' . $this->getToValue();
    }

    public function equals(DateRange $other): bool
    {
        return $this->getCacheKey() === $other->getCacheKey();
    }

    /**
     * Localised representation, e.g. "05.09.2026 – 05.10.2026"
     */
    public function Nice(): string
    {
        return sprintf('%s – %s', $this->niceDate($this->from), $this->niceDate($this->to));
    }

    public function forTemplate(): string
    {
        return $this->Nice();
    }

    private static function today(): DateTimeImmutable
    {
        return new DateTimeImmutable(DBDatetime::now()->getValue());
    }

    private static function parseDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!' . self::FORMAT, $value);

        return $date && $date->format(self::FORMAT) === $value ? $date : null;
    }

    private function niceDate(DateTimeImmutable $date): string
    {
        /** @var DBDate $field */
        $field = DBField::create_field('Date', $date->format(self::FORMAT));

        return $field->Nice();
    }
}
