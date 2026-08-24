package yeswiki

import (
	"context"
	"fmt"
	"net"
	"os"
	"os/signal"
	"path/filepath"
	"strings"
	"syscall"
	"time"

	"github.com/caddyserver/caddy/v2"
	"github.com/caddyserver/caddy/v2/caddyconfig"

	"github.com/YesWiki/yeswiki/binary/internal/commands"
	"github.com/YesWiki/yeswiki/binary/internal/program"
)

// caddyFarm serves every wiki in a directory from this one process.
type caddyFarm struct {
	workers Workers
	admin   string
	options func(directory string) commands.Options
}

// ServeFarm loads the farm and then reloads it on SIGHUP.
func (c caddyFarm) ServeFarm(farm string, wikis []program.Wiki, programDir string) error {
	for name, value := range map[string]string{
		program.EnvProgram:  programDir,
		program.EnvAsyncPHP: program.ShimPath(filepath.Dir(programDir)),
	} {
		if err := os.Setenv(name, value); err != nil {
			return err
		}
	}

	sayWhichNamesWillNotGetACertificate(wikis)

	if err := c.load(wikis, programDir); err != nil {
		return err
	}

	reload := make(chan os.Signal, 1)
	signal.Notify(reload, syscall.SIGHUP)

	stop := make(chan os.Signal, 1)
	signal.Notify(stop, os.Interrupt, syscall.SIGTERM)

	for {
		select {
		case <-reload:
			found, err := commands.Enrol(c.options(farm), farm, programDir)
			if err != nil {
				fmt.Fprintf(os.Stderr, "reload: %s\n", err)

				continue
			}
			if err := c.load(found, programDir); err != nil {
				fmt.Fprintf(os.Stderr, "reload: %s\n", err)
			}
		case <-stop:
			return caddy.Stop()
		}
	}
}

// sayWhichNamesWillNotGetACertificate names the wikis whose hostname does not resolve.
func sayWhichNamesWillNotGetACertificate(wikis []program.Wiki) {
	context, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	unreachable := []string{}
	resolver := &net.Resolver{}

	for _, wiki := range wikis {
		if strings.HasPrefix(wiki.Address, "http://") || internalName(wiki.Host) {
			continue
		}
		if _, err := resolver.LookupHost(context, wiki.Host); err != nil {
			unreachable = append(unreachable, wiki.Host)
		}
	}

	if len(unreachable) > 0 {
		fmt.Fprintf(os.Stderr,
			"%s does not resolve here: no certificate can be obtained for it, and the attempt will keep retrying quietly\n",
			strings.Join(unreachable, ", "))
	}
}

// internalName is a name Caddy issues its own certificate for.
func internalName(host string) bool {
	return host == "localhost" || strings.HasSuffix(host, ".localhost") || strings.HasSuffix(host, ".internal")
}

// load adapts the generated Caddyfile and swaps it in.
func (c caddyFarm) load(wikis []program.Wiki, programDir string) error {
	adapter := caddyconfig.GetAdapter("caddyfile")
	if adapter == nil {
		return fmt.Errorf("this build has no caddyfile adapter")
	}

	workers := c.workers
	workers.Program = programDir

	config, warnings, err := adapter.Adapt([]byte(FarmCaddyfile(wikis, workers, Admin(c.admin))), nil)
	if err != nil {
		return err
	}
	for _, warning := range warnings {
		fmt.Fprintf(os.Stderr, "caddyfile: %s\n", warning.Message)
	}

	return caddy.Load(config, false)
}
