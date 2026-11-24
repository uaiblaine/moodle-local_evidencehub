s/\n\s*\$contextlist->add_from_sql\("\n\s*SELECT ctx\.id\n\s*FROM \{" \. user_filter::TABLE \. "\} f\n[^;]*;\n/\n/;
