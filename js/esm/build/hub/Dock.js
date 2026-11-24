import{fill as l,fillCount as s,num as i}from"../datareport/str";import{jsx as n,jsxs as o}from"react/jsx-runtime";/**
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
 */const p=({labels:e,count:t,workers:c,posting:a,onExport:r})=>o("div",{className:"local-evidencehub-rview-dock",role:"region","aria-label":e.dock,"data-region":"local-evidencehub-dock",children:[o("span",{className:"local-evidencehub-rview-dockcount","aria-live":"polite",children:[l(e.selected,i(t)),o("span",{className:"local-evidencehub-rview-dockworkers",children:[" \xB7 ",s(e,"selectedworkers",c,i(c))]})]}),n("div",{className:"local-evidencehub-rview-dockactions",children:n("button",{type:"button",className:"btn btn-primary",disabled:a,onClick:r,children:e.exportdossiers})})]});export{p as Dock};
