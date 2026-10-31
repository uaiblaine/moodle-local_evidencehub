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
use local_evidencehub\output\evidence_page;
use local_evidencehub\tests\hub_scenario;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The dossiers issued about a worker: the "Shared dossiers" list on their evidence page, and revocation by
 * the dossier's creator or a site exporter only.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(dossier::class)]
#[CoversClass(dossier_revoked::class)]
#[CoversClass(evidence_page::class)]
final class dossier_test extends \advanced_testcase {
    use hub_scenario;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
    }

    /**
     * A dossier of a worker issued by a creator.
     *
     * @param \stdClass $worker The worker.
     * @param \stdClass $creator Who issued it.
     * @param int $templateid The template.
     * @return \stdClass The row.
     */
    private function issued(\stdClass $worker, \stdClass $creator, int $templateid): \stdClass {
        return $this->getDataGenerator()->get_plugin_generator('local_evidencehub')->create_dossier([
            'userid' => $worker->id,
            'templateid' => $templateid,
            'createdby' => $creator->id,
        ]);
    }

    /**
     * The creator revokes; the event names the worker; a second revocation is refused.
     *
     * @return void
     */
    public function test_the_creator_revokes(): void {
        global $DB;
        $worker = $this->getDataGenerator()->create_user();
        $creator = $this->getDataGenerator()->create_user();
        $dossier = $this->issued($worker, $creator, $this->template([]));
        $clock = $this->mock_clock_with_frozen();
        $this->setUser($creator);
        $events = $this->redirectEvents();

        dossier::revoke((int) $dossier->id, (int) $worker->id);

        $row = $DB->get_record(dossier::TABLE, ['id' => $dossier->id]);
        $this->assertSame(1, (int) $row->revoked);
        $this->assertSame($clock->time(), (int) $row->timerevoked);
        $revoked = array_values(array_filter($events->get_events(), fn($e) => $e instanceof dossier_revoked));
        $this->assertCount(1, $revoked);
        $this->assertSame((int) $worker->id, (int) $revoked[0]->relateduserid);
        $this->expectExceptionMessage(get_string('errordossierrevoked', 'local_evidencehub'));
        dossier::revoke((int) $dossier->id, (int) $worker->id);
    }

    /**
     * Anyone else needs exportdossier on the site; a category exporter is refused; a dossier is revoked only
     * from its own worker's page.
     *
     * @return void
     */
    public function test_others_need_exportdossier_on_the_site(): void {
        global $DB;
        $worker = $this->getDataGenerator()->create_user();
        $creator = $this->getDataGenerator()->create_user();
        $templateid = $this->template([]);
        $dossier = $this->issued($worker, $creator, $templateid);
        $category = \context_coursecat::instance($this->getDataGenerator()->create_category()->id);

        foreach ([$this->viewer([]), $this->viewer(['local/evidencehub:exportdossier'], $category)] as $user) {
            $this->setUser($user);
            try {
                dossier::revoke((int) $dossier->id, (int) $worker->id);
                $this->fail('Revoked without exportdossier on the site.');
            } catch (\required_capability_exception $e) {
                $this->assertSame(0, (int) $DB->get_field(dossier::TABLE, 'revoked', ['id' => $dossier->id]));
            }
        }

        $this->setUser($this->viewer(['local/evidencehub:exportdossier']));
        try {
            dossier::revoke((int) $dossier->id, (int) $creator->id);
            $this->fail('Revoked from another worker\'s page.');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidrecord', $e->errorcode);
        }
        dossier::revoke((int) $dossier->id, (int) $worker->id);
        $this->assertSame(1, (int) $DB->get_field(dossier::TABLE, 'revoked', ['id' => $dossier->id]));
    }

    /**
     * The worker's evidence page lists the dossiers about them, newest first, with who issued them, for which
     * template and whether they were revoked; the revoke button shows to who may revoke.
     *
     * @return void
     */
    public function test_the_evidence_page_lists_the_shared_dossiers(): void {
        global $DB, $PAGE;
        $worker = $this->getDataGenerator()->create_user();
        // The creator reads the worker's evidence, as the page requires.
        $creator = $this->viewer(['moodle/competency:userevidenceview']);
        $DB->update_record('user', (object) ['id' => $creator->id, 'firstname' => 'Carla', 'lastname' => 'Menezes']);
        $templateid = $this->template([]);
        $DB->set_field('competency_template', 'shortname', 'Line 4 & Co', ['id' => $templateid]);
        $older = $this->issued($worker, $creator, $templateid);
        $DB->set_field(dossier::TABLE, 'timecreated', time() - DAYSECS, ['id' => $older->id]);
        $newer = $this->issued($worker, $creator, $templateid);
        $DB->set_field(dossier::TABLE, 'revoked', 1, ['id' => $older->id]);
        $this->issued($this->getDataGenerator()->create_user(), $creator, $templateid);
        $output = $PAGE->get_renderer('core');

        $this->setUser($worker);
        $export = (new evidence_page((int) $worker->id))->export_for_template($output);
        $this->assertTrue($export['hasdossiers']);
        $this->assertSame([(int) $newer->id, (int) $older->id], array_column($export['dossiers'], 'id'));
        $this->assertSame('Carla Menezes', $export['dossiers'][0]['createdby']);
        $this->assertSame('Line 4 & Co', $export['dossiers'][0]['template']);
        $this->assertSame([false, true], array_column($export['dossiers'], 'revoked'));
        // The worker neither created them nor exports on the site.
        $this->assertSame([false, false], array_column($export['dossiers'], 'canrevoke'));
        $html = $output->render_from_template('local_evidencehub/evidence_page', $export);
        $this->assertStringContainsString('Line 4 &amp; Co', $html);
        $this->assertStringNotContainsString('value="revokedossier"', $html);

        // The creator may revoke the dossier still in force.
        $this->setUser($creator);
        $export = (new evidence_page((int) $worker->id))->export_for_template($output);
        $this->assertSame([true, false], array_column($export['dossiers'], 'canrevoke'));
        $html = $output->render_from_template('local_evidencehub/evidence_page', $export);
        $this->assertStringContainsString('name="dossierid" value="' . $newer->id . '"', $html);
    }
}
