#!/usr/bin/env bash
# Assemble the Program tree that gets embedded into the binary.
set -euo pipefail

repo="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
target="$repo/binary/program"

keep="$(cat "$target/.gitkeep" 2>/dev/null || true)"
rm -rf "$target"
mkdir -p "$target"
printf '%s\n' "$keep" > "$target/.gitkeep"

for entry in src vendor templates themes javascripts styles extensions docs composer.json composer.lock index.php worker.php; do
    if [ -e "$repo/$entry" ]; then
        cp -a "$repo/$entry" "$target/$entry"
    fi
done

printf '%s\n' "$(git -C "$repo" describe --tags --always --dirty 2>/dev/null || echo dev)" > "$target/VERSION"

rm -rf "$target/extensions"/*/node_modules
find "$target" -name '.git' -prune -exec rm -rf {} + 2>/dev/null || true

printf 'assembled %s\n' "$target"
du -sh "$target"
