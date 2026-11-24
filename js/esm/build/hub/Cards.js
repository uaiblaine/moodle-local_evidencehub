import{Cards as C}from"../datareport/Cards";import{Lines as w}from"../datareport/cells";import{IdentityLines as b,InspectButton as h,StatusLabel as f,cellHtml as a}from"./cells";import{Fragment as y,jsx as t,jsxs as l}from"react/jsx-runtime";/**
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
 */const R=["worker","competency","status","expiry","type"],p=({row:o,column:r})=>l("div",{className:"local-evidencehub-rview-card-line",children:[t("span",{className:"local-evidencehub-rview-card-key",children:r.title}),t("span",{className:"local-evidencehub-rview-inline",dangerouslySetInnerHTML:{__html:a(o,r.key)}})]}),g=(o,r,d,e,m)=>{const i=n=>r.find(v=>v.key===n),s=i("expiry"),c=i("type"),u=r.filter(n=>!R.includes(n.key)&&a(e,n.key).trim()!=="");return{head:l(y,{children:[l("div",{className:"local-evidencehub-rview-card-who",children:[t("span",{className:"local-evidencehub-rview-card-name",dangerouslySetInnerHTML:{__html:a(e,"worker")}}),t(b,{row:e,identity:d})]}),t(h,{row:e,label:o.inspect,onInspect:m})]}),lines:l(y,{children:[l("div",{className:"local-evidencehub-rview-card-line",children:[t("span",{className:"local-evidencehub-rview-inline",dangerouslySetInnerHTML:{__html:a(e,"competency")}}),i("status")?t(f,{row:e}):null]}),s?t(p,{row:e,column:s}):null,c?t(p,{row:e,column:c}):null]}),fields:u.map(n=>({key:n.key,title:n.title,content:t(w,{parts:[a(e,n.key)]})}))}},P=({labels:o,columns:r,identity:d,rows:e,single:m,selection:i,onInspect:s})=>t(C,{labels:o,rows:e,single:m,selection:i,renderCard:c=>g(o,r,d,c,s)});export{P as Cards};
