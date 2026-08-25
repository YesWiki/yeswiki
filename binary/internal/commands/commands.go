// Package commands is what `yeswiki setup` and `yeswiki serve` do, with the PHP runtime injected.
package commands

import (
	"errors"
	"fmt"
	"io/fs"
	"os"
	"path/filepath"
	"strings"

	"github.com/YesWiki/yeswiki/binary/internal/program"
)

// PHP runs the wiki's own console, which is where installing actually happens.
type PHP interface {
	Console(instance, programDir string, arguments []string) error
}

// Server serves an Instance directory.
type Server interface {
	Serve(instance, programDir string) error
}

// FarmServer serves every wiki in a directory, and is asked to serve them again on a reload.
type FarmServer interface {
	ServeFarm(farm string, wikis []program.Wiki, programDir string) error
}

// Options are what both commands resolve before they do anything.
type Options struct {
	Directory   string
	ProgramRoot string
	Version     string
	Source      fs.FS
	Env         func(string) string
	Home        func() (string, error)
	Wd          func() (string, error)
	Self        func() (string, error)
	Out         func(string)
}

// Resolve is the Instance and the Program a command is about, for callers outside this package.
func Resolve(o Options) (instance string, programDir string, err error) {
	return o.resolve()
}

func (o Options) resolve() (instance string, programDir string, err error) {
	instance, err = program.ResolveInstance(o.Directory, o.Wd)
	if err != nil {
		return "", "", err
	}

	programDir, err = o.program()
	if err != nil {
		return "", "", err
	}

	return instance, programDir, nil
}

// program is the Program this binary carries, written where it belongs, with the shim beside it.
func (o Options) program() (string, error) {
	root, err := program.Root(o.ProgramRoot, o.Env, o.Home)
	if err != nil {
		return "", err
	}

	programDir, err := program.Ensure(o.Source, root, o.Version)
	if err != nil {
		return "", fmt.Errorf("%w: %s", program.Missing, err)
	}

	if _, err := program.WriteShim(root, o.self()); err != nil {
		return "", fmt.Errorf("could not write the shim background jobs are started with: %w", err)
	}

	return programDir, nil
}

// self is the path to this binary, which the shim names.
func (o Options) self() string {
	find := o.Self
	if find == nil {
		find = os.Executable
	}

	executable, err := find()
	if err != nil {
		return ""
	}

	return executable
}

// Setup writes the Program, provisions the Instance and installs the wiki into it.
func Setup(options Options, php PHP, installerArguments []string) error {
	instance, programDir, err := options.resolve()
	if err != nil {
		return err
	}

	if err := program.ProvisionInstance(instance, programDir); err != nil {
		return err
	}
	options.say(fmt.Sprintf("program %s", programDir))
	options.say(fmt.Sprintf("instance %s", instance))

	if program.Configured(instance) {
		return fmt.Errorf("%s already holds a wiki; serve it, or remove its yeswiki.config.php to install another", instance)
	}

	clone, installerArguments := takeCloneArguments(installerArguments)

	if err := php.Console(instance, programDir, append([]string{"core:install"}, installerArguments...)); err != nil {
		return err
	}

	if len(clone) > 0 {
		options.say("installed; now filling it from the remote wiki")
		if err := php.Console(instance, programDir, append([]string{"core:clone"}, clone...)); err != nil {
			return fmt.Errorf("%s was installed but not filled: %w", instance, err)
		}
	}

	if neighbours(instance) {
		options.say("this wiki is in a farm: `sudo systemctl reload " + program.UnitName + "` starts serving it")
	}

	return nil
}

// takeCloneArguments separates what `core:clone` is told from what `core:install` is told.
func takeCloneArguments(arguments []string) ([]string, []string) {
	clone := []string{}
	install := []string{}

	for _, argument := range arguments {
		switch {
		case strings.HasPrefix(argument, "--from-wiki"),
			strings.HasPrefix(argument, "--remote-admin"),
			argument == "--keep-archive":
			clone = append(clone, argument)
		default:
			install = append(install, argument)
		}
	}

	return clone, install
}

// neighbours reports whether the directory this Instance sits in already holds other wikis, which is what makes it a farm.
func neighbours(instance string) bool {
	wikis, _, err := program.Wikis(filepath.Dir(instance))
	if err != nil {
		return false
	}

	for _, wiki := range wikis {
		if wiki.Directory != instance {
			return true
		}
	}

	return false
}

