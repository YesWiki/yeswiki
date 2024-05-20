import SpinnerLoader from '../../tools/bazar/presentation/javascripts/components/SpinnerLoader.js'

new Vue({
  el: '.admin-backups',
  components: { SpinnerLoader },
  data() {
    return {
      canForceUpdate: false,
      isPreupdate: false,
      archives: {},
      ready: false,
      updating: false,
      archiving: false,
      message: '',
      messageClass: {
        alert: true,
        'alert-info': true
      },
      selectedArchivesToDelete: [],
      savefiles: true,
      savedatabase: true,
      currentArchiveUid: '',
      archiveMessage: '',
      archiveMessageClass: {
        alert: true,
        'alert-info': true
      },
      stoppingArchive: false,
      canForceDelete: false,
      askConfirmationToDelete: false,
      packageName: '',
      showReturn: true,
      warnIfNotStarted: true,
      callAsync: true
    }
  },
  methods: {
    async loadArchives() {
      this.updating = true
      this.message = _t('ADMIN_BACKUPS_LOADING_LIST')
      this.messageClass = { alert: true, 'alert-info': true }
      return await this.fetch(wiki.url('?api/archives'))
        .then((data) => {
          this.archives = {}
          const archiveNames = []
          if (Array.isArray(data)) {
            for (const key of data.keys()) {
              this.archives[key] = data[key]
              archiveNames.push(data.filename)
            }
          }
          this.message = ''
          this.selectedArchivesToDelete = this.selectedArchivesToDelete.filter((e) => archiveNames.includes(e))
        }, () => {
          // on error
          this.message = _t('ADMIN_BACKUPS_NOT_POSSIBLE_TO_LOAD_LIST')
          this.messageClass = { alert: true, 'alert-danger': true }
          this.selectedArchivesToDelete = []
          return Promise.resolve()
        })
        .finally(() => {
          this.ready = true
          this.updating = false
        })
    },
    async deleteArchive(archive) {
      this.updating = true
      this.message = _t('ADMIN_BACKUPS_DELETE_ARCHIVE', { filename: archive.filename })
      this.messageClass = { alert: true, 'alert-info': true }
      return await this.fetchPost(wiki.url(`?api/archives/${archive.filename}`), { action: 'delete' })
        .then((data) => {
          this.message = ''
          if (Array.isArray(data) || !data.main) {
            toastMessage(
              _t('ADMIN_BACKUPS_DELETE_ARCHIVE_POSSIBLE_ERROR', { filename: archive.filename }),
              3000,
              'alert alert-warning'
            )
          } else {
            toastMessage(
              _t('ADMIN_BACKUPS_DELETE_ARCHIVE_SUCCESS', { filename: archive.filename }),
              3000,
              'alert alert-success'
            )
          }
          return this.loadArchives()
        })
        .catch((error) => {
          // do nothing
        })
    },
    async deleteSelectedArchives() {
      if (this.selectedArchivesToDelete.length == 0) {
        toastMessage(_t('ADMIN_BACKUPS_NO_ARCHIVE_TO_DELETE'), 2000, 'alert alert-info')
      } else {
        this.updating = true
        this.message = _t('ADMIN_BACKUPS_DELETE_SELECTED_ARCHIVES')
        this.messageClass = { alert: true, 'alert-info': true }
        const formObject = { action: 'delete' }
        if (this.selectedArchivesToDelete.length > 0) {
          this.selectedArchivesToDelete.forEach((filename, idx) => {
            formObject[`filesnames[${idx}]`] = filename
          })
        } else {
          formObject.filesnames = ''
        }
        return await this.fetchPost(wiki.url('?api/archives'), formObject)
          .then((data) => {
            this.message = ''
            if (Array.isArray(data) || !data.main) {
              toastMessage(
                _t('ADMIN_BACKUPS_DELETE_ARCHIVE_POSSIBLE_ERROR', { filename: this.selectedArchivesToDelete.join(',<br/>') }),
                3000,
                'alert alert-warning'
              )
            } else {
              toastMessage(
                _t('ADMIN_BACKUPS_DELETE_ARCHIVE_SUCCESS', { filename: this.selectedArchivesToDelete.join(',<br/>') }),
                3000,
                'alert alert-success'
              )
            }
            return this.loadArchives()
          }, () => {
            toastMessage(
              _t('ADMIN_BACKUPS_DELETE_ARCHIVE_ERROR', { filename: this.selectedArchivesToDelete.join(',<br/>') }),
              3000,
              'alert alert-danger'
            )
            this.message = _t('ADMIN_BACKUPS_DELETE_ARCHIVE_ERROR', { filename: this.selectedArchivesToDelete.join(',') })
            this.messageClass = { alert: true, 'alert-danger': true }
            this.updating = false
            return this.loadArchives()
          })
          .catch((error) => {
            // do nothing
          })
      }
    },
    async fetch(url, options = {}) {
      return await fetch(url, options).then((response) => {
        if (!response.ok) {
          throw `response not ok ; code : ${response.status} (${response.statusText})`
        }
        return response.json()
      })
    },
    async fetchPost(url, formObject, options = {}) {
      const internalOptions = { ...options }
      const formData = new FormData()
      if (typeof formObject === 'object' && formObject !== null) {
        Object.keys(formObject).forEach((key) => {
          if (['string', 'number', 'boolean'].includes(typeof formObject[key])) {
            formData.append(key, formObject[key])
          }
        })
      } else {
        throw new Error('"formObject" should be an object !')
      }
      internalOptions.method = 'POST'
      internalOptions.body = new URLSearchParams(formData)
      internalOptions.headers = (new Headers()).append('Content-Type', 'application/x-www-form-urlencoded')
      return this.fetch(url, internalOptions)
    },
    toggleSelectedArchive(filename) {
      if (this.selectedArchivesToDelete.includes(filename)) {
        this.selectedArchivesToDelete = this.selectedArchivesToDelete.filter((e) => (e != filename))
      } else {
        this.selectedArchivesToDelete.push(filename)
      }
    },
    async restoreArchive(archive) {
      // TODO update this part then restore will work
      this.updating = true
      this.message = _t('ADMIN_BACKUPS_RESTORE_ARCHIVE', { filename: archive.filename })
      this.messageClass = { alert: true, 'alert-info': true }
      return await this.fetchPost(wiki.url(`?api/archives/${archive.filename}`), { action: 'restore', filename: archive.filename })
        .then((data) => {
          this.message = ''
          return this.loadArchives()
        })
        .catch((error) => {
          this.message = _t('ADMIN_BACKUPS_RESTORE_ARCHIVE_ERROR', { filename: archive.filename })
          this.messageClass = { alert: true, 'alert-danger': true }
          this.updating = false
        })
    },
    async startArchive() {
      this.updating = true
      this.archiving = true
      this.message = ''
      this.archiveMessage = _t('ADMIN_BACKUPS_START_BACKUP')
      this.archiveMessageClass = { alert: true, 'alert-info': true }
      return await this.fetch(wiki.url('?api/archives/archivingStatus/'))
        .then((data) => {
          if (typeof data != 'object' || !data.hasOwnProperty('canArchive')) {
            this.endStartingUpdateError()
          } else if (data.canArchive) {
            if (data.hasOwnProperty('dB') && !data.dB) {
              console.log(_t('ADMIN_BACKUPS_START_BACKUP_NOT_DB', { helpBaseUrl: wiki.url('doc') }))
            }
            this.callAsync = (!data.hasOwnProperty('callAsync') || data.callAsync)
            return this.startArchiveNextStep()
          } else if (data.hasOwnProperty('archiving') && data.archiving) {
            this.endStartingUpdateErrorWithT('ADMIN_BACKUPS_START_BACKUP_ERROR_ARCHIVING', 'info')
          } else if (data.hasOwnProperty('hibernated') && data.hibernated) {
            this.endStartingUpdateErrorWithT('ADMIN_BACKUPS_START_BACKUP_ERROR_HIBERNATE', 'info')
          } else if (data.hasOwnProperty('privatePathWritable') && !data.privatePathWritable) {
            this.endStartingUpdateErrorWithT('ADMIN_BACKUPS_START_BACKUP_PATH_NOT_WRITABLE', 'danger')
          } else if (data.hasOwnProperty('canExec') && !data.canExec) {
            this.endStartingUpdateErrorWithT('ADMIN_BACKUPS_START_BACKUP_CANNOT_EXEC', 'info')
          } else if (data.hasOwnProperty('notAvailableOnTheInternet') && !data.notAvailableOnTheInternet) {
            this.endStartingUpdateErrorWithT('ADMIN_BACKUPS_START_BACKUP_FOLDER_AVAILABLE', 'danger')
          } else if (data.hasOwnProperty('enoughSpace') && !data.enoughSpace) {
            this.endStartingUpdateErrorWithT('ADMIN_BACKUPS_START_BACKUP_NOT_ENOUGH_SPACE', 'warning')
          } else {
            this.endStartingUpdateError()
          }
        }, () => {
          // if error
          this.endStartingUpdateError()
        })
        .catch(() => {
          // do nothing (silent)
        })
    },
    async startArchiveNextStep() {
      this.updating = true
      this.archiving = true
      if (!this.isPreupdate) {
        if (!this.canForceDelete) {
          return this.checkFilesToDelete()
        }
        this.canForceDelete = false
        this.askConfirmationToDelete = false
      }
      if (!this.callAsync) {
        this.archiveMessage = _t('ADMIN_BACKUPS_START_BACKUP_SYNC').replace(/\n/g, '<br>')
        this.archiveMessageClass = { alert: true, 'alert-warning': true }
      }
      const formObject = {
        action: 'startArchive',
        'params[savefiles]': this.savefiles,
        'params[savedatabase]': this.savedatabase
      }
      formObject.callAsync = this.callAsync
      const options = {}
      if (!this.callAsync) {
        const controller = new AbortController()
        options.signal = controller.signal
        // timeout 10 minutes if sync
        setTimeout(() => {
          controller.abort()
        }, 6000000)
      }
      return await this.fetchPost(wiki.url('?api/archives'), formObject, options)
        .then((data) => {
          if (this.callAsync) {
            toastMessage(
              _t('ADMIN_BACKUPS_STARTED'),
              2000,
              'alert alert-success'
            )
          }
          this.currentArchiveUid = data.uid
          setTimeout(this.updateStatus, 2000)
        }, (error) => {
          this.endStartingUpdateError(`${_t('ADMIN_BACKUPS_START_BACKUP_ERROR')} (${error})`)
        })
    },
    endStartingUpdateError(message = '', className = 'danger') {
      this.archiveMessage = message.length == 0 ? _t('ADMIN_BACKUPS_START_BACKUP_ERROR') : message
      this.archiveMessageClass = { alert: true, [`alert-${className.length > 0 ? className : 'danger'}`]: true }
      this.updating = false
      this.archiving = false
      if (this.isPreupdate) {
        this.canForceUpdate = true
      }
    },
    endStartingUpdateErrorWithT(name, className = 'danger') {
      if (name.length === 0) {
        throw new Error('name should not be empty')
      }
      return this.endStartingUpdateError(_t(name, { helpBaseUrl: wiki.url('doc') }).replace(/\n/g, '<br>'), className)
    },
    async checkFilesToDelete() {
      return await this.fetchPost(wiki.url('?api/archives'), { action: 'futureDeletedArchives' })
        .then((data) => {
          if (data.files.length == 0) {
            this.canForceDelete = true
            this.askConfirmationToDelete = false
            return this.startArchiveNextStep()
          }
          this.updating = false
          this.archiving = false
          this.canForceDelete = false
          this.askConfirmationToDelete = true
          this.archiveMessage = _t('ADMIN_BACKUPS_CONFIRMATION_TO_DELETE', { files: data.files.join('<br>') }).replace('\n', '<br>')
          this.archiveMessageClass = { alert: true, 'alert-warning': true }
        }, () => {
          // if error
          this.archiveMessage = _t('ADMIN_BACKUPS_START_BACKUP_ERROR')
          this.archiveMessageClass = { alert: true, 'alert-danger': true }
          this.updating = false
          this.archiving = false
        })
    },
    toggleconfimationToDeleteFiles() {
      this.canForceDelete = !this.canForceDelete
    },
    async stopArchive() {
      if (this.archiving && this.currentArchiveUid.length == 0) {
        setTimeout(() => {
          this.stopArchive()
        }, 300)
        return
      }
      this.stoppingArchive = true
      return await this.fetchPost(wiki.url('?api/archives'), {
        action: 'stopArchive',
        uid: this.currentArchiveUid
      })
        .then((data) => {
          this.archiveMessage = _t('ADMIN_BACKUPS_STOPPING_ARCHIVE')
          this.archiveMessageClass = { alert: true, 'alert-warning': true }
          setTimeout(this.checkStopped, 500)
        }, () => {
          this.archiveMessage = _t('ADMIN_BACKUPS_STOP_BACKUP_ERROR')
          this.archiveMessageClass = { alert: true, 'alert-danger': true }
          this.stoppingArchive = false
        })
        .catch(() => {
          // do nothing : silent
        })
    },
    async checkStopped() {
      if (this.archiving && this.currentArchiveUid > 0) {
        const getData = {}
        if (!this.callAsync) {
          getData.forceStarted = true
        }
        return await this.fetch(wiki.url(`?api/archives/uidstatus/${this.currentArchiveUid}`, getData))
          .then((data) => {
            if (data.stopped) {
              return true
            }
            if (!data.started) {
              setTimeout(this.checkStopped, 1000)
            } else if (data.finished) {
              return true
            } else if (!data.running) {
              setTimeout(this.checkStopped, 1000)
              return false
            } else {
              setTimeout(this.stopArchive, 1000)
            }
            return false
          }, () => {
            setTimeout(this.checkStopped, 1000)
          })
          .catch(() => {
            // do nothin : silent
          })
      }
    },
    async updateStatus() {
      if (this.currentArchiveUid.length > 0) {
        this.warnIfNotStarted = false
        setTimeout(() => {
          this.warnIfNotStarted = true
        }, 5000)
        const getData = {}
        if (!this.callAsync) {
          getData.forceStarted = true
        }
        return await this.fetch(wiki.url(`?api/archives/uidstatus/${this.currentArchiveUid}`, getData))
          .then((data) => {
            if (data.stopped) {
              this.endUpdatingStatus(_t('ADMIN_BACKUPS_UID_STATUS_STOP'), 'success')
            } else if (!data.started) {
              this.endUpdatingStatus(_t('ADMIN_BACKUPS_UID_STATUS_NOT_FOUND'), 'warning')
              if (this.isPreupdate) { this.canForceUpdate = true }
              setTimeout(this.loadArchives, 3000)
            } else if (data.finished) {
              if (this.isPreupdate) {
                return this.startForcedUpdate(`${_t('ADMIN_BACKUPS_UID_STATUS_FINISHED')}<br/>${_t('ADMIN_BACKUPS_UID_STATUS_FINISHED_THEN_UPDATING')}`)
              }
              this.endUpdatingStatus()
              toastMessage(
                _t('ADMIN_BACKUPS_UID_STATUS_FINISHED'),
                3000,
                'alert alert-success'
              )
            } else if (!data.running) {
              if (this.warnIfNotStarted) {
                this.endUpdatingStatus(_t('ADMIN_BACKUPS_UID_STATUS_NOT_FINISHED'), 'danger')
              } else {
                setTimeout(this.updateStatus, 1000)
              }
            } else if (this.stoppingArchive) {
              this.archiveMessage = _t('ADMIN_BACKUPS_STOPPING_ARCHIVE')
              this.archiveMessage += `<pre>${data.output.split('\n').slice(-5).join('<br>')}</pre>`
              setTimeout(this.updateStatus, 1000)
            } else {
              this.archiveMessage = _t('ADMIN_BACKUPS_UID_STATUS_RUNNING')
              this.archiveMessage += `<pre>${data.output.split('\n').slice(-5).join('<br>')}</pre>`
              this.archiveMessageClass = { alert: true, 'alert-secondary-2': true }
              setTimeout(this.updateStatus, 1000)
            }
          }, () => {
            this.endUpdatingStatus(_t('ADMIN_BACKUPS_UPDATE_UID_STATUS_ERROR'), 'danger')
            setTimeout(this.loadArchives, 3000)
          })
          .catch(() => {
            // do ntohing : silent
          })
      }
      this.endUpdatingStatus()
    },
    endUpdatingStatus(message = '', className = 'info') {
      this.archiveMessage = message
      this.archiveMessageClass = { alert: true, [`alert-${className}`]: true }
      this.updating = false
      this.archiving = false
      this.stoppingArchive = false
      this.currentArchiveUid = ''
      if (!this.isPreupdate) {
        this.loadArchives()
      }
    },
    formatFileSize(bytes, decimalPoint) {
      if (bytes == 0) {
        return '0'
      }
      const k = 1024
      const dm = decimalPoint || 0
      const sizes = ['', 'K', 'M', 'G', 'T', 'P', 'E', 'Z', 'Y']
      const i = Math.floor(Math.log(bytes) / Math.log(k))
      return `${parseFloat((bytes / k ** i).toFixed(dm))} ${sizes[i]}`
    },
    downloadUrl(archive) {
      return wiki.url(`?api/archives/${archive.filename}`)
    },
    updateType() {
      if (this.$refs.adminBackupsTypeFull.checked) {
        this.savefiles = true
        this.savedatabase = true
      } else if (this.$refs.adminBackupsTypeOnlyFiles.checked) {
        this.savefiles = true
        this.savedatabase = false
      } else if (this.$refs.adminBackupsTypeOnlyDb.checked) {
        this.savefiles = false
        this.savedatabase = true
      } else {
        this.savefiles = false
        this.savedatabase = false
      }
    },
    async forceUpdate() {
      return await this.fetch(wiki.url('?api/archives/forcedUpdateToken/'))
        .then((data) => {
          if (
            typeof this.packageName != 'string'
                        || this.packageName.length == 0
                        || typeof data != 'object'
                        || !data.hasOwnProperty('token')
                        || typeof data.token != 'string'
                        || data.token.length == 0) {
            this.endStartingUpdateErrorWithT('ADMIN_BACKUPS_FORCED_UPDATE_NOT_POSSIBLE')
            this.canForceUpdate = false
          } else {
            window.location = wiki.url(wiki.pageTag, {
              action: 'upgrade',
              package: this.packageName,
              forcedUpdateToken: data.token
            })
          }
        }, () => {
          this.endStartingUpdateErrorWithT('ADMIN_BACKUPS_FORCED_UPDATE_NOT_POSSIBLE')
          this.canForceUpdate = false
        })
        .catch(() => {
          // do nothing : silent
        })
    },
    async bypassArchive() {
      if (this.archiving) {
        setTimeout(() => {
          this.bypassArchive()
        }, 1000)
        return await this.stopArchive()
      }
      return await this.startForcedUpdate(_t('ADMIN_BACKUPS_UID_STATUS_FINISHED_THEN_UPDATING'))
    },
    async startForcedUpdate(message) {
      this.endUpdatingStatus(message, 'success')
      this.showReturn = false
      return await this.forceUpdate()
    }
  },
  mounted() {
    this.isPreupdate = $(this.$el).hasClass('preupdate-backups-container')
    if (this.isPreupdate) {
      this.packageName = $(this.$el).data('package')
    }
    $(this.$el).on(
      'dblclick',
      (e) => false
    )
    if (this.isPreupdate) {
      this.startArchive()
    } else {
      this.loadArchives()
    }
  }
})
