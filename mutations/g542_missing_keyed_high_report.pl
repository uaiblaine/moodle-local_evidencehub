s/"CASE WHEN \{\$s\}\.status IS NULL THEN 0 ELSE COALESCE\(\{\$s\}\.effectiveexpiry, " \. hub_query::NO_EXPIRY \. "\) END"/"COALESCE({\$s}.effectiveexpiry, " . hub_query::NO_EXPIRY . ")"/;
