<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_evidencehub\local;

/**
 * The reader's settings of the hub's pairs report, in one user preference for the whole site.
 *
 * It holds catalogue keys and widths, never column definitions: the report reads it in initialise() and adds
 * only the catalogue's columns it names ({@see \local_evidencehub\reportbuilder\local\systemreports\hub_pairs}),
 * so a forged or stale key matches nothing. Beside the grid it keeps whether suspended accounts are listed
 * (decision 5, so a download follows it) and the last template the reader opened in each hub context
 * (decision 2), the most recently chosen first and at most MAX_TEMPLATES of them.
 *
 * Everything is cleaned on write and again on read: a stored value of any other shape reads as no choice, so
 * a broken preference leaves the report at its defaults rather than breaking it. A user preference is the
 * database's, so every node reads the same.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reportpreference {
    /** @var string The user preference's name. */
    public const string NAME = 'local_evidencehub_report';

    /** @var int The most keys either list keeps: the catalogue and the identity fields hold far fewer. */
    public const int MAX_KEYS = 40;

    /** @var int The narrowest width kept, in pixels; a smaller one is raised to it. */
    public const int MIN_WIDTH = 48;

    /** @var int The widest width kept, in pixels; a larger one is lowered to it. */
    public const int MAX_WIDTH = 1600;

    /** @var int The most hub contexts whose last template is remembered. */
    public const int MAX_TEMPLATES = 20;

    /**
     * Whether a string has the shape of a column key: a plain word (status, expiry, ...) or an identity column
     * ("identity:email"). The shape only: whether it names a column is the report's call.
     *
     * @param string $key The key.
     * @return bool
     */
    public static function valid_key(string $key): bool {
        // Anchored at the string's start and end: a "$" would also match before a trailing newline.
        return preg_match('/\A(?:[a-z]{1,30}|identity:[a-z0-9_]{1,40})\z/', $key) === 1;
    }

    /**
     * The reader's settings as stored, cleaned.
     *
     * @param int|null $userid The reader; the current user when null.
     * @return array 'columns' => the chosen keys or null for the default set; 'widths' => key => pixels;
     *     'suspended' => bool; 'templates' => hub context id => template id, the most recent first.
     */
    public static function read(?int $userid = null): array {
        global $USER;
        $raw = get_user_preferences(self::NAME, null, $userid ?? (int) $USER->id);
        $stored = is_string($raw) ? json_decode($raw, true) : null;
        return self::clean(is_array($stored) ? $stored : []);
    }

    /**
     * Store the reader's grid and, when given, the suspended flag; the remembered templates stay.
     *
     * @param array|null $columns The chosen keys, or null for the default set.
     * @param array $widths Key => pixels; empty for the default widths.
     * @param bool|null $suspended Whether suspended accounts are listed; null leaves it as stored.
     * @param int|null $userid The reader; the current user when null.
     * @return array What {@see self::read()} now returns.
     */
    public static function write_grid(?array $columns, array $widths, ?bool $suspended, ?int $userid = null): array {
        $stored = self::read($userid);
        $stored['columns'] = $columns;
        $stored['widths'] = $widths;
        if ($suspended !== null) {
            $stored['suspended'] = $suspended;
        }
        return self::store($stored, $userid);
    }

    /**
     * Forget the column choice and the widths, keeping the suspended flag and the remembered templates.
     *
     * @param int|null $userid The reader; the current user when null.
     * @return array What {@see self::read()} now returns.
     */
    public static function use_default(?int $userid = null): array {
        $stored = self::read($userid);
        $stored['columns'] = null;
        $stored['widths'] = [];
        return self::store($stored, $userid);
    }

    /**
     * Remember the template a reader opened in a hub context, writing only when it changes what is stored.
     *
     * @param int $contextid The hub context.
     * @param int $templateid The template.
     * @param int|null $userid The reader; the current user when null.
     * @return bool Whether the preference was written.
     */
    public static function remember_template(int $contextid, int $templateid, ?int $userid = null): bool {
        $stored = self::read($userid);
        if (($stored['templates'][$contextid] ?? null) === $templateid || $contextid <= 0 || $templateid <= 0) {
            return false;
        }
        unset($stored['templates'][$contextid]);
        // The newest first; clean() keeps the first MAX_TEMPLATES.
        $stored['templates'] = [$contextid => $templateid] + $stored['templates'];
        self::store($stored, $userid);
        return true;
    }

    /**
     * The last template a reader opened in a hub context.
     *
     * @param int $contextid The hub context.
     * @param int|null $userid The reader; the current user when null.
     * @return int|null The template id, or null when none is remembered.
     */
    public static function last_template(int $contextid, ?int $userid = null): ?int {
        return self::read($userid)['templates'][$contextid] ?? null;
    }

    /**
     * The settings a client draws the Columns menu, the widths and the accounts choice from, in the shape of
     * {@see \local_evidencehub\external\preference_structure}. The remembered templates are the server's.
     *
     * @param array $stored As {@see self::read()} returns them.
     * @return array usedefault, columns (keys), widths (key, width), suspended.
     */
    public static function export(array $stored): array {
        $widths = [];
        foreach ($stored['widths'] as $key => $width) {
            $widths[] = ['key' => (string) $key, 'width' => (int) $width];
        }
        return [
            'usedefault' => $stored['columns'] === null,
            'columns' => $stored['columns'] ?? [],
            'widths' => $widths,
            'suspended' => (bool) $stored['suspended'],
        ];
    }

    /**
     * Store settings cleaned; settings that are all defaults remove the preference, so no row is left behind.
     *
     * @param array $settings As {@see self::read()} returns them, or raw.
     * @param int|null $userid The reader; the current user when null.
     * @return array The settings stored, cleaned.
     */
    private static function store(array $settings, ?int $userid): array {
        global $USER;
        $userid ??= (int) $USER->id;
        $clean = self::clean($settings);
        if ($clean['columns'] === null && $clean['widths'] === [] && !$clean['suspended'] && $clean['templates'] === []) {
            unset_user_preference(self::NAME, $userid);
        } else {
            $stored = $clean;
            // A JSON object, whatever the keys: an empty list and an empty map would decode alike otherwise.
            $stored['widths'] = (object) $clean['widths'];
            $stored['templates'] = (object) $clean['templates'];
            set_user_preference(self::NAME, json_encode($stored), $userid);
        }
        return $clean;
    }

    /**
     * Settings of any shape, cleaned: keys of a key's shape once each, widths in whole pixels within the bounds,
     * the flag as a boolean, and templates as positive ids, at most MAX_TEMPLATES of them.
     *
     * @param array $settings Raw settings: columns, widths, suspended, templates; anything else is dropped.
     * @return array As {@see self::read()} returns them.
     */
    public static function clean(array $settings): array {
        $columns = null;
        if (array_key_exists('columns', $settings) && is_array($settings['columns'])) {
            $columns = [];
            foreach ($settings['columns'] as $key) {
                if (is_string($key) && self::valid_key($key) && !in_array($key, $columns, true)) {
                    $columns[] = $key;
                }
            }
            $columns = array_slice($columns, 0, self::MAX_KEYS);
        }

        $widths = [];
        foreach ((is_array($settings['widths'] ?? null) ? $settings['widths'] : []) as $key => $width) {
            if (!is_string($key) || !self::valid_key($key) || !is_numeric($width)) {
                continue;
            }
            $widths[$key] = max(self::MIN_WIDTH, min(self::MAX_WIDTH, (int) round((float) $width)));
            if (count($widths) >= self::MAX_KEYS) {
                break;
            }
        }

        $templates = [];
        foreach ((is_array($settings['templates'] ?? null) ? $settings['templates'] : []) as $contextid => $templateid) {
            $contextid = filter_var($contextid, FILTER_VALIDATE_INT);
            $templateid = is_int($templateid) ? $templateid : filter_var($templateid, FILTER_VALIDATE_INT);
            if ($contextid === false || $templateid === false || $contextid <= 0 || $templateid <= 0) {
                continue;
            }
            $templates[$contextid] = $templateid;
            if (count($templates) >= self::MAX_TEMPLATES) {
                break;
            }
        }

        return [
            'columns' => $columns,
            'widths' => $widths,
            'suspended' => ($settings['suspended'] ?? false) === true,
            'templates' => $templates,
        ];
    }
}
