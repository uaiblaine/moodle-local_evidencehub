var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The hub's cards: the shared card view (datareport/Cards) with the hub's fixed lines (study, section 2.7).
 *
 * The worker with their identity lines and the Inspect action; the competency with the status label; "Valid
 * until"; the type of evidence. Every other chosen column follows in the card's list, behind "More" on a
 * narrow container, a field with nothing in it left out. A fixed line whose column the reader hid is left
 * out too: a card shows what the grid would.
 *
 * @module     local_evidencehub/hub/Cards
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { Cards as DataCards } from "../datareport/Cards";
import { Lines } from "../datareport/cells";
import { IdentityLines, InspectButton, StatusLabel, cellHtml } from "./cells";
const FIXED = ["worker", "competency", "status", "expiry", "type"];
const KeyedLine = /* @__PURE__ */ __name(({ row, column }) => /* @__PURE__ */ jsxDEV("div", { className: "local-evidencehub-rview-card-line", children: [
  /* @__PURE__ */ jsxDEV("span", { className: "local-evidencehub-rview-card-key", children: column.title }, void 0, false, {
    fileName: "public/local/evidencehub/js/esm/src/hub/Cards.tsx",
    lineNumber: 58,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV("span", { className: "local-evidencehub-rview-inline", dangerouslySetInnerHTML: { __html: cellHtml(row, column.key) } }, void 0, false, {
    fileName: "public/local/evidencehub/js/esm/src/hub/Cards.tsx",
    lineNumber: 59,
    columnNumber: 9
  })
] }, void 0, true, {
  fileName: "public/local/evidencehub/js/esm/src/hub/Cards.tsx",
  lineNumber: 57,
  columnNumber: 5
}), "KeyedLine");
const content = /* @__PURE__ */ __name((labels, columns, identity, row, onInspect) => {
  const shown = /* @__PURE__ */ __name((key) => columns.find((column) => column.key === key), "shown");
  const expiry = shown("expiry");
  const type = shown("type");
  const extra = columns.filter((column) => !FIXED.includes(column.key) && cellHtml(row, column.key).trim() !== "");
  return {
    head: /* @__PURE__ */ jsxDEV(Fragment, { children: [
      /* @__PURE__ */ jsxDEV("div", { className: "local-evidencehub-rview-card-who", children: [
        /* @__PURE__ */ jsxDEV(
          "span",
          {
            className: "local-evidencehub-rview-card-name",
            dangerouslySetInnerHTML: { __html: cellHtml(row, "worker") }
          },
          void 0,
          false,
          {
            fileName: "public/local/evidencehub/js/esm/src/hub/Cards.tsx",
            lineNumber: 90,
            columnNumber: 21
          }
        ),
        /* @__PURE__ */ jsxDEV(IdentityLines, { row, identity }, void 0, false, {
          fileName: "public/local/evidencehub/js/esm/src/hub/Cards.tsx",
          lineNumber: 94,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/local/evidencehub/js/esm/src/hub/Cards.tsx",
        lineNumber: 89,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV(InspectButton, { row, label: labels.inspect, onInspect }, void 0, false, {
        fileName: "public/local/evidencehub/js/esm/src/hub/Cards.tsx",
        lineNumber: 96,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/local/evidencehub/js/esm/src/hub/Cards.tsx",
      lineNumber: 88,
      columnNumber: 13
    }),
    lines: /* @__PURE__ */ jsxDEV(Fragment, { children: [
      /* @__PURE__ */ jsxDEV("div", { className: "local-evidencehub-rview-card-line", children: [
        /* @__PURE__ */ jsxDEV(
          "span",
          {
            className: "local-evidencehub-rview-inline",
            dangerouslySetInnerHTML: { __html: cellHtml(row, "competency") }
          },
          void 0,
          false,
          {
            fileName: "public/local/evidencehub/js/esm/src/hub/Cards.tsx",
            lineNumber: 102,
            columnNumber: 21
          }
        ),
        shown("status") ? /* @__PURE__ */ jsxDEV(StatusLabel, { row }, void 0, false, {
          fileName: "public/local/evidencehub/js/esm/src/hub/Cards.tsx",
          lineNumber: 106,
          columnNumber: 40
        }) : null
      ] }, void 0, true, {
        fileName: "public/local/evidencehub/js/esm/src/hub/Cards.tsx",
        lineNumber: 101,
        columnNumber: 17
      }),
      expiry ? /* @__PURE__ */ jsxDEV(KeyedLine, { row, column: expiry }, void 0, false, {
        fileName: "public/local/evidencehub/js/esm/src/hub/Cards.tsx",
        lineNumber: 108,
        columnNumber: 27
      }) : null,
      type ? /* @__PURE__ */ jsxDEV(KeyedLine, { row, column: type }, void 0, false, {
        fileName: "public/local/evidencehub/js/esm/src/hub/Cards.tsx",
        lineNumber: 109,
        columnNumber: 25
      }) : null
    ] }, void 0, true, {
      fileName: "public/local/evidencehub/js/esm/src/hub/Cards.tsx",
      lineNumber: 100,
      columnNumber: 13
    }),
    fields: extra.map((column) => ({
      key: column.key,
      title: column.title,
      content: /* @__PURE__ */ jsxDEV(Lines, { parts: [cellHtml(row, column.key)] }, void 0, false, {
        fileName: "public/local/evidencehub/js/esm/src/hub/Cards.tsx",
        lineNumber: 115,
        columnNumber: 22
      })
    }))
  };
}, "content");
const Cards = /* @__PURE__ */ __name(({ labels, columns, identity, rows, single, selection, onInspect }) => /* @__PURE__ */ jsxDEV(
  DataCards,
  {
    labels,
    rows,
    single,
    selection,
    renderCard: (row) => content(labels, columns, identity, row, onInspect)
  },
  void 0,
  false,
  {
    fileName: "public/local/evidencehub/js/esm/src/hub/Cards.tsx",
    lineNumber: 127,
    columnNumber: 5
  }
), "Cards");
export {
  Cards
};
//# sourceMappingURL=Cards.dev.js.map
