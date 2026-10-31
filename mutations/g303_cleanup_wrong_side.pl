s/\(status = :done AND timeexpires <= :now\)/(status = :done AND timeexpires > :now)/;
