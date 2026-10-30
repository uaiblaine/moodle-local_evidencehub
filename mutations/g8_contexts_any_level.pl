s/(function get_users_in_context\(.*?)\n\s*if \(\$context->contextlevel != CONTEXT_USER\) \{\n\s*return;\n\s*\}\n/$1\n/s;
