import{Grid as h}from"../datareport/Grid";import{CellContent as y,IdentityLines as b,InspectButton as g,SIZING as C,cellHtml as R}from"./cells";import{jsx as o,jsxs as i}from"react/jsx-runtime";/**
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
 */const G=({labels:t,columns:r,identity:n,rows:l,widths:d,sort:s,direction:c,selection:m,onInspect:a,onSort:p,onWidth:w,onWidthCommit:u})=>o(h,{labels:t,columns:r,lead:"worker",rows:l,widths:d,sizing:C,sort:s,direction:c,selection:m,renderLead:e=>i("div",{className:"local-evidencehub-rview-who",children:[i("div",{className:"local-evidencehub-rview-who-text",children:[o("span",{className:"local-evidencehub-rview-c",dangerouslySetInnerHTML:{__html:R(e,"worker")}}),o(b,{row:e,identity:n})]}),o(g,{row:e,label:t.inspect,onInspect:a})]}),renderCell:(e,v)=>o(y,{row:e,column:v.key}),onSort:p,onWidth:w,onWidthCommit:u});export{G as Grid};
