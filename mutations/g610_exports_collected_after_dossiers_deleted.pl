s/(\n\s*\$exportids = \$DB->get_fieldset_select\(.*?\n\s*dossier_export::retire\([^\n]*)(\n\s*\$DB->delete_records\('local_evidencehub_dossier', \['userid' => \$userid\]\);)/$2$1/s;
