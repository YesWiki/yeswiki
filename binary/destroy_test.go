package yeswiki

import (
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/YesWiki/yeswiki/binary/internal/commands"
	"github.com/YesWiki/yeswiki/binary/internal/program"
)

type destroyingPHP struct {
	arguments []string
	err       error
}

func (d *destroyingPHP) Console(_, _ string, arguments []string) error {
	d.arguments = arguments

	return d.err
}

func aWiki(t *testing.T, at string, host string) string {
	t.Helper()

	if err := os.MkdirAll(at, 0o755); err != nil {
		t.Fatal(err)
	}
	configuration := "<?php\nreturn ['base_url' => 'https://" + host + "/?'];\n"
	if err := os.WriteFile(filepath.Join(at, "yeswiki.config.php"), []byte(configuration), 0o644); err != nil {
		t.Fatal(err)
	}

	return at
}

func settingsFor(t *testing.T, directory string) commands.Options {
	t.Helper()

	return commands.Options{
		Directory:   directory,
		ProgramRoot: t.TempDir(),
		Version:     "4.5.0",
		Source:      Program(),
		Env:         func(string) string { return "" },
		Home:        func() (string, error) { return t.TempDir(), nil },
		Wd:          os.Getwd,
		Out:         func(string) {},
	}
}

// A --force is a flag people learn to type. Naming the wiki is a decision each time.
func TestDestroyingNeedsTheWikiNamed(t *testing.T) {
	wiki := aWiki(t, filepath.Join(t.TempDir(), "old"), "old.example.org")
	php := &destroyingPHP{}

	for _, wrong := range []string{"", "yes", "old", "OTHER.example.org"} {
		err := commands.Destroy(settingsFor(t, wiki), php, wrong, t.TempDir(), nil)
		if err == nil {
			t.Fatalf("%q should not have been accepted as confirmation", wrong)
		}
		if !strings.Contains(err.Error(), "old.example.org") {
			t.Errorf("the refusal should say what to type: %v", err)
		}
	}

	if php.arguments != nil {
		t.Error("nothing should have been run without confirmation")
	}
	if _, err := os.Stat(wiki); err != nil {
		t.Error("the wiki must still be there")
	}
}

// The archive is the only copy left, so writing it inside the wiki being deleted would destroy it along with everything else.
func TestTheArchiveCannotBeLeftInsideTheWiki(t *testing.T) {
	wiki := aWiki(t, filepath.Join(t.TempDir(), "old"), "old.example.org")

	err := commands.Destroy(settingsFor(t, wiki), &destroyingPHP{}, "old.example.org", filepath.Join(wiki, "private", "backups"), nil)
	if err == nil {
		t.Fatal("an archive inside the wiki must be refused")
	}
	if !strings.Contains(err.Error(), "inside the wiki") {
		t.Errorf("the refusal should say why: %v", err)
	}
	if _, err := os.Stat(wiki); err != nil {
		t.Error("the wiki must still be there")
	}
}

func TestAWikiThatCannotBeArchivedIsNotDestroyed(t *testing.T) {
	wiki := aWiki(t, filepath.Join(t.TempDir(), "old"), "old.example.org")
	php := &destroyingPHP{err: os.ErrPermission}

	if err := commands.Destroy(settingsFor(t, wiki), php, "old.example.org", t.TempDir(), nil); err == nil {
		t.Fatal("a failed archive must stop the destruction")
	}

	if _, err := os.Stat(wiki); err != nil {
		t.Fatal("the wiki must be completely intact after a failed archive")
	}
	if _, err := os.Stat(filepath.Join(wiki, "yeswiki.config.php")); err != nil {
		t.Error("its configuration must still be there")
	}
}

func TestDestroyingRemovesTheDirectoryAndPassesTheConfirmationOn(t *testing.T) {
	farm := t.TempDir()
	wiki := aWiki(t, filepath.Join(farm, "old"), "old.example.org")
	php := &destroyingPHP{}
	archives := t.TempDir()

	if err := commands.Destroy(settingsFor(t, wiki), php, "old.example.org", archives, nil); err != nil {
		t.Fatal(err)
	}

	if _, err := os.Stat(wiki); !os.IsNotExist(err) {
		t.Error("the directory should be gone")
	}

	joined := strings.Join(php.arguments, " ")
	for _, expected := range []string{"core:destroy", "--confirm=old.example.org", "--archive-to=" + archives} {
		if !strings.Contains(joined, expected) {
			t.Errorf("%q is missing from %q", expected, joined)
		}
	}
}

func TestDestroyingOneWikiLeavesItsNeighboursAlone(t *testing.T) {
	farm := t.TempDir()
	doomed := aWiki(t, filepath.Join(farm, "old"), "old.example.org")
	kept := aWiki(t, filepath.Join(farm, "keeper"), "keeper.example.org")

	if err := commands.Destroy(settingsFor(t, doomed), &destroyingPHP{}, "old.example.org", t.TempDir(), nil); err != nil {
		t.Fatal(err)
	}

	if _, err := os.Stat(filepath.Join(kept, "yeswiki.config.php")); err != nil {
		t.Fatal("the neighbour must be untouched")
	}

	wikis, _, err := program.Wikis(farm)
	if err != nil {
		t.Fatal(err)
	}
	if len(wikis) != 1 || wikis[0].Host != "keeper.example.org" {
		t.Errorf("the farm should hold only the neighbour now: %v", wikis)
	}
}

func TestWhatToKeepIsPassedOn(t *testing.T) {
	wiki := aWiki(t, filepath.Join(t.TempDir(), "old"), "old.example.org")
	php := &destroyingPHP{}

	err := commands.Destroy(settingsFor(t, wiki), php, "old.example.org", t.TempDir(), []string{"--keep-bucket", "--keep-archive"})
	if err != nil {
		t.Fatal(err)
	}

	joined := strings.Join(php.arguments, " ")
	if !strings.Contains(joined, "--keep-bucket") || !strings.Contains(joined, "--keep-archive") {
		t.Errorf("a half-gone wiki has to be destroyable by naming its pieces: %q", joined)
	}
}
