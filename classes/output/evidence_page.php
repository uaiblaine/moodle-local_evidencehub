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
use local_evidencehub\local\meta;

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

        $items = [];
        foreach ($evidence as $ue) {
            $id = (int) $ue->get('id');
            $item = [
                'id' => $id,
                'name' => format_string($ue->get('name'), true, ['context' => $context]),
                'viewurl' => (new \moodle_url('/admin/tool/lp/user_evidence.php', ['id' => $id]))->out(false),
                'canedit' => $canmanage,
                'hasdetails' => false,
                'cancomplete' => false,
                'canverify' => false,
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
            $item['typename'] = $type ? format_string($type->name, true, ['context' => \context_system::instance()]) : '';
            $item['restricted'] = $restricted;
            // Who may not read the details may neither change nor decide on them.
            $item['canedit'] = $canmanage && !$restricted;
            $item['canverify'] = $canverify && !$restricted;
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
            if (!$restricted) {
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

        return [
            'userid' => $this->userid,
            'canadd' => $canmanage,
            'hasitems' => !empty($items),
            'items' => $items,
        ];
    }
}
