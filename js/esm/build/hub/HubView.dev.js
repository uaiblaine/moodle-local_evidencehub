var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The evidence hub's list of pairs, drawn in React (stage E3 of docs/design/data-report-study.md).
 *
 * index.php renders the shell (local_evidencehub\output\hub_report_view) with configuration and strings;
 * the rows and the figures come from the plugin's web services, which read the system report hub_pairs
 * headlessly, so access, the reader's cut, the restriction, the stored filters and the chosen columns are
 * the report's, as on core's table. Three regions load on their own:
 *
 * - the rows (local_evidencehub_report_rows): a sort or a page reloads them alone;
 * - the band (local_evidencehub_report_summary): a filter, the scope or the search reload it with the rows;
 * - the grid (local_evidencehub_set_report_preference): a column change is written, and the rows are read
 *   again only once the write has landed, because the report reads the columns in initialise(). The
 *   "Accounts" choice is written there too, and then both regions are read again.
 *
 * One request is in flight per region in effect: core's ajax cannot abort, so each region numbers its
 * requests and drops any answer but the newest. The search is sent 300 ms after the last key, from two
 * characters. The first load draws skeletons with aria-busy; later ones keep the old rows, dimmed, under a
 * 2 px line. The view (Automatic, Table, Cards) is remembered in this browser only, and Automatic is the
 * cards under a 576 px container.
 *
 * A reader who may export dossiers (the rows answer's candecide) gets the selection variant: a box per
 * selectable row and one for the page, and the dock under the rows while anything is selected. The
 * selection belongs to the page on screen (D131): any other page, page size, scope, filter, search, sort or
 * accounts choice empties it, and a re-read of the same rows keeps only the ids still selectable.
 *
 * A pair has no id of its own: its key is the worker and the competency. The shared parts key rows by a
 * number, so each pair gets one the first time this view meets it, kept for the view's life, so a re-read
 * of the same pair finds its selection again.
 *
 * @module     local_evidencehub/hub/HubView
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { rememberView, storedView, useNarrow } from "../datareport/layout";
import { Pager } from "../datareport/Pager";
import { hold } from "../datareport/pending";
import { RowsRegion } from "../datareport/Rows";
import { fill } from "../datareport/str";
import { inspect } from "./amd";
import { Band } from "./Band";
import { Bar } from "./Bar";
import { Cards } from "./Cards";
import { SIZING } from "./cells";
import { Dock } from "./Dock";
import { exportDossiers } from "./export";
import { COMPETENCY_FILTER, STATUS_FILTER, withChoice, without } from "./filters";
import { Grid } from "./Grid";
import { getRows, getSummary, resetFilters, setFilters, setPreference } from "./repository";
const SEARCH_DELAY = 300;
const WIDTH_DELAY = 500;
const POST_GUARD = 3e3;
const VIEW_KEY = "local_evidencehub_hub_view";
const SKELETON_COLUMNS = ["worker", "competency", "status", "expiry", "count", "type", "issuer"];
const widthList = /* @__PURE__ */ __name((map) => Object.entries(map).map(([key, width]) => ({ key, width })), "widthList");
const gridOf = /* @__PURE__ */ __name((usedefault, chosen, widths) => {
  const list = widthList(widths);
  if (usedefault && list.length === 0) {
    return { usedefault: true, columns: [], widths: [] };
  }
  return { usedefault: false, columns: chosen, widths: list };
}, "gridOf");
const settingsOf = /* @__PURE__ */ __name((rows) => rows === null ? { allcolumns: [], usedefault: true, suspended: false, candecide: false } : {
  allcolumns: rows.columns,
  usedefault: rows.preference.usedefault,
  suspended: rows.preference.suspended,
  candecide: rows.candecide
}, "settingsOf");
const HubView = /* @__PURE__ */ __name((props) => {
  const { labels, reportid } = props;
  const target = useMemo(
    () => ({ contextid: props.contextid, templateid: props.templateid }),
    [props.contextid, props.templateid]
  );
  const root = useRef(null);
  const narrow = useNarrow(root);
  const [view, setViewState] = useState(() => storedView(VIEW_KEY));
  const initial = {
    scope: "attention",
    search: "",
    page: 0,
    perpage: props.perpage,
    sort: "",
    direction: "asc"
  };
  const query = useRef(initial);
  const [current, setCurrent] = useState(initial);
  const [searchtext, setSearchtext] = useState("");
  const [rows, setRows] = useState(null);
  const [rowsloading, setRowsloading] = useState(true);
  const [rowserror, setRowserror] = useState(false);
  const [summary, setSummary] = useState(null);
  const [bandloading, setBandloading] = useState(true);
  const [banderror, setBanderror] = useState(false);
  const [widths, setWidths] = useState({});
  const [counted, setCounted] = useState({});
  const [selected, setSelected] = useState(() => /* @__PURE__ */ new Set());
  const [posting, setPosting] = useState(false);
  const ids = useRef(/* @__PURE__ */ new Map());
  const rowsseq = useRef(0);
  const bandseq = useRef(0);
  const rowshold = useRef(null);
  const bandhold = useRef(null);
  const searchtimer = useRef(null);
  const searchhold = useRef(null);
  const widthsread = useRef(false);
  const widthtimer = useRef(null);
  const widthhold = useRef(null);
  const pendingwidths = useRef(null);
  const flushref = useRef(() => void 0);
  const posttimer = useRef(null);
  const idOf = useCallback((userid, competencyid) => {
    const key = `${userid}:${competencyid}`;
    let id = ids.current.get(key);
    if (id === void 0) {
      id = ids.current.size + 1;
      ids.current.set(key, id);
    }
    return id;
  }, []);
  const loadRows = useCallback((asked, facets) => {
    const seq = ++rowsseq.current;
    if (!rowshold.current) {
      rowshold.current = hold("local_evidencehub/hub:rows");
    }
    setRowsloading(true);
    getRows(target, asked, facets).then((answer) => {
      if (seq !== rowsseq.current) {
        return;
      }
      if (answer.counted) {
        setCounted(Object.fromEntries(answer.filters.map((filter) => [filter.name, filter])));
      }
      if (!widthsread.current) {
        widthsread.current = true;
        setWidths(Object.fromEntries(answer.preference.widths.map((entry) => [entry.key, entry.width])));
      }
      if (answer.page !== query.current.page) {
        query.current = { ...query.current, page: answer.page };
        setCurrent(query.current);
      }
      setRows(answer);
      setSelected((before) => {
        const open = new Set(answer.rows.filter((row) => row.selectable).map((row) => idOf(row.userid, row.competencyid)));
        const kept = Array.from(before).filter((id) => answer.candecide && open.has(id));
        return kept.length === before.size ? before : new Set(kept);
      });
      setRowserror(false);
      setRowsloading(false);
      return;
    }).catch(() => {
      if (seq === rowsseq.current) {
        setRowserror(true);
        setRowsloading(false);
      }
    });
  }, [target, idOf]);
  const loadBand = useCallback((asked) => {
    const seq = ++bandseq.current;
    if (!bandhold.current) {
      bandhold.current = hold("local_evidencehub/hub:band");
    }
    setBandloading(true);
    getSummary(target, asked.scope, asked.search).then((answer) => {
      if (seq === bandseq.current) {
        setSummary(answer);
        setBanderror(false);
        setBandloading(false);
      }
      return;
    }).catch(() => {
      if (seq === bandseq.current) {
        setBanderror(true);
        setBandloading(false);
      }
    });
  }, [target]);
  const run = useCallback((change, readband) => {
    query.current = { ...query.current, ...change };
    setCurrent(query.current);
    setSelected(/* @__PURE__ */ new Set());
    loadRows(query.current, readband);
    if (readband) {
      loadBand(query.current);
    }
  }, [loadRows, loadBand]);
  useEffect(() => {
    if (!rowsloading && rowshold.current) {
      rowshold.current();
      rowshold.current = null;
    }
  }, [rowsloading, rows, rowserror]);
  useEffect(() => {
    if (!bandloading && bandhold.current) {
      bandhold.current();
      bandhold.current = null;
    }
  }, [bandloading, summary, banderror]);
  useEffect(() => {
    loadRows(query.current, true);
    loadBand(query.current);
  }, [loadRows, loadBand]);
  useEffect(() => () => {
    if (searchtimer.current !== null) {
      window.clearTimeout(searchtimer.current);
    }
    if (posttimer.current !== null) {
      window.clearTimeout(posttimer.current);
    }
    searchhold.current?.();
    flushref.current();
  }, []);
  const setView = /* @__PURE__ */ __name((chosen2) => {
    setViewState(chosen2);
    rememberView(VIEW_KEY, chosen2);
  }, "setView");
  const onSearch = /* @__PURE__ */ __name((text) => {
    setSearchtext(text);
    if (searchtimer.current !== null) {
      window.clearTimeout(searchtimer.current);
      searchtimer.current = null;
    }
    const trimmed = text.trim();
    const applied = trimmed.length >= props.searchmin ? trimmed : "";
    if (applied === query.current.search) {
      searchhold.current?.();
      searchhold.current = null;
      return;
    }
    if (text === "") {
      searchhold.current?.();
      searchhold.current = null;
      run({ search: "", page: 0 }, true);
      return;
    }
    if (!searchhold.current) {
      searchhold.current = hold("local_evidencehub/hub:search");
    }
    searchtimer.current = window.setTimeout(() => {
      searchtimer.current = null;
      run({ search: applied, page: 0 }, true);
      searchhold.current?.();
      searchhold.current = null;
    }, SEARCH_DELAY);
  }, "onSearch");
  const writeFilters = /* @__PURE__ */ __name((write) => {
    setSelected(/* @__PURE__ */ new Set());
    const release = hold("local_evidencehub/hub:filters");
    write().then(() => {
      run({ page: 0 }, true);
      return;
    }).catch(() => {
      setRowserror(true);
    }).finally(release);
  }, "writeFilters");
  const filters = (rows?.filters ?? []).map((filter) => counted[filter.name] ? { ...filter, options: counted[filter.name].options } : filter);
  const setChoice = /* @__PURE__ */ __name((name, value) => {
    const filter = filters.find((entry) => entry.name === name);
    if (filter) {
      writeFilters(() => setFilters(reportid, withChoice(filters, filter, value)));
    }
  }, "setChoice");
  const { allcolumns, usedefault, suspended, candecide } = settingsOf(rows);
  const chosenkeys = allcolumns.filter((column) => !column.fixed && column.active).map((column) => column.key);
  const writePreference = /* @__PURE__ */ __name((grid, reread) => {
    const release = hold("local_evidencehub/hub:preference");
    setPreference(target, grid, null).then(() => {
      if (reread) {
        loadRows(query.current, false);
      }
      return;
    }).catch(() => {
      if (reread) {
        setRowserror(true);
      }
    }).finally(release);
  }, "writePreference");
  const onAccounts = /* @__PURE__ */ __name((on) => {
    if (rows) {
      setRows({ ...rows, preference: { ...rows.preference, suspended: on } });
    }
    setSelected(/* @__PURE__ */ new Set());
    const release = hold("local_evidencehub/hub:accounts");
    setPreference(target, gridOf(usedefault, chosenkeys, widths), on).then(() => {
      run({ page: 0 }, true);
      return;
    }).catch(() => {
      setRowserror(true);
    }).finally(release);
  }, "onAccounts");
  const onColumn = /* @__PURE__ */ __name((key, on) => {
    const next = on ? [...chosenkeys, key] : chosenkeys.filter((chosen2) => chosen2 !== key);
    if (rows) {
      setRows({
        ...rows,
        columns: rows.columns.map((column) => column.key === key ? { ...column, active: on } : column),
        preference: { ...rows.preference, usedefault: false, columns: next }
      });
    }
    writePreference({ usedefault: false, columns: next, widths: widthList(widths) }, true);
  }, "onColumn");
  const flushWidths = /* @__PURE__ */ __name(() => {
    if (widthtimer.current !== null) {
      window.clearTimeout(widthtimer.current);
      widthtimer.current = null;
    }
    const next = pendingwidths.current;
    pendingwidths.current = null;
    if (next !== null) {
      writePreference(gridOf(usedefault, chosenkeys, next), false);
    }
    widthhold.current?.();
    widthhold.current = null;
  }, "flushWidths");
  flushref.current = flushWidths;
  const onWidth = /* @__PURE__ */ __name((key, width, defer = false) => {
    const next = { ...widths };
    if (width === null) {
      delete next[key];
    } else {
      next[key] = width;
    }
    setWidths(next);
    pendingwidths.current = next;
    if (!defer) {
      flushWidths();
      return;
    }
    if (widthtimer.current !== null) {
      window.clearTimeout(widthtimer.current);
    }
    if (!widthhold.current) {
      widthhold.current = hold("local_evidencehub/hub:width");
    }
    widthtimer.current = window.setTimeout(() => flushref.current(), WIDTH_DELAY);
  }, "onWidth");
  const onPreset = /* @__PURE__ */ __name((preset) => {
    const named = new Set(preset.columns);
    if (rows) {
      setRows({
        ...rows,
        columns: rows.columns.map((column) => column.fixed ? column : { ...column, active: named.has(column.key) }),
        presets: rows.presets.map((entry) => ({ ...entry, active: entry.name === preset.name })),
        preference: { ...rows.preference, usedefault: false, columns: preset.columns }
      });
    }
    writePreference({ usedefault: false, columns: preset.columns, widths: widthList(widths) }, true);
  }, "onPreset");
  const onSort = /* @__PURE__ */ __name((key) => {
    const sorted = rows?.sort ?? "";
    const direction = sorted === key && rows?.direction === "asc" ? "desc" : "asc";
    run({ sort: key, direction, page: 0 }, false);
  }, "onSort");
  const onInspect = /* @__PURE__ */ __name((button) => {
    const release = hold("local_evidencehub/hub:inspect");
    inspect(button, props.contextid, props.templateid).finally(release);
  }, "onInspect");
  const list = useMemo(
    () => (rows?.rows ?? []).map((row) => ({ ...row, id: idOf(row.userid, row.competencyid) })),
    [rows, idOf]
  );
  const decides = candecide && props.exportform !== null;
  const locked = new Set(list.filter((row) => !row.selectable).map((row) => row.id));
  const chosen = list.filter((row) => selected.has(row.id));
  const onSelect = /* @__PURE__ */ __name((id, on) => setSelected((before) => {
    const next = new Set(before);
    if (on && !locked.has(id)) {
      next.add(id);
    } else {
      next.delete(id);
    }
    return next;
  }), "onSelect");
  const onSelectPage = /* @__PURE__ */ __name((on) => setSelected(
    () => on ? new Set(list.filter((row) => !locked.has(row.id)).map((row) => row.id)) : /* @__PURE__ */ new Set()
  ), "onSelectPage");
  const boxLabel = /* @__PURE__ */ __name((row) => fill(labels.selectrow, row.drawertitle), "boxLabel");
  const selection = decides ? {
    selected,
    locked,
    label: boxLabel,
    pagelabel: labels.selectpage,
    onSelect,
    onSelectPage
  } : void 0;
  const onExport = /* @__PURE__ */ __name(() => {
    if (props.exportform === null || chosen.length === 0 || posting) {
      return;
    }
    setPosting(true);
    posttimer.current = window.setTimeout(() => {
      posttimer.current = null;
      setPosting(false);
    }, POST_GUARD);
    exportDossiers({
      ...props.exportform,
      contextid: props.contextid,
      templateid: props.templateid,
      suspended,
      pairs: chosen.map((row) => ({ userid: row.userid, competencyid: row.competencyid }))
    });
  }, "onExport");
  const filtered = filters.some((filter) => filter.applied) || current.search !== "" || current.scope !== "all";
  const identity = allcolumns.filter((column) => column.key.startsWith("identity:"));
  const shown = allcolumns.filter((column) => column.key === "worker" || column.key === "competency" || !column.fixed && column.active);
  const cards = view === "cards" || view === "auto" && narrow;
  let body = null;
  if (rows !== null) {
    body = cards ? /* @__PURE__ */ jsxDEV(
      Cards,
      {
        labels,
        columns: shown,
        identity,
        rows: list,
        single: narrow,
        selection,
        onInspect
      },
      void 0,
      false,
      {
        fileName: "public/local/evidencehub/js/esm/src/hub/HubView.tsx",
        lineNumber: 657,
        columnNumber: 13
      }
    ) : /* @__PURE__ */ jsxDEV(
      Grid,
      {
        labels,
        columns: shown,
        identity,
        rows: list,
        widths,
        sort: rows.sort,
        direction: rows.direction,
        selection,
        onInspect,
        onSort,
        onWidth,
        onWidthCommit: () => flushref.current()
      },
      void 0,
      false,
      {
        fileName: "public/local/evidencehub/js/esm/src/hub/HubView.tsx",
        lineNumber: 667,
        columnNumber: 13
      }
    );
  }
  const skeleton = SKELETON_COLUMNS.map((key) => ({
    key,
    title: key === "worker" ? labels.worker : "",
    sortable: false,
    "default": true,
    active: true,
    fixed: key === "worker"
  }));
  const workers = new Set(chosen.map((row) => row.userid)).size;
  return /* @__PURE__ */ jsxDEV("div", { className: "local-evidencehub-rview-root", ref: root, "data-region": "local-evidencehub-hubview", children: [
    /* @__PURE__ */ jsxDEV(
      Band,
      {
        labels,
        summary,
        loading: bandloading,
        error: banderror,
        canfilter: rows !== null,
        onStatus: (status) => setChoice(STATUS_FILTER, String(status)),
        onCompetency: (competencyid) => setChoice(COMPETENCY_FILTER, String(competencyid)),
        onRetry: () => loadBand(query.current)
      },
      void 0,
      false,
      {
        fileName: "public/local/evidencehub/js/esm/src/hub/HubView.tsx",
        lineNumber: 695,
        columnNumber: 13
      }
    ),
    /* @__PURE__ */ jsxDEV(
      Bar,
      {
        labels,
        narrow,
        scope: current.scope,
        summary,
        total: rows === null ? null : rows.total,
        filters,
        columns: allcolumns.filter((column) => !column.key.startsWith("identity:")),
        presets: rows?.presets ?? [],
        usedefault,
        haswidths: Object.keys(widths).length > 0,
        suspended,
        view,
        search: searchtext,
        searchmin: props.searchmin,
        searching: rowsloading,
        matrixurl: props.matrixurl,
        tableurl: props.tableurl,
        onScope: (scope) => run({ scope, page: 0 }, true),
        onSearch,
        onFilter: (filter, value) => writeFilters(() => setFilters(reportid, withChoice(filters, filter, value))),
        onRemoveFilter: (filter) => writeFilters(() => setFilters(reportid, without(filters, filter))),
        onClearFilters: () => writeFilters(() => resetFilters(reportid)),
        onClearAll: () => {
          setSearchtext("");
          query.current = { ...query.current, search: "" };
          if (filters.some((filter) => filter.applied)) {
            writeFilters(() => resetFilters(reportid));
          } else {
            run({ page: 0 }, true);
          }
        },
        onAccounts,
        onColumn,
        onPreset,
        onDefaultColumns: () => {
          setWidths({});
          if (rows) {
            setRows({ ...rows, preference: { ...rows.preference, usedefault: true, columns: [], widths: [] } });
          }
          writePreference({ usedefault: true, columns: [], widths: [] }, true);
        },
        onResetWidths: () => {
          setWidths({});
          writePreference(gridOf(usedefault, chosenkeys, {}), false);
        },
        onView: setView
      },
      void 0,
      false,
      {
        fileName: "public/local/evidencehub/js/esm/src/hub/HubView.tsx",
        lineNumber: 705,
        columnNumber: 13
      }
    ),
    /* @__PURE__ */ jsxDEV(
      RowsRegion,
      {
        labels,
        loading: rowsloading,
        loaded: rows !== null,
        error: rowserror,
        total: rows?.total ?? 0,
        filtered,
        cards,
        narrow,
        skeleton: { columns: skeleton, lead: "worker", widths, sizing: SIZING },
        dock: decides && chosen.length > 0 ? /* @__PURE__ */ jsxDEV(Dock, { labels, count: chosen.length, workers, posting, onExport }, void 0, false, {
          fileName: "public/local/evidencehub/js/esm/src/hub/HubView.tsx",
          lineNumber: 765,
          columnNumber: 21
        }) : null,
        onRetry: () => loadRows(query.current, true),
        children: body
      },
      void 0,
      false,
      {
        fileName: "public/local/evidencehub/js/esm/src/hub/HubView.tsx",
        lineNumber: 754,
        columnNumber: 13
      }
    ),
    rows !== null && rows.total > 0 ? /* @__PURE__ */ jsxDEV(
      Pager,
      {
        labels,
        page: current.page,
        perpage: current.perpage,
        perpages: props.perpages,
        total: rows.total,
        shown: rows.rows.length,
        narrow,
        onPage: (page) => run({ page }, false),
        onPerpage: (perpage) => run({ perpage, page: 0 }, false)
      },
      void 0,
      false,
      {
        fileName: "public/local/evidencehub/js/esm/src/hub/HubView.tsx",
        lineNumber: 772,
        columnNumber: 17
      }
    ) : null
  ] }, void 0, true, {
    fileName: "public/local/evidencehub/js/esm/src/hub/HubView.tsx",
    lineNumber: 694,
    columnNumber: 9
  });
}, "HubView");
var HubView_default = HubView;
export {
  HubView_default as default
};
//# sourceMappingURL=HubView.dev.js.map
