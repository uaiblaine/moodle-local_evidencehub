s/"\(status = :done AND timeexpires <= :now\)/"(:done > 0 AND timeexpires <= :now)/;
