package program

import (
	"fmt"
	"net/url"
	"os"
	"path/filepath"
	"regexp"
	"sort"
	"strings"
)

// UnitName is what the generated systemd service is called, and what `systemctl reload` is told.
const UnitName = "yeswiki"

// ServiceUser is the account a farm runs as, created before the unit is started.
const ServiceUser = "yeswiki"

// Wiki is one Instance in a farm, the name it answers to, and the address it is served at.
type Wiki struct {
	Directory string
	Host      string
	Address   string
	Closed    bool
	Why       string
}

// baseURL matches the configuration entry that decides both questions a farm asks of a directory.
var baseURL = regexp.MustCompile(`['"]base_url['"]\s*=>\s*['"]([^'"]*)['"]`)

// BaseURL is what an Instance's configuration says it is reachable at.
func BaseURL(instance string) (string, bool) {
	content, err := os.ReadFile(filepath.Join(instance, "yeswiki.config.php"))
	if err != nil {
		return "", false
	}

	found := baseURL.FindSubmatch(content)
	if found == nil {
		return "", false
	}

	return string(found[1]), true
}

// HostOf is the name a base URL is served under, without the scheme, the path or the port.
func HostOf(stated string) string {
	host, _ := AddressOf(stated)

	return host
}

// AddressOf is the name a base URL is served under and the Caddy site address that serves it.
func AddressOf(stated string) (string, string) {
	stated = strings.TrimSpace(stated)
	if stated == "" {
		return "", ""
	}
	if !strings.Contains(stated, "://") {
		stated = "https://" + stated
	}

	parsed, err := url.Parse(stated)
	if err != nil {
		return "", ""
	}

	host := strings.ToLower(parsed.Hostname())
	if host == "" {
		return "", ""
	}

	address := host
	if port := parsed.Port(); port != "" {
		address += ":" + port
	}
	if parsed.Scheme == "http" {
		address = "http://" + address
	}

	return host, address
}

// Wikis is every wiki one level down from a farm directory, and the names of the entries that were not one.
func Wikis(farm string) ([]Wiki, []string, error) {
	root, err := filepath.Abs(farm)
	if err != nil {
		return nil, nil, err
	}

	entries, err := os.ReadDir(root)
	if err != nil {
		return nil, nil, err
	}

	wikis := []Wiki{}
	skipped := []string{}

	for _, entry := range entries {
		if !entry.IsDir() {
			continue
		}

		directory := filepath.Join(root, entry.Name())
		stated, found := BaseURL(directory)
		if !found {
			skipped = append(skipped, entry.Name()+" is not a wiki yet")

			continue
		}

		host, address := AddressOf(stated)
		if host == "" {
			skipped = append(skipped, fmt.Sprintf("%s says it is served at %q, which names no host", entry.Name(), stated))

			continue
		}

		wikis = append(wikis, Wiki{Directory: directory, Host: host, Address: address})
	}

	sort.Slice(wikis, func(i, j int) bool { return wikis[i].Address < wikis[j].Address })

	return wikis, skipped, sameHostTwice(wikis)
}

// sameHostTwice refuses a farm where two wikis claim one name.
func sameHostTwice(wikis []Wiki) error {
	seen := map[string]string{}
	for _, wiki := range wikis {
		if first, taken := seen[wiki.Address]; taken {
			return fmt.Errorf("%s and %s are both served at %s: give one of them a base_url of its own",
				filepath.Base(first), filepath.Base(wiki.Directory), wiki.Address)
		}
		seen[wiki.Address] = wiki.Directory
	}

	return nil
}
