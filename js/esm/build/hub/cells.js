import{Lines as i}from"../datareport/cells";import{Icon as l}from"../datareport/icons";import{makeSizing as u}from"../datareport/sizing";import{plain as a}from"../datareport/str";import{Fragment as k,jsx as n,jsxs as x}from"react/jsx-runtime";/**
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
 */const m={worker:260,competency:240,status:180,expiry:132,count:104,type:200,issuer:200,competencyidnumber:150,framework:200,issuedate:132,expirydate:132,verification:160,changed:160},p=180,d={worker:200,expiry:104,issuedate:104,expirydate:104,changed:104},I=80,y=800,C=u(m,p,d,I,y),c=-1,b=0,R=1,g=2,w=3,E=4,M=5,f=t=>{switch(t){case g:return"success";case w:return"info";case E:return"warning";case M:return"danger";case R:case b:case c:default:return"neutral"}},o=(t,e)=>t.cells.find(r=>r.key===e)?.html??"",h=({row:t})=>{const e=o(t,"status");return e===""?null:x("span",{className:`local-evidencehub-rview-status local-evidencehub-rview-status--${f(t.status)}`,title:a(e),children:[t.status===c?n(l,{name:"lock"}):null,n("span",{dangerouslySetInnerHTML:{__html:e}})]})},D=({row:t,column:e})=>e==="status"?n(h,{row:t}):n(i,{parts:[o(t,e)]}),N=({row:t,label:e,onInspect:r})=>t.inspect?n("button",{type:"button",className:"btn btn-sm btn-outline-secondary local-evidencehub-rview-inspect","aria-label":t.inspectlabel,"data-userid":t.userid,"data-competencyid":t.competencyid,"data-title":t.drawertitle,onClick:s=>r(s.currentTarget),children:e}):null,P=({row:t,identity:e})=>n(k,{children:e.map(r=>{const s=o(t,r.key);return s===""?null:n("span",{className:"local-evidencehub-rview-sub",title:a(s),dangerouslySetInnerHTML:{__html:s}},r.key)})});export{D as CellContent,P as IdentityLines,N as InspectButton,b as MISSING,c as RESTRICTED,C as SIZING,h as StatusLabel,o as cellHtml,f as tone};
