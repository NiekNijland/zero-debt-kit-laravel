# FAQ

Comparisons below come from public documentation and general knowledge, not from running every tool next to the kit. Where I could not verify a point, it says so. If a statement about another tool is wrong, please open an issue.

## Why not just Deptrac, PHPArkitect or Pest arch?

You should use them where they fit: they are good at what the kit calls architecture tests of class shape and layer dependencies. Deptrac and PHPArkitect are Composer dev dependencies (also shipped as PHARs) with rules in a config file; Pest's `arch()` ships presets (`laravel()`, `php()`, `security()`, `strict()`, `relaxed()`) with `ignoring()` for exceptions. These are from their documentation as of this writing; I did not re-check every option.

What the kit adds is not a better dependency checker. It is the program around the guards: deciding the one way per task, writing it down with a file to copy, burning the old way down to zero with counters, and a contract test over the CI files so the pipeline cannot be narrowed. Its guards are plain PHP tests on a shared parser (the starter, `reference-project/README.md`), so you can build the same rule in Pest arch or Deptrac if you prefer; the kit's rule is that every guard has failing examples, passing examples and a real-tree case, and that a guard for a rule exists in CI at all. Not verified: whether Pest arch can express every guard in the starter (probably not the ones that read the guides or the CI files).

## Why not Betterer?

Betterer is a ratchet for JavaScript and TypeScript projects: it commits a results file and fails when results get worse; `betterer merge` resolves conflicts in that file (from its documentation). The kit's ratchet is the same idea with another mechanism: it counts every row at the merge base (with the base's own definition) and at the head, so there is no results file to conflict. It counts PHP, config files, JSON and CI files too, and an increase needs a tagged commit unless the row is at zero, where it takes none (three named rows excepted). The cost: it is a shell script you copy and adapt, not a package you install, and it has no editor integration. Not verified: how Betterer behaves on a very large repository compared with the script.

## Why not SonarQube "clean as you code"?

Closest in spirit (a gate on new code against a reference), from general knowledge, not tested here. SonarQube judges new code by its rules and quality gate and is a service you run or buy. The kit gates on counters that cover the whole tree and have a direction, plus structure tests written for your rules, and it has no server. It does not replace a general analyser; it holds whatever analysers you use to their strictest setting and counts every way around them (baselines, ignores, skips).

## Why not just PHPStan at a high level and Rector?

Use them; the kit assumes them. A level is a setting, not a ratchet: nothing stops a baseline entry or an inline ignore from being added next week. The kit's rows count those, so strictness cannot be bought back with an exception.

## Is it a Composer package?

No. It is a program an agent runs, with shell tools you copy. A package for the guards and a versioned, checksum-locked script is an idea under consideration, not a promise. The guards are those of a running starter (`reference-project/README.md`); copy them from there, after the starter's `KIT-FINDINGS.md`.

## Does it work for stacks other than Laravel and React?

The method and the shell tools are stack-neutral; the catalogs, configs and reference are Laravel and React. `catalog/other-stacks.md` maps the rows on paper. Nothing outside Laravel and React has been run.

## Does it work on GitHub and GitLab?

The skeletons for both exist, with a contract reader proved on examples. The program has been run on three Laravel applications hosted on Bitbucket. A GitHub run is pending; GitLab is untested (`README.md`, "What is proven and what is not").

## Can the ratchet be bypassed?

By a person with review rights, yes: the override tag is written by the author, which is why the files that carry the guards are covered by a code-owners rule that names a person or team. A pull request cannot rewrite its own gate, because `ci/checks.sh` runs the base's copy of the script. See `SECURITY.md` for the methods that execute pull-request code.

## Do I need an agent?

No. The ratchet script and the guards are ordinary files; "Minimal adoption" in `README.md` needs no agent. The ten-step program is written for an agent to run, with a human lead, but a team could follow it by hand.

## How do I install it in Claude Code?

See "Install as an agent plugin" in `README.md`. The marketplace install path had not been exercised when this was written; the plugin files validate locally with `claude plugin validate .`.

## Is there a time estimate?

No, and none is invented here. Step 1 produces a measured plan for your repository.
