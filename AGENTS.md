# AGENTS.md: working on the kit itself

This file is for an agent that maintains this repository. If you are running the kit on another repository, start at `README.md` or the `zero-debt` skill instead.

## Repo map

| Path | What it is |
| --- | --- |
| `00-START-PROMPT.md` to `10-hazards.md` | the method, in numbered files; the agent reads them in order |
| `record-source-project.md` | lessons of earlier runs and the instruction each became |
| `catalog/` | per task the one way, how to count the old way, the guard |
| `templates/` | files the agent creates in a project |
| `tools/` | the shell and node tools (`ci-ratchets.sh`, `debt-dashboard.sh`, ...); `tools/README.md` documents each |
| `tools/proof/` | the proof suite: `proof.sh` plus `scenarios.d/` |
| `ci/` | the pipeline skeletons (Bitbucket, GitHub, GitLab), `checks.sh`, `prepush.sh`, `CODEOWNERS.example` |
| `reference-project/` | the pointer to the strict Laravel starter (the worked example), `files-to-copy.md`, and a few examples that do not run |
| `.claude-plugin/`, `skills/` | the Claude Code plugin and marketplace, and the skills that point at the numbered files |
| `docs/` | adoption guide, FAQ, launch post draft |

## Rules

- **Generic.** The kit names no project, organisation, ticket, person, address or token. Examples use an example domain. The GitHub handle appears only in repository URLs, and the name in the copyright line of `LICENSE`.
- **English**, plain and short. No hype, no invented numbers: a claim in a README or doc must be checkable in the repository or marked as unverified.
- **No dates, estimates or priorities** in the method files (`README.md`, "Limits").
- **One standard.** Do not add a "lighter version of done".
- **The skills point, they do not copy.** A skill names the numbered files; it never repeats their content. When a numbered file changes, the skills should not need to.
- **Bash 3.2.** Scripts run with `bash` 3.2 or later, no GNU-only flags, no executable bit; `jq`, `node` and `php` are optional per method.
- **Docs and tools agree.** A ratchet row name in the README tables must match the table in `tools/ci-ratchets.sh`; counts quoted in prose (scenarios, self-test cases, row totals) must match what the scripts print. The scenario count is stated once, in `README.md`, as "more than N"; elsewhere say "the proof suite". The scenarios `DOCX01` to `DOCX07` fail when README, the launch post or the proof workflow claim more scenarios than exist, when `VERSION`, `.claude-plugin/plugin.json` and the newest `CHANGELOG.md` heading differ, or when the README ratchet tables and `tools/ci-ratchets.sh` name different rows.

## Run the proof

```bash
bash tools/proof/proof.sh                      # default bash
bash tools/proof/proof.sh /path/to/bash label  # another bash binary
PROOF_ONLY=<filter> bash tools/proof/proof.sh  # only the scenarios the filter selects
bash tools/ci-ratchets.sh --self-test          # the regex cases
```

`proof.sh` builds scratch repositories under a temporary folder (`PROOF_OUT` to choose it), prints the table and writes `proof-<label>.log`. It needs `git`, `jq`, `node` and, for some scenarios, `php`; the scenarios that need an absent tool are skipped and counted as skipped. A full run takes several minutes. `PROOF_ONLY=<regex on the scenario id>` runs one area (for example `PROOF_ONLY='^DX'`); `PROOF_LIST=1` lists the ids and runs nothing.

## Guarded by the proof

Every change to a script in `tools/` or `ci/` adds or updates a scenario in `tools/proof/scenarios.d/` that fails without the change. A change that only edits the script is not done. A new ratchet row also needs its self-test cases in `ci-ratchets.sh`, a row in `tools/README.md` and the README tables. After changing a script, run the proof (or the filter for the area) and the self-test, and quote the result.

## Commits and pushing

- Small logical commits with a message that says why. End every commit message with the trailer `Co-Authored-By: <the model that wrote it> <noreply@anthropic.com>`, as the session's instructions give it.
- Do not push, tag, open a pull request or publish a release without the owner's say. Do not edit the owner's checkout from a worktree.
- After the first push, create the issue labels `proposal` and `run-report` (the templates name them; a label that does not exist is dropped silently).
- Plugin changes: validate with `claude plugin validate .` and check the JSON with `node -e "JSON.parse(require('fs').readFileSync('<file>'))"`.