// Serve writes the Program if it is not there and serves the Instance.
func Serve(options Options, server Server) error {
	instance, programDir, err := options.resolve()
	if err != nil {
		return err
	}

	if !program.Configured(instance) {
		return fmt.Errorf("%s is not a wiki yet: run `yeswiki setup` there first", instance)
	}

	// The third refusal: a wiki that was last migrated against another Program has a schema that
	// may be behind this code, and serving it half-migrated is worse than not serving it.
	if pointed, found := program.NamedBy(instance); found && pointed != programDir {
		return fmt.Errorf("%s was last migrated against %s and this is %s, so its schema may be behind this code: run `yeswiki migrate` before serving it",
			instance, filepath.Base(pointed), filepath.Base(programDir))
	}

	options.say(fmt.Sprintf("serving %s from %s", instance, programDir))
	pruneAround(options, programDir, named(instance))

	return server.Serve(instance, programDir)
}

// named is the Program an Instance points at, or nothing.
func named(instance string) string {
	pointed, found := program.NamedBy(instance)
	if !found {
		return ""
	}

	return pointed
}

// pruneAround removes Programs nothing is using, keeping the one about to be served, the ones
// wikis point at, and one spare for `upgrade --back-to`. It never fails a serve: a Program that
// could not be removed costs disk, and refusing to serve over it would cost the wiki.
func pruneAround(options Options, keep ...string) {
	root, err := program.Root(options.ProgramRoot, options.Env, options.Home)
	if err != nil {
		return
	}

	removed, err := program.Prune(root, keep)
	if err != nil {
		options.say("could not prune old programs: " + err.Error())

		return
	}
	for _, name := range removed {
		options.say("pruned " + name)
	}
}

// ServeFarm writes the Program if needed and serves every wiki one level under a directory.
func ServeFarm(options Options, server FarmServer) error {
	root, err := program.ResolveInstance(options.Directory, options.Wd)
	if err != nil {
		return err
	}

	programDir, err := options.program()
	if err != nil {
		return err
	}

	wikis, err := Enrol(options, root, programDir)
	if err != nil {
		return err
	}

	keep := []string{programDir}
	for _, wiki := range wikis {
		keep = append(keep, named(wiki.Directory))
	}
	pruneAround(options, keep...)

	return server.ServeFarm(root, wikis, programDir)
}

// Enrol is the wikis in a farm directory, provisioned and with what was skipped said out loud.
func Enrol(options Options, farm string, programDir string) ([]program.Wiki, error) {
	wikis, skipped, err := program.Wikis(farm)
	if err != nil {
		return nil, err
	}

	for _, reason := range skipped {
		options.say("skipping " + reason)
	}

	for at, wiki := range wikis {
		if err := program.ProvisionInstance(wiki.Directory, programDir); err != nil {
			return nil, err
		}

		if named, found := program.NamedBy(wiki.Directory); found && named != programDir {
			wikis[at].Closed = true
			wikis[at].Why = "This wiki has not been upgraded yet."
			options.say(fmt.Sprintf("closed %s: it runs %s, and this is %s", wiki.Host, filepath.Base(named), filepath.Base(programDir)))

			continue
		}

		if err := program.OpenDoor(wiki.Directory); err != nil {
			return nil, err
		}
		options.say(fmt.Sprintf("serving %s from %s", wiki.Host, wiki.Directory))
	}

	if len(wikis) == 0 {
		options.say("no wikis in " + farm + " yet: `yeswiki setup " + farm + "/mywiki` makes one")
	}

	return wikis, nil
}

// Upgrade writes the new Program and takes every wiki across to it, one at a time.
func Upgrade(options Options, php PHP, farm bool) error {
	root, err := program.ResolveInstance(options.Directory, options.Wd)
	if err != nil {
		return err
	}

	programDir, err := options.program()
	if err != nil {
		return err
	}

	wikis, err := upgrading(options, root, farm)
	if err != nil {
		return err
	}

	options.say(fmt.Sprintf("upgrading %d wiki(s) to %s", len(wikis), filepath.Base(programDir)))

	crossed := 0
	for _, wiki := range wikis {
		if named, found := program.NamedBy(wiki.Directory); found && named == programDir {
			crossed++
			options.say(fmt.Sprintf("%s is already across", wiki.Host))

			continue
		}

		if err := program.CloseDoor(wiki.Directory); err != nil {
			return err
		}
		if err := program.ForgetCompiled(wiki.Directory); err != nil {
			return err
		}
		options.say(fmt.Sprintf("closed %s", wiki.Host))

		if err := php.Console(wiki.Directory, programDir, []string{"migrate"}); err != nil {
			return fmt.Errorf("%s could not be migrated, so the farm stopped here with %d of %d across: %w",
				wiki.Host, crossed, len(wikis), err)
		}

		if err := program.PointAt(wiki.Directory, programDir); err != nil {
			return err
		}
		crossed++
		options.say(fmt.Sprintf("migrated %s and pointed it at %s", wiki.Host, filepath.Base(programDir)))
	}

	options.say(fmt.Sprintf("%d wiki(s) across. They stay closed until the farm runs the new program:", crossed))
	options.say("    sudo systemctl reload " + program.UnitName)

	return nil
}

