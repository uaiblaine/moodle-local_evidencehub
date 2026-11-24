import{useCallback as et,useEffect as tt,useMemo as Nn,useRef as _,useState as O}from"react";import{useEffect as Kn,useState as zn}from"react";var $n=576,It=e=>{let[t,n]=zn(!1);return Kn(()=>{let r=e.current;if(!r||typeof ResizeObserver>"u")return;let l=new ResizeObserver(s=>{let i=s[0]?.contentRect.width??r.clientWidth;n(i>0&&i<$n)});return l.observe(r),()=>l.disconnect()},[e]),t},Dt=e=>{try{let t=window.localStorage.getItem(e);return t==="table"||t==="cards"?t:"auto"}catch{return"auto"}},Ft=(e,t)=>{try{window.localStorage.setItem(e,t)}catch{}};import{useId as Un}from"react";var P=(e,t)=>(e||"").split("{$a}").join(t),Re=(e,t)=>Object.entries(t).reduce((n,[r,l])=>n.split(`{$a->${r}}`).join(l),e||""),We=(e,t,n,r)=>P(n===1&&e[`${t}_one`]?e[`${t}_one`]:e[t],r),R=e=>{let t=document.documentElement.lang||void 0;try{return new Intl.NumberFormat(t).format(e)}catch{return String(e)}};var Ne=e=>{let t=document.createElement("template");return t.innerHTML=e,(t.content.textContent||"").replace(/\s+/g," ").trim()};import{jsx as j,jsxs as it}from"react/jsx-runtime";var Gn=(e,t)=>[...new Set([1,e-1,e,e+1,t])].filter(n=>n>=1&&n<=t).sort((n,r)=>n-r),At=({labels:e,page:t,perpage:n,perpages:r,total:l,shown:s,narrow:i,onPage:w,onPerpage:m})=>{let d=Un(),a=Math.max(1,Math.ceil(l/n)),f=t+1,x=l>0?t*n+1:0,I=l>0?x+s-1:0,v=[];if(i)v=[j("span",{className:"local-evidencehub-rview-pager-gap",children:Re(e.pageof,{page:R(f),pages:R(a)})},"of")];else{let p=Gn(f,a);p.forEach((L,y)=>{y>0&&L-p[y-1]>1&&v.push(j("span",{className:"local-evidencehub-rview-pager-gap","aria-hidden":"true",children:"\u2026"},`gap${L}`)),v.push(j("button",{type:"button","aria-current":L===f?"page":void 0,onClick:()=>w(L-1),children:R(L)},L))})}return it("div",{className:"local-evidencehub-rview-foot",children:[j("span",{className:"local-evidencehub-rview-num","data-region":"local-evidencehub-range",children:Re(e.range,{from:R(x),to:R(I),total:R(l)})}),a>1?it("nav",{className:"local-evidencehub-rview-pager","aria-label":e.pages,children:[j("button",{type:"button",disabled:f===1,onClick:()=>w(t-1),children:e.previous}),v,j("button",{type:"button",disabled:f===a,onClick:()=>w(t+1),children:e.next})]}):null,it("span",{className:"local-evidencehub-rview-perpage",children:[j("label",{htmlFor:d,children:e.perpage}),j("select",{id:d,className:"form-select form-select-sm",value:n,onChange:p=>m(Number(p.target.value)),children:r.map(p=>j("option",{value:p,children:R(p)},p))})]})]})};var J=e=>{let t=window.M?.util;t?.js_pending(e);let n=!0;return()=>{n&&(n=!1,t?.js_complete(e))}};import{useState as Zn}from"react";import{useEffect as qn,useRef as ze}from"react";var _t=(e,t,n,r,l)=>({defaultWidth:s=>e[s]??t,minimumWidth:s=>n[s]??r,maximum:l}),W=(e,t,n)=>Math.min(e.maximum,Math.max(e.minimumWidth(n),t[n]??e.defaultWidth(n)));import{jsx as N,jsxs as oe}from"react/jsx-runtime";var Ke=16,Qn={ascending:"\u25B2",descending:"\u25BC",none:""},Yn=(e,t)=>e?t==="asc"?"ascending":"descending":"none",Xn=({selection:e,rows:t})=>{let n=ze(null),r=t.filter(i=>!e.locked.has(i.id)),l=r.filter(i=>e.selected.has(i.id)).length,s=r.length>0&&l===r.length;return qn(()=>{n.current&&(n.current.indeterminate=l>0&&!s)},[l,s]),oe("label",{className:"local-evidencehub-rview-pickwrap",children:[N("input",{ref:n,type:"checkbox",className:"local-evidencehub-rview-pick",checked:s,disabled:r.length===0,onChange:i=>e.onSelectPage(i.target.checked)}),N("span",{className:"visually-hidden",children:e.pagelabel})]})},st=({selection:e,row:t})=>e.locked.has(t.id)?N("span",{className:"local-evidencehub-rview-pickwrap local-evidencehub-rview-pick--none"}):oe("label",{className:"local-evidencehub-rview-pickwrap",children:[N("input",{type:"checkbox",className:"local-evidencehub-rview-pick",checked:e.selected.has(t.id),onChange:n=>e.onSelect(t.id,n.target.checked)}),N("span",{className:"visually-hidden",children:e.label(t)})]}),Ht=({labels:e,columns:t,lead:n,rows:r,widths:l,sizing:s,sort:i,direction:w,selection:m,renderLead:d,renderCell:a,onSort:f,onWidth:x,onWidthCommit:I})=>{let v=ze(null),p=ze(null),L=ze(null),y=t.reduce((u,b)=>u+W(s,l,b.key),0),ae=u=>{u.currentTarget.classList.toggle("is-scrolled",u.currentTarget.scrollLeft>0)},U=(u,b)=>{let T=p.current?.querySelector(`col[data-col="${CSS.escape(u)}"]`);!T||!p.current||(T.style.width=`${b}px`,p.current.style.width=`${y-W(s,l,u)+b}px`)},q=(u,b)=>Math.round(Math.min(s.maximum,Math.max(s.minimumWidth(u),b))),ve=(u,b)=>{if(u.button!==0)return;u.preventDefault(),u.currentTarget.setPointerCapture(u.pointerId),u.currentTarget.classList.add("is-active");let T=W(s,l,b);L.current={key:b,x:u.clientX,width:T,now:T}},G=u=>{let b=L.current;if(!b)return;let T=document.documentElement.dir==="rtl",H=(u.clientX-b.x)*(T?-1:1),c=q(b.key,b.width+H);c!==b.now&&(U(b.key,c),b.now=c)},te=u=>{let b=L.current;L.current=null,u.currentTarget.classList.remove("is-active"),b&&b.now!==b.width&&x(b.key,b.now)},ne=(u,b)=>{let T=document.documentElement.dir==="rtl",H=0;if(u.key==="ArrowRight")H=T?-Ke:Ke;else if(u.key==="ArrowLeft")H=T?Ke:-Ke;else if(u.key==="Home"){u.preventDefault(),x(b,null,!0);return}if(H===0)return;u.preventDefault();let c=q(b,W(s,l,b)+H);c!==W(s,l,b)&&x(b,c,!0)};return N("div",{className:"local-evidencehub-rview-gridframe",ref:v,tabIndex:0,role:"region","aria-label":e.grid,onScroll:ae,children:oe("table",{className:"local-evidencehub-rview-grid",ref:p,style:{width:`${y}px`},children:[N("caption",{className:"visually-hidden",children:e.caption}),N("colgroup",{children:t.map(u=>N("col",{"data-col":u.key,style:{width:`${W(s,l,u.key)}px`}},u.key))}),N("thead",{children:N("tr",{children:t.map(u=>{let b=Yn(i===u.key,w),T=N("span",{className:"local-evidencehub-rview-th-title",children:u.title}),H=u.sortable?oe("button",{type:"button",className:"local-evidencehub-rview-sortbtn",onClick:()=>f(u.key),children:[T,N("span",{className:"local-evidencehub-rview-arrow","aria-hidden":"true",children:Qn[b]})]}):N("span",{className:"local-evidencehub-rview-plain",children:T});return oe("th",{scope:"col",className:u.key===n?"local-evidencehub-rview-fixed":void 0,"aria-sort":u.sortable?b:void 0,title:u.title,"data-column":u.key,children:[m&&u.key===n?oe("div",{className:"local-evidencehub-rview-lead",children:[N(Xn,{selection:m,rows:r}),H]}):H,N("span",{className:"local-evidencehub-rview-resize",role:"separator","aria-orientation":"vertical","aria-label":P(e.resize,u.title),"aria-valuemin":s.minimumWidth(u.key),"aria-valuemax":s.maximum,"aria-valuenow":W(s,l,u.key),tabIndex:0,onPointerDown:c=>ve(c,u.key),onPointerMove:G,onPointerUp:te,onPointerCancel:te,onDoubleClick:()=>{W(s,l,u.key)!==s.defaultWidth(u.key)&&x(u.key,null)},onKeyDown:c=>ne(c,u.key),onBlur:I})]},u.key)})})}),N("tbody",{children:r.map(u=>N("tr",{"data-record":u.id,className:m?.selected.has(u.id)?"local-evidencehub-rview-row--selected":void 0,children:t.map(b=>b.key===n?N("th",{scope:"row",className:"local-evidencehub-rview-fixed",children:m?oe("div",{className:"local-evidencehub-rview-lead",children:[N(st,{selection:m,row:u}),d(u,b)]}):d(u,b)},b.key):N("td",{children:a(u,b)},b.key))},u.id))})]})})},Vt=({columns:e,lead:t,widths:n,sizing:r})=>{let l=e.reduce((i,w)=>i+W(r,n,w.key),0),s=["local-evidencehub-rview-skb--w80","local-evidencehub-rview-skb--w60","local-evidencehub-rview-skb--w40"];return N("div",{className:"local-evidencehub-rview-gridframe","aria-hidden":"true",children:oe("table",{className:"local-evidencehub-rview-grid",style:{width:`${l}px`},children:[N("colgroup",{children:e.map(i=>N("col",{style:{width:`${W(r,n,i.key)}px`}},i.key))}),N("thead",{children:N("tr",{children:e.map(i=>N("th",{className:i.key===t?"local-evidencehub-rview-fixed":void 0,children:N("span",{className:"local-evidencehub-rview-plain",children:N("span",{className:"local-evidencehub-rview-th-title",children:i.title})})},i.key))})}),N("tbody",{children:Array.from({length:8},(i,w)=>N("tr",{className:"local-evidencehub-rview-sk",children:e.map((m,d)=>N("td",{className:m.key===t?"local-evidencehub-rview-fixed":void 0,children:N("span",{className:"local-evidencehub-rview-skb "+s[(w+d)%3]})},m.key))},w))})]})})};import{jsx as B,jsxs as Ce}from"react/jsx-runtime";var jn=({labels:e,row:t,single:n,selection:r,renderCard:l})=>{let[s,i]=Zn(!1),w=l(t),m=!n||s,d=r?.selected.has(t.id)??!1;return Ce("article",{className:"local-evidencehub-rview-card"+(d?" local-evidencehub-rview-card--selected":""),role:"listitem","data-record":t.id,children:[Ce("div",{className:"local-evidencehub-rview-card-l1",children:[r?B(st,{selection:r,row:t}):null,w.head]}),w.lines,w.fields.length>0?Ce("div",{className:"local-evidencehub-rview-card-more",children:[n?B("button",{type:"button",className:"btn btn-link btn-sm local-evidencehub-rview-linkbtn","aria-expanded":s,onClick:()=>i(!s),children:s?e.less:e.more}):null,m?B("dl",{className:"local-evidencehub-rview-card-list",children:w.fields.map(a=>Ce("div",{className:"local-evidencehub-rview-card-item",children:[B("dt",{children:a.title}),B("dd",{children:a.content})]},a.key))}):null]}):null]})},Ot=({labels:e,rows:t,single:n,selection:r,renderCard:l})=>B("div",{className:"local-evidencehub-rview-cards"+(n?" local-evidencehub-rview-cards--single":""),role:"list",children:t.map(s=>B(jn,{labels:e,row:s,single:n,selection:r,renderCard:l},s.id))}),Bt=({single:e})=>B("div",{className:"local-evidencehub-rview-cards"+(e?" local-evidencehub-rview-cards--single":""),"aria-hidden":"true",children:Array.from({length:e?4:6},(t,n)=>Ce("div",{className:"local-evidencehub-rview-card local-evidencehub-rview-sk",children:[B("span",{className:"local-evidencehub-rview-skb local-evidencehub-rview-skb--w60"}),B("span",{className:"local-evidencehub-rview-skb local-evidencehub-rview-skb--w80"}),B("span",{className:"local-evidencehub-rview-skb local-evidencehub-rview-skb--w60"}),B("span",{className:"local-evidencehub-rview-skb local-evidencehub-rview-skb--w40"})]},n))});import{Fragment as Kt,jsx as ee,jsxs as me}from"react/jsx-runtime";var Jn=({labels:e,loaded:t,error:n,total:r,filtered:l,cards:s,narrow:i,skeleton:w,children:m,onRetry:d})=>{let a=me("p",{className:"local-evidencehub-rview-error",children:[e.loaderror," ",ee("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:d,children:e.retry})]});return!t&&n?a:t?r===0?me("div",{className:"local-evidencehub-rview-empty",children:[ee("p",{className:"local-evidencehub-rview-empty-title",children:l?e.nomatch:e.empty}),l?ee("p",{className:"local-evidencehub-rview-empty-text",children:e.nomatchhelp}):null]}):me("div",{className:"local-evidencehub-rview-dim",children:[n?a:null,m,s?null:ee("p",{className:"local-evidencehub-rview-hint",children:e.scrollhint})]}):me(Kt,{children:[s?ee(Bt,{single:i}):ee(Vt,{...w}),ee("span",{className:"visually-hidden",role:"status",children:e.loading})]})},Wt=e=>me(Kt,{children:[me("section",{className:"local-evidencehub-rview-region","aria-label":e.labels.caption,"aria-busy":e.loading,"data-region":"local-evidencehub-rows",children:[e.loading&&e.loaded?ee("div",{className:"local-evidencehub-rview-progress"}):null,ee(Jn,{...e})]}),e.dock??null]});import{requireAsync as zt}from"@moodle/lms/core/amd";var $t=async e=>(await zt("core/first"),zt(e)),Ut=async(e,t,n)=>{try{await(await $t("local_evidencehub/drawer")).open(e,t,n)}catch(r){try{(await $t("core/notification")).exception(r)}catch{window.console.error(r)}}};import{Fragment as nr,jsx as k,jsxs as V}from"react/jsx-runtime";var er="\u2013",$e=({label:e,value:t,sub:n})=>V("div",{className:"local-evidencehub-rview-tile",children:[k("div",{className:"local-evidencehub-rview-tile-label",children:e}),k("div",{className:"local-evidencehub-rview-tile-value",children:t}),k("div",{className:"local-evidencehub-rview-tile-sub",children:n})]}),Gt=({label:e,share:t,figure:n,title:r,disabled:l,onPress:s})=>k("li",{className:"local-evidencehub-rview-hitem",children:V("button",{type:"button",className:"local-evidencehub-rview-hrow",title:r,disabled:l,onClick:s,children:[k("span",{className:"local-evidencehub-rview-hrow-label",children:e}),k("span",{className:"local-evidencehub-rview-track","aria-hidden":"true",children:k("span",{className:"local-evidencehub-rview-fill",style:{width:`${Math.round(Math.min(1,Math.max(0,t))*100)}%`}})}),k("span",{className:"local-evidencehub-rview-hrow-n",children:n})]})}),tr=()=>V("div",{"aria-hidden":"true",children:[k("div",{className:"local-evidencehub-rview-band",children:Array.from({length:4},(e,t)=>V("div",{className:"local-evidencehub-rview-tile local-evidencehub-rview-sk",children:[k("span",{className:"local-evidencehub-rview-skb local-evidencehub-rview-skb--w60"}),k("span",{className:"local-evidencehub-rview-skb local-evidencehub-rview-skb--big"}),k("span",{className:"local-evidencehub-rview-skb local-evidencehub-rview-skb--w80"})]},t))}),k("div",{className:"local-evidencehub-rview-dist",children:[0,1].map(e=>V("div",{className:"local-evidencehub-rview-panel local-evidencehub-rview-sk",children:[k("span",{className:"local-evidencehub-rview-skb local-evidencehub-rview-skb--w40"}),k("span",{className:"local-evidencehub-rview-skb"}),k("span",{className:"local-evidencehub-rview-skb"}),k("span",{className:"local-evidencehub-rview-skb"})]},e))})]}),qt=e=>{let{labels:t,summary:n,loading:r,error:l,canfilter:s,chosenstatus:i,onStatus:w,onCompetency:m,onRetry:d}=e,a;if(n===null&&l)a=V("p",{className:"local-evidencehub-rview-error",children:[t.loaderror," ",k("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:d,children:t.retry})]});else if(n===null)a=V(nr,{children:[k(tr,{}),k("span",{className:"visually-hidden",role:"status",children:t.loadband})]});else{let f=n.rate??null,x=n.distribution.filter(p=>p.count>0||String(p.status)===i),I=Math.max(1,...x.map(p=>p.count)),v=Math.max(1,...n.worst.map(p=>p.count));a=V("div",{className:"local-evidencehub-rview-dim",children:[l?V("p",{className:"local-evidencehub-rview-error",children:[t.loaderror," ",k("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:d,children:t.retry})]}):null,V("div",{className:"local-evidencehub-rview-band",children:[k($e,{label:t.workers,value:R(n.workers),sub:n.hidden>0?P(t.hiddenworkers,R(n.hidden)):t.workerssub}),k($e,{label:t.ready,value:f===null?er:R(n.ready),sub:f===null?t.readynone:P(t.readysub,R(Math.round(f*100)))}),k($e,{label:t.attentiontile,value:R(n.attention),sub:P(t.attentionsub,R(n.pairs))}),k($e,{label:t.expiring,value:R(n.expiring),sub:t.expiringsub})]}),V("div",{className:"local-evidencehub-rview-dist",children:[V("div",{className:"local-evidencehub-rview-panel",children:[k("div",{className:"local-evidencehub-rview-panel-title",children:k("span",{children:t.bystatus})}),k("ul",{className:"local-evidencehub-rview-hlist",children:x.map(p=>k(Gt,{label:p.label,share:p.count/I,figure:R(p.count),title:t.showpairs,disabled:!s,onPress:()=>w(p.status)},p.status))})]}),V("div",{className:"local-evidencehub-rview-panel",children:[k("div",{className:"local-evidencehub-rview-panel-title",children:k("span",{children:t.worst})}),n.worst.length>0?k("ul",{className:"local-evidencehub-rview-hlist",children:n.worst.map(p=>k(Gt,{label:p.name,share:p.count/v,figure:R(p.count),title:t.showpairs,disabled:!s,onPress:()=>m(p.competencyid)},p.competencyid))}):k("p",{className:"local-evidencehub-rview-note",children:t.noworst})]})]})]})}return V("section",{className:"local-evidencehub-rview-region local-evidencehub-rview-bandregion","aria-label":t.bandlabel,"aria-busy":r,"data-region":"local-evidencehub-band",children:[r&&n!==null?k("div",{className:"local-evidencehub-rview-progress"}):null,a]})};import{useEffect as qe,useId as vr,useRef as Se,useState as nn}from"react";import{useId as or,useState as Yt}from"react";import{jsx as Qt}from"react/jsx-runtime";var rr={filter:"M2 3h12l-4.5 5.5V13l-3 1.5V8.5L2 3z",columns:"M2 3h12v10H2zM6.5 3v10M10 3v10",table:"M2 3h12v10H2zM2 6.5h12M2 10h12M6 6.5V13",cards:"M2 3h5v4H2zM9 3h5v4H9zM2 9h5v4H2zM9 9h5v4H9z",auto:"M2 3h7v10H2zM11 3h3v4h-3zM11 9h3v4h-3z",download:"M8 2v8M4.5 7 8 10.5 11.5 7M3 13h10",search:"M7 12A5 5 0 1 0 7 2a5 5 0 0 0 0 10zM10.5 10.5 14 14",close:"M4 4l8 8M12 4l-8 8",eraser:"M9.5 2 14 6.5 7.5 13 3 8.5zM5.6 5.9l4.5 4.5M7.5 13H14",tick:"M3 8.5 6.5 12 13 4.5",chevron:"M4 6l4 4 4-4",reset:"M3 8a5 5 0 1 0 1.5-3.5M3 2.5V5h2.5",queue:"M3 4h10M3 8h10M3 12h6",matrix:"M2.5 2.5h3v3h-3zM6.5 2.5h3v3h-3zM10.5 2.5h3v3h-3zM2.5 6.5h3v3h-3zM6.5 6.5h3v3h-3zM10.5 6.5h3v3h-3zM2.5 10.5h3v3h-3zM6.5 10.5h3v3h-3zM10.5 10.5h3v3h-3z",lock:"M4.5 7.5h7v6h-7zM6 7.5V5.5a2 2 0 0 1 4 0v2"},M=({name:e,className:t})=>Qt("svg",{className:"local-evidencehub-rview-icon"+(t?" "+t:""),viewBox:"0 0 16 16",width:"16",height:"16",fill:"none",stroke:"currentColor",strokeWidth:"1.5",strokeLinecap:"round",strokeLinejoin:"round","aria-hidden":"true",focusable:"false",children:Qt("path",{d:rr[e]??""})});import{Fragment as dr,jsx as F,jsxs as K}from"react/jsx-runtime";var ar=8,ct=50,lr=(e,t)=>t===null?e.show:P(e.showcount,R(t)),Xt=({option:e})=>e.count>=0?F("small",{className:"local-evidencehub-rview-optcount",children:R(e.count)}):null,ir=(e,t)=>e.options.filter(n=>n.count!==0||n.value===t),sr=({labels:e,facet:t,shown:n,value:r,headid:l,onChange:s})=>{let[i,w]=Yt(""),[m,d]=Yt(""),a=i.trim().toLocaleLowerCase(),f=t.groupings.length>1?t.groupings:[],x=f.find(y=>String(y.id)===m)?.groups.map(String)??null,I=n.filter(y=>y.value===r),v=n.filter(y=>y.value!==r&&(a===""||y.label.toLocaleLowerCase().includes(a))&&(x===null||x.includes(y.value))),p=P(t.find.label,R(t.options.length)),L=f.length>0?e.moregroupsgrouping:e.moregroups;return K(dr,{children:[K("div",{className:"local-evidencehub-rview-find",children:[F("input",{type:"search",className:"form-control form-control-sm local-evidencehub-rview-input",placeholder:p,"aria-label":p,value:i,onChange:y=>w(y.target.value)}),f.length>0?K("select",{className:"form-select form-select-sm local-evidencehub-rview-grouping","aria-label":e.grouping,value:m,onChange:y=>d(y.target.value),children:[F("option",{value:"",children:e.allgroupings}),f.map(y=>F("option",{value:String(y.id),children:y.name},y.id))]}):null]}),K("div",{className:"local-evidencehub-rview-optlist",role:"group","aria-labelledby":l,children:[[...I,...v.slice(0,ct)].map(y=>K("button",{type:"button",className:"local-evidencehub-rview-optrow","aria-pressed":y.value===r,onClick:()=>s(t.name,y.value===r?"":y.value),children:[F("span",{className:"local-evidencehub-rview-box","aria-hidden":"true",children:F(M,{name:"tick"})}),F("span",{className:"local-evidencehub-rview-optrow-label",children:y.label})," ",F(Xt,{option:y})]},y.value)),v.length>ct?F("p",{className:"local-evidencehub-rview-note",children:P(L,R(v.length-ct))}):null,I.length===0&&v.length===0?F("p",{className:"local-evidencehub-rview-note",children:t.find.nomatch}):null]})]})},cr=({labels:e,facet:t,value:n,onChange:r})=>{let l=or(),s=ir(t,n),i=[{value:"",label:e.any,count:-1},...s];if(t.options.length>ar)return K("div",{className:"local-evidencehub-rview-fgroup",children:[F("span",{className:"local-evidencehub-rview-fpanel-head",id:l,children:t.title}),F(sr,{labels:e,facet:t,shown:s,value:n,headid:l,onChange:r})]});let w=m=>{let d=i.findIndex(f=>f.value===n),a=-1;m.key==="ArrowRight"||m.key==="ArrowDown"?a=(d+1)%i.length:m.key==="ArrowLeft"||m.key==="ArrowUp"?a=(d-1+i.length)%i.length:m.key==="Home"?a=0:m.key==="End"&&(a=i.length-1),!(a<0)&&(m.preventDefault(),r(t.name,i[a].value),m.currentTarget.querySelectorAll('[role="radio"]')[a]?.focus())};return K("div",{className:"local-evidencehub-rview-fgroup",children:[F("span",{className:"local-evidencehub-rview-fpanel-head",id:l,children:t.title}),F("div",{className:"local-evidencehub-rview-fpanel-opts",role:"radiogroup","aria-labelledby":l,onKeyDown:w,children:i.map(m=>K("button",{type:"button",className:"local-evidencehub-rview-fpanel-opt",role:"radio","aria-checked":m.value===n,tabIndex:m.value===n?0:-1,onClick:()=>r(t.name,m.value),children:[m.label," ",F(Xt,{option:m})]},m.value))})]})},Zt=({labels:e,id:t,hidden:n,facets:r,values:l,total:s,note:i,onChange:w,onClear:m,onShow:d})=>K("div",{className:"local-evidencehub-rview-fpanel",id:t,role:"dialog","aria-label":e.filters,hidden:n,children:[K("div",{className:"local-evidencehub-rview-fpanel-body",children:[r.map(a=>F(cr,{labels:e,facet:a,value:l[a.name]??"",onChange:w},a.name)),i??null]}),K("div",{className:"local-evidencehub-rview-fpanel-foot",children:[F("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:m,children:e.clearfilters}),F("button",{type:"button",className:"btn btn-sm btn-primary",onClick:d,children:lr(e,s)})]})]});import{Fragment as ur,jsx as xe,jsxs as jt}from"react/jsx-runtime";var Ue=e=>{if(!["ArrowDown","ArrowUp","Home","End"].includes(e.key))return;let t=Array.from(e.currentTarget.querySelectorAll('[role^="menuitem"]'));if(t.length===0)return;e.preventDefault();let n=t.indexOf(document.activeElement),r=0;e.key==="End"?r=t.length-1:e.key==="ArrowDown"?r=(n+1)%t.length:e.key==="ArrowUp"&&(r=(n-1+t.length)%t.length),t[r].focus()},Jt=({heading:e,sets:t,onSet:n})=>t.length===0?null:jt(ur,{children:[xe("div",{className:"local-evidencehub-rview-menu-head",role:"presentation",children:e}),t.map(r=>jt("button",{type:"button",className:"local-evidencehub-rview-menu-item",role:"menuitemcheckbox","aria-checked":r.active,tabIndex:-1,"data-preset":r.name,onClick:()=>{r.active||n(r.name)},children:[xe("span",{className:"local-evidencehub-rview-box","aria-hidden":"true",children:xe(M,{name:"tick"})}),xe("span",{className:"local-evidencehub-rview-menu-label",children:r.label})]},r.name)),xe("div",{className:"local-evidencehub-rview-menu-sep",role:"separator"})]});import{Fragment as mr,jsx as en,jsxs as tn}from"react/jsx-runtime";var Ge=({label:e,value:t,options:n,small:r,onChange:l})=>{let s=i=>{let w=n.findIndex(a=>a.value===t),m=-1;if(i.key==="ArrowRight"||i.key==="ArrowDown"?m=(w+1)%n.length:i.key==="ArrowLeft"||i.key==="ArrowUp"?m=(w-1+n.length)%n.length:i.key==="Home"?m=0:i.key==="End"&&(m=n.length-1),m<0)return;i.preventDefault(),l(n[m].value),i.currentTarget.querySelectorAll('[role="radio"]')[m]?.focus()};return en("div",{className:"local-evidencehub-rview-seg"+(r?" local-evidencehub-rview-seg--small":""),role:"radiogroup","aria-label":e,onKeyDown:s,children:n.map(i=>tn("button",{type:"button",className:"local-evidencehub-rview-seg-cell",role:"radio","aria-checked":i.value===t,tabIndex:i.value===t?0:-1,onClick:()=>l(i.value),children:[i.label,i.count!==void 0?tn(mr,{children:[" ",en("small",{className:"local-evidencehub-rview-seg-count",children:i.count})]}):null]},i.value))})};import{Fragment as on,jsx as h,jsxs as E}from"react/jsx-runtime";var br=({links:e,tableurl:t,label:n})=>t===""&&e.length===0?null:E(on,{children:[h("div",{className:"local-evidencehub-rview-menu-sep",role:"separator"}),e.map(r=>E("a",{className:"local-evidencehub-rview-menu-item",role:"menuitem",tabIndex:-1,href:r.href,children:[h(M,{name:r.icon}),h("span",{className:"local-evidencehub-rview-menu-label",children:r.label})]},r.key)),t!==""?E("a",{className:"local-evidencehub-rview-menu-item",role:"menuitem",tabIndex:-1,href:t,children:[h(M,{name:"table"}),h("span",{className:"local-evidencehub-rview-menu-label",children:n})]}):null]}),rn=e=>{let{labels:t,chips:n,columns:r}=e,l=vr(),s=Se(null),i=Se(null),w=Se(null),m=Se(!1),[d,a]=nn(null),[f,x]=nn(e.search!==""),I=Se({});qe(()=>{if(d===null)return;let c=D=>{s.current&&!s.current.contains(D.target)&&a(null)};return document.addEventListener("mousedown",c),()=>document.removeEventListener("mousedown",c)},[d]),qe(()=>{if(d===null||d==="filters")return;let c=s.current?.querySelector(`#${CSS.escape(l+d)}`);(c?.querySelector('[aria-checked="true"]:not([aria-disabled="true"])')??c?.querySelector('[role^="menuitem"]:not([aria-disabled="true"])'))?.focus()},[d,l]),qe(()=>{f?i.current?.focus():m.current&&(m.current=!1,w.current?.focus())},[f]),qe(()=>{let c=D=>{if(D.key!=="/"||D.ctrlKey||D.metaKey||D.altKey||D.defaultPrevented)return;let A=document.activeElement;if(A&&(A.isContentEditable||A.closest("input, textarea, select")))return;let Q=s.current?.getBoundingClientRect();!Q||Q.width===0||Q.bottom<=0||Q.top>=window.innerHeight||(D.preventDefault(),f?i.current?.focus():(a(null),x(!0)))};return document.addEventListener("keydown",c),()=>document.removeEventListener("keydown",c)},[f]);let v=c=>a(d===c?null:c),p=c=>{m.current=c,x(!1)},L=c=>{if(!(c.key!=="Escape"||c.defaultPrevented))if(d!==null){c.preventDefault();let D=I.current[d];a(null),D?.focus()}else f&&(c.preventDefault(),p(!0))},y=e.search.trim().length>=e.searchmin,ae=y&&!f,U=n.length+(ae?1:0),q=e.total===null?"\u2026":We(t,"count",e.total,R(e.total)),ve=e.total!==null&&e.narrow?E(on,{children:[h("span",{"aria-hidden":"true",children:R(e.total)}),h("span",{className:"visually-hidden",children:q})]}):q,G=(c,D,A,Q)=>E("button",{type:"button",className:"local-evidencehub-rview-iconbtn","aria-label":A,"data-local-evidencehub-tip":A,"aria-haspopup":c==="filters"?"dialog":"menu","aria-expanded":d===c,"aria-controls":l+c,ref:De=>{I.current[c??""]=De},onClick:()=>v(c),children:[h(M,{name:D}),Q]}),te={auto:"auto",table:"table",cards:"cards"}[e.view],ne=h(M,{name:"chevron",className:"local-evidencehub-rview-chevron"}),u=n.length>0?P(t.filterson,R(n.length)):t.filters,b=n.length>0?h("span",{className:"local-evidencehub-rview-badge","aria-hidden":"true",children:R(n.length)}):null,T=[["auto",t.viewauto,t.viewautohint],["table",t.table,""],["cards",t.cards,""]].filter(([c])=>e.views.includes(c)),H="local-evidencehub-rview-bar"+(e.narrow?" local-evidencehub-rview-bar--narrow":"")+(f?" local-evidencehub-rview-bar--searching":"");return E("div",{className:H,"data-region":"local-evidencehub-bar",ref:s,onKeyDown:L,children:[h(Ge,{label:t.scope,value:e.scope,options:e.scopes,onChange:e.onScope}),h("span",{className:"local-evidencehub-rview-bar-spacer"}),E("div",{className:"local-evidencehub-rview-bar-controls",children:[E("div",{className:"local-evidencehub-rview-bar-cluster",children:[h("span",{className:"local-evidencehub-rview-bar-count","data-region":"local-evidencehub-total","aria-live":"polite",children:ve}),E("div",{className:"local-evidencehub-rview-capsule",children:[G("filters","filter",u,b),E("div",{className:"local-evidencehub-rview-anchor",children:[G("columns","columns",t.columns,ne),E("div",{className:"local-evidencehub-rview-menu",id:l+"columns",role:"menu","aria-label":t.columns,hidden:d!=="columns",onKeyDown:Ue,children:[h(Jt,{heading:t.presets,sets:e.sets,onSet:e.onSet}),r.map(c=>c.fixed?E("div",{className:"local-evidencehub-rview-menu-item",role:"menuitemcheckbox","aria-checked":"true","aria-disabled":"true",tabIndex:-1,children:[h("span",{className:"local-evidencehub-rview-box","aria-hidden":"true",children:h(M,{name:"tick"})}),h("span",{className:"local-evidencehub-rview-menu-label",children:c.title}),h("span",{className:"local-evidencehub-rview-menu-hint",children:t.always})]},c.key):E("button",{type:"button",className:"local-evidencehub-rview-menu-item",role:"menuitemcheckbox","aria-checked":c.active,tabIndex:-1,onClick:()=>e.onColumn(c.key,!c.active),children:[h("span",{className:"local-evidencehub-rview-box","aria-hidden":"true",children:h(M,{name:"tick"})}),h("span",{className:"local-evidencehub-rview-menu-label",children:c.title})]},c.key)),h("div",{className:"local-evidencehub-rview-menu-sep",role:"separator"}),E("button",{type:"button",className:"local-evidencehub-rview-menu-item",role:"menuitem",tabIndex:-1,"aria-disabled":e.usedefault,onClick:()=>{e.usedefault||e.onDefaultColumns()},children:[h(M,{name:"columns"}),h("span",{className:"local-evidencehub-rview-menu-label",children:t.defaultcolumns})]}),E("button",{type:"button",className:"local-evidencehub-rview-menu-item",role:"menuitem",tabIndex:-1,"aria-disabled":!e.haswidths,onClick:()=>{e.haswidths&&e.onResetWidths()},children:[h(M,{name:"reset"}),h("span",{className:"local-evidencehub-rview-menu-label",children:t.resetwidths})]})]})]}),E("div",{className:"local-evidencehub-rview-anchor",children:[G("view",te,t.view,ne),E("div",{className:"local-evidencehub-rview-menu",id:l+"view",role:"menu","aria-label":t.view,hidden:d!=="view",onKeyDown:Ue,children:[T.map(([c,D,A])=>E("button",{type:"button",className:"local-evidencehub-rview-menu-item",role:"menuitemradio","aria-checked":e.view===c,tabIndex:-1,onClick:()=>{e.onView(c),a(null),I.current.view?.focus()},children:[h(M,{name:c}),h("span",{className:"local-evidencehub-rview-menu-label",children:D}),A!==""?h("span",{className:"local-evidencehub-rview-menu-hint",children:A}):null,h(M,{name:"tick",className:"local-evidencehub-rview-tick"})]},c)),h(br,{links:e.viewlinks??[],tableurl:e.tableurl,label:t.coretable})]})]}),e.downloads.length>0?E("div",{className:"local-evidencehub-rview-anchor",children:[G("download","download",t.download,ne),h("div",{className:"local-evidencehub-rview-menu",id:l+"download",role:"menu","aria-label":t.download,hidden:d!=="download",onKeyDown:Ue,children:e.downloads.map(c=>E("a",{className:"local-evidencehub-rview-menu-item",role:"menuitem",tabIndex:-1,href:c.href,children:[h(M,{name:"download"}),h("span",{className:"local-evidencehub-rview-menu-label",children:c.label})]},c.key))})]}):null]})]}),h("div",{className:"local-evidencehub-rview-capsule local-evidencehub-rview-searchcap",children:f?E("span",{className:"local-evidencehub-rview-search",children:[e.searching&&y?h("span",{className:"local-evidencehub-rview-spin","aria-hidden":"true"}):h(M,{name:"search"}),h("input",{ref:i,type:"search","aria-label":t.search,placeholder:t.search,autoComplete:"off",value:e.search,onChange:c=>e.onSearch(c.target.value),onKeyDown:c=>{c.key==="Escape"&&(c.preventDefault(),p(!0))},onBlur:()=>{e.search===""&&p(!1)}}),e.search!==""?h("button",{type:"button",className:"local-evidencehub-rview-eraser","aria-label":t.cleartext,"data-local-evidencehub-tip":t.cleartext,onMouseDown:c=>c.preventDefault(),onClick:()=>{e.onSearch(""),i.current?.focus()},children:h(M,{name:"eraser"})}):null]}):h("button",{ref:w,type:"button",className:"local-evidencehub-rview-iconbtn","aria-label":t.search,"data-local-evidencehub-tip":t.search,onClick:()=>{a(null),x(!0)},children:h(M,{name:"search"})})}),h(Zt,{labels:t,id:l+"filters",hidden:d!=="filters",facets:e.facets,values:e.values,total:e.total,note:e.panelnote,onChange:e.onFacet,onClear:e.onClearFilters,onShow:()=>{a(null),I.current.filters?.focus()}})]}),E("div",{className:"local-evidencehub-rview-chips","data-region":"local-evidencehub-chips",hidden:U===0&&!e.aside,children:[n.map(c=>E("button",{type:"button",className:"local-evidencehub-rview-chip","aria-label":P(t.removefilter,c.text),onClick:()=>e.onRemoveChip(c.key),children:[h("span",{children:c.text}),h(M,{name:"close"})]},c.key)),ae?E("button",{type:"button",className:"local-evidencehub-rview-chip","aria-label":P(t.removefilter,P(t.searchchip,e.search.trim())),onClick:()=>e.onSearch(""),children:[h("span",{children:P(t.searchchip,e.search.trim())}),h(M,{name:"close"})]}):null,U>1?h("button",{type:"button",className:"local-evidencehub-rview-clearall",onClick:()=>{e.onClearAll(),x(!1)},children:t.clearall}):null,e.aside??null]})]})};var dt="validity:status",Qe="competency:competency",an="user:cohort",ut=e=>e.facet!==""&&e.options.length>0,ln=e=>{try{let t=JSON.parse(e.values||"{}");return t!==null&&typeof t=="object"?t:{}}catch{return{}}},hr=e=>e.reduce((t,n)=>({...t,...ln(n)}),{}),Ee=e=>{if(!e.applied||e.facet==="")return"";let t=ln(e),n=t[`${e.name}_value`];return n==null||Number(t[`${e.name}_operator`]??0)!==1?"":String(n)},mt=(e,t)=>{let n=hr(e);for(let r of Object.keys(n))r.startsWith(`${t.name}_`)&&delete n[r];return n},vt=(e,t,n)=>{let r=mt(e,t);return r[`${t.name}_operator`]=n===""?0:1,r[`${t.name}_value`]=n,r},sn=e=>{let t=Ee(e);return t===""?"":e.options.find(n=>n.value===t)?.label??""};import{Fragment as Nr,jsx as Me,jsxs as bt}from"react/jsx-runtime";var pr=["auto","table","cards"],wr=(e,t)=>{switch(t){case"validity":return e.presetvalidity;case"evidence":return e.presetevidence;default:return t}},fr=(e,t)=>{let n={label:e.searchoptions,nomatch:e.nooptionmatch};return t.name===an?n={label:e.searchcohorts,nomatch:e.nocohortmatch}:t.name===Qe&&(n={label:e.searchcompetencies,nomatch:e.nocompetencymatch}),{name:t.name,title:t.title,options:t.options,groupings:[],find:n}},gr=(e,t)=>{let n=sn(t);return{key:t.name,text:n===""?t.title:Re(e.filterchip,{title:t.title,value:n})}},yr=(e,t)=>t==="all"?`${e}&scope=all`:e,kr=(e,t,n,r)=>{let l=new URLSearchParams({scope:e});return t!==""&&l.set("search",t),n!==""&&(l.set("sort",n),l.set("dir",r==="desc"?"desc":"asc")),l.toString()},Rr=({labels:e,suspended:t,onAccounts:n})=>bt("div",{className:"local-evidencehub-rview-fgroup","data-region":"local-evidencehub-accounts",children:[Me("span",{className:"local-evidencehub-rview-fpanel-head",children:e.accounts}),Me(Ge,{label:e.accounts,small:!0,value:t?"all":"active",options:[{value:"active",label:e.activeonly},{value:"all",label:e.includesuspended}],onChange:r=>{r==="all"!==t&&n(r==="all")}})]}),cn=e=>{let{labels:t,filters:n,summary:r}=e,l=n.filter(ut),s=n.filter(a=>!ut(a)),i=a=>n.find(f=>f.name===a),w=kr(e.scope,e.appliedsearch,e.sort,e.direction),m=e.downloadable&&e.downloadurl!==""?e.formats.map(a=>({key:a.value,label:a.label,href:`${e.downloadurl}&${w}&download=${encodeURIComponent(a.value)}`})):[],d=e.presets.map(a=>({name:a.name,label:wr(t,a.name),active:a.active}));return Me(rn,{labels:t,narrow:e.narrow,scopes:[{value:"attention",label:t.attention,count:r===null?void 0:R(r.attention)},{value:"all",label:t.all,count:r===null?void 0:R(r.pairs)}],scope:e.scope,total:e.total,facets:l.map(a=>fr(t,a)),values:Object.fromEntries(l.map(a=>[a.name,Ee(a)])),panelnote:bt(Nr,{children:[Me(Rr,{labels:t,suspended:e.suspended,onAccounts:e.onAccounts}),s.length>0?bt("p",{className:"local-evidencehub-rview-note",children:[t.morefiltershelp," ",Me("a",{href:e.tableurl,children:t.morefilters})]}):null]}),chips:n.filter(a=>a.applied).map(a=>gr(t,a)),columns:e.columns,sets:d,usedefault:e.usedefault,haswidths:e.haswidths,view:e.view,views:pr,tableurl:e.tableurl,viewlinks:e.matrixurl===""?[]:[{key:"matrix",label:t.matrix,href:yr(e.matrixurl,e.scope),icon:"matrix"}],downloads:m,search:e.search,searchmin:e.searchmin,searching:e.searching,onScope:a=>e.onScope(a==="all"?"all":"attention"),onSearch:e.onSearch,onFacet:(a,f)=>{let x=i(a);x&&e.onFilter(x,f)},onRemoveChip:a=>{let f=i(a);f&&e.onRemoveFilter(f)},onClearFilters:e.onClearFilters,onClearAll:e.onClearAll,onColumn:e.onColumn,onSet:a=>{let f=e.presets.find(x=>x.name===a);f&&e.onPreset(f)},onDefaultColumns:e.onDefaultColumns,onResetWidths:e.onResetWidths,onView:e.onView})};import{Fragment as Cr,jsx as dn}from"react/jsx-runtime";var Ye=({parts:e})=>dn(Cr,{children:e.map((t,n)=>dn("span",{className:n===0?"local-evidencehub-rview-c":"local-evidencehub-rview-sub",title:Ne(t),dangerouslySetInnerHTML:{__html:t}},n))});import{Fragment as Vr,jsx as ie,jsxs as Hr}from"react/jsx-runtime";var xr={worker:260,competency:240,status:180,expiry:132,count:104,type:200,issuer:200,competencyidnumber:150,framework:200,issuedate:132,expirydate:132,verification:160,changed:160},Sr=180,Er={worker:200,expiry:104,issuedate:104,expirydate:104,changed:104},Mr=80,Pr=800,Xe=_t(xr,Sr,Er,Mr,Pr),un=-1,Lr=0,Tr=1,Ir=2,Dr=3,Fr=4,Ar=5,_r=e=>{switch(e){case Ir:return"success";case Dr:return"info";case Fr:return"warning";case Ar:return"danger";case Tr:case Lr:case un:default:return"neutral"}},z=(e,t)=>e.cells.find(n=>n.key===t)?.html??"",ht=({row:e})=>{let t=z(e,"status");return t===""?null:Hr("span",{className:`local-evidencehub-rview-status local-evidencehub-rview-status--${_r(e.status)}`,title:Ne(t),children:[e.status===un?ie(M,{name:"lock"}):null,ie("span",{dangerouslySetInnerHTML:{__html:t}})]})},mn=({row:e,column:t})=>t==="status"?ie(ht,{row:e}):ie(Ye,{parts:[z(e,t)]}),Ze=({row:e,label:t,onInspect:n})=>e.inspect?ie("button",{type:"button",className:"btn btn-sm btn-outline-secondary local-evidencehub-rview-inspect","aria-label":e.inspectlabel,"data-userid":e.userid,"data-competencyid":e.competencyid,"data-title":e.drawertitle,onClick:r=>n(r.currentTarget),children:t}):null,je=({row:e,identity:t})=>ie(Vr,{children:t.map(n=>{let r=z(e,n.key);return r===""?null:ie("span",{className:"local-evidencehub-rview-sub",title:Ne(r),dangerouslySetInnerHTML:{__html:r}},n.key)})});import{Fragment as bn,jsx as $,jsxs as Pe}from"react/jsx-runtime";var Or=["worker","competency","status","expiry","type"],vn=({row:e,column:t})=>Pe("div",{className:"local-evidencehub-rview-card-line",children:[$("span",{className:"local-evidencehub-rview-card-key",children:t.title}),$("span",{className:"local-evidencehub-rview-inline",dangerouslySetInnerHTML:{__html:z(e,t.key)}})]}),Br=(e,t,n,r,l)=>{let s=d=>t.find(a=>a.key===d),i=s("expiry"),w=s("type"),m=t.filter(d=>!Or.includes(d.key)&&z(r,d.key).trim()!=="");return{head:Pe(bn,{children:[Pe("div",{className:"local-evidencehub-rview-card-who",children:[$("span",{className:"local-evidencehub-rview-card-name",dangerouslySetInnerHTML:{__html:z(r,"worker")}}),$(je,{row:r,identity:n})]}),$(Ze,{row:r,label:e.inspect,onInspect:l})]}),lines:Pe(bn,{children:[Pe("div",{className:"local-evidencehub-rview-card-line",children:[$("span",{className:"local-evidencehub-rview-inline",dangerouslySetInnerHTML:{__html:z(r,"competency")}}),s("status")?$(ht,{row:r}):null]}),i?$(vn,{row:r,column:i}):null,w?$(vn,{row:r,column:w}):null]}),fields:m.map(d=>({key:d.key,title:d.title,content:$(Ye,{parts:[z(r,d.key)]})}))}},hn=({labels:e,columns:t,identity:n,rows:r,single:l,selection:s,onInspect:i})=>$(Ot,{labels:e,rows:r,single:l,selection:s,renderCard:w=>Br(e,t,n,w,i)});import{jsx as pn,jsxs as pt}from"react/jsx-runtime";var wn=({labels:e,count:t,workers:n,posting:r,onExport:l})=>pt("div",{className:"local-evidencehub-rview-dock",role:"region","aria-label":e.dock,"data-region":"local-evidencehub-dock",children:[pt("span",{className:"local-evidencehub-rview-dockcount","aria-live":"polite",children:[P(e.selected,R(t)),pt("span",{className:"local-evidencehub-rview-dockworkers",children:[" \xB7 ",We(e,"selectedworkers",n,R(n))]})]}),pn("div",{className:"local-evidencehub-rview-dockactions",children:pn("button",{type:"button",className:"btn btn-primary",disabled:r,onClick:l,children:e.exportdossiers})})]});var Le=(e,t,n)=>{let r=document.createElement("input");r.type="hidden",r.name=t,r.value=n,e.appendChild(r)},fn=e=>{let t=document.createElement("form");t.method="post",t.action=e.action,t.hidden=!0,Le(t,"contextid",String(e.contextid)),Le(t,"action","exportdossiers"),Le(t,"sesskey",e.sesskey),Le(t,"templateid",String(e.templateid)),e.pairs.forEach(n=>Le(t,"pairs[]",`${n.userid}:${n.competencyid}`)),document.body.appendChild(t),t.submit(),t.remove()};import{jsx as Te,jsxs as gn}from"react/jsx-runtime";var yn=({labels:e,columns:t,identity:n,rows:r,widths:l,sort:s,direction:i,selection:w,onInspect:m,onSort:d,onWidth:a,onWidthCommit:f})=>Te(Ht,{labels:e,columns:t,lead:"worker",rows:r,widths:l,sizing:Xe,sort:s,direction:i,selection:w,renderLead:x=>gn("div",{className:"local-evidencehub-rview-who",children:[gn("div",{className:"local-evidencehub-rview-who-text",children:[Te("span",{className:"local-evidencehub-rview-c",dangerouslySetInnerHTML:{__html:z(x,"worker")}}),Te(je,{row:x,identity:n})]}),Te(Ze,{row:x,label:e.inspect,onInspect:m})]}),renderCell:(x,I)=>Te(mn,{row:x,column:I.key}),onSort:d,onWidth:a,onWidthCommit:f});import{fetchOne as Ie}from"@moodle/lms/core/ajax";var kn=(e,t,n)=>Ie({methodname:"local_evidencehub_report_rows",args:{...e,...t,facets:n}}),Rn=(e,t,n)=>Ie({methodname:"local_evidencehub_report_summary",args:{...e,scope:t,search:n}}),wt=(e,t,n)=>Ie({methodname:"local_evidencehub_set_report_preference",args:{...e,usedefault:t.usedefault,columns:t.columns,widths:t.widths,suspended:n===null?-1:Number(n)}}),Je=(e,t)=>Ie({methodname:"core_reportbuilder_set_filters",args:{reportid:e,parameters:"{}",values:JSON.stringify(t)}}),ft=e=>Ie({methodname:"core_reportbuilder_filters_reset",args:{reportid:e,parameters:"{}"}});import{jsx as se,jsxs as Qr}from"react/jsx-runtime";var Wr=300,Kr=500,zr=3e3,Cn="local_evidencehub_hub_view",$r=["worker","competency","status","expiry","count","type","issuer"],yt=e=>Object.entries(e).map(([t,n])=>({key:t,width:n})),gt=(e,t,n)=>{let r=yt(n);return e&&r.length===0?{usedefault:!0,columns:[],widths:[]}:{usedefault:!1,columns:t,widths:r}},Ur=e=>e===null?{allcolumns:[],usedefault:!0,suspended:!1,candecide:!1}:{allcolumns:e.columns,usedefault:e.preference.usedefault,suspended:e.preference.suspended,candecide:e.candecide},Gr=(e,t)=>{let n=t.find(r=>r.name===dt&&r.applied);return{sort:e?.sort??"",direction:e?.direction??"",downloadable:e!==null&&e.total>0,chosenstatus:n?Ee(n):""}},qr=e=>{let{labels:t,reportid:n}=e,r=Nn(()=>({contextid:e.contextid,templateid:e.templateid}),[e.contextid,e.templateid]),l=_(null),s=It(l),[i,w]=O(()=>Dt(Cn)),m={scope:"attention",search:"",page:0,perpage:e.perpage,sort:"",direction:"asc"},d=_(m),[a,f]=O(m),[x,I]=O(""),[v,p]=O(null),[L,y]=O(!0),[ae,U]=O(!1),[q,ve]=O(null),[G,te]=O(!0),[ne,u]=O(!1),[b,T]=O({}),[H,c]=O({}),[D,A]=O(()=>new Set),[Q,De]=O(!1),nt=_(new Map),rt=_(0),ot=_(0),be=_(null),he=_(null),le=_(null),Y=_(null),kt=_(!1),ce=_(null),Fe=_(null),at=_(null),Ae=_(()=>{}),_e=_(null),He=et((o,g)=>{let C=`${o}:${g}`,S=nt.current.get(C);return S===void 0&&(S=nt.current.size+1,nt.current.set(C,S)),S},[]),de=et((o,g)=>{let C=++rt.current;be.current||(be.current=J("local_evidencehub/hub:rows")),y(!0),kn(r,o,g).then(S=>{C===rt.current&&(S.counted&&c(Object.fromEntries(S.filters.map(Z=>[Z.name,Z]))),kt.current||(kt.current=!0,T(Object.fromEntries(S.preference.widths.map(Z=>[Z.key,Z.width])))),S.page!==d.current.page&&(d.current={...d.current,page:S.page},f(d.current)),p(S),A(Z=>{let Wn=new Set(S.rows.filter(ue=>ue.selectable).map(ue=>He(ue.userid,ue.competencyid))),Tt=Array.from(Z).filter(ue=>S.candecide&&Wn.has(ue));return Tt.length===Z.size?Z:new Set(Tt)}),U(!1),y(!1))}).catch(()=>{C===rt.current&&(U(!0),y(!1))})},[r,He]),pe=et(o=>{let g=++ot.current;he.current||(he.current=J("local_evidencehub/hub:band")),te(!0),Rn(r,o.scope,o.search).then(C=>{g===ot.current&&(ve(C),u(!1),te(!1))}).catch(()=>{g===ot.current&&(u(!0),te(!1))})},[r]),X=et((o,g)=>{d.current={...d.current,...o},f(d.current),A(new Set),de(d.current,g),g&&pe(d.current)},[de,pe]);tt(()=>{!L&&be.current&&(be.current(),be.current=null)},[L,v,ae]),tt(()=>{!G&&he.current&&(he.current(),he.current=null)},[G,q,ne]),tt(()=>{de(d.current,!0),pe(d.current)},[de,pe]),tt(()=>()=>{le.current!==null&&window.clearTimeout(le.current),_e.current!==null&&window.clearTimeout(_e.current),Y.current?.(),Ae.current()},[]);let Sn=o=>{w(o),Ft(Cn,o)},En=o=>{I(o),le.current!==null&&(window.clearTimeout(le.current),le.current=null);let g=o.trim(),C=g.length>=e.searchmin?g:"";if(C===d.current.search){Y.current?.(),Y.current=null;return}if(o===""){Y.current?.(),Y.current=null,X({search:"",page:0},!0);return}Y.current||(Y.current=J("local_evidencehub/hub:search")),le.current=window.setTimeout(()=>{le.current=null,X({search:C,page:0},!0),Y.current?.(),Y.current=null},Wr)},we=o=>{A(new Set);let g=J("local_evidencehub/hub:filters");o().then(()=>{X({page:0},!0)}).catch(()=>{U(!0)}).finally(g)},re=(v?.filters??[]).map(o=>H[o.name]?{...o,options:H[o.name].options}:o),Rt=(o,g)=>{let C=re.find(S=>S.name===o);C&&we(()=>Je(n,vt(re,C,g)))},{allcolumns:Ve,usedefault:Oe,suspended:Mn,candecide:Pn}=Ur(v),fe=Ve.filter(o=>!o.fixed&&o.active).map(o=>o.key),ge=(o,g)=>{let C=J("local_evidencehub/hub:preference");wt(r,o,null).then(()=>{g&&de(d.current,!1)}).catch(()=>{g&&U(!0)}).finally(C)},Ln=o=>{v&&p({...v,preference:{...v.preference,suspended:o}}),A(new Set);let g=J("local_evidencehub/hub:accounts");wt(r,gt(Oe,fe,b),o).then(()=>{X({page:0},!0)}).catch(()=>{U(!0)}).finally(g)},Tn=(o,g)=>{let C=g?[...fe,o]:fe.filter(S=>S!==o);v&&p({...v,columns:v.columns.map(S=>S.key===o?{...S,active:g}:S),preference:{...v.preference,usedefault:!1,columns:C}}),ge({usedefault:!1,columns:C,widths:yt(b)},!0)},Nt=()=>{ce.current!==null&&(window.clearTimeout(ce.current),ce.current=null);let o=at.current;at.current=null,o!==null&&ge(gt(Oe,fe,o),!1),Fe.current?.(),Fe.current=null};Ae.current=Nt;let In=(o,g,C=!1)=>{let S={...b};if(g===null?delete S[o]:S[o]=g,T(S),at.current=S,!C){Nt();return}ce.current!==null&&window.clearTimeout(ce.current),Fe.current||(Fe.current=J("local_evidencehub/hub:width")),ce.current=window.setTimeout(()=>Ae.current(),Kr)},Dn=o=>{let g=new Set(o.columns);v&&p({...v,columns:v.columns.map(C=>C.fixed?C:{...C,active:g.has(C.key)}),presets:v.presets.map(C=>({...C,active:C.name===o.name})),preference:{...v.preference,usedefault:!1,columns:o.columns}}),ge({usedefault:!1,columns:o.columns,widths:yt(b)},!0)},Fn=o=>{let C=(v?.sort??"")===o&&v?.direction==="asc"?"desc":"asc";X({sort:o,direction:C,page:0},!1)},Ct=o=>{let g=J("local_evidencehub/hub:inspect");Ut(o,e.contextid,e.templateid).finally(g)},ye=Nn(()=>(v?.rows??[]).map(o=>({...o,id:He(o.userid,o.competencyid)})),[v,He]),xt=Pn&&e.exportform!==null,lt=new Set(ye.filter(o=>!o.selectable).map(o=>o.id)),ke=ye.filter(o=>D.has(o.id)),An=(o,g)=>A(C=>{let S=new Set(C);return g&&!lt.has(o)?S.add(o):S.delete(o),S}),_n=o=>A(()=>o?new Set(ye.filter(g=>!lt.has(g.id)).map(g=>g.id)):new Set),St=xt?{selected:D,locked:lt,label:o=>P(t.selectrow,o.drawertitle),pagelabel:t.selectpage,onSelect:An,onSelectPage:_n}:void 0,Hn=()=>{e.exportform===null||ke.length===0||Q||(De(!0),_e.current=window.setTimeout(()=>{_e.current=null,De(!1)},zr),fn({...e.exportform,contextid:e.contextid,templateid:e.templateid,pairs:ke.map(o=>({userid:o.userid,competencyid:o.competencyid}))}))},Vn=re.some(o=>o.applied)||a.search!==""||a.scope!=="all",Et=Ve.filter(o=>o.key.startsWith("identity:")),Mt=Ve.filter(o=>o.key==="worker"||o.key==="competency"||!o.fixed&&o.active),Pt=i==="cards"||i==="auto"&&s,Lt=null;v!==null&&(Lt=Pt?se(hn,{labels:t,columns:Mt,identity:Et,rows:ye,single:s,selection:St,onInspect:Ct}):se(yn,{labels:t,columns:Mt,identity:Et,rows:ye,widths:b,sort:v.sort,direction:v.direction,selection:St,onInspect:Ct,onSort:Fn,onWidth:In,onWidthCommit:()=>Ae.current()}));let On=$r.map(o=>({key:o,title:o==="worker"?t.worker:"",sortable:!1,default:!0,active:!0,fixed:o==="worker"})),Bn=new Set(ke.map(o=>o.userid)).size,Be=Gr(v,re);return Qr("div",{className:"local-evidencehub-rview-root",ref:l,"data-region":"local-evidencehub-hubview",children:[se(qt,{labels:t,summary:q,loading:G,error:ne,canfilter:v!==null,chosenstatus:Be.chosenstatus,onStatus:o=>Rt(dt,String(o)),onCompetency:o=>Rt(Qe,String(o)),onRetry:()=>pe(d.current)}),se(cn,{labels:t,narrow:s,scope:a.scope,summary:q,total:v===null?null:v.total,filters:re,columns:Ve.filter(o=>!o.key.startsWith("identity:")),presets:v?.presets??[],usedefault:Oe,haswidths:Object.keys(b).length>0,suspended:Mn,view:i,search:x,searchmin:e.searchmin,searching:L,matrixurl:e.matrixurl,tableurl:e.tableurl,downloadurl:e.downloadurl,formats:e.formats,appliedsearch:a.search,sort:Be.sort,direction:Be.direction,downloadable:Be.downloadable,onScope:o=>X({scope:o,page:0},!0),onSearch:En,onFilter:(o,g)=>we(()=>Je(n,vt(re,o,g))),onRemoveFilter:o=>we(()=>Je(n,mt(re,o))),onClearFilters:()=>we(()=>ft(n)),onClearAll:()=>{I(""),d.current={...d.current,search:""},re.some(o=>o.applied)?we(()=>ft(n)):X({page:0},!0)},onAccounts:Ln,onColumn:Tn,onPreset:Dn,onDefaultColumns:()=>{T({}),v&&p({...v,preference:{...v.preference,usedefault:!0,columns:[],widths:[]}}),ge({usedefault:!0,columns:[],widths:[]},!0)},onResetWidths:()=>{T({}),ge(gt(Oe,fe,{}),!1)},onView:Sn}),se(Wt,{labels:t,loading:L,loaded:v!==null,error:ae,total:v?.total??0,filtered:Vn,cards:Pt,narrow:s,skeleton:{columns:On,lead:"worker",widths:b,sizing:Xe},dock:v!==null&&xt&&ke.length>0?se(wn,{labels:t,count:ke.length,workers:Bn,posting:Q,onExport:Hn}):null,onRetry:()=>de(d.current,!0),children:Lt}),v!==null&&v.total>0?se(At,{labels:t,page:a.page,perpage:a.perpage,perpages:e.perpages,total:v.total,shown:v.rows.length,narrow:s,onPage:o=>X({page:o},!1),onPerpage:o=>X({perpage:o,page:0},!1)}):null]})},xn=qr;import{jsx as Xr}from"react/jsx-runtime";var Yr=e=>e.mount==="hub"?Xr(xn,{...e}):null,Ka=Yr;export{Ka as default};
/**
 * The narrow step and the reader's view choice, shared by every view of the data report.
 *
 * The narrow step is read from an element's own width, never the window's: the view sits in a page
 * whose drawers and blocks take a width the window does not show (AR-D9; stylelint refuses a
 * container query, which would otherwise say this in CSS).
 *
 * @module     local_evidencehub/datareport/layout
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Words and numbers: filling the strings the props carry, and the wait as the report words it.
 *
 * There is no core/str for an ES module, so the strings arrive translated with their placeholders
 * ("{$a} applications"), and only the client knows the value.
 *
 * @module     local_evidencehub/datareport/str
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The foot of the rows: which rows these are, the pages and the page size (AR-D14: 30, 50 or 100).
 *
 * The server pages (one data query and one count whatever the size); the client never holds more
 * than one page. Narrow, the pages read "Page 2 of 9" between Previous and Next.
 *
 * @module     local_evidencehub/datareport/Pager
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * A synchronous hold on Moodle's pending-JavaScript counter, the one Behat waits on.
 *
 * core/ajax holds the counter only while a request is in flight; between its answer and the render
 * that draws it, and across a filter write followed by the reads it causes, nothing would be
 * pending and Behat would read the page as settled before the rows exist. M.util.js_pending, which
 * core/pending itself calls, is on every page and takes effect at once. block_compass's pending.ts
 * is the fleet's precedent.
 *
 * @module     local_evidencehub/datareport/pending
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Column widths: a view's policy built from its tables, and the width a column is drawn at.
 *
 * @module     local_evidencehub/datareport/sizing
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The bounded grid (section 5b of the study): a real table in a frame that scrolls both ways.
 *
 * table-layout fixed with a col per column, so the widths are exact and the browser does not measure
 * every cell; the heading row sticks to the top of the frame and the lead column (the view's first,
 * named by `lead`) to its left, with a shadow on its edge once the frame has scrolled sideways.
 * Sortable headings are buttons, the order in aria-sort. Each heading's right edge is a separator that
 * resizes its column: a drag changes the col element alone and is saved once, on release, in the
 * reader's preference (AR-D13); the arrow keys move it by 16 px, saved once too, after the last key or
 * when the handle loses the focus; a double click restores the default.
 *
 * What a cell holds is the view's: `renderLead` draws the lead column's cell, `renderCell` every other.
 * A view that selects rows passes a selection, whose boxes lead the lead column's heading and cells,
 * each in a label of its own (visually hidden text) so it is named the way a form control is; a view
 * without one draws none.
 *
 * Strings read from the view's labels: grid, caption, resize.
 *
 * @module     local_evidencehub/datareport/Grid
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
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
 */
