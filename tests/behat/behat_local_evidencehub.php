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
 * Behat page resolution and steps for local_evidencehub.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ExpectationException;
use Moodle\BehatExtension\Exception\SkippedException;

/**
 * Named pages of the plugin, for "I am on the ... page" steps, and the matrix's layout shift measurement.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_evidencehub extends behat_base {
    /**
     * The URL of a page about one thing: "Evidence" takes a username, "Policies" a framework's idnumber,
     * "Hub" a site-level plan template's short name (every status shown), "Matrix" likewise, the matrix
     * view at 100 workers a page, "Dossier verification" a username: the public page of the latest
     * dossier issued about that user.
     *
     * @param string $type The page type.
     * @param string $identifier The username, the framework's idnumber or the template's short name.
     * @return moodle_url
     * @throws Exception On an unknown page type.
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        global $DB;
        return match (strtolower($type)) {
            'evidence' => new moodle_url('/local/evidencehub/evidence.php', [
                'userid' => $this->get_user_id_by_identifier($identifier),
            ]),
            'policies' => new moodle_url('/local/evidencehub/policies.php', [
                'frameworkid' => $DB->get_field('competency_framework', 'id', ['idnumber' => $identifier], MUST_EXIST),
            ]),
            'hub' => new moodle_url('/local/evidencehub/index.php', [
                'contextid' => context_system::instance()->id,
                'templateid' => $DB->get_field('competency_template', 'id', ['shortname' => $identifier], MUST_EXIST),
                'statusfilter' => 1,
            ]),
            'matrix' => new moodle_url('/local/evidencehub/index.php', [
                'contextid' => context_system::instance()->id,
                'templateid' => $DB->get_field('competency_template', 'id', ['shortname' => $identifier], MUST_EXIST),
                'statusfilter' => 1,
                'view' => 'matrix',
                'perpage' => 100,
            ]),
            'dossier verification' => new moodle_url('/local/evidencehub/verify.php', [
                'code' => $DB->get_field_sql(
                    'SELECT code FROM {local_evidencehub_dossier} WHERE userid = :userid ORDER BY id DESC',
                    ['userid' => $this->get_user_id_by_identifier($identifier)],
                    IGNORE_MULTIPLE
                ),
            ]),
            default => throw new Exception('Unrecognised local_evidencehub page type "' . $type . '."'),
        };
    }

    /**
     * Sum the page's layout shifts since it loaded, as the Cumulative Layout Shift metric does: every
     * layout-shift entry without recent input, read from a PerformanceObserver with buffered entries
     * (Chrome keeps them out of performance.getEntriesByType()).
     *
     * @Then /^the layout shift of the page should be at most "(?P<limit_string>[^"]*)"$/
     * @param string $limit The largest sum accepted, e.g. 0.01.
     * @return void
     * @throws ExpectationException When the page shifted more.
     * @throws SkippedException When the browser reports no layout shifts at all.
     */
    public function the_layout_shift_of_the_page_should_be_at_most(string $limit): void {
        $supported = $this->evaluate_script('(function () {
            return typeof PerformanceObserver !== "undefined"
                && (PerformanceObserver.supportedEntryTypes || []).indexOf("layout-shift") !== -1;
        })()');
        if (!$supported) {
            throw new SkippedException('This browser does not report layout shifts.');
        }
        // The buffered entries reach the callback in a task queued by observe(), before the timer;
        // takeRecords() collects any the callback has not seen yet.
        $this->execute_script('(function () {
            var sum = 0;
            var add = function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.hadRecentInput) {
                        sum += entry.value;
                    }
                });
            };
            delete window.localEvidencehubLayoutShift;
            var observer = new PerformanceObserver(function (list) {
                add(list.getEntries());
            });
            observer.observe({type: "layout-shift", buffered: true});
            setTimeout(function () {
                add(observer.takeRecords());
                observer.disconnect();
                window.localEvidencehubLayoutShift = sum;
            }, 250);
        })()');
        $result = $this->spin(
            function (): ?array {
                return $this->evaluate_script('(function () {
                    return window.localEvidencehubLayoutShift === undefined ? null : {shift: window.localEvidencehubLayoutShift};
                })()');
            },
            false,
            10,
            new ExpectationException('The layout shift of the page could not be read.', $this->getSession()),
            true
        );
        $shift = (float) $result['shift'];
        if ($shift > (float) $limit) {
            throw new ExpectationException(
                'The page shifted its layout by ' . round($shift, 4) . ', more than ' . $limit . '.',
                $this->getSession()
            );
        }
    }
}
