var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
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
const field = /* @__PURE__ */ __name((form, name, value) => {
  const input = document.createElement("input");
  input.type = "hidden";
  input.name = name;
  input.value = value;
  form.appendChild(input);
}, "field");
const exportDossiers = /* @__PURE__ */ __name((posted) => {
  const form = document.createElement("form");
  form.method = "post";
  form.action = posted.action;
  form.hidden = true;
  field(form, "contextid", String(posted.contextid));
  field(form, "action", "exportdossiers");
  field(form, "sesskey", posted.sesskey);
  field(form, "templateid", String(posted.templateid));
  posted.pairs.forEach((pair) => field(form, "pairs[]", `${pair.userid}:${pair.competencyid}`));
  document.body.appendChild(form);
  form.submit();
  form.remove();
}, "exportDossiers");
export {
  exportDossiers
};
//# sourceMappingURL=export.dev.js.map
