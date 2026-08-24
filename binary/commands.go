// Package yeswiki registers `setup` and `serve` as Caddy commands.
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

var wikiCommands = map[string]bool{"setup": true, "serve": true, "upgrade": true, "destroy": true, "unit": true, "version": true, "help": true}

var firstRegistered *cobra.Command

const helpTemplate = `{{with (or .Long .Short)}}{{. | trimTrailingWhitespaces}}

{{end}}{{if or .Runnable .HasSubCommands}}{{.UsageString}}{{end}}
`

// hideEverythingButTheWiki hides Caddy's own commands, reachable.
func hideEverythingButTheWiki() {
	if firstRegistered == nil || firstRegistered.Parent() == nil {
		return
	}

	root := firstRegistered.Parent()
	displaceCaddysUpgrade(root)
	root.Example = ""
	root.SetHelpTemplate(helpTemplate)
	root.Version = wikiVersion()
	root.Args = cobra.ArbitraryArgs
	root.RunE = runInsideAWiki
	root.SilenceUsage = true
	root.FParseErrWhitelist = cobra.FParseErrWhitelist{UnknownFlags: true}
	root.PersistentFlags().String("instance", "", "The wiki to act on (default: the one at or above the working directory)")
	helpWithTheWikisCommands(root)

	for _, command := range root.Commands() {
		if command.Name() == "version" {
			command.RunE = func(cmd *cobra.Command, _ []string) error {
				fmt.Fprintln(cmd.OutOrStdout(), wikiVersion())

				return nil
			}
		}
		if !wikiCommands[command.Name()] {
			command.Hidden = true
		}
	}
}

// displaceCaddysUpgrade replaces Caddy's `upgrade` with the one `yeswiki upgrade` means.
func displaceCaddysUpgrade(root *cobra.Command) {
	for _, command := range root.Commands() {
		if command.Name() == "upgrade" {
			root.RemoveCommand(command)
		}
	}

	upgrade := &cobra.Command{
		Use:   "upgrade [<directory>] [--farm]",
		Short: "Take a wiki, or every wiki in a farm, to this binary's program",
		Long: `Take a wiki, or every wiki in a farm, to the program this binary carries.

The program is written beside the one in use, and then each wiki in turn has its door
closed, its database migrated by the new program, and its entry points rewritten. A
wiki is served again only once all three have happened, so none ever serves code that
disagrees with its own schema.

The farm serves one program at a time, so the wikis cross while it still runs the old
one and all of them come back together on the next reload. A wiki whose migration
fails keeps its door closed and is named, the rest are unaffected, and running this
again picks up where it stopped.

Use --back-to <program directory> to point every wiki at a program it was on before.
Programs are kept by version, so going back is repointing and reloading rather than
restoring -- but it cannot undo a migration that has already run.`,
		Args: cobra.MaximumNArgs(1),
	}
	upgrade.Flags().String("program-root", "", "Where to write the program ("+program.EnvRoot+")")
	upgrade.Flags().Bool("farm", false, "Upgrade every wiki one level under the directory")
	upgrade.Flags().String("back-to", "", "Point every wiki at this program directory again, instead of upgrading")
	upgrade.RunE = caddycmd.WrapCommandFuncForCobra(runUpgrade)

	root.AddCommand(upgrade)
}

// engine is what the build stamped into Caddy's version, read before init() replaces it.
var engine = strings.TrimSpace(caddy.CustomVersion)

// wikiVersion names YesWiki first and the engine after, which static-php-cli's smoke test greps for.
func wikiVersion() string {
	if engine == "" {
		return "YesWiki " + Version
	}

	return "YesWiki " + Version + " (" + engine + ")"
}

