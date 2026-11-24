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
 */const n=(t,e,i)=>{const o=document.createElement("input");o.type="hidden",o.name=e,o.value=i,t.appendChild(o)},r=t=>{const e=document.createElement("form");e.method="post",e.action=t.action,e.hidden=!0,n(e,"contextid",String(t.contextid)),n(e,"action","exportdossiers"),n(e,"sesskey",t.sesskey),n(e,"templateid",String(t.templateid)),t.pairs.forEach(i=>n(e,"pairs[]",`${i.userid}:${i.competencyid}`)),document.body.appendChild(e),e.submit(),e.remove()};export{r as exportDossiers};
