#!/bin/bash
R=/Users/nieknijland/Desktop/zero-debt-kit/.claude/worktrees/agent-a6a91a2d378be650b/reference-project
cd /fixref && rsync -a --delete --exclude vendor --exclude composer.json --exclude composer.lock --exclude phpunit.xml --exclude sync.sh $R/ ./
