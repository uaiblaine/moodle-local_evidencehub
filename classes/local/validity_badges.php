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
use core_competency\user_evidence;

/**
 * The validity pills local_dimensions shows beside a worker's competencies.
 *
 * Pure answer to "which pills may the current user see for this worker's competencies": the hook callback only
 * hands it to the hook, so the rule is testable without local_dimensions. The tone values are the family's
 * tone tokens, spelled out here so that nothing in this class loads a local_dimensions class.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class validity_badges {
    /** @var string Tone of a compliant pair. */
    public const string TONE_SUCCESS = 'success';

    /** @var string Tone of a pair due soon. */
    public const string TONE_WARNING = 'warning';

    /** @var string Tone of a critical or expired pair. */
    public const string TONE_DANGER = 'danger';

    /** @var string Tone of a pair awaiting verification. */
    public const string TONE_INFO = 'info';

    /** @var string Tone of a missing or restricted pair. */
    public const string TONE_NEUTRAL = 'neutral';

    /**
     * The pills of a worker's competencies, for the current user.
     *
     * Nothing for a guest, with competencies disabled, or when the current user cannot read the worker's
     * evidence. A pair decided by a sensitive evidence reads "Restricted", without its date, to a viewer who
     * may not see the worker's sensitive evidence (the worker always may). A pair with no status row reads
     * "no evidence" only under an explicit policy of the competency or of its framework: under the built-in
     * default a site that has not set validity up would show a pill on every competency.
     *
     * @param int $userid The worker.
     * @param array $competencyids The competency ids on the page.
     * @return array Competency id => ['label' => plain text, 'tone' => a TONE_* value]; only asked ids.
     */
    public static function for_user(int $userid, array $competencyids): array {
        global $DB;
        $competencyids = array_values(array_unique(array_map('intval', $competencyids)));
        // A guest is refused as evidence.php refuses one, even where the site lets the guest role read evidence.
        if (!$competencyids || isguestuser() || !api::is_enabled()) {
            return [];
        }
        // A deleted or unknown worker has no user context to check against.
        if (!$DB->record_exists('user', ['id' => $userid, 'deleted' => 0]) || !user_evidence::can_read_user($userid)) {
            return [];
        }

        // Restricted when the stored flag or the deciding evidence's type says so, as the drawer and the dossier
        // read it: a type made sensitive after the last recompute must not show its pair's date.
        [$insql, $params] = $DB->get_in_or_equal($competencyids, SQL_PARAMS_NAMED);
        $sql = "SELECT s.competencyid, s.status, s.effectiveexpiry,
                       CASE WHEN s.issensitive = 1 OR t.issensitive = 1 THEN 1 ELSE 0 END AS issensitive
                  FROM {" . status_repository::TABLE . "} s
             LEFT JOIN {" . details::TABLE . "} m ON m.id = s.metaid
             LEFT JOIN {local_evidencehub_type} t ON t.id = m.typeid
                 WHERE s.userid = :userid AND s.competencyid $insql";
        $statuses = $DB->get_records_sql($sql, $params + ['userid' => $userid]);
        $restricted = !details::can_see_sensitive($userid);

        $badges = [];
        $unmatched = [];
        foreach ($competencyids as $competencyid) {
            if (!isset($statuses[$competencyid])) {
                $unmatched[] = $competencyid;
                continue;
            }
            $row = $statuses[$competencyid];
            if ($row->issensitive && $restricted) {
                $restrictedname = get_string('validityrestricted', 'local_evidencehub');
                $badges[$competencyid] = [
                    'label' => get_string('validitybadge', 'local_evidencehub', $restrictedname),
                    'tone' => self::TONE_NEUTRAL,
                ];
                continue;
            }
            $badges[$competencyid] = self::for_status((int) $row->status, $row->effectiveexpiry);
        }

        foreach (self::with_explicit_policy($unmatched) as $competencyid) {
            $badges[$competencyid] = [
                'label' => get_string('validitybadge', 'local_evidencehub', status::label(null)),
                'tone' => self::TONE_NEUTRAL,
            ];
        }
        return $badges;
    }

    /**
     * The pill of a stored status.
     *
     * @param int $status A status::STATUS_* constant.
     * @param string|int|null $expiry The effective expiry, YYYYMMDD, or null.
     * @return array The label and tone.
     */
    private static function for_status(int $status, $expiry): array {
        $name = status::label($status);
        $dated = in_array(
            $status,
            [status::STATUS_VALID, status::STATUS_DUE, status::STATUS_CRITICAL, status::STATUS_EXPIRED],
            true
        );
        if ($dated && $expiry !== null) {
            $label = get_string('validitybadgeexpiry', 'local_evidencehub', (object) [
                'status' => $name,
                'expiry' => meta::iso_date((int) $expiry),
            ]);
        } else {
            $label = get_string('validitybadge', 'local_evidencehub', $name);
        }
        $tone = match ($status) {
            status::STATUS_VALID => self::TONE_SUCCESS,
            status::STATUS_DUE => self::TONE_WARNING,
            status::STATUS_CRITICAL, status::STATUS_EXPIRED => self::TONE_DANGER,
            default => self::TONE_INFO,
        };
        return ['label' => $label, 'tone' => $tone];
    }

    /**
     * The competencies, among these, that an explicit policy (their own row or their framework's) covers.
     *
     * @param array $competencyids Competency ids with no status row.
     * @return array The ids with an explicit policy.
     */
    private static function with_explicit_policy(array $competencyids): array {
        global $DB;
        if (!$competencyids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($competencyids, SQL_PARAMS_NAMED);
        $frameworks = $DB->get_records_select('competency', "id $insql", $params, '', 'id, competencyframeworkid');
        if (!$frameworks) {
            return [];
        }
        $frameworkids = array_unique(array_map(fn(\stdClass $c): int => (int) $c->competencyframeworkid, $frameworks));
        [$fwsql, $fwparams] = $DB->get_in_or_equal($frameworkids, SQL_PARAMS_NAMED, 'fw');
        $rows = [];
        foreach ($DB->get_records_select(policy::TABLE, "frameworkid $fwsql", $fwparams) as $row) {
            $rows[(int) $row->frameworkid][] = $row;
        }
        $covered = [];
        foreach ($frameworks as $competency) {
            $framework = $rows[(int) $competency->competencyframeworkid] ?? [];
            if (policy::resolve_from($framework, (int) $competency->id)->source !== policy::SOURCE_DEFAULT) {
                $covered[] = (int) $competency->id;
            }
        }
        return $covered;
    }
}
