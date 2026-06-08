<?php

/**
 * UX Customizer - Color Palette module
 *
 * Adds custom palette(s) as SELECTABLE GLPI themes — it does NOT override the
 * user's chosen theme. GLPI 11 discovers themes as SCSS files in
 * GLPI_THEMES_DIR (verified in Glpi\UI\ThemeManager); any file there appears
 * in My Settings (preference.php) and the Setup > General default.
 *
 * Generates a light theme and (optionally) a matching dark theme (with
 * `$is-dark: true;`). Both share the brand primary/accent.
 *
 * There is no plugin API to register a theme in code — it is purely file-based.
 *
 * @license   GPL-3.0-or-later
 */

namespace GlpiPlugin\Uxcustomizer;

class ColorPalette
{
    /**
     * Default colors. The values happen to match the TC Transcontinental brand
     * (verified from tctranscontinental.com), but the default palette NAME is the
     * generic "Custom" — this is a public-catalog plugin, so it must not ship an
     * org-specific default theme name. Admins rename + recolor as they like.
     *   primary    #008fd5  brand blue — buttons (.btn-CTA), active tabs, body links (30×)
     *   accent     #199ad9  hover/active highlight — menu .is-active, social icons
     *   body_bg    #f5f5f5  page background (body) — light theme only
     *   sidebar_bg #000000  dark surfaces — footer / menu blocks
     *   sidebar_fg #ffffff  text on the dark nav
     */
    public const DEFAULT_COLORS = [
        'primary'    => '#008fd5',
        'accent'     => '#199ad9',
        'body_bg'    => '#f5f5f5',
        'sidebar_bg' => '#000000',
        'sidebar_fg' => '#ffffff',
    ];

    public const DEFAULT_NAME = 'Custom';

    /** Body/surface base for the dark variant ($is-dark handles the rest). */
    public const DARK_BODY_BG = '#15171c';

    /** glpi_users.palette is char(20): theme keys MUST be <= 20 chars. */
    public const KEY_MAXLEN = 20;

    /** Resolve GLPI's themes directory (where custom palette SCSS files live). */
    public static function themesDir(): string
    {
        if (defined('GLPI_THEMES_DIR')) {
            return GLPI_THEMES_DIR;
        }
        if (defined('GLPI_VAR_DIR')) {
            return GLPI_VAR_DIR . '/_themes';
        }
        return GLPI_ROOT . '/files/_themes';
    }

    /**
     * Current stored palette:
     *   ['name'=>..,'key'=>light,'dark_key'=>..,'dark'=>bool,'colors'=>[...]]
     * Colors are merged over defaults so all keys are always present.
     */
    public static function get(): array
    {
        $raw     = Config::get('palette');
        $decoded = $raw !== null ? json_decode($raw, true) : null;
        $stored  = is_array($decoded) ? $decoded : [];

        $name = isset($stored['name']) && is_string($stored['name']) && $stored['name'] !== ''
            ? $stored['name'] : self::DEFAULT_NAME;
        $colors = is_array($stored['colors'] ?? null) ? $stored['colors'] : [];
        $colors = array_merge(self::DEFAULT_COLORS, array_intersect_key($colors, self::DEFAULT_COLORS));

        return [
            'name'     => $name,
            'key'      => $stored['key']      ?? self::keyFromName($name),
            'dark_key' => $stored['dark_key'] ?? self::darkKeyFromName($name),
            'dark'     => array_key_exists('dark', $stored) ? (bool) $stored['dark'] : true,
            'colors'   => $colors,
        ];
    }

    /**
     * Save the palette: sanitize, write the light SCSS theme (and the dark one
     * if $dark), store the config. Stale files (from a renamed key or a
     * toggled-off dark variant) are removed.
     *
     * @return array{ok:bool,key:string,dark_key:string,error:?string}
     */
    public static function save(string $name, array $colors, bool $dark = true): array
    {
        $name    = trim($name) !== '' ? trim($name) : self::DEFAULT_NAME;
        $key     = self::keyFromName($name);
        $darkKey = self::darkKeyFromName($name);

        $clean = [];
        foreach (self::DEFAULT_COLORS as $k => $default) {
            $val = $colors[$k] ?? $default;
            $clean[$k] = self::isHexColor($val) ? strtolower($val) : $default;
        }

        // Remove any previously-written files whose keys differ from the new ones.
        $prev = self::get();
        foreach ([$prev['key'], $prev['dark_key']] as $oldKey) {
            if (!empty($oldKey) && $oldKey !== $key && $oldKey !== $darkKey) {
                self::deleteThemeFile($oldKey);
            }
        }

        // Light theme — always written.
        $written = self::writeThemeFile($key, self::generateScss($key, $clean, false));
        if ($written !== true) {
            return ['ok' => false, 'key' => $key, 'dark_key' => $darkKey, 'error' => $written];
        }

        // Dark theme — written when enabled, removed when not.
        if ($dark) {
            $written = self::writeThemeFile($darkKey, self::generateScss($darkKey, $clean, true));
            if ($written !== true) {
                return ['ok' => false, 'key' => $key, 'dark_key' => $darkKey, 'error' => $written];
            }
        } else {
            self::deleteThemeFile($darkKey);
        }

        Config::set('palette', json_encode([
            'name'     => $name,
            'key'      => $key,
            'dark_key' => $darkKey,
            'dark'     => $dark,
            'colors'   => $clean,
        ]));
        return ['ok' => true, 'key' => $key, 'dark_key' => $darkKey, 'error' => null];
    }

