import{requireAsync as t}from"@moodle/lms/core/amd";/**
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
 */const n=async e=>(await t("core/first"),t(e)),d=async(e,r,i)=>{try{await(await n("local_evidencehub/drawer")).open(e,r,i)}catch(o){try{(await n("core/notification")).exception(o)}catch{window.console.error(o)}}};export{d as inspect,n as load};
