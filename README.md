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
this release holds the data model, the capabilities and the privacy provider only, with no page
yet.


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

Nothing to configure or use yet: the pages arrive in the next stages (evidence details form,
validity engine, hub page, dossier export).


Capabilities
------------

- `local/evidencehub:view` (course category; manager): open the evidence hub. Each row also needs
  core's `moodle/competency:userevidenceview` in the worker's user context.
- `local/evidencehub:verify` (user; manager): verify or reject the details of a worker's evidence.
- `local/evidencehub:viewsensitive` (user; no archetype): see the details and files of evidence of a
  sensitive type.
- `local/evidencehub:managepolicies` (course category; manager): set the validity policy of a
  competency framework or of one competency.
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
  deciding evidence, effective expiry, when it next changes. Recomputed from the details.
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