/**
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
 */
/**
 * The one door from the hub's ES modules to the plugin's AMD drawer.
 *
 * A component cannot import an AMD module: the import map Moodle serves resolves `@moodle/lms/`, react and
 * react-dom, and nothing of RequireJS. The inspection drawer is an AMD core/modal subclass
 * (amd/src/drawer.js), so it is reached through core's own loader (`@moodle/lms/core/amd`), here only, and
 * never copied: the list and the matrix open the same drawer. Every module the view may load is a key of
 * `Modules`, so any other name is a type error under the tsc gate.
 *
 * @module     local_evidencehub/hub/amd
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The hub's band: the readiness of the listed workers (study, section 2.6, decision 7).
 *
 * Four tiles - the workers, with the workers hidden by permissions as the sub-line when there are any; the
 * ready workers with the rate; the pairs needing attention of all the pairs; the pairs expiring in 30 days -
 * and two panels: the pairs by status and the competencies with most open pairs. Each line of a panel is a
 * button that sets the report's status or competency filter, its number written as text beside its bar.
 * There is no compact band: every figure is already over the reader's own workers. A figure with no value
 * reads "–". A status counting no pair is left out of the distribution unless it is the status filter in force.
 *
 * Every number is the server's (reportengine::summary()), over the report's own rows under every criterion
 * but the scope and the status filter.
 *
 * @module     local_evidencehub/hub/Band
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The data report's icons, drawn in currentColor and always decorative.
 *
 * A component cannot render core's pix icons ({{#pix}} is Mustache), so the few glyphs the bar,
 * the menus and the chips need are inline SVG here, each beside a word or inside a named control.
 *
 * @module     local_evidencehub/datareport/icons
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The filter panel (the Design System's filter-panel.md and counter.md): one group per facet.
 *
 * The panel knows facets, not where their values live: it is handed the definitions, the value each is
 * set to ('' for none) and a callback that a view turns into its own write (the course report rewrites
 * core's stored filters; a queue keeps its filters in the page's address). Each option carries the rows
 * it would list under every other filter in force; an option no row would list is hidden unless chosen.
 * A facet with more than eight options is a search over a list capped at fifty, the chosen one pinned
 * on top, with a grouping select when the facet has groupings (section 5b).
 *
 * Strings read from the view's labels: any, filters, clearfilters, show, showcount, grouping,
 * allgroupings, moregroups, moregroupsgrouping.
 *
 * @module     local_evidencehub/datareport/FilterPanel
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The bar's menus (the Design System's menu.md): the keys that move between items, and the Columns
 * menu's sets.
 *
 * @module     local_evidencehub/datareport/menus
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * A segmented control (the Design System's segmented-control.md): one of two to four visible values.
 *
 * A radiogroup with one tab stop, the chosen cell; the arrow keys, Home and End move to another cell
 * and choose it. A value may carry a count, drawn quietly after its label (counter.md).
 *
 * @module     local_evidencehub/datareport/Segmented
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The filter bar above the rows (the Design System's filter-bar.md, chip.md, menu.md, filter-panel.md).
 *
 * The scope, the count, a capsule of icon buttons - Filters with its loud counter, Columns, View and,
 * where a view offers one, Download - and the search by name in a capsule of its own, folded to its
 * button until pressed or `/` is typed; the chips of what is in force on a row of their own, then
 * "Clear all". One layer is open at a time; Escape or a press outside closes it and gives the focus
 * back to its button. Each menu hangs under its own button, the panel under the row of controls (never
 * under the chips); at the narrow step (the view's container under 576 px) the menus hang from the
 * controls' end, the scope takes a line of its own after them, and the open search takes the line.
 *
 * The open search: Escape closes it whatever it holds, the text staying in force, and gives the focus
 * back to its button; leaving it empty closes it too; the eraser clears the text and keeps it open. The
 * text in force shows as a chip only while the field is closed, so it is never said twice.
 *
 * What differs between views arrives as props: the scope's values, the facets and their values with
 * the callback that writes a change where the view keeps its filters, the chips of the filters in
 * force, the column catalogue and its sets, the View choices and the link to the view's server table,
 * and the Download links (none: no Download button). Columns starts with the sets, each handed to the
 * view as the reader's choice, then lists the catalogue, a fixed column always shown, and ends with
 * the default columns and "Reset column widths". A view may put a link of its own at the end of the
 * chips' row (`aside`: the queue's "Open the report"), which then shows with no chip in force too.
 *
 * Strings read from the view's labels: scope, count (and count_one), filters, filterson, columns,
 * presets, always, defaultcolumns, resetwidths, view, viewauto, viewautohint, table, cards, coretable,
 * download, search, cleartext, removefilter, searchchip, clearall, and FilterPanel's.
 *
 * @module     local_evidencehub/datareport/Bar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The report's filters as core stores them, read and rewritten for the panel, the chips and the band.
 *
 * Core keeps every filter value of a report for a user in one store, keyed "<filter>_<field>", and
 * core_reportbuilder_set_filters REPLACES that whole set. So a change to one filter is written as every
 * stored value, with that filter's own keys changed, and a removal as every other filter's keys. Every
 * filter the panel draws is a choice of one option from a list (the rows answer names its facet
 * "choice"): core's select and the plugin's reader_select, cohort and validuntil, which all store
 * "<filter>_operator" and "<filter>_value" and read EQUAL_TO with one value. The worker's name, a text
 * filter, is set in core's table view and appears here as a chip that removes it.
 *
 * @module     local_evidencehub/hub/filters
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The hub's filter bar: the shared bar (datareport/Bar) with the hub's vocabulary.
 *
 * The scope is "Needs attention" (every status but compliant, the default) or "All", each with its count
 * from the summary (decision 3). Filters draws the report's facets, which live in core's store
 * (core_reportbuilder_set_filters, filters.ts): status, cohort, competency, type of evidence, verification
 * and "Valid until", each option with the pairs it would list under every other criterion in force, a facet
 * above eight options as a searched list. Under them, "Accounts" ("Active only" or "Include suspended"),
 * which is not a stored filter but the reader's preference (decision 5), and a note leading to core's table
 * view for the filter the panel does not draw (the worker's name). Columns starts with the two sets
 * (Validity, Deciding evidence), then the catalogue, the worker and the competency always shown. View is
 * Automatic, Table or Cards, then the matrix (in the same scope) and core's table view. Download is the capsule's last
 * button (D114): a menu of the site's enabled formats, each a link to index.php's download branch with the scope, the
 * search and the sort in force, so the file holds what the list shows; the report applies the stored filters and the
 * columns itself. It is offered only once rows have arrived and while there are any. No More menu: the dossiers are
 * the dock's (the selection variant).
 *
 * @module     local_evidencehub/hub/Bar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
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
 */