// RollBack points every wiki back at a Program it was on before, for a farm that has to go back.
func RollBack(options Options, to string) error {
	root, err := program.ResolveInstance(options.Directory, options.Wd)
	if err != nil {
		return err
	}

	if _, err := os.Stat(filepath.Join(to, "src", "commands", "console")); err != nil {
		return fmt.Errorf("%s does not hold a program to go back to: %w", to, err)
	}

	wikis, _, err := program.Wikis(root)
	if err != nil {
		return err
	}

	for _, wiki := range wikis {
		if err := program.PointAt(wiki.Directory, to); err != nil {
			return err
		}
		if err := program.OpenDoor(wiki.Directory); err != nil {
			return err
		}
		options.say(fmt.Sprintf("%s points at %s again", wiki.Host, filepath.Base(to)))
	}

	options.say("Their databases keep whatever migrations already ran. To serve them again:")
	options.say("    sudo systemctl reload " + program.UnitName)

	return nil
}

// upgrading is the wikis an upgrade is about: a farm's, or the one Instance it was pointed at.
func upgrading(options Options, root string, farm bool) ([]program.Wiki, error) {
	if farm {
		wikis, skipped, err := program.Wikis(root)
		if err != nil {
			return nil, err
		}
		for _, reason := range skipped {
			options.say("skipping " + reason)
		}

		return wikis, nil
	}

	if !program.Configured(root) {
		return nil, fmt.Errorf("%s is not a wiki", root)
	}

	stated, _ := program.BaseURL(root)
	host, address := program.AddressOf(stated)

	return []program.Wiki{{Directory: root, Host: host, Address: address}}, nil
}

// Destroy archives a wiki, drops what it owned, and then removes its directory.
func Destroy(options Options, php PHP, confirm string, archiveTo string, keep []string) error {
	instance, programDir, err := options.resolve()
	if err != nil {
		return err
	}

	if !program.Configured(instance) {
		return fmt.Errorf("%s is not a wiki", instance)
	}

	stated, _ := program.BaseURL(instance)
	host := program.HostOf(stated)
	if host == "" {
		return fmt.Errorf("%s has no base_url, so there is no name to confirm it by", instance)
	}
	if strings.TrimSpace(confirm) != host {
		return fmt.Errorf("this wiki is %s: pass --confirm %s to destroy it", host, host)
	}

	if strings.TrimSpace(archiveTo) == "" {
		return errors.New("--archive-to says where the archive is left, and it has to be outside the wiki")
	}
	archiveTo, err = filepath.Abs(archiveTo)
	if err != nil {
		return err
	}
	if within(archiveTo, instance) {
		return fmt.Errorf("%s is inside the wiki being destroyed: put the archive somewhere that will still be there", archiveTo)
	}

	arguments := append([]string{"core:destroy", "--confirm=" + host, "--archive-to=" + archiveTo}, keep...)
	if err := php.Console(instance, programDir, arguments); err != nil {
		return fmt.Errorf("%s was not destroyed: %w", host, err)
	}

	if err := os.RemoveAll(instance); err != nil {
		return fmt.Errorf("%s owns nothing any more, but its directory is still at %s: %w", host, instance, err)
	}
	options.say("removed " + instance)

	if neighbours(instance) {
		options.say(host + " was in a farm, which stops serving it after:")
		options.say("    sudo systemctl reload " + program.UnitName)
	}

	return nil
}

// within reports whether a path is inside a directory, so that an archive is not written to the one place that is about to be deleted.
func within(path, directory string) bool {
	relative, err := filepath.Rel(directory, path)

	return err == nil && relative != ".." && !strings.HasPrefix(relative, ".."+string(filepath.Separator))
}

// Environment is what a Program and an Instance are stated with, for a process PHP will run in.
func Environment(instance, programDir string) []string {
	return append(os.Environ(),
		"YESWIKI_FORWARDING=1",
		program.EnvInstance+"="+instance,
		program.EnvProgram+"="+programDir,
		program.EnvConfigFile+"="+instance+"/yeswiki.config.php",
		program.EnvAsyncPHP+"="+program.ShimPath(filepath.Dir(programDir)),
	)
}

func (o Options) say(message string) {
	if o.Out != nil {
		o.Out(message)
	}
}
