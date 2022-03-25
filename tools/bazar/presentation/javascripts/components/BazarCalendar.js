Vue.component('BazarCalendar', {
  props: [ 'params' ],
  components: {
  },
  data() {
    return {
      selectedEntry: null,
      calendar: null,
      mounted: false
    }
  },
  mounted() {
  },
  methods: {
    addEntry: function (entry){
      let entryId = entry.id_fiche;
      let existingEvent = this.getEventById(entryId);
      if (!existingEvent && typeof entry.bf_date_debut_evenement != "undefined") {
        let backgroundColor = (entry.color == undefined || entry.color.length == 0) ? "": entry.color;
        let newEvent = {
          id: entryId,
          title: entry.bf_titre,
          start: entry.bf_date_debut_evenement,
          end: this.formatEndDate(entry),
          url: entry.url + ((entry['external-data'] != undefined) ? '/iframe':''),
          allDay: this.isAllDayDate(entry.bf_date_debut_evenement),
          className: "bazar-entry"+((this.params.entrydisplay == undefined || this.params.entrydisplay.length == 0 || this.params.entrydisplay == 'modal') ?  " modalbox":""),
          backgroundColor: backgroundColor,
          borderColor: backgroundColor,
          extendedProps: {
            icon: (entry.icon == undefined || entry.icon.length == 0) ? "": `<i class="${entry.icon}">&nbsp;</i>`,
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
      info.jsEvent.preventDefault();
      let entries = this.entries;
      this.selectedEntry = entries.filter(entry => (entry.id_fiche == info.event.id))[0];
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
      let endDate = (entry.bf_date_fin_evenement == undefined) ? null : entry.bf_date_fin_evenement;
      try {
        endDate = new Date(endDate);
      } catch (error) {
        endDate = null;
      }
      if (endDate == null || endDate == "Invalid Date"){
        if (startDate.length <= 10){
          return startDate;
        }
        endDate = new Date(startDate);
        endDate.setDate(endDate.getDate()+1); // +1 day
        endDate.setHours(0);
        endDate.setMinutes(0);
        endDate.setSeconds(0);
        endDate.setMilliseconds(0);
        return endDate.toISOString();
      }
      let endDateRaw = entry.bf_date_fin_evenement;
      if (endDateRaw.length <= 10) {
        endDate.setDate(endDate.getDate()+1); // +1 day
        endDate.setHours(0);
        endDateRaw = endDate.toISOString();
      }
      return endDateRaw;
    },
    mountCalendar: function(){
      if (!this.mounted){
        let calendarEl = $('<div>').on(
          "dblclick",
          function(e) {
            return false;
          }
        );
        $(this.$el).prepend(calendarEl);
        this.calendar = new FullCalendar.Calendar(calendarEl.get(0),this.calendarOptions);
        this.calendar.setOption('eventDidMount',this.updateEventData);
        if (this.params.entrydisplay == 'sidebar'){
          this.calendar.setOption('eventClick' ,this.displaySideBar);
        }
        this.calendar.render();
        this.mounted = true;
      }
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
      if (!$(element).hasClass("iconDefined")){
        if (event.extendedProps.icon.length > 0){
          if (!$(element).hasClass("fc-list-event")){
            $(element).find('.fc-event-title').prepend($(event.extendedProps.icon));
          } else {
            $(element).find('.fc-list-event-title a').prepend($(event.extendedProps.icon));
          }
        }
        $(element).addClass("iconDefined");
      }
      if (this.params.minical != undefined && [1,"1",true,"true"].indexOf(this.params.minical) > -1 && !$(element).hasClass("toolTipDefined")){
        $(element).tooltip({title:event.title, html:true});
        $(element).addClass("toolTipDefined");
      }
    }
  },
  computed: {
    calendarOptions() {
      let extendedList = "";
      switch (this.params['showlist']) {
        case "week":
          extendedList = ",listWeek";
          break;
        case "month":
          extendedList = ",listMonth";
          break;
        case "year":
          extendedList = ",listYear";
          break;
        default:
          break;
      }
      let initialView = 'dayGridMonth';
      switch (this.params['initialview']) {
        case 'timeGridWeek':
          initialView = 'timeGridWeek';
          break;
        case 'timeGridDay':
          initialView = 'timeGridDay';
          break;
        case 'list':
          if (extendedList.length > 0){
            initialView = extendedList.slice(1); // remove first char
            break;
          }
        case 'dayGridMonth':
        default:
          break;
      }
      return {
        editable: false,
        eventDisplay: 'block',
        firstDay : 1,
        headerToolbar: {
          left: 'prev today',
          center: 'title',
          right: `dayGridMonth,timeGridWeek,timeGridDay${extendedList} next`
        },
        initialView: initialView, // TODO use param to choose the view
        locale: wiki.locale,
        navLinks : true, 
        weekNumbers: true // TODO use param
      }
    },
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
        if (this.params['entrydisplay'] == 'sidebar')
          this.$root.getEntryRender(this.selectedEntry)
      }
    },
    params() {
      this.mountCalendar();
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
    <div class="bazar-list-dynamic-template-container">
      <!-- SideNav to display entry -->
      <div v-if="selectedEntry && this.params.entrydisplay == 'sidebar'" class="entry-container">
        <div class="btn-close" @click="selectedEntry = null"><i class="fa fa-times"></i></div>
        <div v-html="selectedEntry.html_render"></div>
      </div>
    </div>
  `
  // TODO add button with link to .ics for local forms + options
})
