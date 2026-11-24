s/\$named = \$record->paircompetencyname !== null && \$record->pairframeworkcontextid !== null;/\$named = true;/;
s/\\context::instance_by_id\(\$frameworkcontextid, IGNORE_MISSING\)/\\context::instance_by_id(\$frameworkcontextid)/;
