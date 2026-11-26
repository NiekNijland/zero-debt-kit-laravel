---
name: zero-debt-doctor
description: Check a project's copy of the zero-debt scripts against a kit checkout with the kit's doctor script and explain the result. Use when asked whether a project's scripts are current, drifted or edited.
disable-model-invocation: true
argument-hint: "[kit path, default: the plugin root]"
---

# zero-debt: doctor

The doctor script is `${CLAUDE_PLUGIN_ROOT}/tools/doctor.sh`. A kit release that predates it does not have the file: if it is missing, say so and stop. The line that names the kit version and commit needs a git clone of the kit; from the plugin cache it is absent.

Run it from the root of the target repository, with the kit path as `--kit`:

```bash
bash scripts/doctor.sh --kit ${CLAUDE_PLUGIN_ROOT}
```

If `scripts/doctor.sh` is not in the project yet, run the kit's copy: `bash ${CLAUDE_PLUGIN_ROOT}/tools/doctor.sh --kit ${CLAUDE_PLUGIN_ROOT}`. When the user gave a kit path (`$ARGUMENTS`), use it instead of the plugin root.

Then explain the output in plain words: which vendored script differs from the kit and how (the config block only, or a changed body), which rows were added or redefined, and what the kit's `UPGRADING.md` says to do about each. Do not edit the project's scripts: they are guarded files that need the `[ratchet: script]` tag and a code-owner review. Propose the change and let the lead make it.
