var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The report's filters as core stores them, read and rewritten for the panel, the chips and the band.
 *
 * Core keeps every filter value of a report for a user in one store, keyed "<filter>_<field>", and
 * core_reportbuilder_set_filters REPLACES that whole set. So a change to one filter is written as every
 * stored value, with that filter's own keys changed, and a removal as every other filter's keys. Every
 * filter the panel draws is a choice of one option from a list (the rows answer names its facet
 * "choice"): core's select and the plugin's reader_select, cohort and validuntil, which all store
 * "<filter>_operator" and "<filter>_value" and read EQUAL_TO with one value. The worker's name, a text
 * filter, is set in core's table view and appears here as a chip that removes it.
 *
 * @module     local_evidencehub/hub/filters
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const ANY_VALUE = 0;
const EQUAL_TO = 1;
const STATUS_FILTER = "validity:status";
const COMPETENCY_FILTER = "competency:competency";
const COHORT_FILTER = "user:cohort";
const choosable = /* @__PURE__ */ __name((filter) => filter.facet !== "" && filter.options.length > 0, "choosable");
const ownValues = /* @__PURE__ */ __name((filter) => {
  try {
    const parsed = JSON.parse(filter.values || "{}");
    return parsed !== null && typeof parsed === "object" ? parsed : {};
  } catch {
    return {};
  }
}, "ownValues");
const allValues = /* @__PURE__ */ __name((filters) => filters.reduce((all, filter) => ({ ...all, ...ownValues(filter) }), {}), "allValues");
const chosen = /* @__PURE__ */ __name((filter) => {
  if (!filter.applied || filter.facet === "") {
    return "";
  }
  const own = ownValues(filter);
  const value = own[`${filter.name}_value`];
  if (value === void 0 || value === null || Number(own[`${filter.name}_operator`] ?? ANY_VALUE) !== EQUAL_TO) {
    return "";
  }
  return String(value);
}, "chosen");
const without = /* @__PURE__ */ __name((filters, filter) => {
  const values = allValues(filters);
  for (const key of Object.keys(values)) {
    if (key.startsWith(`${filter.name}_`)) {
      delete values[key];
    }
  }
  return values;
}, "without");
const withChoice = /* @__PURE__ */ __name((filters, filter, value) => {
  const values = without(filters, filter);
  values[`${filter.name}_operator`] = value === "" ? ANY_VALUE : EQUAL_TO;
  values[`${filter.name}_value`] = value;
  return values;
}, "withChoice");
const chosenLabel = /* @__PURE__ */ __name((filter) => {
  const value = chosen(filter);
  if (value === "") {
    return "";
  }
  return filter.options.find((option) => option.value === value)?.label ?? "";
}, "chosenLabel");
export {
  COHORT_FILTER,
  COMPETENCY_FILTER,
  STATUS_FILTER,
  allValues,
  choosable,
  chosen,
  chosenLabel,
  ownValues,
  withChoice,
  without
};
//# sourceMappingURL=filters.dev.js.map
