package yeswiki

import (
	"context"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"sync"
	"syscall"
	"time"

	"github.com/YesWiki/yeswiki/binary/internal/program"
)

// ExitNotMyMode is what `messenger:consume --supervised` exits with when the wiki's queue is
// consumed in another mode. This binary does not read PHP's configuration, so the consumer it
// starts is how it finds out, and the answer holds until the farm is reloaded.
const ExitNotMyMode = 78

// ExitConfigurationChanged is what `messenger:consume --supervised` exits with when the wiki's
// configuration changed since it started: it stopped between two messages for a consumer that
// knows the new one. That is the ordinary end of a life however short it was, restarted at once
// and not counted towards the backoff; a consumer cannot exit so before its first look at the
// configuration, a few seconds after it started, so this cannot loop faster than that.
const ExitConfigurationChanged = 75

// StopGrace is how long a consumer has to finish the message in hand once asked to stop, which
// keeps a whole stop under the unit's TimeoutStopSec=30s with Caddy's own shutdown after it.
const StopGrace = 10 * time.Second

const (
	// longLife is how long a consumer has to have run for its exit to be the ordinary end of a
	// life (--time-limit, --memory-limit) rather than a loop.
	longLife = 30 * time.Second
	// firstDelay is the wait before a restart, and the first step of the backoff.
	firstDelay = time.Second
	// longestDelay caps the backoff of a consumer that keeps exiting.
	longestDelay = 60 * time.Second
)

// Starter starts the consumer of one wiki.
type Starter interface {
	Start(ctx context.Context, wiki program.Wiki) (Child, error)
}

// Child is one running consumer.
type Child interface {
	// Wait blocks until the consumer has exited. An exit status is not an error: err is for when
	// there is no status to give.
	Wait() (exitCode int, err error)
	Signal(os.Signal) error
	Kill() error
}

// Supervisor keeps one consumer running for each open wiki, restarting it when it exits.
//
// It decides when to start and stop and nothing else: the Starter runs the process, and the clock
// and the sleep are injected, so that this is tested with neither PHP nor real time.
type Supervisor struct {
	starter Starter
	now     func() time.Time
	sleep   func(time.Duration)
	ctx     context.Context
	cancel  context.CancelFunc

	logged sync.Mutex
	log    io.Writer

	mu      sync.Mutex
	current map[string]*keeper
	leaving map[*keeper]bool
	stopped bool
}

// NewSupervisor supervises nothing until it is told which wikis are open.
func NewSupervisor(starter Starter, now func() time.Time, sleep func(time.Duration), log io.Writer) *Supervisor {
	ctx, cancel := context.WithCancel(context.Background())

	return &Supervisor{
		starter: starter,
		now:     now,
		sleep:   sleep,
		ctx:     ctx,
		cancel:  cancel,
		log:     log,
		current: map[string]*keeper{},
		leaving: map[*keeper]bool{},
	}
}

// Reconcile starts a consumer for each open wiki that has none, and stops the consumers of the
// wikis that were closed or removed. A wiki whose consumer said it was not its mode is asked again,
// since a reload is when an administrator's change of mode is expected to take effect.
//
// It does not wait for the consumers it stops: their grace runs in the background, and a wiki
// reopened in the meantime starts its new consumer only once the old one has gone.
func (s *Supervisor) Reconcile(wikis []program.Wiki) {
	s.mu.Lock()
	defer s.mu.Unlock()

	if s.stopped {
		return
	}

	open := map[string]program.Wiki{}
	for _, wiki := range wikis {
		if !wiki.Closed {
			open[wiki.Directory] = wiki
		}
	}

	for directory, keeper := range s.current {
		if _, still := open[directory]; !still {
			delete(s.current, directory)
			s.retire(keeper)
		}
	}

	for directory, wiki := range open {
		if keeper, running := s.current[directory]; running && !keeper.over() {
			continue
		}

		var previous <-chan struct{}
		for leaving := range s.leaving {
			if leaving.wiki.Directory == directory {
				previous = leaving.done
			}
		}

		keeper := newKeeper(wiki)
		s.current[directory] = keeper
		go s.keep(keeper, previous)
	}
}

// Stop asks every consumer to stop with SIGTERM, waits up to the grace for them to finish what
// they hold, and kills the ones still running. Nothing is started after it.
func (s *Supervisor) Stop(grace time.Duration) {
	s.mu.Lock()
	if s.stopped {
		s.mu.Unlock()

		return
	}
	s.stopped = true

	keepers := []*keeper{}
	for _, keeper := range s.current {
		keepers = append(keepers, keeper)
	}
	for keeper := range s.leaving {
		keepers = append(keepers, keeper)
	}
	s.mu.Unlock()

	s.halt(keepers, grace)
	s.cancel()
}

// retire stops a keeper in the background. The caller holds s.mu.
func (s *Supervisor) retire(leaving *keeper) {
	s.leaving[leaving] = true
	leaving.terminate()

	go func() {
		s.halt([]*keeper{leaving}, StopGrace)

		s.mu.Lock()
		delete(s.leaving, leaving)
		s.mu.Unlock()
	}()
}

