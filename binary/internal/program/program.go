// Package program resolves where the Program lives and writes it there.
//
// The Program is the code YesWiki is made of, as one versioned read-only tree serving any number
// of Instances. Nothing here knows about PHP, Caddy or FrankenPHP, which is what lets it be
// tested without any of them.
package program

import (
	"errors"
	"fmt"
	"io"
	"io/fs"
	"os"
	"path/filepath"
	"sort"
	"strings"
	"time"
)

// EnvRoot names the directory Programs are written under.
const EnvRoot = "YESWIKI_PROGRAM_ROOT"

// marker is written last and read first: a tree without it is a Program that never finished.
const marker = ".yeswiki-program"

// DefaultRoot is where a Program goes when nobody says otherwise.
func DefaultRoot(home string) string {
	return filepath.Join(home, ".local", "share", "yeswiki")
}

// Root resolves the Program root: the flag, then the environment, then the default under home.
func Root(flag string, env func(string) string, home func() (string, error)) (string, error) {
	if strings.TrimSpace(flag) != "" {
		return filepath.Abs(flag)
	}
	if stated := strings.TrimSpace(env(EnvRoot)); stated != "" {
		return filepath.Abs(stated)
	}
	dir, err := home()
	if err != nil {
		return "", fmt.Errorf("no %s and no home directory to fall back on: %w", EnvRoot, err)
	}

	return DefaultRoot(dir), nil
}

// Dir is where one version of the Program lives under a root.
func Dir(root, version string) string {
	return filepath.Join(root, "program-"+version)
}

// Complete reports whether dir holds a Program that finished being written, at this version.
func Complete(dir, version string) bool {
	written, err := os.ReadFile(filepath.Join(dir, marker))

	return err == nil && strings.TrimSpace(string(written)) == version
}

// Ensure writes the Program at version into root unless it is already there, and answers its path.
func Ensure(source fs.FS, root, version string) (string, error) {
	dir := Dir(root, version)
	if Complete(dir, version) {
		return dir, nil
	}

	staging := dir + fmt.Sprintf(".writing-%d", os.Getpid())
	if err := os.RemoveAll(staging); err != nil {
		return "", err
	}
	if err := write(source, staging); err != nil {
		_ = os.RemoveAll(staging)

		return "", err
	}
	if err := os.WriteFile(filepath.Join(staging, marker), []byte(version+"\n"), 0o644); err != nil {
		_ = os.RemoveAll(staging)

		return "", err
	}

	if err := os.RemoveAll(dir); err != nil {
		_ = os.RemoveAll(staging)

		return "", err
	}
	if err := os.Rename(staging, dir); err != nil {
		_ = os.RemoveAll(staging)

		return "", fmt.Errorf("could not put the program in place at %s: %w", dir, err)
	}

	return dir, nil
}

// Missing answers a sentence naming what is absent, for a failure that must never be quiet.
var Missing = errors.New("the program is missing")

func write(source fs.FS, target string) error {
	if err := os.MkdirAll(target, 0o755); err != nil {
		return err
	}

	return fs.WalkDir(source, ".", func(path string, entry fs.DirEntry, err error) error {
		if err != nil {
			return err
		}
		if path == "." {
			return nil
		}
		destination := filepath.Join(target, filepath.FromSlash(path))
		if entry.IsDir() {
			return os.MkdirAll(destination, 0o755)
		}

		return copyFile(source, path, destination, entry)
	})
}

func copyFile(source fs.FS, path, destination string, entry fs.DirEntry) error {
	in, err := source.Open(path)
	if err != nil {
		return err
	}
	defer in.Close()

	mode := fs.FileMode(0o644)
	if info, err := entry.Info(); err == nil && info.Mode()&0o111 != 0 {
		mode = 0o755
	}

	if err := os.MkdirAll(filepath.Dir(destination), 0o755); err != nil {
		return err
	}
	out, err := os.OpenFile(destination, os.O_WRONLY|os.O_CREATE|os.O_TRUNC, mode)
	if err != nil {
		return err
	}
	if _, err := io.Copy(out, in); err != nil {
		out.Close()

		return err
	}

	return out.Close()
}

// Prune removes Programs that nothing is using any more, keeping the ones named plus one spare.
//
// The ticket asks for this to happen once the new version has served successfully, and this is
// what makes that true: it runs at the start of `serve`, so a Program that never booted is never
// the one that triggers a prune, and the newest of the rest survives so `upgrade --back-to` has
// somewhere to go. It answers what it removed rather than saying nothing.
func Prune(root string, keep []string) ([]string, error) {
	entries, err := os.ReadDir(root)
	if err != nil {
		return nil, err
	}

	kept := map[string]bool{}
	for _, path := range keep {
		if strings.TrimSpace(path) != "" {
			kept[filepath.Base(path)] = true
		}
	}

	candidates := []string{}
	for _, entry := range entries {
		if !entry.IsDir() || !strings.HasPrefix(entry.Name(), "program-") || kept[entry.Name()] {
			continue
		}
		candidates = append(candidates, entry.Name())
	}
	if len(candidates) < 2 {
		return nil, nil
	}

	// Newest last, so the spare left behind is the most recent thing to roll back to.
	sort.Slice(candidates, func(a, b int) bool {
		return modifiedAt(root, candidates[a]).Before(modifiedAt(root, candidates[b]))
	})

	removed := []string{}
	for _, name := range candidates[:len(candidates)-1] {
		if err := os.RemoveAll(filepath.Join(root, name)); err != nil {
			return removed, err
		}
		removed = append(removed, name)
	}

	return removed, nil
}

func modifiedAt(root, name string) time.Time {
	info, err := os.Stat(filepath.Join(root, name))
	if err != nil {
		return time.Time{}
	}

	return info.ModTime()
}
