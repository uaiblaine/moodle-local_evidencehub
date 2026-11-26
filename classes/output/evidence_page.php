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
use core_competency\api;
use core_competency\user_evidence;
use local_evidencehub\local\details;
use local_evidencehub\local\dossier;
use local_evidencehub\local\meta;
use local_evidencehub\local\status;

/**
 * A worker's core evidence with its validity details, as the viewer may see them.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class evidence_page implements renderable, templatable {
    /**
     * The page of one worker.
     *
     * @param int $userid The worker.
     */
    public function __construct(
        /** @var int The worker. */
        private readonly int $userid,
    ) {
    }

    /**
     * The template context.
     *
     * @param renderer_base $output The renderer.
     * @return array The context of templates/evidence_page.mustache.
     * @throws \required_capability_exception When the viewer cannot read the worker's evidence.
     */
    #[\Override]
    public function export_for_template(renderer_base $output): array {
        global $DB, $USER;
        $context = \context_user::instance($this->userid);
        $own = $this->userid === (int) $USER->id;
        $canmanage = user_evidence::can_manage_user($this->userid);
        $canverify = details::can_verify($this->userid);
        $isverifier = has_capability('local/evidencehub:verify', $context);

        $evidence = api::list_user_evidence($this->userid);
        $metas = [];
        if ($evidence) {
            $ids = array_map(fn(user_evidence $ue): int => (int) $ue->get('id'), $evidence);
            [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
            foreach ($DB->get_records_select(details::TABLE, "userevidenceid $insql", $params) as $meta) {
                $metas[(int) $meta->userevidenceid] = $meta;
            }
        }
        $types = $DB->get_records('local_evidencehub_type');
        $competencies = $this->competencies($evidence, $types);

        $items = [];
        foreach ($evidence as $ue) {
            $id = (int) $ue->get('id');
            $item = [
                'id' => $id,
                // Plain spellings throughout: the template's double stashes escape them once.
                'name' => format_string($ue->get('name'), true, ['context' => $context, 'escape' => false]),
                'viewurl' => (new \moodle_url('/admin/tool/lp/user_evidence.php', ['id' => $id]))->out(false),
                'canedit' => $canmanage,
                'hasdetails' => false,
                'cancomplete' => false,
                'canverify' => false,
                'hascompetencies' => !empty($competencies[$id]),
                'competencies' => $competencies[$id] ?? [],
            ];
            $meta = $metas[$id] ?? null;
            if ($meta === null) {
                $item['cancomplete'] = $canmanage;
                $items[] = $item;
                continue;
            }
            $type = $types[$meta->typeid] ?? null;
            $restricted = $type && details::is_restricted($meta, $type);
            $state = (int) $meta->verifystatus;
            $item['hasdetails'] = true;
            $item['metaid'] = (int) $meta->id;
            $item['typename'] = $type
                ? format_string($type->name, true, ['context' => \context_system::instance(), 'escape' => false])
                : '';
            $item['restricted'] = $restricted;
            // Who may not read the details may neither change nor decide on them.
            $item['canedit'] = $canmanage && !$restricted;
            $item['canverify'] = $canverify && !$restricted;
            if ($restricted) {
                // The validity dates of a restricted evidence are its details too.
                foreach (array_keys($item['competencies']) as $index) {
                    unset($item['competencies'][$index]['expiry']);
                }
            } else {
                // The verification state, the verifier and the time date the sensitive evidence's handling: a
                // restricted card carries its type only.
                $item['statelabel'] = meta::verify_label($state);
                $item['badgeclass'] = match ($state) {
                    meta::VERIFY_VERIFIED => 'bg-success text-white',
                    meta::VERIFY_REJECTED => 'bg-danger text-white',
                    default => 'bg-warning text-dark',
                };
                if ($state !== meta::VERIFY_PENDING && $meta->verifiedby) {
                    $verifier = \core_user::get_user((int) $meta->verifiedby);
                    $item['decidedby'] = get_string('decidedby', 'local_evidencehub', (object) [
                        'name' => $verifier ? fullname($verifier) : '',
                        'time' => userdate((int) $meta->timeverified, get_string('strftimedatetimeshort', 'core_langconfig')),
                    ]);
                }
                $item['issuedate'] = meta::iso_date((int) $meta->issuedate);
                $item['expirydate'] = $meta->expirydate === null ? null : meta::iso_date((int) $meta->expirydate);
                $item['noexpiry'] = (bool) $meta->noexpiry;
                $item['issuer'] = $meta->issuer;
                $item['hours'] = $meta->hours === null ? null : format_float((float) $meta->hours, 2, true, true);
                if ($canmanage || $isverifier) {
                    $item['authcode'] = $meta->authcode;
                }
                if (($own || $isverifier) && $meta->verifynote !== null && $meta->verifynote !== '') {
                    $item['note'] = $meta->verifynote;
                }
            }
            $items[] = $item;
        }

        $dossiers = $this->dossiers();
        return [
            'userid' => $this->userid,
            'canadd' => $canmanage,
            'hasitems' => !empty($items),
            'items' => $items,
            'hasdossiers' => !empty($dossiers),
            'dossiers' => $dossiers,
            'actionurl' => (new \moodle_url('/local/evidencehub/evidence.php', ['userid' => $this->userid]))->out(false),
            'sesskey' => sesskey(),
        ];
    }

    /**
     * The dossiers issued about the worker (LGPD art. 18: whom their data was shared with), newest first.
     *
     * @return array List of [id, created, createdby, template, revoked, revokedon, canrevoke, revokelabel].
     */
    private function dossiers(): array {
        $format = get_string('strftimedatetimeshort', 'core_langconfig');
        $names = [];
        $dossiers = [];
        foreach (dossier::for_worker($this->userid) as $record) {
            $creatorid = (int) $record->createdby;
            if (!array_key_exists($creatorid, $names)) {
                $creator = \core_user::get_user($creatorid);
                $names[$creatorid] = $creator && !$creator->deleted ? fullname($creator) : '';
            }
            $templatecontext = $record->templatecontextid === null
                ? null : \context::instance_by_id((int) $record->templatecontextid, IGNORE_MISSING);
            $template = $record->templatename === null ? '' : format_string(
                $record->templatename,
                true,
                ['context' => $templatecontext ?: \context_system::instance(), 'escape' => false]
            );
            $created = userdate((int) $record->timecreated, $format);
            $revoked = (int) $record->revoked === 1;
            $dossiers[] = [
                'id' => (int) $record->id,
                'created' => $created,
                'createdby' => $names[$creatorid],
                'template' => $template,
                'revoked' => $revoked,
                'revokedon' => $revoked && $record->timerevoked ? userdate((int) $record->timerevoked, $format) : '',
                'canrevoke' => !$revoked && dossier::can_revoke($record),
                'revokelabel' => get_string('dossierrevokelabel', 'local_evidencehub', (object) [
                    'template' => $template,
                    'created' => $created,
                ]),
            ];
        }
        return $dossiers;
    }

    /**
     * The competencies each evidence is linked to, with the worker's validity status of each: one
     * query for the whole page.
     *
     * The status of a pair is decided by one evidence, which may be another than the item shown: when
     * that deciding evidence is restricted for the viewer, the pill says only "Restricted", on every
     * item linked to the competency, so neither its date nor its state leaks through another item.
     *
     * @param array $evidence The worker's user_evidence objects.
     * @param array $types The evidence types, by id.
     * @return array Core evidence id => list of [name, statuslabel, badgeclass, expiry].
     */
    private function competencies(array $evidence, array $types): array {
        global $DB;
        if (!$evidence) {
            return [];
        }
        $ids = array_map(fn(user_evidence $ue): int => (int) $ue->get('id'), $evidence);
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $sql = "SELECT uec.id, uec.userevidenceid, c.shortname, f.contextid, s.status, s.effectiveexpiry,
                       s.metaid, dm.typeid AS decidingtypeid
                  FROM {competency_userevidencecomp} uec
                  JOIN {competency} c ON c.id = uec.competencyid
                  JOIN {competency_framework} f ON f.id = c.competencyframeworkid
             LEFT JOIN {local_evidencehub_status} s ON s.competencyid = uec.competencyid AND s.userid = :userid
             LEFT JOIN {" . details::TABLE . "} dm ON dm.id = s.metaid
                 WHERE uec.userevidenceid $insql
              ORDER BY c.shortname, c.id";
        $linked = [];
        foreach ($DB->get_records_sql($sql, $params + ['userid' => $this->userid]) as $record) {
            // A competency's name belongs to its framework's context (system or a category).
            $context = \context::instance_by_id((int) $record->contextid);
            $pill = ['name' => format_string($record->shortname, true, ['context' => $context, 'escape' => false])];
            $deciding = $record->decidingtypeid === null ? null : (object) [
                'userid' => $this->userid,
                'typeid' => (int) $record->decidingtypeid,
            ];
            if ($deciding !== null && details::is_restricted($deciding, $types[$deciding->typeid] ?? null)) {
                $pill += [
                    'statuslabel' => get_string('validityrestricted', 'local_evidencehub'),
                    'badgeclass' => status::badge_class(null),
                    'expiry' => null,
                ];
            } else {
                $state = $record->status === null ? null : (int) $record->status;
                $pill += [
                    'statuslabel' => status::label($state),
                    'badgeclass' => status::badge_class($state),
                    'expiry' => $record->effectiveexpiry === null ? null : meta::iso_date((int) $record->effectiveexpiry),
                ];
            }
            $linked[(int) $record->userevidenceid][] = $pill;
        }
        return $linked;
    }
}
