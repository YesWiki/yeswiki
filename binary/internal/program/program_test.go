package program

import (
	"errors"
	"io/fs"
	"os"
	"path/filepath"
	"strings"
	"testing"
	"testing/fstest"
)

func source() fs.FS {
	return fstest.MapFS{
		"index.php":              {Data: []byte("<?php // program")},
		"src/YesWikiRuntime.php": {Data: []byte("<?php // runtime")},
		"vendor/autoload.php":    {Data: []byte("<?php // autoload")},
		"composer.json":          {Data: []byte("{}")},
	}
}

func TestTheRootIsStatedByTheFlagFirst(t *testing.T) {
	root, err := Root("/stated/by/flag", func(string) string { return "/from/env" }, func() (string, error) { return "/home/someone", nil })
	if err != nil {
		t.Fatal(err)
	}
	if root != "/stated/by/flag" {
		t.Fatalf("got %q", root)
	}
}

func TestTheEnvironmentIsNextAndHomeIsLast(t *testing.T) {
	root, err := Root("", func(string) string { return "/from/env" }, func() (string, error) { return "/home/someone", nil })
	if err != nil || root != "/from/env" {
		t.Fatalf("got %q, %v", root, err)
	}

	root, err = Root("", func(string) string { return "" }, func() (string, error) { return "/home/someone", nil })
	if err != nil || root != "/home/someone/.local/share/yeswiki" {
		t.Fatalf("got %q, %v", root, err)
	}
}

func TestWithNoRootAndNoHomeItSaysSoRatherThanGuessing(t *testing.T) {
	_, err := Root("", func(string) string { return "" }, func() (string, error) { return "", errors.New("no home") })
	if err == nil {
		t.Fatal("a Program written somewhere nobody named is a Program nobody finds")
	}
	if !strings.Contains(err.Error(), EnvRoot) {
		t.Fatalf("the message must name the variable to set, got %q", err)
	}
}

func TestTheProgramIsWrittenOnceAndFoundAgain(t *testing.T) {
	root := t.TempDir()

	dir, err := Ensure(source(), root, "4.5.0")
	if err != nil {
		t.Fatal(err)
	}
	if dir != filepath.Join(root, "program-4.5.0") {
		t.Fatalf("got %q", dir)
	}
	for _, file := range []string{"index.php", "src/YesWikiRuntime.php", "vendor/autoload.php"} {
		if _, err := os.Stat(filepath.Join(dir, file)); err != nil {
			t.Fatalf("%s is missing from the program: %v", file, err)
		}
	}

	stamp, err := os.Stat(filepath.Join(dir, "index.php"))
	if err != nil {
		t.Fatal(err)
	}
	if _, err := Ensure(source(), root, "4.5.0"); err != nil {
		t.Fatal(err)
	}
	again, err := os.Stat(filepath.Join(dir, "index.php"))
	if err != nil {
		t.Fatal(err)
	}
	if !stamp.ModTime().Equal(again.ModTime()) {
		t.Fatal("a program already there must be left alone: this runs on every serve")
	}
}

func TestAProgramThatNeverFinishedIsNotUsed(t *testing.T) {
	root := t.TempDir()
	dir, err := Ensure(source(), root, "4.5.0")
	if err != nil {
		t.Fatal(err)
	}

	if err := os.Remove(filepath.Join(dir, marker)); err != nil {
		t.Fatal(err)
	}
	if Complete(dir, "4.5.0") {
		t.Fatal("a tree with no marker is a write that was interrupted, not a program")
	}

	if _, err := Ensure(source(), root, "4.5.0"); err != nil {
		t.Fatal(err)
	}
	if !Complete(dir, "4.5.0") {
		t.Fatal("and Ensure must write it again")
	}
}

func TestAnotherVersionLandsBesideItRatherThanOverIt(t *testing.T) {
	root := t.TempDir()
	if _, err := Ensure(source(), root, "4.5.0"); err != nil {
		t.Fatal(err)
	}
	if _, err := Ensure(source(), root, "4.6.0"); err != nil {
		t.Fatal(err)
	}

	for _, version := range []string{"4.5.0", "4.6.0"} {
		if !Complete(Dir(root, version), version) {
			t.Fatalf("program-%s is gone: a running process may be reading it", version)
		}
	}
}

func TestNothingIsLeftBehindWhenTheWriteFails(t *testing.T) {
	root := t.TempDir()
	broken := fstest.MapFS{"src/one.php": {Data: []byte("<?php")}}

	if _, err := Ensure(broken, root, "4.5.0"); err != nil {
		t.Fatal(err)
	}

	entries, err := os.ReadDir(root)
	if err != nil {
		t.Fatal(err)
	}
	for _, entry := range entries {
		if strings.Contains(entry.Name(), ".writing-") {
			t.Fatalf("a staging directory survived: %s", entry.Name())
		}
	}
}
