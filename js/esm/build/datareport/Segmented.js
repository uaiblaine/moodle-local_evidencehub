import{Fragment as g,jsx as c,jsxs as u}from"react/jsx-runtime";/**
 * A segmented control (the Design System's segmented-control.md): one of two to four visible values.
 *
 * A radiogroup with one tab stop, the chosen cell; the arrow keys, Home and End move to another cell
 * and choose it. A value may carry a count, drawn quietly after its label (counter.md).
 *
 * @module     local_evidencehub/datareport/Segmented
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const v=({label:s,value:a,options:l,small:d,onChange:t})=>{const o=e=>{const n=l.findIndex(i=>i.value===a);let r=-1;if(e.key==="ArrowRight"||e.key==="ArrowDown"?r=(n+1)%l.length:e.key==="ArrowLeft"||e.key==="ArrowUp"?r=(n-1+l.length)%l.length:e.key==="Home"?r=0:e.key==="End"&&(r=l.length-1),r<0)return;e.preventDefault(),t(l[r].value),e.currentTarget.querySelectorAll('[role="radio"]')[r]?.focus()};return c("div",{className:"local-evidencehub-rview-seg"+(d?" local-evidencehub-rview-seg--small":""),role:"radiogroup","aria-label":s,onKeyDown:o,children:l.map(e=>u("button",{type:"button",className:"local-evidencehub-rview-seg-cell",role:"radio","aria-checked":e.value===a,tabIndex:e.value===a?0:-1,onClick:()=>t(e.value),children:[e.label,e.count!==void 0?u(g,{children:[" ",c("small",{className:"local-evidencehub-rview-seg-count",children:e.count})]}):null]},e.value))})};export{v as Segmented};