/**
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
 */
/**
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
 */
/**
 * The bulk dock under the hub's rows (the Design System's data-report.md, "Bulk dock", D133 to D135).
 *
 * The count of the rows selected on this page, then how many workers they name: a dossier is per worker,
 * so two pairs of one worker are one dossier (the plugin's addition to the mould's count). One action,
 * "Export dossiers", the page's only one, so a solid fill. It needs no more input, so it opens no dialog
 * (D135): it posts the form at once (export.ts). Drawn only while something is selected; the view keeps it
 * out otherwise.
 *
 * @module     local_evidencehub/hub/Dock
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The dossier export is a real form posted to index.php's own branch, never a web service.
 *
 * The form carries what that branch reads, under its own names: `contextid`, `action=exportdossiers`, `sesskey`,
 * the `templateid`, and one `pairs[]` per selected row, `<userid>:<competencyid>` (dossier_export::posted_workers()
 * reads the workers out of them). The server then requires the POST and the sesskey, `exportdossier` in the
 * context, the template offered there, at most the configured number of workers, and keeps only the posted workers
 * of the template's pair set this reader may view, suspended accounts as the reader's preference lists them
 * (dossier_export::selection()): what the client selected decides nothing on the server. A small export answers
 * with the ZIP, which the browser saves while the page stays; a larger one redirects back to the list with a
 * notification.
 *
 * @module     local_evidencehub/hub/export
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
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
 */
