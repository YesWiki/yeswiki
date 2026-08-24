package yeswiki

import (
	"fmt"
	"strings"

	"github.com/YesWiki/yeswiki/binary/internal/program"
)

// Unit is what the generated systemd service needs to know.
type Unit struct {
	Binary      string
	Farm        string
	User        string
	ProgramRoot string
}

// account is the user the service runs as.
func (u Unit) account() string {
	if strings.TrimSpace(u.User) != "" {
		return strings.TrimSpace(u.User)
	}

	return program.ServiceUser
}

// Service is a systemd unit serving a farm.
func (u Unit) Service() string {
	root := ""
	if strings.TrimSpace(u.ProgramRoot) != "" {
		root = fmt.Sprintf("Environment=YESWIKI_PROGRAM_ROOT=%s\n", u.ProgramRoot)
	}

	return fmt.Sprintf(`[Unit]
Description=YesWiki farm
After=network.target
Documentation=https://yeswiki.net

[Service]
Type=simple
User=%[1]s
Group=%[1]s
ExecStart=%[2]s serve --farm %[3]s
ExecReload=/bin/sh -c 'kill -HUP $MAINPID'
Restart=on-failure
RestartSec=5s
TimeoutStopSec=30s
%[4]sStateDirectory=%[5]s
Environment=XDG_DATA_HOME=/var/lib/%[5]s
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=full
ProtectHome=true
ProtectKernelTunables=true
ProtectControlGroups=true
RestrictSUIDSGID=true
AmbientCapabilities=CAP_NET_BIND_SERVICE
CapabilityBoundingSet=CAP_NET_BIND_SERVICE
ReadWritePaths=%[3]s
LimitNOFILE=1048576

[Install]
WantedBy=multi-user.target
`, u.account(), u.Binary, u.Farm, root, program.UnitName)
}

// Steps are what has to happen around the unit, which this binary will not do itself.
func (u Unit) Steps() string {
	return fmt.Sprintf(`Write that to /etc/systemd/system/%[1]s.service, then:

	sudo useradd --system --home-dir %[2]s --shell /usr/sbin/nologin %[3]s
	sudo chown -R %[3]s:%[3]s %[2]s
	sudo systemctl daemon-reload
	sudo systemctl enable --now %[1]s

After adding or removing a wiki:

	sudo systemctl reload %[1]s
`, program.UnitName, u.Farm, u.account())
}
