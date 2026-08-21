// Package commands is what `yeswiki setup` and `yeswiki serve` do, with the PHP runtime injected.
//
// The runtime is an interface so that everything except starting FrankenPHP itself can be tested
// without a PHP build: what these commands mostly do is resolve two roots, write a Program and
// hand the rest to PHP.
package commands

import (
	"fmt"
	"io/fs"
	"os"

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

// Options are what both commands resolve before they do anything.
type Options struct {
	Directory   string
	ProgramRoot string
	Version     string
	Source      fs.FS
	Env         func(string) string
	Home        func() (string, error)
	Wd          func() (string, error)
	Out         func(string)
}

func (o Options) resolve() (instance string, programDir string, err error) {
	instance, err = program.ResolveInstance(o.Directory, o.Wd)
	if err != nil {
		return "", "", err
	}

	root, err := program.Root(o.ProgramRoot, o.Env, o.Home)
	if err != nil {
		return "", "", err
	}

	programDir, err = program.Ensure(o.Source, root, o.Version)
	if err != nil {
		return "", "", fmt.Errorf("%w: %s", program.Missing, err)
	}

	return instance, programDir, nil
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

	return php.Console(instance, programDir, append([]string{"core:install"}, installerArguments...))
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
	options.say(fmt.Sprintf("serving %s from %s", instance, programDir))

	return server.Serve(instance, programDir)
}

// Environment is what a Program and an Instance are stated with, for a process PHP will run in.
func Environment(instance, programDir string) []string {
	return append(os.Environ(),
		program.EnvInstance+"="+instance,
		program.EnvProgram+"="+programDir,
		program.EnvConfigFile+"="+instance+"/yeswiki.config.php",
	)
}

func (o Options) say(message string) {
	if o.Out != nil {
		o.Out(message)
	}
}
