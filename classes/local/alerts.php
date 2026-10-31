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

use local_evidencehub\event\validity_status_changed;
use local_evidencehub\task\send_verification_request;

/**
 * The immediate alerts: a worker told that their evidence of a competency is due or expired, and the
 * verifiers told that an evidence awaits them; and the rules every alert of the plugin shares (who may
 * receive one, who holds a capability through a role in a worker's user context, the recipient's language).
 *
 * Only a role assigned in the worker's user context counts (the mentor pattern, tool_cohortroles): a
 * system or category assignment would alert every site manager about every worker.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class alerts {
    /** @var string The worker's pair entered due or critical. */
    public const string VALIDITY_DUE = 'validitydue';

    /** @var string The worker's pair entered expired. */
    public const string VALIDITY_EXPIRED = 'validityexpired';

    /** @var string An evidence entered the pending state on a competency whose policy requires verification. */
    public const string VERIFICATION_REQUESTED = 'verificationrequested';

    /** @var string The weekly digest to a mentor ({@see digest}). */
    public const string WEEKLY_DIGEST = 'weeklydigest';

    /**
     * The provider that alerts a status change, or null when the change alerts nobody.
     *
     * Only a real change into an alerting status alerts; due to critical alerts again, as a new threshold.
     *
     * @param int|null $from The previous status, null on a first status.
     * @param int $to The new status.
     * @return string|null VALIDITY_DUE, VALIDITY_EXPIRED or null.
     */
    public static function provider(?int $from, int $to): ?string {
        if ($from === $to) {
            return null;
        }
        return match ($to) {
            status::STATUS_DUE, status::STATUS_CRITICAL => self::VALIDITY_DUE,
            status::STATUS_EXPIRED => self::VALIDITY_EXPIRED,
            default => null,
        };
    }

    /**
     * Tell the worker that their pair entered due, critical or expired.
     *
     * Called after the status row committed ({@see \local_evidencehub\observer::validity_status_changed()}),
     * never from inside the recomputation's transaction. The worker is always told about their own pair,
     * whoever caused the change. External observers run after the outermost commit, so two changes of one
     * pair in one transaction arrive together: the alert goes out only while the pair's stored status is
     * still the event's, and for due or critical with the expiry the event announced.
     *
     * @param validity_status_changed $event The change.
     * @return bool Whether a message was sent.
     */
    public static function validity_changed(validity_status_changed $event): bool {
        global $DB;
        $from = $event->other['from'] === null ? null : (int) $event->other['from'];
        $to = (int) $event->other['to'];
        $provider = self::provider($from, $to);
        if ($provider === null) {
            return false;
        }
        $worker = \core_user::get_user((int) $event->relateduserid);
        if (!$worker || !self::reachable($worker)) {
            return false;
        }
        $sql = "SELECT c.id, c.shortname, f.contextid
                  FROM {competency} c
                  JOIN {competency_framework} f ON f.id = c.competencyframeworkid
                 WHERE c.id = :competencyid";
        $competency = $DB->get_record_sql($sql, ['competencyid' => (int) $event->other['competencyid']]);
        if (!$competency) {
            return false;
        }
        // The row as the change wrote it, and as it stands now: a later change may have moved it since.
        $written = $event->get_record_snapshot(status_repository::TABLE, (int) $event->objectid);
        $current = $DB->get_record(status_repository::TABLE, ['id' => (int) $event->objectid]);
        if (!$written || !$current || (int) $current->status !== $to) {
            return false;
        }
        $day = fn(\stdClass $row): ?int => $row->effectiveexpiry === null ? null : (int) $row->effectiveexpiry;
        if ($to !== status::STATUS_EXPIRED && $day($current) !== $day($written)) {
            return false;
        }
        $expiry = $day($current) === null ? null : meta::iso_date($day($current));
        $url = new \moodle_url('/local/evidencehub/evidence.php', ['userid' => (int) $worker->id]);
        return self::in_language($worker, function () use ($worker, $competency, $expiry, $to, $provider, $url): bool {
            $a = (object) [
                'competency' => self::competency_name($competency->shortname, (int) $competency->contextid),
                'expiry' => $expiry ?? get_string('noexpiry', 'local_evidencehub'),
            ];
            // A literal key per status, never a built one.
            [$subject, $body] = match ($to) {
                status::STATUS_DUE => [
                    get_string('alertduesubject', 'local_evidencehub', $a),
                    get_string('alertduebody', 'local_evidencehub', $a),
                ],
                status::STATUS_CRITICAL => [
                    get_string('alertcriticalsubject', 'local_evidencehub', $a),
                    get_string('alertcriticalbody', 'local_evidencehub', $a),
                ],
                default => [
                    get_string('alertexpiredsubject', 'local_evidencehub', $a),
                    get_string('alertexpiredbody', 'local_evidencehub', $a),
                ],
            };
            return self::send($provider, $worker, $subject, $body, '<p>' . s($body) . '</p>', $url);
        });
    }

    /**
     * Queue the verification request of an evidence whose details entered the pending state.
     *
     * Queued, not sent: the plugin's own form updates the core evidence (which resets a decided row) and
     * then saves the details, inside one transaction and before it links the competency. The task runs
     * after the commit, sees the links, and core keeps one queued task per evidence, so the two entries
     * into pending of one save make one request.
     *
     * @param int $metaid The meta row.
     * @return void
     */
    public static function request_verification(int $metaid): void {
        $task = new send_verification_request();
        $task->set_custom_data(['metaid' => $metaid]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Tell the verifiers of a worker that an evidence awaits verification.
     *
     * Only while the details are pending and one of the competencies the evidence is linked to has a
     * policy that requires verification. The recipients hold local/evidencehub:verify through a role
     * assigned in the worker's user context ({@see self::user_context_holders()}) and may read the
     * evidence; the worker never; for an evidence of a sensitive type only those who also hold
     * local/evidencehub:viewsensitive there, since nobody else may decide on it or learn of it.
     *
     * A recipient whose message fails is traced and skipped: the others are still told, and the task that
     * calls this completes, so a retry never sends the same request twice to those already told.
     *
     * @param int $metaid The meta row.
     * @param callable|null $send What sends one message, with send()'s parameters; send() when null (a test seam).
     * @return int How many messages were sent.
     */
    public static function verification_requested(int $metaid, ?callable $send = null): int {
        global $DB;
        $sql = "SELECT m.id, m.userid, m.verifystatus, t.issensitive, ue.name AS evidencename
                  FROM {" . details::TABLE . "} m
                  JOIN {competency_userevidence} ue ON ue.id = m.userevidenceid AND ue.userid = m.userid
                  JOIN {local_evidencehub_type} t ON t.id = m.typeid
                 WHERE m.id = :metaid";
        $meta = $DB->get_record_sql($sql, ['metaid' => $metaid]);
        if (!$meta || (int) $meta->verifystatus !== meta::VERIFY_PENDING) {
            return 0;
        }
        $competencies = self::requiring_verification($metaid);
        if (!$competencies) {
            return 0;
        }
        $worker = \core_user::get_user((int) $meta->userid);
        if (!$worker || $worker->deleted) {
            return 0;
        }
        $context = \context_user::instance((int) $worker->id);
        $url = new \moodle_url('/local/evidencehub/evidence.php', ['userid' => (int) $worker->id]);
        $sent = 0;
        foreach (self::user_context_holders('local/evidencehub:verify', $context) as $recipient) {
            if ((int) $recipient->id === (int) $worker->id) {
                continue;
            }
            if (!has_capability('moodle/competency:userevidenceview', $context, $recipient)) {
                continue;
            }
            if (!empty($meta->issensitive) && !has_capability('local/evidencehub:viewsensitive', $context, $recipient)) {
                continue;
            }
            try {
                $sent += (int) self::in_language(
                    $recipient,
                    fn(): bool => self::send_verification($recipient, $worker, $meta, $competencies, $url, $send)
                );
            } catch (\Throwable $e) {
                mtrace("The verification request of evidence details $metaid to user {$recipient->id} failed: "
                    . $e->getMessage());
            }
        }
        return $sent;
    }

    /**
     * Compose and send one verification request, in the current language.
     *
     * @param \stdClass $recipient The verifier.
     * @param \stdClass $worker The worker.
     * @param \stdClass $meta The meta row with evidencename.
     * @param array $competencies The competencies requiring verification, as requiring_verification() lists them.
     * @param \moodle_url $url The worker's evidence page.
     * @param callable|null $send What sends the message, with send()'s parameters; send() when null.
     * @return bool Whether core accepted the message.
     */
    private static function send_verification(
        \stdClass $recipient,
        \stdClass $worker,
        \stdClass $meta,
        array $competencies,
        \moodle_url $url,
        ?callable $send = null
    ): bool {
        $send ??= self::send(...);
        $context = \context_user::instance((int) $worker->id);
        $a = (object) [
            'worker' => fullname($worker),
            'evidence' => format_string($meta->evidencename, true, ['context' => $context, 'escape' => false]),
            'competencies' => implode(', ', array_map(
                fn(\stdClass $competency): string => self::competency_name($competency->shortname, $competency->contextid),
                $competencies
            )),
        ];
        $subject = get_string('alertverificationsubject', 'local_evidencehub', $a);
        $body = get_string('alertverificationbody', 'local_evidencehub', $a);
        return $send(self::VERIFICATION_REQUESTED, $recipient, $subject, $body, '<p>' . s($body) . '</p>', $url);
    }

    /**
     * The competencies an evidence is linked to whose policy requires verified evidence, by name.
     *
     * @param int $metaid The meta row.
     * @return array List of records: id, shortname, frameworkid, contextid (the framework's), as ints but shortname.
     */
    private static function requiring_verification(int $metaid): array {
        global $DB;
        $sql = "SELECT c.id, c.shortname, c.competencyframeworkid AS frameworkid, f.contextid
                  FROM {" . details::TABLE . "} m
                  JOIN {competency_userevidencecomp} uec ON uec.userevidenceid = m.userevidenceid
                  JOIN {competency} c ON c.id = uec.competencyid
                  JOIN {competency_framework} f ON f.id = c.competencyframeworkid
                 WHERE m.id = :metaid
              ORDER BY c.shortname, c.id";
        $competencies = [];
        foreach ($DB->get_records_sql($sql, ['metaid' => $metaid]) as $record) {
            if (policy::resolve((int) $record->id, (int) $record->frameworkid)->requireverified) {
                $competencies[] = (object) [
                    'id' => (int) $record->id,
                    'shortname' => $record->shortname,
                    'frameworkid' => (int) $record->frameworkid,
                    'contextid' => (int) $record->contextid,
                ];
            }
        }
        return $competencies;
    }

    /**
     * The active users holding a capability through a role assigned in a worker's user context.
     *
     * A role assigned in the system or a category never counts here, even when it grants the
     * capability over the worker. The SQL keeps the roles that allow the capability and do not
     * prohibit it in their definition or in the user context; has_capability() then confirms each
     * candidate, so a prohibition through another role still wins.
     *
     * @param string $capability The capability.
     * @param \context_user $context The worker's user context.
     * @return array List of user records, by id.
     */
    public static function user_context_holders(string $capability, \context_user $context): array {
        global $DB;
        [$grants, $params] = self::grants_sql('ra', $capability, 'uch');
        $sql = "SELECT DISTINCT ra.userid
                  FROM {role_assignments} ra
                  JOIN {user} u ON u.id = ra.userid
                 WHERE ra.contextid = :uchcontextid AND u.deleted = 0 AND u.suspended = 0 AND u.confirmed = 1
                       AND $grants
              ORDER BY ra.userid";
        $holders = [];
        foreach ($DB->get_fieldset_sql($sql, $params + ['uchcontextid' => $context->id]) as $userid) {
            $user = \core_user::get_user((int) $userid);
            if ($user && has_capability($capability, $context, $user)) {
                $holders[] = $user;
            }
        }
        return $holders;
    }

    /**
     * The condition that a role assignment's role allows a capability and does not prohibit it, in the
     * role's definition (system context) or in the assignment's own context.
     *
     * @param string $alias The alias of the {role_assignments} row.
     * @param string $capability The capability.
     * @param string $prefix A prefix for the subqueries' aliases and parameters, unique per statement.
     * @return array [sql, named params].
     */
    public static function grants_sql(string $alias, string $capability, string $prefix): array {
        $sql = "EXISTS (SELECT 1
                          FROM {role_capabilities} {$prefix}a
                         WHERE {$prefix}a.roleid = {$alias}.roleid AND {$prefix}a.capability = :{$prefix}acap
                               AND {$prefix}a.permission = :{$prefix}allow
                               AND ({$prefix}a.contextid = :{$prefix}asys OR {$prefix}a.contextid = {$alias}.contextid))
                AND NOT EXISTS (SELECT 1
                                  FROM {role_capabilities} {$prefix}p
                                 WHERE {$prefix}p.roleid = {$alias}.roleid AND {$prefix}p.capability = :{$prefix}pcap
                                       AND {$prefix}p.permission = :{$prefix}prohibit
                                       AND ({$prefix}p.contextid = :{$prefix}psys OR {$prefix}p.contextid = {$alias}.contextid))";
        $system = \context_system::instance()->id;
        return [$sql, [
            $prefix . 'acap' => $capability,
            $prefix . 'allow' => CAP_ALLOW,
            $prefix . 'asys' => $system,
            $prefix . 'pcap' => $capability,
            $prefix . 'prohibit' => CAP_PROHIBIT,
            $prefix . 'psys' => $system,
        ]];
    }

    /**
     * Whether a user may receive an alert: not deleted, not suspended, confirmed.
     *
     * @param \stdClass $user The user record.
     * @return bool
     */
    public static function reachable(\stdClass $user): bool {
        return empty($user->deleted) && empty($user->suspended) && !empty($user->confirmed);
    }

    /**
     * Run a composition in a user's language, restoring the current one afterwards.
     *
     * @param \stdClass $user The recipient.
     * @param callable $compose The composition; its return value is returned.
     * @return mixed What the composition returned.
     */
    public static function in_language(\stdClass $user, callable $compose): mixed {
        global $CFG;
        $previous = force_current_language(!empty($user->lang) ? $user->lang : $CFG->lang);
        try {
            return $compose();
        } finally {
            force_current_language($previous);
        }
    }

    /**
     * A competency's short name as a plain spelling, filtered in its framework's context.
     *
     * @param string $shortname The short name.
     * @param int $contextid The framework's context.
     * @return string
     */
    public static function competency_name(string $shortname, int $contextid): string {
        return format_string($shortname, true, ['context' => \context::instance_by_id($contextid), 'escape' => false]);
    }

    /**
     * Send one notification from the no-reply user.
     *
     * @param string $provider The provider, a constant of this class.
     * @param \stdClass $recipient The recipient.
     * @param string $subject The subject.
     * @param string $body The plain body.
     * @param string $html The HTML body, every value in it escaped.
     * @param \moodle_url|null $url The page the notification links to.
     * @return bool Whether core accepted the message.
     */
    public static function send(
        string $provider,
        \stdClass $recipient,
        string $subject,
        string $body,
        string $html,
        ?\moodle_url $url = null
    ): bool {
        $message = new \core\message\message();
        $message->courseid = SITEID;
        $message->component = 'local_evidencehub';
        $message->name = $provider;
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $recipient;
        $message->notification = 1;
        $message->subject = $subject;
        $message->fullmessage = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = $html;
        $message->smallmessage = $subject;
        if ($url !== null) {
            $message->contexturl = $url;
            $message->contexturlname = get_string('alertcontexturlname', 'local_evidencehub');
        }
        return message_send($message) !== false;
    }
}
