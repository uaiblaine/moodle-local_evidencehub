var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The hub's cells as the grid and the cards draw them, and the columns' widths.
 *
 * A cell is the report's own formatted HTML (format_row(): the masking and the escaping already ran on the
 * server, as on core's table), so it is inserted as HTML and never built here. Two cells are drawn around
 * it: the status, as a status label whose tone is read from the row's effective status code (study,
 * decision 15), and the worker, with the identity fields the reader's report shows under the name and the
 * Inspect action at the cell's end where the server says the reader may open the pair's drawer.
 *
 * @module     local_evidencehub/hub/cells
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { Lines } from "../datareport/cells";
import { Icon } from "../datareport/icons";
import { makeSizing } from "../datareport/sizing";
import { plain } from "../datareport/str";
const WIDTHS = {
  worker: 260,
  competency: 240,
  status: 180,
  expiry: 132,
  count: 104,
  type: 200,
  issuer: 200,
  competencyidnumber: 150,
  framework: 200,
  issuedate: 132,
  expirydate: 132,
  verification: 160,
  changed: 160
};
const DEFAULT_WIDTH = 180;
const MINIMUMS = { worker: 200, expiry: 104, issuedate: 104, expirydate: 104, changed: 104 };
const MINIMUM = 80;
const MAXIMUM = 800;
const SIZING = makeSizing(WIDTHS, DEFAULT_WIDTH, MINIMUMS, MINIMUM, MAXIMUM);
const RESTRICTED = -1;
const MISSING = 0;
const UNVERIFIED = 1;
const VALID = 2;
const DUE = 3;
const CRITICAL = 4;
const EXPIRED = 5;
const tone = /* @__PURE__ */ __name((status) => {
  switch (status) {
    case VALID:
      return "success";
    case DUE:
      return "info";
    case CRITICAL:
      return "warning";
    case EXPIRED:
      return "danger";
    case UNVERIFIED:
    case MISSING:
    case RESTRICTED:
    default:
      return "neutral";
  }
}, "tone");
const cellHtml = /* @__PURE__ */ __name((row, key) => row.cells.find((cell) => cell.key === key)?.html ?? "", "cellHtml");
const StatusLabel = /* @__PURE__ */ __name(({ row }) => {
  const html = cellHtml(row, "status");
  if (html === "") {
    return null;
  }
  return /* @__PURE__ */ jsxDEV("span", { className: `local-evidencehub-rview-status local-evidencehub-rview-status--${tone(row.status)}`, title: plain(html), children: [
    row.status === RESTRICTED ? /* @__PURE__ */ jsxDEV(Icon, { name: "lock" }, void 0, false, {
      fileName: "public/local/evidencehub/js/esm/src/hub/cells.tsx",
      lineNumber: 131,
      columnNumber: 42
    }) : null,
    /* @__PURE__ */ jsxDEV("span", { dangerouslySetInnerHTML: { __html: html } }, void 0, false, {
      fileName: "public/local/evidencehub/js/esm/src/hub/cells.tsx",
      lineNumber: 132,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/local/evidencehub/js/esm/src/hub/cells.tsx",
    lineNumber: 130,
    columnNumber: 9
  });
}, "StatusLabel");
const CellContent = /* @__PURE__ */ __name(({ row, column }) => column === "status" ? /* @__PURE__ */ jsxDEV(StatusLabel, { row }, void 0, false, {
  fileName: "public/local/evidencehub/js/esm/src/hub/cells.tsx",
  lineNumber: 144,
  columnNumber: 27
}) : /* @__PURE__ */ jsxDEV(Lines, { parts: [cellHtml(row, column)] }, void 0, false, {
  fileName: "public/local/evidencehub/js/esm/src/hub/cells.tsx",
  lineNumber: 144,
  columnNumber: 55
}), "CellContent");
const InspectButton = /* @__PURE__ */ __name(({ row, label, onInspect }) => row.inspect ? /* @__PURE__ */ jsxDEV(
  "button",
  {
    type: "button",
    className: "btn btn-sm btn-outline-secondary local-evidencehub-rview-inspect",
    "aria-label": row.inspectlabel,
    "data-userid": row.userid,
    "data-competencyid": row.competencyid,
    "data-title": row.drawertitle,
    onClick: (event) => onInspect(event.currentTarget),
    children: label
  },
  void 0,
  false,
  {
    fileName: "public/local/evidencehub/js/esm/src/hub/cells.tsx",
    lineNumber: 157,
    columnNumber: 9
  }
) : null, "InspectButton");
const IdentityLines = /* @__PURE__ */ __name(({ row, identity }) => /* @__PURE__ */ jsxDEV(Fragment, { children: identity.map((column) => {
  const html = cellHtml(row, column.key);
  return html === "" ? null : /* @__PURE__ */ jsxDEV(
    "span",
    {
      className: "local-evidencehub-rview-sub",
      title: plain(html),
      dangerouslySetInnerHTML: { __html: html }
    },
    column.key,
    false,
    {
      fileName: "public/local/evidencehub/js/esm/src/hub/cells.tsx",
      lineNumber: 182,
      columnNumber: 17
    }
  );
}) }, void 0, false, {
  fileName: "public/local/evidencehub/js/esm/src/hub/cells.tsx",
  lineNumber: 178,
  columnNumber: 5
}), "IdentityLines");
export {
  CellContent,
  IdentityLines,
  InspectButton,
  MISSING,
  RESTRICTED,
  SIZING,
  StatusLabel,
  cellHtml,
  tone
};
//# sourceMappingURL=cells.dev.js.map
