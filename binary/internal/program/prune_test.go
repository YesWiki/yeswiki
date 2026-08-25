package program

import (
	"os"
	"path/filepath"
	"testing"
	"time"
)

func aProgramTree(t *testing.T, root, version string, age time.Duration) string {
	t.Helper()

	dir := Dir(root, version)
	if err := os.MkdirAll(dir, 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(dir, marker), []byte(version+"\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	when := time.Now().Add(-age)
	if err := os.Chtimes(dir, when, when); err != nil {
		t.Fatal(err)
	}

	return dir
}

func TestPruningKeepsWhatIsNamedAndOneSpareToRollBackTo(t *testing.T) {
	root := t.TempDir()
	current := aProgramTree(t, root, "5.0.0-alpha4", 0)
	previous := aProgramTree(t, root, "5.0.0-alpha3", time.Hour)
	older := aProgramTree(t, root, "5.0.0-alpha2", 48*time.Hour)
	oldest := aProgramTree(t, root, "5.0.0-alpha1", 96*time.Hour)

	removed, err := Prune(root, []string{current})
	if err != nil {
		t.Fatal(err)
	}
	if len(removed) != 2 {
		t.Fatalf("removed %v, and the two oldest of the three unused ones were expected", removed)
	}

	for _, kept := range []string{current, previous} {
		if _, err := os.Stat(kept); err != nil {
			t.Errorf("%s should have survived: %v", filepath.Base(kept), err)
		}
	}
	for _, gone := range []string{older, oldest} {
		if _, err := os.Stat(gone); !os.IsNotExist(err) {
			t.Errorf("%s should have been pruned", filepath.Base(gone))
		}
	}
}

// A wiki that has not been upgraded yet still points at an old Program, and pruning it would take
// the farm's un-upgraded wikis down with it.
func TestAProgramAWikiStillPointsAtIsNeverPruned(t *testing.T) {
	root := t.TempDir()
	current := aProgramTree(t, root, "5.0.0-alpha4", 0)
	stillInUse := aProgramTree(t, root, "5.0.0-alpha1", 96*time.Hour)
	spare := aProgramTree(t, root, "5.0.0-alpha3", time.Hour)
	disposable := aProgramTree(t, root, "5.0.0-alpha2", 48*time.Hour)

	removed, err := Prune(root, []string{current, stillInUse})
	if err != nil {
		t.Fatal(err)
	}

	if _, err := os.Stat(stillInUse); err != nil {
		t.Fatal("a Program a wiki still points at was pruned")
	}
	if _, err := os.Stat(spare); err != nil {
		t.Error("the newest unused Program should stay as the rollback target")
	}
	if _, err := os.Stat(disposable); !os.IsNotExist(err) {
		t.Errorf("nothing was pruned: %v", removed)
	}
}

func TestPruningLeavesAnythingThatIsNotAProgramAlone(t *testing.T) {
	root := t.TempDir()
	current := aProgramTree(t, root, "5.0.0-alpha3", 0)
	aProgramTree(t, root, "5.0.0-alpha2", time.Hour)
	aProgramTree(t, root, "5.0.0-alpha1", 48*time.Hour)

	shim := filepath.Join(root, shimName)
	if err := os.WriteFile(shim, []byte("#!/bin/sh\n"), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.MkdirAll(filepath.Join(root, "cache"), 0o755); err != nil {
		t.Fatal(err)
	}

	if _, err := Prune(root, []string{current}); err != nil {
		t.Fatal(err)
	}

	for _, kept := range []string{shim, filepath.Join(root, "cache")} {
		if _, err := os.Stat(kept); err != nil {
			t.Errorf("%s was removed, and it is not a Program: %v", filepath.Base(kept), err)
		}
	}
}

// One Program left over is not worth a removal: it is the rollback target.
func TestNothingIsPrunedWhenThereIsOnlyOneSpare(t *testing.T) {
	root := t.TempDir()
	current := aProgramTree(t, root, "5.0.0-alpha2", 0)
	aProgramTree(t, root, "5.0.0-alpha1", time.Hour)

	removed, err := Prune(root, []string{current})
	if err != nil {
		t.Fatal(err)
	}
	if len(removed) != 0 {
		t.Fatalf("pruned %v, leaving nothing to roll back to", removed)
	}
}
