# Upgrading

A project copies the kit's scripts into its own `scripts/` folder and sets the config block of each. The kit has a `VERSION` file
and a `CHANGELOG.md`; the project has no record of which version its copies came from. `tools/doctor.sh` finds out, and does the
upgrade without touching what the project decided.

## What belongs to whom

| Part of a script | Owner | Upgrade |
|---|---|---|
| The body (everything outside the three parts below) | the kit | replaced by the kit's new body |
| The config block (`# >>> config` to `# <<< config`) | the project | kept: the project's values go back in |
| The ratchet table of `ci-ratchets.sh` (`cat <<'TABLE'`) | the project | kept; the rows the kit added are listed, not copied |
| The task commands of `checks.sh` (`"Name::command"` lines) | the project | kept |

A row is added to the table, or a config key is set, by a decision in a pull request; an upgrade never makes it silently.

## Move a project from version A to version B

1. Update your clone of the kit to version B (`git -C <kit> checkout v<B>`, or pull). Read `CHANGELOG.md` from A to B: it names the
   scripts that changed, the config keys that are new, and the rows that are new.
2. In the project, from a branch: `bash scripts/doctor.sh --kit <kit>` (or, from the kit, `bash tools/doctor.sh --project <project>`).
   Read the report:
   - `identical`, `config-differs`: nothing to do; the listed values are the project's own decisions.
   - `body-differs`: the copy is older than B or edited locally. With the kit as a git clone, the line `Kit version` says which
     kit version and commit the copies equal (`matches kit v0.9.0 / commit abc1234`), or `diverged: body edited locally`: read
     the diff summary before you overwrite a local edit, and put it into the kit as a change, or into the config block, if it
     was a need.
   - `config keys only in the kit`: B added a key; the script needs a value (the kit's default is used until you set it).
   - `missing`: a script the kit has and the project has not; the optional ones are only listed.
   - `ratchet rows of the kit that the project does not have`: decide per row (a new row is a pull request with its own cap).
3. `bash scripts/doctor.sh --kit <kit> --upgrade-plan` prints the steps and changes nothing. Add `--apply` to carry them out: the
   kit's body, with the project's config values, table rows and task commands put back, is written under `scripts/` only (a
   temporary file, verified to equal the kit's body and to parse, then renamed). A script without config block markers is not
   touched: do it by hand. Run the doctor again: it must say `matches kit v<B>` and exit 0 (config values that differ are fine).
4. Set the config keys that B added, then re-run: `bash scripts/ci-ratchets.sh --self-test`, `bash scripts/checks.sh --part gate`
   and the project's own checks.
5. Commit and open the pull request.

## The guard: tag and code owner

Every script under `scripts/` is a guarded file by default (`GUARDED_FILES` in the config block of `ci-ratchets.sh`, and the
code-owners files). Changing one without the tag fails the ratchet step. The upgrade is such a change:

- the commit that changes the scripts carries the line `[ratchet: script] upgrade the kit scripts from v<A> to v<B>` (or the
  reason in your words; a reason is required), and
- a code owner approves the pull request (`ci/CODEOWNERS.example`; contract point 22). The author of the upgrade does not approve it.

The ratchet step compares the scripts with the base branch: a change of `ci-ratchets.sh` outside its table, and a change of any other guarded script, needs the tag (the config block of `ci-ratchets.sh` counts as outside the table).

## What to run

| Who | What |
|---|---|
| A project, after the upgrade | `bash scripts/doctor.sh --kit <kit>`, `bash scripts/ci-ratchets.sh --self-test`, then the project's checks |
| A kit maintainer, while developing | `PROOF_ONLY='^DX' bash tools/proof/proof.sh /bin/bash` (the filter for the area; `PROOF_LIST=1` lists the ids) |
| A kit maintainer, before a release | the full proof, `bash tools/proof/proof.sh /bin/bash`, with 0 wrong; the kit's own CI (`.github/workflows/proof.yml`) runs it on Linux and macOS on a pull request, on `main` and on a tag |

## Releasing the kit (maintainers)

Move the `[Unreleased]` entries of `CHANGELOG.md` under the new version and date, set `VERSION`, run the full proof, commit, tag `v<version>`.
A change of a script's behaviour or of a config key is a minor version at least, so a project can see in the changelog what it must set.
