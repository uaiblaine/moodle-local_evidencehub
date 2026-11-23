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

namespace local_evidencehub\reportbuilder\datasource;

use core_competency\reportbuilder\local\entities\competency;
use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\helpers\database;
use local_evidencehub\local\details;
use local_evidencehub\local\hub_scope;
use local_evidencehub\local\pairset;
use local_evidencehub\local\status_repository;
use local_evidencehub\reportbuilder\local\entities\evidence_details;
use local_evidencehub\reportbuilder\local\entities\validity as validity_entity;

/**
 * Custom reports over the materialised validity: one row per stored (worker, competency) status.
 *
 * Offered to holders of local/evidencehub:view in the system context. Rows follow the hub's rules for
 * the user the report is built for (core builds a datasource per user, schedules included: the
 * creator, a chosen user or each recipient): only workers whose user context grants that user
 * moodle/competency:userevidenceview, and a pair decided by a sensitive evidence reads "Restricted"
 * unless the user is the worker or holds local/evidencehub:viewsensitive in the worker's user context
 * ({@see \local_evidencehub\local\pairset}, the rule {@see \local_evidencehub\local\hub_query::base_sql()} reads).
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class validity extends datasource {
    /**
     * The datasource's name.
     *
     * @return string
     */
    #[\Override]
    public static function get_name(): string {
        return get_string('datasource:validity', 'local_evidencehub');
    }

    /**
     * Only for holders of local/evidencehub:view at system level.
     *
     * @return bool
     */
    #[\Override]
    public static function is_available(): bool {
        return has_capability('local/evidencehub:view', \context_system::instance());
    }

    /**
     * Join the status rows to their deciding evidence, the worker and the competency, for the current user.
     *
     * @return void
     */
    #[\Override]
    protected function initialise(): void {
        $scope = hub_scope::for_user();
        $s = database::generate_alias();
        $m = database::generate_alias();
        $t = database::generate_alias();
        $r = database::generate_alias();
        $this->set_main_table(status_repository::TABLE, $s);
        $names = pairset::report_names();

        // Only the workers whose user context grants the user userevidenceview, as in the hub.
        [$viewsql, $viewparams] = pairset::visible("{$s}.userid", $scope, $names);
        $this->add_base_condition_sql($viewsql, $viewparams);

        // The deciding evidence and its type, for the details entity.
        $this->add_join("LEFT JOIN {" . details::TABLE . "} {$m} ON {$m}.id = {$s}.metaid");
        $this->add_join("LEFT JOIN {local_evidencehub_type} {$t} ON {$t}.id = {$m}.typeid");
        // The row itself again, joined only when it is restricted for the user: the hub's rule, with its
        // parameters once in the statement however many columns read it.
        [$restrictedjoin, $restrictedparams] = pairset::restricted_join($r, $s, "{$s}.userid", $scope, $names);
        $this->add_join($restrictedjoin, $restrictedparams);
        $restricted = "{$r}.id IS NOT NULL";

        $this->add_entity((new validity_entity($restricted))->set_table_alias(status_repository::TABLE, $s));
        $this->add_entity((new evidence_details($restricted))
            ->set_table_alias(details::TABLE, $m)
            ->set_table_alias('local_evidencehub_type', $t));

        $userentity = new user();
        $u = $userentity->get_table_alias('user');
        $this->add_join("JOIN {user} {$u} ON {$u}.id = {$s}.userid");
        $this->add_base_condition_sql("{$u}.deleted = 0");
        $this->add_entity($userentity);

        $competencyentity = new competency();
        $c = $competencyentity->get_table_alias('competency');
        $this->add_entity($competencyentity->add_join("JOIN {competency} {$c} ON {$c}.id = {$s}.competencyid"));

        $this->add_all_from_entities();
    }

    /**
     * The columns of a new report.
     *
     * @return array
     */
    #[\Override]
    public function get_default_columns(): array {
        return [
            'user:fullname',
            'competency:name',
            'validity:status',
            'validity:effectiveexpiry',
            'evidence_details:type',
        ];
    }

    /**
     * The sorting of a new report.
     *
     * @return array
     */
    #[\Override]
    public function get_default_column_sorting(): array {
        return ['user:fullname' => SORT_ASC, 'competency:name' => SORT_ASC];
    }

    /**
     * The filters of a new report.
     *
     * @return array
     */
    #[\Override]
    public function get_default_filters(): array {
        return ['validity:status', 'evidence_details:type'];
    }

    /**
     * The conditions of a new report.
     *
     * @return array
     */
    #[\Override]
    public function get_default_conditions(): array {
        return [];
    }
}
