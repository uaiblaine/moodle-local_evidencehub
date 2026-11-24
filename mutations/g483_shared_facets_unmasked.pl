s/\$field = \$filters\[\$identifier\]->get_field_sql\(\);/\$field = str_replace('.id IS NOT NULL THEN', '.id IS NULL AND 1 = 0 THEN', \$filters[\$identifier]->get_field_sql());/;
