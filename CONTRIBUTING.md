# Contributing

Thank you for helping. The kit is one standard for one kind of project (Laravel and React); contributions that make it more correct, more verifiable or more generic are welcome. Read `AGENTS.md` for the repository map and the rules; they apply to people too.

## Ground rules

- **Generic.** No project, organisation, ticket, person, address or token in any file. Use the example domain of `reference-project/` (a small web shop).
- **English**, short and concrete. A claim must be checkable in the repository, or marked as unverified.
- **One standard.** A proposal that adds a smaller or optional version of done will be declined.
- **Honest scope.** If you ran something, say what, where and what happened. If you did not, say so.

## Propose a guard or a ratchet row

1. **Measure first.** `04-guards.md` C7 is the procedure: write the detector, count today's violations over a real tree, and decide by the count (zero: build the guard; a handful: fix and build; more: a counted list as a ratchet row; every site needs its own answer: a decision list). Open an issue with the "Guard or row proposal" template and put the count, the command that produced it and the kind of project it was measured on.
2. **Name the rule** the way the catalog does: per task, the one way, how the old way is counted, the guard. Add it to the right file in `catalog/` and to the tables in `README.md`.
3. **A row** (`tools/ci-ratchets.sh`): a name, a direction, a method, paths, an expression. It needs self-test cases (what it must count, what it must ignore) and a line in `tools/README.md`, "The rows that ship". The README row names must match the table in the script.
4. **A proof scenario is required** for any change to a script in `tools/` or `ci/`, in `tools/proof/scenarios.d/`. It must fail without your change. See `AGENTS.md`, "Guarded by the proof".
5. **A PHP guard** follows `04-guards.md` C1: a detector class, a thin test with failing examples, passing examples and one case over the real tree. Put it under `reference-project/` with illustrative names.

## Genericness check before you open a pull request

- Search your diff for names of real products, companies, hosts, tickets and people: none.
- No secret, token or internal URL, not even in an example.
- Names in examples come from the example domain.

## Other contributions

- A real run: what the kit got wrong or missed on your project is the most useful report. Use the "Run report" issue template; remove anything private.
- Typos and clarity fixes: send the pull request directly.

By contributing you agree that your contribution is released under the MIT License of this repository. Everyone taking part is expected to follow `CODE_OF_CONDUCT.md`. Security issues go through `SECURITY.md`, not a public issue.
