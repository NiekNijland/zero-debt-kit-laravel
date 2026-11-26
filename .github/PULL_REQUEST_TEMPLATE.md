## What and why

<!-- One or two sentences. Link the issue if there is one. -->

## Checks

- [ ] Generic: no project, organisation, ticket, person, address or token in the diff
- [ ] Every claim I added is checkable in the repository, or marked as unverified
- [ ] A change to a script in `tools/` or `ci/` has a proof scenario in `tools/proof/scenarios.d/` that fails without it
- [ ] I ran the proof (or `PROOF_ONLY` for the area) and `bash tools/ci-ratchets.sh --self-test`; result:
- [ ] A new or renamed ratchet row is in `tools/README.md` and in the `README.md` tables, with the same name as in the script
- [ ] A new guard or row came with a count measured as `04-guards.md` C7 says: count and command:

## Not tested

<!-- What you could not run, and why. -->
