import{useState as m}from"react";import{RowBox as w}from"./Grid";import{jsx as e,jsxs as d}from"react/jsx-runtime";/**
 * The card view (AR-D9): the same rows, page and order as the grid, one article each.
 *
 * A card is a first line (who, and the row's action), the view's fixed lines, then the other chosen
 * fields in a list, at once on a wide screen (a grid of 17.5rem cards) and behind "More" on a narrow
 * one (one column). What each part holds is the view's (`renderCard`); a view that selects rows passes
 * a selection, whose box leads the first line.
 *
 * Strings read from the view's labels: more, less.
 *
 * @module     local_evidencehub/datareport/Cards
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const u=({labels:a,row:l,single:c,selection:i,renderCard:t})=>{const[s,o]=m(!1),n=t(l),v=!c||s,b=i?.selected.has(l.id)??!1;return d("article",{className:"local-evidencehub-rview-card"+(b?" local-evidencehub-rview-card--selected":""),role:"listitem","data-record":l.id,children:[d("div",{className:"local-evidencehub-rview-card-l1",children:[i?e(w,{selection:i,row:l}):null,n.head]}),n.lines,n.fields.length>0?d("div",{className:"local-evidencehub-rview-card-more",children:[c?e("button",{type:"button",className:"btn btn-link btn-sm local-evidencehub-rview-linkbtn","aria-expanded":s,onClick:()=>o(!s),children:s?a.less:a.more}):null,v?e("dl",{className:"local-evidencehub-rview-card-list",children:n.fields.map(r=>d("div",{className:"local-evidencehub-rview-card-item",children:[e("dt",{children:r.title}),e("dd",{children:r.content})]},r.key))}):null]}):null]})},R=({labels:a,rows:l,single:c,selection:i,renderCard:t})=>e("div",{className:"local-evidencehub-rview-cards"+(c?" local-evidencehub-rview-cards--single":""),role:"list",children:l.map(s=>e(u,{labels:a,row:s,single:c,selection:i,renderCard:t},s.id))}),k=({single:a})=>e("div",{className:"local-evidencehub-rview-cards"+(a?" local-evidencehub-rview-cards--single":""),"aria-hidden":"true",children:Array.from({length:a?4:6},(l,c)=>d("div",{className:"local-evidencehub-rview-card local-evidencehub-rview-sk",children:[e("span",{className:"local-evidencehub-rview-skb local-evidencehub-rview-skb--w60"}),e("span",{className:"local-evidencehub-rview-skb local-evidencehub-rview-skb--w80"}),e("span",{className:"local-evidencehub-rview-skb local-evidencehub-rview-skb--w60"}),e("span",{className:"local-evidencehub-rview-skb local-evidencehub-rview-skb--w40"})]},c))});export{R as Cards,k as CardsSkeleton};
