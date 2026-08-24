// Package yeswiki registers `setup` and `serve` as Caddy commands.
//
// A Caddy plugin rather than a main package: xcaddy generates the main, which is how the commands
// end up inside a FrankenPHP static build alongside `php-cli` and `php-server`.
package yeswiki

import (
	"errors"
	"fmt"
	"os"
	"os/exec"
	"os/signal"
	"path/filepath"
	"strconv"
	"strings"
	"syscall"

	"github.com/caddyserver/caddy/v2"
	"github.com/caddyserver/caddy/v2/caddyconfig"
	caddycmd "github.com/caddyserver/caddy/v2/cmd"
	"github.com/spf13/cobra"

	_ "github.com/caddyserver/caddy/v2/modules/standard"

	"github.com/YesWiki/yeswiki/binary/internal/commands"
	"github.com/YesWiki/yeswiki/binary/internal/program"
)

var wikiCommands = map[string]bool{"setup": true, "serve": true, "version": true, "help": true}

var firstRegistered *cobra.Command

const helpTemplate = `{{with (or .Long .Short)}}{{. | trimTrailingWhitespaces}}

{{end}}{{if or .Runnable .HasSubCommands}}{{.UsageString}}{{end}}
`

// hideEverythingButTheWiki hides Caddy's own commands, reachable because a command's CobraFunc
// runs after every previously registered command has been added to the root.
func hideEverythingButTheWiki() {
	if firstRegistered == nil || firstRegistered.Parent() == nil {
		return
	}

	root := firstRegistered.Parent()
	root.Example = ""
	root.SetHelpTemplate(helpTemplate)
	root.Version = wikiVersion()

	for _, command := range root.Commands() {
		if command.Name() == "version" {
			command.RunE = func(cmd *cobra.Command, _ []string) error {
				cmd.Printf("%s\n", wikiVersion())

				return nil
			}
		}
		if !wikiCommands[command.Name()] {
			command.Hidden = true
		}
	}
}

func wikiVersion() string {
	return "YesWiki " + Version
}

func init() {
	caddy.CustomBinaryName = "yeswiki"
	caddy.CustomLongDescription = longDescription
	caddy.CustomVersion = wikiVersion()

	caddycmd.RegisterCommand(caddycmd.Command{
		Name:  "setup",
		Usage: "[<directory>] [--program-root <path>] [installer options]",
		Short: "Install a wiki in a directory",
		CobraFunc: func(command *cobra.Command) {
			firstRegistered = command
			command.Flags().String("program-root", "", "Where to write the program ("+program.EnvRoot+")")
			command.Flags().SetInterspersed(false)
			command.FParseErrWhitelist = cobra.FParseErrWhitelist{UnknownFlags: true}
			command.Args = cobra.ArbitraryArgs
			command.RunE = caddycmd.WrapCommandFuncForCobra(runSetup)
		},
	})

	caddycmd.RegisterCommand(caddycmd.Command{
		Name:  "serve",
		Usage: "[<directory>] [--port <n>] [--domain <name>] [--program-root <path>] [--classic]",
		Short: "Serve a wiki",
		CobraFunc: func(command *cobra.Command) {
			command.Flags().String("program-root", "", "Where to write the program ("+program.EnvRoot+")")
			command.Flags().Int("port", 0, "Serve on this port of localhost (default 8080)")
			command.Flags().String("admin", "", "Expose Caddy's admin API here, for diagnosis only (default off)")
			command.Flags().String("listen", "", "Full address to listen on, when --port is not enough")
			command.Flags().String("domain", "", "Serve this domain publicly, with a certificate Caddy obtains")
			command.Flags().Bool("classic", false, "Serve without workers, rebuilding the wiki on every request")
			command.Flags().Int("workers", 0, "How many workers to run (default: what FrankenPHP picks for this machine)")
			command.Args = cobra.MaximumNArgs(1)
			command.RunE = caddycmd.WrapCommandFuncForCobra(runServe)
			hideEverythingButTheWiki()
		},
	})
}

// longDescription replaces Caddy's own, which describes a server platform rather than a wiki.
const longDescription = `YesWiki is a wiki engine. This binary carries all of it -- the application, a PHP
interpreter and a webserver -- so a wiki needs nothing else installed.

To put a wiki somewhere and open it:

	- 'yeswiki setup mywiki' installs one into the directory ` + "`mywiki`" + `.
	- 'yeswiki serve mywiki' serves it on http://localhost:8080.

Add '--domain example.org' to serve it publicly, with a certificate obtained and
renewed for you.

A wiki's own content, uploads and configuration live in the directory you name, which
is the whole of what you back up. The program itself is written once under
~/.local/share/yeswiki, shared by every wiki this binary serves, and moved by setting
` + program.EnvRoot + `.

This binary is Caddy and FrankenPHP underneath, and every command of theirs still
works -- 'yeswiki php-cli', 'yeswiki run', 'yeswiki list-modules' and the rest. They
are hidden rather than removed, because a binary that owns its whole surface owns
every escape hatch too, and things go wrong in the field.`

func runSetup(flags caddycmd.Flags) (int, error) {
	directory, installerArguments := splitArguments(flags.Args())

	if err := commands.Setup(options(flags, directory), phpConsole{}, installerArguments); err != nil {
		return caddy.ExitCodeFailedStartup, err
	}

	return caddy.ExitCodeSuccess, nil
}

// listenAddress turns --port into an address, and refuses the pair that would need one to win.
func listenAddress(listen string, port int) (string, error) {
	listen = strings.TrimSpace(listen)
	if port == 0 {
		return listen, nil
	}

	if port < 1 || port > 65535 {
		return "", fmt.Errorf("--port %d is not a port", port)
	}
	if listen != "" {
		return "", errors.New("--port and --listen both name where to serve: keep one")
	}

	return "localhost:" + strconv.Itoa(port), nil
}

func runServe(flags caddycmd.Flags) (int, error) {
	directory := ""
	if arguments := flags.Args(); len(arguments) > 0 {
		directory = arguments[0]
	}

	address, err := listenAddress(flags.String("listen"), flags.Int("port"))
	if err != nil {
		return caddy.ExitCodeFailedStartup, err
	}

	server := caddyServer{
		listen:  Listen{Address: address, Domain: flags.String("domain")},
		workers: Workers{Classic: flags.Bool("classic"), Count: flags.Int("workers")},
		admin:   flags.String("admin"),
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
	admin   string
}

// Serve runs the wiki from inside its own directory, which is where a relative path in its
// configuration -- `db_database` for SQLite, among others -- is resolved from.
func (c caddyServer) Serve(instance, programDir string) error {
	if err := os.Chdir(instance); err != nil {
		return err
	}

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
	workers.Instance = instance

	caddyfile, err := CaddyfileFor(instance, c.listen, workers, Admin(c.admin))
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
