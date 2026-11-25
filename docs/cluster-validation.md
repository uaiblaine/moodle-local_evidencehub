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

## Results, 2026-11-24 (version 2026100518): the pairs report

On a seed of 10,000 workers by 30 competencies (the measurement section of `docs/design/data-report-study.md`,
section 5.1, has the method and every figure). The raw files are kept with the measuring scripts, outside the
repository; each line names its file.

| Check | Result |
|---|---|
| The three web services on the first node, warm and cold, three readers (`bench-cluster.md`, `bench-cluster-cold.md`) | every case within the budgets: the rows call at most 275.4 ms at p95 (rows budget 300 ms), the summary at most 179.1 ms (budget 500 ms); a constant statement count per case, cold |
| The figures cached on one node served on the other (`cluster-cache-check.txt`, the reader of every worker, each step a process on one node) | node 1 computes the rows (316 ms, 40 statements) and the summary (148 ms, 29); node 2 is served both from the cache (rows 103 ms, 36; summary 3 ms, 22) |
| A generation replaced on one node retires the other's figures (same file) | node 1 replaces the generation; node 2 computes again (rows 316 ms, 39; summary 159 ms, 29); node 1 is then served node 2's figures (rows 101 ms, 36; summary 3 ms, 22) |

Not checked on the cluster: a request that replaces the generation inside a transaction, then the second
replacement when that request ends (it needs a real form save through the balancer); and the list in a browser
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
