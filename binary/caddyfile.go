package yeswiki

import (
	"fmt"
	"os"
	"path/filepath"
	"strings"

	"github.com/YesWiki/yeswiki/binary/internal/program"
)

// CaddyfileName is the override an Instance can drop beside its index.php.
const CaddyfileName = "Caddyfile"

// Listen is how `serve` was told to listen.
type Listen struct {
	Address string
	Domain  string
}

// Admin is where Caddy's admin API is exposed, and by default it is nowhere.
type Admin string

// block is the admin global directive.
func (a Admin) block() string {
	if strings.TrimSpace(string(a)) == "" {
		return "\tadmin off\n"
	}

	return fmt.Sprintf("\tadmin %s\n", strings.TrimSpace(string(a)))
}

// Workers is how `serve` was told to run PHP.
type Workers struct {
	Classic  bool
	Count    int
	Program  string
	Instance string
}

// FrontController is the script requests resolve to, and the file a worker runs.
func (w Workers) FrontController() string {
	if w.Classic {
		return "index.php"
	}

	return "worker.php"
}

// block is the frankenphp global directive: a worker per Instance, unless classic mode was asked for.
func (w Workers) block(instances []string) string {
	if w.Classic || len(instances) == 0 {
		return ""
	}

	count := ""
	if w.Count > 0 {
		count = fmt.Sprintf("\n\t\t\tnum %d", w.Count)
	}

	workers := ""
	for _, instance := range instances {
		workers += fmt.Sprintf(`		worker {
			file %s/%s%s
		}
`, instance, w.FrontController(), count)
	}

	return "\tfrankenphp {\n" + workers + "\t}\n"
}

// site is the address line: a domain when there is one, so Caddy obtains a certificate for it.
func (l Listen) site() string {
	if strings.TrimSpace(l.Domain) != "" {
		return l.Domain
	}
	if strings.TrimSpace(l.Address) != "" {
		return "http://" + strings.TrimPrefix(l.Address, "http://")
	}

	return "http://localhost:8080"
}

// automaticHTTPS is off unless a domain was named, so a first run reaches no certificate authority.
func (l Listen) automaticHTTPS() string {
	if strings.TrimSpace(l.Domain) != "" {
		return ""
	}

	return "\tauto_https off\n"
}

// CaddyfileFor is the Instance's own Caddyfile when it has one, and the shipped rules otherwise.
func CaddyfileFor(instance string, listen Listen, workers Workers, admin Admin) (string, error) {
	override := filepath.Join(instance, CaddyfileName)
	if content, err := os.ReadFile(override); err == nil {
		return string(content), nil
	} else if !os.IsNotExist(err) {
		return "", err
	}

	return Caddyfile(instance, listen, workers, admin), nil
}

// Caddyfile is docker/nginx.conf's rules, as the webserver configuration core ships.
func Caddyfile(instance string, listen Listen, workers Workers, admin Admin) string {
	return globals(admin, listen.automaticHTTPS(), workers.block([]string{instance})) +
		"\n" + site(listen.site(), instance, workers.FrontController())
}

// FarmCaddyfile serves every wiki in a farm from one process, each on its own name.
func FarmCaddyfile(wikis []program.Wiki, workers Workers, admin Admin) string {
	instances := make([]string, 0, len(wikis))
	for _, wiki := range wikis {
		if !wiki.Closed {
			instances = append(instances, wiki.Directory)
		}
	}

	file := globals(admin, redirects(wikis), workers.block(instances))
	for _, wiki := range wikis {
		if wiki.Closed {
			file += "\n" + closedSite(wiki.Address, wiki.Why)

			continue
		}
		file += "\n" + site(wiki.Address, wiki.Directory, workers.FrontController())
	}

	return file
}

// redirects turns off the port 80 redirect server when no wiki is served on 443.
func redirects(wikis []program.Wiki) string {
	for _, wiki := range wikis {
		if !strings.Contains(wiki.Address, "://") && !strings.Contains(wiki.Address, ":") {
			return ""
		}
		if strings.HasSuffix(wiki.Address, ":443") {
			return ""
		}
	}

	return "\tauto_https disable_redirects\n"
}

// globals is the block every generated configuration opens with.
func globals(admin Admin, automaticHTTPS string, workers string) string {
	return fmt.Sprintf(`{
%s%s%s	servers {
		timeouts {
			read_body 300s
			read_header 30s
		}
		max_header_size 512kb
	}
}
`, admin.block(), automaticHTTPS, workers)
}

// closedSite is a wiki the farm will not serve, answering rather than refusing the connection.
func closedSite(address, why string) string {
	if strings.TrimSpace(why) == "" {
		why = "This wiki is not being served."
	}

	return fmt.Sprintf(`%s {
	handle {
		respond "%s" 503 {
			close
		}
	}
}
`, address, why)
}

// site is docker/nginx.conf's rules for one wiki, at one address, out of one directory.
func site(address, instance, frontController string) string {
	return fmt.Sprintf(`%[1]s {
	root * %[2]s
	encode zstd gzip

	request_body {
		max_size 512MB
	}

	@immutable path /cache/assets/*
	header @immutable Cache-Control "public, max-age=15778463, immutable"

	@static path *.js *.css *.png *.jpg *.jpeg *.gif *.ico *.woff *.woff2 *.svg
	header @static Cache-Control "public, max-age=15778463"

	@upgrading file %[4]s
	handle @upgrading {
		respond "This wiki is being upgraded, and will be back shortly." 503
	}

	@private path_regexp private ^/(.*/)?private/
	handle @private {
		respond 403
	}

	handle /.well-known/webfinger {
		redir * /?api/webfinger&{query} 301
	}

	@actor path_regexp actor ^/actors/(\d+)(/(?:outbox|inbox|followers|following))?$
	handle @actor {
		rewrite * /index.php
		php_server {
			env QUERY_STRING api/forms/{re.actor.1}/actor{re.actor.2}
			env REQUEST_URI /?api/forms/{re.actor.1}/actor{re.actor.2}
		}
	}

	handle {
		php_server {
			try_files {path} {path}/%[3]s %[3]s
		}
	}
}
`, address, instance, frontController, program.DoorMarker)
}