/**
 * The only module of the hub's view that talks to the server's web services.
 *
 * Every call goes through core's ES module @moodle/lms/core/ajax (a component cannot import an AMD
 * module), and every call is ONE request of its own (fetchOne), never a batch: core keeps one instance
 * of a report per user for the whole request, so a rows call batched after a filter or preference write
 * would read the report built before it (the preference writer never builds the report for that reason,
 * classes/external/set_report_preference.php). Core's ajax holds Behat's pending counter while each
 * request is in flight; the view holds it across the render that follows (datareport/pending.ts).
 *
 * The report is identified by its context and template (hub_pairs, the template being its item): core's
 * filter services rebuild it from its persistent, whose context and item it already holds, and take no
 * parameter of the hub's.
 *
 * @module     local_evidencehub/hub/repository
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
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
/**
 * The bundle's entry: every React view of the plugin, one chosen per mount by its props.
 *
 * Core's react_autoinit mounts the DEFAULT export of the module a mount names, and the fleet's bundle step
 * builds one entry into one file (js/esm/bundle.json), so every view of the plugin is reached through this
 * one component: each template names @moodle/lms/local_evidencehub/bundle, and its props name the view in
 * `mount`. Today there is one, the hub's list of pairs (hub_report_view, mounted by index.php); another view
 * is one more branch here and a template whose props name it, sharing the data report's parts.
 *
 * @module     local_evidencehub/views
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
//# sourceMappingURL=bundle.js.map
