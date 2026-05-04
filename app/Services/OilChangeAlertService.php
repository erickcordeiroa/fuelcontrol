<?php

namespace App\Services;

use App\Models\OilChange;
use App\Models\Trip;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class OilChangeAlertService
{
    public const KM_THRESHOLD = 1000;

    public const DAYS_THRESHOLD = 30;

    public const SIX_MONTHS_DAYS = 180;

    /**
     * @return Collection<int, array{
     *     vehicle_id: int,
     *     plate: string,
     *     model: string,
     *     oil_change_id: int,
     *     oil_change_date: string,
     *     km_driven: int,
     *     km_remaining: int,
     *     days_remaining: int,
     *     reasons: list<string>
     * }>
     */
    public function getAlerts(): Collection
    {
        $tenantSegment = Auth::check()
            ? (string) Auth::user()->tenantOwnerId()
            : '0';

        $cacheKey = 'oil_change_alerts:'.$tenantSegment;

        $rows = Cache::remember($cacheKey, 60, function (): array {
            return $this->computeLatestPerVehicle()
                ->map(fn (OilChange $oc) => $this->computeForRecord($oc))
                ->filter(fn (array $row) => $row['km_remaining'] <= self::KM_THRESHOLD || $row['days_remaining'] <= self::DAYS_THRESHOLD)
                ->sortBy(fn (array $row) => min($row['km_remaining'], $row['days_remaining'] * 100))
                ->values()
                ->all();
        });

        return collect($rows);
    }

    public static function forgetCacheForCurrentTenant(): void
    {
        if (! Auth::check()) {
            return;
        }

        Cache::forget('oil_change_alerts:'.Auth::user()->tenantOwnerId());
    }

    /**
     * @return array{
     *     vehicle_id: int,
     *     plate: string,
     *     model: string,
     *     oil_change_id: int,
     *     oil_change_date: string,
     *     km_driven: int,
     *     km_remaining: int,
     *     days_remaining: int,
     *     reasons: list<string>
     * }
     */
    public function computeForRecord(OilChange $oilChange): array
    {
        $oilChange->loadMissing('vehicle');

        $startInclusive = $this->oilChangeOccurredAt($oilChange);
        $next = $this->nextOilChange($oilChange);
        $endExclusive = $next !== null ? $this->oilChangeOccurredAt($next) : null;

        $kmDriven = $this->sumTripKmInWindow(
            (int) $oilChange->vehicle_id,
            $startInclusive,
            $endExclusive,
            $oilChange->date->toDateString(),
            $next?->date->toDateString(),
        );

        $kmRemaining = (int) $oilChange->interval_km - $kmDriven;

        $daysSince = (int) $oilChange->date->startOfDay()
            ->diffInDays(CarbonImmutable::now()->startOfDay(), absolute: false);
        $daysRemaining = self::SIX_MONTHS_DAYS - $daysSince;

        $reasons = [];
        if ($kmRemaining <= self::KM_THRESHOLD) {
            $reasons[] = 'km';
        }
        if ($daysRemaining <= self::DAYS_THRESHOLD) {
            $reasons[] = 'time';
        }

        return [
            'vehicle_id' => (int) $oilChange->vehicle_id,
            'plate' => (string) ($oilChange->vehicle?->plate ?? ''),
            'model' => (string) ($oilChange->vehicle?->model ?? ''),
            'oil_change_id' => (int) $oilChange->id,
            'oil_change_date' => $oilChange->date->toDateString(),
            'km_driven' => $kmDriven,
            'km_remaining' => $kmRemaining,
            'days_remaining' => $daysRemaining,
            'reasons' => $reasons,
        ];
    }

    /**
     * Troca vigente por veículo (a mais recente pelo instante efetivo), usada para status na listagem.
     *
     * @return array<int, int> vehicle_id => oil_change_id
     */
    public function latestOilChangeIdByVehicleId(): array
    {
        $map = [];
        foreach ($this->computeLatestPerVehicle() as $oc) {
            $map[(int) $oc->vehicle_id] = (int) $oc->id;
        }

        return $map;
    }

    /**
     * @return Collection<int, OilChange>
     */
    private function computeLatestPerVehicle(): Collection
    {
        return OilChange::query()
            ->with('vehicle')
            ->get()
            ->groupBy(fn (OilChange $oc): int => (int) $oc->vehicle_id)
            ->map(function (Collection $group): OilChange {
                return $group
                    ->sort(function (OilChange $a, OilChange $b): int {
                        $ta = $this->oilChangeOccurredAt($a)->timestamp;
                        $tb = $this->oilChangeOccurredAt($b)->timestamp;

                        if ($ta !== $tb) {
                            return $ta <=> $tb;
                        }

                        return $a->id <=> $b->id;
                    })
                    ->last();
            })
            ->values();
    }

    private function nextOilChange(OilChange $oilChange): ?OilChange
    {
        $at = $this->oilChangeOccurredAt($oilChange);

        return OilChange::query()
            ->where('vehicle_id', $oilChange->vehicle_id)
            ->where('id', '!=', $oilChange->id)
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->filter(function (OilChange $other) use ($at, $oilChange): bool {
                $otherAt = $this->oilChangeOccurredAt($other);

                return $otherAt->greaterThan($at)
                    || ($otherAt->equalTo($at) && $other->id > $oilChange->id);
            })
            ->sortBy(fn (OilChange $o) => [$this->oilChangeOccurredAt($o)->timestamp, $o->id])
            ->first();
    }

    private function oilChangeOccurredAt(OilChange $oilChange): CarbonImmutable
    {
        $calendar = $oilChange->date->format('Y-m-d');
        $timezone = (string) config('app.timezone');

        if ($oilChange->occurred_at !== null) {
            $wallClock = CarbonImmutable::parse($oilChange->occurred_at)->timezone($timezone);

            return CarbonImmutable::createFromFormat(
                'Y-m-d H:i:s',
                $calendar.' '.$wallClock->format('H:i:s'),
                $timezone,
            );
        }

        if ($oilChange->created_at !== null) {
            $wallClock = CarbonImmutable::parse($oilChange->created_at)->timezone($timezone);

            return CarbonImmutable::createFromFormat(
                'Y-m-d H:i:s',
                $calendar.' '.$wallClock->format('H:i:s'),
                $timezone,
            );
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $calendar, $timezone)->startOfDay();
    }

    /**
     * @param  string  $tripSqlDateFrom  Início civil (coluna date da troca), alinhado a trips.date.
     * @param  string|null  $tripSqlDateThrough  Fim civil inclusivo (coluna date da próxima troca).
     */
    private function sumTripKmInWindow(
        int $vehicleId,
        CarbonImmutable $startInclusive,
        ?CarbonImmutable $endExclusive,
        string $tripSqlDateFrom,
        ?string $tripSqlDateThrough,
    ): int {
        $query = Trip::query()
            ->where('vehicle_id', $vehicleId)
            ->whereDate('date', '>=', $tripSqlDateFrom);

        if ($tripSqlDateThrough !== null) {
            $query->whereDate('date', '<=', $tripSqlDateThrough);
        }

        return (int) $query->get()->filter(function (Trip $trip) use ($startInclusive, $endExclusive): bool {
            $tripAt = $this->tripOccurredAt($trip);

            if ($tripAt->lessThan($startInclusive)) {
                return false;
            }

            if ($endExclusive !== null && ! $tripAt->lessThan($endExclusive)) {
                return false;
            }

            return true;
        })->sum('km_total');
    }

    private function tripOccurredAt(Trip $trip): CarbonImmutable
    {
        $dateStr = $trip->date instanceof CarbonInterface
            ? $trip->date->format('Y-m-d')
            : (string) $trip->date;

        $date = CarbonImmutable::createFromFormat('Y-m-d', $dateStr, (string) config('app.timezone'))->startOfDay();
        $time = $trip->trip_time;

        if ($time === null || trim((string) $time) === '') {
            return $date;
        }

        $parts = explode(':', $time);
        $h = (int) ($parts[0] ?? 0);
        $m = (int) ($parts[1] ?? 0);

        return $date->setTime(max(0, min(23, $h)), max(0, min(59, $m)), 0);
    }
}
