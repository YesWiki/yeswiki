package yeswiki

import (
	"encoding/json"
	"os"
	"strings"
	"testing"
)

// A checkout has no BUILD.json: asking is safe, and the answer says which case it is in.
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

// build-static.sh writes the manifest with php and Go reads it back, so the field names must agree.
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

// A patch pin is what makes two builds of one tag ship one interpreter.
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

// The build injects the tag into the Program's composer.json; a checkout carries none and is dev.
func TestTheVersionIsTheOneComposerJsonStates(t *testing.T) {
	for manifest, want := range map[string]string{
		`{"name": "yeswiki/yeswiki", "version": "5.2.0", "extra": {"yeswiki": {"release-line": "ectoplasme"}}}`: "5.2.0",
		`{"version": " 5.0.0-alpha1-40-gc56a29d59-dirty\n"}`:                                                    "5.0.0-alpha1-40-gc56a29d59-dirty",
		`{"name": "yeswiki/yeswiki", "extra": {"yeswiki": {"release-line": "ectoplasme"}}}`:                     "dev",
		`{"version": ""}`: "dev",
		`not json`:        "dev",
	} {
		if got := versionFromManifest([]byte(manifest)); got != want {
			t.Errorf("versionFromManifest(%s) = %q, want %q", manifest, got, want)
		}
	}
}
