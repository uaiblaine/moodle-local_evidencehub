import e from"./hub/HubView";import{jsx as r}from"react/jsx-runtime";/**
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
 */const o=t=>t.mount==="hub"?r(e,{...t}):null;var p=o;export{p as default};
