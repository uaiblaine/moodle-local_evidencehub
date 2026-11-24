var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The bulk dock under the hub's rows (the Design System's data-report.md, "Bulk dock", D133 to D135).
 *
 * The count of the rows selected on this page, then how many workers they name: a dossier is per worker,
 * so two pairs of one worker are one dossier (the plugin's addition to the mould's count). One action,
 * "Export dossiers", the page's only one, so a solid fill. It needs no more input, so it opens no dialog
 * (D135): it posts the form at once (export.ts). Drawn only while something is selected; the view keeps it
 * out otherwise.
 *
 * @module     local_evidencehub/hub/Dock
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { fill, fillCount, num } from "../datareport/str";
const Dock = /* @__PURE__ */ __name(({ labels, count, workers, posting, onExport }) => /* @__PURE__ */ jsxDEV("div", { className: "local-evidencehub-rview-dock", role: "region", "aria-label": labels.dock, "data-region": "local-evidencehub-dock", children: [
  /* @__PURE__ */ jsxDEV("span", { className: "local-evidencehub-rview-dockcount", "aria-live": "polite", children: [
    fill(labels.selected, num(count)),
    /* @__PURE__ */ jsxDEV("span", { className: "local-evidencehub-rview-dockworkers", children: [
      " \xB7 ",
      fillCount(labels, "selectedworkers", workers, num(workers))
    ] }, void 0, true, {
      fileName: "public/local/evidencehub/js/esm/src/hub/Dock.tsx",
      lineNumber: 54,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/local/evidencehub/js/esm/src/hub/Dock.tsx",
    lineNumber: 52,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV("div", { className: "local-evidencehub-rview-dockactions", children: /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-primary", disabled: posting, onClick: onExport, children: labels.exportdossiers }, void 0, false, {
    fileName: "public/local/evidencehub/js/esm/src/hub/Dock.tsx",
    lineNumber: 59,
    columnNumber: 13
  }) }, void 0, false, {
    fileName: "public/local/evidencehub/js/esm/src/hub/Dock.tsx",
    lineNumber: 58,
    columnNumber: 9
  })
] }, void 0, true, {
  fileName: "public/local/evidencehub/js/esm/src/hub/Dock.tsx",
  lineNumber: 51,
  columnNumber: 5
}), "Dock");
export {
  Dock
};
//# sourceMappingURL=Dock.dev.js.map
