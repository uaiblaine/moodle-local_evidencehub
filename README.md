moodle-local_evidencehub
========================

[![Moodle Plugin CI](https://github.com/uaiblaine/moodle-local_evidencehub/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/uaiblaine/moodle-local_evidencehub/actions/workflows/ci.yml?query=branch%3Amain)

Validity of competency evidence, readiness per learning plan template, and verifiable dossiers.

Core competencies let a worker upload evidence of prior learning (a training certificate, a health
certificate, a diploma) and link it to competencies, but the evidence has no issue date, no expiry
and no type. This plugin adds those details to core's own evidence, works out from them whether
each competency a plan template requires is compliant, due, expired or pending for each worker,
and lets a manager export a ZIP of readiness dossiers whose PDF pages carry a verification code.

The evidence itself stays core's (`competency_userevidence` and its files): the plugin keeps the
details beside it in tables of its own and never changes a core rating. It is developed in stages;
this release holds the data model, the capabilities, the privacy provider, the evidence details
(entering, verifying and rejecting them) and the validity engine with its policies; the hub and the
dossiers come next.


Requirements
------------

- Moodle 5.3 (`$plugin->supported = [503, 503]`)
- Competencies enabled (`core_competency/enabled`), with learning plan templates (`tool_lp`, part
  of core)
- Optional: `tool_cohortroles` (part of core) to give mentors the user-context capabilities over
  the workers of their cohorts


Installation
------------

Install the plugin like any other plugin to folder `/local/evidencehub`.

See http://docs.moodle.org/en/Installing_plugins for details on installing
Moodle plugins.

The installation seeds five evidence types: regulatory training certificate (NR), occupational
health certificate (ASO, marked sensitive), technical responsibility record (ART/RRT), diploma and
practical assessment. Their names are stored in the language of the site at installation and can
be changed.


Usage
-----

**The evidence page** (`/local/evidencehub/evidence.php?userid=<id>`, the current user by
default) lists a worker's evidence of prior learning with its validity details: type, issue and
expiry dates, issuer, hours, the authenticity code (to who may manage or verify the evidence) and
the verification state with who decided and when. Reading it follows core: a user who can view the
worker's evidence in tool_lp (`moodle/competency:userevidenceview` in the worker's user context, or
the right to manage it) can open the page. From it:

- "Add evidence" and "Edit" open one form that saves core's evidence (name, description, URL,
  files, through core's API) and its validity details together, or neither.
- "Complete details" adds the details to an evidence entered in tool_lp.
- "Verify or reject" records a verifier's decision; a rejection needs a note, which the worker
  sees.
- "Link competencies" opens the evidence in tool_lp, where competencies are linked.

Each type sets rules: an expiry date is required (or "Never expires" is refused), an authenticity
code is required, and a default validity in months fills a missing expiry date from the issue date
(the last day of a shorter month when the day does not exist). A type with no default validity
needs an expiry date or "Never expires".

Evidence of a **sensitive** type (the occupational health certificate) shows only its type and
"Restricted details" to a viewer other than the worker who lacks `local/evidencehub:viewsensitive`.
Such a viewer can neither edit nor verify or reject those details, whatever else they hold; a
manager without it can still enter a new evidence of a sensitive type.

**On tool_lp's evidence pages** (the list of a user's evidence and the view of one) whoever may
manage the evidence sees a prompt linking to the evidence page while some evidence has no validity
details.

**Nobody verifies their own evidence.** The owner of an evidence can never verify or reject it,
whatever their capabilities.

**An edit sends a decision back to pending.** When the details or the core evidence of a verified
or rejected evidence change (in this plugin's form or in tool_lp), the evidence returns to
"Awaiting verification", unless the change came from a holder of `local/evidencehub:verify` who is
not the owner. Every entry, change, decision and reset is written to the audit trail.


**Validity.** For each (worker, competency) pair the plugin works out a status from the evidence
linked to the competency in tool_lp and from the competency's validity policy, and keeps it in
`local_evidencehub_status`:

- *Compliant*: a counted evidence covers today with more days left than the policy's first alert.
- *Due soon* / *Due, critical*: covered, within the first / second alert window (days before the
  last valid day).
- *Expired*: counted evidence exists and none covers today.
- *Pending, awaiting verification*: evidence exists but none counts yet (no details, not verified
  where the policy requires it, too few hours, refused by a verifier, or issued in the future).
- A pair with nothing at all has no row: it shows as *Pending, no evidence*.

Dates are calendar days in the site's timezone; the expiry day itself is still valid. Among several
covering evidence the one with the latest last valid day decides. The status is recomputed when an
evidence is linked or unlinked, its details change, it is verified or rejected, or the competency is
rated; the evidence page shows it next to each linked competency.

**Validity policies** (Site administration > Competencies > Validity policies,
`/local/evidencehub/policies.php`) are set per competency framework, and a competency can override
its framework. A holder of `local/evidencehub:managepolicies` in a framework's context (the system
or a course category) manages that framework's policies; a category manager sees only the
frameworks of their categories. Without a policy, a competency is technical with alerts at 60 and
30 days. A policy says:

