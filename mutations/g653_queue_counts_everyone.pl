s/"requestedby = :dxqrequester AND status \$waitingsql AND timecreated > :dxqsince"/"(requestedby = :dxqrequester OR 1 = 1) AND status \$waitingsql AND timecreated > :dxqsince"/;
