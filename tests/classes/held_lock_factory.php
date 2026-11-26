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

namespace local_evidencehub\tests;

use core\lock\lock;

/**
 * A lock factory that refuses chosen locks of the evidence hub, as if another process held them.
 *
 * Inside one PHPUnit process a second acquire of a held lock succeeds, so a lock timeout cannot be
 * produced by holding a real lock. Core picks the factory from $CFG->lock_factory, which makes this
 * a seam of core's own configuration rather than of the plugin's code: every other lock is a real
 * database lock.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class held_lock_factory implements \core\lock\lock_factory {
    /** @var array Resource names of the local_evidencehub lock type that are refused. */
    public static array $held = [];

    /** @var string The lock type. */
    private string $type;

    /** @var \core\lock\lock_factory The real factory for every other lock. */
    private \core\lock\lock_factory $inner;

    /**
     * A factory of one lock type.
     *
     * @param string $type The lock type.
     */
    public function __construct($type) {
        $this->type = (string) $type;
        $this->inner = new \core\lock\db_record_lock_factory($type);
    }

    /**
     * Refuse a held lock of the evidence hub; get any other from the real factory.
     *
     * @param string $resource The resource.
     * @param int $timeout Seconds to wait.
     * @param int $maxlifetime Seconds the lock may live.
     * @return lock|false
     */
    #[\Override]
    public function get_lock($resource, $timeout, $maxlifetime = 86400) {
        if ($this->type === 'local_evidencehub' && in_array($resource, self::$held, true)) {
            return false;
        }
        return $this->inner->get_lock($resource, $timeout, $maxlifetime);
    }

    /**
     * Release a lock of the real factory.
     *
     * @param lock $lock The lock.
     * @return bool
     */
    #[\Override]
    public function release_lock(lock $lock) {
        return $this->inner->release_lock($lock);
    }

    /**
     * As the real factory.
     *
     * @return bool
     */
    #[\Override]
    public function supports_timeout() {
        return $this->inner->supports_timeout();
    }

    /**
     * As the real factory.
     *
     * @return bool
     */
    #[\Override]
    public function supports_auto_release() {
        return $this->inner->supports_auto_release();
    }

    /**
     * Always available.
     *
     * @return bool
     */
    #[\Override]
    public function is_available() {
        return true;
    }

    /**
     * Run some code while the recomputation lock of these users is held elsewhere.
     *
     * @param array $userids The users.
     * @param callable $work The code.
     * @return mixed What the code returned.
     */
    public static function while_held(array $userids, callable $work): mixed {
        return self::while_resources_held(array_map(fn(int $userid): string => 'user_' . $userid, $userids), $work);
    }

    /**
     * Run some code while some locks of the evidence hub, by resource name, are held elsewhere.
     *
     * @param array $resources The resource names.
     * @param callable $work The code.
     * @return mixed What the code returned.
     */
    public static function while_resources_held(array $resources, callable $work): mixed {
        global $CFG;
        $previous = $CFG->lock_factory ?? null;
        $CFG->lock_factory = self::class;
        self::$held = $resources;
        try {
            return $work();
        } finally {
            self::$held = [];
            if ($previous === null) {
                unset($CFG->lock_factory);
            } else {
                $CFG->lock_factory = $previous;
            }
        }
    }
}
