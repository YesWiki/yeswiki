// Package yeswiki registers `setup` and `serve` as Caddy commands.
//
// A Caddy plugin rather than a main package: xcaddy generates the main, which is how the commands
// end up inside a FrankenPHP static build alongside `php-cli` and `php-server`.
package yeswiki

import (
	"fmt"
	"os"
	"os/exec"
	"os/signal"
	"path/filepath"
	"syscall"

	"github.com/caddyserver/caddy/v2"
	"github.com/caddyserver/caddy/v2/caddyconfig"
	caddycmd "github.com/caddyserver/caddy/v2/cmd"
	"github.com/spf13/cobra"

	_ "github.com/caddyserver/caddy/v2/modules/standard"

	"github.com/YesWiki/yeswiki/binary/internal/commands"
	"github.com/YesWiki/yeswiki/binary/internal/program"
)

func init() {
	caddy.CustomBinaryName = "yeswiki"

	caddycmd.RegisterCommand(caddycmd.Command{
		Name:  "setup",
		Usage: "[<directory>] [--program-root <path>] [installer options]",
		Short: "Install a wiki in a directory",
		CobraFunc: func(command *cobra.Command) {
			command.Flags().String("program-root", "", "Where to write the program ("+program.EnvRoot+")")
			command.Flags().SetInterspersed(false)
			command.FParseErrWhitelist = cobra.FParseErrWhitelist{UnknownFlags: true}
			command.Args = cobra.ArbitraryArgs
			command.RunE = caddycmd.WrapCommandFuncForCobra(runSetup)
		},
	})

	caddycmd.RegisterCommand(caddycmd.Command{
		Name:  "serve",
		Usage: "[<directory>] [--program-root <path>] [--domain <name>] [--listen <address>] [--classic]",
		Short: "Serve a wiki",
		CobraFunc: func(command *cobra.Command) {
			command.Flags().String("program-root", "", "Where to write the program ("+program.EnvRoot+")")
			command.Flags().String("listen", "", "Address to listen on (default http://localhost:8080)")
			command.Flags().String("domain", "", "Serve this domain publicly, with a certificate Caddy obtains")
			command.Flags().Bool("classic", false, "Serve without workers, rebuilding the wiki on every request")
			command.Flags().Int("workers", 0, "How many workers to run (default: what FrankenPHP picks for this machine)")
			command.Args = cobra.MaximumNArgs(1)
			command.RunE = caddycmd.WrapCommandFuncForCobra(runServe)
		},
	})
}

func runSetup(flags caddycmd.Flags) (int, error) {
	directory, installerArguments := splitArguments(flags.Args())

	if err := commands.Setup(options(flags, directory), phpConsole{}, installerArguments); err != nil {
		return caddy.ExitCodeFailedStartup, err
	}

	return caddy.ExitCodeSuccess, nil
}

func runServe(flags caddycmd.Flags) (int, error) {
	directory := ""
	if arguments := flags.Args(); len(arguments) > 0 {
		directory = arguments[0]
	}

	server := caddyServer{
		listen:  Listen{Address: flags.String("listen"), Domain: flags.String("domain")},
		workers: Workers{Classic: flags.Bool("classic"), Count: flags.Int("workers")},
	}
	if err := commands.Serve(options(flags, directory), server); err != nil {
		return caddy.ExitCodeFailedStartup, err
	}

	return caddy.ExitCodeSuccess, nil
}

func options(flags caddycmd.Flags, directory string) commands.Options {
	return commands.Options{
		Directory:   directory,
		ProgramRoot: flags.String("program-root"),
		Version:     Version,
		Source:      Program(),
		Env:         os.Getenv,
		Home:        os.UserHomeDir,
		Wd:          os.Getwd,
		Out:         func(message string) { fmt.Fprintln(os.Stderr, message) },
	}
}

/*
splitArguments separates the directory from the installer options that follow it.
*/
func splitArguments(arguments []string) (string, []string) {
	if len(arguments) == 0 {
		return "", nil
	}
	if arguments[0] == "" || arguments[0][0] == '-' {
		return "", arguments
	}

	return arguments[0], arguments[1:]
}

// phpConsole runs the wiki's own console in the embedded PHP.
type phpConsole struct{}

func (phpConsole) Console(instance, programDir string, arguments []string) error {
	self, err := os.Executable()
	if err != nil {
		return err
	}

	console := filepath.Join(programDir, "src", "commands", "console")
	command := exec.Command(self, append([]string{"php-cli", console}, arguments...)...)
	command.Dir = instance
	command.Env = commands.Environment(instance, programDir)
	command.Stdin, command.Stdout, command.Stderr = os.Stdin, os.Stdout, os.Stderr

	return command.Run()
}

// caddyServer serves an Instance until the process is asked to stop.
type caddyServer struct {
	listen  Listen
	workers Workers
}

func (c caddyServer) Serve(instance, programDir string) error {
	for name, value := range map[string]string{
		program.EnvInstance:   instance,
		program.EnvProgram:    programDir,
		program.EnvConfigFile: filepath.Join(instance, "yeswiki.config.php"),
	} {
		if err := os.Setenv(name, value); err != nil {
			return err
		}
	}

	adapter := caddyconfig.GetAdapter("caddyfile")
	if adapter == nil {
		return fmt.Errorf("this build has no caddyfile adapter")
	}

	workers := c.workers
	workers.Program = programDir

	caddyfile, err := CaddyfileFor(instance, c.listen, workers)
	if err != nil {
		return err
	}

	config, warnings, err := adapter.Adapt([]byte(caddyfile), nil)
	if err != nil {
		return err
	}
	for _, warning := range warnings {
		fmt.Fprintf(os.Stderr, "caddyfile: %s\n", warning.Message)
	}

	if err := caddy.Load(config, true); err != nil {
		return err
	}

	stop := make(chan os.Signal, 1)
	signal.Notify(stop, os.Interrupt, syscall.SIGTERM)
	<-stop

	return caddy.Stop()
}
