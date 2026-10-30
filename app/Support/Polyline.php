<?php

namespace App\Support;

/**
 * Encoded Polyline (precisão 5, formato do Google): guarda o trajeto de cada trecho em texto curto.
 * O app e o mapa decodificam com qualquer biblioteca de polyline.
 */
class Polyline
{
    private const FACTOR = 1e5;

    /**
     * @param  list<array{0: float, 1: float}>  $points  Pares [lat, lng]
     */
    public static function encode(array $points): string
    {
        $encoded = '';
        $previousLat = 0;
        $previousLng = 0;

        foreach ($points as [$lat, $lng]) {
            $latE5 = (int) round($lat * self::FACTOR);
            $lngE5 = (int) round($lng * self::FACTOR);

            $encoded .= self::encodeValue($latE5 - $previousLat).self::encodeValue($lngE5 - $previousLng);

            $previousLat = $latE5;
            $previousLng = $lngE5;
        }

        return $encoded;
    }

    /**
     * @return list<array{0: float, 1: float}> Pares [lat, lng]
     */
    public static function decode(?string $encoded): array
    {
        if ($encoded === null || $encoded === '') {
            return [];
        }

        $points = [];
        $index = 0;
        $length = strlen($encoded);
        $lat = 0;
        $lng = 0;

        while ($index < $length) {
            $lat += self::decodeValue($encoded, $index);
            $lng += self::decodeValue($encoded, $index);
            $points[] = [$lat / self::FACTOR, $lng / self::FACTOR];
        }

        return $points;
    }

    private static function encodeValue(int $value): string
    {
        $value = $value < 0 ? ~($value << 1) : $value << 1;
        $chunk = '';

        while ($value >= 0x20) {
            $chunk .= chr((0x20 | ($value & 0x1F)) + 63);
            $value >>= 5;
        }

        return $chunk.chr($value + 63);
    }

    private static function decodeValue(string $encoded, int &$index): int
    {
        $result = 0;
        $shift = 0;

        do {
            $byte = ord($encoded[$index++]) - 63;
            $result |= ($byte & 0x1F) << $shift;
            $shift += 5;
        } while ($byte >= 0x20);

        return ($result & 1) ? ~($result >> 1) : $result >> 1;
    }
}