// halt is SIGTERM, then the grace, then SIGKILL for whoever is still there.
func (s *Supervisor) halt(keepers []*keeper, grace time.Duration) {
	for _, keeper := range keepers {
		keeper.terminate()
	}

	gone := make(chan struct{})
	go func() {
		for _, keeper := range keepers {
			<-keeper.done
		}
		close(gone)
	}()

	expired := make(chan struct{})
	go func() {
		s.sleep(grace)
		close(expired)
	}()

	select {
	case <-gone:
		return
	case <-expired:
	}

	for _, keeper := range keepers {
		keeper.kill()
	}
	<-gone
}

// keep runs one wiki's consumer again and again, until it is told to stop or the consumer says
// the wiki is not consumed in this mode.
func (s *Supervisor) keep(keeper *keeper, previous <-chan struct{}) {
	defer close(keeper.done)

	if previous != nil {
		select {
		case <-previous:
		case <-keeper.quit:
			return
		}
	}

	delay := firstDelay
	for {
		if keeper.quitting() {
			return
		}

		started := s.now()
		code := -1
		child, err := s.starter.Start(s.ctx, keeper.wiki)
		if err != nil {
			s.say(keeper.wiki, "the background jobs consumer could not start, retrying in %s: %s", delay, err)
		} else {
			keeper.hold(child)
			code, err = child.Wait()
			keeper.hold(nil)

			if keeper.quitting() {
				return
			}
			if err != nil {
				s.say(keeper.wiki, "lost track of the background jobs consumer: %s", err)
			}
			if code == ExitNotMyMode {
				keeper.giveUp()
				s.say(keeper.wiki, "background jobs are consumed in another mode: not restarted until the next reload")

				return
			}
		}

		life := s.now().Sub(started)
		recycled := child != nil && code == ExitConfigurationChanged
		if life >= longLife || recycled {
			delay = firstDelay
		}
		switch {
		case recycled:
			s.say(keeper.wiki, "the configuration changed: restarting the background jobs consumer in %s", delay)
		case child != nil && (code != 0 || life < longLife):
			s.say(keeper.wiki, "the background jobs consumer exited with %d after %s, restarting in %s", code, life.Round(time.Second), delay)
		}

		if !keeper.wait(s.sleep, delay) {
			return
		}
		if life < longLife && !recycled {
			delay = min(delay*2, longestDelay)
		}
	}
}

// say writes one line of the binary's log about a wiki, prefixed as its consumer's own lines are.
func (s *Supervisor) say(wiki program.Wiki, format string, arguments ...any) {
	s.logged.Lock()
	defer s.logged.Unlock()

	fmt.Fprintf(s.log, logPrefix(wiki)+format+"\n", arguments...)
}

// logPrefix is how a wiki is named in the binary's log.
func logPrefix(wiki program.Wiki) string {
	name := wiki.Host
	if name == "" {
		name = filepath.Base(wiki.Directory)
	}

	return "[" + name + "] "
}

// keeper is the state of one wiki's supervision, shared between its loop and whoever stops it.
type keeper struct {
	wiki program.Wiki
	quit chan struct{}
	done chan struct{}
	once sync.Once

	mu     sync.Mutex
	child  Child
	killed bool
	given  bool
}

func newKeeper(wiki program.Wiki) *keeper {
	return &keeper{wiki: wiki, quit: make(chan struct{}), done: make(chan struct{})}
}

// terminate asks the keeper to stop and its consumer to finish, once, without waiting for either.
// The consumer is signalled here or, when it is still being started, by hold.
func (k *keeper) terminate() {
	k.once.Do(func() {
		k.mu.Lock()
		defer k.mu.Unlock()

		close(k.quit)
		if k.child != nil {
			_ = k.child.Signal(syscall.SIGTERM)
		}
	})
}

// kill ends the consumer now, and the next one too should it be starting at this very moment.
func (k *keeper) kill() {
	k.mu.Lock()
	defer k.mu.Unlock()
	k.killed = true
	if k.child != nil {
		_ = k.child.Kill()
	}
}

// hold records the running consumer, and passes on a stop that came while it was starting.
func (k *keeper) hold(child Child) {
	k.mu.Lock()
	defer k.mu.Unlock()
	k.child = child
	if child == nil {
		return
	}

	switch {
	case k.killed:
		_ = child.Kill()
	case k.quitting():
		_ = child.Signal(syscall.SIGTERM)
	}
}

func (k *keeper) quitting() bool {
	select {
	case <-k.quit:
		return true
	default:
		return false
	}
}

// giveUp marks a keeper that will start nothing more, so that a reload can replace it at once.
func (k *keeper) giveUp() {
	k.mu.Lock()
	defer k.mu.Unlock()
	k.given = true
}

// over is whether this keeper has stopped supervising for good.
func (k *keeper) over() bool {
	k.mu.Lock()
	given := k.given
	k.mu.Unlock()
	if given {
		return true
	}

	select {
	case <-k.done:
		return true
	default:
		return false
	}
}

// wait sleeps out a backoff, and reports false when the keeper was stopped during it.
func (k *keeper) wait(sleep func(time.Duration), delay time.Duration) bool {
	slept := make(chan struct{})
	go func() {
		sleep(delay)
		close(slept)
	}()

	select {
	case <-slept:
		return true
	case <-k.quit:
		return false
	}
}
