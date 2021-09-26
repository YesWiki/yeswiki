Vue.prototype.window = window
new Vue({
  el: '.revisions-container',
  data: {
    revisions: [],
    selectedRevision: null,
    viewTypes: {
      'html': "Aperçu de la version",
      'code': "Code de la version",
      'diff': "Différences avec la version antérieure"
    },
    selectedViewType: 'html',
  },
  computed: {
    firstRevision() { return this.revisions[this.revisions.length - 1] },
    lastRevision() { return this.revisions[0] },
    restoreUrl() { return `${document.location.search}&restoreRevisionId=${this.selectedRevision.id}` }
  },
  mounted() {
    let revisionsCount = parseInt(this.$el.dataset.revisionsCount)
    this.revisions = JSON.parse(this.$el.dataset.revisions).map(rev => {
      rev.id = parseInt(rev.id)
      rev.time = new Date(rev.time)
      rev.timestamp = rev.time.getTime()
      rev.displayTime = rev.time.toLocaleDateString(window.locale, { 
        year: '2-digit', month: 'short', day: 'numeric', 
        hour: 'numeric', minute: 'numeric' 
      })
      rev.body = ''; rev.html = ''; rev.diff = '';// initial prop so it gets reactive
      return rev
    })    
    let timelineLength = this.lastRevision.timestamp - this.firstRevision.timestamp
    let prevRevision
    this.revisions.forEach((rev, index) => {
      rev.number = revisionsCount - index
      rev.placeInTimeLine = (rev.timestamp - this.firstRevision.timestamp)/timelineLength * 100
      if (prevRevision) {
        // At least 1% gap betwwen each, otherwise we don't see anything in the UI
        rev.placeInTimeLine = Math.min(rev.placeInTimeLine, prevRevision.placeInTimeLine - 1.5)
      }
      prevRevision = rev
    })
    this.selectedRevision = this.lastRevision
  },
  watch: {
    selectedRevision() {
      if (this.selectedRevision && !this.selectedRevision.body) {
        let url = `?api/pages/${this.selectedRevision.id}&includeRender=true`
        let prevRevision = this.revisions.filter(rev => rev.id < this.selectedRevision.id)[0]
        if (prevRevision) url += `&includeDiffFromId=${prevRevision.id}`
        $.getJSON(url, (data) => {
          this.selectedRevision.body = data.body
          this.selectedRevision.html = data.html
          this.selectedRevision.diff = data.diff
        })
      }
    }
  },
  methods: {
    timelineItemStyle(revision, includeTransform = true) {
      let result = {}
      if (revision.placeInTimeLine < 50) 
        result = {left: `${revision.placeInTimeLine}%`, transform: "translateX(-50%) translateY(50%)"}
      else 
        result = {right: `${100 - revision.placeInTimeLine}%`, transform: "translateX(50%) translateY(50%)"}
      if (!includeTransform) result.transform = null
      result['z-index'] = revision.number
      return result
    },
    moveRevision(forward = true) {
      let newNumber = this.selectedRevision.number + (forward ? -1 : 1)
      let newRevision = this.revisions.find(rev => rev.number == newNumber)
      if (newRevision) this.selectedRevision = newRevision
    }
  }
})