- its kind: *regulatory* (only evidence counts, a new policy counts verified evidence only, expiry is
  strict and an expired competency blocks the worker), *technical* (evidence or the latest
  proficient rating counts, with optional grace days after expiry and optionally only while the
  competency is rated proficient) or *behavioural* (only the latest proficient rating counts, for
  the number of months the policy sets; 0 means it never expires);
- whether only verified evidence counts, the minimum hours an evidence must state, the two alert
  windows, and what happens on expiry: the expiry is always written into the competency's history,
  and the *review* choice also asks core competencies for a review. The plugin never changes a
  rating or a proficiency.

A policy change recomputes its framework's or competency's pairs in an ad hoc task.

Core loads the plugin's admin entries only for users who can see the site administration, so a
category manager never sees the "Validity policies" entry: they open
`/local/evidencehub/policies.php` directly, which lists the frameworks of their categories.

On the evidence page, a competency whose validity is decided by an evidence of a sensitive type
shows only "Restricted", on every evidence linked to it, to a viewer who may not see that evidence.

**Core competency history.** Each status change writes one log entry ("Evidence hub: ...") into the
history of the worker's user competency (`competency_evidence`, through core's `add_evidence()`,
with the plugin's component): core's own privacy provider exports and deletes those entries with the
rest of the competency history.

**Tasks.**

- *Evaluate validity transitions* (`\local_evidencehub\task\evaluate_validity`, hourly): recomputes
  the statuses whose next transition (an alert window, the expiry, a renewal starting) has come,
  500 per run, oldest first.
- *Reconcile validity statuses* (`\local_evidencehub\task\reconcile_validity`, weekly on Sunday
  night): recomputes every pair that has a linked evidence, a status or a proficient rating that
  counts, healing whatever a missed event left behind.
- *Recompute validity after a policy change* (`\local_evidencehub\task\recompute_scope`, ad hoc).


Capabilities
------------

- `local/evidencehub:view` (course category; manager): open the evidence hub. Each row also needs
  core's `moodle/competency:userevidenceview` in the worker's user context.
- `local/evidencehub:verify` (user; manager): verify or reject the details of a worker's evidence.
- `local/evidencehub:viewsensitive` (user; no archetype): see the details and files of evidence of a
  sensitive type.
- `local/evidencehub:managepolicies` (course category; manager): set the validity policy of a
  competency framework or of one competency, in the framework's context.
- `local/evidencehub:managetypes` (system; manager): manage the evidence types.
- `local/evidencehub:exportdossier` (course category; manager): export readiness dossiers.
- `local/evidencehub:receivealerts` (user; no archetype): be told when a worker's evidence is due,
  expired or awaiting verification.

The user-context capabilities follow core competencies: a role assigned in a worker's user
context, typically through `tool_cohortroles`, reaches that worker.


Data stored
-----------

- `local_evidencehub_type`: the evidence types (name, default validity, whether expiry and an
  authenticity code are required, whether the type is sensitive). Configuration, no personal data.
- `local_evidencehub_meta`: the details of one core evidence of prior learning: type, issue date,
  expiry date or "never expires", issuer, authenticity code, hours, and its verification (state,
  who verified, when, note). Kept while the evidence exists.
- `local_evidencehub_policy`: the validity policy of a framework or competency (kind, whether only
  verified evidence counts, minimum hours, alert windows, grace days, cycle). Configuration, no
  personal data.
- `local_evidencehub_status`: the validity worked out for each (worker, competency): status,
  deciding evidence, effective expiry, when it next changes. Recomputed from the details; removed
  with the competency, the user, or when nothing proves the competency any more.
- `local_evidencehub_audit`: an append-only trail of what happened to each evidence's details, who
  acted and their note. Kept beyond the log store's lifetime, because compliance audits ask for it.
- `local_evidencehub_dossier`: each readiness dossier issued about a worker: its verification code,
  the plan template, who issued it, a snapshot of what it states, and whether it was revoked.
- `local_evidencehub_export`: each dossier export request: who asked, for which template, how many
  workers.

A data request exports, in the worker's user context, the details of the worker's evidence, the
trail, the dossiers about the worker and the validity of each competency; an actor (a verifier, a
dossier issuer) also finds the rows they acted on in each worker's context, and a requester their
export requests. A deletion request for a worker deletes every row about that worker and their
export requests (the dossiers of those exports describe other workers and stay). As in core
competencies, deleting an actor leaves the workers' rows, including the column naming the actor.
The evidence and its files are core's and follow core's privacy provider.


Troubleshooting
---------------

Nothing yet.


Translations
------------

The English strings ship in the package. The Brazilian Portuguese translation
is maintained in this repository (`lang/pt_br`) and reaches sites through
[AMOS](https://lang.moodle.org/), as the plugins directory requires; it is left
out of the release package.


License
-------

This plugin is licensed under the [GNU GPL v3 or later](http://www.gnu.org/copyleft/gpl.html).

Copyright: 2026 Anderson Blaine
