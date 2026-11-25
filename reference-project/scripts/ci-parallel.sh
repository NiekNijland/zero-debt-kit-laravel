#!/bin/bash

set -uo pipefail

CHECK_TIMEOUT="${CI_CHECK_TIMEOUT:-600}"
LOG_DIR="$(mktemp -d)"
NAMES=()

for task in "$@"; do
    name="${task%%::*}"
    command="${task#*::}"
    NAMES+=("$name")
    (
        start=$(date +%s)
        timeout -k 10 "$CHECK_TIMEOUT" bash -c "$command" > "$LOG_DIR/$name.log" 2>&1
        status=$?
        seconds=$(( $(date +%s) - start ))
        if [ "$status" -eq 137 ] && [ "$seconds" -ge "$CHECK_TIMEOUT" ]; then
            status=124
        fi
        echo "$status $seconds" > "$LOG_DIR/$name.tmp"
        mv "$LOG_DIR/$name.tmp" "$LOG_DIR/$name.status"
    ) &
done

FAILED=0
PENDING=${#NAMES[@]}

while [ "$PENDING" -gt 0 ]; do
    wait -n || break
    for name in "${NAMES[@]}"; do
        if [ ! -f "$LOG_DIR/$name.status" ] || [ -f "$LOG_DIR/$name.printed" ]; then
            continue
        fi
        touch "$LOG_DIR/$name.printed"
        PENDING=$(( PENDING - 1 ))
        read -r status seconds < "$LOG_DIR/$name.status"
        if [ "$status" -eq 124 ]; then
            echo "=== $name timed out after ${CHECK_TIMEOUT}s, output so far:"
        elif [ "$status" -eq 137 ]; then
            echo "=== $name killed after ${seconds}s (exit 137, likely out of memory), output so far:"
        else
            echo "=== $name (${seconds}s, exit $status)"
        fi
        cat "$LOG_DIR/$name.log"
        if [ "$status" -ne 0 ]; then
            FAILED=1
        fi
    done
done

echo
for name in "${NAMES[@]}"; do
    read -r status seconds < "$LOG_DIR/$name.status"
    if [ "$status" -eq 0 ]; then
        echo "PASS     ${seconds}s  $name"
    elif [ "$status" -eq 124 ]; then
        echo "TIMEOUT  ${seconds}s  $name"
    else
        echo "FAIL     ${seconds}s  $name"
    fi
done

exit "$FAILED"
