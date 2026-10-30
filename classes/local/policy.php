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

use local_evidencehub\event\validity_policy_updated;

/**
 * Policy kinds and expiry behaviours, as stored in local_evidencehub_policy, and the policy API:
 * which policy applies to a competency, and how an administrator changes it.
 *
 * The kind is operational (does an expired evidence stop the worker?), not an educational
 * taxonomy.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class policy {
    /** @var int Regulatory (health and safety): verified evidence only, strict expiry, blocking. */
    public const int KIND_REGULATORY = 1;

    /** @var int Technical or role-specific: evidence or a proficient rating, grace days, not blocking. */
    public const int KIND_TECHNICAL = 2;

    /** @var int Behavioural or organisational: a proficient rating valid for cyclemonths, not blocking. */
    public const int KIND_BEHAVIOURAL = 3;

    /** @var int On expiry, log it and alert. */
    public const int ONEXPIRY_LOG = 1;

    /** @var int On expiry, log it, alert and request a review in core; proficiency is never revoked. */
    public const int ONEXPIRY_REVIEW = 2;

    /** @var string The resolved policy is the competency's own row. */
    public const string SOURCE_COMPETENCY = 'competency';

    /** @var string The resolved policy is the framework's row. */
    public const string SOURCE_FRAMEWORK = 'framework';

    /** @var string No row applies: the built-in default. */
    public const string SOURCE_DEFAULT = 'default';

    /** @var string The table of the policies. */
    public const string TABLE = 'local_evidencehub_policy';

    /** @var int The largest value the four-digit day and month columns hold. */
    public const int MAX_DAYS = 9999;

    /** @var float The largest value local_evidencehub_policy.minhours holds. */
    public const float MAX_HOURS = 999999.99;

    /**
     * The built-in policy of a competency that neither it nor its framework sets.
     *
     * @return \stdClass The policy's columns, with source SOURCE_DEFAULT and id null.
     */
    public static function defaults(): \stdClass {
        return (object) [
            'id' => null,
            'kind' => self::KIND_TECHNICAL,
            'requireverified' => 0,
            'requireproficiency' => 0,
            'minhours' => null,
            'alert1' => 60,
            'alert2' => 30,
            'gracedays' => 0,
            'cyclemonths' => 0,
            'onexpiry' => self::ONEXPIRY_LOG,
            'source' => self::SOURCE_DEFAULT,
        ];
    }

    /**
     * What a new policy row of a kind starts with: regulatory evidence must be verified (D4).
     *
     * The form proposes these when an administrator creates a row; the resolver never applies them.
     *
     * @param int $kind A KIND_* constant.
     * @return \stdClass The columns the kind sets.
     */
    public static function kind_defaults(int $kind): \stdClass {
        return (object) ['requireverified' => (int) ($kind === self::KIND_REGULATORY)];
    }

    /**
     * The policy in force for a competency: its own row, else its framework's, else the default.
     *
     * @param int $competencyid The competency.
     * @param int $frameworkid Its framework.
     * @return \stdClass The policy's columns and its source (a SOURCE_* constant).
     */
    public static function resolve(int $competencyid, int $frameworkid): \stdClass {
        global $DB;
        $rows = $DB->get_records_select(
            self::TABLE,
            'frameworkid = :frameworkid AND (competencyid = :competencyid OR competencyid = 0)',
            ['frameworkid' => $frameworkid, 'competencyid' => $competencyid]
        );
        return self::resolve_from($rows, $competencyid);
    }

    /**
     * The policy in force for a competency, among rows of its framework already read.
     *
     * @param array $rows Policy rows of one framework.
     * @param int $competencyid The competency.
     * @return \stdClass The policy's columns and its source (a SOURCE_* constant).
     */
    public static function resolve_from(array $rows, int $competencyid): \stdClass {
        $own = null;
        $framework = null;
        foreach ($rows as $row) {
            if ($competencyid && (int) $row->competencyid === $competencyid) {
                $own = $row;
            } else if ((int) $row->competencyid === 0) {
                $framework = $row;
            }
        }
        if ($own !== null) {
            return self::normalise($own, self::SOURCE_COMPETENCY);
        }
        if ($framework !== null) {
            return self::normalise($framework, self::SOURCE_FRAMEWORK);
        }
        return self::defaults();
    }

    /**
     * A stored row with typed values and its source.
     *
     * @param \stdClass $row The row.
     * @param string $source A SOURCE_* constant.
     * @return \stdClass
     */
    private static function normalise(\stdClass $row, string $source): \stdClass {
        $policy = clone $row;
        $integers = ['id', 'frameworkid', 'competencyid', 'kind', 'requireverified', 'requireproficiency', 'alert1', 'alert2',
            'gracedays', 'cyclemonths', 'onexpiry'];
        foreach ($integers as $column) {
            $policy->$column = (int) $row->$column;
        }
        $policy->minhours = $row->minhours === null ? null : (float) $row->minhours;
        $policy->source = $source;
        return $policy;
    }

    /**
     * What is wrong with a submitted policy, field by field.
     *
     * @param \stdClass $data kind, requireverified, requireproficiency, minhours (null or '' for
     *     none), alert1, alert2, gracedays, cyclemonths and onexpiry.
     * @return array Field name => string id in local_evidencehub; empty when the policy is fine.
     */
    public static function problems(\stdClass $data): array {
        $problems = [];
        if (!in_array((int) ($data->kind ?? 0), [self::KIND_REGULATORY, self::KIND_TECHNICAL, self::KIND_BEHAVIOURAL], true)) {
            $problems['kind'] = 'errorpolicykind';
        }
        if (!in_array((int) ($data->onexpiry ?? 0), [self::ONEXPIRY_LOG, self::ONEXPIRY_REVIEW], true)) {
            $problems['onexpiry'] = 'errorpolicyonexpiry';
        }
        foreach (['alert1', 'alert2', 'gracedays', 'cyclemonths'] as $field) {
            $value = (int) ($data->$field ?? 0);
            if ($value < 0 || $value > self::MAX_DAYS) {
                $problems[$field] = 'errorpolicyrange';
            }
        }
        $inrange = !isset($problems['alert1']) && !isset($problems['alert2']);
        if ($inrange && (int) ($data->alert1 ?? 0) < (int) ($data->alert2 ?? 0)) {
            $problems['alert2'] = 'errorpolicyalerts';
        }
        $minhours = $data->minhours ?? null;
        if ($minhours !== null && $minhours !== '' && ((float) $minhours < 0 || (float) $minhours > self::MAX_HOURS)) {
            $problems['minhours'] = 'errorpolicyrange';
        }
        return $problems;
    }

    /**
     * The context a framework's policies are managed in: the framework's own (system or a category).
     *
     * @param int $frameworkid The framework.
     * @return \context
     * @throws \dml_missing_record_exception When the framework does not exist.
     */
    public static function framework_context(int $frameworkid): \context {
        global $DB;
        $contextid = $DB->get_field('competency_framework', 'contextid', ['id' => $frameworkid], MUST_EXIST);
        return \context::instance_by_id((int) $contextid);
    }

    /**
     * Refuse a user who may not manage a framework's policies, or a competency of another framework.
     *
     * @param int $frameworkid The framework.
     * @param int $competencyid A competency of it, or 0 for the framework's own policy.
     * @return \context The framework's context.
     * @throws \required_capability_exception Without local/evidencehub:managepolicies there.
     * @throws \moodle_exception When the competency belongs to another framework.
     */
    public static function require_manage(int $frameworkid, int $competencyid): \context {
        global $DB;
        $context = self::framework_context($frameworkid);
        require_capability('local/evidencehub:managepolicies', $context);
        if ($competencyid) {
            $owner = $DB->get_field('competency', 'competencyframeworkid', ['id' => $competencyid]);
            if ((int) $owner !== $frameworkid) {
                throw new \moodle_exception('errorwrongframework', 'local_evidencehub');
            }
        }
        return $context;
    }

    /**
     * Write the policy of a framework or of one competency, inserting or replacing it, and queue the
     * recomputation of every pair it may change.
     *
     * @param \stdClass $data frameworkid, competencyid (0 for the framework's own) and the columns
     *     problems() reads.
     * @return \stdClass The row as stored.
     * @throws \required_capability_exception Without local/evidencehub:managepolicies in the framework's context.
     * @throws \moodle_exception When the policy breaks a rule or the competency belongs to another framework.
     */
    public static function save(\stdClass $data): \stdClass {
        global $DB;
        $frameworkid = (int) $data->frameworkid;
        $competencyid = (int) ($data->competencyid ?? 0);
        $context = self::require_manage($frameworkid, $competencyid);
        $problems = self::problems($data);
        if ($problems) {
            throw new \moodle_exception(reset($problems), 'local_evidencehub');
        }
        $minhours = $data->minhours ?? null;
        $now = \core\di::get(\core\clock::class)->time();
        $kind = (int) $data->kind;
        $fields = [
            'kind' => $kind,
            'requireverified' => (int) !empty($data->requireverified),
            // Only a technical policy reads it; another kind stores it off so the row says what applies.
            'requireproficiency' => (int) ($kind === self::KIND_TECHNICAL && !empty($data->requireproficiency)),
            'minhours' => ($minhours === null || $minhours === '') ? null : round((float) $minhours, 2),
            'alert1' => (int) $data->alert1,
            'alert2' => (int) $data->alert2,
            'gracedays' => (int) $data->gracedays,
            'cyclemonths' => (int) $data->cyclemonths,
            'onexpiry' => (int) $data->onexpiry,
            'timemodified' => $now,
        ];
        $key = ['frameworkid' => $frameworkid, 'competencyid' => $competencyid];
        $transaction = $DB->start_delegated_transaction();
        $existing = $DB->get_record(self::TABLE, $key);
        if ($existing) {
            $DB->update_record(self::TABLE, (object) (['id' => (int) $existing->id] + $fields));
            $id = (int) $existing->id;
        } else {
            $id = $DB->insert_record(self::TABLE, (object) ($key + $fields + ['timecreated' => $now]));
        }
        $transaction->allow_commit();
        $stored = $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);
        self::changed($context, $stored);
        return $stored;
    }

    /**
     * Remove the policy of a framework or of one competency, so the next one up applies again.
     *
     * @param int $frameworkid The framework.
     * @param int $competencyid A competency of it, or 0 for the framework's own policy.
     * @return bool Whether a row was removed.
     * @throws \required_capability_exception Without local/evidencehub:managepolicies in the framework's context.
     * @throws \moodle_exception When the competency belongs to another framework.
     */
    public static function delete(int $frameworkid, int $competencyid): bool {
        global $DB;
        $context = self::require_manage($frameworkid, $competencyid);
        $existing = $DB->get_record(self::TABLE, ['frameworkid' => $frameworkid, 'competencyid' => $competencyid]);
        if (!$existing) {
            return false;
        }
        $DB->delete_records(self::TABLE, ['id' => $existing->id]);
        self::changed($context, $existing);
        return true;
    }

    /**
     * Tell the site a policy changed and queue the recomputation of its scope.
     *
     * @param \context $context The framework's context.
     * @param \stdClass $row The row written or removed.
     * @return void
     */
    private static function changed(\context $context, \stdClass $row): void {
        validity_policy_updated::create_from_policy($context, $row)->trigger();
        if ((int) $row->competencyid) {
            status_repository::recompute_competency((int) $row->competencyid);
        } else {
            status_repository::recompute_framework((int) $row->frameworkid);
        }
    }

    /**
     * The human-readable name of a kind.
     *
     * @param int $kind A KIND_* constant.
     * @return string
     * @throws \coding_exception On an unknown kind.
     */
    public static function kind_label(int $kind): string {
        return match ($kind) {
            self::KIND_REGULATORY => get_string('kind_regulatory', 'local_evidencehub'),
            self::KIND_TECHNICAL => get_string('kind_technical', 'local_evidencehub'),
            self::KIND_BEHAVIOURAL => get_string('kind_behavioural', 'local_evidencehub'),
            default => throw new \coding_exception('Unknown policy kind: ' . $kind),
        };
    }

    /**
     * The human-readable name of an expiry behaviour.
     *
     * @param int $onexpiry An ONEXPIRY_* constant.
     * @return string
     * @throws \coding_exception On an unknown behaviour.
     */
    public static function onexpiry_label(int $onexpiry): string {
        return match ($onexpiry) {
            self::ONEXPIRY_LOG => get_string('onexpiry_log', 'local_evidencehub'),
            self::ONEXPIRY_REVIEW => get_string('onexpiry_review', 'local_evidencehub'),
            default => throw new \coding_exception('Unknown expiry behaviour: ' . $onexpiry),
        };
    }

    /**
     * The human-readable name of where a resolved policy came from.
     *
     * @param string $source A SOURCE_* constant.
     * @return string
     * @throws \coding_exception On an unknown source.
     */
    public static function source_label(string $source): string {
        return match ($source) {
            self::SOURCE_COMPETENCY => get_string('source_competency', 'local_evidencehub'),
            self::SOURCE_FRAMEWORK => get_string('source_framework', 'local_evidencehub'),
            self::SOURCE_DEFAULT => get_string('source_default', 'local_evidencehub'),
            default => throw new \coding_exception('Unknown policy source: ' . $source),
        };
    }
}
