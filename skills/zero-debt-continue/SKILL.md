---
name: zero-debt-continue
description: Continue an existing zero-debt cleanup run from its hand-off notes: read CONTINUE.md, check reality (fetch, open PRs, CI state), then resume the next step. Use when a zero-debt run is already under way.
disable-model-invocation: true
argument-hint: "<TICKET> <hand-off folder>"
---

# zero-debt: continue a run

The kit is the folder `${CLAUDE_PLUGIN_ROOT}`. Arguments: `$ARGUMENTS` (the ticket, then the hand-off folder). Ask for whichever is missing.

This is the "To continue an existing run" paragraph of `${CLAUDE_PLUGIN_ROOT}/00-START-PROMPT.md`:

1. Read `CONTINUE.md` in the hand-off folder first.
2. Read the kit's `07-process.md` section 1.
3. Check reality before trusting any status in the notes: fetch, list the open PRs, read the CI state, compare the branch with the notes.
4. Re-read only the part of `05-program.md` D2 that the current step names, and the catalog tables `CONTINUE.md` lists.
5. Resume the current step. Keep the hand-off files current after every merge. Stop for the owner's go after steps 1, 3 and 10 and whenever a decision is theirs.

The rules of `00-START-PROMPT.md` still hold: guards only shrink, no hand-added exception to make a build green, never touch production, never print or store secrets, never force push, and no push, PR, merge or message to anyone unless the owner's answers in the project profile allow it.
