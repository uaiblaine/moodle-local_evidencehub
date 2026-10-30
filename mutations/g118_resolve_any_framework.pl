s/'frameworkid = :frameworkid AND \(competencyid = :competencyid OR competencyid = 0\)'/'(frameworkid = :frameworkid OR 1 = 1) AND (competencyid = :competencyid OR competencyid = 0)'/;
