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
 * Where settings.php hangs the plugin's admin entries.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class admin_tree {
    /** @var string The core category every competency admin page sits in. */
    public const string CORE_CATEGORY = 'competencies';

    /** @var string The category the companion plugin local_dimensions registers under it. */
    public const string COMPANION_CATEGORY = 'local_dimensions';

    /**
     * The category for the plugin's entries: the companion's when it exists in the tree, else core's.
     *
     * Neither plugin requires the other, so the category is looked up, never assumed. It exists only when
     * local_dimensions' settings.php has already run: admin/settings/plugins.php loads local plugins'
     * settings sorted by their localised display name, not by component, so the order depends on the
     * language ("Competency Dimensions" sorts before "Evidence hub" in English). When the category is not
     * there (loaded later, or competencies disabled, when local_dimensions registers none) the entries go
     * to 'competencies' and stay reachable.
     *
     * @param \admin_root $root The admin tree being built.
     * @return string The name of the category to add the entries to.
     */
    public static function parent_category(\admin_root $root): string {
        return $root->locate(self::COMPANION_CATEGORY) instanceof \admin_category
            ? self::COMPANION_CATEGORY
            : self::CORE_CATEGORY;
    }
}
