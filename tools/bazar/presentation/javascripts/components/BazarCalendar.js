Vue.component('BazarCalendar', {
  props: [ 'params' ],
  components: {
  },
  data() {
    return {
      selectedEntry: null,
      calendar: null,
      calendarOptions: {
        buttonIcons: {
          prev: '◄',
          next: '►',
          prevYear: 'chevrons-left', // double chevron
          nextYear: 'chevrons-right' // double chevron
        },
        editable: false,
        events: [
          { title: 'event 1', date: '2022-03-15' },
          { title: 'event 2', date: '2022-03-17' }
        ],
        eventLimit: true, // allow more link when too many events
        firstDay : 1,
        headerToolbar: {
          left: 'prev,next today',
          center: 'title',
          right: 'dayGridMonth,dayGridWeek,dayGridDay'
        },
        initialView: 'dayGridMonth', // to do use param to choose the view
        locale: wiki.locale,
        themeSystem: 'bootstrap3',
        weekNumbers: true // TODO use param
      }
    }
  },
  mounted() {
    let calendarEl = $('<div>').addClass('no-dblclick');
    $(this.$el).prepend(calendarEl);
    this.calendar = new FullCalendar.Calendar(calendarEl.get(0),this.calendarOptions);
    this.calendar.setOption('eventClick',this.handleEventClick);
    this.calendar.render();
  },
  methods: {
    handleEventClick: function(info) {
      alert('Event: ' + info.event.title);
  
      // change the border color just for fun
      info.el.style.borderColor = 'red';
    }
  },
  computed: {
    entries() {
      return this.$root.entriesToDisplay.filter(entry => entry.bf_date_debut_evenement && entry.bf_date_fin_evenement)
    },
  },
  watch: {
    selectedEntry: function (newVal, oldVal) {
      if (oldVal) oldVal.marker._icon.classList.remove('selected')
      if (this.selectedEntry) {
        if (this.params.entrydisplay == 'modal')
          this.$root.openEntryModal(this.selectedEntry)
        else
          this.$root.getEntryRender(this.selectedEntry)
      }
    },
    params() {
    },
    entries(newVal, oldVal) {
    }
  },
  template: `
    <div class="bazar-calendar-container">
      <!-- SideNav to display entry -->
      <div v-if="selectedEntry && this.params.entrydisplay == 'sidebar'" class="entry-container">
        <div class="btn-close" @click="selectedEntry = null"><i class="fa fa-times"></i></div>
        <div v-html="selectedEntry.html_render"></div>
      </div>
    </div>
  `
})
