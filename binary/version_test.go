package yeswiki

import (
	"encoding/json"
	"os"
	"strings"
	"testing"
)

// A checkout has no BUILD.json in binary/program: only build-static.sh writes one. The contract
// that matters here is that asking is safe, and that the answer says which case it is in.
func TestABinaryWithNoManifestSaysSoRatherThanInventingOne(t *testing.T) {
	if _, err := os.Stat("program/BUILD.json"); err == nil {
		t.Skip("this tree carries a built manifest, so there is no missing case to test")
	}

	build, stated := BuildInfo()
	if stated {
		t.Fatal("BuildInfo() claimed a manifest that is not in the Program")
	}
	if build.Version != Version {
		t.Fatalf("a binary with no manifest still knows its version: got %q, want %q", build.Version, Version)
	}
}

// build-static.sh writes the manifest with php, and Go reads it back. The two agree on the field
// names or a shipped binary answers `version --build` with an empty object and nothing notices.
func TestTheManifestBuildStaticWritesIsTheOneGoReads(t *testing.T) {
	written := `{
	  "version": "5.0.0-alpha1",
	  "commit": "828581729ab",
	  "frankenphp": "1.12.7",
	  "php": "8.4.24",
	  "arch": "x86_64",
	  "extensions": ["gd", "opcache"],
	  "extension_libs": ["freetype"],
	  "caddy_modules": ["github.com/dunglas/caddy-cbrotli"],
	  "compressed": true,
	  "sha256": "abc",
	  "bytes": 73400320
	}`

	var build Build
	if err := json.Unmarshal([]byte(written), &build); err != nil {
		t.Fatal(err)
	}

	for name, got := range map[string]string{
		"version":    build.Version,
		"commit":     build.Commit,
		"frankenphp": build.FrankenPHP,
		"php":        build.PHP,
		"arch":       build.Arch,
		"sha256":     build.SHA256,
	} {
		if got == "" {
			t.Errorf("%s did not survive the round trip, so the json tag does not match what build-static.sh writes", name)
		}
	}
	if len(build.Extensions) != 2 || build.Extensions[0] != "gd" {
		t.Errorf("extensions did not survive the round trip: %v", build.Extensions)
	}
	if len(build.ExtensionLibs) != 1 || len(build.CaddyModules) != 1 {
		t.Errorf("extension_libs or caddy_modules did not survive: %v %v", build.ExtensionLibs, build.CaddyModules)
	}
	if !build.Compressed || build.Bytes == 0 {
		t.Errorf("compressed or bytes did not survive: %v %d", build.Compressed, build.Bytes)
	}
}

// The pin is the whole reason two builds of one tag ship one interpreter. A minor here would let
// `spc download --with-php=8.4` resolve to whatever is current on the day.
func TestTheBuildPinsAPatchVersionOfPHP(t *testing.T) {
	script, err := os.ReadFile("build-static.sh")
	if err != nil {
		t.Fatal(err)
	}

	line := ""
	for _, candidate := range strings.Split(string(script), "\n") {
		if strings.HasPrefix(candidate, "PHP_VERSION=") {
			line = candidate

			break
		}
	}
	if line == "" {
		t.Fatal("build-static.sh no longer sets PHP_VERSION")
	}
	if strings.Count(line, ".") < 2 {
		t.Errorf("PHP_VERSION is not pinned to a patch: %s", line)
	}
}
