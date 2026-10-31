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

/**
 * Tests for the privacy provider.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_evidencehub\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_evidencehub\local\audit;
use local_evidencehub\local\dossier_export;
use local_evidencehub\local\hub_filter;
use local_evidencehub\local\meta;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Two workers, one verifier who acted on both, one requester who issued a dossier about each.
 *
 * Every deletion test counts the other worker's rows as the control: a provider that deleted
 * everything would pass a test that only looked at the subject.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var array The users and rows of the scenario, filled by setUp(). */
    private array $w = [];

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();
        $gen = $dg->get_plugin_generator('local_evidencehub');
        $cgen = $dg->get_plugin_generator('core_competency');

        $w = [];
        foreach (['a', 'b', 'verifier', 'requester', 'other'] as $name) {
            $w[$name] = $dg->create_user();
        }
        $framework = $cgen->create_framework();
        $w['competency'] = $cgen->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $w['template'] = $cgen->create_template();
        $templateid = (int) $w['template']->get('id');
        $competencyid = (int) $w['competency']->get('id');

        $verified = ['verifystatus' => meta::VERIFY_VERIFIED, 'verifiedby' => $w['verifier']->id, 'timeverified' => time()];
        $w['metaa'] = $gen->create_meta(['userid' => $w['a']->id] + $verified);
        $w['metaa2'] = $gen->create_meta(['userid' => $w['a']->id, 'expirydate' => null, 'noexpiry' => 1]);
        $w['metab'] = $gen->create_meta(['userid' => $w['b']->id] + $verified);
        $gen->create_status(['userid' => $w['a']->id, 'competencyid' => $competencyid, 'effectiveexpiry' => 20280101]);
        $gen->create_status(['userid' => $w['b']->id, 'competencyid' => $competencyid]);
        $gen->create_audit($w['metaa'], (int) $w['a']->id);
        $gen->create_audit($w['metaa'], (int) $w['verifier']->id, audit::ACTION_VERIFIED);
        $gen->create_audit($w['metab'], (int) $w['verifier']->id, audit::ACTION_VERIFIED);
        $w['export'] = $gen->create_export([
            'requestedby' => $w['requester']->id,
            'templateid' => $templateid,
            'subjectcount' => 2,
        ]);
        foreach (['a', 'b'] as $name) {
            $w['dossier' . $name] = $gen->create_dossier([
                'userid' => $w[$name]->id,
                'templateid' => $templateid,
                'exportid' => $w['export']->id,
                'createdby' => $w['requester']->id,
            ]);
        }
        $this->w = $w;
    }

    /**
     * The user context of one of the scenario's users.
     *
     * @param string $name A key of the scenario.
     * @return \context_user
     */
    private function ctx(string $name): \context_user {
        return \context_user::instance($this->w[$name]->id);
    }

    /**
     * How many rows each table holds about a worker.
     *
     * @param string $name A key of the scenario.
     * @return array Table name => count.
     */
    private function rows_about(string $name): array {
        global $DB;
        $userid = $this->w[$name]->id;
        return [
            'meta' => $DB->count_records('local_evidencehub_meta', ['userid' => $userid]),
            'status' => $DB->count_records('local_evidencehub_status', ['userid' => $userid]),
            'audit' => $DB->count_records('local_evidencehub_audit', ['relateduserid' => $userid]),
            'dossier' => $DB->count_records('local_evidencehub_dossier', ['userid' => $userid]),
        ];
    }

    /**
     * The export path of an evidence's details.
     *
     * @param \stdClass $meta The details row.
     * @return array
     */
    private function details_path(\stdClass $meta): array {
        global $DB;
        $name = $DB->get_field('competency_userevidence', 'name', ['id' => $meta->userevidenceid]);
        return [
            get_string('pluginname', 'local_evidencehub'),
            get_string('privacy:path:details', 'local_evidencehub'),
            $name . ' (' . $meta->userevidenceid . ')',
        ];
    }

    /**
     * A path under the plugin's folder.
     *
     * @param string $key The privacy:path:* string id.
     * @param string ...$more Further folders.
     * @return array
     */
    private function path(string $key, string ...$more): array {
        return array_merge([get_string('pluginname', 'local_evidencehub'), get_string($key, 'local_evidencehub')], $more);
    }

    /**
     * The plugin counts as compliant: it implements the metadata provider and a data provider.
     *
     * Core's own compliance test sweeps every component but is not in the plugin's testsuite,
     * which is all moodle-plugin-ci runs, so the check is repeated here.
     *
     * @return void
     */
    public function test_the_component_is_compliant(): void {
        $this->assertTrue((new \core_privacy\manager())->component_is_compliant('local_evidencehub'));
    }

    /**
     * Every table with a user column is declared, and every declared field is a real column.
     *
     * The second half catches a renamed column that the metadata still names.
     *
     * @return void
     */
    public function test_the_metadata_names_real_columns_of_every_table_with_user_data(): void {
        global $DB;
        $declared = [];
        $links = [];
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('local_evidencehub'));
        foreach ($collection->get_collection() as $item) {
            if ($item instanceof \core_privacy\local\metadata\types\database_table) {
                $declared[] = $item->get_name();
                $columns = $DB->get_columns($item->get_name());
                foreach (array_keys($item->get_privacy_fields()) as $field) {
                    $this->assertArrayHasKey($field, $columns, $item->get_name() . '.' . $field);
                }
            } else if ($item instanceof \core_privacy\local\metadata\types\subsystem_link) {
                $links[] = $item->get_name();
            }
        }
        sort($declared);
        $this->assertSame([
            'local_evidencehub_audit',
            'local_evidencehub_dossier',
            'local_evidencehub_export',
            'local_evidencehub_meta',
            'local_evidencehub_status',
        ], $declared);
        $this->assertSame(['core_competency', 'core_files'], $links);

        // The two tables left out hold configuration only: no column refers to a user.
        foreach (['local_evidencehub_type', 'local_evidencehub_policy'] as $table) {
            foreach (array_keys($DB->get_columns($table)) as $column) {
                $this->assertDoesNotMatchRegularExpression('/user|by$/', $column, $table . '.' . $column);
            }
        }
    }

    /**
     * Workers find their own context, actors the contexts of the workers they acted on, and an
     * uninvolved user nothing.
     *
     * @return void
     */
    public function test_contexts_for_workers_actors_and_others(): void {
        $ids = fn(string $name): array => array_map(
            'intval',
            $this->get_contexts_for_userid((int) $this->w[$name]->id, 'local_evidencehub')->get_contextids()
        );
        $sorted = function (array $ids): array {
            sort($ids);
            return $ids;
        };
        $this->assertSame([$this->ctx('a')->id], $ids('a'));
        $this->assertSame([$this->ctx('b')->id], $ids('b'));
        $this->assertSame($sorted([$this->ctx('a')->id, $this->ctx('b')->id]), $sorted($ids('verifier')));
        $this->assertSame(
            $sorted([$this->ctx('a')->id, $this->ctx('b')->id, $this->ctx('requester')->id]),
            $sorted($ids('requester'))
        );
        $this->assertSame([], $ids('other'));
    }

    /**
     * A worker's context lists the worker and every actor on the worker's rows.
     *
     * @return void
     */
    public function test_users_in_a_worker_context(): void {
        $userlist = new userlist($this->ctx('a'), 'local_evidencehub');
        provider::get_users_in_context($userlist);
        $userids = array_map('intval', $userlist->get_userids());
        sort($userids);
        $expected = array_map('intval', [$this->w['a']->id, $this->w['verifier']->id, $this->w['requester']->id]);
        sort($expected);
        $this->assertSame($expected, $userids);

        $userlist = new userlist($this->ctx('requester'), 'local_evidencehub');
        provider::get_users_in_context($userlist);
        $this->assertSame([(int) $this->w['requester']->id], array_map('intval', $userlist->get_userids()));

        $userlist = new userlist(\context_system::instance(), 'local_evidencehub');
        provider::get_users_in_context($userlist);
        $this->assertSame([], $userlist->get_userids());
    }

    /**
     * A worker's export carries the details of every evidence, the whole trail, the dossiers and
     * the validity of each competency.
     *
     * @return void
     */
    public function test_a_worker_exports_everything_about_them(): void {
        $this->export_context_data_for_user((int) $this->w['a']->id, $this->ctx('a'), 'local_evidencehub');
        $writer = writer::with_context($this->ctx('a'));

        $details = $writer->get_data($this->details_path($this->w['metaa']));
        $this->assertSame('2026-01-01', $details->issuedate);
        $this->assertSame('2028-01-01', $details->expirydate);
        $this->assertSame(get_string('verify_verified', 'local_evidencehub'), $details->verifystatus);
        $this->assertSame(get_string('no'), $details->verifiedbyyou);
        $undated = $writer->get_data($this->details_path($this->w['metaa2']));
        $this->assertNull($undated->expirydate);
        $this->assertSame(get_string('yes'), $undated->noexpiry);

        $this->assertCount(2, $writer->get_data($this->path('privacy:path:trail'))->entries);
        $statuses = $writer->get_data($this->path('privacy:path:statuses'))->competencies;
        $this->assertCount(1, $statuses);
        $this->assertSame(get_string('status_valid', 'local_evidencehub'), $statuses[0]->status);
        $this->assertSame('2028-01-01', $statuses[0]->effectiveexpiry);
        $dossier = $writer->get_data($this->path('privacy:path:dossiers', $this->w['dossiera']->code));
        $this->assertSame($this->w['dossiera']->code, $dossier->code);
        $this->assertSame(get_string('no'), $dossier->createdbyyou);

        // Worker b's rows stay out of a's export.
        $this->assertFalse(writer::with_context($this->ctx('b'))->has_any_data());
    }

    /**
     * In a worker's context an actor exports only what they did: the details they verified, their
     * own trail entries, the dossiers they issued; never the worker's validity.
     *
     * @return void
     */
    public function test_an_actor_exports_only_their_own_actions(): void {
        $this->export_context_data_for_user((int) $this->w['verifier']->id, $this->ctx('a'), 'local_evidencehub');
        $writer = writer::with_context($this->ctx('a'));
        $this->assertSame(get_string('yes'), $writer->get_data($this->details_path($this->w['metaa']))->verifiedbyyou);
        $this->assertSame([], (array) $writer->get_data($this->details_path($this->w['metaa2'])));
        $entries = $writer->get_data($this->path('privacy:path:trail'))->entries;
        $this->assertCount(1, $entries);
        $this->assertSame(get_string('yes'), $entries[0]->byyou);
        $this->assertSame([], (array) $writer->get_data($this->path('privacy:path:statuses')));
        $this->assertSame([], (array) $writer->get_data($this->path('privacy:path:dossiers', $this->w['dossiera']->code)));

        writer::reset();
        $this->export_context_data_for_user((int) $this->w['requester']->id, $this->ctx('a'), 'local_evidencehub');
        $writer = writer::with_context($this->ctx('a'));
        $dossier = $writer->get_data($this->path('privacy:path:dossiers', $this->w['dossiera']->code));
        $this->assertSame(get_string('yes'), $dossier->createdbyyou);
        $this->assertSame([], (array) $writer->get_data($this->details_path($this->w['metaa'])));
        // The requester's own export requests belong to their context, not to the worker's.
        $this->assertSame([], (array) $writer->get_data($this->path('privacy:path:requests')));

        writer::reset();
        $this->export_context_data_for_user((int) $this->w['requester']->id, $this->ctx('requester'), 'local_evidencehub');
        $requests = writer::with_context($this->ctx('requester'))->get_data($this->path('privacy:path:requests'))->requests;
        $this->assertCount(1, $requests);
        $this->assertSame(2, $requests[0]->subjectcount);
    }

    /**
     * The ZIP prepared for a requester is exported with their requests and deleted with their data; another
     * requester's ZIP stays (the control).
     *
     * @return void
     */
    public function test_the_prepared_zip_is_exported_and_deleted(): void {
        $path = make_request_directory() . '/zip';
        file_put_contents($path, 'zip bytes');
        $file = dossier_export::store($this->w['export'], $path);
        $gen = $this->getDataGenerator()->get_plugin_generator('local_evidencehub');
        $otherexport = $gen->create_export(['requestedby' => $this->w['other']->id, 'templateid' => 1]);
        $otherfile = dossier_export::store($otherexport, $path);

        $this->export_context_data_for_user((int) $this->w['requester']->id, $this->ctx('requester'), 'local_evidencehub');
        $files = writer::with_context($this->ctx('requester'))->get_files(
            $this->path('privacy:path:requests', (string) $this->w['export']->id)
        );
        $this->assertSame([$file->get_filename()], array_keys($files));
        $requests = writer::with_context($this->ctx('requester'))->get_data($this->path('privacy:path:requests'))->requests;
        $this->assertSame(get_string('export_done', 'local_evidencehub'), $requests[0]->status);

        provider::delete_data_for_user(new approved_contextlist(
            $this->w['requester'],
            'local_evidencehub',
            [$this->ctx('requester')->id]
        ));
        $fs = get_file_storage();
        $this->assertFalse($fs->file_exists_by_hash($file->get_pathnamehash()));
        $this->assertTrue($fs->file_exists_by_hash($otherfile->get_pathnamehash()));
    }

    /**
     * Deleting a worker's data clears every row about them and leaves the other worker's rows and
     * core's evidence (core_competency's provider owns it).
     *
     * @return void
     */
    public function test_deleting_a_worker_clears_their_rows_only(): void {
        global $DB;
        $before = $this->rows_about('b');
        $this->assertSame(['meta' => 2, 'status' => 1, 'audit' => 2, 'dossier' => 1], $this->rows_about('a'));

        provider::delete_data_for_user(new approved_contextlist($this->w['a'], 'local_evidencehub', [$this->ctx('a')->id]));

        $this->assertSame(['meta' => 0, 'status' => 0, 'audit' => 0, 'dossier' => 0], $this->rows_about('a'));
        $this->assertSame($before, $this->rows_about('b'));
        $this->assertTrue($DB->record_exists('competency_userevidence', ['id' => $this->w['metaa']->userevidenceid]));
    }

    /**
     * Deleting an actor's data leaves the workers' rows, actor columns included, as core_competency
     * does for usermodified.
     *
     * @return void
     */
    public function test_deleting_an_actor_leaves_the_workers_rows(): void {
        global $DB;
        // The verifier is a worker too, and their own context is not on the list.
        $this->getDataGenerator()->get_plugin_generator('local_evidencehub')->create_status([
            'userid' => $this->w['verifier']->id,
            'competencyid' => $this->w['competency']->get('id'),
        ]);
        $before = [$this->rows_about('a'), $this->rows_about('b'), $this->rows_about('verifier')];

        provider::delete_data_for_user(new approved_contextlist(
            $this->w['verifier'],
            'local_evidencehub',
            [$this->ctx('a')->id, $this->ctx('b')->id]
        ));

        $this->assertSame($before, [$this->rows_about('a'), $this->rows_about('b'), $this->rows_about('verifier')]);
        $this->assertSame(1, $before[2]['status'], 'Precondition: the verifier holds a row of their own.');
        $this->assertSame(2, $DB->count_records('local_evidencehub_meta', ['verifiedby' => $this->w['verifier']->id]));
    }

    /**
     * Deleting a requester removes their export requests; the dossiers of those exports describe
     * other workers, so they stay and lose only the link.
     *
     * @return void
     */
    public function test_deleting_a_requester_keeps_the_dossiers_of_their_exports(): void {
        global $DB;
        provider::delete_data_for_user(new approved_contextlist(
            $this->w['requester'],
            'local_evidencehub',
            [$this->ctx('requester')->id, $this->ctx('a')->id, $this->ctx('b')->id]
        ));

        $this->assertFalse($DB->record_exists('local_evidencehub_export', ['id' => $this->w['export']->id]));
        foreach (['dossiera', 'dossierb'] as $key) {
            $dossier = $DB->get_record('local_evidencehub_dossier', ['id' => $this->w[$key]->id], '*', MUST_EXIST);
            $this->assertNull($dossier->exportid);
        }
    }

    /**
     * A course context whose instance id equals worker a's user id.
     *
     * Every provider method reads the worker from the context's instance id, so only a context of
     * another level carrying the same number shows whether the level is checked; the system
     * context's instance id is 0 and matches no row.
     *
     * @return \context_course
     */
    private function course_context_numbered_like_a(): \context_course {
        global $DB;
        // A context row, not a course: the PHPUnit reset starts each table's sequence at its own
        // offset, so no course created here is ever numbered like a user.
        $userid = (int) $this->w['a']->id;
        $id = $DB->insert_record('context', ['contextlevel' => CONTEXT_COURSE, 'instanceid' => $userid, 'depth' => 2]);
        $DB->set_field('context', 'path', '/' . SYSCONTEXTID . '/' . $id, ['id' => $id]);
        $context = \context::instance_by_id($id);
        $this->assertInstanceOf(\context_course::class, $context);
        $this->assertSame($userid, (int) $context->instanceid, 'Precondition: a course context numbered like worker a.');
        return $context;
    }

    /**
     * A context of another level never reaches worker a's rows, whatever its instance id.
     *
     * @return void
     */
    public function test_a_context_of_another_level_is_never_read_as_a_worker(): void {
        $course = $this->course_context_numbered_like_a();
        $full = ['meta' => 2, 'status' => 1, 'audit' => 2, 'dossier' => 1];

        $userlist = new userlist($course, 'local_evidencehub');
        provider::get_users_in_context($userlist);
        $this->assertSame([], $userlist->get_userids());

        $this->export_context_data_for_user((int) $this->w['a']->id, $course, 'local_evidencehub');
        $this->assertFalse(writer::with_context($course)->has_any_data());

        provider::delete_data_for_all_users_in_context($course);
        $this->assertSame($full, $this->rows_about('a'));
        provider::delete_data_for_user(new approved_contextlist($this->w['a'], 'local_evidencehub', [$course->id]));
        $this->assertSame($full, $this->rows_about('a'));
        provider::delete_data_for_users(new approved_userlist($course, 'local_evidencehub', [$this->w['a']->id]));
        $this->assertSame($full, $this->rows_about('a'));

        // Control: the same calls on a's own context do reach the rows.
        provider::delete_data_for_all_users_in_context($this->ctx('a'));
        $this->assertSame(['meta' => 0, 'status' => 0, 'audit' => 0, 'dossier' => 0], $this->rows_about('a'));
    }

    /**
     * Deleting a whole user context clears that worker; a system context changes nothing.
     *
     * @return void
     */
    public function test_deleting_all_users_in_a_context(): void {
        $before = $this->rows_about('b');
        provider::delete_data_for_all_users_in_context(\context_system::instance());
        $this->assertSame(['meta' => 2, 'status' => 1, 'audit' => 2, 'dossier' => 1], $this->rows_about('a'));

        provider::delete_data_for_all_users_in_context($this->ctx('a'));
        $this->assertSame(['meta' => 0, 'status' => 0, 'audit' => 0, 'dossier' => 0], $this->rows_about('a'));
        $this->assertSame($before, $this->rows_about('b'));
    }

    /**
     * A user list clears the worker of the context only when the worker is on the list.
     *
     * @return void
     */
    public function test_deleting_users_in_a_context_needs_its_worker_on_the_list(): void {
        $actor = new approved_userlist($this->ctx('a'), 'local_evidencehub', [$this->w['verifier']->id]);
        provider::delete_data_for_users($actor);
        $this->assertSame(['meta' => 2, 'status' => 1, 'audit' => 2, 'dossier' => 1], $this->rows_about('a'));

        $both = new approved_userlist($this->ctx('a'), 'local_evidencehub', [$this->w['verifier']->id, $this->w['a']->id]);
        provider::delete_data_for_users($both);
        $this->assertSame(['meta' => 0, 'status' => 0, 'audit' => 0, 'dossier' => 0], $this->rows_about('a'));
    }

    /**
     * The last hub filter is declared and exported as the user's preference; another user's is not.
     *
     * @return void
     */
    public function test_the_hub_filter_preference_is_exported(): void {
        $declared = array_map(
            fn($item): string => $item->get_name(),
            provider::get_metadata(new \core_privacy\local\metadata\collection('local_evidencehub'))->get_collection()
        );
        $this->assertContains(hub_filter::PREFERENCE, $declared);

        $value = json_encode(['templateid' => 5, 'statuses' => [5]]);
        set_user_preference(hub_filter::PREFERENCE, $value, $this->w['a']->id);
        set_user_preference(hub_filter::PREFERENCE, json_encode(['templateid' => 6]), $this->w['b']->id);
        provider::export_user_preferences((int) $this->w['a']->id);
        $exported = writer::with_context(\context_system::instance())->get_user_preferences('local_evidencehub');
        $this->assertSame($value, $exported->{hub_filter::PREFERENCE}->value);

        // The control: a user who stored none exports none.
        writer::reset();
        provider::export_user_preferences((int) $this->w['verifier']->id);
        $this->assertFalse(writer::with_context(\context_system::instance())->has_any_data());
    }
}
