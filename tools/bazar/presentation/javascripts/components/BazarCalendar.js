Vue.component('BazarCalendar', {
  props: [ 'params' ],
  components: {
  },
  data() {
    return {
      selectedEntry: null,
      calendar: null,
      calendarOptions: {
        editable: false,
        firstDay : 1,
        headerToolbar: {
          left: 'prev today',
          center: 'title',
          right: 'dayGridMonth,dayGridWeek,dayGridDay next'
        },
        initialView: 'dayGridMonth', // TODO use param to choose the view
        locale: wiki.locale,
        navLinks : true, 
        weekNumbers: true // TODO use param
      }
    }
  },
  mounted() {
    let calendarEl = $('<div>').addClass('no-dblclick');
    $(this.$el).prepend(calendarEl);
    this.calendar = new FullCalendar.Calendar(calendarEl.get(0),this.calendarOptions);
    this.calendar.setOption('eventDidMount',this.updateEventData);
    if (this.params.entrydisplay == 'sidebar'){
      this.calendar.setOption('eventClick' ,this.displaySideBar);
    }
    this.calendar.render();
  },
  methods: {
    addEntry: function (entry){
      let entryId = entry.id_fiche;
      let existingEvent = this.getEventById(entryId);
      if (!existingEvent && typeof entry.bf_date_debut_evenement != "undefined") {
        let titleIcon = "";
        let backgroundColor = "";
        let newEvent = {
          id: entryId,
          title: titleIcon + entry.bf_titre,
          start: entry.bf_date_debut_evenement,
          end: this.formatEndDate(entry),
          url: entry.url + ((entry['external-data'] != undefined) ? '/iframe':''),
          allDay: this.isAllDayDate(entry.bf_date_debut_evenement),
          className: "bazar-entry"+((this.params.entrydisplay == undefined || this.params.entrydisplay.length == 0 || this.params.entrydisplay == 'modal') ?  " modalbox":""),
          backgroundColor: backgroundColor,
          extendedProps: {
            htmlattributes: ((entry.html_data != undefined) ? entry.html_data : '')+
              ((entry['external-data'] != undefined) ? ' data-iframe="1"':'')+
              ' data-size="modal-lg"'
          }
        }
        this.calendar.addEvent(newEvent);
      }
    },
    arraysEqual(a, b) {
      if (a === b) return true;
      if (a == null || b == null) return false;
      if (a.length !== b.length) return false;

      a.sort(); b.sort()
      for (var i = 0; i < a.length; ++i) {
        if (a[i] !== b[i]) return false;
      }
      return true;
    },
    displaySideBar: function(info) {
      // TODO repair: NOT working
      info.jsEvent.preventDefault();
      let entries = this.entries;
      this.selectedEntry = entries.filter(entry => (entry.id == info.event.id));
    },
    getEventById: function (id){
      return this.calendar ? this.calendar.getEventById(id): null;
    },
    isAllDayDate: function (date){
      return (date.length <= 10);
    },
    formatEndDate: function(entry){
      // Fixs bug, when no time is specified, is the event is on multiple day, calendJs show it like
      // it end one day earlier
      let startDate = entry.bf_date_debut_evenement;
      if (entry.bf_date_fin_evenement == undefined){
        // TODO manage end hour if start contains hours
        return startDate;
      }
      let endDate = entry.bf_date_fin_evenement;
      if (endDate.length <= 10) {
        let dateObject = new Date(endDate);
        dateObject.setDate(dateObject.getDate()+1); // +1 day
        endDate = dateObject.toISOString();
      }
      return endDate;
    },
    removeEntry: function (entry){
      let entryId = entry.id_fiche;
      let existingEvent = this.getEventById(entryId);
      if (existingEvent){
        existingEvent.remove();
      }
    },
    updateEventData: function (arg){
      let event = arg.event;
      let htmlAttributes = event.extendedProps.htmlattributes;
      let element = $(arg.el);
      $.each($('<div '+ htmlAttributes + '>').data(), function (index, value) {
        $(element).attr('data-'+index, value);
      })
    }
  },
  computed: {
    entries() {
      return this.$root.entriesToDisplay.filter(entry => entry.bf_date_debut_evenement)
    },
    events() {
      return this.calendar ? this.calendar.getEvents(): [];
    },
  },
  watch: {
    selectedEntry: function (newVal, oldVal) {
      if (this.selectedEntry) {
        if (this.params.entrydisplay == 'sidebar')
          this.$root.getEntryRender(this.selectedEntry)
      }
    },
    params() {
    },
    entries(newVal, oldVal) {
      let newIds = newVal.map(e => e.id_fiche)
      let oldIds = oldVal.map(e => e.id_fiche)
      if (!this.arraysEqual(newIds, oldIds)) {
        let entries = this.entries;
        this.$nextTick(function() {
            oldVal.forEach(entry => this.removeEntry(entry));
            entries.forEach(entry => this.addEntry(entry));
          }
        );
      }
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
