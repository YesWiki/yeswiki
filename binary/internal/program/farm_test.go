package program

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func wikiAt(t *testing.T, farm, name, baseURL string) string {
	t.Helper()

	directory := filepath.Join(farm, name)
	if err := os.MkdirAll(directory, 0o755); err != nil {
		t.Fatal(err)
	}
	configuration := "<?php\nreturn [\n  'base_url' => '" + baseURL + "',\n];\n"
	if err := os.WriteFile(filepath.Join(directory, "yeswiki.config.php"), []byte(configuration), 0o644); err != nil {
		t.Fatal(err)
	}

	return directory
}

func TestAFarmIsTheWikisInIt(t *testing.T) {
	farm := t.TempDir()
	wikiAt(t, farm, "beta", "https://beta.example.org/?")
	wikiAt(t, farm, "alpha", "https://alpha.example.org/?")

	wikis, skipped, err := Wikis(farm)
	if err != nil {
		t.Fatal(err)
	}
	if len(wikis) != 2 {
		t.Fatalf("expected two wikis, got %v", wikis)
	}
	if wikis[0].Host != "alpha.example.org" || wikis[1].Host != "beta.example.org" {
		t.Fatalf("wikis are not in a settled order: %v", wikis)
	}
	if len(skipped) != 0 {
		t.Fatalf("nothing should have been skipped: %v", skipped)
	}
}

// A half-created directory is not a wiki yet, and a farm that refuses to start.
func TestWhatIsNotAWikiIsSkippedAndNamed(t *testing.T) {
	farm := t.TempDir()
	wikiAt(t, farm, "real", "https://real.example.org/?")
	if err := os.MkdirAll(filepath.Join(farm, "halfway"), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(farm, "notes.txt"), []byte("hello"), 0o644); err != nil {
		t.Fatal(err)
	}

	wikis, skipped, err := Wikis(farm)
	if err != nil {
		t.Fatal(err)
	}
	if len(wikis) != 1 {
		t.Fatalf("expected the one real wiki, got %v", wikis)
	}
	if len(skipped) != 1 || !strings.Contains(skipped[0], "halfway") {
		t.Fatalf("the half-created directory should be named once: %v", skipped)
	}
}

func TestTwoWikisCannotClaimOneName(t *testing.T) {
	farm := t.TempDir()
	wikiAt(t, farm, "one", "https://same.example.org/?")
	wikiAt(t, farm, "two", "https://same.example.org/?")

	if _, _, err := Wikis(farm); err == nil {
		t.Fatal("two wikis on one hostname must not start")
	} else if !strings.Contains(err.Error(), "same.example.org") {
		t.Fatalf("the error should name the hostname: %v", err)
	}
}

func TestTheHostIsWhateverShapeTheBaseUrlIsWrittenIn(t *testing.T) {
	for stated, expected := range map[string]string{
		"https://wiki.example.org/?":      "wiki.example.org",
		"http://wiki.example.org/wiki/?":  "wiki.example.org",
		"wiki.example.org/?":              "wiki.example.org",
		"https://wiki.example.org:8443/?": "wiki.example.org",
		"https://WIKI.example.org/?":      "wiki.example.org",
		"":                                "",
		"/?":                              "",
	} {
		if got := HostOf(stated); got != expected {
			t.Errorf("HostOf(%q) = %q, want %q", stated, got, expected)
		}
	}
}

// The site address keeps what the wiki said.
func TestTheAddressKeepsTheSchemeAndThePort(t *testing.T) {
	for stated, expected := range map[string]string{
		"https://wiki.example.org/?":      "wiki.example.org",
		"wiki.example.org/?":              "wiki.example.org",
		"http://wiki.example.org/?":       "http://wiki.example.org",
		"http://wiki.example.org:8080/?":  "http://wiki.example.org:8080",
		"https://wiki.example.org:8443/?": "wiki.example.org:8443",
	} {
		if _, got := AddressOf(stated); got != expected {
			t.Errorf("AddressOf(%q) = %q, want %q", stated, got, expected)
		}
	}
}

func TestAWikiWithNoHostToServeIsSkipped(t *testing.T) {
	farm := t.TempDir()
	wikiAt(t, farm, "pathonly", "/?")

	wikis, skipped, err := Wikis(farm)
	if err != nil {
		t.Fatal(err)
	}
	if len(wikis) != 0 {
		t.Fatalf("a base_url with no host cannot be a site address: %v", wikis)
	}
	if len(skipped) != 1 || !strings.Contains(skipped[0], "names no host") {
		t.Fatalf("the reason should say what is wrong: %v", skipped)
	}
}
