# Green CI is not a codebase with a shape

*Draft. Check every claim against the README before publishing; counts change with each release.*

You can have high coverage and PHPStan at level 8 and still end up with three ways to authorize a route, four ways to write a test helper and a new pattern with every pull request. A linter checks lines, not structure. When AI agents write a growing share of the code, the drift gets faster, because each agent picks a way that looks reasonable from where it stands.

I built a kit for this, for Laravel and React applications, and I am publishing it under the MIT license. This is what it is, what has been shown to work, and what has not.

## Two mechanisms

**Architecture tests** decide, per task, the one allowed way: where a class lives, what it may depend on, how a controller or a test is shaped. Code that does it another way fails the build, and the message says what to do instead. These are ordinary PHP tests on a shared parser, with failing examples, passing examples and a case over the real tree, so a blind spot shows up as a missing example.

**Ratchets** are counters for everything that is still wrong: old violations, baselines, suppressions, skipped tests, oversized files. A counter may only go down. A script compares each counter at the merge base, using the base's own definition, with the same counter at the head. If a number moves the wrong way, the build fails unless a commit names the row and gives a reason. A row at zero takes no override (three named rows are the exception), and at zero the list is deleted and CI fails if it comes back. There is no results file to merge. A rise needs a tagged reason that a code owner reads.

Together they are the guards. When CI is green the code fits the patterns the guards check. A guard only checks what its tests show it can see, and review covers the rest.

## The program around it

Guards alone are a gate, not a cleanup. The kit is also a program you hand to an agent in your repository. It measures the codebase, writes down the one way per task with a file to copy, puts a guard in CI for every rule and burns the old way down to zero in ten steps. You sign off at three gates and answer the questions that are yours: the definition of clean, the patterns, the end state. The agent does the rest. There is one standard and no lighter version of done.

You can also take only the core: the ratchet script and three guards. The README says plainly what you do not get this way. The program installs as a Claude Code plugin whose skills point at the kit's numbered documents.

## What is proven

- The shell tools are exercised by a proof suite that builds scratch repositories and runs several hundred scenarios (the suite counts scenarios, not features; the README has the count), plus 224 regex cases that prove each counting row counts the spellings it must and ignores the near misses. The proof runs no real analyser, formatter or CI host.
- The program has been run on three Laravel applications, all hosted on Bitbucket, and the Bitbucket pipeline skeleton ran in real pipelines there. The lessons of each run were written back into the kit. I am not publishing what those runs measured.

## What is not proven

- A run on a real GitHub repository is in progress; until it finishes, treat the GitHub skeleton as unverified. It is proved on examples only.
- GitLab is untested.
- Nothing outside Laravel and React has been run. The catalog maps every row to other stacks on paper.
- The mutation forms for Pest, Infection and Stryker were run on small scratch projects (Pest 5.3.7, Infection 0.35.12, Stryker 10.1.1); only the Pest form also ran on a sample of a real application. Infection with Xdebug is not verified.
- The reference is a small running starter (a notes API), pinned to a kit commit; copy from it by `reference-project/files-to-copy.md`, and read what it lacks in `docs/starter-variants.md`.
- The plugin install path from a marketplace had not been exercised at the time of writing this draft.

## What it is not

It is not a replacement for PHPStan, Rector, Pint, Deptrac or Pest's arch presets. It assumes tools like them and holds them to their strictest setting. The scripts are copied into your repository, not installed. Upgrading is a pull request: `tools/doctor.sh` compares your copies with the kit and can carry the new bodies over (`UPGRADING.md`). It decides patterns and structure, not product questions, and it comes from one person's runs: expect rough edges.

## Try it, or break it

The repository is at https://github.com/NiekNijland/zero-debt-kit-laravel. The shortest way to see the effect is the minimal adoption path in the README: one script, three guards. If you run it on a project, the "Run report" issue template is the most useful thing you can send me, especially for a stack or a CI host that has not been run yet.
