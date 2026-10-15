#!/usr/bin/env bash
# Print the version a git checkout runs, named the way yeswiki-build-repo names a build:
# the tag without its "v" on a tagged commit, otherwise the last commit's date and how many commits that day had.
set -euo pipefail

cd "${1:-.}"

if tag=$(git describe --tags --exact-match 2>/dev/null); then
    printf '%s\n' "${tag//v/}"
    exit 0
fi

day=$(git log --pretty=%cd --date=short -1 .)
count=$(git log --pretty=%cd --date=short --after="$day 00:00" --before="$day 23:59" . | wc -l)
printf '%s-%s\n' "$day" "$((count))"
