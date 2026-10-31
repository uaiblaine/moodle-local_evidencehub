s/JOIN \{context\} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :dgusercontext/JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel >= :dgusercontext - 40/;
