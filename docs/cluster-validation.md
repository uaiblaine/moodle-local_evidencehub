# Validation on a multi-node deployment

What the plugin does when it is not alone on one machine, checked on the fleet's mini cluster: one
Moodle 5.3 site on two web nodes behind a round-robin balancer with no stickiness, MUC application and
session stores and PHP sessions in Redis, a shared dataroot volume, a tmpfs `localcachedir` per node,
and a cron that is a new container on every run. Demo data: the README screenshots' seed (24 workers,
8 competencies, 178 status rows).

## Stores

| Store | Kind | Used for |
|---|---|---|
| Database tables | shared | every record, the status rows, the dossiers, the exports |
| MUC `hubkpis`, `hubgeneration` | shared (application, Redis) | the hub's KPI counts, retired by a generation every node reads |
| `filedir` (file storage) | shared | the prepared dossier ZIP, copied there before the task ends |
| `make_request_directory()` | node-local, per request | the ZIP while the task writes it |
| Core locks (`lock_config`) | shared (database) | `user_<id>` around every recomputation |

Nothing is kept in `localcachedir`, statics across requests, APCu or a file under `dirroot`.

## Results, 2026-11-12 (version 2026100510)

| Check | Result |
|---|---|
| Verification page, logged out, four requests through the balancer | 200 on web1 and web2 alike, the same snapshot |
| Hub KPIs as admin through the balancer | the same counts on web1 and web2 |
| A pair recomputed in a new container (status 2 -> 4) | the generation moved in Redis; the next request on each node showed the new counts |
| Export of 24 workers above `syncmaxworkers` | queued from one container, built by the ad hoc task in another (0.96 s, 88 queries); the ZIP served byte for byte by both nodes; refused logged out |
| Scheduled tasks, each twice in new containers | `evaluate_validity` 1 query, `cleanup_dossier_exports` 3, `send_weekly_digest` 6 then 1 (round already done), `reconcile_validity` 1945 (every pair recomputed) |
| Two `reconcile_validity` at once, in two containers | both finished; no duplicate pair, nothing queued by a lock timeout |

## Open

- `reconcile_validity` recomputes every pair and `status_repository::recompute()` replaces the KPI
  generation for every row it rewrites, changed or not: a run with no change still retires every
  node's KPI cache and writes the generation once per pair.
