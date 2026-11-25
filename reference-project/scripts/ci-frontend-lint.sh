#!/bin/bash

set -euo pipefail

[ -f node_modules/.package-lock.json ] || npm ci

NODE_OPTIONS=--max-old-space-size=5120 npx eslint --max-warnings 0 resources/js .storybook vitest.setup.ts vite.config.ts scripts
