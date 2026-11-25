# Validation on a multi-node deployment

What the plugin does when it is not alone on one machine, checked on the fleet's mini cluster: one
Moodle 5.3 site on two web nodes behind a round-robin balancer with no stickiness, MUC application and
session stores and PHP sessions in Redis, a shared dataroot volume, a tmpfs `localcachedir` per node,
and a cron that is a new container on every run.

## Stores

| Store | Kind | Used for |
|---|---|---|
| Database tables | shared | every record, the status rows, the dossiers, the exports |
| Core's `reportbuilder_report`, `reportbuilder_user_filter` | shared (database) | the pairs report's persistent per hub context and template, and each reader's stored filter values |
| User preference `local_evidencehub_report` | shared (database) | a reader's columns and widths, the suspended choice and the last template per hub context |
| MUC `hubgeneration` | shared (application, Redis) | the generation of the pair set and its statuses; every replacement also replaces `reportgeneration` |
| MUC `reportgeneration` | shared (application, Redis) | the generation the report's cached figures are read under |
| MUC `reportsummary` | shared (application, Redis) | the report's summary, and its rows' total, hidden workers and facet counts, per reader and criteria (codes and ids, never a name), retired by `reportgeneration` |
| `filedir` (file storage) | shared | the prepared dossier ZIP, copied there before the task ends |
| `make_request_directory()` | node-local, per request | the ZIP while the task writes it |
| A static flag in `hub_kpis` | node-local, per request | that the generations are replaced once more when a request that replaced them inside a transaction ends |
| Core locks (`lock_config`) | shared (database) | `user_<id>` around every recomputation |

Nothing is kept in `localcachedir`, statics across requests, APCu or a file under `dirroot`. The view a reader
chooses (automatic, table or cards) is kept in their browser only. The cache definitions are in `db/caches.php`;
the keys and generations in `classes/local/hub_kpis.php`.

## Results, 2026-11-25 (version 2026100521): a generation replaced inside a transaction

A request that replaces a generation while its transaction is open replaces both again when it ends
(`hub_kpis::defer_when_in_transaction()`): a reader arriving between the first replacement and the commit stores
figures of the rows as they were under the new generation. PHPUnit holds the rule
(`tests/local/hub_kpis_test.php`); this is the same thing in real requests. Five saves of one demo evidence
through the balancer, in a browser, as the administrator (the demo data of 24 workers), each put back afterwards.
Two read-only watchers ran beside them: `redis-cli MONITOR` on the two generation keys, which gives the time and
the node of every write, and a poller on the first node reading the evidence's details and its pair's status
row about every 0.12 ms, which gives the interval in which the commit became visible. Times are from that
interval. The raw files are kept with the measuring scripts, outside the repository.

| Save | Node | Replaced before the commit was visible | Replaced after it |
|---|---|---|---|
| Evidence form, the issuer changed | web2 | the report's generation, 1.2 to 1.4 ms before | both generations, 31.5 to 31.8 ms after |
| Evidence form, the expiry moved to a past day (compliant to expired) | web2 | the hub's and the report's (three writes), 10.7 to 16.7 ms before | both generations, 0.99 s after |
| Evidence form, the expiry and the issuer put back (expired to compliant) | web2 | the hub's and the report's (three writes), 9.4 to 11.5 ms before | both generations, 32.6 to 33.0 ms after |
| Verify form, rejected | web1 | none | the hub's and the report's (three writes), 3.2 to 8.2 ms after the status row's commit; nothing more when the request ended |
| Verify form, verified again | web2 | not read | three writes, as above, inside the one 19 ms gap the poller left on a loaded machine |

What it shows:

- **The evidence form** saves inside one transaction, so its replacements come before the commit, and each of the
  three requests replaced both generations once more after it, with new values: the deferred replacement runs in
  a real request, on whichever node serves it. The gap after the commit is the rest of the request: 32 ms twice,
  and 0.99 s for the save that made the pair expired (probably the alert an external observer sends then; not
  traced).
- **The verify form** opens no transaction around the recomputation: the details commit, the status row commits
  about 0.1 s later, and the generations are replaced after that. Nothing is deferred, as outside a transaction
  nothing should be.
- **Both nodes read the new figures after each of the last three changes**, the first to ask computing them and
  the other served the same figures from the shared cache (the summary in 17 statements against 10): pairs
  needing attention 63, 64 and 63 after the verification, the expiry in the past and the restore (64 after the
  rejection, read on one node).
- **The list in a browser through the balancer** draws its 50 rows and its band with the same figures; six
  requests of the page were answered by web1 and web2 in turn.

Not shown: a reader placed between a replacement and its commit. A real request cannot be held there from
outside, so the stale figures the second replacement exists to retire were not produced on the cluster; the
windows they would need were 1.2 to 16.7 ms wide, and they could have been served until the request ended.

## Results, 2026-11-24 (version 2026100518): the pairs report

On a seed of 10,000 workers by 30 competencies (the measurement section of `docs/design/data-report-study.md`,
section 5.1, has the method and every figure). The raw files are kept with the measuring scripts, outside the
repository; each line names its file.

| Check | Result |
|---|---|
| The three web services on the first node, warm and cold, three readers (`bench-cluster.md`, `bench-cluster-cold.md`) | every case within the budgets: the rows call at most 275.4 ms at p95 (rows budget 300 ms), the summary at most 179.1 ms (budget 500 ms); a constant statement count per case, cold |
| The figures cached on one node served on the other (`cluster-cache-check.txt`, the reader of every worker, each step a process on one node) | node 1 computes the rows (316 ms, 40 statements) and the summary (148 ms, 29); node 2 is served both from the cache (rows 103 ms, 36; summary 3 ms, 22) |
| A generation replaced on one node retires the other's figures (same file) | node 1 replaces the generation; node 2 computes again (rows 316 ms, 39; summary 159 ms, 29); node 1 is then served node 2's figures (rows 101 ms, 36; summary 3 ms, 22) |

Not checked on the cluster at that date, and checked on 2026-11-25 (above): a request that replaces the
generation inside a transaction, then the second replacement when that request ends; and the list in a browser
through the balancer.

## Results, 2026-11-12 (version 2026100510)

Demo data: the README screenshots' seed (24 workers, 8 competencies, 178 status rows). At that version the hub's
figures were the old list's KPIs, in the MUC cache `hubkpis` retired by `hubgeneration`; that cache was removed
with the old list in version 2026100519.

| Check | Result |
|---|---|
| Verification page, logged out, four requests through the balancer | 200 on web1 and web2 alike, the same snapshot |
| Hub KPIs as admin through the balancer | the same counts on web1 and web2 |
| A pair recomputed in a new container (status 2 -> 4) | the generation moved in Redis; the next request on each node showed the new counts |
| Export of 24 workers above `syncmaxworkers` | queued from one container, built by the ad hoc task in another (0.96 s, 88 queries); the ZIP served byte for byte by both nodes; refused logged out |
| Scheduled tasks, each twice in new containers | `evaluate_validity` 1 query, `cleanup_dossier_exports` 3, `send_weekly_digest` 6 then 1 (round already done), `reconcile_validity` 1945 (every pair recomputed) |
| Two `reconcile_validity` at once, in two containers | both finished; no duplicate pair, nothing queued by a lock timeout |

## Resolved since

- `reconcile_validity` recomputes every pair, and `status_repository::recompute()` used to replace the
  KPI generation for every row it rewrote, changed or not. It now replaces it only when a row appears,
  goes, or changes its status or its sensitive flag, so a reconcile run with no change leaves every
  node's cached figures in place (version 2026100514, when those figures were the `hubkpis` cache's).
