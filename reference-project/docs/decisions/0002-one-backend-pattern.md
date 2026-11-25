# One backend pattern

Status: accepted.

## Context

The backend had two layouts side by side. Part of the code sat in flat folders per kind of class, and the rest sat
in domain modules under `app/Modules`. New code went to both. The modules did not agree with each other either: the
same kind of class sat in differently named folders depending on the module, and some files sat in the module root.
The architecture test did not look inside the modules, so nothing stopped a new variant.

People and agents copy the code next to the code they change. With several patterns in view, each change adds to the
mix.

## Decision

Keep the module style: domain code goes in `app/Modules/<Domain>`, with a fixed set of folder names and a name
rule per folder. The framework layer stays flat like stock Laravel: controllers, requests, middleware, models,
policies and providers. The rules, and the one way to do each common task, are in `app/AGENTS.md`.

Domain helpers that are neither an Action (one `handle()` that changes state) nor a Service (a shared read or an
external client), such as parsers, trackers, per-call state and static helpers, go in a module `Support` folder under
plain noun names. Spatie `Data` is only for `#[TypeScript]` types and API DTOs; every other domain value is a
ValueObject. The module folders `Contracts`, `Data`, `Enums`, `Exceptions` and `ValueObjects` are pure layers and never
reach the database; `Support` may.

`tests/Unit/LayoutTest.php` enforces the folders and names. Code that broke the rules when the test arrived was
listed in an allowlist that only shrank; at zero the list was deleted.

## Consequences

The existing code moves into the pattern one domain per PR. Moving a class changes its stored name for audit events
(`events.name`) and database notifications (`notifications.type`), which needs a data migration in the same PR, and
for queued jobs, notifications and mailables, which keep their old name through a `class_alias` in
`MovedClassServiceProvider` for one release, so payloads queued, delayed or retried across the deploy still resolve.
