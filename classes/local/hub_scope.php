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

use core_competency\api;

/**
 * Which workers' rows a viewer reaches in the hub, and whose sensitive evidence they may read: the two
 * user-context capability cuts as SQL fragments, computed once per viewer and request.
 *
 * Each fragment is what {@see api::filter_users_with_capability_on_user_context_sql()} returns: "> 0"
 * for everyone, "= -1" for no one, or an IN / NOT IN list. The two carry distinct parameter
 * prefixes, so one statement holds both.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hub_scope {
    /**
     * A scope; use {@see self::for_user()}.
     *
     * @param int $viewerid The viewer; their own sensitive evidence is never restricted to them.
     * @param string $viewsql Who holds moodle/competency:userevidenceview over the worker, to append to a user id.
     * @param array $viewparams Its named parameters.
     * @param string $sensitivesql Who holds local/evidencehub:viewsensitive over the worker, likewise.
     * @param array $sensitiveparams Its named parameters.
     */
    public function __construct(
        /** @var int The viewer. */
        public readonly int $viewerid,
        /** @var string The userevidenceview fragment. */
        public readonly string $viewsql,
        /** @var array Its parameters. */
        public readonly array $viewparams,
        /** @var string The viewsensitive fragment. */
        public readonly string $sensitivesql,
        /** @var array Its parameters. */
        public readonly array $sensitiveparams,
    ) {
    }

    /**
     * The scope of a viewer.
     *
     * @param int|null $userid The viewer; the current user when null.
     * @return self
     */
    public static function for_user(?int $userid = null): self {
        global $USER;
        $userid ??= (int) $USER->id;
        [$viewsql, $viewparams] = api::filter_users_with_capability_on_user_context_sql(
            'moodle/competency:userevidenceview',
            $userid,
            SQL_PARAMS_NAMED,
            'hubview'
        );
        [$sensitivesql, $sensitiveparams] = api::filter_users_with_capability_on_user_context_sql(
            'local/evidencehub:viewsensitive',
            $userid,
            SQL_PARAMS_NAMED,
            'hubsens'
        );
        return new self($userid, $viewsql, $viewparams, $sensitivesql, $sensitiveparams);
    }

    /**
     * Whether the viewer holds viewsensitive over every user: core's "everyone" fragment, with no parameters.
     *
     * Then nothing is restricted to them ({@see pairset::restricted()} needs a worker outside the fragment), so a
     * reader of the pair set may skip the restricted rule. Any other spelling of the fragment, including one core
     * might return in a later version for the same users, answers false and keeps the rule: the safe side.
     *
     * @return bool
     */
    public function sees_every_sensitive(): bool {
        return $this->sensitivesql === '> 0' && $this->sensitiveparams === [];
    }

    /**
     * What the scope reaches, independent of the parameter names a request happened to draw.
     *
     * get_in_or_equal() numbers its placeholders with a counter of the request, so two requests of
     * the same viewer name them differently: the key reads the SQL without names and the values.
     *
     * @return string
     */
    public function key(): string {
        return json_encode([
            $this->viewerid,
            preg_replace('/:\w+/', '?', $this->viewsql),
            array_values($this->viewparams),
            preg_replace('/:\w+/', '?', $this->sensitivesql),
            array_values($this->sensitiveparams),
        ]);
    }
}
