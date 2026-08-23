package yeswiki

import (
	"embed"
	"io/fs"
)

//go:embed all:program
var embedded embed.FS

// Program is the tree written out at setup, rooted so that index.php is at its top.
func Program() fs.FS {
	tree, err := fs.Sub(embedded, "program")
	if err != nil {
		panic(err)
	}

	return tree
}
