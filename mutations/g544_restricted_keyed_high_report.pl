s/(return \$this->masked\(\n\s*"CASE WHEN \{\$s\}\.status IS NULL THEN 0 ELSE COALESCE\(\{\$s\}\.effectiveexpiry, " \. hub_query::NO_EXPIRY \. "\) END",\n\s*)'0'/$1(string) hub_query::NO_EXPIRY/;
