import{fetchOne as t}from"@moodle/lms/core/ajax";/**
 * The only module of the hub's view that talks to the server's web services.
 *
 * Every call goes through core's ES module @moodle/lms/core/ajax (a component cannot import an AMD
 * module), and every call is ONE request of its own (fetchOne), never a batch: core keeps one instance
 * of a report per user for the whole request, so a rows call batched after a filter or preference write
 * would read the report built before it (the preference writer never builds the report for that reason,
 * classes/external/set_report_preference.php). Core's ajax holds Behat's pending counter while each
 * request is in flight; the view holds it across the render that follows (datareport/pending.ts).
 *
 * The report is identified by its context and template (hub_pairs, the template being its item): core's
 * filter services rebuild it from its persistent, whose context and item it already holds, and take no
 * parameter of the hub's.
 *
 * @module     local_evidencehub/hub/repository
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const n=(e,r,o)=>t({methodname:"local_evidencehub_report_rows",args:{...e,...r,facets:o}}),m=(e,r,o)=>t({methodname:"local_evidencehub_report_summary",args:{...e,scope:r,search:o}}),a=(e,r,o)=>t({methodname:"local_evidencehub_set_report_preference",args:{...e,usedefault:r.usedefault,columns:r.columns,widths:r.widths,suspended:o===null?-1:Number(o)}}),l=(e,r)=>t({methodname:"core_reportbuilder_set_filters",args:{reportid:e,parameters:"{}",values:JSON.stringify(r)}}),u=e=>t({methodname:"core_reportbuilder_filters_reset",args:{reportid:e,parameters:"{}"}});export{n as getRows,m as getSummary,u as resetFilters,l as setFilters,a as setPreference};
