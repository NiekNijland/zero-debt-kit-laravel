#!/usr/bin/env bash

# Before every push: the parts of the checks step, side by side, in this one place, so CI only confirms what already ran here.
# Copy to scripts/prepush.sh. Run it where the pipeline's tools are installed (the pipeline's own image: baselines and
# analyser results depend on the extensions of the machine). It writes no file and commits nothing.
#   bash scripts/prepush.sh [--main]

set -euo pipefail

MAIN="${1:-}"
ARGS=()
for part in $(bash scripts/checks.sh --list-parts); do
    ARGS+=("$part::bash scripts/checks.sh $MAIN --part $part")
done

bash scripts/ci-parallel.sh "${ARGS[@]}"
