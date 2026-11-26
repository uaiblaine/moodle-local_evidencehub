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
use local_evidencehub\local\details;
use local_evidencehub\local\dossier;
use local_evidencehub\local\dossier_config;
use local_evidencehub\local\meta;
use local_evidencehub\local\status;
use local_evidencehub\local\status_repository;

/**
 * The public verification page of one dossier: its snapshot as issued and the PDF's hash, or, once it is
 * revoked, the revocation and nothing else.
 *
 * Reads the snapshot only, which holds the masked name and no identifier, full name or file. With the
 * verifylive setting on it adds each competency's current status, computed with no viewer rights: a pair
 * whose status row is flagged sensitive, or whose deciding evidence's type is, reads "Restricted", and a
 * suspended or deleted worker has no status today. Its verdict follows the readiness basis setting: the
 * snapshot's real one, or the one over the statuses the snapshot shows.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class verify_page implements renderable, templatable {
    /**
     * The page of a code.
     *
     * @param \stdClass|null $dossier The dossier the code names, or null when it names none.
     * @param bool $searched Whether a code was given at all.
     * @param bool $live Whether to add the current status of each competency.
     * @param string $basis The readiness basis setting ({@see dossier_config::readiness_basis()}), read at view time.
     */
    public function __construct(
        /** @var \stdClass|null The dossier. */
        private readonly ?\stdClass $dossier,
        /** @var bool Whether a code was given. */
        private readonly bool $searched,
        /** @var bool Whether to add the current status. */
        private readonly bool $live,
        /** @var string The readiness basis setting. */
        private readonly string $basis = dossier_config::READINESS_REAL,
    ) {
    }

    /**
     * The template context.
     *
     * @param renderer_base $output The renderer.
     * @return array The context of templates/verify_page.mustache.
     */
    #[\Override]
    public function export_for_template(renderer_base $output): array {
        $data = [
            'actionurl' => (new \moodle_url('/local/evidencehub/verify.php'))->out(false),
            'notfound' => $this->searched && $this->dossier === null,
            'found' => $this->dossier !== null,
        ];
        if ($this->dossier === null) {
            return $data;
        }
        $format = get_string('strftimedate', 'core_langconfig');
        // A revoked dossier certifies nothing, so its page states the revocation alone: the snapshot is not read,
        // nor are today's statuses under verifylive.
        if ((int) $this->dossier->revoked === 1) {
            return $data + [
                'revoked' => true,
                'revokedon' => $this->dossier->timerevoked ? userdate((int) $this->dossier->timerevoked, $format) : '',
            ];
        }
        $snapshot = json_decode($this->dossier->snapshot) ?: new \stdClass();
        $current = $this->live ? $this->current((int) $this->dossier->userid, $snapshot->competencies ?? []) : [];
        $competencies = [];
        foreach ($snapshot->competencies ?? [] as $competency) {
            $code = (int) $competency->status;
            $item = [
                'name' => (string) $competency->name,
                'statuslabel' => status::effective_label($code),
                'badgeclass' => status::effective_badge_class($code),
                'expiry' => $competency->expiry === null ? '' : meta::iso_date((int) $competency->expiry),
            ];
            if ($this->live) {
                $now = $current[(int) $competency->id] ?? status::EFFECTIVE_MISSING;
                $item['livelabel'] = status::effective_label($now);
                $item['livebadgeclass'] = status::effective_badge_class($now);
            }
            $competencies[] = $item;
        }
        // Under "shown" the verdict is the snapshot's readinessshown; a snapshot issued before the field existed
        // has none, and the page then states no verdict rather than the real one the setting rules out.
        $shown = $this->basis === dossier_config::READINESS_SHOWN;
        $hasreadiness = !$shown || isset($snapshot->readinessshown);
        $stored = $shown ? ($snapshot->readinessshown ?? null) : ($snapshot->readiness ?? null);
        $readiness = (string) ($stored ?? dossier::READINESS_NOTREADY);
        return $data + [
            'name' => (string) ($snapshot->name ?? ''),
            'template' => (string) ($snapshot->template ?? ''),
            'issued' => userdate((int) ($snapshot->issued ?? $this->dossier->timecreated), $format),
            'hasreadiness' => $hasreadiness,
            'readiness' => dossier::readiness_label($readiness),
            'readinessclass' => dossier::readiness_badge_class($readiness),
            'revoked' => false,
            'pdfhash' => (string) $this->dossier->pdfhash,
            'code' => (string) $this->dossier->code,
            'live' => $this->live,
            'competencies' => $competencies,
        ];
    }

    /**
     * The current effective status of each competency of the snapshot, for no viewer in particular.
     *
     * Restricted when the stored flag or the deciding evidence's type says so, as
     * {@see \local_evidencehub\local\validity_badges} reads it: a type made sensitive after the last recompute
     * must not show its pair's status. A suspended or deleted worker counts as not found: no status today.
     *
     * @param int $userid The worker.
     * @param array $competencies The snapshot's competencies.
     * @return array Competency id => effective status code; a competency without a row is missing.
     */
    private function current(int $userid, array $competencies): array {
        global $DB;
        $ids = array_map(fn(\stdClass $competency): int => (int) $competency->id, $competencies);
        if (!$ids) {
            return [];
        }
        if (!$DB->record_exists('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0])) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'vfc');
        $sql = "SELECT s.id, s.competencyid, s.status,
                       CASE WHEN s.issensitive = 1 OR t.issensitive = 1 THEN 1 ELSE 0 END AS issensitive
                  FROM {" . status_repository::TABLE . "} s
             LEFT JOIN {" . details::TABLE . "} m ON m.id = s.metaid
             LEFT JOIN {local_evidencehub_type} t ON t.id = m.typeid
                 WHERE s.userid = :userid AND s.competencyid $insql";
        $rows = $DB->get_records_sql($sql, $params + ['userid' => $userid]);
        $current = [];
        foreach ($rows as $row) {
            $current[(int) $row->competencyid] = (int) $row->issensitive === 1 ? status::EFFECTIVE_RESTRICTED : (int) $row->status;
        }
        return $current;
    }
}
