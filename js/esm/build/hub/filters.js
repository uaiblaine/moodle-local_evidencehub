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
 */const u="validity:status",i="competency:competency",l="user:cohort",p=t=>t.facet!==""&&t.options.length>0,r=t=>{try{const e=JSON.parse(t.values||"{}");return e!==null&&typeof e=="object"?e:{}}catch{return{}}},s=t=>t.reduce((e,n)=>({...e,...r(n)}),{}),c=t=>{if(!t.applied||t.facet==="")return"";const e=r(t),n=e[`${t.name}_value`];return n==null||Number(e[`${t.name}_operator`]??0)!==1?"":String(n)},a=(t,e)=>{const n=s(t);for(const o of Object.keys(n))o.startsWith(`${e.name}_`)&&delete n[o];return n},d=(t,e,n)=>{const o=a(t,e);return o[`${e.name}_operator`]=n===""?0:1,o[`${e.name}_value`]=n,o},_=t=>{const e=c(t);return e===""?"":t.options.find(n=>n.value===e)?.label??""};export{l as COHORT_FILTER,i as COMPETENCY_FILTER,u as STATUS_FILTER,s as allValues,p as choosable,c as chosen,_ as chosenLabel,r as ownValues,d as withChoice,a as without};
