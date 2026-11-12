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
 * Tests for the validity pills.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_evidencehub\local;

use local_evidencehub\tests\scenario;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Each status reads with its tone; a sensitive pair is restricted to who may not see it; a viewer who cannot
 * read the worker gets nothing; a missing pair shows only under an explicit policy.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(validity_badges::class)]
final class validity_badges_test extends \advanced_testcase {
    use scenario;

    /** @var \core_competency\competency_framework The framework. */
    private $framework;

    /** @var \core_competency\competency The competency. */
    private $competency;

    /** @var \stdClass The worker. */
    private \stdClass $worker;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $this->framework = $generator->create_framework();
        $this->competency = $generator->create_competency(['competencyframeworkid' => $this->framework->get('id')]);
        $this->worker = $this->getDataGenerator()->create_user();
    }

    /**
     * A status row of the worker for a competency.
     *
     * @param int $status A status constant.
     * @param int|null $expiry The effective expiry, YYYYMMDD.
     * @param int $sensitive Whether the deciding evidence is sensitive.
     * @param \core_competency\competency|null $competency The competency; the setup's when null.
     * @return void
     */
    private function status_row(int $status, ?int $expiry = 20270301, int $sensitive = 0, $competency = null): void {
        $this->getDataGenerator()->get_plugin_generator('local_evidencehub')->create_status([
            'userid' => $this->worker->id,
            'competencyid' => ($competency ?? $this->competency)->get('id'),
            'status' => $status,
            'effectiveexpiry' => $expiry,
            'issensitive' => $sensitive,
        ]);
    }

    /**
     * An explicit policy row of the setup's framework, written directly.
     *
     * @param int $competencyid The competency, or 0 for the framework's own policy.
     * @return void
     */
    private function policy_row(int $competencyid): void {
        global $DB;
        $DB->insert_record(policy::TABLE, (object) [
            'frameworkid' => $this->framework->get('id'),
            'competencyid' => $competencyid,
            'kind' => policy::KIND_TECHNICAL,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * The pill of the setup's competency, for the current user.
     *
     * @return array|null The label and tone, or null when none.
     */
    private function pill(): ?array {
        $id = (int) $this->competency->get('id');
        return validity_badges::for_user((int) $this->worker->id, [$id])[$id] ?? null;
    }

    /**
     * A manager over every worker, reading sensitive evidence or not.
     *
     * @param bool $sensitive Whether the manager holds viewsensitive.
     * @return \stdClass The user.
     */
    private function manager(bool $sensitive = false): \stdClass {
        $caps = ['moodle/competency:userevidenceview'];
        return $this->user_with($sensitive ? array_merge($caps, ['local/evidencehub:viewsensitive']) : $caps);
    }

    /**
     * Every status, with its label, tone and whether the expiry is shown.
     *
     * @return array Name => [status, expected English label, tone, dated].
     */
    public static function statuses(): array {
        return [
            'compliant' => [status::STATUS_VALID, 'Compliant', 'success', true],
            'due' => [status::STATUS_DUE, 'Due soon', 'warning', true],
            'critical' => [status::STATUS_CRITICAL, 'Due, critical', 'danger', true],
            'expired' => [status::STATUS_EXPIRED, 'Expired', 'danger', true],
            'unverified' => [status::STATUS_UNVERIFIED, 'Pending, awaiting verification', 'info', false],
        ];
    }

    /**
     * Each status reads with its label and tone; the four dated states carry the expiry.
     *
     * @param int $status The status.
     * @param string $name The English label.
     * @param string $tone The tone.
     * @param bool $dated Whether the expiry is shown.
     * @return void
     */
    #[DataProvider('statuses')]
    public function test_each_status_reads_with_its_tone_and_expiry(int $status, string $name, string $tone, bool $dated): void {
        $this->status_row($status);
        $this->setUser($this->manager());
        $pill = $this->pill();
        $this->assertSame($tone, $pill['tone']);
        $this->assertSame($dated ? "Validity: $name, 2027-03-01" : "Validity: $name", $pill['label']);
    }

    /**
     * A dated state whose deciding evidence never expires has no date to show.
     *
     * @return void
     */
    public function test_a_pair_that_never_expires_shows_no_date(): void {
        $this->status_row(status::STATUS_VALID, null);
        $this->setUser($this->manager());
        $this->assertSame('Validity: Compliant', $this->pill()['label']);
    }

    /**
     * A sensitive pair reads restricted, without its date, to who may not see sensitive evidence.
     *
     * @return void
     */
    public function test_a_sensitive_pair_is_restricted_without_viewsensitive(): void {
        $this->status_row(status::STATUS_VALID, 20270301, 1);
        $this->setUser($this->manager());
        $this->assertSame(['label' => 'Validity: Restricted', 'tone' => 'neutral'], $this->pill());
    }

    /**
     * A pair whose deciding evidence's type became sensitive after the last recompute is restricted too, as
     * the drawer and the dossier read it; control: with viewsensitive it reads as it is.
     *
     * @return void
     */
    public function test_a_pair_whose_type_became_sensitive_is_restricted(): void {
        global $DB;
        $generator = $this->getDataGenerator()->get_plugin_generator('local_evidencehub');
        $typeid = (int) $DB->get_field('local_evidencehub_type', 'id', ['shortname' => 'aso'], MUST_EXIST);
        $meta = $generator->create_meta(['userid' => $this->worker->id, 'typeid' => $typeid]);
        // The stored flag is stale: it predates the type's.
        $generator->create_status([
            'userid' => $this->worker->id,
            'competencyid' => $this->competency->get('id'),
            'status' => status::STATUS_VALID,
            'effectiveexpiry' => 20270301,
            'metaid' => $meta->id,
            'issensitive' => 0,
        ]);

        $this->setUser($this->manager());
        $this->assertSame(['label' => 'Validity: Restricted', 'tone' => 'neutral'], $this->pill());
        $this->setUser($this->manager(true));
        $this->assertSame('Validity: Compliant, 2027-03-01', $this->pill()['label']);
    }

    /**
     * A guest is refused as evidence.php refuses one, even where the guest role may read evidence; control:
     * a user holding the same capability sees the pill.
     *
     * @return void
     */
    public function test_a_guest_is_refused_even_where_guests_may_read_evidence(): void {
        global $CFG;
        $this->status_row(status::STATUS_VALID);
        assign_capability('moodle/competency:userevidenceview', CAP_ALLOW, $CFG->guestroleid, \context_system::instance());
        $this->setGuestUser();
        $this->assertNull($this->pill());

        $this->setUser($this->manager());
        $this->assertNotNull($this->pill());
    }

    /**
     * A deleted or unknown worker gets nothing, without an error; control: the worker before deletion.
     *
     * @return void
     */
    public function test_a_deleted_or_unknown_worker_gets_nothing(): void {
        $this->status_row(status::STATUS_VALID);
        $this->setAdminUser();
        $this->assertNotNull($this->pill());

        delete_user($this->worker);
        $this->assertNull($this->pill());
        $this->assertSame([], validity_badges::for_user(999999, [(int) $this->competency->get('id')]));
    }

    /**
     * Control: with viewsensitive, and the worker themself, the sensitive pair reads as it is.
     *
     * @return void
     */
    public function test_a_sensitive_pair_is_shown_to_who_may_see_it(): void {
        $this->status_row(status::STATUS_VALID, 20270301, 1);
        $this->setUser($this->manager(true));
        $this->assertSame('Validity: Compliant, 2027-03-01', $this->pill()['label']);
        $this->setUser($this->worker);
        $this->grant($this->worker, ['moodle/competency:userevidenceview']);
        $this->assertSame('Validity: Compliant, 2027-03-01', $this->pill()['label']);
    }

    /**
     * A non-sensitive pair is not restricted to a viewer without viewsensitive.
     *
     * @return void
     */
    public function test_a_plain_pair_is_not_restricted(): void {
        $this->status_row(status::STATUS_DUE);
        $this->setUser($this->manager());
        $this->assertSame('Validity: Due soon, 2027-03-01', $this->pill()['label']);
    }

    /**
     * A viewer who cannot read the worker's evidence gets nothing; a mentor through a user-context role does.
     *
     * @return void
     */
    public function test_a_viewer_who_cannot_read_the_worker_gets_nothing(): void {
        $this->status_row(status::STATUS_VALID);
        $stranger = $this->getDataGenerator()->create_user();
        $this->setUser($stranger);
        $this->assertNull($this->pill());

        // Control: a mentor holding the capability only in the worker's user context.
        $roleid = $this->getDataGenerator()->create_role();
        set_role_contextlevels($roleid, [CONTEXT_USER]);
        assign_capability('moodle/competency:userevidenceview', CAP_ALLOW, $roleid, \context_system::instance()->id, true);
        role_assign($roleid, $stranger->id, \context_user::instance((int) $this->worker->id)->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertSame('success', $this->pill()['tone']);
    }

    /**
     * A guest gets nothing, nor does anyone with competencies disabled.
     *
     * @return void
     */
    public function test_a_guest_and_disabled_competencies_get_nothing(): void {
        $this->status_row(status::STATUS_VALID);
        $this->setGuestUser();
        $this->assertNull($this->pill());

        $this->setUser($this->manager());
        $this->assertNotNull($this->pill());
        set_config('enabled', 0, 'core_competency');
        $this->assertNull($this->pill());
    }

    /**
     * A pair with no status row shows "no evidence" under an explicit competency policy.
     *
     * @return void
     */
    public function test_a_missing_pair_under_a_competency_policy_shows_no_evidence(): void {
        $this->policy_row($this->competency->get('id'));
        $this->setUser($this->manager());
        $this->assertSame(['label' => 'Validity: Pending, no evidence', 'tone' => 'neutral'], $this->pill());
    }

    /**
     * A framework policy covers its competencies too.
     *
     * @return void
     */
    public function test_a_missing_pair_under_a_framework_policy_shows_no_evidence(): void {
        $this->policy_row(0);
        $this->setUser($this->manager());
        $this->assertSame('Validity: Pending, no evidence', $this->pill()['label']);
    }

    /**
     * Control: under the built-in default policy a missing pair shows nothing; another competency's own
     * policy does not cover it.
     *
     * @return void
     */
    public function test_a_missing_pair_under_the_default_policy_shows_nothing(): void {
        $this->setUser($this->manager());
        $this->assertNull($this->pill());

        $other = $this->getDataGenerator()->get_plugin_generator('core_competency')
            ->create_competency(['competencyframeworkid' => $this->framework->get('id')]);
        $this->policy_row($other->get('id'));
        $this->assertNull($this->pill());
        $otherid = (int) $other->get('id');
        $this->assertArrayHasKey($otherid, validity_badges::for_user((int) $this->worker->id, [$otherid]));
    }

    /**
     * Only the asked competencies are answered, and a status of another worker is not read.
     *
     * @return void
     */
    public function test_only_asked_competencies_of_that_worker_are_answered(): void {
        $other = $this->getDataGenerator()->get_plugin_generator('core_competency')
            ->create_competency(['competencyframeworkid' => $this->framework->get('id')]);
        $this->status_row(status::STATUS_VALID);
        $this->status_row(status::STATUS_VALID, 20270301, 0, $other);
        $this->setUser($this->manager());
        $answer = validity_badges::for_user((int) $this->worker->id, [(int) $this->competency->get('id')]);
        $this->assertSame([(int) $this->competency->get('id')], array_keys($answer));

        // Asked about another worker, the same competency answers nothing: the worker's row is not read.
        $someone = $this->getDataGenerator()->create_user();
        $this->assertSame([], validity_badges::for_user((int) $someone->id, [(int) $this->competency->get('id')]));
    }
}
