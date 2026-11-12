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

namespace local_evidencehub\output;

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use core_competency\plan;
use core_competency\user_competency;
use core_competency\user_evidence;
use local_evidencehub\local\audit;
use local_evidencehub\local\details;
use local_evidencehub\local\meta;
use local_evidencehub\local\policy;
use local_evidencehub\local\status;
use local_evidencehub\local\status_repository;

/**
 * The inspection drawer of one (worker, competency) pair: the evidence in force, the policy, every
 * linked evidence as a timeline, their audit trail and the actions the viewer may take.
 *
 * The caller checked the access ({@see local_evidencehub_output_fragment_pair()}). Each item follows
 * the sensitive rule ({@see details::is_restricted()}) on its own: an evidence of a sensitive type the
 * viewer may not read shows its type and "Restricted details" only, and its audit rows their date,
 * action and evidence name only.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pair_drawer implements renderable, templatable {
    /** @var string The status row's deciding evidence, while the status covers today. */
    public const string STATE_INFORCE = 'inforce';

    /** @var string Its expiry day is past. */
    public const string STATE_EXPIRED = 'expired';

    /** @var string Its issue day is still ahead. */
    public const string STATE_NOTYETVALID = 'notyetvalid';

    /** @var string Its details wait for a verifier. */
    public const string STATE_AWAITING = 'awaiting';

    /** @var string A verifier refused its details. */
    public const string STATE_REJECTED = 'rejected';

    /** @var string No details were entered for it. */
    public const string STATE_MISSING = 'missing';

    /** @var array The statuses under which the deciding evidence covers today. */
    private const array COVERING = [status::STATUS_VALID, status::STATUS_DUE, status::STATUS_CRITICAL];

    /**
     * The drawer of a pair.
     *
     * @param \context $context The hub's context: it decides the identity fields shown.
     * @param int $userid The worker.
     * @param int $competencyid The competency.
     * @param int $templateid The hub's template, 0 for none; only finds the worker's plan.
     */
    public function __construct(
        /** @var \context The hub's context. */
        private readonly \context $context,
        /** @var int The worker. */
        private readonly int $userid,
        /** @var int The competency. */
        private readonly int $competencyid,
        /** @var int The hub's template, 0 for none. */
        private readonly int $templateid = 0,
    ) {
    }

    /**
     * The human-readable name of an item's state.
     *
     * @param string $state A STATE_* constant.
     * @return string
     * @throws \coding_exception On an unknown state.
     */
    public static function state_label(string $state): string {
        return match ($state) {
            self::STATE_INFORCE => get_string('state_inforce', 'local_evidencehub'),
            self::STATE_EXPIRED => get_string('state_expired', 'local_evidencehub'),
            self::STATE_NOTYETVALID => get_string('state_notyetvalid', 'local_evidencehub'),
            self::STATE_AWAITING => get_string('state_awaiting', 'local_evidencehub'),
            self::STATE_REJECTED => get_string('state_rejected', 'local_evidencehub'),
            self::STATE_MISSING => get_string('state_missing', 'local_evidencehub'),
            default => throw new \coding_exception('Unknown evidence state: ' . $state),
        };
    }

    /**
     * The template context.
     *
     * @param renderer_base $output The renderer.
     * @return array The context of templates/pair_drawer.mustache.
     */
    #[\Override]
    public function export_for_template(renderer_base $output): array {
        global $DB;
        $competency = $DB->get_record(
            'competency',
            ['id' => $this->competencyid],
            'id, shortname, idnumber, competencyframeworkid',
            MUST_EXIST
        );
        $frameworkcontextid = $DB->get_field('competency_framework', 'contextid', ['id' => $competency->competencyframeworkid]);
        $frameworkcontext = \context::instance_by_id((int) $frameworkcontextid);
        $row = $DB->get_record(status_repository::TABLE, ['userid' => $this->userid, 'competencyid' => $this->competencyid]);
        $row = $row ?: null;
        $today = status_repository::site_day(\core\di::get(\core\clock::class)->time());
        $types = $DB->get_records('local_evidencehub_type');
        $policy = policy::resolve($this->competencyid, (int) $competency->competencyframeworkid);

        $items = $this->items($row, $types, $today);
        $deciding = null;
        foreach ($items as $item) {
            if ($row !== null && $row->metaid !== null && $item['metaid'] === (int) $row->metaid) {
                $deciding = $item;
            }
        }
        $pillrestricted = $this->pill_restricted($row, $types);
        $covers = $row !== null && in_array((int) $row->status, self::COVERING, true);
        $canmanage = user_evidence::can_manage_user($this->userid);

        $data = [
            'userid' => $this->userid,
            'competencyid' => $this->competencyid,
            'worker' => $this->worker(),
            // Plain spellings throughout: the template's double stashes escape them once.
            'competencyname' => format_string($competency->shortname, true, ['context' => $frameworkcontext, 'escape' => false]),
            'competencyidnumber' => (string) $competency->idnumber,
            'status' => $this->pill($row, $pillrestricted, $covers, $today),
            'hasproficiency' => false,
            'canrecycle' => $canmanage,
            'recyclefrom' => $this->recycle_source($items),
            'hasplan' => false,
            'planurl' => '',
            'hasdeciding' => $deciding !== null,
            // Whether a restricted deciding evidence covers today is its dates' business: one neutral heading.
            'decidinginforce' => $covers && !$pillrestricted,
            'deciding' => $deciding === null ? [] : [$this->deciding($deciding, $policy)],
            'policy' => $this->policy_sentence($policy),
            'policysource' => policy::source_label($policy->source),
            'hashistory' => !empty($items),
            'history' => array_values($items),
            'audit' => $this->audit($items),
            'footer' => $this->footer($row, $pillrestricted),
        ];
        $data['hasaudit'] = !empty($data['audit']);
        if (user_competency::can_read_user($this->userid)) {
            $data['hasproficiency'] = true;
            $data['proficiency'] = $this->proficiency();
        }
        $planid = $this->planid();
        if ($planid !== null) {
            $data['hasplan'] = true;
            $data['planurl'] = (new \moodle_url('/admin/tool/lp/user_competency_in_plan.php', [
                'userid' => $this->userid,
                'competencyid' => $this->competencyid,
                'planid' => $planid,
            ]))->out(false);
        }
        return $data;
    }

    /**
     * The worker's name and the identity fields the hub's context shows.
     *
     * @return array name, identity (list of [label, value]).
     */
    private function worker(): array {
        global $DB;
        $identity = \core_user\fields::get_identity_fields($this->context, false);
        $fields = \core_user\fields::for_identity($this->context, false)->with_name()->get_sql('u', true);
        $user = $DB->get_record_sql(
            "SELECT u.id{$fields->selects} FROM {user} u {$fields->joins} WHERE u.id = :drawerworkerid",
            $fields->params + ['drawerworkerid' => $this->userid],
            MUST_EXIST
        );
        $shown = [];
        foreach ($identity as $field) {
            $value = (string) ($user->$field ?? '');
            if ($value !== '') {
                $shown[] = ['label' => \core_user\fields::get_display_name($field), 'value' => $value];
            }
        }
        return [
            'name' => fullname($user, has_capability('moodle/site:viewfullnames', $this->context)),
            'hasidentity' => !empty($shown),
            'identity' => $shown,
        ];
    }

    /**
     * Whether the pair's status reads "Restricted" to the viewer: decided by an evidence of a
     * sensitive type, as the hub reads it ({@see \local_evidencehub\local\hub_query::base_sql()}).
     *
     * The stored flag and the deciding evidence's current type both count, so the drawer never shows
     * more than the hub's grid for the same pair.
     *
     * @param \stdClass|null $row The status row.
     * @param array $types The evidence types, by id.
     * @return bool
     */
    private function pill_restricted(?\stdClass $row, array $types): bool {
        global $DB;
        if ($row === null || $row->metaid === null || details::can_see_sensitive($this->userid)) {
            return false;
        }
        $typeid = $DB->get_field(details::TABLE, 'typeid', ['id' => $row->metaid]);
        $type = $typeid === false ? null : ($types[(int) $typeid] ?? null);
        return !empty($row->issensitive) || ($type && $type->issensitive);
    }

    /**
     * The effective status pill, with the days left while the status covers today.
     *
     * @param \stdClass|null $row The status row.
     * @param bool $restricted Whether the pill reads "Restricted".
     * @param bool $covers Whether the status covers today.
     * @param int $today Today, YYYYMMDD in the site's timezone.
     * @return array label, badgeclass, daysleft (empty for none).
     */
    private function pill(?\stdClass $row, bool $restricted, bool $covers, int $today): array {
        $code = match (true) {
            $restricted => status::EFFECTIVE_RESTRICTED,
            $row === null => status::EFFECTIVE_MISSING,
            default => (int) $row->status,
        };
        $daysleft = '';
        if (!$restricted && $covers && $row->effectiveexpiry !== null) {
            $daysleft = get_string('drawerdaysleft', 'local_evidencehub', meta::days_between($today, (int) $row->effectiveexpiry));
        }
        return [
            'code' => $code,
            'label' => status::effective_label($code),
            'badgeclass' => status::effective_badge_class($code),
            'daysleft' => $daysleft,
        ];
    }

    /**
     * Every core evidence of the worker linked to the competency, newest issue first, as items.
     *
     * Ties keep the highest evidence id first; evidence without details has no issue day and comes
     * last. The gap to the next newer interval is worked out between neighbours whose dates the
     * viewer may read: never next to a restricted item.
     *
     * @param \stdClass|null $row The status row.
     * @param array $types The evidence types, by id.
     * @param int $today Today, YYYYMMDD in the site's timezone.
     * @return array List of item contexts, in timeline order.
     */
    private function items(?\stdClass $row, array $types, int $today): array {
        global $DB;
        $sql = "SELECT ue.id, ue.name, m.id AS metaid, m.typeid, m.issuedate, m.expirydate, m.noexpiry, m.issuer,
                       m.hours, m.verifystatus, m.verifiedby, m.timeverified
                  FROM {competency_userevidencecomp} uec
                  JOIN {competency_userevidence} ue ON ue.id = uec.userevidenceid
             LEFT JOIN {" . details::TABLE . "} m ON m.userevidenceid = ue.id
                 WHERE ue.userid = :userid AND uec.competencyid = :competencyid";
        $records = array_values($DB->get_records_sql($sql, ['userid' => $this->userid, 'competencyid' => $this->competencyid]));
        usort($records, [self::class, 'compare']);

        $usercontext = \context_user::instance($this->userid);
        $canedit = user_evidence::can_manage_user($this->userid);
        $canverify = details::can_verify($this->userid);
        $decidingid = ($row !== null && $row->metaid !== null) ? (int) $row->metaid : null;
        $covers = $row !== null && in_array((int) $row->status, self::COVERING, true);

        $items = [];
        // The issue day of the next newer item, when the viewer may read it.
        $newerissue = null;
        foreach ($records as $record) {
            $item = [
                'userevidenceid' => (int) $record->id,
                'name' => format_string($record->name, true, ['context' => $usercontext, 'escape' => false]),
                'viewurl' => (new \moodle_url('/admin/tool/lp/user_evidence.php', ['id' => $record->id]))->out(false),
                'metaid' => null,
                'hasdetails' => $record->metaid !== null,
                'restricted' => false,
                'typename' => '',
                'gap' => '',
                'canverify' => false,
                // An evidence without details opens the same form to complete them.
                'canedit' => $canedit,
            ];
            $meta = null;
            if ($record->metaid !== null) {
                $meta = (object) [
                    'id' => (int) $record->metaid,
                    'userid' => $this->userid,
                    'typeid' => (int) $record->typeid,
                    'issuedate' => (int) $record->issuedate,
                    'expirydate' => $record->expirydate === null ? null : (int) $record->expirydate,
                    'verifystatus' => (int) $record->verifystatus,
                ];
                $type = $types[$meta->typeid] ?? null;
                $item['metaid'] = $meta->id;
                $item['typename'] = $type
                    ? format_string($type->name, true, ['context' => \context_system::instance(), 'escape' => false])
                    : '';
                $item['restricted'] = details::is_restricted($meta, $type);
                // Who may not read the details may neither change nor decide on them.
                $item['canedit'] = $canedit && !$item['restricted'];
                $item['canverify'] = $canverify && !$item['restricted'];
            }
            if (!$item['restricted']) {
                $state = self::state($meta, $covers && $meta !== null && $meta->id === $decidingid, $today);
                $item['state'] = $state;
                $item['statelabel'] = self::state_label($state);
                if ($meta !== null) {
                    $item += $this->details($record);
                }
            }
            // The gap sits on the older of two neighbours, and only when both dates may be read.
            if ($newerissue !== null && $meta !== null && !$item['restricted']) {
                $gap = self::gap($meta->expirydate, $newerissue);
                if ($gap > 0) {
                    $item['gap'] = get_string('drawergap', 'local_evidencehub', $gap);
                }
            }
            $newerissue = ($meta !== null && !$item['restricted']) ? $meta->issuedate : null;
            $items[] = $item;
        }
        return $items;
    }

    /**
     * The timeline order of two linked evidence records: newest issue first, then the highest id;
     * without details last.
     *
     * @param \stdClass $a A record.
     * @param \stdClass $b Another record.
     * @return int
     */
    private static function compare(\stdClass $a, \stdClass $b): int {
        $aissue = $a->metaid === null ? 0 : (int) $a->issuedate;
        $bissue = $b->metaid === null ? 0 : (int) $b->issuedate;
        return [$bissue, (int) $b->id] <=> [$aissue, (int) $a->id];
    }

    /**
     * Days between an interval's expiry and the next newer interval's issue that neither covers.
     *
     * Touching intervals (the newer issued the day after the older expires) have no gap; an older
     * one that never expires, or overlapping ones, neither.
     *
     * @param int|null $expiry The older interval's expiry, YYYYMMDD, or null for never.
     * @param int $newerissue The newer interval's issue day.
     * @return int The days of the gap; 0 or less for none.
     */
    private static function gap(?int $expiry, int $newerissue): int {
        if ($expiry === null) {
            return 0;
        }
        return meta::days_between($expiry, $newerissue) - 1;
    }

    /**
     * The state of one linked evidence.
     *
     * @param \stdClass|null $meta Its details, or null.
     * @param bool $decides Whether it is the status row's deciding evidence while the status covers today.
     * @param int $today Today, YYYYMMDD in the site's timezone.
     * @return string A STATE_* constant.
     */
    private static function state(?\stdClass $meta, bool $decides, int $today): string {
        return match (true) {
            $meta === null => self::STATE_MISSING,
            $decides => self::STATE_INFORCE,
            $meta->verifystatus === meta::VERIFY_REJECTED => self::STATE_REJECTED,
            $meta->issuedate > $today => self::STATE_NOTYETVALID,
            $meta->expirydate !== null && $meta->expirydate < $today => self::STATE_EXPIRED,
            $meta->verifystatus === meta::VERIFY_PENDING => self::STATE_AWAITING,
            // Verified and covering today, though another evidence decides.
            default => self::STATE_INFORCE,
        };
    }

    /**
     * The readable details of one evidence: interval, issuer, hours and verification.
     *
     * @param \stdClass $record The evidence record with its details.
     * @return array issuedate, expirydate, noexpiry, interval, issuer, hours, rawhours, verifylabel, verifybadge,
     *     decidedby; never the authenticity code, which only the deciding section reads.
     */
    private function details(\stdClass $record): array {
        $issue = meta::iso_date((int) $record->issuedate);
        $expiry = $record->expirydate === null ? null : meta::iso_date((int) $record->expirydate);
        $state = (int) $record->verifystatus;
        $details = [
            'issuedate' => $issue,
            'expirydate' => $expiry ?? '',
            'noexpiry' => $expiry === null,
            'interval' => get_string('drawerinterval', 'local_evidencehub', (object) [
                'issue' => $issue,
                'expiry' => $expiry ?? get_string('noexpiry', 'local_evidencehub'),
            ]),
            'issuer' => (string) $record->issuer,
            'hours' => $record->hours === null ? '' : format_float((float) $record->hours, 2, true, true),
            'rawhours' => $record->hours === null ? null : (float) $record->hours,
            'verifylabel' => meta::verify_label($state),
            'verifybadge' => match ($state) {
                meta::VERIFY_VERIFIED => 'bg-success text-white',
                meta::VERIFY_REJECTED => 'bg-danger text-white',
                default => 'bg-warning text-dark',
            },
            'decidedby' => '',
        ];
        if ($state !== meta::VERIFY_PENDING && $record->verifiedby) {
            $verifier = \core_user::get_user((int) $record->verifiedby);
            $details['decidedby'] = get_string('decidedby', 'local_evidencehub', (object) [
                'name' => $verifier ? fullname($verifier) : '',
                'time' => userdate((int) $record->timeverified, get_string('strftimedatetimeshort', 'core_langconfig')),
            ]);
        }
        return $details;
    }

    /**
     * The deciding evidence's section: its item with the policy's minimum hours, its files, and the
     * authenticity code only to whoever may manage or verify the worker's evidence, as on {@see evidence_page}.
     *
     * @param array $item The deciding item.
     * @param \stdClass $policy The resolved policy.
     * @return array The item context, with hoursline, authcode, hasfiles and files.
     */
    private function deciding(array $item, \stdClass $policy): array {
        global $DB;
        $item['files'] = [];
        $item['hasfiles'] = false;
        $item['authcode'] = '';
        if ($item['restricted'] || !$item['hasdetails']) {
            return $item;
        }
        $usercontext = \context_user::instance($this->userid);
        $canmanage = user_evidence::can_manage_user($this->userid);
        if ($canmanage || has_capability('local/evidencehub:verify', $usercontext)) {
            $item['authcode'] = (string) $DB->get_field(details::TABLE, 'authcode', ['id' => $item['metaid']]);
        }
        $item['hoursline'] = $item['hours'];
        if ($policy->minhours !== null && $item['rawhours'] !== null) {
            $item['hoursline'] = get_string('drawerhoursmin', 'local_evidencehub', (object) [
                'hours' => $item['hours'],
                'minhours' => format_float((float) $policy->minhours, 2, true, true),
            ]);
        }
        // Core serves these files with its own checks (core_competency_pluginfile()).
        $files = get_file_storage()->get_area_files(
            $usercontext->id,
            'core_competency',
            'userevidence',
            $item['userevidenceid'],
            'filename',
            false
        );
        foreach ($files as $file) {
            $item['files'][] = [
                'filename' => $file->get_filename(),
                'url' => \moodle_url::make_pluginfile_url(
                    $usercontext->id,
                    'core_competency',
                    'userevidence',
                    $item['userevidenceid'],
                    $file->get_filepath(),
                    $file->get_filename(),
                    true
                )->out(false),
            ];
        }
        $item['hasfiles'] = !empty($item['files']);
        return $item;
    }

    /**
     * Which evidence a recycling copies its type and issuer from: the newest the viewer may read.
     *
     * The evidence form checks it again: owner, competency and restriction.
     *
     * @param array $items The timeline.
     * @return int A meta id, or 0 for none.
     */
    private function recycle_source(array $items): int {
        foreach ($items as $item) {
            if ($item['metaid'] !== null && !$item['restricted']) {
                return $item['metaid'];
            }
        }
        return 0;
    }

    /**
     * The resolved policy in one sentence.
     *
     * @param \stdClass $policy The resolved policy.
     * @return string
     */
    private function policy_sentence(\stdClass $policy): string {
        $kind = (int) $policy->kind;
        $parts = [policy::kind_label($kind)];
        if ($policy->requireverified) {
            $parts[] = get_string('policy_verifiedonly', 'local_evidencehub');
        } else {
            $parts[] = get_string('policy_unverifiedcounts', 'local_evidencehub');
        }
        if ($policy->minhours !== null) {
            $parts[] = get_string('policy_minhours', 'local_evidencehub', format_float((float) $policy->minhours, 2, true, true));
        }
        $parts[] = get_string('policy_alerts', 'local_evidencehub', (object) [
            'alert1' => (int) $policy->alert1,
            'alert2' => (int) $policy->alert2,
        ]);
        if ($kind === policy::KIND_TECHNICAL) {
            $parts[] = get_string('policy_grace', 'local_evidencehub', (int) $policy->gracedays);
        }
        if ($kind !== policy::KIND_REGULATORY) {
            $parts[] = (int) $policy->cyclemonths > 0
                ? get_string('policy_cycle', 'local_evidencehub', (int) $policy->cyclemonths)
                : get_string('policy_cyclenever', 'local_evidencehub');
        }
        if ($kind === policy::KIND_REGULATORY) {
            $parts[] = get_string('policy_blocking', 'local_evidencehub');
        } else {
            $parts[] = get_string('policy_notblocking', 'local_evidencehub');
        }
        return implode(' · ', $parts);
    }

    /**
     * The audit rows of the timeline's evidence, newest first.
     *
     * A restricted item's rows show their date, action and evidence name only. A note shows only to the
     * worker and to holders of local/evidencehub:verify in the worker's context, as on {@see evidence_page}.
     *
     * @param array $items The timeline.
     * @return array List of [date, action, actor, evidence, note].
     */
    private function audit(array $items): array {
        global $DB, $USER;
        if (!$items) {
            return [];
        }
        $byevidence = array_column($items, null, 'userevidenceid');
        [$insql, $params] = $DB->get_in_or_equal(array_keys($byevidence), SQL_PARAMS_NAMED, 'draweraudit');
        $records = $DB->get_records_select(
            'local_evidencehub_audit',
            "userevidenceid $insql",
            $params,
            'timecreated DESC, id DESC'
        );
        $actorids = array_unique(array_map(fn(\stdClass $record): int => (int) $record->userid, $records));
        $actors = $actorids ? \core_user::get_users_by_id($actorids) : [];
        $notes = $this->userid === (int) $USER->id
            || has_capability('local/evidencehub:verify', \context_user::instance($this->userid));
        $viewfullnames = has_capability('moodle/site:viewfullnames', $this->context);

        $rows = [];
        foreach ($records as $record) {
            $item = $byevidence[(int) $record->userevidenceid];
            $restricted = $item['restricted'];
            $actor = $actors[(int) $record->userid] ?? null;
            $rows[] = [
                'date' => userdate((int) $record->timecreated, get_string('strftimedatetimeshort', 'core_langconfig')),
                'action' => audit::label($record->action),
                'actor' => (!$restricted && $actor) ? fullname($actor, $viewfullnames) : '',
                'evidence' => $item['name'],
                'note' => (!$restricted && $notes) ? (string) $record->note : '',
            ];
        }
        return $rows;
    }

    /**
     * The footer: when the status was computed and its next change.
     *
     * A restricted pill hides the next change: it is worked out from the deciding evidence's dates.
     *
     * @param \stdClass|null $row The status row.
     * @param bool $restricted Whether the pill reads "Restricted".
     * @return string
     */
    private function footer(?\stdClass $row, bool $restricted): string {
        if ($row === null) {
            return get_string('drawernostatus', 'local_evidencehub');
        }
        $computed = get_string(
            'drawercomputed',
            'local_evidencehub',
            userdate((int) $row->timemodified, get_string('strftimedatetimeshort', 'core_langconfig'))
        );
        if ($restricted) {
            return $computed;
        }
        $next = $row->nexttransition === null
            ? get_string('drawernonextchange', 'local_evidencehub')
            : get_string(
                'drawernextchange',
                'local_evidencehub',
                meta::iso_date(status_repository::site_day((int) $row->nexttransition))
            );
        return $computed . ' · ' . $next;
    }

    /**
     * Core's proficiency of the pair: proficient, not proficient, or not rated.
     *
     * @return array label, badgeclass.
     */
    private function proficiency(): array {
        global $DB;
        $proficiency = $DB->get_field(
            user_competency::TABLE,
            'proficiency',
            ['userid' => $this->userid, 'competencyid' => $this->competencyid]
        );
        return match (true) {
            $proficiency === false || $proficiency === null => [
                'label' => get_string('proficiency_none', 'local_evidencehub'),
                'badgeclass' => 'bg-secondary text-dark',
            ],
            (int) $proficiency === 1 => [
                'label' => get_string('proficiency_yes', 'local_evidencehub'),
                'badgeclass' => 'bg-success text-white',
            ],
            default => [
                'label' => get_string('proficiency_no', 'local_evidencehub'),
                'badgeclass' => 'bg-warning text-dark',
            ],
        };
    }

    /**
     * The worker's plan from the hub's template that the viewer may read, preferring an active one.
     *
     * The link opens the competency inside that plan, so the template must require the competency;
     * tool_lp's page checks the plan again, the drawer only avoids offering a link it would refuse.
     *
     * @return int|null The plan id, or null without a template, when the template does not require the
     *     competency, or without a plan from it the viewer may read.
     */
    private function planid(): ?int {
        global $DB;
        if (!$this->templateid) {
            return null;
        }
        $required = $DB->record_exists(
            'competency_templatecomp',
            ['templateid' => $this->templateid, 'competencyid' => $this->competencyid]
        );
        if (!$required) {
            return null;
        }
        $readable = array_filter(
            plan::get_records(['userid' => $this->userid, 'templateid' => $this->templateid], 'id', 'DESC'),
            fn(plan $plan): bool => $plan->can_read()
        );
        foreach ($readable as $plan) {
            if ((int) $plan->get('status') === plan::STATUS_ACTIVE) {
                return (int) $plan->get('id');
            }
        }
        $plan = reset($readable);
        return $plan ? (int) $plan->get('id') : null;
    }
}
