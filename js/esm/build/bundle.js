import{useCallback as tt,useEffect as nt,useMemo as Sn,useRef as A,useState as V}from"react";import{useEffect as Un,useState as Gn}from"react";var qn=576,At=e=>{let[t,n]=Gn(!1);return Un(()=>{let r=e.current;if(!r||typeof ResizeObserver>"u")return;let l=new ResizeObserver(c=>{let i=c[0]?.contentRect.width??r.clientWidth;n(i>0&&i<qn)});return l.observe(r),()=>l.disconnect()},[e]),t},_t=e=>{try{let t=window.localStorage.getItem(e);return t==="table"||t==="cards"?t:"auto"}catch{return"auto"}},Ht=(e,t)=>{try{window.localStorage.setItem(e,t)}catch{}};import{useId as Qn}from"react";var P=(e,t)=>(e||"").split("{$a}").join(t),Ne=(e,t)=>Object.entries(t).reduce((n,[r,l])=>n.split(`{$a->${r}}`).join(l),e||""),Ke=(e,t,n,r)=>P(n===1&&e[`${t}_one`]?e[`${t}_one`]:e[t],r),R=e=>{let t=document.documentElement.lang||void 0;try{return new Intl.NumberFormat(t).format(e)}catch{return String(e)}};var Ce=e=>{let t=document.createElement("template");return t.innerHTML=e,(t.content.textContent||"").replace(/\s+/g," ").trim()};import{jsx as Z,jsxs as st}from"react/jsx-runtime";var Yn=(e,t)=>[...new Set([1,e-1,e,e+1,t])].filter(n=>n>=1&&n<=t).sort((n,r)=>n-r),Vt=({labels:e,page:t,perpage:n,perpages:r,total:l,shown:c,narrow:i,onPage:w,onPerpage:m})=>{let u=Qn(),a=Math.max(1,Math.ceil(l/n)),f=t+1,x=l>0?t*n+1:0,I=l>0?x+c-1:0,v=[];if(i)v=[Z("span",{className:"local-evidencehub-rview-pager-gap",children:Ne(e.pageof,{page:R(f),pages:R(a)})},"of")];else{let h=Yn(f,a);h.forEach((L,y)=>{y>0&&L-h[y-1]>1&&v.push(Z("span",{className:"local-evidencehub-rview-pager-gap","aria-hidden":"true",children:"\u2026"},`gap${L}`)),v.push(Z("button",{type:"button","aria-current":L===f?"page":void 0,onClick:()=>w(L-1),children:R(L)},L))})}return st("div",{className:"local-evidencehub-rview-foot",children:[Z("span",{className:"local-evidencehub-rview-num","data-region":"local-evidencehub-range",children:Ne(e.range,{from:R(x),to:R(I),total:R(l)})}),a>1?st("nav",{className:"local-evidencehub-rview-pager","aria-label":e.pages,children:[Z("button",{type:"button",disabled:f===1,onClick:()=>w(t-1),children:e.previous}),v,Z("button",{type:"button",disabled:f===a,onClick:()=>w(t+1),children:e.next})]}):null,st("span",{className:"local-evidencehub-rview-perpage",children:[Z("label",{htmlFor:u,children:e.perpage}),Z("select",{id:u,className:"form-select form-select-sm",value:n,onChange:h=>m(Number(h.target.value)),children:r.map(h=>Z("option",{value:h,children:R(h)},h))})]})]})};var j=e=>{let t=window.M?.util;t?.js_pending(e);let n=!0;return()=>{n&&(n=!1,t?.js_complete(e))}};import{useState as er}from"react";import{useEffect as Xn,useRef as $e}from"react";var Ot=(e,t,n,r,l)=>({defaultWidth:c=>e[c]??t,minimumWidth:c=>n[c]??r,maximum:l}),W=(e,t,n)=>Math.min(e.maximum,Math.max(e.minimumWidth(n),t[n]??e.defaultWidth(n)));import{jsx as N,jsxs as oe}from"react/jsx-runtime";var ze=16,Zn={ascending:"\u25B2",descending:"\u25BC",none:""},jn=(e,t)=>e?t==="asc"?"ascending":"descending":"none",Jn=({selection:e,rows:t})=>{let n=$e(null),r=t.filter(i=>!e.locked.has(i.id)),l=r.filter(i=>e.selected.has(i.id)).length,c=r.length>0&&l===r.length;return Xn(()=>{n.current&&(n.current.indeterminate=l>0&&!c)},[l,c]),oe("label",{className:"local-evidencehub-rview-pickwrap",children:[N("input",{ref:n,type:"checkbox",className:"local-evidencehub-rview-pick",checked:c,disabled:r.length===0,onChange:i=>e.onSelectPage(i.target.checked)}),N("span",{className:"visually-hidden",children:e.pagelabel})]})},ct=({selection:e,row:t})=>e.locked.has(t.id)?N("span",{className:"local-evidencehub-rview-pickwrap local-evidencehub-rview-pick--none"}):oe("label",{className:"local-evidencehub-rview-pickwrap",children:[N("input",{type:"checkbox",className:"local-evidencehub-rview-pick",checked:e.selected.has(t.id),onChange:n=>e.onSelect(t.id,n.target.checked)}),N("span",{className:"visually-hidden",children:e.label(t)})]}),Bt=({labels:e,columns:t,lead:n,rows:r,widths:l,sizing:c,sort:i,direction:w,selection:m,renderLead:u,renderCell:a,onSort:f,onWidth:x,onWidthCommit:I})=>{let v=$e(null),h=$e(null),L=$e(null),y=t.reduce((d,p)=>d+W(c,l,p.key),0),ae=d=>{d.currentTarget.classList.toggle("is-scrolled",d.currentTarget.scrollLeft>0)},U=(d,p)=>{let D=h.current?.querySelector(`col[data-col="${CSS.escape(d)}"]`);!D||!h.current||(D.style.width=`${p}px`,h.current.style.width=`${y-W(c,l,d)+p}px`)},q=(d,p)=>Math.round(Math.min(c.maximum,Math.max(c.minimumWidth(d),p))),be=(d,p)=>{if(d.button!==0)return;d.preventDefault(),d.currentTarget.setPointerCapture(d.pointerId),d.currentTarget.classList.add("is-active");let D=W(c,l,p);L.current={key:p,x:d.clientX,width:D,now:D}},G=d=>{let p=L.current;if(!p)return;let D=document.documentElement.dir==="rtl",_=(d.clientX-p.x)*(D?-1:1),s=q(p.key,p.width+_);s!==p.now&&(U(p.key,s),p.now=s)},ee=d=>{let p=L.current;L.current=null,d.currentTarget.classList.remove("is-active"),p&&p.now!==p.width&&x(p.key,p.now)},te=(d,p)=>{let D=document.documentElement.dir==="rtl",_=0;if(d.key==="ArrowRight")_=D?-ze:ze;else if(d.key==="ArrowLeft")_=D?ze:-ze;else if(d.key==="Home"){d.preventDefault(),x(p,null,!0);return}if(_===0)return;d.preventDefault();let s=q(p,W(c,l,p)+_);s!==W(c,l,p)&&x(p,s,!0)};return N("div",{className:"local-evidencehub-rview-gridframe",ref:v,tabIndex:0,role:"region","aria-label":e.grid,onScroll:ae,children:oe("table",{className:"local-evidencehub-rview-grid",ref:h,style:{width:`${y}px`},children:[N("caption",{className:"visually-hidden",children:e.caption}),N("colgroup",{children:t.map(d=>N("col",{"data-col":d.key,style:{width:`${W(c,l,d.key)}px`}},d.key))}),N("thead",{children:N("tr",{children:t.map(d=>{let p=jn(i===d.key,w),D=N("span",{className:"local-evidencehub-rview-th-title",children:d.title}),_=d.sortable?oe("button",{type:"button",className:"local-evidencehub-rview-sortbtn",onClick:()=>f(d.key),children:[D,N("span",{className:"local-evidencehub-rview-arrow","aria-hidden":"true",children:Zn[p]})]}):N("span",{className:"local-evidencehub-rview-plain",children:D});return oe("th",{scope:"col",className:d.key===n?"local-evidencehub-rview-fixed":void 0,"aria-sort":d.sortable?p:void 0,title:d.title,"data-column":d.key,children:[m&&d.key===n?oe("div",{className:"local-evidencehub-rview-lead",children:[N(Jn,{selection:m,rows:r}),_]}):_,N("span",{className:"local-evidencehub-rview-resize",role:"separator","aria-orientation":"vertical","aria-label":P(e.resize,d.title),"aria-valuemin":c.minimumWidth(d.key),"aria-valuemax":c.maximum,"aria-valuenow":W(c,l,d.key),tabIndex:0,onPointerDown:s=>be(s,d.key),onPointerMove:G,onPointerUp:ee,onPointerCancel:ee,onDoubleClick:()=>{W(c,l,d.key)!==c.defaultWidth(d.key)&&x(d.key,null)},onKeyDown:s=>te(s,d.key),onBlur:I})]},d.key)})})}),N("tbody",{children:r.map(d=>N("tr",{"data-record":d.id,className:m?.selected.has(d.id)?"local-evidencehub-rview-row--selected":void 0,children:t.map(p=>p.key===n?N("th",{scope:"row",className:"local-evidencehub-rview-fixed",children:m?oe("div",{className:"local-evidencehub-rview-lead",children:[N(ct,{selection:m,row:d}),u(d,p)]}):u(d,p)},p.key):N("td",{children:a(d,p)},p.key))},d.id))})]})})},Wt=({columns:e,lead:t,widths:n,sizing:r})=>{let l=e.reduce((i,w)=>i+W(r,n,w.key),0),c=["local-evidencehub-rview-skb--w80","local-evidencehub-rview-skb--w60","local-evidencehub-rview-skb--w40"];return N("div",{className:"local-evidencehub-rview-gridframe","aria-hidden":"true",children:oe("table",{className:"local-evidencehub-rview-grid",style:{width:`${l}px`},children:[N("colgroup",{children:e.map(i=>N("col",{style:{width:`${W(r,n,i.key)}px`}},i.key))}),N("thead",{children:N("tr",{children:e.map(i=>N("th",{className:i.key===t?"local-evidencehub-rview-fixed":void 0,children:N("span",{className:"local-evidencehub-rview-plain",children:N("span",{className:"local-evidencehub-rview-th-title",children:i.title})})},i.key))})}),N("tbody",{children:Array.from({length:8},(i,w)=>N("tr",{className:"local-evidencehub-rview-sk",children:e.map((m,u)=>N("td",{className:m.key===t?"local-evidencehub-rview-fixed":void 0,children:N("span",{className:"local-evidencehub-rview-skb "+c[(w+u)%3]})},m.key))},w))})]})})};import{jsx as B,jsxs as xe}from"react/jsx-runtime";var tr=({labels:e,row:t,single:n,selection:r,renderCard:l})=>{let[c,i]=er(!1),w=l(t),m=!n||c,u=r?.selected.has(t.id)??!1;return xe("article",{className:"local-evidencehub-rview-card"+(u?" local-evidencehub-rview-card--selected":""),role:"listitem","data-record":t.id,children:[xe("div",{className:"local-evidencehub-rview-card-l1",children:[r?B(ct,{selection:r,row:t}):null,w.head]}),w.lines,w.fields.length>0?xe("div",{className:"local-evidencehub-rview-card-more",children:[n?B("button",{type:"button",className:"btn btn-link btn-sm local-evidencehub-rview-linkbtn","aria-expanded":c,onClick:()=>i(!c),children:c?e.less:e.more}):null,m?B("dl",{className:"local-evidencehub-rview-card-list",children:w.fields.map(a=>xe("div",{className:"local-evidencehub-rview-card-item",children:[B("dt",{children:a.title}),B("dd",{children:a.content})]},a.key))}):null]}):null]})},Kt=({labels:e,rows:t,single:n,selection:r,renderCard:l})=>B("div",{className:"local-evidencehub-rview-cards"+(n?" local-evidencehub-rview-cards--single":""),role:"list",children:t.map(c=>B(tr,{labels:e,row:c,single:n,selection:r,renderCard:l},c.id))}),zt=({single:e})=>B("div",{className:"local-evidencehub-rview-cards"+(e?" local-evidencehub-rview-cards--single":""),"aria-hidden":"true",children:Array.from({length:e?4:6},(t,n)=>xe("div",{className:"local-evidencehub-rview-card local-evidencehub-rview-sk",children:[B("span",{className:"local-evidencehub-rview-skb local-evidencehub-rview-skb--w60"}),B("span",{className:"local-evidencehub-rview-skb local-evidencehub-rview-skb--w80"}),B("span",{className:"local-evidencehub-rview-skb local-evidencehub-rview-skb--w60"}),B("span",{className:"local-evidencehub-rview-skb local-evidencehub-rview-skb--w40"})]},n))});import{Fragment as Ut,jsx as J,jsxs as ve}from"react/jsx-runtime";var nr=({labels:e,loaded:t,error:n,total:r,filtered:l,cards:c,narrow:i,skeleton:w,children:m,onRetry:u})=>{let a=ve("p",{className:"local-evidencehub-rview-error",children:[e.loaderror," ",J("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:u,children:e.retry})]});return!t&&n?a:t?r===0?ve("div",{className:"local-evidencehub-rview-empty",children:[J("p",{className:"local-evidencehub-rview-empty-title",children:l?e.nomatch:e.empty}),l?J("p",{className:"local-evidencehub-rview-empty-text",children:e.nomatchhelp}):null]}):ve("div",{className:"local-evidencehub-rview-dim",children:[n?a:null,m,c?null:J("p",{className:"local-evidencehub-rview-hint",children:e.scrollhint})]}):ve(Ut,{children:[c?J(zt,{single:i}):J(Wt,{...w}),J("span",{className:"visually-hidden",role:"status",children:e.loading})]})},$t=e=>ve(Ut,{children:[ve("section",{className:"local-evidencehub-rview-region","aria-label":e.labels.caption,"aria-busy":e.loading,"data-region":"local-evidencehub-rows",children:[e.loading&&e.loaded?J("div",{className:"local-evidencehub-rview-progress"}):null,J(nr,{...e})]}),e.dock??null]});import{requireAsync as Gt}from"@moodle/lms/core/amd";var qt=async e=>(await Gt("core/first"),Gt(e)),Qt=async(e,t,n)=>{try{await(await qt("local_evidencehub/drawer")).open(e,t,n)}catch(r){try{(await qt("core/notification")).exception(r)}catch{window.console.error(r)}}};import{Fragment as ar,jsx as k,jsxs as H}from"react/jsx-runtime";var rr="\u2013",Ue=({label:e,value:t,sub:n})=>H("div",{className:"local-evidencehub-rview-tile",children:[k("div",{className:"local-evidencehub-rview-tile-label",children:e}),k("div",{className:"local-evidencehub-rview-tile-value",children:t}),k("div",{className:"local-evidencehub-rview-tile-sub",children:n})]}),Yt=({label:e,share:t,figure:n,title:r,disabled:l,onPress:c})=>k("li",{className:"local-evidencehub-rview-hitem",children:H("button",{type:"button",className:"local-evidencehub-rview-hrow",title:r,disabled:l,onClick:c,children:[k("span",{className:"local-evidencehub-rview-hrow-label",children:e}),k("span",{className:"local-evidencehub-rview-track","aria-hidden":"true",children:k("span",{className:"local-evidencehub-rview-fill",style:{width:`${Math.round(Math.min(1,Math.max(0,t))*100)}%`}})}),k("span",{className:"local-evidencehub-rview-hrow-n",children:n})]})}),or=()=>H("div",{"aria-hidden":"true",children:[k("div",{className:"local-evidencehub-rview-band",children:Array.from({length:4},(e,t)=>H("div",{className:"local-evidencehub-rview-tile local-evidencehub-rview-sk",children:[k("span",{className:"local-evidencehub-rview-skb local-evidencehub-rview-skb--w60"}),k("span",{className:"local-evidencehub-rview-skb local-evidencehub-rview-skb--big"}),k("span",{className:"local-evidencehub-rview-skb local-evidencehub-rview-skb--w80"})]},t))}),k("div",{className:"local-evidencehub-rview-dist",children:[0,1].map(e=>H("div",{className:"local-evidencehub-rview-panel local-evidencehub-rview-sk",children:[k("span",{className:"local-evidencehub-rview-skb local-evidencehub-rview-skb--w40"}),k("span",{className:"local-evidencehub-rview-skb"}),k("span",{className:"local-evidencehub-rview-skb"}),k("span",{className:"local-evidencehub-rview-skb"})]},e))})]}),Xt=e=>{let{labels:t,summary:n,loading:r,error:l,canfilter:c,chosenstatus:i,onStatus:w,onCompetency:m,onRetry:u}=e,a;if(n===null&&l)a=H("p",{className:"local-evidencehub-rview-error",children:[t.loaderror," ",k("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:u,children:t.retry})]});else if(n===null)a=H(ar,{children:[k(or,{}),k("span",{className:"visually-hidden",role:"status",children:t.loadband})]});else{let f=n.rate??null,x=n.distribution.filter(h=>h.count>0||String(h.status)===i),I=Math.max(1,...x.map(h=>h.count)),v=Math.max(1,...n.worst.map(h=>h.count));a=H("div",{className:"local-evidencehub-rview-dim",children:[l?H("p",{className:"local-evidencehub-rview-error",children:[t.loaderror," ",k("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:u,children:t.retry})]}):null,H("div",{className:"local-evidencehub-rview-band",children:[k(Ue,{label:t.workers,value:R(n.workers),sub:n.hidden>0?P(t.hiddenworkers,R(n.hidden)):t.workerssub}),k(Ue,{label:t.ready,value:f===null?rr:R(n.ready),sub:f===null?t.readynone:P(t.readysub,R(Math.round(f*100)))}),k(Ue,{label:t.attentiontile,value:R(n.attention),sub:P(t.attentionsub,R(n.pairs))}),k(Ue,{label:t.expiring,value:R(n.expiring),sub:t.expiringsub})]}),H("div",{className:"local-evidencehub-rview-dist",children:[H("div",{className:"local-evidencehub-rview-panel",children:[k("div",{className:"local-evidencehub-rview-panel-title",children:k("span",{children:t.bystatus})}),k("ul",{className:"local-evidencehub-rview-hlist",children:x.map(h=>k(Yt,{label:h.label,share:h.count/I,figure:R(h.count),title:t.showpairs,disabled:!c,onPress:()=>w(h.status)},h.status))})]}),H("div",{className:"local-evidencehub-rview-panel",children:[k("div",{className:"local-evidencehub-rview-panel-title",children:k("span",{children:t.worst})}),n.worst.length>0?k("ul",{className:"local-evidencehub-rview-hlist",children:n.worst.map(h=>k(Yt,{label:h.name,share:h.count/v,figure:R(h.count),title:t.showpairs,disabled:!c,onPress:()=>m(h.competencyid)},h.competencyid))}):k("p",{className:"local-evidencehub-rview-note",children:t.noworst})]})]})]})}return H("section",{className:"local-evidencehub-rview-region local-evidencehub-rview-bandregion","aria-label":t.bandlabel,"aria-busy":r,"data-region":"local-evidencehub-band",children:[r&&n!==null?k("div",{className:"local-evidencehub-rview-progress"}):null,a]})};import{useEffect as Qe,useId as pr,useRef as Ee,useState as an}from"react";import{useId as ir,useState as jt}from"react";import{jsx as Zt}from"react/jsx-runtime";var lr={filter:"M2 3h12l-4.5 5.5V13l-3 1.5V8.5L2 3z",columns:"M2 3h12v10H2zM6.5 3v10M10 3v10",table:"M2 3h12v10H2zM2 6.5h12M2 10h12M6 6.5V13",cards:"M2 3h5v4H2zM9 3h5v4H9zM2 9h5v4H2zM9 9h5v4H9z",auto:"M2 3h7v10H2zM11 3h3v4h-3zM11 9h3v4h-3z",download:"M8 2v8M4.5 7 8 10.5 11.5 7M3 13h10",search:"M7 12A5 5 0 1 0 7 2a5 5 0 0 0 0 10zM10.5 10.5 14 14",close:"M4 4l8 8M12 4l-8 8",eraser:"M9.5 2 14 6.5 7.5 13 3 8.5zM5.6 5.9l4.5 4.5M7.5 13H14",tick:"M3 8.5 6.5 12 13 4.5",chevron:"M4 6l4 4 4-4",reset:"M3 8a5 5 0 1 0 1.5-3.5M3 2.5V5h2.5",queue:"M3 4h10M3 8h10M3 12h6",matrix:"M2.5 2.5h3v3h-3zM6.5 2.5h3v3h-3zM10.5 2.5h3v3h-3zM2.5 6.5h3v3h-3zM6.5 6.5h3v3h-3zM10.5 6.5h3v3h-3zM2.5 10.5h3v3h-3zM6.5 10.5h3v3h-3zM10.5 10.5h3v3h-3z",lock:"M4.5 7.5h7v6h-7zM6 7.5V5.5a2 2 0 0 1 4 0v2"},M=({name:e,className:t})=>Zt("svg",{className:"local-evidencehub-rview-icon"+(t?" "+t:""),viewBox:"0 0 16 16",width:"16",height:"16",fill:"none",stroke:"currentColor",strokeWidth:"1.5",strokeLinecap:"round",strokeLinejoin:"round","aria-hidden":"true",focusable:"false",children:Zt("path",{d:lr[e]??""})});import{Fragment as vr,jsx as F,jsxs as K}from"react/jsx-runtime";var sr=8,ut=50,cr=(e,t)=>t===null?e.show:P(e.showcount,R(t)),Jt=({option:e})=>e.count>=0?F("small",{className:"local-evidencehub-rview-optcount",children:R(e.count)}):null,ur=(e,t)=>e.options.filter(n=>n.count!==0||n.value===t),dr=({labels:e,facet:t,shown:n,value:r,headid:l,onChange:c})=>{let[i,w]=jt(""),[m,u]=jt(""),a=i.trim().toLocaleLowerCase(),f=t.groupings.length>1?t.groupings:[],x=f.find(y=>String(y.id)===m)?.groups.map(String)??null,I=n.filter(y=>y.value===r),v=n.filter(y=>y.value!==r&&(a===""||y.label.toLocaleLowerCase().includes(a))&&(x===null||x.includes(y.value))),h=P(t.find.label,R(t.options.length)),L=f.length>0?e.moregroupsgrouping:e.moregroups;return K(vr,{children:[K("div",{className:"local-evidencehub-rview-find",children:[F("input",{type:"search",className:"form-control form-control-sm local-evidencehub-rview-input",placeholder:h,"aria-label":h,value:i,onChange:y=>w(y.target.value)}),f.length>0?K("select",{className:"form-select form-select-sm local-evidencehub-rview-grouping","aria-label":e.grouping,value:m,onChange:y=>u(y.target.value),children:[F("option",{value:"",children:e.allgroupings}),f.map(y=>F("option",{value:String(y.id),children:y.name},y.id))]}):null]}),K("div",{className:"local-evidencehub-rview-optlist",role:"group","aria-labelledby":l,children:[[...I,...v.slice(0,ut)].map(y=>K("button",{type:"button",className:"local-evidencehub-rview-optrow","aria-pressed":y.value===r,onClick:()=>c(t.name,y.value===r?"":y.value),children:[F("span",{className:"local-evidencehub-rview-box","aria-hidden":"true",children:F(M,{name:"tick"})}),F("span",{className:"local-evidencehub-rview-optrow-label",children:y.label})," ",F(Jt,{option:y})]},y.value)),v.length>ut?F("p",{className:"local-evidencehub-rview-note",children:P(L,R(v.length-ut))}):null,I.length===0&&v.length===0?F("p",{className:"local-evidencehub-rview-note",children:t.find.nomatch}):null]})]})},mr=({labels:e,facet:t,value:n,onChange:r})=>{let l=ir(),c=ur(t,n),i=[{value:"",label:e.any,count:-1},...c];if(t.options.length>sr)return K("div",{className:"local-evidencehub-rview-fgroup",children:[F("span",{className:"local-evidencehub-rview-fpanel-head",id:l,children:t.title}),F(dr,{labels:e,facet:t,shown:c,value:n,headid:l,onChange:r})]});let w=m=>{let u=i.findIndex(f=>f.value===n),a=-1;m.key==="ArrowRight"||m.key==="ArrowDown"?a=(u+1)%i.length:m.key==="ArrowLeft"||m.key==="ArrowUp"?a=(u-1+i.length)%i.length:m.key==="Home"?a=0:m.key==="End"&&(a=i.length-1),!(a<0)&&(m.preventDefault(),r(t.name,i[a].value),m.currentTarget.querySelectorAll('[role="radio"]')[a]?.focus())};return K("div",{className:"local-evidencehub-rview-fgroup",children:[F("span",{className:"local-evidencehub-rview-fpanel-head",id:l,children:t.title}),F("div",{className:"local-evidencehub-rview-fpanel-opts",role:"radiogroup","aria-labelledby":l,onKeyDown:w,children:i.map(m=>K("button",{type:"button",className:"local-evidencehub-rview-fpanel-opt",role:"radio","aria-checked":m.value===n,tabIndex:m.value===n?0:-1,onClick:()=>r(t.name,m.value),children:[m.label," ",F(Jt,{option:m})]},m.value))})]})},en=({labels:e,id:t,hidden:n,facets:r,values:l,total:c,note:i,onChange:w,onClear:m,onShow:u})=>K("div",{className:"local-evidencehub-rview-fpanel",id:t,role:"dialog","aria-label":e.filters,hidden:n,children:[K("div",{className:"local-evidencehub-rview-fpanel-body",children:[r.map(a=>F(mr,{labels:e,facet:a,value:l[a.name]??"",onChange:w},a.name)),i??null]}),K("div",{className:"local-evidencehub-rview-fpanel-foot",children:[F("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:m,children:e.clearfilters}),F("button",{type:"button",className:"btn btn-sm btn-primary",onClick:u,children:cr(e,c)})]})]});import{Fragment as br,jsx as Se,jsxs as tn}from"react/jsx-runtime";var Ge=e=>{if(!["ArrowDown","ArrowUp","Home","End"].includes(e.key))return;let t=Array.from(e.currentTarget.querySelectorAll('[role^="menuitem"]'));if(t.length===0)return;e.preventDefault();let n=t.indexOf(document.activeElement),r=0;e.key==="End"?r=t.length-1:e.key==="ArrowDown"?r=(n+1)%t.length:e.key==="ArrowUp"&&(r=(n-1+t.length)%t.length),t[r].focus()},nn=({heading:e,sets:t,onSet:n})=>t.length===0?null:tn(br,{children:[Se("div",{className:"local-evidencehub-rview-menu-head",role:"presentation",children:e}),t.map(r=>tn("button",{type:"button",className:"local-evidencehub-rview-menu-item",role:"menuitemcheckbox","aria-checked":r.active,tabIndex:-1,"data-preset":r.name,onClick:()=>{r.active||n(r.name)},children:[Se("span",{className:"local-evidencehub-rview-box","aria-hidden":"true",children:Se(M,{name:"tick"})}),Se("span",{className:"local-evidencehub-rview-menu-label",children:r.label})]},r.name)),Se("div",{className:"local-evidencehub-rview-menu-sep",role:"separator"})]});import{Fragment as hr,jsx as rn,jsxs as on}from"react/jsx-runtime";var qe=({label:e,value:t,options:n,small:r,onChange:l})=>{let c=i=>{let w=n.findIndex(a=>a.value===t),m=-1;if(i.key==="ArrowRight"||i.key==="ArrowDown"?m=(w+1)%n.length:i.key==="ArrowLeft"||i.key==="ArrowUp"?m=(w-1+n.length)%n.length:i.key==="Home"?m=0:i.key==="End"&&(m=n.length-1),m<0)return;i.preventDefault(),l(n[m].value),i.currentTarget.querySelectorAll('[role="radio"]')[m]?.focus()};return rn("div",{className:"local-evidencehub-rview-seg"+(r?" local-evidencehub-rview-seg--small":""),role:"radiogroup","aria-label":e,onKeyDown:c,children:n.map(i=>on("button",{type:"button",className:"local-evidencehub-rview-seg-cell",role:"radio","aria-checked":i.value===t,tabIndex:i.value===t?0:-1,onClick:()=>l(i.value),children:[i.label,i.count!==void 0?on(hr,{children:[" ",rn("small",{className:"local-evidencehub-rview-seg-count",children:i.count})]}):null]},i.value))})};import{Fragment as sn,jsx as b,jsxs as E}from"react/jsx-runtime";var wr=({links:e,tableurl:t,label:n})=>t===""&&e.length===0?null:E(sn,{children:[b("div",{className:"local-evidencehub-rview-menu-sep",role:"separator"}),e.map(r=>E("a",{className:"local-evidencehub-rview-menu-item",role:"menuitem",tabIndex:-1,href:r.href,children:[b(M,{name:r.icon}),b("span",{className:"local-evidencehub-rview-menu-label",children:r.label})]},r.key)),t!==""?E("a",{className:"local-evidencehub-rview-menu-item",role:"menuitem",tabIndex:-1,href:t,children:[b(M,{name:"table"}),b("span",{className:"local-evidencehub-rview-menu-label",children:n})]}):null]}),ln=e=>{let{labels:t,chips:n,columns:r}=e,l=pr(),c=Ee(null),i=Ee(null),w=Ee(null),m=Ee(!1),[u,a]=an(null),[f,x]=an(e.search!==""),I=Ee({});Qe(()=>{if(u===null)return;let s=T=>{c.current&&!c.current.contains(T.target)&&a(null)};return document.addEventListener("mousedown",s),()=>document.removeEventListener("mousedown",s)},[u]),Qe(()=>{if(u===null||u==="filters")return;let s=c.current?.querySelector(`#${CSS.escape(l+u)}`);(s?.querySelector('[aria-checked="true"]:not([aria-disabled="true"])')??s?.querySelector('[role^="menuitem"]:not([aria-disabled="true"])'))?.focus()},[u,l]),Qe(()=>{f?i.current?.focus():m.current&&(m.current=!1,w.current?.focus())},[f]),Qe(()=>{let s=T=>{if(T.key!=="/"||T.ctrlKey||T.metaKey||T.altKey||T.defaultPrevented)return;let O=document.activeElement;if(O&&(O.isContentEditable||O.closest("input, textarea, select")))return;let ne=c.current?.getBoundingClientRect();!ne||ne.width===0||ne.bottom<=0||ne.top>=window.innerHeight||(T.preventDefault(),f?i.current?.focus():(a(null),x(!0)))};return document.addEventListener("keydown",s),()=>document.removeEventListener("keydown",s)},[f]);let v=s=>a(u===s?null:s),h=s=>{m.current=s,x(!1)},L=s=>{if(!(s.key!=="Escape"||s.defaultPrevented))if(u!==null){s.preventDefault();let T=I.current[u];a(null),T?.focus()}else f&&(s.preventDefault(),h(!0))},y=e.search.trim().length>=e.searchmin,ae=y&&!f,U=n.length+(ae?1:0),q=e.total===null?"\u2026":Ke(t,"count",e.total,R(e.total)),be=e.total!==null&&e.narrow?E(sn,{children:[b("span",{"aria-hidden":"true",children:R(e.total)}),b("span",{className:"visually-hidden",children:q})]}):q,G=(s,T,O,ne)=>E("button",{type:"button",className:"local-evidencehub-rview-iconbtn","aria-label":O,"data-local-evidencehub-tip":O,"aria-haspopup":s==="filters"?"dialog":"menu","aria-expanded":u===s,"aria-controls":l+s,ref:Fe=>{I.current[s??""]=Fe},onClick:()=>v(s),children:[b(M,{name:T}),ne]}),ee={auto:"auto",table:"table",cards:"cards"}[e.view],te=b(M,{name:"chevron",className:"local-evidencehub-rview-chevron"}),d=n.length>0?P(t.filterson,R(n.length)):t.filters,p=n.length>0?b("span",{className:"local-evidencehub-rview-badge","aria-hidden":"true",children:R(n.length)}):null,D=[["auto",t.viewauto,t.viewautohint],["table",t.table,""],["cards",t.cards,""]].filter(([s])=>e.views.includes(s)),_="local-evidencehub-rview-bar"+(e.narrow?" local-evidencehub-rview-bar--narrow":"")+(f?" local-evidencehub-rview-bar--searching":"");return E("div",{className:_,"data-region":"local-evidencehub-bar",ref:c,onKeyDown:L,children:[b(qe,{label:t.scope,value:e.scope,options:e.scopes,onChange:e.onScope}),b("span",{className:"local-evidencehub-rview-bar-spacer"}),E("div",{className:"local-evidencehub-rview-bar-controls",children:[E("div",{className:"local-evidencehub-rview-bar-cluster",children:[b("span",{className:"local-evidencehub-rview-bar-count","data-region":"local-evidencehub-total","aria-live":"polite",children:be}),E("div",{className:"local-evidencehub-rview-capsule",children:[G("filters","filter",d,p),E("div",{className:"local-evidencehub-rview-anchor",children:[G("columns","columns",t.columns,te),E("div",{className:"local-evidencehub-rview-menu",id:l+"columns",role:"menu","aria-label":t.columns,hidden:u!=="columns",onKeyDown:Ge,children:[b(nn,{heading:t.presets,sets:e.sets,onSet:e.onSet}),r.map(s=>s.fixed?E("div",{className:"local-evidencehub-rview-menu-item",role:"menuitemcheckbox","aria-checked":"true","aria-disabled":"true",tabIndex:-1,children:[b("span",{className:"local-evidencehub-rview-box","aria-hidden":"true",children:b(M,{name:"tick"})}),b("span",{className:"local-evidencehub-rview-menu-label",children:s.title}),b("span",{className:"local-evidencehub-rview-menu-hint",children:t.always})]},s.key):E("button",{type:"button",className:"local-evidencehub-rview-menu-item",role:"menuitemcheckbox","aria-checked":s.active,tabIndex:-1,onClick:()=>e.onColumn(s.key,!s.active),children:[b("span",{className:"local-evidencehub-rview-box","aria-hidden":"true",children:b(M,{name:"tick"})}),b("span",{className:"local-evidencehub-rview-menu-label",children:s.title})]},s.key)),b("div",{className:"local-evidencehub-rview-menu-sep",role:"separator"}),E("button",{type:"button",className:"local-evidencehub-rview-menu-item",role:"menuitem",tabIndex:-1,"aria-disabled":e.usedefault,onClick:()=>{e.usedefault||e.onDefaultColumns()},children:[b(M,{name:"columns"}),b("span",{className:"local-evidencehub-rview-menu-label",children:t.defaultcolumns})]}),E("button",{type:"button",className:"local-evidencehub-rview-menu-item",role:"menuitem",tabIndex:-1,"aria-disabled":!e.haswidths,onClick:()=>{e.haswidths&&e.onResetWidths()},children:[b(M,{name:"reset"}),b("span",{className:"local-evidencehub-rview-menu-label",children:t.resetwidths})]})]})]}),E("div",{className:"local-evidencehub-rview-anchor",children:[G("view",ee,t.view,te),E("div",{className:"local-evidencehub-rview-menu",id:l+"view",role:"menu","aria-label":t.view,hidden:u!=="view",onKeyDown:Ge,children:[D.map(([s,T,O])=>E("button",{type:"button",className:"local-evidencehub-rview-menu-item",role:"menuitemradio","aria-checked":e.view===s,tabIndex:-1,onClick:()=>{e.onView(s),a(null),I.current.view?.focus()},children:[b(M,{name:s}),b("span",{className:"local-evidencehub-rview-menu-label",children:T}),O!==""?b("span",{className:"local-evidencehub-rview-menu-hint",children:O}):null,b(M,{name:"tick",className:"local-evidencehub-rview-tick"})]},s)),b(wr,{links:e.viewlinks??[],tableurl:e.tableurl,label:t.coretable})]})]}),e.downloads.length>0?E("div",{className:"local-evidencehub-rview-anchor",children:[G("download","download",t.download,te),b("div",{className:"local-evidencehub-rview-menu",id:l+"download",role:"menu","aria-label":t.download,hidden:u!=="download",onKeyDown:Ge,children:e.downloads.map(s=>E("a",{className:"local-evidencehub-rview-menu-item",role:"menuitem",tabIndex:-1,href:s.href,children:[b(M,{name:"download"}),b("span",{className:"local-evidencehub-rview-menu-label",children:s.label})]},s.key))})]}):null]})]}),b("div",{className:"local-evidencehub-rview-capsule local-evidencehub-rview-searchcap",children:f?E("span",{className:"local-evidencehub-rview-search",children:[e.searching&&y?b("span",{className:"local-evidencehub-rview-spin","aria-hidden":"true"}):b(M,{name:"search"}),b("input",{ref:i,type:"search","aria-label":t.search,placeholder:t.search,autoComplete:"off",value:e.search,onChange:s=>e.onSearch(s.target.value),onKeyDown:s=>{s.key==="Escape"&&(s.preventDefault(),h(!0))},onBlur:()=>{e.search===""&&h(!1)}}),e.search!==""?b("button",{type:"button",className:"local-evidencehub-rview-eraser","aria-label":t.cleartext,"data-local-evidencehub-tip":t.cleartext,onMouseDown:s=>s.preventDefault(),onClick:()=>{e.onSearch(""),i.current?.focus()},children:b(M,{name:"eraser"})}):null]}):b("button",{ref:w,type:"button",className:"local-evidencehub-rview-iconbtn","aria-label":t.search,"data-local-evidencehub-tip":t.search,onClick:()=>{a(null),x(!0)},children:b(M,{name:"search"})})}),b(en,{labels:t,id:l+"filters",hidden:u!=="filters",facets:e.facets,values:e.values,total:e.total,note:e.panelnote,onChange:e.onFacet,onClear:e.onClearFilters,onShow:()=>{a(null),I.current.filters?.focus()}})]}),E("div",{className:"local-evidencehub-rview-chips","data-region":"local-evidencehub-chips",hidden:U===0&&!e.aside,children:[n.map(s=>E("button",{type:"button",className:"local-evidencehub-rview-chip","aria-label":P(t.removefilter,s.text),onClick:()=>e.onRemoveChip(s.key),children:[b("span",{children:s.text}),b(M,{name:"close"})]},s.key)),ae?E("button",{type:"button",className:"local-evidencehub-rview-chip","aria-label":P(t.removefilter,P(t.searchchip,e.search.trim())),onClick:()=>e.onSearch(""),children:[b("span",{children:P(t.searchchip,e.search.trim())}),b(M,{name:"close"})]}):null,U>1?b("button",{type:"button",className:"local-evidencehub-rview-clearall",onClick:()=>{e.onClearAll(),x(!1)},children:t.clearall}):null,e.aside??null]})]})};var dt="validity:status",Ye="competency:competency",cn="user:cohort",mt=e=>e.facet!==""&&e.options.length>0,un=e=>{try{let t=JSON.parse(e.values||"{}");return t!==null&&typeof t=="object"?t:{}}catch{return{}}},fr=e=>e.reduce((t,n)=>({...t,...un(n)}),{}),Me=e=>{if(!e.applied||e.facet==="")return"";let t=un(e),n=t[`${e.name}_value`];return n==null||Number(t[`${e.name}_operator`]??0)!==1?"":String(n)},vt=(e,t)=>{let n=fr(e);for(let r of Object.keys(n))r.startsWith(`${t.name}_`)&&delete n[r];return n},bt=(e,t,n)=>{let r=vt(e,t);return r[`${t.name}_operator`]=n===""?0:1,r[`${t.name}_value`]=n,r},dn=e=>{let t=Me(e);return t===""?"":e.options.find(n=>n.value===t)?.label??""};import{Fragment as Sr,jsx as Pe,jsxs as ht}from"react/jsx-runtime";var gr=["auto","table","cards"],yr=(e,t)=>{switch(t){case"validity":return e.presetvalidity;case"evidence":return e.presetevidence;default:return t}},kr=(e,t)=>{let n={label:e.searchoptions,nomatch:e.nooptionmatch};return t.name===cn?n={label:e.searchcohorts,nomatch:e.nocohortmatch}:t.name===Ye&&(n={label:e.searchcompetencies,nomatch:e.nocompetencymatch}),{name:t.name,title:t.title,options:t.options,groupings:[],find:n}},Rr=(e,t)=>{let n=dn(t);return{key:t.name,text:n===""?t.title:Ne(e.filterchip,{title:t.title,value:n})}},Nr=(e,t)=>t==="all"?`${e}&scope=all`:e,Cr=(e,t,n,r)=>{let l=new URLSearchParams({scope:e});return t!==""&&l.set("search",t),n!==""&&(l.set("sort",n),l.set("dir",r==="desc"?"desc":"asc")),l.toString()},xr=({labels:e,suspended:t,onAccounts:n})=>ht("div",{className:"local-evidencehub-rview-fgroup","data-region":"local-evidencehub-accounts",children:[Pe("span",{className:"local-evidencehub-rview-fpanel-head",children:e.accounts}),Pe(qe,{label:e.accounts,small:!0,value:t?"all":"active",options:[{value:"active",label:e.activeonly},{value:"all",label:e.includesuspended}],onChange:r=>{r==="all"!==t&&n(r==="all")}})]}),mn=e=>{let{labels:t,filters:n,summary:r}=e,l=n.filter(mt),c=n.filter(a=>!mt(a)),i=a=>n.find(f=>f.name===a),w=Cr(e.scope,e.appliedsearch,e.sort,e.direction),m=e.downloadable&&e.downloadurl!==""?e.formats.map(a=>({key:a.value,label:a.label,href:`${e.downloadurl}&${w}&download=${encodeURIComponent(a.value)}`})):[],u=e.presets.map(a=>({name:a.name,label:yr(t,a.name),active:a.active}));return Pe(ln,{labels:t,narrow:e.narrow,scopes:[{value:"attention",label:t.attention,count:r===null?void 0:R(r.attention)},{value:"all",label:t.all,count:r===null?void 0:R(r.pairs)}],scope:e.scope,total:e.total,facets:l.map(a=>kr(t,a)),values:Object.fromEntries(l.map(a=>[a.name,Me(a)])),panelnote:ht(Sr,{children:[Pe(xr,{labels:t,suspended:e.suspended,onAccounts:e.onAccounts}),c.length>0?ht("p",{className:"local-evidencehub-rview-note",children:[t.morefiltershelp," ",Pe("a",{href:e.tableurl,children:t.morefilters})]}):null]}),chips:n.filter(a=>a.applied).map(a=>Rr(t,a)),columns:e.columns,sets:u,usedefault:e.usedefault,haswidths:e.haswidths,view:e.view,views:gr,tableurl:e.tableurl,viewlinks:e.matrixurl===""?[]:[{key:"matrix",label:t.matrix,href:Nr(e.matrixurl,e.scope),icon:"matrix"}],downloads:m,search:e.search,searchmin:e.searchmin,searching:e.searching,onScope:a=>e.onScope(a==="all"?"all":"attention"),onSearch:e.onSearch,onFacet:(a,f)=>{let x=i(a);x&&e.onFilter(x,f)},onRemoveChip:a=>{let f=i(a);f&&e.onRemoveFilter(f)},onClearFilters:e.onClearFilters,onClearAll:e.onClearAll,onColumn:e.onColumn,onSet:a=>{let f=e.presets.find(x=>x.name===a);f&&e.onPreset(f)},onDefaultColumns:e.onDefaultColumns,onResetWidths:e.onResetWidths,onView:e.onView})};import{Fragment as Er,jsx as vn}from"react/jsx-runtime";var Xe=({parts:e})=>vn(Er,{children:e.map((t,n)=>vn("span",{className:n===0?"local-evidencehub-rview-c":"local-evidencehub-rview-sub",title:Ce(t),dangerouslySetInnerHTML:{__html:t}},n))});import{Fragment as Wr,jsx as ie,jsxs as Br}from"react/jsx-runtime";var Mr={worker:260,competency:240,status:180,expiry:132,count:104,type:200,issuer:200,competencyidnumber:150,framework:200,issuedate:132,expirydate:132,verification:160,changed:160},Pr=180,Lr={worker:200,expiry:104,issuedate:104,expirydate:104,changed:104},Tr=80,Ir=800,Ze=Ot(Mr,Pr,Lr,Tr,Ir),bn=-1,Dr=0,Fr=1,Ar=2,_r=3,Hr=4,Vr=5,Or=e=>{switch(e){case Ar:return"success";case _r:return"info";case Hr:return"warning";case Vr:return"danger";case Fr:case Dr:case bn:default:return"neutral"}},z=(e,t)=>e.cells.find(n=>n.key===t)?.html??"",pt=({row:e})=>{let t=z(e,"status");return t===""?null:Br("span",{className:`local-evidencehub-rview-status local-evidencehub-rview-status--${Or(e.status)}`,title:Ce(t),children:[e.status===bn?ie(M,{name:"lock"}):null,ie("span",{dangerouslySetInnerHTML:{__html:t}})]})},hn=({row:e,column:t})=>t==="status"?ie(pt,{row:e}):ie(Xe,{parts:[z(e,t)]}),je=({row:e,label:t,onInspect:n})=>e.inspect?ie("button",{type:"button",className:"btn btn-sm btn-outline-secondary local-evidencehub-rview-inspect","aria-label":e.inspectlabel,"data-userid":e.userid,"data-competencyid":e.competencyid,"data-title":e.drawertitle,onClick:r=>n(r.currentTarget),children:t}):null,Je=({row:e,identity:t})=>ie(Wr,{children:t.map(n=>{let r=z(e,n.key);return r===""?null:ie("span",{className:"local-evidencehub-rview-sub",title:Ce(r),dangerouslySetInnerHTML:{__html:r}},n.key)})});import{Fragment as wn,jsx as $,jsxs as Le}from"react/jsx-runtime";var Kr=["worker","competency","status","expiry","type"],pn=({row:e,column:t})=>Le("div",{className:"local-evidencehub-rview-card-line",children:[$("span",{className:"local-evidencehub-rview-card-key",children:t.title}),$("span",{className:"local-evidencehub-rview-inline",dangerouslySetInnerHTML:{__html:z(e,t.key)}})]}),zr=(e,t,n,r,l)=>{let c=u=>t.find(a=>a.key===u),i=c("expiry"),w=c("type"),m=t.filter(u=>!Kr.includes(u.key)&&z(r,u.key).trim()!=="");return{head:Le(wn,{children:[Le("div",{className:"local-evidencehub-rview-card-who",children:[$("span",{className:"local-evidencehub-rview-card-name",dangerouslySetInnerHTML:{__html:z(r,"worker")}}),$(Je,{row:r,identity:n})]}),$(je,{row:r,label:e.inspect,onInspect:l})]}),lines:Le(wn,{children:[Le("div",{className:"local-evidencehub-rview-card-line",children:[$("span",{className:"local-evidencehub-rview-inline",dangerouslySetInnerHTML:{__html:z(r,"competency")}}),c("status")?$(pt,{row:r}):null]}),i?$(pn,{row:r,column:i}):null,w?$(pn,{row:r,column:w}):null]}),fields:m.map(u=>({key:u.key,title:u.title,content:$(Xe,{parts:[z(r,u.key)]})}))}},fn=({labels:e,columns:t,identity:n,rows:r,single:l,selection:c,onInspect:i})=>$(Kt,{labels:e,rows:r,single:l,selection:c,renderCard:w=>zr(e,t,n,w,i)});import{jsx as gn,jsxs as wt}from"react/jsx-runtime";var yn=({labels:e,count:t,workers:n,posting:r,onExport:l})=>wt("div",{className:"local-evidencehub-rview-dock",role:"region","aria-label":e.dock,"data-region":"local-evidencehub-dock",children:[wt("span",{className:"local-evidencehub-rview-dockcount","aria-live":"polite",children:[P(e.selected,R(t)),wt("span",{className:"local-evidencehub-rview-dockworkers",children:[" \xB7 ",Ke(e,"selectedworkers",n,R(n))]})]}),gn("div",{className:"local-evidencehub-rview-dockactions",children:gn("button",{type:"button",className:"btn btn-primary",disabled:r,onClick:l,children:e.exportdossiers})})]});var Te=(e,t,n)=>{let r=document.createElement("input");r.type="hidden",r.name=t,r.value=n,e.appendChild(r)},kn=e=>{let t=document.createElement("form");t.method="post",t.action=e.action,t.hidden=!0,Te(t,"contextid",String(e.contextid)),Te(t,"action","exportdossiers"),Te(t,"sesskey",e.sesskey),Te(t,"templateid",String(e.templateid)),e.pairs.forEach(n=>Te(t,"pairs[]",`${n.userid}:${n.competencyid}`)),document.body.appendChild(t),t.submit(),t.remove()};import{jsx as Ie,jsxs as Rn}from"react/jsx-runtime";var Nn=({labels:e,columns:t,identity:n,rows:r,widths:l,sort:c,direction:i,selection:w,onInspect:m,onSort:u,onWidth:a,onWidthCommit:f})=>Ie(Bt,{labels:e,columns:t,lead:"worker",rows:r,widths:l,sizing:Ze,sort:c,direction:i,selection:w,renderLead:x=>Rn("div",{className:"local-evidencehub-rview-who",children:[Rn("div",{className:"local-evidencehub-rview-who-text",children:[Ie("span",{className:"local-evidencehub-rview-c",dangerouslySetInnerHTML:{__html:z(x,"worker")}}),Ie(Je,{row:x,identity:n})]}),Ie(je,{row:x,label:e.inspect,onInspect:m})]}),renderCell:(x,I)=>Ie(hn,{row:x,column:I.key}),onSort:u,onWidth:a,onWidthCommit:f});import{fetchOne as De}from"@moodle/lms/core/ajax";var Cn=(e,t,n)=>De({methodname:"local_evidencehub_report_rows",args:{...e,...t,facets:n}}),xn=(e,t,n)=>De({methodname:"local_evidencehub_report_summary",args:{...e,scope:t,search:n}}),ft=(e,t,n)=>De({methodname:"local_evidencehub_set_report_preference",args:{...e,usedefault:t.usedefault,columns:t.columns,widths:t.widths,suspended:n===null?-1:Number(n)}}),et=(e,t)=>De({methodname:"core_reportbuilder_set_filters",args:{reportid:e,parameters:"{}",values:JSON.stringify(t)}}),gt=e=>De({methodname:"core_reportbuilder_filters_reset",args:{reportid:e,parameters:"{}"}});import{jsx as se,jsxs as Zr}from"react/jsx-runtime";var $r=300,Ur=500,Gr=3e3,En="local_evidencehub_hub_view",qr=["worker","competency","status","expiry","count","type","issuer"],kt=e=>Object.entries(e).map(([t,n])=>({key:t,width:n})),yt=(e,t,n)=>{let r=kt(n);return e&&r.length===0?{usedefault:!0,columns:[],widths:[]}:{usedefault:!1,columns:t,widths:r}},Qr=e=>e===null?{allcolumns:[],usedefault:!0,suspended:!1,candecide:!1}:{allcolumns:e.columns,usedefault:e.preference.usedefault,suspended:e.preference.suspended,candecide:e.candecide},Yr=(e,t)=>{let n=t.find(r=>r.name===dt&&r.applied);return{sort:e?.sort??"",direction:e?.direction??"",downloadable:e!==null&&e.total>0,chosenstatus:n?Me(n):""}},Xr=e=>{let{labels:t,reportid:n}=e,r=Sn(()=>({contextid:e.contextid,templateid:e.templateid}),[e.contextid,e.templateid]),l=A(null),c=At(l),[i,w]=V(()=>_t(En)),m={scope:"attention",search:"",page:0,perpage:e.perpage,sort:"",direction:"asc"},u=A(m),[a,f]=V(m),[x,I]=V(""),[v,h]=V(null),[L,y]=V(!0),[ae,U]=V(!1),[q,be]=V(null),[G,ee]=V(!0),[te,d]=V(!1),[p,D]=V(!1),_=A(0),[s,T]=V({}),[O,ne]=V({}),[Fe,ce]=V(()=>new Set),[Rt,Nt]=V(!1),rt=A(new Map),ot=A(0),at=A(0),he=A(null),pe=A(null),le=A(null),Q=A(null),Ct=A(!1),ue=A(null),Ae=A(null),lt=A(null),_e=A(()=>{}),He=A(null),Ve=tt((o,g)=>{let C=`${o}:${g}`,S=rt.current.get(C);return S===void 0&&(S=rt.current.size+1,rt.current.set(C,S)),S},[]),de=tt((o,g)=>{let C=++ot.current;he.current||(he.current=j("local_evidencehub/hub:rows")),y(!0),Cn(r,o,g).then(S=>{C===ot.current&&(S.counted&&ne(Object.fromEntries(S.filters.map(X=>[X.name,X]))),Ct.current||(Ct.current=!0,T(Object.fromEntries(S.preference.widths.map(X=>[X.key,X.width])))),S.page!==u.current.page&&(u.current={...u.current,page:S.page},f(u.current)),h(S),ce(X=>{let $n=new Set(S.rows.filter(me=>me.selectable).map(me=>Ve(me.userid,me.competencyid))),Ft=Array.from(X).filter(me=>S.candecide&&$n.has(me));return Ft.length===X.size?X:new Set(Ft)}),U(!1),y(!1))}).catch(()=>{C===ot.current&&(U(!0),y(!1))})},[r,Ve]),we=tt(o=>{let g=++at.current;pe.current||(pe.current=j("local_evidencehub/hub:band")),ee(!0),xn(r,o.scope,o.search).then(C=>{g===at.current&&(be(C),d(!1),ee(!1))}).catch(()=>{g===at.current&&(d(!0),ee(!1))})},[r]),Y=tt((o,g)=>{u.current={...u.current,...o},f(u.current),ce(new Set),de(u.current,g),g&&we(u.current)},[de,we]);nt(()=>{!L&&he.current&&(he.current(),he.current=null)},[L,v,ae]),nt(()=>{!G&&pe.current&&(pe.current(),pe.current=null)},[G,q,te]),nt(()=>{de(u.current,!0),we(u.current)},[de,we]),nt(()=>()=>{le.current!==null&&window.clearTimeout(le.current),He.current!==null&&window.clearTimeout(He.current),Q.current?.(),_e.current()},[]);let Pn=o=>{w(o),Ht(En,o)},Ln=o=>{I(o),le.current!==null&&(window.clearTimeout(le.current),le.current=null);let g=o.trim(),C=g.length>=e.searchmin?g:"";if(C===u.current.search){Q.current?.(),Q.current=null;return}if(o===""){Q.current?.(),Q.current=null,Y({search:"",page:0},!0);return}Q.current||(Q.current=j("local_evidencehub/hub:search")),le.current=window.setTimeout(()=>{le.current=null,Y({search:C,page:0},!0),Q.current?.(),Q.current=null},$r)},fe=o=>{ce(new Set);let g=j("local_evidencehub/hub:filters");_.current+=1,D(!0),o().then(()=>{Y({page:0},!0)}).catch(()=>{U(!0)}).finally(()=>{_.current-=1,D(_.current>0),g()})},re=(v?.filters??[]).map(o=>O[o.name]?{...o,options:O[o.name].options}:o),xt=(o,g)=>{let C=re.find(S=>S.name===o);C&&fe(()=>et(n,bt(re,C,g)))},{allcolumns:Oe,usedefault:Be,suspended:Tn,candecide:In}=Qr(v),ge=Oe.filter(o=>!o.fixed&&o.active).map(o=>o.key),ye=(o,g)=>{let C=j("local_evidencehub/hub:preference");ft(r,o,null).then(()=>{g&&de(u.current,!1)}).catch(()=>{g&&U(!0)}).finally(C)},Dn=o=>{v&&h({...v,preference:{...v.preference,suspended:o}}),ce(new Set);let g=j("local_evidencehub/hub:accounts");ft(r,yt(Be,ge,s),o).then(()=>{Y({page:0},!0)}).catch(()=>{U(!0)}).finally(g)},Fn=(o,g)=>{let C=g?[...ge,o]:ge.filter(S=>S!==o);v&&h({...v,columns:v.columns.map(S=>S.key===o?{...S,active:g}:S),preference:{...v.preference,usedefault:!1,columns:C}}),ye({usedefault:!1,columns:C,widths:kt(s)},!0)},St=()=>{ue.current!==null&&(window.clearTimeout(ue.current),ue.current=null);let o=lt.current;lt.current=null,o!==null&&ye(yt(Be,ge,o),!1),Ae.current?.(),Ae.current=null};_e.current=St;let An=(o,g,C=!1)=>{let S={...s};if(g===null?delete S[o]:S[o]=g,T(S),lt.current=S,!C){St();return}ue.current!==null&&window.clearTimeout(ue.current),Ae.current||(Ae.current=j("local_evidencehub/hub:width")),ue.current=window.setTimeout(()=>_e.current(),Ur)},_n=o=>{let g=new Set(o.columns);v&&h({...v,columns:v.columns.map(C=>C.fixed?C:{...C,active:g.has(C.key)}),presets:v.presets.map(C=>({...C,active:C.name===o.name})),preference:{...v.preference,usedefault:!1,columns:o.columns}}),ye({usedefault:!1,columns:o.columns,widths:kt(s)},!0)},Hn=o=>{let C=(v?.sort??"")===o&&v?.direction==="asc"?"desc":"asc";Y({sort:o,direction:C,page:0},!1)},Et=o=>{let g=j("local_evidencehub/hub:inspect");Qt(o,e.contextid,e.templateid).finally(g)},ke=Sn(()=>(v?.rows??[]).map(o=>({...o,id:Ve(o.userid,o.competencyid)})),[v,Ve]),Mt=In&&e.exportform!==null,it=new Set(ke.filter(o=>!o.selectable).map(o=>o.id)),Re=ke.filter(o=>Fe.has(o.id)),Vn=(o,g)=>ce(C=>{let S=new Set(C);return g&&!it.has(o)?S.add(o):S.delete(o),S}),On=o=>ce(()=>o?new Set(ke.filter(g=>!it.has(g.id)).map(g=>g.id)):new Set),Pt=Mt?{selected:Fe,locked:it,label:o=>P(t.selectrow,o.drawertitle),pagelabel:t.selectpage,onSelect:Vn,onSelectPage:On}:void 0,Bn=()=>{e.exportform===null||Re.length===0||Rt||(Nt(!0),He.current=window.setTimeout(()=>{He.current=null,Nt(!1)},Gr),kn({...e.exportform,contextid:e.contextid,templateid:e.templateid,pairs:Re.map(o=>({userid:o.userid,competencyid:o.competencyid}))}))},Wn=re.some(o=>o.applied)||a.search!==""||a.scope!=="all",Lt=Oe.filter(o=>o.key.startsWith("identity:")),Tt=Oe.filter(o=>o.key==="worker"||o.key==="competency"||!o.fixed&&o.active),It=i==="cards"||i==="auto"&&c,Dt=null;v!==null&&(Dt=It?se(fn,{labels:t,columns:Tt,identity:Lt,rows:ke,single:c,selection:Pt,onInspect:Et}):se(Nn,{labels:t,columns:Tt,identity:Lt,rows:ke,widths:s,sort:v.sort,direction:v.direction,selection:Pt,onInspect:Et,onSort:Hn,onWidth:An,onWidthCommit:()=>_e.current()}));let Kn=qr.map(o=>({key:o,title:o==="worker"?t.worker:"",sortable:!1,default:!0,active:!0,fixed:o==="worker"})),zn=new Set(Re.map(o=>o.userid)).size,We=Yr(v,re);return Zr("div",{className:"local-evidencehub-rview-root",ref:l,"data-region":"local-evidencehub-hubview",children:[se(Xt,{labels:t,summary:q,loading:G||p,error:te,canfilter:v!==null,chosenstatus:We.chosenstatus,onStatus:o=>xt(dt,String(o)),onCompetency:o=>xt(Ye,String(o)),onRetry:()=>we(u.current)}),se(mn,{labels:t,narrow:c,scope:a.scope,summary:q,total:v===null?null:v.total,filters:re,columns:Oe.filter(o=>!o.key.startsWith("identity:")),presets:v?.presets??[],usedefault:Be,haswidths:Object.keys(s).length>0,suspended:Tn,view:i,search:x,searchmin:e.searchmin,searching:L,matrixurl:e.matrixurl,tableurl:e.tableurl,downloadurl:e.downloadurl,formats:e.formats,appliedsearch:a.search,sort:We.sort,direction:We.direction,downloadable:We.downloadable,onScope:o=>Y({scope:o,page:0},!0),onSearch:Ln,onFilter:(o,g)=>fe(()=>et(n,bt(re,o,g))),onRemoveFilter:o=>fe(()=>et(n,vt(re,o))),onClearFilters:()=>fe(()=>gt(n)),onClearAll:()=>{I(""),u.current={...u.current,search:""},re.some(o=>o.applied)?fe(()=>gt(n)):Y({page:0},!0)},onAccounts:Dn,onColumn:Fn,onPreset:_n,onDefaultColumns:()=>{T({}),v&&h({...v,preference:{...v.preference,usedefault:!0,columns:[],widths:[]}}),ye({usedefault:!0,columns:[],widths:[]},!0)},onResetWidths:()=>{T({}),ye(yt(Be,ge,{}),!1)},onView:Pn}),se($t,{labels:t,loading:L||p,loaded:v!==null,error:ae,total:v?.total??0,filtered:Wn,cards:It,narrow:c,skeleton:{columns:Kn,lead:"worker",widths:s,sizing:Ze},dock:v!==null&&Mt&&Re.length>0?se(yn,{labels:t,count:Re.length,workers:zn,posting:Rt,onExport:Bn}):null,onRetry:()=>de(u.current,!0),children:Dt}),v!==null&&v.total>0?se(Vt,{labels:t,page:a.page,perpage:a.perpage,perpages:e.perpages,total:v.total,shown:v.rows.length,narrow:c,onPage:o=>Y({page:o},!1),onPerpage:o=>Y({perpage:o,page:0},!1)}):null]})},Mn=Xr;import{jsx as Jr}from"react/jsx-runtime";var jr=e=>e.mount==="hub"?Jr(Mn,{...e}):null,Ua=jr;export{Ua as default};
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
