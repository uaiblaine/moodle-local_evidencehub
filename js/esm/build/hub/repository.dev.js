var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
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
 */
import { fetchOne } from "@moodle/lms/core/ajax";
const getRows = /* @__PURE__ */ __name((target, query, facets) => fetchOne({ methodname: "local_evidencehub_report_rows", args: { ...target, ...query, facets } }), "getRows");
const getSummary = /* @__PURE__ */ __name((target, scope, search) => fetchOne({ methodname: "local_evidencehub_report_summary", args: { ...target, scope, search } }), "getSummary");
const setPreference = /* @__PURE__ */ __name((target, preference, suspended) => fetchOne({
  methodname: "local_evidencehub_set_report_preference",
  args: {
    ...target,
    usedefault: preference.usedefault,
    columns: preference.columns,
    widths: preference.widths,
    suspended: suspended === null ? -1 : Number(suspended)
  }
}), "setPreference");
const setFilters = /* @__PURE__ */ __name((reportid, values) => fetchOne({
  methodname: "core_reportbuilder_set_filters",
  args: { reportid, parameters: "{}", values: JSON.stringify(values) }
}), "setFilters");
const resetFilters = /* @__PURE__ */ __name((reportid) => fetchOne({
  methodname: "core_reportbuilder_filters_reset",
  args: { reportid, parameters: "{}" }
}), "resetFilters");
export {
  getRows,
  getSummary,
  resetFilters,
  setFilters,
  setPreference
};
//# sourceMappingURL=repository.dev.js.map
