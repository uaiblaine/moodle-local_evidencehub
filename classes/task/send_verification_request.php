<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_evidencehub\task;

use local_evidencehub\local\alerts;

/**
 * Tell the verifiers of a worker that an evidence entered the pending state.
 *
 * Queued by {@see alerts::request_verification()} once per evidence, and run after the transaction that
 * queued it committed, so it reads the evidence's competency links as they ended up.
 *
 * Custom data: metaid.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_verification_request extends \core\task\adhoc_task {
    /**
     * The task's name.
     *
     * @return string
     */
    #[\Override]
    public function get_name(): string {
        return get_string('tasksendverificationrequest', 'local_evidencehub');
    }

    /**
     * Send the request, when the details are still pending.
     *
     * @return void
     */
    #[\Override]
    public function execute(): void {
        $data = (object) $this->get_custom_data();
        $sent = alerts::verification_requested((int) ($data->metaid ?? 0));
        mtrace("Sent $sent verification requests.");
    }
}
