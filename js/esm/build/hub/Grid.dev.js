var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The hub's grid: the shared bounded grid (datareport/Grid) with the hub's cells.
 *
 * The lead column is the worker: their name linked to their evidence page as the report formats it, the
 * identity fields the reader's report shows under it, and the Inspect action at the cell's end. The status
 * is a status label; every other cell is the report's formatted HTML. A reader who may export dossiers gets
 * the selection (the Design System's selection variant), which the view builds and passes.
 *
 * @module     local_evidencehub/hub/Grid
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { Grid as DataGrid } from "../datareport/Grid";
import { CellContent, IdentityLines, InspectButton, SIZING, cellHtml } from "./cells";
const Grid = /* @__PURE__ */ __name(({
  labels,
  columns,
  identity,
  rows,
  widths,
  sort,
  direction,
  selection,
  onInspect,
  onSort,
  onWidth,
  onWidthCommit
}) => /* @__PURE__ */ jsxDEV(
  DataGrid,
  {
    labels,
    columns,
    lead: "worker",
    rows,
    widths,
    sizing: SIZING,
    sort,
    direction,
    selection,
    renderLead: (row) => /* @__PURE__ */ jsxDEV("div", { className: "local-evidencehub-rview-who", children: [
      /* @__PURE__ */ jsxDEV("div", { className: "local-evidencehub-rview-who-text", children: [
        /* @__PURE__ */ jsxDEV(
          "span",
          {
            className: "local-evidencehub-rview-c",
            dangerouslySetInnerHTML: { __html: cellHtml(row, "worker") }
          },
          void 0,
          false,
          {
            fileName: "public/local/evidencehub/js/esm/src/hub/Grid.tsx",
            lineNumber: 71,
            columnNumber: 21
          }
        ),
        /* @__PURE__ */ jsxDEV(IdentityLines, { row, identity }, void 0, false, {
          fileName: "public/local/evidencehub/js/esm/src/hub/Grid.tsx",
          lineNumber: 75,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/local/evidencehub/js/esm/src/hub/Grid.tsx",
        lineNumber: 70,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV(InspectButton, { row, label: labels.inspect, onInspect }, void 0, false, {
        fileName: "public/local/evidencehub/js/esm/src/hub/Grid.tsx",
        lineNumber: 77,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/local/evidencehub/js/esm/src/hub/Grid.tsx",
      lineNumber: 69,
      columnNumber: 13
    }),
    renderCell: (row, column) => /* @__PURE__ */ jsxDEV(CellContent, { row, column: column.key }, void 0, false, {
      fileName: "public/local/evidencehub/js/esm/src/hub/Grid.tsx",
      lineNumber: 80,
      columnNumber: 38
    }),
    onSort,
    onWidth,
    onWidthCommit
  },
  void 0,
  false,
  {
    fileName: "public/local/evidencehub/js/esm/src/hub/Grid.tsx",
    lineNumber: 58,
    columnNumber: 5
  }
), "Grid");
export {
  Grid
};
//# sourceMappingURL=Grid.dev.js.map
