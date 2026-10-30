package yeswiki

import (
	"context"
	"errors"
	"os"
	"strings"
	"sync"
	"syscall"
	"testing"
	"time"

	"github.com/YesWiki/yeswiki/binary/internal/program"
)

// patience bounds how long a test waits for the supervisor's goroutines, and only fails a test
// that would otherwise hang: nothing here sleeps on the success path.
const patience = 5 * time.Second

var (
	alpha = program.Wiki{Directory: "/srv/wikis/alpha", Host: "alpha.example.org", Address: "alpha.example.org"}
	beta  = program.Wiki{Directory: "/srv/wikis/beta", Host: "beta.example.org", Address: "beta.example.org"}
	gamma = program.Wiki{Directory: "/srv/wikis/gamma", Host: "gamma.example.org", Address: "gamma.example.org"}
)

func closed(wiki program.Wiki) program.Wiki {
	wiki.Closed = true

	return wiki
}

// fakeClock is the time the supervisor reads, moved by the test only.
type fakeClock struct {
	mu sync.Mutex
	at time.Time
}

func (c *fakeClock) now() time.Time {
	c.mu.Lock()
	defer c.mu.Unlock()

	return c.at
}

func (c *fakeClock) advance(by time.Duration) {
	c.mu.Lock()
	defer c.mu.Unlock()
	c.at = c.at.Add(by)
}

// fakeChild is a consumer that exits when the test says so, or when it is signalled the way a
// well-behaved php-cli is.
type fakeChild struct {
	wiki      program.Wiki
	stubborn  bool
	exit      chan int
	signalled chan os.Signal

	mu      sync.Mutex
	signals []os.Signal
	killed  bool
	exited  bool
}

func (c *fakeChild) Wait() (int, error) {
	return <-c.exit, nil
}

func (c *fakeChild) exitWith(code int) bool {
	c.mu.Lock()
	defer c.mu.Unlock()
	if c.exited {
		return false
	}
	c.exited = true
	c.exit <- code

	return true
}

func (c *fakeChild) Signal(signal os.Signal) error {
	c.mu.Lock()
	c.signals = append(c.signals, signal)
	c.mu.Unlock()
	c.signalled <- signal

	if signal == syscall.SIGTERM && !c.stubborn {
		c.exitWith(0)
	}

	return nil
}

// Kill on a process that already left fails, as os.Process.Kill does, and is not a kill.
func (c *fakeChild) Kill() error {
	if !c.exitWith(-1) {
		return errors.New("os: process already finished")
	}
	c.mu.Lock()
	c.killed = true
	c.mu.Unlock()

	return nil
}

// signalledOnce waits for the child to be signalled. A child is published by Start before the keeper
// holds it, so a stop that comes in between is passed on by hold, a little later than terminate.
func (c *fakeChild) signalledOnce(t *testing.T) {
	t.Helper()
	select {
	case <-c.signalled:
	case <-time.After(patience):
		t.Fatalf("%s was never signalled", c.wiki.Host)
	}
}

func (c *fakeChild) received() ([]os.Signal, bool) {
	c.mu.Lock()
	defer c.mu.Unlock()

	return append([]os.Signal(nil), c.signals...), c.killed
}

// fakeStarter hands every child it starts to the test.
type fakeStarter struct {
	stubborn map[string]bool
	started  chan *fakeChild
}

func newFakeStarter(stubborn ...string) *fakeStarter {
	starter := &fakeStarter{stubborn: map[string]bool{}, started: make(chan *fakeChild, 64)}
	for _, host := range stubborn {
		starter.stubborn[host] = true
	}

	return starter
}

func (s *fakeStarter) Start(_ context.Context, wiki program.Wiki) (Child, error) {
	child := &fakeChild{
		wiki:      wiki,
		stubborn:  s.stubborn[wiki.Host],
		exit:      make(chan int, 1),
		signalled: make(chan os.Signal, 8),
	}
	s.started <- child

	return child, nil
}

func (s *fakeStarter) next(t *testing.T) *fakeChild {
	t.Helper()
	select {
	case child := <-s.started:
		return child
	case <-time.After(patience):
		t.Fatal("no consumer was started")

		return nil
	}
}

