<?php

/**
 * UX Customizer - Asset retention policy
 *
 * Stores a company retention period (in years) per Computer type (e.g. Laptop
 * 5y, Server 7y) plus a default, in the plugin config (`retention` key, JSON).
 * The Computer Dashboard uses it to compute a retirement date from the purchase
 * date (Infocom). Set it under Setup → UX Customizer → Lifecycle.
 *
 * @license   GPL-3.0-or-later
 */

namespace GlpiPlugin\Uxcustomizer;

class Lifecycle
{
    public const DEFAULT_YEARS = 5;

    /**
     * Retention map: ['default' => years, '<computertypes_id>' => years, ...].
     */
    public static function getRetentionMap(): array
    {
        $raw     = Config::get('retention');
        $decoded = $raw !== null ? json_decode($raw, true) : null;
        $map     = is_array($decoded) ? $decoded : [];
        if (!isset($map['default']) || (int) $map['default'] <= 0) {
            $map['default'] = self::DEFAULT_YEARS;
        }
        return $map;
    }

    /**
     * Persist the retention map. Keys are 'default' or numeric computertypes_id;
     * values are positive ints (0/empty = "use default", so dropped).
     *
     * @param array<string,mixed> $map
     */
    public static function saveRetentionMap(array $map): bool
    {
        $clean = [];
        foreach ($map as $k => $v) {
            $years = (int) $v;
            if ($k === 'default') {
                $clean['default'] = $years > 0 ? $years : self::DEFAULT_YEARS;
                continue;
            }
            if (ctype_digit((string) $k) && $years > 0) {
                $clean[(string) ((int) $k)] = $years;
            }
        }
        if (!isset($clean['default'])) {
            $clean['default'] = self::DEFAULT_YEARS;
        }
        return Config::set('retention', json_encode($clean));
    }

    /** Retention years for a given computer type (falls back to default). */
    public static function yearsForType(int $computertypesId): int
    {
        $map = self::getRetentionMap();
        if ($computertypesId > 0 && isset($map[(string) $computertypesId]) && (int) $map[(string) $computertypesId] > 0) {
            return (int) $map[(string) $computertypesId];
        }
        return (int) $map['default'];
    }
}
