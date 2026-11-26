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

use core_competency\user_evidence;
use local_evidencehub\event\evidence_details_updated;
use local_evidencehub\event\evidence_rejected;
use local_evidencehub\event\evidence_verified;

/**
 * The validity details of a core user evidence: the one API every page, form and observer calls.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class details {
    /** @var string The table of the details. */
    public const string TABLE = 'local_evidencehub_meta';

    /** @var int Length of local_evidencehub_meta.issuer. */
    public const int ISSUER_MAXLENGTH = 255;

    /** @var int Length of local_evidencehub_meta.authcode. */
    public const int AUTHCODE_MAXLENGTH = 100;

    /** @var int Seconds a writer waits for another writer of the same meta row. */
    private const int LOCK_TIMEOUT = 5;

    /**
     * The details of one core evidence.
     *
     * @param int $userevidenceid The competency_userevidence id.
     * @return \stdClass|null The meta row, or null when none was entered.
     */
    public static function get(int $userevidenceid): ?\stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['userevidenceid' => $userevidenceid]) ?: null;
    }

    /**
     * What is wrong with submitted details, field by field.
     *
     * The form shows these on its fields and save() refuses on the first one, so both read the
     * same rules.
     *
     * @param \stdClass $data typeid, issuedate and expirydate (YYYYMMDD, expirydate null for
     *     none), noexpiry, issuer, authcode and hours (null for none).
     * @return array Field name => string id in local_evidencehub; empty when the details are fine.
     */
    public static function problems(\stdClass $data): array {
        global $DB;
        $problems = [];
        $type = $DB->get_record('local_evidencehub_type', ['id' => (int) ($data->typeid ?? 0)]);
        if (!$type) {
            $problems['typeid'] = 'errorunknowntype';
        }
        $issuedate = (int) ($data->issuedate ?? 0);
        if (!meta::is_valid_date($issuedate)) {
            $problems['issuedate'] = 'errorissuedate';
        }
        $expirydate = self::optional_int($data->expirydate ?? null);
        if (!empty($data->noexpiry)) {
            if ($type && $type->requireexpiry) {
                $problems['noexpiry'] = 'errornoexpiry';
            }
        } else if ($expirydate === null) {
            if ($type && !$type->defaultvalidity) {
                $problems['expirydate'] = 'errorexpiryrequired';
            }
        } else if (!meta::is_valid_date($expirydate)) {
            $problems['expirydate'] = 'errorexpirydate';
        } else if (!isset($problems['issuedate']) && $expirydate < $issuedate) {
            $problems['expirydate'] = 'errorexpirybeforeissue';
        }
        $authcode = trim((string) ($data->authcode ?? ''));
        if ($type && $type->requireauthcode && $authcode === '') {
            $problems['authcode'] = 'errorauthcoderequired';
        } else if (\core_text::strlen($authcode) > self::AUTHCODE_MAXLENGTH) {
            $problems['authcode'] = 'errorauthcodetoolong';
        }
        if (\core_text::strlen(trim((string) ($data->issuer ?? ''))) > self::ISSUER_MAXLENGTH) {
            $problems['issuer'] = 'errorissuertoolong';
        }
        $hours = $data->hours ?? null;
        if ($hours !== null && $hours !== '' && (float) $hours < 0) {
            $problems['hours'] = 'errorhours';
        }
        return $problems;
    }

    /**
     * Write the details of one core evidence, inserting them or replacing the stored ones.
     *
     * @param user_evidence $ue The core evidence.
     * @param \stdClass $data The details, as problems() reads them.
     * @return \stdClass The meta row as stored.
     * @throws \required_capability_exception When the current user cannot manage the evidence.
     * @throws \moodle_exception When the details break a rule of their type, when the stored details
     *     are of a sensitive type the actor may not read, or when another writer holds the row.
     */
    public static function save(user_evidence $ue, \stdClass $data): \stdClass {
        global $DB, $USER;
        if (!$ue->can_manage()) {
            throw new \required_capability_exception(
                $ue->get_context(),
                'moodle/competency:userevidencemanage',
                'nopermissions',
                ''
            );
        }
        $problems = self::problems($data);
        if ($problems) {
            throw new \moodle_exception(reset($problems), 'local_evidencehub');
        }

        $type = $DB->get_record('local_evidencehub_type', ['id' => (int) $data->typeid], '*', MUST_EXIST);
        $issuedate = (int) $data->issuedate;
        $noexpiry = !empty($data->noexpiry);
        $expirydate = $noexpiry ? null : self::optional_int($data->expirydate ?? null);
        if (!$noexpiry && $expirydate === null) {
            $expirydate = meta::add_months($issuedate, (int) $type->defaultvalidity);
        }
        $hours = $data->hours ?? null;
        $fields = [
            'typeid' => (int) $type->id,
            'issuedate' => $issuedate,
            'expirydate' => $expirydate,
            'noexpiry' => (int) $noexpiry,
            'issuer' => self::optional_text($data->issuer ?? null),
            'authcode' => self::optional_text($data->authcode ?? null),
            'hours' => ($hours === null || $hours === '') ? null : round((float) $hours, 2),
            'timemodified' => time(),
        ];

        $actorid = (int) $USER->id;
        $existing = self::get((int) $ue->get('id'));
        if ($existing === null) {
            $transaction = $DB->start_delegated_transaction();
            $meta = (object) ($fields + [
                'userevidenceid' => (int) $ue->get('id'),
                'userid' => (int) $ue->get('userid'),
                'verifystatus' => meta::VERIFY_PENDING,
                'timecreated' => $fields['timemodified'],
            ]);
            $meta->id = $DB->insert_record(self::TABLE, $meta);
            self::write_audit($meta, $actorid, audit::ACTION_CREATED);
            $transaction->allow_commit();
        } else {
            self::locked((int) $existing->id, function () use ($existing, $fields, $actorid): void {
                global $DB;
                $transaction = $DB->start_delegated_transaction();
                $fresh = $DB->get_record(self::TABLE, ['id' => $existing->id], '*', MUST_EXIST);
                // Saving over details the actor may not read would let them see and unmask them.
                if (self::is_restricted($fresh)) {
                    $transaction->allow_commit();
                    throw new \moodle_exception('errorrestricted', 'local_evidencehub');
                }
                $DB->update_record(self::TABLE, (object) (['id' => (int) $fresh->id] + $fields));
                self::write_audit($fresh, $actorid, audit::ACTION_DETAILS);
                // The rule judges the row as it now is: a change to a sensitive type is decided on that type.
                $saved = (object) ($fields + (array) $fresh);
                self::reset_row($saved, $actorid);
                $transaction->allow_commit();
            });
        }

        // The stored row, so the event's snapshot carries every column.
        $stored = self::get((int) $ue->get('id'));
        evidence_details_updated::create_from_meta($stored)->trigger();
        return $stored;
    }

    /**
     * Send a decided evidence back to pending after its details changed, unless the change came
     * from someone entitled to decide on it ({@see self::may_decide()}).
     *
     * The owner resets even when they hold local/evidencehub:verify: otherwise a worker verified
     * once could edit the details freely afterwards, walking around the self-verification rule. So does
     * a verifier who may not read the evidence or its sensitive type: keeping a decision is deciding.
     *
     * @param \stdClass $meta The meta row; changed in place when it resets.
     * @param int $actorid Who changed the details.
     * @return bool Whether the details went back to pending.
     */
    public static function apply_edit_rule(\stdClass $meta, int $actorid): bool {
        $fresh = self::locked((int) $meta->id, function () use ($meta, $actorid): \stdClass {
            global $DB;
            $transaction = $DB->start_delegated_transaction();
            $fresh = $DB->get_record(self::TABLE, ['id' => $meta->id], '*', MUST_EXIST);
            self::reset_row($fresh, $actorid);
            $transaction->allow_commit();
            return $fresh;
        });
        $reset = (int) $meta->verifystatus !== (int) $fresh->verifystatus;
        foreach (['verifystatus', 'verifiedby', 'timeverified', 'verifynote', 'timemodified'] as $column) {
            $meta->$column = $fresh->$column;
        }
        return $reset;
    }

    /**
     * The reset rule on a row read under its lock; writes only the decision columns.
     *
     * The caller holds the row's lock (save() and apply_edit_rule() take it around their write), so
     * this never takes it again.
     *
     * @param \stdClass $meta The fresh meta row; changed in place when it resets.
     * @param int $actorid Who changed the details.
     * @return bool Whether the details went back to pending.
     */
    private static function reset_row(\stdClass $meta, int $actorid): bool {
        global $DB;
        if ((int) $meta->verifystatus === meta::VERIFY_PENDING) {
            return false;
        }
        if (self::may_decide($meta, $actorid)) {
            return false;
        }
        $decision = [
            'verifystatus' => meta::VERIFY_PENDING,
            'verifiedby' => null,
            'timeverified' => null,
            'verifynote' => null,
            'timemodified' => time(),
        ];
        $DB->update_record(self::TABLE, (object) (['id' => (int) $meta->id] + $decision));
        foreach ($decision as $column => $value) {
            $meta->$column = $value;
        }
        self::write_audit($meta, $actorid, audit::ACTION_RESET);
        return true;
    }

    /**
     * Whether a user may read the details of a worker's sensitive evidence: the worker, or a holder of
     * viewsensitive in the worker's user context.
     *
     * @param int $ownerid The worker.
     * @param int|null $actorid The user asking; the current user when null.
     * @return bool
     */
    public static function can_see_sensitive(int $ownerid, ?int $actorid = null): bool {
        global $USER;
        $actorid ??= (int) $USER->id;
        return $ownerid === $actorid
            || has_capability('local/evidencehub:viewsensitive', \context_user::instance($ownerid), $actorid);
    }

    /**
     * Whether an actor may decide on a worker's evidence: verify it, reject it, or keep its decision while
     * changing it. The one rule of {@see self::decide()} and {@see self::reset_row()}.
     *
     * Not the owner; local/evidencehub:verify in the owner's user context; the right to read the evidence,
     * as {@see \core_competency\user_evidence::can_read_user()} counts it (userevidenceview or
     * userevidencemanage there), here for the actor rather than the current user, because an observer passes
     * the event's actor; and, when the evidence's type is sensitive, the right to read it.
     *
     * @param \stdClass $meta The meta row: userid and typeid are read.
     * @param int $actorid The user who decides or edits.
     * @return bool
     */
    public static function may_decide(\stdClass $meta, int $actorid): bool {
        global $DB;
        $ownerid = (int) $meta->userid;
        if ($actorid === $ownerid) {
            return false;
        }
        $context = \context_user::instance($ownerid);
        if (!has_capability('local/evidencehub:verify', $context, $actorid)) {
            return false;
        }
        $read = ['moodle/competency:userevidenceview', 'moodle/competency:userevidencemanage'];
        if (!has_any_capability($read, $context, $actorid)) {
            return false;
        }
        $sensitive = (bool) $DB->get_field('local_evidencehub_type', 'issensitive', ['id' => (int) $meta->typeid]);
        return !$sensitive || self::can_see_sensitive($ownerid, $actorid);
    }

    /**
     * Whether a meta row is hidden from the current user: a sensitive type they may not read.
     *
     * A restricted row is neither shown, edited nor decided on by that user.
     *
     * @param \stdClass $meta The meta row.
     * @param \stdClass|null $type Its type, when the caller has it already.
     * @return bool
     */
    public static function is_restricted(\stdClass $meta, ?\stdClass $type = null): bool {
        global $DB;
        $type ??= $DB->get_record('local_evidencehub_type', ['id' => (int) $meta->typeid]);
        return $type && $type->issensitive && !self::can_see_sensitive((int) $meta->userid);
    }

    /**
     * Whether the current user may verify or reject a worker's evidence: {@see self::may_decide()} for the
     * current user, for the pages that only decide whether to offer the action.
     *
     * Given the meta row, the whole rule, its type's sensitivity included. Without it (a page deciding for all of a
     * worker's evidence at once), the rule for a type that is not sensitive; such a page also hides the action on a
     * restricted item ({@see self::is_restricted()}).
     *
     * @param int $ownerid The worker.
     * @param \stdClass|null $meta The meta row of the evidence, when the page has it; its owner must be $ownerid.
     * @return bool
     */
    public static function can_verify(int $ownerid, ?\stdClass $meta = null): bool {
        global $USER;
        if ($meta !== null && (int) $meta->userid !== $ownerid) {
            return false;
        }
        return self::may_decide($meta ?? (object) ['userid' => $ownerid, 'typeid' => 0], (int) $USER->id);
    }

    /**
     * Confirm the details of an evidence against its files.
     *
     * @param int $metaid The meta row id.
     * @param string $note An optional note.
     * @return \stdClass The meta row as stored.
     * @throws \required_capability_exception Without local/evidencehub:verify or the right to read the evidence.
     * @throws \moodle_exception On the user's own evidence, restricted details, or when it is already verified.
     */
    public static function verify(int $metaid, string $note = ''): \stdClass {
        return self::decide($metaid, meta::VERIFY_VERIFIED, $note);
    }

    /**
     * Refuse the details of an evidence, saying why.
     *
     * @param int $metaid The meta row id.
     * @param string $note Why; required.
     * @return \stdClass The meta row as stored.
     * @throws \required_capability_exception Without local/evidencehub:verify or the right to read the evidence.
     * @throws \moodle_exception On the user's own evidence, restricted details, without a note, or when already rejected.
     */
    public static function reject(int $metaid, string $note): \stdClass {
        return self::decide($metaid, meta::VERIFY_REJECTED, $note);
    }

    /**
     * Record a verification decision.
     *
     * @param int $metaid The meta row id.
     * @param int $state meta::VERIFY_VERIFIED or meta::VERIFY_REJECTED.
     * @param string $note The note.
     * @return \stdClass The meta row as stored.
     * @throws \required_capability_exception Without local/evidencehub:verify or the right to read the evidence.
     * @throws \moodle_exception When a rule of verification refuses the decision, the details are
     *     restricted for the actor, or another writer holds the row.
     */
    private static function decide(int $metaid, int $state, string $note): \stdClass {
        global $DB, $USER;
        $meta = $DB->get_record(self::TABLE, ['id' => $metaid], '*', MUST_EXIST);
        // The owner is read from core's evidence, the record the decision is about.
        $ue = new user_evidence((int) $meta->userevidenceid);
        $ownerid = (int) $ue->get('userid');
        $context = $ue->get_context();
        require_capability('local/evidencehub:verify', $context);
        if (!$ue->can_read()) {
            throw new \required_capability_exception($context, 'moodle/competency:userevidenceview', 'nopermissions', '');
        }
        if ($ownerid === (int) $USER->id) {
            throw new \moodle_exception('cannotverifyown', 'local_evidencehub');
        }
        $note = trim($note);
        if ($state === meta::VERIFY_REJECTED && $note === '') {
            throw new \moodle_exception('rejectneedsnote', 'local_evidencehub');
        }

        $actorid = (int) $USER->id;
        $fresh = self::locked($metaid, function () use ($metaid, $state, $note, $actorid): \stdClass {
            global $DB;
            $transaction = $DB->start_delegated_transaction();
            $fresh = $DB->get_record(self::TABLE, ['id' => $metaid], '*', MUST_EXIST);
            // A refusal commits the empty transaction: a rollback would poison the caller's outer one. The checks
            // above give their own errors; what may_decide() refuses past them is the sensitive type.
            $refusal = null;
            if (!self::may_decide($fresh, $actorid)) {
                $refusal = 'errorrestricted';
            } else if ((int) $fresh->verifystatus === $state) {
                $refusal = $state === meta::VERIFY_VERIFIED ? 'alreadyverified' : 'alreadyrejected';
            }
            if ($refusal !== null) {
                $transaction->allow_commit();
                throw new \moodle_exception($refusal, 'local_evidencehub');
            }
            $now = time();
            $decision = [
                'verifystatus' => $state,
                'verifiedby' => $actorid,
                'timeverified' => $now,
                'verifynote' => $note === '' ? null : $note,
                'timemodified' => $now,
            ];
            // Only the decision columns: the details may have changed since the verifier loaded them.
            $DB->update_record(self::TABLE, (object) (['id' => $metaid] + $decision));
            $action = $state === meta::VERIFY_VERIFIED ? audit::ACTION_VERIFIED : audit::ACTION_REJECTED;
            self::write_audit($fresh, $actorid, $action, $decision['verifynote']);
            $transaction->allow_commit();
            return $DB->get_record(self::TABLE, ['id' => $metaid], '*', MUST_EXIST);
        });

        if ($state === meta::VERIFY_VERIFIED) {
            evidence_verified::create_from_meta($fresh)->trigger();
        } else {
            evidence_rejected::create_from_meta($fresh)->trigger();
        }
        return $fresh;
    }

    /**
     * Run a write to one meta row under that row's lock, so its writers never interleave.
     *
     * @param int $metaid The meta row id.
     * @param callable $work The write; its return value is returned.
     * @return mixed What the write returned.
     * @throws \moodle_exception When another writer holds the row for too long.
     */
    private static function locked(int $metaid, callable $work): mixed {
        $lock = \core\lock\lock_config::get_lock_factory('local_evidencehub')->get_lock('meta_' . $metaid, self::LOCK_TIMEOUT);
        if (!$lock) {
            throw new \moodle_exception('errorlocked', 'local_evidencehub');
        }
        try {
            return $work();
        } finally {
            $lock->release();
        }
    }

    /**
     * Append a row to the trail.
     *
     * @param \stdClass $meta The meta row.
     * @param int $actorid Who acted.
     * @param string $action An audit ACTION_* constant.
     * @param string|null $note The note, when the action has one.
     * @return void
     */
    private static function write_audit(\stdClass $meta, int $actorid, string $action, ?string $note = null): void {
        global $DB;
        $DB->insert_record('local_evidencehub_audit', (object) [
            'metaid' => (int) $meta->id,
            'userevidenceid' => (int) $meta->userevidenceid,
            'relateduserid' => (int) $meta->userid,
            'userid' => $actorid,
            'action' => $action,
            'note' => $note,
            'timecreated' => time(),
        ]);
    }

    /**
     * An optional integer field: null, '' and 0 all mean none.
     *
     * The date selector posts 0 for a disabled optional date.
     *
     * @param mixed $value The submitted value.
     * @return int|null
     */
    private static function optional_int(mixed $value): ?int {
        return ($value === null || $value === '' || (int) $value === 0) ? null : (int) $value;
    }

    /**
     * An optional text field, trimmed; empty means none.
     *
     * @param mixed $value The submitted value.
     * @return string|null
     */
    private static function optional_text(mixed $value): ?string {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
