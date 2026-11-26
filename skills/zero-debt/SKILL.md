---
name: zero-debt
description: Start a zero-debt cleanup of a Laravel (and React) repository: ask the step 1 questions, measure, write the four hand-off files, then run the ten-step program with CI guards and ratchets. Use when the user asks to start, plan or adopt the zero-debt kit in a repository.
disable-model-invocation: true
argument-hint: "<TICKET>"
---

# zero-debt: start a run

The kit is the folder `${CLAUDE_PLUGIN_ROOT}`. Every file named below is in it. Do not copy their content into this chat; read them from there, when the list says so.

The ticket for every commit and PR is `$ARGUMENTS`. If that is empty, ask for it. If the project has no ticket system, the ticket is the fixed prefix `NOTICKET`.

Follow `${CLAUDE_PLUGIN_ROOT}/00-START-PROMPT.md` as if the user had pasted it, with these values: the kit path is `${CLAUDE_PLUGIN_ROOT}`, and the ticket is the one above. In short:

1. Read only the kit's `README.md`, `01-principles.md` and `02-step-1-discover.md`, and the target repository's own `AGENTS.md`, `CLAUDE.md` and agent memory.
2. Ask the questions of `02-step-1-discover.md` 1.1 in one round, before measuring anything. Include the hand-off folder (outside the repository) in that round.
3. Run step 1 only: no code change, no PR, no push. Produce the four hand-off files (`project-profile.md`, `backlog.md`, `zero-debt-plan.md`, `CONTINUE.md`) from the templates in `${CLAUDE_PLUGIN_ROOT}/templates/`.
4. Come back with those files and every owner decision the program needs, in one round. The owner signs the definition of clean.
5. Only after that, work through steps 2 to 10 of `05-program.md`, stopping for the owner's go after steps 1, 3 and 10.

Rules from the first minute: guards only shrink; no exception entry added by hand to make a build green; never touch production; never print or store secrets; never force push; no push, PR, merge or message to anyone before the owner has seen the step 1 result.

The numbered files are the method. When `00-START-PROMPT.md` and this file differ, `00-START-PROMPT.md` wins. To continue a run later use `/zero-debt:zero-debt-continue`; to review a guard or a PR use `/zero-debt:zero-debt-review`.
