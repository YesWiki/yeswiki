// Command yeswiki builds the commands without FrankenPHP, for developing and testing them.
//
// The shipped binary is built by xcaddy from the package at the module root, which is where the
// commands live; this main exists so `go build ./...` works on a machine with no static PHP
// toolchain.
package main

import (
	caddycmd "github.com/caddyserver/caddy/v2/cmd"

	_ "github.com/YesWiki/yeswiki/binary"
	_ "github.com/caddyserver/caddy/v2/modules/standard"
)

func main() {
	caddycmd.Main()
}