    /** Reset colors to defaults and rewrite both theme files. */
    public static function reset(): array
    {
        return self::save(self::DEFAULT_NAME, self::DEFAULT_COLORS, true);
    }

    /** Remove the managed theme files (e.g. when the module is disabled). */
    public static function removeThemeFile(): bool
    {
        $stored = self::get();
        $ok = true;
        foreach ([$stored['key'], $stored['dark_key']] as $k) {
            if (!empty($k)) {
                $ok = self::deleteThemeFile($k) && $ok;
            }
        }
        return $ok;
    }

    // ── key helpers ────────────────────────────────────────────────────────

    /**
     * "TC Transcontinental" → "tc-transcontinental" (theme key = SCSS filename
     * stem, and the value GLPI stores in the char(20) glpi_users.palette).
     * Slugify + hard-cap at 20. No prefix (it pushed brand names over 20).
     */
    public static function keyFromName(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim((string) $slug, '-');
        if ($slug === '') {
            $slug = 'custom';
        }
        if (strlen($slug) > self::KEY_MAXLEN) {
            $slug = rtrim(substr($slug, 0, self::KEY_MAXLEN), '-');
        }
        return $slug;
    }

    /**
     * Dark sibling key: base shortened so "-dark" still fits char(20), and
     * always distinct from the light key. e.g. "tc-transcontinental" →
     * "tc-transcontine-dark" (20).
     */
    public static function darkKeyFromName(string $name): string
    {
        $base = self::keyFromName($name);
        $base = rtrim(substr($base, 0, self::KEY_MAXLEN - 5), '-'); // room for "-dark"
        if ($base === '') {
            $base = 'custom';
        }
        return $base . '-dark';
    }

    public static function isHexColor(string $value): bool
    {
        return (bool) preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value);
    }

    /** "#0054a6" → "0, 84, 166" for *-rgb CSS variables. */
    public static function hexToRgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return sprintf('%d, %d, %d',
            hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
    }

    // ── SCSS generation ──────────────────────────────────────────────────────

    /**
     * Build a palette SCSS file in GLPI 11's expected format. When $isDark, the
     * file is marked `$is-dark: true;` (GLPI then applies its dark-mode rules to
     * surfaces) and we only brand the accents + nav; otherwise we also set the
     * light page background.
     */
    public static function generateScss(string $key, array $c, bool $isDark): string
    {
        $primary    = $c['primary'];
        $primaryRgb = self::hexToRgb($c['primary']);
        $accent     = $c['accent'];
        $accentRgb  = self::hexToRgb($c['accent']);
        $sidebarBg  = $c['sidebar_bg'];
        $sidebarFg  = $c['sidebar_fg'];

        // Page background: light value for the light theme; a dark neutral for
        // the dark theme (GLPI's $is-dark restyles cards/text on top of this).
        $bodyBg = $isDark ? self::DARK_BODY_BG : $c['body_bg'];

        $darkFlag = $isDark ? "\$is-dark: true;\n\n" : '';
        $variant  = $isDark ? 'dark' : 'light';

        return <<<SCSS
// Generated by the UX Customizer plugin — do not edit by hand.
// TC Transcontinental-style palette ({$variant}). Selectable in My Settings.
{$darkFlag}:root[data-glpi-theme="{$key}"] {
    // ── Brand primary: buttons, active nav, focus rings, badges ──
    --tblr-primary: {$primary};
    --tblr-primary-rgb: {$primaryRgb};

    // ── Links ──
    --tblr-link-color: {$primary};
    --tblr-link-color-rgb: {$primaryRgb};
    --tblr-link-hover-color: {$accent};

    // ── Accent / active highlight ──
    --tblr-active-bg: rgba({$accentRgb}, 0.12);

    // ── Page background ──
    --tblr-body-bg: {$bodyBg};

    // ── GLPI left navigation ──
    --glpi-mainmenu-bg: {$sidebarBg};
    --glpi-mainmenu-fg: {$sidebarFg};

    // ── Preview swatches (theme picker) ──
    --glpi-palette-color-1: {$primary};
    --glpi-palette-color-2: {$accent};
    --glpi-palette-color-3: {$sidebarBg};
    --glpi-palette-color-4: {$bodyBg};
}

// Sidebar link colours + active highlight (scoped to this theme only)
:root[data-glpi-theme="{$key}"] .navbar-vertical .nav-link {
    color: {$sidebarFg};
}
:root[data-glpi-theme="{$key}"] .navbar-vertical .nav-link:hover,
:root[data-glpi-theme="{$key}"] .navbar-vertical .nav-link.active {
    color: {$accent};
}

SCSS;
    }

    /**
     * Write a theme SCSS file. Returns true, or an error string for the UI.
     *
     * @return true|string
     */
    private static function writeThemeFile(string $key, string $scss)
    {
        $dir = self::themesDir();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return sprintf('Themes directory could not be created: %s', $dir);
        }
        if (!is_writable($dir)) {
            return sprintf('Themes directory is not writable: %s', $dir);
        }

        $path = $dir . '/' . $key . '.scss';
        if (@file_put_contents($path, $scss) === false) {
            return sprintf('Could not write theme file: %s', $path);
        }
        return true;
    }

    private static function deleteThemeFile(string $key): bool
    {
        $path = self::themesDir() . '/' . $key . '.scss';
        if (is_file($path)) {
            return @unlink($path);
        }
        return true;
    }
}
