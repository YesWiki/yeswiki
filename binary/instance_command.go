package yeswiki

import (
	"fmt"
	"os"
	"strings"

	"github.com/spf13/cobra"

	"github.com/YesWiki/yeswiki/binary/internal/commands"
	"github.com/YesWiki/yeswiki/binary/internal/program"
)

// instanceFlag names the wiki a command is about when the working directory is not it.
const instanceFlag = "--instance"

// forwardingGuard is set on the console's environment.
const forwardingGuard = "YESWIKI_FORWARDING"

// alreadyForwarding reports whether this process was started by a forward.
func alreadyForwarding() bool {
	return os.Getenv(forwardingGuard) != ""
}

// wikiFor is the wiki a command applies to: what --instance says, else the first wiki at or above the working directory.
func wikiFor(arguments []string) (string, []string, error) {
	stated, rest := takeInstanceFlag(arguments)

	if stated != "" {
		instance, err := program.ResolveInstance(stated, os.Getwd)
		if err != nil {
			return "", nil, err
		}
		if !program.Configured(instance) {
			return "", nil, fmt.Errorf("%s is not a wiki: no yeswiki.config.php with a base_url in it", instance)
		}

		return instance, rest, nil
	}

	working, err := os.Getwd()
	if err != nil {
		return "", nil, err
	}

	instance, found := program.FindInstance(working)
	if !found {
		return "", nil, fmt.Errorf("no wiki here or above %s: name one with %s <path>", working, instanceFlag)
	}

	return instance, rest, nil
}

// instanceArgument puts a flag cobra has already parsed back where wikiFor looks for it.
func instanceArgument(stated string) []string {
	if strings.TrimSpace(stated) == "" {
		return nil
	}

	return []string{instanceFlag, stated}
}

// takeInstanceFlag pulls --instance out of the arguments, so the rest can go to the console untouched.
func takeInstanceFlag(arguments []string) (string, []string) {
	rest := make([]string, 0, len(arguments))

	for i := 0; i < len(arguments); i++ {
		switch {
		case arguments[i] == instanceFlag && i+1 < len(arguments):
			return arguments[i+1], append(rest, arguments[i+2:]...)
		case strings.HasPrefix(arguments[i], instanceFlag+"="):
			return strings.TrimPrefix(arguments[i], instanceFlag+"="), append(rest, arguments[i+1:]...)
		default:
			rest = append(rest, arguments[i])
		}
	}

	return "", rest
}

// forwardToConsole runs a command the binary does not own against the wiki it is standing in.
func forwardToConsole(command *cobra.Command, stated string, arguments []string) error {
	instance, rest, err := wikiFor(append(instanceArgument(stated), arguments...))
	if err != nil {
		return err
	}

	programDir, err := programOf(instance)
	if err != nil {
		return err
	}

	command.Printf("wiki %s\n", instance)

	return phpConsole{}.Console(instance, programDir, rest)
}

// refuseToForward is what a nested invocation says instead of recursing.
func refuseToForward(arguments []string) error {
	return fmt.Errorf("this build has no %q: it carries no PHP, so a wiki's commands cannot run here",
		strings.Join(arguments, " "))
}

// programOf is the Program a wiki's own commands run in.
func programOf(instance string) (string, error) {
	if stated, hasOne := program.OfInstance(instance); hasOne {
		return stated, nil
	}

	return programFor(instance)
}

// programFor is the Program this binary carries, written out if it is not there yet: what a wiki that names none has to fall back on.
func programFor(instance string) (string, error) {
	_, programDir, err := commands.Resolve(commands.Options{
		Directory: instance,
		Source:    Program(),
		Version:   Version,
		Env:       os.Getenv,
		Home:      os.UserHomeDir,
		Wd:        os.Getwd,
		Out:       func(string) {},
	})

	return programDir, err
}

// consoleCommands is the wiki's own list of commands, appended to this binary's help when it is standing in one.
func consoleCommands() (string, bool) {
	working, err := os.Getwd()
	if err != nil {
		return "", false
	}

	instance, found := program.FindInstance(working)
	if !found || alreadyForwarding() {
		return "", false
	}

	programDir, hasOne := program.OfInstance(instance)
	if !hasOne {
		return fmt.Sprintf("\nThis directory is the wiki %s. Its own commands appear here once it "+
			"has a Program: run `yeswiki serve` or `yeswiki setup` for that.\n", instance), true
	}

	listed, err := phpConsole{}.Listing(instance, programDir)
	if err != nil || strings.TrimSpace(listed) == "" {
		return fmt.Sprintf("\nThis directory is the wiki %s, whose own commands could not be listed:\n  %v\n",
			instance, err), true
	}

	return fmt.Sprintf("\nCommands of the wiki in %s:\n\n%s\n", instance, listed), true
}

// helpWithTheWikisCommands appends the wiki's commands to the binary's own help.
func helpWithTheWikisCommands(root *cobra.Command) {
	ownHelp := root.HelpFunc()

	root.SetHelpFunc(func(command *cobra.Command, arguments []string) {
		ownHelp(command, arguments)

		if command != root {
			return
		}
		if extra, inAWiki := consoleCommands(); inAWiki {
			command.Print(extra)
		}
	})
}

// runInsideAWiki is what the root command does with arguments it does not recognise.
func runInsideAWiki(command *cobra.Command, arguments []string) error {
	if len(arguments) == 0 {
		return command.Help()
	}
	if alreadyForwarding() {
		return refuseToForward(arguments)
	}

	stated, _ := command.Flags().GetString("instance")

	return forwardToConsole(command, stated, arguments)
}
