import SpinnerLoader from '../../tools/bazar/presentation/javascripts/components/SpinnerLoader.js'

Vue.prototype.window = window

new Vue({
  el: '.revisions-container',
  components: { SpinnerLoader },
  data: {
    isEntry: false,
    revisions: [],
    selectedRevision: null,
    viewTypes: {
      'current': "Aperçu de cette version",
      'commit_diff': "Modifs apportées par cette version",
      'diff': "Comparaison avec version actuelle"
    },
    displayWikiCode: false,
    selectedViewType: 'current',
  },
  computed: {
    firstRevision() { return this.revisions[this.revisions.length - 1] },
    lastRevision() { return this.revisions[0] },
    restoreUrl() { return wiki.url(`${this.selectedRevision.tag}/revisions`, { restoreRevisionId: this.selectedRevision.id }) },
    previewUrl() { return wiki.url(`${this.selectedRevision.tag}/iframe`, { time: this.selectedRevision.phpTime }) }
  },
  mounted() {
    this.isEntry = this.$el.dataset.isEntry == "1"
    this.revisions = JSON.parse(this.$el.dataset.revisions).map(rev => {
      rev.id = parseInt(rev.id)
      rev.phpTime = rev.time
      rev.time = new Date(rev.time)
      rev.timestamp = rev.time.getTime()
      rev.displayTime = rev.time.toLocaleDateString(window.wiki.locale, { 
        year: '2-digit', month: 'short', day: 'numeric', 
        hour: 'numeric', minute: 'numeric' 
      })
      // initial prop so it gets reactive
      rev.current_code = ''; rev.current_html = ''; 
      rev.commit_diff_html = ''; rev.commit_diff_code = '';
      rev.diff_html = ''; rev.diff_code = '';
      rev.fullyRetrieved = false
      return rev
    })    
    this.calculateRevisionsPlaceInTimeLine()
    this.selectedRevision = this.lastRevision
  },
  watch: {
    selectedRevision() {
      if (this.selectedRevision && !this.selectedRevision.fullyRetrieved) {
        let url = wiki.url(`?api/pages/${this.selectedRevision.tag}`, {
          time: this.selectedRevision.phpTime, 
          includeDiff: true
        })
        $.getJSON(url, (data) => {
          this.selectedRevision.current_html = data.html
          this.selectedRevision.current_code = data.code
          this.selectedRevision.commit_diff_html = data.commit_diff_html
          this.selectedRevision.commit_diff_code = data.commit_diff_code
          this.selectedRevision.diff_html = data.diff_html
          this.selectedRevision.diff_code = data.diff_code
          this.selectedRevision.fullyRetrieved = true
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
    },
    calculateRevisionsPlaceInTimeLine() {
      let revisionsCount = parseInt(this.$el.dataset.revisionsCount)
      let timelineLength = this.lastRevision.timestamp - this.firstRevision.timestamp
      let prevRevision
      this.revisions.forEach((rev, index) => {
        rev.number = revisionsCount - index
        rev.placeInTimeLine = (rev.timestamp - this.firstRevision.timestamp) / timelineLength * 100
        if (prevRevision) {
          // At least 1% gap between each, otherwise we don't see anything in the UI
          let minGap = this.minGapBetween(rev, prevRevision)
          rev.placeInTimeLine = Math.min(rev.placeInTimeLine, prevRevision.placeInTimeLine - minGap)
        }
        prevRevision = rev
      })

      // due to the 1% gap, it can occurs that the first revision ends up with negative position
      // so we recover that gap on the first revisions
      if (this.firstRevision.placeInTimeLine < 0) {
        this.firstRevision.placeInTimeLine = 0
        prevRevision = null
        this.revisions.slice().reverse().forEach((rev, index) => {
          if (prevRevision) {
            let minGap = this.minGapBetween(rev, prevRevision)
            rev.placeInTimeLine = Math.max(rev.placeInTimeLine, prevRevision.placeInTimeLine + minGap)
          }
          prevRevision = rev
        }) 
      }
    },
    minGapBetween(rev1, rev2) {
      // Bigger gap if different day
      return rev1.time.setHours(0,0,0,0) == rev2.time.setHours(0,0,0,0) ? 1.3 : 3
    },
  }
})