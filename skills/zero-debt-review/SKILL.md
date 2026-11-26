---
name: zero-debt-review
description: Cold review of a zero-debt guard, a ratchet change or a cleanup pull request with the kit's reviewer brief (break sample, object-level access checklist). Use when asked to review a guard or a PR of a zero-debt run.
disable-model-invocation: true
argument-hint: "<branch, PR or path of the guard>"
---

# zero-debt: cold review

The kit is the folder `${CLAUDE_PLUGIN_ROOT}`. The target to review is `$ARGUMENTS`; ask if it is empty.

Read `${CLAUDE_PLUGIN_ROOT}/09-reviewer-brief.md` and follow it. It says what you may and may not do: you never edit the branch, push, comment on, approve, decline or merge, and everything you run, you run in a detached worktree.

- For a branch or PR: review `git diff <base>...<branch>` against the target pattern in the repository's `AGENTS.md` and the review rubric. Form your own conclusion from the code, not from the builder's summary.
- For a guard: check that it has failing examples, passing examples and a case over the real tree, then run the break sample (break the code in a few places and expect a red test). The shape of a guard is `${CLAUDE_PLUGIN_ROOT}/04-guards.md` C1.
- For a ratchet change: read `${CLAUDE_PLUGIN_ROOT}/tools/README.md` ("Adding a row") and check the row has self-test cases.

Report findings ranked by severity with file and line evidence. For an audit at the end of the program, use the auditor part of the brief.
