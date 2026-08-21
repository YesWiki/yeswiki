package yeswiki

import (
	"fmt"
	"os"
	"path/filepath"
	"strings"
)

// CaddyfileName is the override an Instance can drop beside its index.php.
const CaddyfileName = "Caddyfile"

// Listen is how `serve` was told to listen.
type Listen struct {
	Address string
	Domain  string
}

// Workers is how `serve` was told to run PHP.
type Workers struct {
	Classic bool
	Count   int
	Program string
}

// block is the frankenphp global directive: a worker per Instance, unless classic mode was asked for.
func (w Workers) block() string {
	if w.Classic {
		return ""
	}

	count := ""
	if w.Count > 0 {
		count = fmt.Sprintf("\n\t\t\tnum %d", w.Count)
	}

	return fmt.Sprintf(`	frankenphp {
		worker {
			file %s/worker.php%s
		}
	}
`, w.Program, count)
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
func CaddyfileFor(instance string, listen Listen, workers Workers) (string, error) {
	override := filepath.Join(instance, CaddyfileName)
	if content, err := os.ReadFile(override); err == nil {
		return string(content), nil
	} else if !os.IsNotExist(err) {
		return "", err
	}

	return Caddyfile(instance, listen, workers), nil
}

// Caddyfile is docker/nginx.conf's rules, as the webserver configuration core ships.
func Caddyfile(instance string, listen Listen, workers Workers) string {
	return fmt.Sprintf(`{
%s%s	servers {
		timeouts {
			read_body 300s
			read_header 30s
		}
		max_header_size 512kb
	}
}

%s {
	root * %s
	encode zstd gzip

	request_body {
		max_size 512MB
	}

	@immutable path /cache/assets/*
	header @immutable Cache-Control "public, max-age=15778463, immutable"

	@static path *.js *.css *.png *.jpg *.jpeg *.gif *.ico *.woff *.woff2 *.svg
	header @static Cache-Control "public, max-age=15778463"

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
			try_files {path} {path}/ /index.php
		}
	}
}
`, listen.automaticHTTPS(), workers.block(), listen.site(), instance)
}
