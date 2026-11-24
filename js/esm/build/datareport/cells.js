import{plain as i}from"./str";import{Fragment as l,jsx as r}from"react/jsx-runtime";/**
 * A cell drawn from the server's formatted HTML, one fact per line.
 *
 * A data report's cells are formatted on the server, where the masking and the escaping ran, so a cell
 * is inserted as HTML and never built here. A cell that holds several facts has them joined by a
 * newline; the first line is the cell's line and the others its sub-lines. Every line ends in an
 * ellipsis, its whole text in its title.
 *
 * @module     local_evidencehub/datareport/cells
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const o=(t,n)=>n?t.split(`
`).map(e=>e.trim()).filter(e=>e!==""):[t],a=({parts:t})=>r(l,{children:t.map((n,e)=>r("span",{className:e===0?"local-evidencehub-rview-c":"local-evidencehub-rview-sub",title:i(n),dangerouslySetInnerHTML:{__html:n}},e))});export{a as Lines,o as splitLines};