func (s *fakeStarter) nothingMore(t *testing.T) {
	t.Helper()
	select {
	case child := <-s.started:
		t.Fatalf("a consumer for %s was started", child.wiki.Host)
	default:
	}
}

// recordingSleep is the backoff the supervisor asked for, returned at once.
type recordingSleep struct {
	slept chan time.Duration
}

func newRecordingSleep() *recordingSleep {
	return &recordingSleep{slept: make(chan time.Duration, 64)}
}

func (r *recordingSleep) sleep(d time.Duration) {
	r.slept <- d
}

func (r *recordingSleep) next(t *testing.T) time.Duration {
	t.Helper()
	select {
	case d := <-r.slept:
		return d
	case <-time.After(patience):
		t.Fatal("the supervisor never waited")

		return 0
	}
}

// logLines is the supervisor's log, one line per write.
type logLines struct {
	lines chan string
}

func newLogLines() *logLines {
	return &logLines{lines: make(chan string, 64)}
}

func (l *logLines) Write(p []byte) (int, error) {
	l.lines <- string(p)

	return len(p), nil
}

func (l *logLines) waitFor(t *testing.T, fragment string) string {
	t.Helper()
	deadline := time.After(patience)
	for {
		select {
		case line := <-l.lines:
			if strings.Contains(line, fragment) {
				return line
			}
		case <-deadline:
			t.Fatalf("the log never said %q", fragment)

			return ""
		}
	}
}

func hostsOf(children ...*fakeChild) map[string]bool {
	hosts := map[string]bool{}
	for _, child := range children {
		hosts[child.wiki.Host] = true
	}

	return hosts
}

func TestReconcileStartsOneChildPerOpenWiki(t *testing.T) {
	starter := newFakeStarter()
	supervisor := NewSupervisor(starter, (&fakeClock{}).now, newRecordingSleep().sleep, newLogLines())

	supervisor.Reconcile([]program.Wiki{alpha, beta, closed(gamma)})
	started := hostsOf(starter.next(t), starter.next(t))
	if !started[alpha.Host] || !started[beta.Host] {
		t.Fatalf("expected a consumer for alpha and beta, got %v", started)
	}

	// Seeing the same farm again is a reload where nothing changed.
	supervisor.Reconcile([]program.Wiki{alpha, beta, closed(gamma)})
	supervisor.Stop(StopGrace)

	starter.nothingMore(t)
}

func TestReconcileStopsRemovedAndClosedWikis(t *testing.T) {
	starter := newFakeStarter()
	// The grace never runs out here: a retired consumer is asked with SIGTERM, not killed.
	never := make(chan struct{})
	defer close(never)
	supervisor := NewSupervisor(starter, (&fakeClock{}).now, func(time.Duration) { <-never }, newLogLines())

	supervisor.Reconcile([]program.Wiki{alpha, beta, gamma})
	children := map[string]*fakeChild{}
	for range 3 {
		child := starter.next(t)
		children[child.wiki.Host] = child
	}

	// beta was closed (not upgraded yet, or its worker would not start) and gamma was removed.
	supervisor.Reconcile([]program.Wiki{alpha, closed(beta)})

	for _, host := range []string{beta.Host, gamma.Host} {
		children[host].signalledOnce(t)
		if signals, _ := children[host].received(); len(signals) != 1 || signals[0] != syscall.SIGTERM {
			t.Errorf("%s should have been asked to stop once with SIGTERM, got %v", host, signals)
		}
	}
	if signals, _ := children[alpha.Host].received(); len(signals) != 0 {
		t.Errorf("alpha is still open and should have been left alone, got %v", signals)
	}

	supervisor.Stop(StopGrace)
	starter.nothingMore(t)
}

