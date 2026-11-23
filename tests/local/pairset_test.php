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

use core_reportbuilder\manager;
use core_reportbuilder\table\system_report_table;
use core_reportbuilder\tests\core_reportbuilder_testcase;
use local_evidencehub\reportbuilder\datasource\validity;
use local_evidencehub\reportbuilder\local\systemreports\hub_pairs;
use local_evidencehub\tests\hub_scenario;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The pair set's restricted rule: each of its terms, and the same pairs restricted on every surface that
 * reads it (the hub's base set, the custom-report datasource and the system report).
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(pairset::class)]
final class pairset_test extends core_reportbuilder_testcase {
    use hub_scenario;

    /** @var array The capabilities of the reader: every worker, no viewsensitive. */
    private const array READER = [
        'local/evidencehub:view',
        'moodle/competency:templateview',
        'moodle/competency:userevidenceview',
        'moodle/reportbuilder:view',
    ];

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->setAdminUser();
    }

    /**
     * Four workers of one template, each pair restricted or not by one term of the rule, and a reader.
     *
     * The reader is a worker too. Every pair but one is spared by exactly one term: a non-sensitive
     * deciding evidence (issensitive), the reader's own pair (not the reader's own), a worker over whom
     * the reader holds viewsensitive (the sensitive fragment).
     *
     * @return array [template id, reader, "userid:competencyid" of the restricted pair, of the three spared ones].
     */
    private function fixture(): array {
        [$c1] = $this->competencies(1);
        $templateid = $this->template([$c1]);
        $restricted = $this->worker($templateid);
        $plain = $this->worker($templateid);
        $trusted = $this->worker($templateid);
        $reader = $this->worker($templateid);
        $this->status_row((int) $restricted->id, $c1, status::STATUS_VALID, 20300101, 'aso');
        $this->status_row((int) $plain->id, $c1, status::STATUS_VALID, 20300101, 'nrcertificate');
        $this->status_row((int) $trusted->id, $c1, status::STATUS_VALID, 20300101, 'aso');
        $this->status_row((int) $reader->id, $c1, status::STATUS_VALID, 20300101, 'aso');

        $system = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        foreach (self::READER as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $system->id, true);
        }
        role_assign($roleid, $reader->id, $system->id);
        $trustedcontext = \context_user::instance($trusted->id);
        $sensitiverole = $this->getDataGenerator()->create_role();
        assign_capability('local/evidencehub:viewsensitive', CAP_ALLOW, $sensitiverole, $trustedcontext->id, true);
        role_assign($sensitiverole, $reader->id, $trustedcontext->id);
        accesslib_clear_all_caches_for_unit_testing();

        return [
            $templateid,
            $reader,
            $this->pair($restricted, $c1),
            [$this->pair($plain, $c1), $this->pair($trusted, $c1), $this->pair($reader, $c1)],
        ];
    }

    /**
     * The rule over the status rows alone restricts the one pair no term spares, under either namer.
     *
     * @return void
     */
    public function test_each_term_spares_its_pair(): void {
        global $DB;
        [, $reader, $restricted, $spared] = $this->fixture();
        $this->setUser($reader);
        foreach (['hub' => pairset::names_as_given(), 'report' => pairset::report_names()] as $namer => $names) {
            [$sql, $params] = pairset::restricted('s', 's.userid', hub_scope::for_user(), $names);
            $rows = $DB->get_records_sql(
                "SELECT s.id, s.userid, s.competencyid, CASE WHEN {$sql} THEN 1 ELSE 0 END AS restricted
                   FROM {" . status_repository::TABLE . "} s",
                $params
            );
            $flags = [];
            foreach ($rows as $row) {
                $flags[$this->pair((int) $row->userid, (int) $row->competencyid)] = (int) $row->restricted;
            }
            $this->assertSame(1, $flags[$restricted], $namer);
            foreach ($spared as $pair) {
                $this->assertSame(0, $flags[$pair], $namer . ' ' . $pair);
            }
        }
    }

    /**
     * The hub's base set, the datasource and the system report restrict the same pair to the same reader.
     *
     * @return void
     */
    public function test_every_surface_restricts_the_same_pairs(): void {
        global $DB;
        [$templateid, $reader, $restricted] = $this->fixture();
        $label = status::effective_label(status::EFFECTIVE_RESTRICTED);
        $this->setUser($reader);

        $hub = array_keys(array_filter(
            $this->all_pages(\context_system::instance(), $this->filter($templateid)),
            fn(int $code): bool => $code === status::EFFECTIVE_RESTRICTED
        ));
        $this->assertSame([$restricted], $hub);

        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $persistent = $generator->create_report(['name' => 'Validity', 'source' => validity::class, 'default' => 0]);
        foreach (['user:username', 'competency:name', 'validity:status'] as $column) {
            $generator->create_column(['reportid' => $persistent->get('id'), 'uniqueidentifier' => $column]);
        }
        $datasource = [];
        foreach ($this->get_custom_report_content($persistent->get('id')) as $row) {
            [$username, , $status] = array_values($row);
            if ($status === $label) {
                $datasource[] = $username;
            }
        }
        $userid = (int) explode(':', $restricted)[0];
        $this->assertSame([$DB->get_field('user', 'username', ['id' => $userid])], $datasource);

        manager::reset_caches();
        $report = hub_pairs::for_template(\context_system::instance(), $templateid);
        $table = system_report_table::create((int) $report->get_report_persistent()->get('id'), []);
        $table->define_baseurl(new \moodle_url('/local/evidencehub/index.php'));
        $table->setup();
        $table->query_db(100, false);
        $statuses = [];
        $alias = $report->get_column('validity:status')->get_column_alias();
        foreach ($table->rawdata as $record) {
            $statuses[$record->userid . ':' . $record->competencyid] = $table->format_row($record)[$alias];
        }
        $table->close_recordset();
        $this->assertCount(4, $statuses);
        $this->assertSame([$restricted], array_keys(array_filter($statuses, fn($status): bool => $status === $label)));
    }
}
