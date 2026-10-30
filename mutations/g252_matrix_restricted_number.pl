s/CASE WHEN r\.restricted = 1 THEN 0 ELSE COALESCE\(r\.effectiveexpiry, 0\) END AS eexpiry/COALESCE(r.effectiveexpiry, 0) AS eexpiry/;
