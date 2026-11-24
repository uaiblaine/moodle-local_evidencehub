import{useCallback as je,useEffect as Je,useMemo as Nn,useRef as F,useState as O}from"react";import{useEffect as Wn,useState as Kn}from"react";var zn=576,Tt=e=>{let[t,n]=Kn(!1);return Wn(()=>{let r=e.current;if(!r||typeof ResizeObserver>"u")return;let s=new ResizeObserver(c=>{let l=c[0]?.contentRect.width??r.clientWidth;n(l>0&&l<zn)});return s.observe(r),()=>s.disconnect()},[e]),t},It=e=>{try{let t=window.localStorage.getItem(e);return t==="table"||t==="cards"?t:"auto"}catch{return"auto"}},_t=(e,t)=>{try{window.localStorage.setItem(e,t)}catch{}};import{useId as $n}from"react";var P=(e,t)=>(e||"").split("{$a}").join(t),Ne=(e,t)=>Object.entries(t).reduce((n,[r,s])=>n.split(`{$a->${r}}`).join(s),e||""),Oe=(e,t,n,r)=>P(n===1&&e[`${t}_one`]?e[`${t}_one`]:e[t],r),k=e=>{let t=document.documentElement.lang||void 0;try{return new Intl.NumberFormat(t).format(e)}catch{return String(e)}};var Ce=e=>{let t=document.createElement("template");return t.innerHTML=e,(t.content.textContent||"").replace(/\s+/g," ").trim()};import{jsx as j,jsxs as at}from"react/jsx-runtime";var Un=(e,t)=>[...new Set([1,e-1,e,e+1,t])].filter(n=>n>=1&&n<=t).sort((n,r)=>n-r),At=({labels:e,page:t,perpage:n,perpages:r,total:s,shown:c,narrow:l,onPage:p,onPerpage:a})=>{let i=$n(),m=Math.max(1,Math.ceil(s/n)),C=t+1,f=s>0?t*n+1:0,A=s>0?f+c-1:0,v=[];if(l)v=[j("span",{className:"local-evidencehub-rview-pager-gap",children:Ne(e.pageof,{page:k(C),pages:k(m)})},"of")];else{let E=Un(C,m);E.forEach((L,g)=>{g>0&&L-E[g-1]>1&&v.push(j("span",{className:"local-evidencehub-rview-pager-gap","aria-hidden":"true",children:"\u2026"},`gap${L}`)),v.push(j("button",{type:"button","aria-current":L===C?"page":void 0,onClick:()=>p(L-1),children:k(L)},L))})}return at("div",{className:"local-evidencehub-rview-foot",children:[j("span",{className:"local-evidencehub-rview-num","data-region":"local-evidencehub-range",children:Ne(e.range,{from:k(f),to:k(A),total:k(s)})}),m>1?at("nav",{className:"local-evidencehub-rview-pager","aria-label":e.pages,children:[j("button",{type:"button",disabled:C===1,onClick:()=>p(t-1),children:e.previous}),v,j("button",{type:"button",disabled:C===m,onClick:()=>p(t+1),children:e.next})]}):null,at("span",{className:"local-evidencehub-rview-perpage",children:[j("label",{htmlFor:i,children:e.perpage}),j("select",{id:i,className:"form-select form-select-sm",value:n,onChange:E=>a(Number(E.target.value)),children:r.map(E=>j("option",{value:E,children:k(E)},E))})]})]})};var J=e=>{let t=window.M?.util;t?.js_pending(e);let n=!0;return()=>{n&&(n=!1,t?.js_complete(e))}};import{useState as Xn}from"react";import{useEffect as Gn,useRef as We}from"react";var Dt=(e,t,n,r,s)=>({defaultWidth:c=>e[c]??t,minimumWidth:c=>n[c]??r,maximum:s}),W=(e,t,n)=>Math.min(e.maximum,Math.max(e.minimumWidth(n),t[n]??e.defaultWidth(n)));import{jsx as R,jsxs as re}from"react/jsx-runtime";var Be=16,qn={ascending:"\u25B2",descending:"\u25BC",none:""},Qn=(e,t)=>e?t==="asc"?"ascending":"descending":"none",Yn=({selection:e,rows:t})=>{let n=We(null),r=t.filter(l=>!e.locked.has(l.id)),s=r.filter(l=>e.selected.has(l.id)).length,c=r.length>0&&s===r.length;return Gn(()=>{n.current&&(n.current.indeterminate=s>0&&!c)},[s,c]),re("label",{className:"local-evidencehub-rview-pickwrap",children:[R("input",{ref:n,type:"checkbox",className:"local-evidencehub-rview-pick",checked:c,disabled:r.length===0,onChange:l=>e.onSelectPage(l.target.checked)}),R("span",{className:"visually-hidden",children:e.pagelabel})]})},lt=({selection:e,row:t})=>e.locked.has(t.id)?R("span",{className:"local-evidencehub-rview-pickwrap local-evidencehub-rview-pick--none"}):re("label",{className:"local-evidencehub-rview-pickwrap",children:[R("input",{type:"checkbox",className:"local-evidencehub-rview-pick",checked:e.selected.has(t.id),onChange:n=>e.onSelect(t.id,n.target.checked)}),R("span",{className:"visually-hidden",children:e.label(t)})]}),Ft=({labels:e,columns:t,lead:n,rows:r,widths:s,sizing:c,sort:l,direction:p,selection:a,renderLead:i,renderCell:m,onSort:C,onWidth:f,onWidthCommit:A})=>{let v=We(null),E=We(null),L=We(null),g=t.reduce((d,b)=>d+W(c,s,b.key),0),oe=d=>{d.currentTarget.classList.toggle("is-scrolled",d.currentTarget.scrollLeft>0)},U=(d,b)=>{let T=E.current?.querySelector(`col[data-col="${CSS.escape(d)}"]`);!T||!E.current||(T.style.width=`${b}px`,E.current.style.width=`${g-W(c,s,d)+b}px`)},q=(d,b)=>Math.round(Math.min(c.maximum,Math.max(c.minimumWidth(d),b))),be=(d,b)=>{if(d.button!==0)return;d.preventDefault(),d.currentTarget.setPointerCapture(d.pointerId),d.currentTarget.classList.add("is-active");let T=W(c,s,b);L.current={key:b,x:d.clientX,width:T,now:T}},G=d=>{let b=L.current;if(!b)return;let T=document.documentElement.dir==="rtl",H=(d.clientX-b.x)*(T?-1:1),u=q(b.key,b.width+H);u!==b.now&&(U(b.key,u),b.now=u)},te=d=>{let b=L.current;L.current=null,d.currentTarget.classList.remove("is-active"),b&&b.now!==b.width&&f(b.key,b.now)},ne=(d,b)=>{let T=document.documentElement.dir==="rtl",H=0;if(d.key==="ArrowRight")H=T?-Be:Be;else if(d.key==="ArrowLeft")H=T?Be:-Be;else if(d.key==="Home"){d.preventDefault(),f(b,null,!0);return}if(H===0)return;d.preventDefault();let u=q(b,W(c,s,b)+H);u!==W(c,s,b)&&f(b,u,!0)};return R("div",{className:"local-evidencehub-rview-gridframe",ref:v,tabIndex:0,role:"region","aria-label":e.grid,onScroll:oe,children:re("table",{className:"local-evidencehub-rview-grid",ref:E,style:{width:`${g}px`},children:[R("caption",{className:"visually-hidden",children:e.caption}),R("colgroup",{children:t.map(d=>R("col",{"data-col":d.key,style:{width:`${W(c,s,d.key)}px`}},d.key))}),R("thead",{children:R("tr",{children:t.map(d=>{let b=Qn(l===d.key,p),T=R("span",{className:"local-evidencehub-rview-th-title",children:d.title}),H=d.sortable?re("button",{type:"button",className:"local-evidencehub-rview-sortbtn",onClick:()=>C(d.key),children:[T,R("span",{className:"local-evidencehub-rview-arrow","aria-hidden":"true",children:qn[b]})]}):R("span",{className:"local-evidencehub-rview-plain",children:T});return re("th",{scope:"col",className:d.key===n?"local-evidencehub-rview-fixed":void 0,"aria-sort":d.sortable?b:void 0,title:d.title,"data-column":d.key,children:[a&&d.key===n?re("div",{className:"local-evidencehub-rview-lead",children:[R(Yn,{selection:a,rows:r}),H]}):H,R("span",{className:"local-evidencehub-rview-resize",role:"separator","aria-orientation":"vertical","aria-label":P(e.resize,d.title),"aria-valuemin":c.minimumWidth(d.key),"aria-valuemax":c.maximum,"aria-valuenow":W(c,s,d.key),tabIndex:0,onPointerDown:u=>be(u,d.key),onPointerMove:G,onPointerUp:te,onPointerCancel:te,onDoubleClick:()=>{W(c,s,d.key)!==c.defaultWidth(d.key)&&f(d.key,null)},onKeyDown:u=>ne(u,d.key),onBlur:A})]},d.key)})})}),R("tbody",{children:r.map(d=>R("tr",{"data-record":d.id,className:a?.selected.has(d.id)?"local-evidencehub-rview-row--selected":void 0,children:t.map(b=>b.key===n?R("th",{scope:"row",className:"local-evidencehub-rview-fixed",children:a?re("div",{className:"local-evidencehub-rview-lead",children:[R(lt,{selection:a,row:d}),i(d,b)]}):i(d,b)},b.key):R("td",{children:m(d,b)},b.key))},d.id))})]})})},Ht=({columns:e,lead:t,widths:n,sizing:r})=>{let s=e.reduce((l,p)=>l+W(r,n,p.key),0),c=["local-evidencehub-rview-skb--w80","local-evidencehub-rview-skb--w60","local-evidencehub-rview-skb--w40"];return R("div",{className:"local-evidencehub-rview-gridframe","aria-hidden":"true",children:re("table",{className:"local-evidencehub-rview-grid",style:{width:`${s}px`},children:[R("colgroup",{children:e.map(l=>R("col",{style:{width:`${W(r,n,l.key)}px`}},l.key))}),R("thead",{children:R("tr",{children:e.map(l=>R("th",{className:l.key===t?"local-evidencehub-rview-fixed":void 0,children:R("span",{className:"local-evidencehub-rview-plain",children:R("span",{className:"local-evidencehub-rview-th-title",children:l.title})})},l.key))})}),R("tbody",{children:Array.from({length:8},(l,p)=>R("tr",{className:"local-evidencehub-rview-sk",children:e.map((a,i)=>R("td",{className:a.key===t?"local-evidencehub-rview-fixed":void 0,children:R("span",{className:"local-evidencehub-rview-skb "+c[(p+i)%3]})},a.key))},p))})]})})};import{jsx as B,jsxs as xe}from"react/jsx-runtime";var Zn=({labels:e,row:t,single:n,selection:r,renderCard:s})=>{let[c,l]=Xn(!1),p=s(t),a=!n||c,i=r?.selected.has(t.id)??!1;return xe("article",{className:"local-evidencehub-rview-card"+(i?" local-evidencehub-rview-card--selected":""),role:"listitem","data-record":t.id,children:[xe("div",{className:"local-evidencehub-rview-card-l1",children:[r?B(lt,{selection:r,row:t}):null,p.head]}),p.lines,p.fields.length>0?xe("div",{className:"local-evidencehub-rview-card-more",children:[n?B("button",{type:"button",className:"btn btn-link btn-sm local-evidencehub-rview-linkbtn","aria-expanded":c,onClick:()=>l(!c),children:c?e.less:e.more}):null,a?B("dl",{className:"local-evidencehub-rview-card-list",children:p.fields.map(m=>xe("div",{className:"local-evidencehub-rview-card-item",children:[B("dt",{children:m.title}),B("dd",{children:m.content})]},m.key))}):null]}):null]})},Vt=({labels:e,rows:t,single:n,selection:r,renderCard:s})=>B("div",{className:"local-evidencehub-rview-cards"+(n?" local-evidencehub-rview-cards--single":""),role:"list",children:t.map(c=>B(Zn,{labels:e,row:c,single:n,selection:r,renderCard:s},c.id))}),Ot=({single:e})=>B("div",{className:"local-evidencehub-rview-cards"+(e?" local-evidencehub-rview-cards--single":""),"aria-hidden":"true",children:Array.from({length:e?4:6},(t,n)=>xe("div",{className:"local-evidencehub-rview-card local-evidencehub-rview-sk",children:[B("span",{className:"local-evidencehub-rview-skb local-evidencehub-rview-skb--w60"}),B("span",{className:"local-evidencehub-rview-skb local-evidencehub-rview-skb--w80"}),B("span",{className:"local-evidencehub-rview-skb local-evidencehub-rview-skb--w60"}),B("span",{className:"local-evidencehub-rview-skb local-evidencehub-rview-skb--w40"})]},n))});import{Fragment as Wt,jsx as ee,jsxs as ve}from"react/jsx-runtime";var jn=({labels:e,loaded:t,error:n,total:r,filtered:s,cards:c,narrow:l,skeleton:p,children:a,onRetry:i})=>{let m=ve("p",{className:"local-evidencehub-rview-error",children:[e.loaderror," ",ee("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:i,children:e.retry})]});return!t&&n?m:t?r===0?ve("div",{className:"local-evidencehub-rview-empty",children:[ee("p",{className:"local-evidencehub-rview-empty-title",children:s?e.nomatch:e.empty}),s?ee("p",{className:"local-evidencehub-rview-empty-text",children:e.nomatchhelp}):null]}):ve("div",{className:"local-evidencehub-rview-dim",children:[n?m:null,a,c?null:ee("p",{className:"local-evidencehub-rview-hint",children:e.scrollhint})]}):ve(Wt,{children:[c?ee(Ot,{single:l}):ee(Ht,{...p}),ee("span",{className:"visually-hidden",role:"status",children:e.loading})]})},Bt=e=>ve(Wt,{children:[ve("section",{className:"local-evidencehub-rview-region","aria-label":e.labels.caption,"aria-busy":e.loading,"data-region":"local-evidencehub-rows",children:[e.loading&&e.loaded?ee("div",{className:"local-evidencehub-rview-progress"}):null,ee(jn,{...e})]}),e.dock??null]});import{requireAsync as Kt}from"@moodle/lms/core/amd";var zt=async e=>(await Kt("core/first"),Kt(e)),$t=async(e,t,n)=>{try{await(await zt("local_evidencehub/drawer")).open(e,t,n)}catch(r){try{(await zt("core/notification")).exception(r)}catch{window.console.error(r)}}};import{Fragment as tr,jsx as y,jsxs as V}from"react/jsx-runtime";var Jn="\u2013",Ke=({label:e,value:t,sub:n})=>V("div",{className:"local-evidencehub-rview-tile",children:[y("div",{className:"local-evidencehub-rview-tile-label",children:e}),y("div",{className:"local-evidencehub-rview-tile-value",children:t}),y("div",{className:"local-evidencehub-rview-tile-sub",children:n})]}),Ut=({label:e,share:t,figure:n,title:r,disabled:s,onPress:c})=>y("li",{className:"local-evidencehub-rview-hitem",children:V("button",{type:"button",className:"local-evidencehub-rview-hrow",title:r,disabled:s,onClick:c,children:[y("span",{className:"local-evidencehub-rview-hrow-label",children:e}),y("span",{className:"local-evidencehub-rview-track","aria-hidden":"true",children:y("span",{className:"local-evidencehub-rview-fill",style:{width:`${Math.round(Math.min(1,Math.max(0,t))*100)}%`}})}),y("span",{className:"local-evidencehub-rview-hrow-n",children:n})]})}),er=()=>V("div",{"aria-hidden":"true",children:[y("div",{className:"local-evidencehub-rview-band",children:Array.from({length:4},(e,t)=>V("div",{className:"local-evidencehub-rview-tile local-evidencehub-rview-sk",children:[y("span",{className:"local-evidencehub-rview-skb local-evidencehub-rview-skb--w60"}),y("span",{className:"local-evidencehub-rview-skb local-evidencehub-rview-skb--big"}),y("span",{className:"local-evidencehub-rview-skb local-evidencehub-rview-skb--w80"})]},t))}),y("div",{className:"local-evidencehub-rview-dist",children:[0,1].map(e=>V("div",{className:"local-evidencehub-rview-panel local-evidencehub-rview-sk",children:[y("span",{className:"local-evidencehub-rview-skb local-evidencehub-rview-skb--w40"}),y("span",{className:"local-evidencehub-rview-skb"}),y("span",{className:"local-evidencehub-rview-skb"}),y("span",{className:"local-evidencehub-rview-skb"})]},e))})]}),Gt=({labels:e,summary:t,loading:n,error:r,canfilter:s,onStatus:c,onCompetency:l,onRetry:p})=>{let a;if(t===null&&r)a=V("p",{className:"local-evidencehub-rview-error",children:[e.loaderror," ",y("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:p,children:e.retry})]});else if(t===null)a=V(tr,{children:[y(er,{}),y("span",{className:"visually-hidden",role:"status",children:e.loadband})]});else{let i=t.rate??null,m=Math.max(1,...t.distribution.map(f=>f.count)),C=Math.max(1,...t.worst.map(f=>f.count));a=V("div",{className:"local-evidencehub-rview-dim",children:[r?V("p",{className:"local-evidencehub-rview-error",children:[e.loaderror," ",y("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:p,children:e.retry})]}):null,V("div",{className:"local-evidencehub-rview-band",children:[y(Ke,{label:e.workers,value:k(t.workers),sub:t.hidden>0?P(e.hiddenworkers,k(t.hidden)):e.workerssub}),y(Ke,{label:e.ready,value:i===null?Jn:k(t.ready),sub:i===null?e.readynone:P(e.readysub,k(Math.round(i*100)))}),y(Ke,{label:e.attentiontile,value:k(t.attention),sub:P(e.attentionsub,k(t.pairs))}),y(Ke,{label:e.expiring,value:k(t.expiring),sub:e.expiringsub})]}),V("div",{className:"local-evidencehub-rview-dist",children:[V("div",{className:"local-evidencehub-rview-panel",children:[y("div",{className:"local-evidencehub-rview-panel-title",children:y("span",{children:e.bystatus})}),y("ul",{className:"local-evidencehub-rview-hlist",children:t.distribution.map(f=>y(Ut,{label:f.label,share:f.count/m,figure:k(f.count),title:e.showpairs,disabled:!s,onPress:()=>c(f.status)},f.status))})]}),V("div",{className:"local-evidencehub-rview-panel",children:[y("div",{className:"local-evidencehub-rview-panel-title",children:y("span",{children:e.worst})}),t.worst.length>0?y("ul",{className:"local-evidencehub-rview-hlist",children:t.worst.map(f=>y(Ut,{label:f.name,share:f.count/C,figure:k(f.count),title:e.showpairs,disabled:!s,onPress:()=>l(f.competencyid)},f.competencyid))}):y("p",{className:"local-evidencehub-rview-note",children:e.noworst})]})]})]})}return V("section",{className:"local-evidencehub-rview-region local-evidencehub-rview-bandregion","aria-label":e.bandlabel,"aria-busy":n,"data-region":"local-evidencehub-band",children:[n&&t!==null?y("div",{className:"local-evidencehub-rview-progress"}):null,a]})};import{useEffect as Ue,useId as mr,useRef as Se,useState as tn}from"react";import{useId as rr,useState as Qt}from"react";import{jsx as qt}from"react/jsx-runtime";var nr={filter:"M2 3h12l-4.5 5.5V13l-3 1.5V8.5L2 3z",columns:"M2 3h12v10H2zM6.5 3v10M10 3v10",table:"M2 3h12v10H2zM2 6.5h12M2 10h12M6 6.5V13",cards:"M2 3h5v4H2zM9 3h5v4H9zM2 9h5v4H2zM9 9h5v4H9z",auto:"M2 3h7v10H2zM11 3h3v4h-3zM11 9h3v4h-3z",download:"M8 2v8M4.5 7 8 10.5 11.5 7M3 13h10",search:"M7 12A5 5 0 1 0 7 2a5 5 0 0 0 0 10zM10.5 10.5 14 14",close:"M4 4l8 8M12 4l-8 8",eraser:"M9.5 2 14 6.5 7.5 13 3 8.5zM5.6 5.9l4.5 4.5M7.5 13H14",tick:"M3 8.5 6.5 12 13 4.5",chevron:"M4 6l4 4 4-4",reset:"M3 8a5 5 0 1 0 1.5-3.5M3 2.5V5h2.5",queue:"M3 4h10M3 8h10M3 12h6",matrix:"M2.5 2.5h3v3h-3zM6.5 2.5h3v3h-3zM10.5 2.5h3v3h-3zM2.5 6.5h3v3h-3zM6.5 6.5h3v3h-3zM10.5 6.5h3v3h-3zM2.5 10.5h3v3h-3zM6.5 10.5h3v3h-3zM10.5 10.5h3v3h-3z",lock:"M4.5 7.5h7v6h-7zM6 7.5V5.5a2 2 0 0 1 4 0v2"},M=({name:e,className:t})=>qt("svg",{className:"local-evidencehub-rview-icon"+(t?" "+t:""),viewBox:"0 0 16 16",width:"16",height:"16",fill:"none",stroke:"currentColor",strokeWidth:"1.5",strokeLinecap:"round",strokeLinejoin:"round","aria-hidden":"true",focusable:"false",children:qt("path",{d:nr[e]??""})});import{Fragment as cr,jsx as _,jsxs as K}from"react/jsx-runtime";var or=8,it=50,ar=(e,t)=>t===null?e.show:P(e.showcount,k(t)),Yt=({option:e})=>e.count>=0?_("small",{className:"local-evidencehub-rview-optcount",children:k(e.count)}):null,lr=(e,t)=>e.options.filter(n=>n.count!==0||n.value===t),ir=({labels:e,facet:t,shown:n,value:r,headid:s,onChange:c})=>{let[l,p]=Qt(""),[a,i]=Qt(""),m=l.trim().toLocaleLowerCase(),C=t.groupings.length>1?t.groupings:[],f=C.find(g=>String(g.id)===a)?.groups.map(String)??null,A=n.filter(g=>g.value===r),v=n.filter(g=>g.value!==r&&(m===""||g.label.toLocaleLowerCase().includes(m))&&(f===null||f.includes(g.value))),E=P(t.find.label,k(t.options.length)),L=C.length>0?e.moregroupsgrouping:e.moregroups;return K(cr,{children:[K("div",{className:"local-evidencehub-rview-find",children:[_("input",{type:"search",className:"form-control form-control-sm local-evidencehub-rview-input",placeholder:E,"aria-label":E,value:l,onChange:g=>p(g.target.value)}),C.length>0?K("select",{className:"form-select form-select-sm local-evidencehub-rview-grouping","aria-label":e.grouping,value:a,onChange:g=>i(g.target.value),children:[_("option",{value:"",children:e.allgroupings}),C.map(g=>_("option",{value:String(g.id),children:g.name},g.id))]}):null]}),K("div",{className:"local-evidencehub-rview-optlist",role:"group","aria-labelledby":s,children:[[...A,...v.slice(0,it)].map(g=>K("button",{type:"button",className:"local-evidencehub-rview-optrow","aria-pressed":g.value===r,onClick:()=>c(t.name,g.value===r?"":g.value),children:[_("span",{className:"local-evidencehub-rview-box","aria-hidden":"true",children:_(M,{name:"tick"})}),_("span",{className:"local-evidencehub-rview-optrow-label",children:g.label})," ",_(Yt,{option:g})]},g.value)),v.length>it?_("p",{className:"local-evidencehub-rview-note",children:P(L,k(v.length-it))}):null,A.length===0&&v.length===0?_("p",{className:"local-evidencehub-rview-note",children:t.find.nomatch}):null]})]})},sr=({labels:e,facet:t,value:n,onChange:r})=>{let s=rr(),c=lr(t,n),l=[{value:"",label:e.any,count:-1},...c];if(t.options.length>or)return K("div",{className:"local-evidencehub-rview-fgroup",children:[_("span",{className:"local-evidencehub-rview-fpanel-head",id:s,children:t.title}),_(ir,{labels:e,facet:t,shown:c,value:n,headid:s,onChange:r})]});let p=a=>{let i=l.findIndex(C=>C.value===n),m=-1;a.key==="ArrowRight"||a.key==="ArrowDown"?m=(i+1)%l.length:a.key==="ArrowLeft"||a.key==="ArrowUp"?m=(i-1+l.length)%l.length:a.key==="Home"?m=0:a.key==="End"&&(m=l.length-1),!(m<0)&&(a.preventDefault(),r(t.name,l[m].value),a.currentTarget.querySelectorAll('[role="radio"]')[m]?.focus())};return K("div",{className:"local-evidencehub-rview-fgroup",children:[_("span",{className:"local-evidencehub-rview-fpanel-head",id:s,children:t.title}),_("div",{className:"local-evidencehub-rview-fpanel-opts",role:"radiogroup","aria-labelledby":s,onKeyDown:p,children:l.map(a=>K("button",{type:"button",className:"local-evidencehub-rview-fpanel-opt",role:"radio","aria-checked":a.value===n,tabIndex:a.value===n?0:-1,onClick:()=>r(t.name,a.value),children:[a.label," ",_(Yt,{option:a})]},a.value))})]})},Xt=({labels:e,id:t,hidden:n,facets:r,values:s,total:c,note:l,onChange:p,onClear:a,onShow:i})=>K("div",{className:"local-evidencehub-rview-fpanel",id:t,role:"dialog","aria-label":e.filters,hidden:n,children:[K("div",{className:"local-evidencehub-rview-fpanel-body",children:[r.map(m=>_(sr,{labels:e,facet:m,value:s[m.name]??"",onChange:p},m.name)),l??null]}),K("div",{className:"local-evidencehub-rview-fpanel-foot",children:[_("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:a,children:e.clearfilters}),_("button",{type:"button",className:"btn btn-sm btn-primary",onClick:i,children:ar(e,c)})]})]});import{Fragment as ur,jsx as Ee,jsxs as Zt}from"react/jsx-runtime";var ze=e=>{if(!["ArrowDown","ArrowUp","Home","End"].includes(e.key))return;let t=Array.from(e.currentTarget.querySelectorAll('[role^="menuitem"]'));if(t.length===0)return;e.preventDefault();let n=t.indexOf(document.activeElement),r=0;e.key==="End"?r=t.length-1:e.key==="ArrowDown"?r=(n+1)%t.length:e.key==="ArrowUp"&&(r=(n-1+t.length)%t.length),t[r].focus()},jt=({heading:e,sets:t,onSet:n})=>t.length===0?null:Zt(ur,{children:[Ee("div",{className:"local-evidencehub-rview-menu-head",role:"presentation",children:e}),t.map(r=>Zt("button",{type:"button",className:"local-evidencehub-rview-menu-item",role:"menuitemcheckbox","aria-checked":r.active,tabIndex:-1,"data-preset":r.name,onClick:()=>{r.active||n(r.name)},children:[Ee("span",{className:"local-evidencehub-rview-box","aria-hidden":"true",children:Ee(M,{name:"tick"})}),Ee("span",{className:"local-evidencehub-rview-menu-label",children:r.label})]},r.name)),Ee("div",{className:"local-evidencehub-rview-menu-sep",role:"separator"})]});import{Fragment as dr,jsx as Jt,jsxs as en}from"react/jsx-runtime";var $e=({label:e,value:t,options:n,small:r,onChange:s})=>{let c=l=>{let p=n.findIndex(m=>m.value===t),a=-1;if(l.key==="ArrowRight"||l.key==="ArrowDown"?a=(p+1)%n.length:l.key==="ArrowLeft"||l.key==="ArrowUp"?a=(p-1+n.length)%n.length:l.key==="Home"?a=0:l.key==="End"&&(a=n.length-1),a<0)return;l.preventDefault(),s(n[a].value),l.currentTarget.querySelectorAll('[role="radio"]')[a]?.focus()};return Jt("div",{className:"local-evidencehub-rview-seg"+(r?" local-evidencehub-rview-seg--small":""),role:"radiogroup","aria-label":e,onKeyDown:c,children:n.map(l=>en("button",{type:"button",className:"local-evidencehub-rview-seg-cell",role:"radio","aria-checked":l.value===t,tabIndex:l.value===t?0:-1,onClick:()=>s(l.value),children:[l.label,l.count!==void 0?en(dr,{children:[" ",Jt("small",{className:"local-evidencehub-rview-seg-count",children:l.count})]}):null]},l.value))})};import{Fragment as rn,jsx as h,jsxs as S}from"react/jsx-runtime";var vr=({links:e,tableurl:t,label:n})=>t===""&&e.length===0?null:S(rn,{children:[h("div",{className:"local-evidencehub-rview-menu-sep",role:"separator"}),e.map(r=>S("a",{className:"local-evidencehub-rview-menu-item",role:"menuitem",tabIndex:-1,href:r.href,children:[h(M,{name:r.icon}),h("span",{className:"local-evidencehub-rview-menu-label",children:r.label})]},r.key)),t!==""?S("a",{className:"local-evidencehub-rview-menu-item",role:"menuitem",tabIndex:-1,href:t,children:[h(M,{name:"table"}),h("span",{className:"local-evidencehub-rview-menu-label",children:n})]}):null]}),nn=e=>{let{labels:t,chips:n,columns:r}=e,s=mr(),c=Se(null),l=Se(null),p=Se(null),a=Se(!1),[i,m]=tn(null),[C,f]=tn(e.search!==""),A=Se({});Ue(()=>{if(i===null)return;let u=I=>{c.current&&!c.current.contains(I.target)&&m(null)};return document.addEventListener("mousedown",u),()=>document.removeEventListener("mousedown",u)},[i]),Ue(()=>{if(i===null||i==="filters")return;let u=c.current?.querySelector(`#${CSS.escape(s+i)}`);(u?.querySelector('[aria-checked="true"]:not([aria-disabled="true"])')??u?.querySelector('[role^="menuitem"]:not([aria-disabled="true"])'))?.focus()},[i,s]),Ue(()=>{C?l.current?.focus():a.current&&(a.current=!1,p.current?.focus())},[C]),Ue(()=>{let u=I=>{if(I.key!=="/"||I.ctrlKey||I.metaKey||I.altKey||I.defaultPrevented)return;let D=document.activeElement;if(D&&(D.isContentEditable||D.closest("input, textarea, select")))return;let Q=c.current?.getBoundingClientRect();!Q||Q.width===0||Q.bottom<=0||Q.top>=window.innerHeight||(I.preventDefault(),C?l.current?.focus():(m(null),f(!0)))};return document.addEventListener("keydown",u),()=>document.removeEventListener("keydown",u)},[C]);let v=u=>m(i===u?null:u),E=u=>{a.current=u,f(!1)},L=u=>{if(!(u.key!=="Escape"||u.defaultPrevented))if(i!==null){u.preventDefault();let I=A.current[i];m(null),I?.focus()}else C&&(u.preventDefault(),E(!0))},g=e.search.trim().length>=e.searchmin,oe=g&&!C,U=n.length+(oe?1:0),q=e.total===null?"\u2026":Oe(t,"count",e.total,k(e.total)),be=e.total!==null&&e.narrow?S(rn,{children:[h("span",{"aria-hidden":"true",children:k(e.total)}),h("span",{className:"visually-hidden",children:q})]}):q,G=(u,I,D,Q)=>S("button",{type:"button",className:"local-evidencehub-rview-iconbtn","aria-label":D,"data-local-evidencehub-tip":D,"aria-haspopup":u==="filters"?"dialog":"menu","aria-expanded":i===u,"aria-controls":s+u,ref:Ie=>{A.current[u??""]=Ie},onClick:()=>v(u),children:[h(M,{name:I}),Q]}),te={auto:"auto",table:"table",cards:"cards"}[e.view],ne=h(M,{name:"chevron",className:"local-evidencehub-rview-chevron"}),d=n.length>0?P(t.filterson,k(n.length)):t.filters,b=n.length>0?h("span",{className:"local-evidencehub-rview-badge","aria-hidden":"true",children:k(n.length)}):null,T=[["auto",t.viewauto,t.viewautohint],["table",t.table,""],["cards",t.cards,""]].filter(([u])=>e.views.includes(u)),H="local-evidencehub-rview-bar"+(e.narrow?" local-evidencehub-rview-bar--narrow":"")+(C?" local-evidencehub-rview-bar--searching":"");return S("div",{className:H,"data-region":"local-evidencehub-bar",ref:c,onKeyDown:L,children:[h($e,{label:t.scope,value:e.scope,options:e.scopes,onChange:e.onScope}),h("span",{className:"local-evidencehub-rview-bar-spacer"}),S("div",{className:"local-evidencehub-rview-bar-controls",children:[S("div",{className:"local-evidencehub-rview-bar-cluster",children:[h("span",{className:"local-evidencehub-rview-bar-count","data-region":"local-evidencehub-total","aria-live":"polite",children:be}),S("div",{className:"local-evidencehub-rview-capsule",children:[G("filters","filter",d,b),S("div",{className:"local-evidencehub-rview-anchor",children:[G("columns","columns",t.columns,ne),S("div",{className:"local-evidencehub-rview-menu",id:s+"columns",role:"menu","aria-label":t.columns,hidden:i!=="columns",onKeyDown:ze,children:[h(jt,{heading:t.presets,sets:e.sets,onSet:e.onSet}),r.map(u=>u.fixed?S("div",{className:"local-evidencehub-rview-menu-item",role:"menuitemcheckbox","aria-checked":"true","aria-disabled":"true",tabIndex:-1,children:[h("span",{className:"local-evidencehub-rview-box","aria-hidden":"true",children:h(M,{name:"tick"})}),h("span",{className:"local-evidencehub-rview-menu-label",children:u.title}),h("span",{className:"local-evidencehub-rview-menu-hint",children:t.always})]},u.key):S("button",{type:"button",className:"local-evidencehub-rview-menu-item",role:"menuitemcheckbox","aria-checked":u.active,tabIndex:-1,onClick:()=>e.onColumn(u.key,!u.active),children:[h("span",{className:"local-evidencehub-rview-box","aria-hidden":"true",children:h(M,{name:"tick"})}),h("span",{className:"local-evidencehub-rview-menu-label",children:u.title})]},u.key)),h("div",{className:"local-evidencehub-rview-menu-sep",role:"separator"}),S("button",{type:"button",className:"local-evidencehub-rview-menu-item",role:"menuitem",tabIndex:-1,"aria-disabled":e.usedefault,onClick:()=>{e.usedefault||e.onDefaultColumns()},children:[h(M,{name:"columns"}),h("span",{className:"local-evidencehub-rview-menu-label",children:t.defaultcolumns})]}),S("button",{type:"button",className:"local-evidencehub-rview-menu-item",role:"menuitem",tabIndex:-1,"aria-disabled":!e.haswidths,onClick:()=>{e.haswidths&&e.onResetWidths()},children:[h(M,{name:"reset"}),h("span",{className:"local-evidencehub-rview-menu-label",children:t.resetwidths})]})]})]}),S("div",{className:"local-evidencehub-rview-anchor",children:[G("view",te,t.view,ne),S("div",{className:"local-evidencehub-rview-menu",id:s+"view",role:"menu","aria-label":t.view,hidden:i!=="view",onKeyDown:ze,children:[T.map(([u,I,D])=>S("button",{type:"button",className:"local-evidencehub-rview-menu-item",role:"menuitemradio","aria-checked":e.view===u,tabIndex:-1,onClick:()=>{e.onView(u),m(null),A.current.view?.focus()},children:[h(M,{name:u}),h("span",{className:"local-evidencehub-rview-menu-label",children:I}),D!==""?h("span",{className:"local-evidencehub-rview-menu-hint",children:D}):null,h(M,{name:"tick",className:"local-evidencehub-rview-tick"})]},u)),h(vr,{links:e.viewlinks??[],tableurl:e.tableurl,label:t.coretable})]})]}),e.downloads.length>0?S("div",{className:"local-evidencehub-rview-anchor",children:[G("download","download",t.download,ne),h("div",{className:"local-evidencehub-rview-menu",id:s+"download",role:"menu","aria-label":t.download,hidden:i!=="download",onKeyDown:ze,children:e.downloads.map(u=>S("a",{className:"local-evidencehub-rview-menu-item",role:"menuitem",tabIndex:-1,href:u.href,children:[h(M,{name:"download"}),h("span",{className:"local-evidencehub-rview-menu-label",children:u.label})]},u.key))})]}):null]})]}),h("div",{className:"local-evidencehub-rview-capsule local-evidencehub-rview-searchcap",children:C?S("span",{className:"local-evidencehub-rview-search",children:[e.searching&&g?h("span",{className:"local-evidencehub-rview-spin","aria-hidden":"true"}):h(M,{name:"search"}),h("input",{ref:l,type:"search","aria-label":t.search,placeholder:t.search,autoComplete:"off",value:e.search,onChange:u=>e.onSearch(u.target.value),onKeyDown:u=>{u.key==="Escape"&&(u.preventDefault(),E(!0))},onBlur:()=>{e.search===""&&E(!1)}}),e.search!==""?h("button",{type:"button",className:"local-evidencehub-rview-eraser","aria-label":t.cleartext,"data-local-evidencehub-tip":t.cleartext,onMouseDown:u=>u.preventDefault(),onClick:()=>{e.onSearch(""),l.current?.focus()},children:h(M,{name:"eraser"})}):null]}):h("button",{ref:p,type:"button",className:"local-evidencehub-rview-iconbtn","aria-label":t.search,"data-local-evidencehub-tip":t.search,onClick:()=>{m(null),f(!0)},children:h(M,{name:"search"})})}),h(Xt,{labels:t,id:s+"filters",hidden:i!=="filters",facets:e.facets,values:e.values,total:e.total,note:e.panelnote,onChange:e.onFacet,onClear:e.onClearFilters,onShow:()=>{m(null),A.current.filters?.focus()}})]}),S("div",{className:"local-evidencehub-rview-chips","data-region":"local-evidencehub-chips",hidden:U===0&&!e.aside,children:[n.map(u=>S("button",{type:"button",className:"local-evidencehub-rview-chip","aria-label":P(t.removefilter,u.text),onClick:()=>e.onRemoveChip(u.key),children:[h("span",{children:u.text}),h(M,{name:"close"})]},u.key)),oe?S("button",{type:"button",className:"local-evidencehub-rview-chip","aria-label":P(t.removefilter,P(t.searchchip,e.search.trim())),onClick:()=>e.onSearch(""),children:[h("span",{children:P(t.searchchip,e.search.trim())}),h(M,{name:"close"})]}):null,U>1?h("button",{type:"button",className:"local-evidencehub-rview-clearall",onClick:()=>{e.onClearAll(),f(!1)},children:t.clearall}):null,e.aside??null]})]})};var on="validity:status",Ge="competency:competency",an="user:cohort",st=e=>e.facet!==""&&e.options.length>0,ln=e=>{try{let t=JSON.parse(e.values||"{}");return t!==null&&typeof t=="object"?t:{}}catch{return{}}},br=e=>e.reduce((t,n)=>({...t,...ln(n)}),{}),ct=e=>{if(!e.applied||e.facet==="")return"";let t=ln(e),n=t[`${e.name}_value`];return n==null||Number(t[`${e.name}_operator`]??0)!==1?"":String(n)},ut=(e,t)=>{let n=br(e);for(let r of Object.keys(n))r.startsWith(`${t.name}_`)&&delete n[r];return n},dt=(e,t,n)=>{let r=ut(e,t);return r[`${t.name}_operator`]=n===""?0:1,r[`${t.name}_value`]=n,r},sn=e=>{let t=ct(e);return t===""?"":e.options.find(n=>n.value===t)?.label??""};import{Fragment as kr,jsx as Me,jsxs as mt}from"react/jsx-runtime";var hr=["auto","table","cards"],pr=(e,t)=>{switch(t){case"validity":return e.presetvalidity;case"evidence":return e.presetevidence;default:return t}},wr=(e,t)=>{let n={label:e.searchoptions,nomatch:e.nooptionmatch};return t.name===an?n={label:e.searchcohorts,nomatch:e.nocohortmatch}:t.name===Ge&&(n={label:e.searchcompetencies,nomatch:e.nocompetencymatch}),{name:t.name,title:t.title,options:t.options,groupings:[],find:n}},fr=(e,t)=>{let n=sn(t);return{key:t.name,text:n===""?t.title:Ne(e.filterchip,{title:t.title,value:n})}},gr=(e,t)=>t==="all"?`${e}&statusfilter=1`:e,yr=({labels:e,suspended:t,onAccounts:n})=>mt("div",{className:"local-evidencehub-rview-fgroup","data-region":"local-evidencehub-accounts",children:[Me("span",{className:"local-evidencehub-rview-fpanel-head",children:e.accounts}),Me($e,{label:e.accounts,small:!0,value:t?"all":"active",options:[{value:"active",label:e.activeonly},{value:"all",label:e.includesuspended}],onChange:r=>{r==="all"!==t&&n(r==="all")}})]}),cn=e=>{let{labels:t,filters:n,summary:r}=e,s=n.filter(st),c=n.filter(a=>!st(a)),l=a=>n.find(i=>i.name===a),p=e.presets.map(a=>({name:a.name,label:pr(t,a.name),active:a.active}));return Me(nn,{labels:t,narrow:e.narrow,scopes:[{value:"attention",label:t.attention,count:r===null?void 0:k(r.attention)},{value:"all",label:t.all,count:r===null?void 0:k(r.pairs)}],scope:e.scope,total:e.total,facets:s.map(a=>wr(t,a)),values:Object.fromEntries(s.map(a=>[a.name,ct(a)])),panelnote:mt(kr,{children:[Me(yr,{labels:t,suspended:e.suspended,onAccounts:e.onAccounts}),c.length>0?mt("p",{className:"local-evidencehub-rview-note",children:[t.morefiltershelp," ",Me("a",{href:e.tableurl,children:t.morefilters})]}):null]}),chips:n.filter(a=>a.applied).map(a=>fr(t,a)),columns:e.columns,sets:p,usedefault:e.usedefault,haswidths:e.haswidths,view:e.view,views:hr,tableurl:e.tableurl,viewlinks:e.matrixurl===""?[]:[{key:"matrix",label:t.matrix,href:gr(e.matrixurl,e.scope),icon:"matrix"}],downloads:[],search:e.search,searchmin:e.searchmin,searching:e.searching,onScope:a=>e.onScope(a==="all"?"all":"attention"),onSearch:e.onSearch,onFacet:(a,i)=>{let m=l(a);m&&e.onFilter(m,i)},onRemoveChip:a=>{let i=l(a);i&&e.onRemoveFilter(i)},onClearFilters:e.onClearFilters,onClearAll:e.onClearAll,onColumn:e.onColumn,onSet:a=>{let i=e.presets.find(m=>m.name===a);i&&e.onPreset(i)},onDefaultColumns:e.onDefaultColumns,onResetWidths:e.onResetWidths,onView:e.onView})};import{Fragment as Rr,jsx as un}from"react/jsx-runtime";var qe=({parts:e})=>un(Rr,{children:e.map((t,n)=>un("span",{className:n===0?"local-evidencehub-rview-c":"local-evidencehub-rview-sub",title:Ce(t),dangerouslySetInnerHTML:{__html:t}},n))});import{Fragment as Fr,jsx as ie,jsxs as Dr}from"react/jsx-runtime";var Nr={worker:260,competency:240,status:180,expiry:132,count:104,type:200,issuer:200,competencyidnumber:150,framework:200,issuedate:132,expirydate:132,verification:160,changed:160},Cr=180,xr={worker:200,expiry:104,issuedate:104,expirydate:104,changed:104},Er=80,Sr=800,Qe=Dt(Nr,Cr,xr,Er,Sr),dn=-1,Mr=0,Pr=1,Lr=2,Tr=3,Ir=4,_r=5,Ar=e=>{switch(e){case Lr:return"success";case Tr:return"info";case Ir:return"warning";case _r:return"danger";case Pr:case Mr:case dn:default:return"neutral"}},z=(e,t)=>e.cells.find(n=>n.key===t)?.html??"",vt=({row:e})=>{let t=z(e,"status");return t===""?null:Dr("span",{className:`local-evidencehub-rview-status local-evidencehub-rview-status--${Ar(e.status)}`,title:Ce(t),children:[e.status===dn?ie(M,{name:"lock"}):null,ie("span",{dangerouslySetInnerHTML:{__html:t}})]})},mn=({row:e,column:t})=>t==="status"?ie(vt,{row:e}):ie(qe,{parts:[z(e,t)]}),Ye=({row:e,label:t,onInspect:n})=>e.inspect?ie("button",{type:"button",className:"btn btn-sm btn-outline-secondary local-evidencehub-rview-inspect","aria-label":e.inspectlabel,"data-userid":e.userid,"data-competencyid":e.competencyid,"data-title":e.drawertitle,onClick:r=>n(r.currentTarget),children:t}):null,Xe=({row:e,identity:t})=>ie(Fr,{children:t.map(n=>{let r=z(e,n.key);return r===""?null:ie("span",{className:"local-evidencehub-rview-sub",title:Ce(r),dangerouslySetInnerHTML:{__html:r}},n.key)})});import{Fragment as bn,jsx as $,jsxs as Pe}from"react/jsx-runtime";var Hr=["worker","competency","status","expiry","type"],vn=({row:e,column:t})=>Pe("div",{className:"local-evidencehub-rview-card-line",children:[$("span",{className:"local-evidencehub-rview-card-key",children:t.title}),$("span",{className:"local-evidencehub-rview-inline",dangerouslySetInnerHTML:{__html:z(e,t.key)}})]}),Vr=(e,t,n,r,s)=>{let c=i=>t.find(m=>m.key===i),l=c("expiry"),p=c("type"),a=t.filter(i=>!Hr.includes(i.key)&&z(r,i.key).trim()!=="");return{head:Pe(bn,{children:[Pe("div",{className:"local-evidencehub-rview-card-who",children:[$("span",{className:"local-evidencehub-rview-card-name",dangerouslySetInnerHTML:{__html:z(r,"worker")}}),$(Xe,{row:r,identity:n})]}),$(Ye,{row:r,label:e.inspect,onInspect:s})]}),lines:Pe(bn,{children:[Pe("div",{className:"local-evidencehub-rview-card-line",children:[$("span",{className:"local-evidencehub-rview-inline",dangerouslySetInnerHTML:{__html:z(r,"competency")}}),c("status")?$(vt,{row:r}):null]}),l?$(vn,{row:r,column:l}):null,p?$(vn,{row:r,column:p}):null]}),fields:a.map(i=>({key:i.key,title:i.title,content:$(qe,{parts:[z(r,i.key)]})}))}},hn=({labels:e,columns:t,identity:n,rows:r,single:s,selection:c,onInspect:l})=>$(Vt,{labels:e,rows:r,single:s,selection:c,renderCard:p=>Vr(e,t,n,p,l)});import{jsx as pn,jsxs as bt}from"react/jsx-runtime";var wn=({labels:e,count:t,workers:n,posting:r,onExport:s})=>bt("div",{className:"local-evidencehub-rview-dock",role:"region","aria-label":e.dock,"data-region":"local-evidencehub-dock",children:[bt("span",{className:"local-evidencehub-rview-dockcount","aria-live":"polite",children:[P(e.selected,k(t)),bt("span",{className:"local-evidencehub-rview-dockworkers",children:[" \xB7 ",Oe(e,"selectedworkers",n,k(n))]})]}),pn("div",{className:"local-evidencehub-rview-dockactions",children:pn("button",{type:"button",className:"btn btn-primary",disabled:r,onClick:s,children:e.exportdossiers})})]});var se=(e,t,n)=>{let r=document.createElement("input");r.type="hidden",r.name=t,r.value=n,e.appendChild(r)},fn=e=>{let t=document.createElement("form");t.method="post",t.action=e.action,t.hidden=!0,se(t,"contextid",String(e.contextid)),se(t,"action","exportdossiers"),se(t,"sesskey",e.sesskey),se(t,"templateid",String(e.templateid)),se(t,"statusfilter","1"),se(t,"suspended",e.suspended?"1":"0"),e.pairs.forEach(n=>se(t,"pairs[]",`${n.userid}:${n.competencyid}`)),document.body.appendChild(t),t.submit(),t.remove()};import{jsx as Le,jsxs as gn}from"react/jsx-runtime";var yn=({labels:e,columns:t,identity:n,rows:r,widths:s,sort:c,direction:l,selection:p,onInspect:a,onSort:i,onWidth:m,onWidthCommit:C})=>Le(Ft,{labels:e,columns:t,lead:"worker",rows:r,widths:s,sizing:Qe,sort:c,direction:l,selection:p,renderLead:f=>gn("div",{className:"local-evidencehub-rview-who",children:[gn("div",{className:"local-evidencehub-rview-who-text",children:[Le("span",{className:"local-evidencehub-rview-c",dangerouslySetInnerHTML:{__html:z(f,"worker")}}),Le(Xe,{row:f,identity:n})]}),Le(Ye,{row:f,label:e.inspect,onInspect:a})]}),renderCell:(f,A)=>Le(mn,{row:f,column:A.key}),onSort:i,onWidth:m,onWidthCommit:C});import{fetchOne as Te}from"@moodle/lms/core/ajax";var kn=(e,t,n)=>Te({methodname:"local_evidencehub_report_rows",args:{...e,...t,facets:n}}),Rn=(e,t,n)=>Te({methodname:"local_evidencehub_report_summary",args:{...e,scope:t,search:n}}),ht=(e,t,n)=>Te({methodname:"local_evidencehub_set_report_preference",args:{...e,usedefault:t.usedefault,columns:t.columns,widths:t.widths,suspended:n===null?-1:Number(n)}}),Ze=(e,t)=>Te({methodname:"core_reportbuilder_set_filters",args:{reportid:e,parameters:"{}",values:JSON.stringify(t)}}),pt=e=>Te({methodname:"core_reportbuilder_filters_reset",args:{reportid:e,parameters:"{}"}});import{jsx as ce,jsxs as Ur}from"react/jsx-runtime";var Or=300,Br=500,Wr=3e3,Cn="local_evidencehub_hub_view",Kr=["worker","competency","status","expiry","count","type","issuer"],ft=e=>Object.entries(e).map(([t,n])=>({key:t,width:n})),wt=(e,t,n)=>{let r=ft(n);return e&&r.length===0?{usedefault:!0,columns:[],widths:[]}:{usedefault:!1,columns:t,widths:r}},zr=e=>e===null?{allcolumns:[],usedefault:!0,suspended:!1,candecide:!1}:{allcolumns:e.columns,usedefault:e.preference.usedefault,suspended:e.preference.suspended,candecide:e.candecide},$r=e=>{let{labels:t,reportid:n}=e,r=Nn(()=>({contextid:e.contextid,templateid:e.templateid}),[e.contextid,e.templateid]),s=F(null),c=Tt(s),[l,p]=O(()=>It(Cn)),a={scope:"attention",search:"",page:0,perpage:e.perpage,sort:"",direction:"asc"},i=F(a),[m,C]=O(a),[f,A]=O(""),[v,E]=O(null),[L,g]=O(!0),[oe,U]=O(!1),[q,be]=O(null),[G,te]=O(!0),[ne,d]=O(!1),[b,T]=O({}),[H,u]=O({}),[I,D]=O(()=>new Set),[Q,Ie]=O(!1),et=F(new Map),tt=F(0),nt=F(0),he=F(null),pe=F(null),ae=F(null),Y=F(null),gt=F(!1),ue=F(null),_e=F(null),rt=F(null),Ae=F(()=>{}),De=F(null),Fe=je((o,w)=>{let N=`${o}:${w}`,x=et.current.get(N);return x===void 0&&(x=et.current.size+1,et.current.set(N,x)),x},[]),de=je((o,w)=>{let N=++tt.current;he.current||(he.current=J("local_evidencehub/hub:rows")),g(!0),kn(r,o,w).then(x=>{N===tt.current&&(x.counted&&u(Object.fromEntries(x.filters.map(Z=>[Z.name,Z]))),gt.current||(gt.current=!0,T(Object.fromEntries(x.preference.widths.map(Z=>[Z.key,Z.width])))),x.page!==i.current.page&&(i.current={...i.current,page:x.page},C(i.current)),E(x),D(Z=>{let Bn=new Set(x.rows.filter(me=>me.selectable).map(me=>Fe(me.userid,me.competencyid))),Lt=Array.from(Z).filter(me=>x.candecide&&Bn.has(me));return Lt.length===Z.size?Z:new Set(Lt)}),U(!1),g(!1))}).catch(()=>{N===tt.current&&(U(!0),g(!1))})},[r,Fe]),we=je(o=>{let w=++nt.current;pe.current||(pe.current=J("local_evidencehub/hub:band")),te(!0),Rn(r,o.scope,o.search).then(N=>{w===nt.current&&(be(N),d(!1),te(!1))}).catch(()=>{w===nt.current&&(d(!0),te(!1))})},[r]),X=je((o,w)=>{i.current={...i.current,...o},C(i.current),D(new Set),de(i.current,w),w&&we(i.current)},[de,we]);Je(()=>{!L&&he.current&&(he.current(),he.current=null)},[L,v,oe]),Je(()=>{!G&&pe.current&&(pe.current(),pe.current=null)},[G,q,ne]),Je(()=>{de(i.current,!0),we(i.current)},[de,we]),Je(()=>()=>{ae.current!==null&&window.clearTimeout(ae.current),De.current!==null&&window.clearTimeout(De.current),Y.current?.(),Ae.current()},[]);let En=o=>{p(o),_t(Cn,o)},Sn=o=>{A(o),ae.current!==null&&(window.clearTimeout(ae.current),ae.current=null);let w=o.trim(),N=w.length>=e.searchmin?w:"";if(N===i.current.search){Y.current?.(),Y.current=null;return}if(o===""){Y.current?.(),Y.current=null,X({search:"",page:0},!0);return}Y.current||(Y.current=J("local_evidencehub/hub:search")),ae.current=window.setTimeout(()=>{ae.current=null,X({search:N,page:0},!0),Y.current?.(),Y.current=null},Or)},fe=o=>{D(new Set);let w=J("local_evidencehub/hub:filters");o().then(()=>{X({page:0},!0)}).catch(()=>{U(!0)}).finally(w)},le=(v?.filters??[]).map(o=>H[o.name]?{...o,options:H[o.name].options}:o),yt=(o,w)=>{let N=le.find(x=>x.name===o);N&&fe(()=>Ze(n,dt(le,N,w)))},{allcolumns:He,usedefault:Ve,suspended:kt,candecide:Mn}=zr(v),ge=He.filter(o=>!o.fixed&&o.active).map(o=>o.key),ye=(o,w)=>{let N=J("local_evidencehub/hub:preference");ht(r,o,null).then(()=>{w&&de(i.current,!1)}).catch(()=>{w&&U(!0)}).finally(N)},Pn=o=>{v&&E({...v,preference:{...v.preference,suspended:o}}),D(new Set);let w=J("local_evidencehub/hub:accounts");ht(r,wt(Ve,ge,b),o).then(()=>{X({page:0},!0)}).catch(()=>{U(!0)}).finally(w)},Ln=(o,w)=>{let N=w?[...ge,o]:ge.filter(x=>x!==o);v&&E({...v,columns:v.columns.map(x=>x.key===o?{...x,active:w}:x),preference:{...v.preference,usedefault:!1,columns:N}}),ye({usedefault:!1,columns:N,widths:ft(b)},!0)},Rt=()=>{ue.current!==null&&(window.clearTimeout(ue.current),ue.current=null);let o=rt.current;rt.current=null,o!==null&&ye(wt(Ve,ge,o),!1),_e.current?.(),_e.current=null};Ae.current=Rt;let Tn=(o,w,N=!1)=>{let x={...b};if(w===null?delete x[o]:x[o]=w,T(x),rt.current=x,!N){Rt();return}ue.current!==null&&window.clearTimeout(ue.current),_e.current||(_e.current=J("local_evidencehub/hub:width")),ue.current=window.setTimeout(()=>Ae.current(),Br)},In=o=>{let w=new Set(o.columns);v&&E({...v,columns:v.columns.map(N=>N.fixed?N:{...N,active:w.has(N.key)}),presets:v.presets.map(N=>({...N,active:N.name===o.name})),preference:{...v.preference,usedefault:!1,columns:o.columns}}),ye({usedefault:!1,columns:o.columns,widths:ft(b)},!0)},_n=o=>{let N=(v?.sort??"")===o&&v?.direction==="asc"?"desc":"asc";X({sort:o,direction:N,page:0},!1)},Nt=o=>{let w=J("local_evidencehub/hub:inspect");$t(o,e.contextid,e.templateid).finally(w)},ke=Nn(()=>(v?.rows??[]).map(o=>({...o,id:Fe(o.userid,o.competencyid)})),[v,Fe]),Ct=Mn&&e.exportform!==null,ot=new Set(ke.filter(o=>!o.selectable).map(o=>o.id)),Re=ke.filter(o=>I.has(o.id)),An=(o,w)=>D(N=>{let x=new Set(N);return w&&!ot.has(o)?x.add(o):x.delete(o),x}),Dn=o=>D(()=>o?new Set(ke.filter(w=>!ot.has(w.id)).map(w=>w.id)):new Set),xt=Ct?{selected:I,locked:ot,label:o=>P(t.selectrow,o.drawertitle),pagelabel:t.selectpage,onSelect:An,onSelectPage:Dn}:void 0,Fn=()=>{e.exportform===null||Re.length===0||Q||(Ie(!0),De.current=window.setTimeout(()=>{De.current=null,Ie(!1)},Wr),fn({...e.exportform,contextid:e.contextid,templateid:e.templateid,suspended:kt,pairs:Re.map(o=>({userid:o.userid,competencyid:o.competencyid}))}))},Hn=le.some(o=>o.applied)||m.search!==""||m.scope!=="all",Et=He.filter(o=>o.key.startsWith("identity:")),St=He.filter(o=>o.key==="worker"||o.key==="competency"||!o.fixed&&o.active),Mt=l==="cards"||l==="auto"&&c,Pt=null;v!==null&&(Pt=Mt?ce(hn,{labels:t,columns:St,identity:Et,rows:ke,single:c,selection:xt,onInspect:Nt}):ce(yn,{labels:t,columns:St,identity:Et,rows:ke,widths:b,sort:v.sort,direction:v.direction,selection:xt,onInspect:Nt,onSort:_n,onWidth:Tn,onWidthCommit:()=>Ae.current()}));let Vn=Kr.map(o=>({key:o,title:o==="worker"?t.worker:"",sortable:!1,default:!0,active:!0,fixed:o==="worker"})),On=new Set(Re.map(o=>o.userid)).size;return Ur("div",{className:"local-evidencehub-rview-root",ref:s,"data-region":"local-evidencehub-hubview",children:[ce(Gt,{labels:t,summary:q,loading:G,error:ne,canfilter:v!==null,onStatus:o=>yt(on,String(o)),onCompetency:o=>yt(Ge,String(o)),onRetry:()=>we(i.current)}),ce(cn,{labels:t,narrow:c,scope:m.scope,summary:q,total:v===null?null:v.total,filters:le,columns:He.filter(o=>!o.key.startsWith("identity:")),presets:v?.presets??[],usedefault:Ve,haswidths:Object.keys(b).length>0,suspended:kt,view:l,search:f,searchmin:e.searchmin,searching:L,matrixurl:e.matrixurl,tableurl:e.tableurl,onScope:o=>X({scope:o,page:0},!0),onSearch:Sn,onFilter:(o,w)=>fe(()=>Ze(n,dt(le,o,w))),onRemoveFilter:o=>fe(()=>Ze(n,ut(le,o))),onClearFilters:()=>fe(()=>pt(n)),onClearAll:()=>{A(""),i.current={...i.current,search:""},le.some(o=>o.applied)?fe(()=>pt(n)):X({page:0},!0)},onAccounts:Pn,onColumn:Ln,onPreset:In,onDefaultColumns:()=>{T({}),v&&E({...v,preference:{...v.preference,usedefault:!0,columns:[],widths:[]}}),ye({usedefault:!0,columns:[],widths:[]},!0)},onResetWidths:()=>{T({}),ye(wt(Ve,ge,{}),!1)},onView:En}),ce(Bt,{labels:t,loading:L,loaded:v!==null,error:oe,total:v?.total??0,filtered:Hn,cards:Mt,narrow:c,skeleton:{columns:Vn,lead:"worker",widths:b,sizing:Qe},dock:Ct&&Re.length>0?ce(wn,{labels:t,count:Re.length,workers:On,posting:Q,onExport:Fn}):null,onRetry:()=>de(i.current,!0),children:Pt}),v!==null&&v.total>0?ce(At,{labels:t,page:m.page,perpage:m.perpage,perpages:e.perpages,total:v.total,shown:v.rows.length,narrow:c,onPage:o=>X({page:o},!1),onPerpage:o=>X({perpage:o,page:0},!1)}):null]})},xn=$r;import{jsx as qr}from"react/jsx-runtime";var Gr=e=>e.mount==="hub"?qr(xn,{...e}):null,Oa=Gr;export{Oa as default};
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
 * reads "–".
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
 * Automatic, Table or Cards, then the matrix (in the same scope) and core's table view. No Download yet (stage E4) and no
 * More menu: the dossiers are the dock's (the selection variant).
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
 * The form carries what that branch reads today, under its own names: `contextid`, `action=exportdossiers`,
 * `sesskey`, the filter's `templateid` with `statusfilter=1` (every status: the scope narrows the list, never
 * the selection's check) and `suspended` as the reader's preference lists them, and one `pairs[]` per
 * selected row, `<userid>:<competencyid>` (dossier_export::posted_workers() reads the workers out of them).
 * The server then requires the POST and the sesskey, `exportdossier` in the context, at most the configured
 * number of workers, and keeps only the posted workers the hub would list to this reader under that filter
 * (dossier_export::selection()): what the client selected decides nothing on the server. A small export
 * answers with the ZIP, which the browser saves while the page stays; a larger one redirects back to the hub
 * with a notification.
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
