s/usercreated = :userid AND reportid IN \(SELECT r\.id FROM \{' \. report::TABLE \. '\} r WHERE r\.component = :component\)/usercreated = :userid/;
