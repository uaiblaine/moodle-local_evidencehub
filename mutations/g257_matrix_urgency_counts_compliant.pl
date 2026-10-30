s/MIN\(CASE WHEN b\.estatus <> " \. status::STATUS_VALID \. " THEN b\.eexpiry END\)/MIN(b.eexpiry)/;
