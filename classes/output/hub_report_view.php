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
use core\output\single_select;
use core\output\templatable;
use local_evidencehub\local\dossier_builder;
use local_evidencehub\local\hub_access;
use local_evidencehub\local\hub_filter;
use local_evidencehub\local\reportengine;
use local_evidencehub\local\reportpreference;
use local_evidencehub\reportbuilder\local\systemreports\hub_pairs;

/**
 * The hub's list of pairs as a data report (stage E3): the shell index.php renders for a template.
 *
 * The props carry configuration and strings only; every pair and every figure arrives through the plugin's
 * web services (local_evidencehub_report_rows, _report_summary), which read the same system report
 * ({@see reportengine}). So nothing here may put a record into the props: the component is mounted by core's
 * react_autoinit from a data attribute, and whatever the props hold is in the page for anyone who reads its
 * source, whatever the component then draws. The page itself also carries core's table of the report inside
 * a noscript element (below), so its records are in the source of a page the reader may see, not in the props.
 *
 * The template is chosen from core's single select beside the heading, a GET form that works without
 * JavaScript; the client never lists templates. Core's own rendering of the report goes into the shell's
 * noscript, without its filter form (a dynamic form, which does nothing without JavaScript, and whose script
 * would look for a form the browser never builds from a noscript); "Table view" (index.php?view=table)
 * renders it whole.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hub_report_view implements renderable, templatable {
    /** @var string The view the props name, as js/esm/src/views.tsx dispatches it. */
    public const string MOUNT = 'hub';

    /**
     * The shell of one template's pairs in a hub context.
     *
     * @param \context $context The hub context: the system or a course category.
     * @param hub_pairs $report The pairs report of that template there, as index.php built it.
     */
    public function __construct(
        /** @var \context The hub context. */
        private readonly \context $context,
        /** @var hub_pairs The report. */
        private readonly hub_pairs $report,
    ) {
    }

    /**
     * The hub's list of a template: index.php with the context and the template.
     *
     * @param \context $context The hub context.
     * @param int $templateid The template.
     * @return \moodle_url
     */
    public static function url(\context $context, int $templateid): \moodle_url {
        return new \moodle_url('/local/evidencehub/index.php', ['contextid' => $context->id, 'templateid' => $templateid]);
    }

    /**
     * The template the hub opens on in a context when the request names none: the reader's last one there, while
     * the hub still offers it (decision 2).
     *
     * The preference is the reader's own and only names a template; whether it may be opened is the hub's rule,
     * asked again here, so a template the reader lost access to, or one removed, opens the choice instead.
     *
     * @param \context $context The hub context, whose view the page already required.
     * @return int The template, or 0 for none.
     */
    public static function last_template(\context $context): int {
        $templateid = reportpreference::last_template((int) $context->id);
        if ($templateid === null || hub_access::offered_template($templateid, $context) === null) {
            return 0;
        }
        return $templateid;
    }

    /**
     * The component's props: configuration and strings, never a record.
     *
     * @return array The props; see js/esm/src/hub/types.ts, ViewProps.
     */
    public function props(): array {
        $templateid = $this->report->template_id();
        $exportform = null;
        // The dock is offered to a reader the export would accept; index.php's branch checks again.
        if (dossier_builder::can_export($this->context)) {
            $exportform = [
                'action' => (new \moodle_url('/local/evidencehub/index.php'))->out(false),
                'sesskey' => sesskey(),
            ];
        }
        return [
            'mount' => self::MOUNT,
            'contextid' => (int) $this->context->id,
            'templateid' => $templateid,
            'reportid' => (int) $this->report->get_report_persistent()->get('id'),
            'perpage' => reportengine::DEFAULT_PERPAGE,
            'perpages' => reportengine::PERPAGES,
            'searchmin' => reportengine::SEARCH_MIN,
            'matrixurl' => (new \moodle_url('/local/evidencehub/index.php', [
                'contextid' => $this->context->id,
                'templateid' => $templateid,
                'view' => hub_filter::VIEW_MATRIX,
            ]))->out(false),
            'tableurl' => $this->table_url()->out(false),
            'exportform' => $exportform,
            'labels' => self::labels(),
        ];
    }

    /**
     * Core's table of the template's pairs: index.php with view=table.
     *
     * @return \moodle_url
     */
    private function table_url(): \moodle_url {
        return new \moodle_url('/local/evidencehub/index.php', [
            'contextid' => $this->context->id,
            'templateid' => $this->report->template_id(),
            'view' => hub_pairs::VIEW,
        ]);
    }

    /**
     * The strings the component draws, by the name it reads them under, in the plain spelling.
     *
     * A literal per string, so tools that check string ids find every one of them. Strings that take a value
     * arrive with their placeholder, which the client fills (js/esm/src/datareport/str.ts). The names the shared
     * parts read (datareport/) are the reference's, so a later sync with it is a diff.
     *
     * @return array Name => string.
     */
    public static function labels(): array {
        return [
            'accounts' => get_string('reportviewaccounts', 'local_evidencehub'),
            'activeonly' => get_string('reportviewactiveonly', 'local_evidencehub'),
            'all' => get_string('huball', 'local_evidencehub'),
            'always' => get_string('reportviewalways', 'local_evidencehub'),
            'any' => get_string('reportviewany', 'local_evidencehub'),
            'attention' => get_string('hubneedsattention', 'local_evidencehub'),
            'attentionsub' => get_string('reportviewattentionsub', 'local_evidencehub'),
            'attentiontile' => get_string('reportviewattentiontile', 'local_evidencehub'),
            'bandlabel' => get_string('reportviewbandlabel', 'local_evidencehub'),
            'bystatus' => get_string('reportviewbystatus', 'local_evidencehub'),
            'caption' => get_string('reportviewcaption', 'local_evidencehub'),
            'cards' => get_string('reportviewcards', 'local_evidencehub'),
            'clearall' => get_string('reportviewclearall', 'local_evidencehub'),
            'clearfilters' => get_string('reportviewclearfilters', 'local_evidencehub'),
            'cleartext' => get_string('reportviewcleartext', 'local_evidencehub'),
            'columns' => get_string('reportviewcolumns', 'local_evidencehub'),
            'coretable' => get_string('hubtableview', 'local_evidencehub'),
            'count' => get_string('hubtotal', 'local_evidencehub'),
            'count_one' => get_string('reportviewcount_one', 'local_evidencehub'),
            'defaultcolumns' => get_string('reportviewdefaultcolumns', 'local_evidencehub'),
            'dock' => get_string('reportviewdock', 'local_evidencehub'),
            'empty' => get_string('reportviewempty', 'local_evidencehub'),
            'expiring' => get_string('reportviewexpiring', 'local_evidencehub'),
            'expiringsub' => get_string('reportviewexpiringsub', 'local_evidencehub'),
            'exportdossiers' => get_string('dossierexport', 'local_evidencehub'),
            'filterchip' => get_string('reportviewfilterchip', 'local_evidencehub'),
            'filters' => get_string('reportviewfilters', 'local_evidencehub'),
            'filterson' => get_string('reportviewfilterson', 'local_evidencehub'),
            'grid' => get_string('reportviewgrid', 'local_evidencehub'),
            'hiddenworkers' => get_string('hubhiddenworkers', 'local_evidencehub'),
            'includesuspended' => get_string('hubsuspended', 'local_evidencehub'),
            'inspect' => get_string('actioninspect', 'local_evidencehub'),
            'less' => get_string('reportviewless', 'local_evidencehub'),
            'loadband' => get_string('reportviewloadband', 'local_evidencehub'),
            'loaderror' => get_string('reportviewloaderror', 'local_evidencehub'),
            'loading' => get_string('reportviewloading', 'local_evidencehub'),
            'matrix' => get_string('hubmatrix', 'local_evidencehub'),
            'more' => get_string('reportviewmore', 'local_evidencehub'),
            'morefilters' => get_string('reportviewmorefilters', 'local_evidencehub'),
            'morefiltershelp' => get_string('reportviewmorefiltersnote', 'local_evidencehub'),
            'moregroups' => get_string('reportviewmoreoptions', 'local_evidencehub'),
            'next' => get_string('reportviewnext', 'local_evidencehub'),
            'nocohortmatch' => get_string('reportviewnocohortmatch', 'local_evidencehub'),
            'nocompetencymatch' => get_string('reportviewnocompetencymatch', 'local_evidencehub'),
            'nomatch' => get_string('reportviewnomatch', 'local_evidencehub'),
            'nomatchhelp' => get_string('reportviewnomatchhelp', 'local_evidencehub'),
            'nooptionmatch' => get_string('reportviewnooptionmatch', 'local_evidencehub'),
            'noworst' => get_string('reportviewnoworst', 'local_evidencehub'),
            'pageof' => get_string('reportviewpageof', 'local_evidencehub'),
            'pages' => get_string('reportviewpages', 'local_evidencehub'),
            'perpage' => get_string('hubperpage', 'local_evidencehub'),
            'presetevidence' => get_string('reportviewpresetevidence', 'local_evidencehub'),
            'presets' => get_string('reportviewpresets', 'local_evidencehub'),
            'presetvalidity' => get_string('reportviewpresetvalidity', 'local_evidencehub'),
            'previous' => get_string('reportviewprevious', 'local_evidencehub'),
            'range' => get_string('reportviewrange', 'local_evidencehub'),
            'ready' => get_string('reportviewready', 'local_evidencehub'),
            'readynone' => get_string('reportviewreadynone', 'local_evidencehub'),
            'readysub' => get_string('reportviewreadysub', 'local_evidencehub'),
            'removefilter' => get_string('reportviewremovefilter', 'local_evidencehub'),
            'resetwidths' => get_string('reportviewresetwidths', 'local_evidencehub'),
            'resize' => get_string('reportviewresize', 'local_evidencehub'),
            'retry' => get_string('reportviewretry', 'local_evidencehub'),
            'scope' => get_string('reportviewscope', 'local_evidencehub'),
            'scrollhint' => get_string('reportviewscrollhint', 'local_evidencehub'),
            'search' => get_string('hubsearch', 'local_evidencehub'),
            'searchchip' => get_string('reportviewsearchchip', 'local_evidencehub'),
            'searchcohorts' => get_string('reportviewsearchcohorts', 'local_evidencehub'),
            'searchcompetencies' => get_string('reportviewsearchcompetencies', 'local_evidencehub'),
            'searchoptions' => get_string('reportviewsearchoptions', 'local_evidencehub'),
            'selected' => get_string('reportviewselected', 'local_evidencehub'),
            'selectedworkers' => get_string('reportviewselectedworkers', 'local_evidencehub'),
            'selectedworkers_one' => get_string('reportviewselectedworkers_one', 'local_evidencehub'),
            'selectpage' => get_string('hubselectall', 'local_evidencehub'),
            'selectrow' => get_string('hubselectrow', 'local_evidencehub'),
            'show' => get_string('reportviewshow', 'local_evidencehub'),
            'showcount' => get_string('reportviewshowcount', 'local_evidencehub'),
            'showpairs' => get_string('reportviewshowpairs', 'local_evidencehub'),
            'table' => get_string('reportviewtable', 'local_evidencehub'),
            'view' => get_string('reportviewview', 'local_evidencehub'),
            'viewauto' => get_string('reportviewviewauto', 'local_evidencehub'),
            'viewautohint' => get_string('reportviewviewautohint', 'local_evidencehub'),
            'worker' => get_string('worker', 'local_evidencehub'),
            'workers' => get_string('reportviewworkers', 'local_evidencehub'),
            'workerssub' => get_string('reportviewworkerssub', 'local_evidencehub'),
            'worst' => get_string('reportviewworst', 'local_evidencehub'),
        ];
    }

    /**
     * The template picker beside the heading: core's single select, a GET form to this page with the context.
     *
     * @param renderer_base $output The renderer.
     * @return string Its HTML, rendered by core.
     */
    private function template_picker(renderer_base $output): string {
        $choices = [];
        foreach (hub_access::templates($this->context) as $template) {
            $templatecontext = \context::instance_by_id($template->contextid);
            // Core's single select prints the option text with a triple stash: the escaped spelling.
            $name = format_string($template->shortname, true, ['context' => $templatecontext]);
            $choices[$template->id] = $name . ' (' . s($templatecontext->get_context_name(false, false, false)) . ')';
        }
        $select = new single_select(
            new \moodle_url('/local/evidencehub/index.php', ['contextid' => $this->context->id]),
            'templateid',
            $choices,
            $this->report->template_id(),
            ['' => get_string('hubchoosetemplate', 'local_evidencehub')],
            'local-evidencehub-templatepicker'
        );
        $select->set_label(get_string('template', 'local_evidencehub'));
        return $output->render($select);
    }

    /**
     * Export the shell.
     *
     * @param renderer_base $output The renderer.
     * @return array title, picker (core's HTML), locked and contextname, props (JSON), tableurl, noscript (core's
     *     rendering of the report).
     */
    #[\Override]
    public function export_for_template(renderer_base $output): array {
        // Without JavaScript core's filter form is a dynamic form that cannot submit, and its script, which the
        // template registers on the page whatever the browser does with a noscript, would look up a form a
        // scripting browser never builds.
        $this->report->set_filter_form_default(false);

        return [
            'title' => get_string('hubpage', 'local_evidencehub'),
            'picker' => $this->template_picker($output),
            'locked' => (int) $this->context->contextlevel === CONTEXT_COURSECAT,
            'contextname' => $this->context->get_context_name(false, false, false),
            'props' => json_encode($this->props(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE),
            'tableurl' => $this->table_url()->out(false),
            'noscript' => $this->report->output(),
        ];
    }
}