func TestExitNotMyModeIsNotRestarted(t *testing.T) {
	starter := newFakeStarter()
	sleep := newRecordingSleep()
	log := newLogLines()
	supervisor := NewSupervisor(starter, (&fakeClock{}).now, sleep.sleep, log)
	defer supervisor.Stop(StopGrace)

	supervisor.Reconcile([]program.Wiki{alpha})
	starter.next(t).exitWith(ExitNotMyMode)

	line := log.waitFor(t, "another mode")
	if !strings.HasPrefix(line, "["+alpha.Host+"] ") {
		t.Errorf("the supervisor's own lines are prefixed by the wiki, got %q", line)
	}
	starter.nothingMore(t)
	select {
	case d := <-sleep.slept:
		t.Fatalf("a wiki in another mode should not be waited on for a restart, got a %s wait", d)
	default:
	}

	// A reload that still finds it open asks again: its administrator may have switched back.
	supervisor.Reconcile([]program.Wiki{alpha})
	if again := starter.next(t); again.wiki.Host != alpha.Host {
		t.Fatalf("expected alpha to be asked again, got %s", again.wiki.Host)
	}
}

func TestFastExitsBackOffUpToSixtySeconds(t *testing.T) {
	starter := newFakeStarter()
	clock := &fakeClock{}
	sleep := newRecordingSleep()
	supervisor := NewSupervisor(starter, clock.now, sleep.sleep, newLogLines())
	defer supervisor.Stop(StopGrace)

	supervisor.Reconcile([]program.Wiki{alpha})

	for _, expected := range []time.Duration{1, 2, 4, 8, 16, 32, 60, 60} {
		child := starter.next(t)
		clock.advance(5 * time.Second)
		child.exitWith(255)

		if waited := sleep.next(t); waited != expected*time.Second {
			t.Fatalf("expected a %s wait, got %s", expected*time.Second, waited)
		}
	}
}

func TestALongLifeResetsTheBackoff(t *testing.T) {
	starter := newFakeStarter()
	clock := &fakeClock{}
	sleep := newRecordingSleep()
	supervisor := NewSupervisor(starter, clock.now, sleep.sleep, newLogLines())
	defer supervisor.Stop(StopGrace)

	supervisor.Reconcile([]program.Wiki{alpha})

	for _, life := range []struct {
		lasted   time.Duration
		code     int
		expected time.Duration
	}{
		{lasted: time.Second, code: 255, expected: time.Second},
		{lasted: time.Second, code: 255, expected: 2 * time.Second},
		{lasted: time.Second, code: 255, expected: 4 * time.Second},
		// --time-limit ran out: the ordinary end of a consumer's life.
		{lasted: time.Hour, code: 0, expected: time.Second},
		{lasted: time.Second, code: 255, expected: time.Second},
		{lasted: time.Second, code: 255, expected: 2 * time.Second},
		// A crash after a long life is restarted at once too: it is not a loop.
		{lasted: 30 * time.Second, code: 255, expected: time.Second},
		{lasted: time.Second, code: 255, expected: time.Second},
	} {
		child := starter.next(t)
		clock.advance(life.lasted)
		child.exitWith(life.code)

		if waited := sleep.next(t); waited != life.expected {
			t.Fatalf("after a life of %s, expected a %s wait, got %s", life.lasted, life.expected, waited)
		}
	}
}

func TestAConfigurationChangeIsRestartedAtOnce(t *testing.T) {
	starter := newFakeStarter()
	clock := &fakeClock{}
	sleep := newRecordingSleep()
	log := newLogLines()
	supervisor := NewSupervisor(starter, clock.now, sleep.sleep, log)
	defer supervisor.Stop(StopGrace)

	supervisor.Reconcile([]program.Wiki{alpha})

	for i, life := range []struct {
		lasted   time.Duration
		code     int
		expected time.Duration
	}{
		{lasted: time.Second, code: 255, expected: time.Second},
		{lasted: time.Second, code: 255, expected: 2 * time.Second},
		// An administrator saving the settings again and again: each save is one restart, never a backoff.
		{lasted: 5 * time.Second, code: ExitConfigurationChanged, expected: time.Second},
		{lasted: 5 * time.Second, code: ExitConfigurationChanged, expected: time.Second},
		{lasted: 5 * time.Second, code: ExitConfigurationChanged, expected: time.Second},
		{lasted: 5 * time.Second, code: ExitConfigurationChanged, expected: time.Second},
		// What comes after starts from the first step again.
		{lasted: time.Second, code: 255, expected: time.Second},
		{lasted: time.Second, code: 255, expected: 2 * time.Second},
	} {
		child := starter.next(t)
		clock.advance(life.lasted)
		child.exitWith(life.code)

		if waited := sleep.next(t); waited != life.expected {
			t.Fatalf("restart %d, after exit %d: expected a %s wait, got %s", i, life.code, life.expected, waited)
		}
		if life.code == ExitConfigurationChanged {
			log.waitFor(t, "the configuration changed")
		}
	}
}

