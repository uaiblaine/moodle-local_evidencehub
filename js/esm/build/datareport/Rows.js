import{CardsSkeleton as b}from"./Cards";import{GridSkeleton as p}from"./Grid";import{Fragment as c,jsx as o,jsxs as n}from"react/jsx-runtime";/**
 * The rows' region: what it holds while the rows load, fail, are none, or are there.
 *
 * The first load draws a skeleton of the grid or of the cards, with aria-busy and a status for
 * assistive technology; later loads keep the old rows, dimmed, under a 2 px progress line. A failed
 * read says so with a retry, above the old rows when there are some. No rows is the empty state, worded
 * for a filtered view or an empty one. The rows themselves (the view's grid or cards) are the children;
 * a dock the view keeps under them (a queue's decision bar) follows the region.
 *
 * Strings read from the view's labels: caption, loading, loaderror, retry, empty, nomatch, nomatchhelp,
 * scrollhint.
 *
 * @module     local_evidencehub/datareport/Rows
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const g=({labels:e,loaded:t,error:i,total:s,filtered:l,cards:a,narrow:d,skeleton:m,children:u,onRetry:v})=>{const r=n("p",{className:"local-evidencehub-rview-error",children:[e.loaderror," ",o("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:v,children:e.retry})]});return!t&&i?r:t?s===0?n("div",{className:"local-evidencehub-rview-empty",children:[o("p",{className:"local-evidencehub-rview-empty-title",children:l?e.nomatch:e.empty}),l?o("p",{className:"local-evidencehub-rview-empty-text",children:e.nomatchhelp}):null]}):n("div",{className:"local-evidencehub-rview-dim",children:[i?r:null,u,a?null:o("p",{className:"local-evidencehub-rview-hint",children:e.scrollhint})]}):n(c,{children:[a?o(b,{single:d}):o(p,{...m}),o("span",{className:"visually-hidden",role:"status",children:e.loading})]})},w=e=>n(c,{children:[n("section",{className:"local-evidencehub-rview-region","aria-label":e.labels.caption,"aria-busy":e.loading,"data-region":"local-evidencehub-rows",children:[e.loading&&e.loaded?o("div",{className:"local-evidencehub-rview-progress"}):null,o(g,{...e})]}),e.dock??null]});export{w as RowsRegion};
