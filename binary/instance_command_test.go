package yeswiki

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/YesWiki/yeswiki/binary/internal/program"
)

// wiki writes just enough for Configured() to agree there is one here.
func wiki(t *testing.T, at string) string {
	t.Helper()
	if err := os.MkdirAll(at, 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(at, "yeswiki.config.php"), []byte("<?php\n$config = ['base_url' => 'http://x/?'];\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	return at
}

func TestAWikiIsFoundFromASubdirectory(t *testing.T) {
	root := t.TempDir()
	instance := wiki(t, filepath.Join(root, "mine"))
	deep := filepath.Join(instance, "custom", "templates")
	if err := os.MkdirAll(deep, 0o755); err != nil {
		t.Fatal(err)
	}

	found, ok := program.FindInstance(deep)
	if !ok {
		t.Fatal("a command run inside a wiki's own folders is about that wiki, the way git works")
	}
	if found != instance {
		t.Errorf("found %q, want %q", found, instance)
	}
}

func TestNothingIsFoundOutsideAWiki(t *testing.T) {
	if _, ok := program.FindInstance(t.TempDir()); ok {
		t.Error("a directory that is not a wiki must not resolve to one somewhere above it by accident")
	}
}

func TestTheInstanceFlagIsTakenOutOfWhatIsForwarded(t *testing.T) {
	for _, form := range [][]string{
		{"--instance", "/wikis/mine", "migrate", "--force"},
		{"--instance=/wikis/mine", "migrate", "--force"},
	} {
		stated, rest := takeInstanceFlag(form)
		if stated != "/wikis/mine" {
			t.Errorf("%v gave instance %q", form, stated)
		}
		if len(rest) != 2 || rest[0] != "migrate" || rest[1] != "--force" {
			t.Errorf("%v left %v to forward, want [migrate --force]", form, rest)
		}
	}
}

func TestArgumentsWithoutTheFlagAreForwardedWhole(t *testing.T) {
	stated, rest := takeInstanceFlag([]string{"migrate", "--force"})
	if stated != "" {
		t.Errorf("nothing named an instance, got %q", stated)
	}
	if len(rest) != 2 {
		t.Errorf("forwarded %v, want both arguments", rest)
	}
}

// A build with no PHP must say so rather than run itself again forever.
func TestANestedInvocationRefusesToForward(t *testing.T) {
	t.Setenv(forwardingGuard, "1")

	if !alreadyForwarding() {
		t.Fatal("the guard must see the environment the console is given")
	}
	if err := refuseToForward([]string{"migrate"}); err == nil {
		t.Error("forwarding from inside a forward is the recursion this guard exists to stop")
	}
}
