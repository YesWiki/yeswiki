package program

import (
	"os"
	"path/filepath"
	"strings"
)

// EnvFile is what an Instance's private/.env states, the host's settings since replicas 20, read the way Symfony's Dotenv reads the lines the wiki writes.
func EnvFile(instance string) map[string]string {
	values := map[string]string{}
	content, err := os.ReadFile(filepath.Join(instance, "private", ".env"))
	if err != nil {
		return values
	}

	for _, line := range strings.Split(string(content), "\n") {
		line = strings.TrimSpace(strings.TrimPrefix(strings.TrimSpace(line), "export "))
		name, value, found := strings.Cut(line, "=")
		if !found || strings.HasPrefix(line, "#") {
			continue
		}
		values[strings.TrimSpace(name)] = unquote(strings.TrimSpace(value))
	}

	return values
}

// DatabaseStated reports whether those settings name a database: a SQLite driver, or a database name.
func DatabaseStated(lookup func(string) string) bool {
	return strings.EqualFold(strings.TrimSpace(lookup("DB_DRIVER")), "sqlite") || strings.TrimSpace(lookup("DB_DATABASE")) != ""
}

func unquote(value string) string {
	if strings.HasPrefix(value, "'") {
		var out strings.Builder
		quoted := false
		for index := 0; index < len(value); index++ {
			switch {
			case value[index] == '\'':
				quoted = !quoted
			case !quoted && value[index] == '\\' && index+1 < len(value):
				index++
				out.WriteByte(value[index])
			case !quoted && (value[index] == ' ' || value[index] == '#'):
				return out.String()
			default:
				out.WriteByte(value[index])
			}
		}

		return out.String()
	}
	if strings.HasPrefix(value, `"`) {
		var out strings.Builder
		for index := 1; index < len(value); index++ {
			switch {
			case value[index] == '\\' && index+1 < len(value):
				index++
				out.WriteByte(value[index])
			case value[index] == '"':
				return out.String()
			default:
				out.WriteByte(value[index])
			}
		}

		return out.String()
	}
	if hash := strings.Index(value, " #"); hash >= 0 {
		value = strings.TrimSpace(value[:hash])
	}

	return value
}
