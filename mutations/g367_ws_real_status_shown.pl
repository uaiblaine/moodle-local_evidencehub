s/\$code = \$pair === null \? status::EFFECTIVE_MISSING : \(int\) \$pair->estatus;/\$code = (\$pair === null || \$pair->status === null) ? status::EFFECTIVE_MISSING : (int) \$pair->status;/;
