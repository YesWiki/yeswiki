import SpinnerLoader from '../components/SpinnerLoader.js'

const { createApp } = Vue

const mountElement = document.querySelector('.revisions-container')
const elementDataset = mountElement ? { ...mountElement.dataset } : {}

const app = createApp({
  components: { SpinnerLoader },
  data() {
    return {
      isEntry: false,
      revisions: [],
      selectedRevision: null,
      viewTypes: {
        current: _t('REVISIONS_PREVIEW'),
        commit_diff: _t('REVISIONS_COMMIT_DIFF'),
        diff: _t('REVISIONS_DIFF'),
      },
      displayWikiCode: false,
      selectedViewType: 'current',
      revisionsCount: parseInt(elementDataset.revisionsCount, 10) || 0,
    }
  },
  computed: {
    firstRevision() {
      return this.revisions[this.revisions.length - 1]
    },
    lastRevision() {
      return this.revisions[0]
    },
    isFirstRevision() {
      return (
        this.selectedRevision &&
        this.firstRevision &&
        Vue.toRaw(this.selectedRevision) === Vue.toRaw(this.firstRevision)
      )
    },
    isLastRevision() {
      return (
        this.selectedRevision &&
        this.lastRevision &&
        Vue.toRaw(this.selectedRevision) === Vue.toRaw(this.lastRevision)
      )
    },
    restoreUrl() {
      return wiki.url(`${wiki.pageTag}/revisions`, {
        restoreRevisionId: this.selectedRevision.id,
      })
    },
    fullRestoreUrl() {
      return wiki.url(`${wiki.pageTag}/revisions`, {
        restoreRevisionId: this.selectedRevision.id,
        fullRevert: 1,
      })
    },
    previewUrl() {
      return wiki.url(`${wiki.pageTag}/iframe`, {
        time: this.selectedRevision.phpTime,
        iframelinks: 0,
      })
    },
  },
  mounted() {
    this.isEntry = elementDataset.isEntry === '1'
    this.revisions = JSON.parse(elementDataset.revisions || '[]').map((rev) => {
      rev.id = parseInt(rev.id)
      rev.phpTime = rev.time
      rev.time = new Date(rev.time)
      rev.timestamp = rev.time.getTime()
      rev.displayTime = rev.time.toLocaleDateString(window.wiki.locale, {
        year: '2-digit',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: 'numeric',
      })
      rev.current_code = ''
      rev.current_html = ''
      rev.commit_diff_html = ''
      rev.commit_diff_code = ''
      rev.diff_html = ''
      rev.diff_code = ''
      rev.fullyRetrieved = false
      return rev
    })
    this.calculateRevisionsPlaceInTimeLine()
    this.selectedRevision = this.lastRevision
  },
  watch: {
    selectedRevision() {
      if (this.selectedRevision && !this.selectedRevision.fullyRetrieved) {
        const url = wiki.url(`?api/pages/${wiki.pageTag}`, {
          time: this.selectedRevision.phpTime,
          includeDiff: true,
        })
        fetch(url)
          .then((response) => response.json())
          .then((data) => {
            this.selectedRevision.current_html = data.html
            this.selectedRevision.current_code = data.code
            this.selectedRevision.commit_diff_html = data.commit_diff_html
            this.selectedRevision.commit_diff_code = data.commit_diff_code
            this.selectedRevision.diff_html = data.diff_html
            this.selectedRevision.diff_code = data.diff_code
            this.selectedRevision.fullyRetrieved = true
          })
      }
    },
  },
  methods: {
    timelineItemStyle(revision, includeTransform = true) {
      let result = {}
      if (revision.placeInTimeLine < 50) {
        result = {
          left: `${revision.placeInTimeLine}%`,
          transform: 'translateX(-50%) translateY(50%)',
        }
      } else {
        result = {
          right: `${100 - revision.placeInTimeLine}%`,
          transform: 'translateX(50%) translateY(50%)',
        }
      }
      if (!includeTransform) result.transform = null
      result['z-index'] = revision.number
      return result
    },
    moveRevision(forward = true) {
      const newNumber = this.selectedRevision.number + (forward ? -1 : 1)
      const newRevision = this.revisions.find(
        (rev) => Number(rev.number) === newNumber,
      )
      if (newRevision) this.selectedRevision = newRevision
    },
    calculateRevisionsPlaceInTimeLine() {
      const timelineLength =
        this.lastRevision.timestamp - this.firstRevision.timestamp
      let prevRevision
      this.revisions.forEach((rev, index) => {
        rev.number = this.revisionsCount - index
        rev.placeInTimeLine =
          ((rev.timestamp - this.firstRevision.timestamp) / timelineLength) *
          100
        if (prevRevision) {
          const minGap = this.minGapBetween(rev, prevRevision)
          rev.placeInTimeLine = Math.min(
            rev.placeInTimeLine,
            prevRevision.placeInTimeLine - minGap,
          )
        }
        prevRevision = rev
      })

      if (this.firstRevision.placeInTimeLine < 0) {
        this.firstRevision.placeInTimeLine = 0
        prevRevision = null
        this.revisions
          .slice()
          .reverse()
          .forEach((rev) => {
            if (prevRevision) {
              const minGap = this.minGapBetween(rev, prevRevision)
              rev.placeInTimeLine = Math.max(
                rev.placeInTimeLine,
                prevRevision.placeInTimeLine + minGap,
              )
            }
            prevRevision = rev
          })
      }
    },
    minGapBetween(rev1, rev2) {
      return rev1.time.setHours(0, 0, 0, 0) === rev2.time.setHours(0, 0, 0, 0)
        ? 1.3
        : 3
    },
  },
})

app.config.globalProperties.window = window

if (mountElement) {
  app.mount('.revisions-container')
}
