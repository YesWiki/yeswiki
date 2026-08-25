package release

import (
	"errors"
	"fmt"
	"io"
	"net/http"
	"os"
	"path/filepath"
	"strings"
)

// ErrReadOnly is a deployment whose executable is owned by something else.
var ErrReadOnly = errors.New("this binary cannot replace itself")

// Say reports progress to whoever asked for the update.
type Say func(string)

// Update is one self-replacement, resolved before anything is written.
type Update struct {
	From     string
	To       string
	Platform Platform
	Path     string
}

// Writable reports whether a directory can be written to, by writing to it.
//
// Not by detecting a container. `/.dockerenv`, cgroup shapes and `KUBERNETES_SERVICE_HOST` are
// heuristics that have been wrong before and that answer wrongly for a writable container somebody
// genuinely wants to self-update. Writability is a fact about the deployment rather than a claim
// it makes, and the only way to know it is to try.
func Writable(directory string) error {
	probe, err := os.CreateTemp(directory, ".yeswiki-writable-")
	if err != nil {
		return err
	}
	name := probe.Name()
	_ = probe.Close()

	return os.Remove(name)
}

// CanReplaceItself is the first of the three refusals: the Program root decides, and the message
// names the path so the operator knows which deployment they are in.
func CanReplaceItself(programRoot, executable string) error {
	if err := Writable(programRoot); err != nil {
		return fmt.Errorf("%w: %s is not writable, so the image or the package owns this binary. Roll the image, or upgrade the package, and run `yeswiki migrate` afterwards", ErrReadOnly, programRoot)
	}

	beside := filepath.Dir(executable)
	if err := Writable(beside); err != nil {
		return fmt.Errorf("%w: %s is not writable, so the executable at %s cannot be replaced in place. Roll the image, or upgrade the package, and run `yeswiki migrate` afterwards", ErrReadOnly, beside, executable)
	}

	return nil
}

// Available is what the channel offers this platform, if it is not what is already running.
func (c Client) Available(running string) (Index, Platform, bool, error) {
	index, err := c.Latest()
	if err != nil {
		return Index{}, Platform{}, false, err
	}

	entry, err := index.For(ThisPlatform())
	if err != nil {
		return index, Platform{}, false, err
	}

	if strings.TrimSpace(index.Version) == strings.TrimSpace(running) {
		return index, entry, false, nil
	}

	return index, entry, true, nil
}

// Download puts the new executable beside the current one and verifies it there.
//
// Beside, and not in a temp directory, for one reason: the swap is a rename, and a rename is only
// atomic within a filesystem. A download in /tmp would have to be copied across at the end, which
// is the truncate-and-write this must never do.
func (c Client) Download(entry Platform, executable string, say Say) (string, error) {
	target, err := c.Resolve(entry.URL)
	if err != nil {
		return "", err
	}

	beside := filepath.Dir(executable)
	partial, err := os.CreateTemp(beside, "."+filepath.Base(executable)+".incoming-")
	if err != nil {
		return "", fmt.Errorf("could not write beside %s: %w", executable, err)
	}
	path := partial.Name()

	remove := func() {
		_ = partial.Close()
		_ = os.Remove(path)
	}

	say("downloading " + target)
	response, err := c.fetcher().Get(target)
	if err != nil {
		remove()

		return "", fmt.Errorf("could not fetch %s: %w", target, err)
	}
	defer response.Body.Close()

	if response.StatusCode != http.StatusOK {
		remove()

		return "", fmt.Errorf("%s answered %s: nothing was installed", target, response.Status)
	}

	if _, err := io.Copy(partial, response.Body); err != nil {
		remove()

		return "", fmt.Errorf("could not write %s: %w", path, err)
	}
	if err := partial.Close(); err != nil {
		_ = os.Remove(path)

		return "", err
	}

	return path, nil
}

// FetchSignature reads the detached signature beside the artefact.
func (c Client) FetchSignature(entry Platform) ([]byte, error) {
	target, err := c.Resolve(entry.Signature)
	if err != nil {
		return nil, err
	}

	response, err := c.fetcher().Get(target)
	if err != nil {
		return nil, fmt.Errorf("could not fetch %s: %w", target, err)
	}
	defer response.Body.Close()

	if response.StatusCode != http.StatusOK {
		return nil, fmt.Errorf("%s answered %s, so the download cannot be verified: nothing was installed", target, response.Status)
	}

	content, err := io.ReadAll(io.LimitReader(response.Body, 4096))
	if err != nil {
		return nil, err
	}

	return DecodeSignature(content)
}

// Install verifies the download and only then renames it over the running executable.
//
// The rename is the whole point: a failed download must never leave an unbootable binary, and
// truncate-and-write is exactly how that happens. Unix lets a running executable be replaced by
// rename; the running process keeps the inode it started from until it exits.
func (c Client) Install(downloaded, executable string, entry Platform, signature []byte, say Say) error {
	key, err := c.key()
	if err != nil {
		_ = os.Remove(downloaded)

		return err
	}

	if err := Verify(downloaded, entry, signature, key); err != nil {
		_ = os.Remove(downloaded)

		return err
	}
	say("signature verified")

	mode := os.FileMode(0o755)
	if existing, err := os.Stat(executable); err == nil {
		mode = existing.Mode().Perm()
	}
	if err := os.Chmod(downloaded, mode); err != nil {
		_ = os.Remove(downloaded)

		return err
	}

	if err := os.Rename(downloaded, executable); err != nil {
		_ = os.Remove(downloaded)

		return fmt.Errorf("could not put the new binary at %s, and the old one is untouched: %w", executable, err)
	}

	return nil
}
