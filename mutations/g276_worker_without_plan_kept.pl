s/AND EXISTS \(SELECT 1/AND (1 = 1 OR EXISTS (SELECT 1/; s/p\.status = :dbactive\)/p.status = :dbactive))/;
