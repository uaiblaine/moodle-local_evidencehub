var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
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
import { requireAsync } from "@moodle/lms/core/amd";
const load = /* @__PURE__ */ __name(async (name) => {
  await requireAsync("core/first");
  return requireAsync(name);
}, "load");
const inspect = /* @__PURE__ */ __name(async (button, contextid, templateid) => {
  try {
    const drawer = await load("local_evidencehub/drawer");
    await drawer.open(button, contextid, templateid);
  } catch (error) {
    try {
      const notification = await load("core/notification");
      notification.exception(error);
    } catch {
      window.console.error(error);
    }
  }
}, "inspect");
export {
  inspect,
  load
};
//# sourceMappingURL=amd.dev.js.map
