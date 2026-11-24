var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The hub's band: the readiness of the listed workers (study, section 2.6, decision 7).
 *
 * Four tiles - the workers, with the workers hidden by permissions as the sub-line when there are any; the
 * ready workers with the rate; the pairs needing attention of all the pairs; the pairs expiring in 30 days -
 * and two panels: the pairs by status and the competencies with most open pairs. Each line of a panel is a
 * button that sets the report's status or competency filter, its number written as text beside its bar.
 * There is no compact band: every figure is already over the reader's own workers. A figure with no value
 * reads "–".
 *
 * Every number is the server's (reportengine::summary()), over the report's own rows under every criterion
 * but the scope and the status filter.
 *
 * @module     local_evidencehub/hub/Band
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { fill, num } from "../datareport/str";
const NONE = "\u2013";
const Tile = /* @__PURE__ */ __name(({ label, value, sub }) => /* @__PURE__ */ jsxDEV("div", { className: "local-evidencehub-rview-tile", children: [
  /* @__PURE__ */ jsxDEV("div", { className: "local-evidencehub-rview-tile-label", children: label }, void 0, false, {
    fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
    lineNumber: 61,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV("div", { className: "local-evidencehub-rview-tile-value", children: value }, void 0, false, {
    fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
    lineNumber: 62,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV("div", { className: "local-evidencehub-rview-tile-sub", children: sub }, void 0, false, {
    fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
    lineNumber: 63,
    columnNumber: 9
  })
] }, void 0, true, {
  fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
  lineNumber: 60,
  columnNumber: 5
}), "Tile");
const BarRow = /* @__PURE__ */ __name(({ label, share, figure, title, disabled, onPress }) => /* @__PURE__ */ jsxDEV("li", { className: "local-evidencehub-rview-hitem", children: /* @__PURE__ */ jsxDEV("button", { type: "button", className: "local-evidencehub-rview-hrow", title, disabled, onClick: onPress, children: [
  /* @__PURE__ */ jsxDEV("span", { className: "local-evidencehub-rview-hrow-label", children: label }, void 0, false, {
    fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
    lineNumber: 77,
    columnNumber: 13
  }),
  /* @__PURE__ */ jsxDEV("span", { className: "local-evidencehub-rview-track", "aria-hidden": "true", children: /* @__PURE__ */ jsxDEV(
    "span",
    {
      className: "local-evidencehub-rview-fill",
      style: { width: `${Math.round(Math.min(1, Math.max(0, share)) * 100)}%` }
    },
    void 0,
    false,
    {
      fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
      lineNumber: 79,
      columnNumber: 17
    }
  ) }, void 0, false, {
    fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
    lineNumber: 78,
    columnNumber: 13
  }),
  /* @__PURE__ */ jsxDEV("span", { className: "local-evidencehub-rview-hrow-n", children: figure }, void 0, false, {
    fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
    lineNumber: 84,
    columnNumber: 13
  })
] }, void 0, true, {
  fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
  lineNumber: 76,
  columnNumber: 9
}) }, void 0, false, {
  fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
  lineNumber: 75,
  columnNumber: 5
}), "BarRow");
const BandSkeleton = /* @__PURE__ */ __name(() => /* @__PURE__ */ jsxDEV("div", { "aria-hidden": "true", children: [
  /* @__PURE__ */ jsxDEV("div", { className: "local-evidencehub-rview-band", children: Array.from({ length: 4 }, (_, i) => /* @__PURE__ */ jsxDEV("div", { className: "local-evidencehub-rview-tile local-evidencehub-rview-sk", children: [
    /* @__PURE__ */ jsxDEV("span", { className: "local-evidencehub-rview-skb local-evidencehub-rview-skb--w60" }, void 0, false, {
      fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
      lineNumber: 99,
      columnNumber: 21
    }),
    /* @__PURE__ */ jsxDEV("span", { className: "local-evidencehub-rview-skb local-evidencehub-rview-skb--big" }, void 0, false, {
      fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
      lineNumber: 100,
      columnNumber: 21
    }),
    /* @__PURE__ */ jsxDEV("span", { className: "local-evidencehub-rview-skb local-evidencehub-rview-skb--w80" }, void 0, false, {
      fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
      lineNumber: 101,
      columnNumber: 21
    })
  ] }, i, true, {
    fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
    lineNumber: 98,
    columnNumber: 17
  })) }, void 0, false, {
    fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
    lineNumber: 96,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV("div", { className: "local-evidencehub-rview-dist", children: [0, 1].map((i) => /* @__PURE__ */ jsxDEV("div", { className: "local-evidencehub-rview-panel local-evidencehub-rview-sk", children: [
    /* @__PURE__ */ jsxDEV("span", { className: "local-evidencehub-rview-skb local-evidencehub-rview-skb--w40" }, void 0, false, {
      fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
      lineNumber: 108,
      columnNumber: 21
    }),
    /* @__PURE__ */ jsxDEV("span", { className: "local-evidencehub-rview-skb" }, void 0, false, {
      fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
      lineNumber: 109,
      columnNumber: 21
    }),
    /* @__PURE__ */ jsxDEV("span", { className: "local-evidencehub-rview-skb" }, void 0, false, {
      fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
      lineNumber: 110,
      columnNumber: 21
    }),
    /* @__PURE__ */ jsxDEV("span", { className: "local-evidencehub-rview-skb" }, void 0, false, {
      fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
      lineNumber: 111,
      columnNumber: 21
    })
  ] }, i, true, {
    fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
    lineNumber: 107,
    columnNumber: 17
  })) }, void 0, false, {
    fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
    lineNumber: 105,
    columnNumber: 9
  })
] }, void 0, true, {
  fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
  lineNumber: 95,
  columnNumber: 5
}), "BandSkeleton");
const Band = /* @__PURE__ */ __name(({ labels, summary, loading, error, canfilter, onStatus, onCompetency, onRetry }) => {
  let body;
  if (summary === null && error) {
    body = /* @__PURE__ */ jsxDEV("p", { className: "local-evidencehub-rview-error", children: [
      labels.loaderror,
      " ",
      /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-sm btn-outline-secondary", onClick: onRetry, children: labels.retry }, void 0, false, {
        fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
        lineNumber: 130,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
      lineNumber: 128,
      columnNumber: 13
    });
  } else if (summary === null) {
    body = /* @__PURE__ */ jsxDEV(Fragment, { children: [
      /* @__PURE__ */ jsxDEV(BandSkeleton, {}, void 0, false, {
        fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
        lineNumber: 136,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", role: "status", children: labels.loadband }, void 0, false, {
        fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
        lineNumber: 137,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
      lineNumber: 135,
      columnNumber: 13
    });
  } else {
    const rate = summary.rate ?? null;
    const statusmost = Math.max(1, ...summary.distribution.map((entry) => entry.count));
    const worstmost = Math.max(1, ...summary.worst.map((entry) => entry.count));
    body = /* @__PURE__ */ jsxDEV("div", { className: "local-evidencehub-rview-dim", children: [
      error ? /* @__PURE__ */ jsxDEV("p", { className: "local-evidencehub-rview-error", children: [
        labels.loaderror,
        " ",
        /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-sm btn-outline-secondary", onClick: onRetry, children: labels.retry }, void 0, false, {
          fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
          lineNumber: 149,
          columnNumber: 25
        })
      ] }, void 0, true, {
        fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
        lineNumber: 147,
        columnNumber: 21
      }) : null,
      /* @__PURE__ */ jsxDEV("div", { className: "local-evidencehub-rview-band", children: [
        /* @__PURE__ */ jsxDEV(
          Tile,
          {
            label: labels.workers,
            value: num(summary.workers),
            sub: summary.hidden > 0 ? fill(labels.hiddenworkers, num(summary.hidden)) : labels.workerssub
          },
          void 0,
          false,
          {
            fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
            lineNumber: 153,
            columnNumber: 21
          }
        ),
        /* @__PURE__ */ jsxDEV(
          Tile,
          {
            label: labels.ready,
            value: rate === null ? NONE : num(summary.ready),
            sub: rate === null ? labels.readynone : fill(labels.readysub, num(Math.round(rate * 100)))
          },
          void 0,
          false,
          {
            fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
            lineNumber: 158,
            columnNumber: 21
          }
        ),
        /* @__PURE__ */ jsxDEV(
          Tile,
          {
            label: labels.attentiontile,
            value: num(summary.attention),
            sub: fill(labels.attentionsub, num(summary.pairs))
          },
          void 0,
          false,
          {
            fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
            lineNumber: 163,
            columnNumber: 21
          }
        ),
        /* @__PURE__ */ jsxDEV(Tile, { label: labels.expiring, value: num(summary.expiring), sub: labels.expiringsub }, void 0, false, {
          fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
          lineNumber: 168,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
        lineNumber: 152,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("div", { className: "local-evidencehub-rview-dist", children: [
        /* @__PURE__ */ jsxDEV("div", { className: "local-evidencehub-rview-panel", children: [
          /* @__PURE__ */ jsxDEV("div", { className: "local-evidencehub-rview-panel-title", children: /* @__PURE__ */ jsxDEV("span", { children: labels.bystatus }, void 0, false, {
            fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
            lineNumber: 172,
            columnNumber: 78
          }) }, void 0, false, {
            fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
            lineNumber: 172,
            columnNumber: 25
          }),
          /* @__PURE__ */ jsxDEV("ul", { className: "local-evidencehub-rview-hlist", children: summary.distribution.map((entry) => /* @__PURE__ */ jsxDEV(
            BarRow,
            {
              label: entry.label,
              share: entry.count / statusmost,
              figure: num(entry.count),
              title: labels.showpairs,
              disabled: !canfilter,
              onPress: () => onStatus(entry.status)
            },
            entry.status,
            false,
            {
              fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
              lineNumber: 175,
              columnNumber: 33
            }
          )) }, void 0, false, {
            fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
            lineNumber: 173,
            columnNumber: 25
          })
        ] }, void 0, true, {
          fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
          lineNumber: 171,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV("div", { className: "local-evidencehub-rview-panel", children: [
          /* @__PURE__ */ jsxDEV("div", { className: "local-evidencehub-rview-panel-title", children: /* @__PURE__ */ jsxDEV("span", { children: labels.worst }, void 0, false, {
            fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
            lineNumber: 188,
            columnNumber: 78
          }) }, void 0, false, {
            fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
            lineNumber: 188,
            columnNumber: 25
          }),
          summary.worst.length > 0 ? /* @__PURE__ */ jsxDEV("ul", { className: "local-evidencehub-rview-hlist", children: summary.worst.map((entry) => /* @__PURE__ */ jsxDEV(
            BarRow,
            {
              label: entry.name,
              share: entry.count / worstmost,
              figure: num(entry.count),
              title: labels.showpairs,
              disabled: !canfilter,
              onPress: () => onCompetency(entry.competencyid)
            },
            entry.competencyid,
            false,
            {
              fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
              lineNumber: 192,
              columnNumber: 37
            }
          )) }, void 0, false, {
            fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
            lineNumber: 190,
            columnNumber: 29
          }) : /* @__PURE__ */ jsxDEV("p", { className: "local-evidencehub-rview-note", children: labels.noworst }, void 0, false, {
            fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
            lineNumber: 203,
            columnNumber: 29
          })
        ] }, void 0, true, {
          fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
          lineNumber: 187,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
        lineNumber: 170,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
      lineNumber: 145,
      columnNumber: 13
    });
  }
  return /* @__PURE__ */ jsxDEV(
    "section",
    {
      className: "local-evidencehub-rview-region local-evidencehub-rview-bandregion",
      "aria-label": labels.bandlabel,
      "aria-busy": loading,
      "data-region": "local-evidencehub-band",
      children: [
        loading && summary !== null ? /* @__PURE__ */ jsxDEV("div", { className: "local-evidencehub-rview-progress" }, void 0, false, {
          fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
          lineNumber: 217,
          columnNumber: 44
        }) : null,
        body
      ]
    },
    void 0,
    true,
    {
      fileName: "public/local/evidencehub/js/esm/src/hub/Band.tsx",
      lineNumber: 211,
      columnNumber: 9
    }
  );
}, "Band");
export {
  Band
};
//# sourceMappingURL=Band.dev.js.map