func init() {
	caddy.CustomBinaryName = "yeswiki"
	caddy.CustomLongDescription = longDescription
	caddy.CustomVersion = wikiVersion()

	caddycmd.RegisterCommand(caddycmd.Command{
		Name:  "setup",
		Usage: "[<directory>] [--from-wiki <url> --remote-admin <name>] [installer options]",
		Short: "Install a wiki in a directory, optionally from a remote one",
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
		Name:  "destroy",
		Usage: "[<directory>] --confirm <hostname> --archive-to <path>",
		Short: "Archive a wiki, then remove it and everything it owned",
		CobraFunc: func(command *cobra.Command) {
			command.Long = `Archive a wiki, then remove its database, its database account, its bucket
and its directory.

This is the one command with no undo, so it takes an archive first and stops if it
cannot write one. --confirm has to name the host the wiki answers to: a --force that
people learn to type reflexively stops being a decision.

	yeswiki destroy /srv/wikis/old --confirm old.example.org --archive-to /var/backups

Dropping a database and its account needs an administrator, given as --db-admin-user
and --db-admin-password (or DB_ADMIN_USER and DB_ADMIN_PASSWORD, which keeps them out
of ps). They are read for this one command and written nowhere.

A wiki that is already half-gone can still be finished off: --keep-archive skips the
backup when there is no database left to back up, and --keep-database and
--keep-bucket leave a piece alone.`
			command.Flags().String("program-root", "", "Where the program is ("+program.EnvRoot+")")
			command.Flags().String("confirm", "", "The host this wiki answers to, which has to be typed to destroy it")
			command.Flags().String("archive-to", "", "Where the archive is left, which must be outside the wiki")
			command.Flags().String("db-admin-user", "", "Drop the database and account as this administrator (or DB_ADMIN_USER)")
			command.Flags().String("db-admin-password", "", "Password of that administrator (or DB_ADMIN_PASSWORD)")
			command.Flags().String("s3-admin-key", "", "Drop the bucket with this key (or S3_ADMIN_KEY)")
			command.Flags().String("s3-admin-secret", "", "Secret of that key (or S3_ADMIN_SECRET)")
			command.Flags().Bool("keep-database", false, "Leave the database and its account alone")
			command.Flags().Bool("keep-bucket", false, "Leave the object storage alone")
			command.Flags().Bool("keep-archive", false, "Skip the archive, for a wiki whose database is already gone")
			command.Args = cobra.MaximumNArgs(1)
			command.RunE = caddycmd.WrapCommandFuncForCobra(runDestroy)
		},
	})

	caddycmd.RegisterCommand(caddycmd.Command{
		Name:  "unit",
		Usage: "[<directory>] [--user <name>] [--program-root <path>]",
		Short: "Print a systemd unit that serves a farm",
		CobraFunc: func(command *cobra.Command) {
			command.Long = "Print a systemd unit that serves every wiki in a directory.\n\n" +
				"The unit goes to stdout and the steps around it to stderr, so this can be piped\nstraight into the file:\n\n" +
				"\tyeswiki unit /srv/wikis | sudo tee /etc/systemd/system/yeswiki.service\n"
			command.Flags().String("user", "", "The account the service runs as (default "+program.ServiceUser+")")
			command.Flags().String("program-root", "", "Where the program is written ("+program.EnvRoot+")")
			command.Args = cobra.MaximumNArgs(1)
			command.RunE = caddycmd.WrapCommandFuncForCobra(runUnit)
		},
	})

	caddycmd.RegisterCommand(caddycmd.Command{
		Name:  "serve",
		Usage: "[<directory>] [--farm] [--port <n>] [--domain <name>] [--program-root <path>] [--classic]",
		Short: "Serve a wiki, or a directory of them",
		CobraFunc: func(command *cobra.Command) {
			command.Flags().String("program-root", "", "Where to write the program ("+program.EnvRoot+")")
			command.Flags().Int("port", 0, "Serve on this port of localhost (default 8080)")
			command.Flags().String("admin", "", "Expose Caddy's admin API here, for diagnosis only (default off)")
			command.Flags().String("listen", "", "Full address to listen on, when --port is not enough")
			command.Flags().String("domain", "", "Serve this domain publicly, with a certificate Caddy obtains")
			command.Flags().Bool("classic", false, "Serve without workers, rebuilding the wiki on every request")
			command.Flags().Int("workers", 0, "How many workers to run (default: what FrankenPHP picks for this machine)")
			command.Flags().Bool("farm", false, "Serve every wiki one level under the directory, each on the name its own configuration gives it")
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

To host several wikis, put each in its own directory under one parent and serve them
together:

	- 'yeswiki serve --farm /srv/wikis' serves every wiki in there, each on the name
	  its own configuration gives it, each with its own certificate.
	- 'yeswiki unit /srv/wikis' prints a systemd service that does the same at boot.

A directory becomes a wiki in the farm as soon as it holds one, and starts being served
on the next 'systemctl reload yeswiki'.

To take over a wiki that lives somewhere else, install from it:

	yeswiki setup mywiki --from-wiki https://old.example.org --remote-admin WikiAdmin \
	  --base-url "https://new.example.org/?" --driver sqlite

It asks that wiki for a full archive, waits for it, downloads it and restores it here.
The new wiki keeps its own address, database and storage; everything else comes across.
The password is asked for rather than passed, so it stays out of ps and shell history.

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

	workers := Workers{Classic: flags.Bool("classic"), Count: flags.Int("workers")}

	if flags.Bool("farm") {
		farm := caddyFarm{
			workers: workers,
			admin:   flags.String("admin"),
			options: func(directory string) commands.Options { return options(flags, directory) },
		}
		if err := commands.ServeFarm(options(flags, directory), farm); err != nil {
			return caddy.ExitCodeFailedStartup, err
		}

		return caddy.ExitCodeSuccess, nil
	}

	server := caddyServer{
		listen:  Listen{Address: address, Domain: flags.String("domain")},
		workers: workers,
		admin:   flags.String("admin"),
	}
	if err := commands.Serve(options(flags, directory), server); err != nil {
		return caddy.ExitCodeFailedStartup, err
	}

	return caddy.ExitCodeSuccess, nil
}

func runUpgrade(flags caddycmd.Flags) (int, error) {
	directory := ""
	if arguments := flags.Args(); len(arguments) > 0 {
		directory = arguments[0]
	}

	if back := flags.String("back-to"); strings.TrimSpace(back) != "" {
		if err := commands.RollBack(options(flags, directory), strings.TrimSpace(back)); err != nil {
			return caddy.ExitCodeFailedStartup, err
		}

		return caddy.ExitCodeSuccess, nil
	}

	if err := commands.Upgrade(options(flags, directory), phpConsole{}, flags.Bool("farm")); err != nil {
		return caddy.ExitCodeFailedStartup, err
	}

	return caddy.ExitCodeSuccess, nil
}

func runDestroy(flags caddycmd.Flags) (int, error) {
	directory := ""
	if arguments := flags.Args(); len(arguments) > 0 {
		directory = arguments[0]
	}

	keep := []string{}
	for _, piece := range []string{"keep-database", "keep-bucket", "keep-archive"} {
		if flags.Bool(piece) {
			keep = append(keep, "--"+piece)
		}
	}
	for _, credential := range []string{"db-admin-user", "db-admin-password", "s3-admin-key", "s3-admin-secret"} {
		if given := flags.String(credential); strings.TrimSpace(given) != "" {
			keep = append(keep, "--"+credential+"="+given)
		}
	}

	err := commands.Destroy(options(flags, directory), phpConsole{}, flags.String("confirm"), flags.String("archive-to"), keep)
	if err != nil {
		return caddy.ExitCodeFailedStartup, err
	}

	return caddy.ExitCodeSuccess, nil
}

// runUnit prints the unit on stdout and what to do with it on stderr, so a pipe into the service file carries only the file.
func runUnit(flags caddycmd.Flags) (int, error) {
	directory := ""
	if arguments := flags.Args(); len(arguments) > 0 {
		directory = arguments[0]
	}

	farm, err := program.ResolveInstance(directory, os.Getwd)
	if err != nil {
		return caddy.ExitCodeFailedStartup, err
	}

	self, err := os.Executable()
	if err != nil {
		return caddy.ExitCodeFailedStartup, err
	}

	unit := Unit{
		Binary:      self,
		Farm:        farm,
		User:        flags.String("user"),
		ProgramRoot: flags.String("program-root"),
	}

	fmt.Print(unit.Service())
	fmt.Fprintln(os.Stderr, "\n"+unit.Steps())

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
		Self:        os.Executable,
		Out:         func(message string) { fmt.Fprintln(os.Stderr, message) },
	}
}

// splitArguments separates the directory from the installer options that follow it.
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

// Listing is the wiki's own command list, captured rather than streamed so it can be shown inside this binary's help.
func (phpConsole) Listing(instance, programDir string) (string, error) {
	self, err := os.Executable()
	if err != nil {
		return "", err
	}

	console := filepath.Join(programDir, "src", "commands", "console")
	command := exec.Command(self, "php-cli", console, "list", "--format=txt")
	command.Dir = instance
	command.Env = commands.Environment(instance, programDir)

	listed, err := command.Output()
	if exit := (&exec.ExitError{}); errors.As(err, &exit) && len(exit.Stderr) > 0 {
		return string(listed), fmt.Errorf("%w: %s", err, firstLine(string(exit.Stderr)))
	}

	return string(listed), err
}

// firstLine is as much of a command's complaint as belongs in a help footer.
func firstLine(text string) string {
	for _, line := range strings.Split(text, "\n") {
		if strings.TrimSpace(line) != "" {
			return strings.TrimSpace(line)
		}
	}

	return strings.TrimSpace(text)
}

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

// Serve runs the wiki from inside its own directory.
func (c caddyServer) Serve(instance, programDir string) error {
	if err := os.Chdir(instance); err != nil {
		return err
	}

	for name, value := range map[string]string{
		program.EnvInstance:   instance,
		program.EnvProgram:    programDir,
		program.EnvConfigFile: filepath.Join(instance, "yeswiki.config.php"),
		program.EnvAsyncPHP:   program.ShimPath(filepath.Dir(programDir)),
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
