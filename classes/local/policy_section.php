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
use local_evidencehub\output\policy_frameworks;

/**
 * The validity policy section local_dimensions shows in its Competency hub's panel for the selected competency.
 *
 * Decides what the current user may see of one competency's policy; the hook callback only hands the answer to
 * the hook, so the rule is testable without local_dimensions. The rows are those policies.php shows for the
 * competency ({@see policy_frameworks::summary()}), in its order and wording: the policy in force, whether it is
 * the competency's own, its framework's or the built-in default. Who sees it is decided in the framework's own
 * context, read from the framework's row: local/evidencehub:view (the drawer shows a pair's policy to it) or
 * local/evidencehub:managepolicies (policies.php shows every policy to it); "Edit policy" links to policies.php
 * for the second only, the one page where a policy is changed. The tone values mirror the TONE_* constants of
 * {@see \local_dimensions\hook\competency_panel_sections}, spelled out here because local_dimensions may not be
 * installed and nothing in this class may load one of its classes.
 *
 * The pair the caller hands in is checked against the competency's own row, as policy::require_manage() checks
 * membership: a framework that is not the competency's gives no section, so its context never decides.
 *
 * Three statements per call: the competency's framework, the framework's context and the policy rows of the
 * competency and its framework.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class policy_section {
    /** @var string The section's key within this plugin; the hook prefixes it with the component. */
    public const string KEY = 'policy';

    /** @var string Tone of the source row when the competency sets its own policy. */
    public const string TONE_INFO = 'info';

    /** @var string Tone of the source row when the policy is inherited or the default. */
    public const string TONE_NEUTRAL = 'neutral';

    /**
     * The section of one competency's policy, for the current user.
     *
     * @param int $competencyid The competency.
     * @param int $frameworkid Its framework.
     * @return array|null Null when the user may see nothing: a guest, competencies disabled, a competency that is
     *     gone or not of that framework, a framework that is gone, or neither capability in the framework's context.
     *     Otherwise keys: key, title, rows (list of label, value, tone), linkurl (a moodle_url, or null) and linklabel
     *     (plain text, '' without a link).
     */
    public static function for_competency(int $competencyid, int $frameworkid): ?array {
        global $DB;
        if (!$competencyid || isguestuser() || !api::is_enabled()) {
            return null;
        }
        $owner = $DB->get_field('competency', 'competencyframeworkid', ['id' => $competencyid]);
        if ($owner === false || (int) $owner !== $frameworkid) {
            return null;
        }
        try {
            $context = policy::framework_context($frameworkid);
        } catch (\dml_missing_record_exception $e) {
            return null;
        }
        $canedit = has_capability('local/evidencehub:managepolicies', $context);
        if (!$canedit && !has_capability('local/evidencehub:view', $context)) {
            return null;
        }

        $policy = policy::resolve($competencyid, $frameworkid);
        $summary = policy_frameworks::summary($policy);
        $rows = [];
        // The order and the labels of policies.php (templates/framework_policies.mustache); an empty cell is left out.
        foreach (['kind', 'requireverified', 'minhours', 'alerts', 'gracedays', 'cyclemonths', 'onexpiry'] as $field) {
            if ($summary[$field] !== '') {
                $rows[] = ['label' => self::label($field), 'value' => $summary[$field], 'tone' => ''];
            }
        }
        $rows[] = [
            'label' => get_string('source', 'local_evidencehub'),
            'value' => $summary['source'],
            'tone' => $policy->source === policy::SOURCE_COMPETENCY ? self::TONE_INFO : self::TONE_NEUTRAL,
        ];

        return [
            'key' => self::KEY,
            'title' => get_string('policysection', 'local_evidencehub'),
            'rows' => $rows,
            'linkurl' => $canedit ? new \moodle_url('/local/evidencehub/policies.php', ['frameworkid' => $frameworkid]) : null,
            'linklabel' => $canedit ? get_string('actioneditpolicy', 'local_evidencehub') : '',
        ];
    }

    /**
     * The label policies.php gives a field of the summary.
     *
     * @param string $field A key of policy_frameworks::summary().
     * @return string
     * @throws \coding_exception On a field the section does not show.
     */
    private static function label(string $field): string {
        return match ($field) {
            'kind' => get_string('kind', 'local_evidencehub'),
            'requireverified' => get_string('requireverified', 'local_evidencehub'),
            'minhours' => get_string('minhours', 'local_evidencehub'),
            'alerts' => get_string('alerts', 'local_evidencehub'),
            'gracedays' => get_string('gracedays', 'local_evidencehub'),
            'cyclemonths' => get_string('cyclemonths', 'local_evidencehub'),
            'onexpiry' => get_string('onexpiry', 'local_evidencehub'),
            default => throw new \coding_exception('Unknown policy field: ' . $field),
        };
    }
}
