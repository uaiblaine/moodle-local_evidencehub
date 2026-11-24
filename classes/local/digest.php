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

use core_competency\plan;
use core_competency\template;

/**
 * The weekly digest to mentors: who is a mentor, which workers each follows, and what is not compliant.
 *
 * A mentor holds local/evidencehub:receivealerts and moodle/competency:userevidenceview through roles
 * assigned in a worker's user context ({@see alerts::grants_sql()}); a system or category holder is no
 * mentor. Each mentor's pairs are read through the hub's base set with the mentor's own scope
 * ({@see hub_query::base_sql()}, {@see hub_scope::for_user()}), never with the cron user's rights, so the
 * digest shows each pair's effective status for that mentor: a pair decided by a sensitive evidence reads
 * "Restricted" to a mentor without local/evidencehub:viewsensitive over the worker, and, as on the hub, is
 * always listed, since telling a compliant restricted pair apart would reveal what the cell hides.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class digest {
    /** @var int The hour of Monday, in the site's timezone, at which a new weekly round opens. */
    public const int OPENS_AT_HOUR = 7;

    /** @var int How many workers one statement names. */
    private const int CHUNK = 1000;

    /** @var array Template id => validated filter, or null when the template is not offered; per digest. */
    private array $filters = [];

    /**
     * The weekly round an instant belongs to: the Monday, 07:00 in the site's timezone, that opened it.
     *
     * @param int $now The instant.
     * @return int That Monday, YYYYMMDD.
     */
    public static function round(int $now): int {
        $moment = (new \DateTimeImmutable('@' . $now))->setTimezone(\core_date::get_server_timezone_object());
        $opening = $moment->modify('monday this week')->setTime(self::OPENS_AT_HOUR, 0);
        if ($opening > $moment) {
            $opening = $opening->modify('-7 days');
        }
        return (int) $opening->format('Ymd');
    }

    /**
     * The next mentors after a user id, in id order: one statement over role assignments in user contexts.
     *
     * @param int $after The last mentor id handled; 0 to start.
     * @param int $limit The most mentors to return.
     * @return array List of user ids, ascending.
     */
    public static function mentors(int $after, int $limit): array {
        global $DB;
        [$sql, $params] = self::assignments_sql();
        $sql = "SELECT DISTINCT ra.userid
                  $sql
                   AND ra.userid > :dgafter
              ORDER BY ra.userid";
        $records = $DB->get_records_sql($sql, $params + ['dgafter' => $after], 0, $limit);
        return array_map('intval', array_keys($records));
    }

    /**
     * The workers a mentor follows: a user context where the mentor's roles grant both capabilities, and
     * where has_capability() confirms both (a prevention or prohibition, through any role or override, wins).
     *
     * @param int $mentorid The mentor.
     * @return array List of user ids, ascending.
     */
    public static function workers(int $mentorid): array {
        global $DB;
        [$sql, $params] = self::assignments_sql();
        $sql = "SELECT DISTINCT ctx.instanceid
                  $sql
                   AND ra.userid = :dgmentor
              ORDER BY ctx.instanceid";
        $workers = [];
        foreach ($DB->get_fieldset_sql($sql, $params + ['dgmentor' => $mentorid]) as $workerid) {
            $context = \context_user::instance((int) $workerid, IGNORE_MISSING);
            if (
                $context
                && has_capability('local/evidencehub:receivealerts', $context, $mentorid)
                && has_capability('moodle/competency:userevidenceview', $context, $mentorid)
            ) {
                $workers[] = (int) $workerid;
            }
        }
        return $workers;
    }

    /**
     * The FROM and WHERE of the active users whose roles in one user context grant both capabilities,
     * over ra (the receivealerts assignment) and ctx (its user context).
     *
     * @return array [sql from FROM to the open WHERE, params].
     */
    private static function assignments_sql(): array {
        [$alerts, $alertparams] = alerts::grants_sql('ra', 'local/evidencehub:receivealerts', 'dga');
        [$view, $viewparams] = alerts::grants_sql('rv', 'moodle/competency:userevidenceview', 'dgv');
        $sql = "FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :dgusercontext
                  JOIN {user} u ON u.id = ra.userid AND u.deleted = 0 AND u.suspended = 0 AND u.confirmed = 1
                 WHERE $alerts
                       AND EXISTS (SELECT 1
                                     FROM {role_assignments} rv
                                    WHERE rv.userid = ra.userid AND rv.contextid = ra.contextid AND $view)";
        return [$sql, $alertparams + $viewparams + ['dgusercontext' => CONTEXT_USER]];
    }

    /**
     * What a mentor's digest lists: per worker with something to report, the pairs not compliant with
     * their effective status for the mentor, and the count of compliant pairs.
     *
     * The pairs are those of the templates each worker has an active plan from and the mentor may read
     * (the hub's base set, suspended workers left out); a competency two templates share is counted once.
     *
     * @param int $mentorid The mentor.
     * @param array $workerids The workers the mentor follows.
     * @return array List of records, by worker name: userid, pairs (list of records: competencyid,
     *     shortname, contextid (the framework's), code (effective), expiry (YYYYMMDD or null)), compliant (int).
     */
    public function report(int $mentorid, array $workerids): array {
        global $DB;
        $scope = hub_scope::for_user($mentorid);
        $system = \context_system::instance();
        $workers = [];
        foreach (array_chunk($workerids, self::CHUNK) as $chunk) {
            [$userin, $userparams] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'dgu');
            $templates = $DB->get_records_sql_menu(
                "SELECT DISTINCT t.id, t.contextid
                   FROM {" . plan::TABLE . "} p
                   JOIN {" . template::TABLE . "} t ON t.id = p.templateid
                  WHERE p.userid $userin AND p.status = :dgactive",
                $userparams + ['dgactive' => plan::STATUS_ACTIVE]
            );
            foreach ($templates as $templateid => $contextid) {
                $filter = $this->filter((int) $templateid);
                if ($filter === null || !self::can_read_template((int) $contextid, $mentorid)) {
                    continue;
                }
                [$basesql, $params] = hub_query::base_sql($filter, $scope, $system);
                $sql = "SELECT b.userid, b.competencyid, b.estatus, b.eexpiry, c.shortname, f.contextid
                          FROM ($basesql) b
                          JOIN {competency} c ON c.id = b.competencyid
                          JOIN {competency_framework} f ON f.id = c.competencyframeworkid
                          JOIN {competency_templatecomp} tc ON tc.competencyid = b.competencyid AND tc.templateid = :dgtemplateid
                         WHERE b.userid $userin
                      ORDER BY b.userid, tc.sortorder, tc.id";
                $rs = $DB->get_recordset_sql($sql, $params + $userparams + ['dgtemplateid' => (int) $templateid]);
                foreach ($rs as $record) {
                    $userid = (int) $record->userid;
                    $workers[$userid] ??= ['pairs' => [], 'compliant' => []];
                    $competencyid = (int) $record->competencyid;
                    if ((int) $record->estatus === status::STATUS_VALID) {
                        $workers[$userid]['compliant'][$competencyid] = true;
                    } else {
                        $workers[$userid]['pairs'][$competencyid] ??= (object) [
                            'competencyid' => $competencyid,
                            'shortname' => $record->shortname,
                            'contextid' => (int) $record->contextid,
                            'code' => (int) $record->estatus,
                            'expiry' => hub_query::expiry_day((int) $record->eexpiry),
                        ];
                    }
                }
                $rs->close();
            }
        }
        $workers = array_filter($workers, fn(array $worker): bool => (bool) $worker['pairs']);
        if (!$workers) {
            return [];
        }
        $names = \core_user\fields::for_name()->get_sql('u', true);
        [$userin, $userparams] = $DB->get_in_or_equal(array_keys($workers), SQL_PARAMS_NAMED, 'dgn');
        $users = $DB->get_records_sql(
            "SELECT u.id{$names->selects} FROM {user} u {$names->joins} WHERE u.id $userin ORDER BY u.lastname, u.firstname, u.id",
            $userparams + $names->params
        );
        $report = [];
        foreach ($users as $user) {
            $worker = $workers[(int) $user->id];
            $report[] = (object) [
                'userid' => (int) $user->id,
                'user' => $user,
                'pairs' => array_values($worker['pairs']),
                'compliant' => count($worker['compliant']),
            ];
        }
        return $report;
    }

    /**
     * Whether a mentor may read a template, by core's rule ({@see template::can_read_context()}) for them.
     *
     * @param int $contextid The template's context.
     * @param int $mentorid The mentor.
     * @return bool
     */
    private static function can_read_template(int $contextid, int $mentorid): bool {
        $context = \context::instance_by_id($contextid, IGNORE_MISSING);
        return $context && has_any_capability(
            ['moodle/competency:templateview', 'moodle/competency:templatemanage'],
            $context,
            $mentorid
        );
    }

    /**
     * Send one mentor's digest, when the mentor may receive it and has something to report.
     *
     * @param int $mentorid The mentor.
     * @return bool Whether a message was sent.
     */
    public function send(int $mentorid): bool {
        $mentor = \core_user::get_user($mentorid);
        if (!$mentor || !alerts::reachable($mentor)) {
            return false;
        }
        $workers = self::workers($mentorid);
        if (!$workers) {
            return false;
        }
        $report = $this->report($mentorid, $workers);
        if (!$report) {
            return false;
        }
        return alerts::in_language($mentor, fn(): bool => self::send_report($mentor, $report));
    }

    /**
     * Compose a report in the current language and send it.
     *
     * @param \stdClass $mentor The mentor.
     * @param array $report What report() returned.
     * @return bool Whether core accepted the message.
     */
    private static function send_report(\stdClass $mentor, array $report): bool {
        $intro = get_string('digestintro', 'local_evidencehub');
        $text = [$intro];
        $html = '<p>' . s($intro) . '</p>';
        foreach ($report as $worker) {
            $heading = get_string('digestworker', 'local_evidencehub', (object) [
                'name' => fullname($worker->user),
                'compliant' => $worker->compliant,
            ]);
            $url = new \moodle_url('/local/evidencehub/evidence.php', ['userid' => $worker->userid]);
            $text[] = '';
            $text[] = $heading;
            $html .= '<p><a href="' . s($url->out(false)) . '">' . s($heading) . '</a></p><ul>';
            foreach ($worker->pairs as $pair) {
                $a = (object) [
                    'competency' => alerts::competency_name($pair->shortname, $pair->contextid),
                    'status' => status::effective_label($pair->code),
                    'expiry' => $pair->expiry === null ? null : meta::iso_date($pair->expiry),
                ];
                $line = $a->expiry === null
                    ? get_string('digestpair', 'local_evidencehub', $a)
                    : get_string('digestpairexpiry', 'local_evidencehub', $a);
                $text[] = '- ' . $line;
                $html .= '<li>' . s($line) . '</li>';
            }
            $html .= '</ul>';
        }
        $subject = get_string('digestsubject', 'local_evidencehub');
        return alerts::send(alerts::WEEKLY_DIGEST, $mentor, $subject, implode("\n", $text), $html);
    }

    /**
     * The validated filter of a template, or null when the hub does not offer it.
     *
     * Validated in the system context with the current (cron) user's rights: that only checks that the
     * template exists; report() then requires the mentor to read it, and the pairs are cut by the mentor's scope.
     *
     * @param int $templateid The template.
     * @return hub_filter|null
     */
    private function filter(int $templateid): ?hub_filter {
        if (!array_key_exists($templateid, $this->filters)) {
            try {
                $this->filters[$templateid] = hub_filter::from_values(
                    ['templateid' => $templateid],
                    \context_system::instance()
                );
            } catch (\moodle_exception $e) {
                $this->filters[$templateid] = null;
            }
        }
        return $this->filters[$templateid];
    }
}
