# Security policy

## Report a vulnerability

Use GitHub's private vulnerability reporting: on the repository page open **Security**, then **Report a vulnerability**. Please do not open a public issue or pull request for a vulnerability. Give the file, the steps to reproduce and what an attacker gains. This is a one-person, volunteer project: expect an acknowledgement, not a service level.

## What is in scope

The kit is shell scripts, node scripts, CI skeletons, Markdown and example PHP. Bugs where a script runs more than it should, reads or writes outside the repository it is pointed at, leaks a secret into a log, or lets a pull request weaken its own gate are in scope.

## Documented risks (not vulnerabilities)

These are known and written down in `tools/README.md`; they are properties of the method:

- **Ratchet methods that execute PR code.** The `return`, `const` and `call` methods of `tools/ci-ratchets.sh` run PHP code from the pull request to count a row. On a runner that builds forks or holds secrets, use the other methods only.
- **The base's script gates the head.** `ci/checks.sh` runs the base branch's copy of the ratchet script, so a pull request cannot rewrite its own gate. The script tag (`[ratchet: script]`) is written by the author; only a code-owners rule that names a person or team, with the host requiring that review, tells a real reason from a written one. That setting lives on the host and is the project owner's.
- **Agents run with their own privileges.** A skill or start prompt is instructions an agent follows. Review a plugin's files before installing it, pin to a release tag (v0.10.0 once tagged) or a commit hash, and read a diff before updating.
- **The proof suite** builds scratch repositories and runs the scripts in them; it replaces the secret scan by `true` and runs no real analyser or CI host.

## Supported versions

The latest release. Fixes are not backported.