func TestStopSignalsThenKillsAfterGrace(t *testing.T) {
	starter := newFakeStarter(beta.Host)
	graceAsked := make(chan time.Duration, 1)
	graceOver := make(chan struct{})
	sleep := func(d time.Duration) {
		graceAsked <- d
		<-graceOver
	}
	supervisor := NewSupervisor(starter, (&fakeClock{}).now, sleep, newLogLines())

	supervisor.Reconcile([]program.Wiki{alpha, beta})
	children := map[string]*fakeChild{}
	for range 2 {
		child := starter.next(t)
		children[child.wiki.Host] = child
	}

	stopped := make(chan struct{})
	go func() {
		supervisor.Stop(10 * time.Second)
		close(stopped)
	}()

	select {
	case d := <-graceAsked:
		if d != 10*time.Second {
			t.Fatalf("expected a grace of 10s, got %s", d)
		}
	case <-time.After(patience):
		t.Fatal("Stop never started its grace period")
	}
	children[alpha.Host].signalledOnce(t)
	children[beta.Host].signalledOnce(t)
	select {
	case <-stopped:
		t.Fatal("Stop returned while beta, which ignores SIGTERM, was still running")
	default:
	}

	close(graceOver)
	select {
	case <-stopped:
	case <-time.After(patience):
		t.Fatal("Stop did not return after the grace period")
	}

	if signals, killed := children[alpha.Host].received(); len(signals) != 1 || signals[0] != syscall.SIGTERM || killed {
		t.Errorf("alpha left on SIGTERM and should not have been killed, got %v (killed: %v)", signals, killed)
	}
	if signals, killed := children[beta.Host].received(); len(signals) != 1 || signals[0] != syscall.SIGTERM || !killed {
		t.Errorf("beta ignored SIGTERM and should have been killed after the grace, got %v (killed: %v)", signals, killed)
	}
	starter.nothingMore(t)
}

// A wiki waiting out its backoff is not restarted by Stop, nor does it hold Stop up.
func TestStopDoesNotWaitForABackoff(t *testing.T) {
	starter := newFakeStarter()
	waiting := make(chan time.Duration, 2)
	never := make(chan struct{})
	defer close(never)
	sleep := func(d time.Duration) {
		waiting <- d
		<-never
	}
	supervisor := NewSupervisor(starter, (&fakeClock{}).now, sleep, newLogLines())

	supervisor.Reconcile([]program.Wiki{alpha})
	starter.next(t).exitWith(255)
	<-waiting

	stopped := make(chan struct{})
	go func() {
		supervisor.Stop(StopGrace)
		close(stopped)
	}()
	select {
	case <-stopped:
	case <-time.After(patience):
		t.Fatal("Stop waited for a backoff")
	}

	starter.nothingMore(t)
}

// A consumer's output reaches the binary's log a whole line at a time, under its wiki's name.
func TestTheConsumersOutputIsPrefixedByItsWiki(t *testing.T) {
	var log strings.Builder
	output := &prefixedLines{prefix: logPrefix(alpha), out: &log}

	for _, chunk := range []string{"Consuming", " messages\nfrom the queue\n", "Killed"} {
		if _, err := output.Write([]byte(chunk)); err != nil {
			t.Fatal(err)
		}
	}
	output.flush()

	expected := "[alpha.example.org] Consuming messages\n[alpha.example.org] from the queue\n[alpha.example.org] Killed\n"
	if log.String() != expected {
		t.Errorf("expected\n%q\ngot\n%q", expected, log.String())
	}
}
