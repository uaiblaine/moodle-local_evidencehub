s/\n\s*\$DB->set_field_select\(\n\s*self::TABLE,\n\s*'nexttransition',\n\s*\$now \+ HOURSECS,\n\s*'id = :id AND nexttransition <= :now',\n\s*\['id' => \$row->id, 'now' => \$now\]\n\s*\);\n/\n/;
