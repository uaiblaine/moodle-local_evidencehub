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

use local_evidencehub\event\dossier_revoked;

/**
 * The issued dossiers (local_evidencehub_dossier): codes, snapshots, readiness, the worker's list and revocation.
 *
 * A dossier row is never deleted by the plugin: it backs the public verification page, which shows its
 * snapshot as issued and whether it was revoked.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dossier {
    /** @var string The table. */
    public const string TABLE = 'local_evidencehub_dossier';

    /** @var string Every competency of the template is compliant, due or critical. */
    public const string READINESS_READY = 'ready';

    /** @var string A competency under a regulatory policy is not: the worker is stopped. */
    public const string READINESS_NOTREADY = 'notready';

    /** @var string Only competencies under a technical or behavioural policy are not: flagged, not stopped. */
    public const string READINESS_ATRISK = 'atrisk';

    /**
     * A new verification code: 128 random bits as 32 lower-case hex characters.
     *
     * @return string
     */
    public static function new_code(): string {
        return bin2hex(random_bytes(16));
    }

    /**
     * A name as the verification page shows it: the first letter of each part, then three stars.
     *
     * "Joana da Silva Souza" becomes "J*** d*** S*** S***".
     *
     * @param string $firstname The first name.
     * @param string $lastname The last name.
     * @return string
     */
    public static function mask_name(string $firstname, string $lastname): string {
        $parts = preg_split('/\s+/u', trim($firstname . ' ' . $lastname), -1, PREG_SPLIT_NO_EMPTY);
        return implode(' ', array_map(fn(string $part): string => \core_text::substr($part, 0, 1) . '***', $parts));
    }

    /**
     * A worker's readiness from the effective status of each competency and its policy kind.
     *
     * A competency is met when compliant, due or critical ({@see hub_kpis::READY}); a restricted one is
     * not met, as the hub's readiness counts it, so the verdict reveals nothing the cell hides. An unmet
     * regulatory competency stops the worker; unmet technical or behavioural ones flag the worker at risk.
     *
     * @param array $pairs List of [effective status code, policy KIND_* constant].
     * @return string A READINESS_* constant.
     */
    public static function readiness(array $pairs): string {
        $readiness = self::READINESS_READY;
        foreach ($pairs as [$code, $kind]) {
            if (in_array($code, hub_kpis::READY, true)) {
                continue;
            }
            if ($kind === policy::KIND_REGULATORY) {
                return self::READINESS_NOTREADY;
            }
            $readiness = self::READINESS_ATRISK;
        }
        return $readiness;
    }

    /**
     * The human-readable name of a readiness.
     *
     * @param string $readiness A READINESS_* constant.
     * @return string
     * @throws \coding_exception On an unknown value.
     */
    public static function readiness_label(string $readiness): string {
        return match ($readiness) {
            self::READINESS_READY => get_string('readiness_ready', 'local_evidencehub'),
            self::READINESS_NOTREADY => get_string('readiness_notready', 'local_evidencehub'),
            self::READINESS_ATRISK => get_string('readiness_atrisk', 'local_evidencehub'),
            default => throw new \coding_exception('Unknown readiness: ' . $readiness),
        };
    }

    /**
     * The badge classes of a readiness, each with its text colour stated.
     *
     * @param string $readiness A READINESS_* constant.
     * @return string
     */
    public static function readiness_badge_class(string $readiness): string {
        return match ($readiness) {
            self::READINESS_READY => 'bg-success text-white',
            self::READINESS_ATRISK => 'bg-warning text-dark',
            default => 'bg-danger text-white',
        };
    }

    /**
     * The dossier a verification code names.
     *
     * Anything but 32 hex characters finds nothing, as an unknown code does: the page answers both
     * the same way, so it is no oracle of the code's shape.
     *
     * @param string $code The code as given.
     * @return \stdClass|null The dossier row.
     */
    public static function find_by_code(string $code): ?\stdClass {
        global $DB;
        $code = strtolower($code);
        if (!preg_match('/^[0-9a-f]{32}$/', $code)) {
            return null;
        }
        return $DB->get_record(self::TABLE, ['code' => $code]) ?: null;
    }

    /**
     * The dossiers issued about a worker, newest first, with their template's name and context.
     *
     * @param int $userid The worker.
     * @return array List of dossier rows with templatename and templatecontextid (null when the template is gone).
     */
    public static function for_worker(int $userid): array {
        global $DB;
        $sql = "SELECT d.id, d.userid, d.templateid, d.createdby, d.revoked, d.timerevoked, d.timecreated,
                       t.shortname AS templatename, t.contextid AS templatecontextid
                  FROM {" . self::TABLE . "} d
             LEFT JOIN {competency_template} t ON t.id = d.templateid
                 WHERE d.userid = :userid
              ORDER BY d.timecreated DESC, d.id DESC";
        return array_values($DB->get_records_sql($sql, ['userid' => $userid]));
    }

    /**
     * Whether the current visitor may open the verification page.
     *
     * A visitor who is not logged in has no roles of their own: the guest role answers for them, so a site can
     * keep the page open under forcelogin and with guest login off. Mirrors
     * {@see \tool_certificate\permission::can_verify()}.
     *
     * @return bool
     */
    public static function can_verify(): bool {
        global $CFG;
        if (isloggedin()) {
            return has_capability('local/evidencehub:verifydossier', \context_system::instance());
        }
        return has_capability('local/evidencehub:verifydossier', \context_system::instance(), (int) $CFG->siteguest);
    }

    /**
     * Whether the current user may revoke a dossier: its creator, or a holder of exportdossier on the site.
     *
     * @param \stdClass $dossier The dossier row.
     * @return bool
     */
    public static function can_revoke(\stdClass $dossier): bool {
        global $USER;
        return (int) $dossier->createdby === (int) $USER->id
            || has_capability('local/evidencehub:exportdossier', \context_system::instance());
    }

    /**
     * Revoke a dossier of a worker: the verification page then says so.
     *
     * @param int $dossierid The dossier.
     * @param int $userid The worker the page is about; a dossier of another worker is refused.
     * @return \stdClass The dossier row as stored.
     * @throws \moodle_exception When the dossier is not the worker's, or already revoked.
     * @throws \required_capability_exception When the user may not revoke it.
     */
    public static function revoke(int $dossierid, int $userid): \stdClass {
        global $DB;
        $dossier = $DB->get_record(self::TABLE, ['id' => $dossierid]);
        if (!$dossier || (int) $dossier->userid !== $userid) {
            throw new \moodle_exception('invalidrecord', 'error', '', self::TABLE);
        }
        if (!self::can_revoke($dossier)) {
            throw new \required_capability_exception(
                \context_system::instance(),
                'local/evidencehub:exportdossier',
                'nopermissions',
                ''
            );
        }
        if ((int) $dossier->revoked === 1) {
            throw new \moodle_exception('errordossierrevoked', 'local_evidencehub');
        }
        // Atomic: only a row still in force is revoked, so two revocations racing cannot both stamp it. The
        // re-read tells which one did; two in the same second both read their own time (accepted).
        $now = \core\di::get(\core\clock::class)->time();
        $DB->execute(
            'UPDATE {' . self::TABLE . '} SET revoked = 1, timerevoked = :timerevoked WHERE id = :id AND revoked = 0',
            ['timerevoked' => $now, 'id' => $dossierid]
        );
        $dossier = $DB->get_record(self::TABLE, ['id' => $dossierid], '*', MUST_EXIST);
        if ((int) $dossier->timerevoked !== $now) {
            throw new \moodle_exception('errordossierrevoked', 'local_evidencehub');
        }
        dossier_revoked::create_from_dossier($dossier)->trigger();
        return $dossier;
    }
}
