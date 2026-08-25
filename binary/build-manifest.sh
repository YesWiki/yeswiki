#!/usr/bin/env bash
# What a binary was built from, written into the Program so the executable can be asked rather
# than guessed at (single-binary 02).
#
# Sourced by build-static.sh and build-dynamic.sh. `spc` resolves its dependency sources at build
# time with no lockfile -- and asks the GitHub API for their versions -- so two builds of one tag
# are not bit-identical. Stating the inputs is what can be done instead of pretending they are.

# write_build_manifest <repo> <frankenphp> <php> <arch> <extensions> <libs> <caddy-modules> <compressed> <linkage>
write_build_manifest() {
    local repo="$1"
    php -r '
        echo json_encode([
            "version" => trim(file_get_contents($argv[1])),
            "commit" => trim($argv[2]),
            "frankenphp" => $argv[3],
            "php" => $argv[4],
            "arch" => $argv[5],
            "extensions" => $argv[6] === "" ? [] : explode(",", $argv[6]),
            "extension_libs" => $argv[7] === "" ? [] : explode(",", $argv[7]),
            "caddy_modules" => array_values(array_filter(explode(" ", $argv[8]), fn ($word) => $word !== "" && $word !== "--with")),
            "compressed" => $argv[9] === "1",
            "linkage" => $argv[10],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
    ' \
        "$repo/binary/program/VERSION" \
        "$(git -C "$repo" rev-parse HEAD 2>/dev/null || echo unknown)" \
        "$2" "$3" "$4" "$5" "$6" "$7" "$8" "$9"
}

# Add what only exists once the artefact is built.
# stamp_build_manifest <manifest> <binary>
stamp_build_manifest() {
    php -r '
        $manifest = json_decode(file_get_contents($argv[1]), true);
        $manifest["sha256"] = hash_file("sha256", $argv[2]);
        $manifest["bytes"] = filesize($argv[2]);
        echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
    ' "$1" "$2"
}
