var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The hub's filter bar: the shared bar (datareport/Bar) with the hub's vocabulary.
 *
 * The scope is "Needs attention" (every status but compliant, the default) or "All", each with its count
 * from the summary (decision 3). Filters draws the report's facets, which live in core's store
 * (core_reportbuilder_set_filters, filters.ts): status, cohort, competency, type of evidence, verification
 * and "Valid until", each option with the pairs it would list under every other criterion in force, a facet
 * above eight options as a searched list. Under them, "Accounts" ("Active only" or "Include suspended"),
 * which is not a stored filter but the reader's preference (decision 5), and a note leading to core's table
 * view for the filter the panel does not draw (the worker's name). Columns starts with the two sets
 * (Validity, Deciding evidence), then the catalogue, the worker and the competency always shown. View is
 * Automatic, Table or Cards, then the matrix (in the same scope) and core's table view. Download is the capsule's last
 * button (D114): a menu of the site's enabled formats, each a link to index.php's download branch with the scope, the
 * search and the sort in force, so the file holds what the list shows; the report applies the stored filters and the
 * columns itself. It is offered only once rows have arrived and while there are any. No More menu: the dossiers are
 * the dock's (the selection variant).
 *
 * @module     local_evidencehub/hub/Bar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { Bar as DataBar } from "../datareport/Bar";
import { Segmented } from "../datareport/Segmented";
import { fillObject, num } from "../datareport/str";
import { COHORT_FILTER, COMPETENCY_FILTER, choosable, chosen, chosenLabel } from "./filters";
const VIEWS = ["auto", "table", "cards"];
const presetLabel = /* @__PURE__ */ __name((labels, name) => {
  switch (name) {
    case "validity":
      return labels.presetvalidity;
    case "evidence":
      return labels.presetevidence;
    default:
      return name;
  }
}, "presetLabel");
const facet = /* @__PURE__ */ __name((labels, filter) => {
  let find = { label: labels.searchoptions, nomatch: labels.nooptionmatch };
  if (filter.name === COHORT_FILTER) {
    find = { label: labels.searchcohorts, nomatch: labels.nocohortmatch };
  } else if (filter.name === COMPETENCY_FILTER) {
    find = { label: labels.searchcompetencies, nomatch: labels.nocompetencymatch };
  }
  return { name: filter.name, title: filter.title, options: filter.options, groupings: [], find };
}, "facet");
const chip = /* @__PURE__ */ __name((labels, filter) => {
  const value = chosenLabel(filter);
  return { key: filter.name, text: value === "" ? filter.title : fillObject(labels.filterchip, { title: filter.title, value }) };
}, "chip");
const matrixHref = /* @__PURE__ */ __name((url, scope) => scope === "all" ? `${url}&scope=all` : url, "matrixHref");
const downloadQuery = /* @__PURE__ */ __name((scope, search, sort, direction) => {
  const query = new URLSearchParams({ scope });
  if (search !== "") {
    query.set("search", search);
  }
  if (sort !== "") {
    query.set("sort", sort);
    query.set("dir", direction === "desc" ? "desc" : "asc");
  }
  return query.toString();
}, "downloadQuery");
const Accounts = /* @__PURE__ */ __name(({ labels, suspended, onAccounts }) => /* @__PURE__ */ jsxDEV("div", { className: "local-evidencehub-rview-fgroup", "data-region": "local-evidencehub-accounts", children: [
  /* @__PURE__ */ jsxDEV("span", { className: "local-evidencehub-rview-fpanel-head", children: labels.accounts }, void 0, false, {
    fileName: "public/local/evidencehub/js/esm/src/hub/Bar.tsx",
    lineNumber: 180,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV(
    Segmented,
    {
      label: labels.accounts,
      small: true,
      value: suspended ? "all" : "active",
      options: [{ value: "active", label: labels.activeonly }, { value: "all", label: labels.includesuspended }],
      onChange: (value) => {
        if (value === "all" !== suspended) {
          onAccounts(value === "all");
        }
      }
    },
    void 0,
    false,
    {
      fileName: "public/local/evidencehub/js/esm/src/hub/Bar.tsx",
      lineNumber: 181,
      columnNumber: 9
    }
  )
] }, void 0, true, {
  fileName: "public/local/evidencehub/js/esm/src/hub/Bar.tsx",
  lineNumber: 179,
  columnNumber: 5
}), "Accounts");
const Bar = /* @__PURE__ */ __name((props) => {
  const { labels, filters, summary } = props;
  const panelfilters = filters.filter(choosable);
  const others = filters.filter((filter) => !choosable(filter));
  const named = /* @__PURE__ */ __name((name) => filters.find((filter) => filter.name === name), "named");
  const query = downloadQuery(props.scope, props.appliedsearch, props.sort, props.direction);
  const downloads = props.downloadable && props.downloadurl !== "" ? props.formats.map((format) => ({
    key: format.value,
    label: format.label,
    href: `${props.downloadurl}&${query}&download=${encodeURIComponent(format.value)}`
  })) : [];
  const sets = props.presets.map((preset) => ({
    name: preset.name,
    label: presetLabel(labels, preset.name),
    active: preset.active
  }));
  return /* @__PURE__ */ jsxDEV(
    DataBar,
    {
      labels,
      narrow: props.narrow,
      scopes: [
        { value: "attention", label: labels.attention, count: summary === null ? void 0 : num(summary.attention) },
        { value: "all", label: labels.all, count: summary === null ? void 0 : num(summary.pairs) }
      ],
      scope: props.scope,
      total: props.total,
      facets: panelfilters.map((filter) => facet(labels, filter)),
      values: Object.fromEntries(panelfilters.map((filter) => [filter.name, chosen(filter)])),
      panelnote: /* @__PURE__ */ jsxDEV(Fragment, { children: [
        /* @__PURE__ */ jsxDEV(Accounts, { labels, suspended: props.suspended, onAccounts: props.onAccounts }, void 0, false, {
          fileName: "public/local/evidencehub/js/esm/src/hub/Bar.tsx",
          lineNumber: 238,
          columnNumber: 21
        }),
        others.length > 0 ? /* @__PURE__ */ jsxDEV("p", { className: "local-evidencehub-rview-note", children: [
          labels.morefiltershelp,
          " ",
          /* @__PURE__ */ jsxDEV("a", { href: props.tableurl, children: labels.morefilters }, void 0, false, {
            fileName: "public/local/evidencehub/js/esm/src/hub/Bar.tsx",
            lineNumber: 242,
            columnNumber: 29
          })
        ] }, void 0, true, {
          fileName: "public/local/evidencehub/js/esm/src/hub/Bar.tsx",
          lineNumber: 240,
          columnNumber: 25
        }) : null
      ] }, void 0, true, {
        fileName: "public/local/evidencehub/js/esm/src/hub/Bar.tsx",
        lineNumber: 237,
        columnNumber: 17
      }),
      chips: filters.filter((filter) => filter.applied).map((filter) => chip(labels, filter)),
      columns: props.columns,
      sets,
      usedefault: props.usedefault,
      haswidths: props.haswidths,
      view: props.view,
      views: VIEWS,
      tableurl: props.tableurl,
      viewlinks: props.matrixurl === "" ? [] : [
        { key: "matrix", label: labels.matrix, href: matrixHref(props.matrixurl, props.scope), icon: "matrix" }
      ],
      downloads,
      search: props.search,
      searchmin: props.searchmin,
      searching: props.searching,
      onScope: (scope) => props.onScope(scope === "all" ? "all" : "attention"),
      onSearch: props.onSearch,
      onFacet: (name, value) => {
        const filter = named(name);
        if (filter) {
          props.onFilter(filter, value);
        }
      },
      onRemoveChip: (name) => {
        const filter = named(name);
        if (filter) {
          props.onRemoveFilter(filter);
        }
      },
      onClearFilters: props.onClearFilters,
      onClearAll: props.onClearAll,
      onColumn: props.onColumn,
      onSet: (name) => {
        const preset = props.presets.find((entry) => entry.name === name);
        if (preset) {
          props.onPreset(preset);
        }
      },
      onDefaultColumns: props.onDefaultColumns,
      onResetWidths: props.onResetWidths,
      onView: props.onView
    },
    void 0,
    false,
    {
      fileName: "public/local/evidencehub/js/esm/src/hub/Bar.tsx",
      lineNumber: 225,
      columnNumber: 9
    }
  );
}, "Bar");
export {
  Bar
};
//# sourceMappingURL=Bar.dev.js.map
