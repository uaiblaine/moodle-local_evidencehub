s/(JOIN \{user\} u ON u\.id = p\.userid AND u\.deleted = 0)\n/$1 AND u.suspended = 0\n/;
