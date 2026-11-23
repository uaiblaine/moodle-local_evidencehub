s/\$status = \$this->pairs \? "COALESCE\(\{\$s\}\.status, " \. status::EFFECTIVE_MISSING \. "\)" : "\{\$s\}\.status";/\$status = "{\$s}.status";/;
