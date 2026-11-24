import{useId as k}from"react";import{fillObject as h,num as r}from"./str";import{jsx as a,jsxs as b}from"react/jsx-runtime";/**
 * The foot of the rows: which rows these are, the pages and the page size (AR-D14: 30, 50 or 100).
 *
 * The server pages (one data query and one count whatever the size); the client never holds more
 * than one page. Narrow, the pages read "Page 2 of 9" between Previous and Next.
 *
 * @module     local_evidencehub/datareport/Pager
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const P=(e,o)=>[...new Set([1,e-1,e,e+1,o])].filter(t=>t>=1&&t<=o).sort((t,u)=>t-u),R=({labels:e,page:o,perpage:t,perpages:u,total:c,shown:f,narrow:w,onPage:m,onPerpage:y})=>{const g=k(),p=Math.max(1,Math.ceil(c/t)),l=o+1,d=c>0?o*t+1:0,N=c>0?d+f-1:0;let i=[];if(w)i=[a("span",{className:"local-evidencehub-rview-pager-gap",children:h(e.pageof,{page:r(l),pages:r(p)})},"of")];else{const n=P(l,p);n.forEach((s,v)=>{v>0&&s-n[v-1]>1&&i.push(a("span",{className:"local-evidencehub-rview-pager-gap","aria-hidden":"true",children:"\u2026"},`gap${s}`)),i.push(a("button",{type:"button","aria-current":s===l?"page":void 0,onClick:()=>m(s-1),children:r(s)},s))})}return b("div",{className:"local-evidencehub-rview-foot",children:[a("span",{className:"local-evidencehub-rview-num","data-region":"local-evidencehub-range",children:h(e.range,{from:r(d),to:r(N),total:r(c)})}),p>1?b("nav",{className:"local-evidencehub-rview-pager","aria-label":e.pages,children:[a("button",{type:"button",disabled:l===1,onClick:()=>m(o-1),children:e.previous}),i,a("button",{type:"button",disabled:l===p,onClick:()=>m(o+1),children:e.next})]}):null,b("span",{className:"local-evidencehub-rview-perpage",children:[a("label",{htmlFor:g,children:e.perpage}),a("select",{id:g,className:"form-select form-select-sm",value:t,onChange:n=>y(Number(n.target.value)),children:u.map(n=>a("option",{value:n,children:r(n)},n))})]})]})};export{R as Pager};
