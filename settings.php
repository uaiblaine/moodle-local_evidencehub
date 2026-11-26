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

/**
 * Admin tree entries, in local_dimensions' category when it is there and in 'competencies' otherwise.
 *
 * Both pages do their own checks, the policies page in each framework's context, so the entries
 * name the plugin's capabilities rather than moodle/site:config. The dossier settings are the site's.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$parent = \local_evidencehub\local\admin_tree::parent_category($ADMIN);

$ADMIN->add($parent, new admin_externalpage(
    'local_evidencehub_policies',
    new lang_string('policiespage', 'local_evidencehub'),
    new moodle_url('/local/evidencehub/policies.php'),
    'local/evidencehub:managepolicies'
));

$ADMIN->add($parent, new admin_externalpage(
    'local_evidencehub_hub',
    new lang_string('hubpage', 'local_evidencehub'),
    new moodle_url('/local/evidencehub/index.php'),
    'local/evidencehub:view'
));

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_evidencehub', new lang_string('dossiersettings', 'local_evidencehub'));
    $ADMIN->add($parent, $settings);
    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configcheckbox(
            'local_evidencehub/includesensitivefiles',
            new lang_string('includesensitivefiles', 'local_evidencehub'),
            new lang_string('includesensitivefiles_desc', 'local_evidencehub'),
            0
        ));
        // The choices are read when the page shows them, never while settings.php loads (a fresh install included).
        // They must be a closure: core's configselect takes an array callable for the choices themselves.
        $settings->add(new admin_setting_configselect(
            'local_evidencehub/identifierfield',
            new lang_string('identifierfield', 'local_evidencehub'),
            new lang_string('identifierfield_desc', 'local_evidencehub'),
            \local_evidencehub\local\dossier_config::IDENTIFIER_IDNUMBER,
            \local_evidencehub\local\dossier_config::identifier_choices(...)
        ));
        $settings->add(new admin_setting_configtext(
            'local_evidencehub/syncmaxworkers',
            new lang_string('syncmaxworkers', 'local_evidencehub'),
            new lang_string('syncmaxworkers_desc', 'local_evidencehub'),
            \local_evidencehub\local\dossier_config::DEFAULT_SYNCMAXWORKERS,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configtext(
            'local_evidencehub/syncmaxbytes',
            new lang_string('syncmaxbytes', 'local_evidencehub'),
            new lang_string('syncmaxbytes_desc', 'local_evidencehub'),
            \local_evidencehub\local\dossier_config::DEFAULT_SYNCMAXBYTES,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configtext(
            'local_evidencehub/maxexportworkers',
            new lang_string('maxexportworkers', 'local_evidencehub'),
            new lang_string('maxexportworkers_desc', 'local_evidencehub'),
            \local_evidencehub\local\dossier_config::DEFAULT_MAXEXPORTWORKERS,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configtext(
            'local_evidencehub/maxqueuedexports',
            new lang_string('maxqueuedexports', 'local_evidencehub'),
            new lang_string('maxqueuedexports_desc', 'local_evidencehub'),
            \local_evidencehub\local\dossier_config::DEFAULT_MAXQUEUEDEXPORTS,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configtext(
            'local_evidencehub/exportretentiondays',
            new lang_string('exportretentiondays', 'local_evidencehub'),
            new lang_string('exportretentiondays_desc', 'local_evidencehub'),
            \local_evidencehub\local\dossier_config::DEFAULT_RETENTIONDAYS,
            PARAM_INT
        ));
        $basis = \local_evidencehub\local\dossier_config::class;
        $readinesschoices = [
            $basis::READINESS_REAL => new lang_string('readinessbasis_real', 'local_evidencehub'),
            $basis::READINESS_SHOWN => new lang_string('readinessbasis_shown', 'local_evidencehub'),
        ];
        $settings->add(new admin_setting_configselect(
            'local_evidencehub/readinessbasis',
            new lang_string('readinessbasis', 'local_evidencehub'),
            new lang_string('readinessbasis_desc', 'local_evidencehub'),
            \local_evidencehub\local\dossier_config::READINESS_REAL,
            $readinesschoices
        ));
        $settings->add(new admin_setting_configcheckbox(
            'local_evidencehub/verifylive',
            new lang_string('verifylive', 'local_evidencehub'),
            new lang_string('verifylive_desc', 'local_evidencehub'),
            0
        ));
    }
}
