package commands

import (
	"errors"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"syscall"

	"github.com/YesWiki/yeswiki/binary/internal/program"
	"github.com/YesWiki/yeswiki/binary/internal/release"
)

// SelfUpdate fetches a signed release, verifies it offline, and replaces this executable with it.
//
// It does not write the new Program or migrate anything: the running process still has the old
// tree embedded in it, so the only honest way to do the rest is to hand over to the binary that
// just arrived. ReplaceAndContinue is what does that.
func SelfUpdate(options Options, client release.Client, running string) (bool, error) {
	executable, err := options.executable()
	if err != nil {
		return false, err
	}

	root, err := program.Root(options.ProgramRoot, options.Env, options.Home)
	if err != nil {
		return false, err
	}
	if err := os.MkdirAll(root, 0o755); err != nil {
		return false, fmt.Errorf("%w: %s could not be created", release.ErrReadOnly, root)
	}
	if err := release.CanReplaceItself(root, executable); err != nil {
		return false, err
	}

	index, entry, newer, err := client.Available(running)
	if err != nil {
		return false, err
	}
	if !newer {
		options.say(fmt.Sprintf("%s is what the repository offers, and it is what is running", index.Version))

		return false, nil
	}

	options.say(fmt.Sprintf("%s is running, %s is offered", running, index.Version))

	downloaded, err := client.Download(entry, executable, options.say)
	if err != nil {
		return false, err
	}

	signature, err := client.FetchSignature(entry)
	if err != nil {
		_ = os.Remove(downloaded)

		return false, err
	}

	if err := client.Install(downloaded, executable, entry, signature, options.say); err != nil {
		return false, err
	}

	options.say(fmt.Sprintf("%s is now %s", executable, index.Version))

	return true, nil
}

// ReplaceAndContinue is `yeswiki upgrade` end to end: swap the executable, then hand the rest of
// the work to the binary that just replaced it.
//
// The hand-over is an exec rather than a call, because writing the new Program means reading the
// new Program, and this process has the old one compiled into it.
//
// It answers whether it handed over, which the caller needs: a binary that is already current has
// not migrated anything, and returning "no error" would end an upgrade that never ran.
func ReplaceAndContinue(options Options, client release.Client, running string, rest []string) (bool, error) {
	replaced, err := SelfUpdate(options, client, running)
	if err != nil {
		return false, err
	}
	if !replaced {
		return false, nil
	}

	executable, err := options.executable()
	if err != nil {
		return false, err
	}

	options.say("handing over to " + filepath.Base(executable) + " " + strings.Join(rest, " "))

	return true, handOver(executable, append([]string{executable}, rest...), os.Environ())
}

// handOver replaces this process with the new binary, so `upgrade` is one command from the
// operator's side even though two executables did the work.
var handOver = func(path string, argv []string, environment []string) error {
	if err := syscall.Exec(path, argv, environment); err != nil {
		// A platform without exec, or a binary that will not start: run it as a child rather
		// than leaving the operator with a swapped executable and no migration.
		command := exec.Command(path, argv[1:]...)
		command.Env = environment
		command.Stdout = os.Stdout
		command.Stderr = os.Stderr

		if runErr := command.Run(); runErr != nil {
			return fmt.Errorf("the new binary is in place at %s but did not finish the upgrade, so run `yeswiki migrate` yourself: %w", path, runErr)
		}
	}

	return nil
}

// executable is where this binary is, resolved through symlinks so the rename replaces the file
// the operator actually installed rather than a link pointing at it.
func (o Options) executable() (string, error) {
	find := o.Self
	if find == nil {
		find = os.Executable
	}

	executable, err := find()
	if err != nil {
		return "", fmt.Errorf("could not work out where this binary is, so it cannot replace itself: %w", err)
	}
	if resolved, err := filepath.EvalSymlinks(executable); err == nil {
		executable = resolved
	}

	return filepath.Abs(executable)
}

// ErrNotUpgradable is what a caller checks to tell "cannot" from "went wrong".
var ErrNotUpgradable = errors.New("this deployment does not upgrade itself")
