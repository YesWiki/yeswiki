import SpinnerLoader from '../components/SpinnerLoader.js'

const { createApp } = Vue

const app = createApp({
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
        'alert-info': true,
      },
      selectedArchivesToDelete: [],
      savefiles: true,
      savedatabase: true,
      currentArchiveUid: '',
      restoreUid: '',
      archiveMessage: '',
      archiveMessageClass: {
        alert: true,
        'alert-info': true,
      },
      stoppingArchive: false,
      canForceDelete: false,
      askConfirmationToDelete: false,
      packageName: '',
      csrfToken: '',
      showReturn: true,
      warnIfNotStarted: true,
      remoteUrl: '',
      remoteUsername: '',
      remotePassword: '',
      remoteRunning: false,
      remoteCancelling: false,
      remoteBytes: 0,
      remoteTotal: 0,
      remoteMessage: '',
      remoteMessageClass: {
        alert: true,
        'alert-info': true,
      },
    }
  },
  methods: {
    async loadArchives() {
      this.updating = true
      this.message = _t('ADMIN_BACKUPS_LOADING_LIST')
      this.messageClass = { alert: true, 'alert-info': true }
      return await this.fetch(wiki.url('?api/archives'))
        .then(
          (data) => {
            this.archives = {}
            const archiveNames = []
            if (Array.isArray(data)) {
              for (const key of data.keys()) {
                this.archives[key] = data[key]
                archiveNames.push(data.filename)
              }
            }
            this.message = ''
            this.selectedArchivesToDelete =
              this.selectedArchivesToDelete.filter((e) =>
                archiveNames.includes(e),
              )
          },
          (pError) => {
            this.message = _t(
              `ADMIN_BACKUPS_NOT_POSSIBLE_TO_LOAD_LIST${pError.message.trim() !== '' ? ` : ${pError}` : ''}`,
            )
            this.messageClass = { alert: true, 'alert-danger': true }
            this.selectedArchivesToDelete = []
            return Promise.resolve()
          },
        )
        .finally(() => {
          this.ready = true
          this.updating = false
        })
    },
    async deleteArchive(archive) {
      this.updating = true
      this.message = _t('ADMIN_BACKUPS_DELETE_ARCHIVE', {
        filename: archive.filename,
      })
      this.messageClass = { alert: true, 'alert-info': true }
      return await this.fetchPost(
        wiki.url(`?api/archives/${archive.filename}`),
        { action: 'delete' },
      )
        .then((data) => {
          this.message = ''
          if (Array.isArray(data) || !data.main) {
            toastMessage(
              _t('ADMIN_BACKUPS_DELETE_ARCHIVE_POSSIBLE_ERROR', {
                filename: archive.filename,
              }),
              3000,
              'alert alert-warning',
            )
          } else {
            toastMessage(
              _t('ADMIN_BACKUPS_DELETE_ARCHIVE_SUCCESS', {
                filename: archive.filename,
              }),
              3000,
              'alert alert-success',
            )
          }
          return this.loadArchives()
        })
        .catch((pError) => {
          console.log(pError)
        })
    },
    async deleteSelectedArchives() {
      if (this.selectedArchivesToDelete.length === 0) {
        toastMessage(
          _t('ADMIN_BACKUPS_NO_ARCHIVE_TO_DELETE'),
          2000,
          'alert alert-info',
        )
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
          .then(
            (data) => {
              this.message = ''
              if (Array.isArray(data) || !data.main) {
                toastMessage(
                  _t('ADMIN_BACKUPS_DELETE_ARCHIVE_POSSIBLE_ERROR', {
                    filename: this.selectedArchivesToDelete.join(',<br/>'),
                  }),
                  3000,
                  'alert alert-warning',
                )
              } else {
                toastMessage(
                  _t('ADMIN_BACKUPS_DELETE_ARCHIVE_SUCCESS', {
                    filename: this.selectedArchivesToDelete.join(',<br/>'),
                  }),
                  3000,
                  'alert alert-success',
                )
              }
              return this.loadArchives()
            },
            () => {
              toastMessage(
                _t('ADMIN_BACKUPS_DELETE_ARCHIVE_ERROR', {
                  filename: this.selectedArchivesToDelete.join(',<br/>'),
                }),
                3000,
                'alert alert-danger',
              )
              this.message = _t('ADMIN_BACKUPS_DELETE_ARCHIVE_ERROR', {
                filename: this.selectedArchivesToDelete.join(','),
              })
              this.messageClass = { alert: true, 'alert-danger': true }
              this.updating = false
              return this.loadArchives()
            },
          )
          .catch((pError) => {
            console.log(pError)
          })
      }
    },
    async fetch(url, options = {}) {
      const response = await fetch(url, options)
      const text = await response.text()
      let data
      try {
        data = JSON.parse(text)
      } catch {
        data = null
      }
      if (data === null || typeof data !== 'object') {
        throw new Error(
          `${_t('ERROR_CONTACT_ADMIN')} ${response.statusText} (${response.status}) - "${this.responseExcerpt(text)}"`,
        )
      }
      if (!response.ok) {
        throw new Error(
          `${_t('ERROR_CONTACT_ADMIN')} ${response.statusText} (${response.status}) - "${data.error || data.exceptionMessage || this.responseExcerpt(text)}"`,
        )
      }
      return data
    },
    responseExcerpt(text) {
      const excerpt = String(text)
        .replace(/<[^>]*>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
      if (excerpt.length === 0) {
        return _t('ADMIN_BACKUPS_EMPTY_ANSWER')
      }
      return excerpt.length > 300 ? `${excerpt.slice(0, 300)}…` : excerpt
    },
    async fetchPost(url, formObject, options = {}) {
      const internalOptions = { ...options }
      const formData = new FormData()
      if (typeof formObject === 'object' && formObject !== null) {
        Object.keys(formObject).forEach((key) => {
          if (
            ['string', 'number', 'boolean'].includes(typeof formObject[key])
          ) {
            formData.append(key, formObject[key])
          }
        })
      } else {
        throw new Error('"formObject" should be an object !')
      }
      internalOptions.method = 'POST'
      internalOptions.body = new URLSearchParams(formData)
      internalOptions.headers = {
        'Content-Type': 'application/x-www-form-urlencoded',
      }
      return this.fetch(url, internalOptions)
    },
    toggleSelectedArchive(filename) {
      if (this.selectedArchivesToDelete.includes(filename)) {
        this.selectedArchivesToDelete = this.selectedArchivesToDelete.filter(
          (e) => e !== filename,
        )
      } else {
        this.selectedArchivesToDelete.push(filename)
      }
    },
    restoreConfirmation(archive) {
      const params = { filename: archive.filename }
      if (archive.type === 'only_db') {
        return _t('ADMIN_BACKUPS_RESTORE_CONFIRM_ONLY_DB', params)
      }
      if (archive.type === 'only_files') {
        return _t('ADMIN_BACKUPS_RESTORE_CONFIRM_ONLY_FILES', params)
      }
      return _t('ADMIN_BACKUPS_RESTORE_CONFIRM_FULL', params)
    },
    async restoreArchive(archive) {
      if (!confirm(this.restoreConfirmation(archive))) {
        return
      }
      this.updating = true
      this.message = _t('ADMIN_BACKUPS_RESTORE_ARCHIVE', {
        filename: archive.filename,
      })
      this.messageClass = { alert: true, 'alert-info': true }
      return await this.fetchPost(
        wiki.url(`?api/archives/${archive.filename}`),
        { action: 'restore' },
      )
        .then((data) => {
          this.restoreUid = (data && data.uid) || ''
          if (this.restoreUid.length === 0) {
            return this.failRestore(archive)
          }
          return this.followRestore(archive)
        })
        .catch(() => this.failRestore(archive))
    },
    failRestore(archive, data = null) {
      this.message = _t('ADMIN_BACKUPS_RESTORE_ARCHIVE_ERROR', {
        filename: archive.filename,
      })
      if (data && data.failed) {
        this.message = this.failureMessage(data, this.message)
      }
      this.messageClass = { alert: true, 'alert-danger': true }
      this.updating = false
      this.restoreUid = ''
    },
    async followRestore(archive) {
      if (this.restoreUid.length === 0) {
        return
      }
      return await this.fetch(
        wiki.url(`?api/archives/uidstatus/${this.restoreUid}`),
      )
        .then((data) => {
          const tail = (data.output || '').split('\n').slice(-5).join('<br>')
          // A run that failed or was stopped also reads as finished: it ended.
          if (data.failed || data.stopped) {
            return this.failRestore(archive, data)
          }
          if (data.finished) {
            this.restoreUid = ''
            this.message = _t('ADMIN_BACKUPS_RESTORE_ARCHIVE_SUCCESS', {
              filename: archive.filename,
            })
            this.messageClass = { alert: true, 'alert-success': true }
            toastMessage(
              _t('ADMIN_BACKUPS_RESTORE_ARCHIVE_SUCCESS', {
                filename: archive.filename,
              }),
              3000,
              'alert alert-success',
            )
            setTimeout(() => {
              window.location.href = wiki.url(wiki.pageTag)
            }, 2000)
            return
          }
          this.message = `${_t('ADMIN_BACKUPS_RESTORE_ARCHIVE', {
            filename: archive.filename,
          })}<pre>${tail}</pre>`
          setTimeout(() => this.followRestore(archive), 1000)
        })
        .catch(() => this.failRestore(archive))
    },
    async startArchive() {
      this.updating = true
      this.archiving = true
      this.message = ''
      this.archiveMessage = _t('ADMIN_BACKUPS_START_BACKUP')
      this.archiveMessageClass = { alert: true, 'alert-info': true }
      return await this.fetch(wiki.url('?api/archives/archivingStatus'))
        .then(
          (data) => {
            if (
              typeof data != 'object' ||
              !Object.prototype.hasOwnProperty.call(data, 'canArchive')
            ) {
              this.endStartingUpdateError()
            } else if (data.canArchive) {
              return this.startArchiveNextStep()
            } else if (
              Object.prototype.hasOwnProperty.call(data, 'archiving') &&
              data.archiving
            ) {
              this.endStartingUpdateErrorWithT(
                'ADMIN_BACKUPS_START_BACKUP_ERROR_ARCHIVING',
                'info',
              )
            } else if (
              Object.prototype.hasOwnProperty.call(data, 'hibernated') &&
              data.hibernated
            ) {
              this.endStartingUpdateErrorWithT(
                'ADMIN_BACKUPS_START_BACKUP_ERROR_HIBERNATE',
                'info',
              )
            } else if (
              Object.prototype.hasOwnProperty.call(
                data,
                'privatePathWritable',
              ) &&
              !data.privatePathWritable
            ) {
              this.endStartingUpdateErrorWithT(
                'ADMIN_BACKUPS_START_BACKUP_PATH_NOT_WRITABLE',
                'danger',
              )
            } else if (
              Object.prototype.hasOwnProperty.call(
                data,
                'notAvailableOnTheInternet',
              ) &&
              !data.notAvailableOnTheInternet
            ) {
              this.endStartingUpdateErrorWithT(
                'ADMIN_BACKUPS_START_BACKUP_FOLDER_AVAILABLE',
                'danger',
              )
            } else if (
              Object.prototype.hasOwnProperty.call(data, 'enoughSpace') &&
              !data.enoughSpace
            ) {
              this.endStartingUpdateErrorWithT(
                'ADMIN_BACKUPS_START_BACKUP_NOT_ENOUGH_SPACE',
                'warning',
              )
            } else if (
              Object.prototype.hasOwnProperty.call(data, 'canArchive') &&
              !data.canArchive
            ) {
              this.endStartingUpdateErrorWithT(
                'ADMIN_BACKUPS_CANNOT_ARCHIVE',
                'danger',
              )
            } else {
              this.endStartingUpdateError()
            }
          },
          (pError) => {
            this.endStartingUpdateError(pError)
          },
        )
        .catch((pError) => {
          console.log(pError)
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
      const formObject = {
        action: 'startArchive',
        'params[savefiles]': this.savefiles,
        'params[savedatabase]': this.savedatabase,
      }
      return await this.fetchPost(wiki.url('?api/archives'), formObject).then(
        (data) => {
          toastMessage(_t('ADMIN_BACKUPS_STARTED'), 2000, 'alert alert-success')
          this.currentArchiveUid = data.uid
          setTimeout(this.updateStatus, 2000)
        },
        (pError) => {
          console.log(pError)
          this.endStartingUpdateError(
            _t('ADMIN_BACKUPS_START_BACKUP_ERROR') +
              (pError.message.trim() !== '' ? ` : ${pError}` : ''),
          )
        },
      )
    },
    endStartingUpdateError(message = '', className = 'danger') {
      this.archiveMessage =
        message.length === 0 ? _t('ADMIN_BACKUPS_START_BACKUP_ERROR') : message
      this.archiveMessageClass = {
        alert: true,
        [`alert-${className.length > 0 ? className : 'danger'}`]: true,
      }
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
      return this.endStartingUpdateError(
        _t(name, { helpBaseUrl: wiki.url('doc') }).replace(/\n/g, '<br>'),
        className,
      )
    },
    async checkFilesToDelete() {
      return await this.fetchPost(wiki.url('?api/archives'), {
        action: 'futureDeletedArchives',
      }).then(
        (data) => {
          if (data.files.length === 0) {
            this.canForceDelete = true
            this.askConfirmationToDelete = false
            return this.startArchiveNextStep()
          }
          this.updating = false
          this.archiving = false
          this.canForceDelete = false
          this.askConfirmationToDelete = true
          const escHtml = (s) =>
            String(s)
              .replace(/&/g, '&amp;')
              .replace(/</g, '&lt;')
              .replace(/>/g, '&gt;')
              .replace(/"/g, '&quot;')
          this.archiveMessage = _t('ADMIN_BACKUPS_CONFIRMATION_TO_DELETE', {
            files: data.files.map(escHtml).join('<br>'),
          }).replace('\n', '<br>')
          this.archiveMessageClass = { alert: true, 'alert-warning': true }
        },
        () => {
          this.archiveMessage = _t('ADMIN_BACKUPS_START_BACKUP_ERROR')
          this.archiveMessageClass = { alert: true, 'alert-danger': true }
          this.updating = false
          this.archiving = false
        },
      )
    },
    toggleconfimationToDeleteFiles() {
      this.canForceDelete = !this.canForceDelete
    },
    async stopArchive() {
      if (this.archiving && this.currentArchiveUid.length === 0) {
        setTimeout(() => {
          this.stopArchive()
        }, 300)
        return
      }
      this.stoppingArchive = true
      return await this.fetchPost(wiki.url('?api/archives'), {
        action: 'stopArchive',
        uid: this.currentArchiveUid,
      })
        .then(
          () => {
            this.archiveMessage = _t('ADMIN_BACKUPS_STOPPING_ARCHIVE')
            this.archiveMessageClass = { alert: true, 'alert-warning': true }
            setTimeout(this.checkStopped, 500)
          },
          (pError) => {
            this.archiveMessage = _t(
              `ADMIN_BACKUPS_STOP_BACKUP_ERROR${pError.message.trim() !== '' ? ` : ${pError}` : ''}`,
            )
            this.archiveMessageClass = { alert: true, 'alert-danger': true }
            this.stoppingArchive = false
          },
        )
        .catch((pError) => {
          console.log(pError)
        })
    },
    async checkStopped() {
      if (this.archiving && this.currentArchiveUid > 0) {
        return await this.fetch(
          wiki.url(`?api/archives/uidstatus/${this.currentArchiveUid}`),
        )
          .then(
            (data) => {
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
            },
            () => {
              setTimeout(this.checkStopped, 1000)
            },
          )
          .catch((pError) => {
            console.log(pError)
          })
      }
    },
    async updateStatus() {
      if (this.currentArchiveUid.length > 0) {
        this.warnIfNotStarted = false
        setTimeout(() => {
          this.warnIfNotStarted = true
        }, 5000)
        return await this.fetch(
          wiki.url(`?api/archives/uidstatus/${this.currentArchiveUid}`),
        )
          .then(
            (data) => {
              if (data.failed) {
                this.endUpdatingStatus(
                  this.failureMessage(
                    data,
                    _t('ADMIN_BACKUPS_UID_STATUS_FAILED'),
                  ),
                  'danger',
                )
                if (this.isPreupdate) {
                  this.canForceUpdate = true
                }
              } else if (data.stopped) {
                this.endUpdatingStatus(
                  _t('ADMIN_BACKUPS_UID_STATUS_STOP'),
                  'success',
                )
              } else if (!data.started) {
                this.endUpdatingStatus(
                  _t('ADMIN_BACKUPS_UID_STATUS_NOT_FOUND'),
                  'warning',
                )
                if (this.isPreupdate) {
                  this.canForceUpdate = true
                }
                setTimeout(this.loadArchives, 3000)
              } else if (data.finished) {
                if (this.isPreupdate) {
                  return this.startForcedUpdate(
                    `${_t('ADMIN_BACKUPS_UID_STATUS_FINISHED')}<br/>${_t('ADMIN_BACKUPS_UID_STATUS_FINISHED_THEN_UPDATING')}`,
                  )
                }
                this.endUpdatingStatus()
                toastMessage(
                  _t('ADMIN_BACKUPS_UID_STATUS_FINISHED'),
                  3000,
                  'alert alert-success',
                )
              } else if (!data.running) {
                if (this.warnIfNotStarted) {
                  this.endUpdatingStatus(
                    _t('ADMIN_BACKUPS_UID_STATUS_NOT_FINISHED'),
                    'danger',
                  )
                } else {
                  setTimeout(this.updateStatus, 1000)
                }
              } else if (this.stoppingArchive) {
                this.archiveMessage = _t('ADMIN_BACKUPS_STOPPING_ARCHIVE')
                this.archiveMessage += `<pre>${data.output.split('\n').slice(-5).join('<br>')}</pre>`
                setTimeout(this.updateStatus, 1000)
              } else {
                this.archiveMessage =
                  data.state === 'pending'
                    ? _t('ADMIN_BACKUPS_UID_STATUS_PENDING')
                    : _t('ADMIN_BACKUPS_UID_STATUS_RUNNING')
                this.archiveMessage += `<pre>${data.output.split('\n').slice(-5).join('<br>')}</pre>`
                this.archiveMessageClass = {
                  alert: true,
                  'alert-secondary-2': true,
                }
                setTimeout(this.updateStatus, 1000)
              }
            },
            (pError) => {
              this.endUpdatingStatus(
                _t('ADMIN_BACKUPS_UPDATE_UID_STATUS_ERROR') +
                  (pError.message.trim() !== '' ? ` : ${pError}` : ''),
                'danger',
              )
              setTimeout(this.loadArchives, 3000)
            },
          )
          .catch((pError) => {
            console.log(pError)
          })
      }
      this.endUpdatingStatus()
    },
    /**
     * What to say of a run the queue reports failed or killed: why, and the last lines it wrote.
     */
    failureMessage(data, heading) {
      let message = heading
      if (data.state === 'killed') {
        message += `<br>${_t('ADMIN_BACKUPS_UID_STATUS_KILLED')}`
      } else if (data.error) {
        message += `<br>${this.escapeHtml(data.error)}`
      }
      const tail = (data.output || '').split('\n').slice(-5).join('\n')
      if (tail.trim() !== '') {
        message += `<pre>${this.escapeHtml(tail)}</pre>`
      }
      return message
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
      if (Number(bytes) === 0) {
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
    postTo(url) {
      const form = document.createElement('form')
      form.method = 'post'
      form.action = url
      const token = document.createElement('input')
      token.type = 'hidden'
      token.name = 'csrf-token'
      token.value = this.csrfToken
      form.appendChild(token)
      document.body.appendChild(form)
      form.submit()
    },
    async forceUpdate() {
      return await this.fetch(wiki.url('?api/archives/forcedUpdateToken'))
        .then(
          (data) => {
            if (
              typeof this.packageName != 'string' ||
              this.packageName.length === 0 ||
              typeof data != 'object' ||
              !Object.prototype.hasOwnProperty.call(data, 'token') ||
              typeof data.token != 'string' ||
              data.token.length === 0
            ) {
              this.endStartingUpdateErrorWithT(
                'ADMIN_BACKUPS_FORCED_UPDATE_NOT_POSSIBLE',
              )
              this.canForceUpdate = false
            } else {
              this.postTo(
                wiki.url(wiki.pageTag, {
                  action: 'upgrade',
                  package: this.packageName,
                  forcedUpdateToken: data.token,
                }),
              )
            }
          },
          (pError) => {
            this.endStartingUpdateErrorWithT(
              `ADMIN_BACKUPS_FORCED_UPDATE_NOT_POSSIBLE${pError.message.trim() !== '' ? ` : ${pError}` : ''}`,
            )
            this.canForceUpdate = false
          },
        )
        .catch((pError) => {
          console.log(pError)
        })
    },
    async bypassArchive() {
      if (this.archiving) {
        setTimeout(() => {
          this.bypassArchive()
        }, 1000)
        return await this.stopArchive()
      }
      return await this.startForcedUpdate(
        _t('ADMIN_BACKUPS_UID_STATUS_FINISHED_THEN_UPDATING'),
      )
    },
    async startForcedUpdate(message) {
      this.endUpdatingStatus(message, 'success')
      this.showReturn = false
      return await this.forceUpdate()
    },
    escapeHtml(text) {
      return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
    },
    remoteStepMessage(step) {
      const messages = {
        checking: _t('ADMIN_BACKUPS_REMOTE_STEP_CHECKING'),
        starting: _t('ADMIN_BACKUPS_REMOTE_STEP_STARTING'),
        archiving: _t('ADMIN_BACKUPS_REMOTE_STEP_ARCHIVING'),
        identifying: _t('ADMIN_BACKUPS_REMOTE_STEP_IDENTIFYING'),
        downloading: _t('ADMIN_BACKUPS_REMOTE_STEP_DOWNLOADING'),
        cleaning: _t('ADMIN_BACKUPS_REMOTE_STEP_CLEANING'),
      }
      return messages[step] || this.escapeHtml(step)
    },
    postRemoteBackup(formObject) {
      return this.fetchPost(wiki.url('?api/remotebackup'), {
        ...formObject,
        'csrf-token': this.csrfToken,
      })
    },
    async resumeRemoteBackup() {
      return await this.fetch(wiki.url('?api/remotebackup')).then(
        (data) => {
          if (!data.running) {
            return
          }
          this.remoteRunning = true
          this.showRemoteState(data)
          setTimeout(this.pollRemoteBackup, 1000)
        },
        () => {},
      )
    },
    async startRemoteBackup() {
      if (this.remoteRunning) {
        return
      }
      this.remoteRunning = true
      this.remoteBytes = 0
      this.remoteTotal = 0
      this.setRemoteMessage(_t('ADMIN_BACKUPS_REMOTE_CONNECTING'), 'info')
      return await this.postRemoteBackup({
        action: 'start',
        url: this.remoteUrl,
        username: this.remoteUsername,
        password: this.remotePassword,
      }).then(
        (data) => {
          this.remotePassword = ''
          this.showRemoteState(data)
          setTimeout(this.pollRemoteBackup, 500)
        },
        (pError) => {
          this.endRemoteBackup(this.escapeHtml(pError.message), 'danger')
        },
      )
    },
    // The queue moves the fetch on, step by step; the page only watches it. On a wiki whose
    // queue runs at the end of page views, each of these requests is one more such view.
    async pollRemoteBackup() {
      if (!this.remoteRunning || this.remoteCancelling) {
        return
      }
      return await this.fetch(wiki.url('?api/remotebackup')).then(
        (data) => {
          if (data.error) {
            return this.endRemoteBackup(this.escapeHtml(data.error), 'danger')
          }
          if (data.step === 'idle') {
            // given up from another tab
            return this.endRemoteBackup(
              _t('ADMIN_BACKUPS_REMOTE_CANCELLED'),
              'warning',
            )
          }
          if (!data.running) {
            const done = _t('ADMIN_BACKUPS_REMOTE_FINISHED', {
              filename: this.escapeHtml(data.filename || ''),
            })
            this.endRemoteBackup(done, 'success')
            toastMessage(done, 3000, 'alert alert-success')
            return this.loadArchives()
          }
          this.showRemoteState(data)
          setTimeout(
            this.pollRemoteBackup,
            data.step === 'downloading' ? 500 : 2000,
          )
        },
        (pError) => {
          this.endRemoteBackup(this.escapeHtml(pError.message), 'danger')
        },
      )
    },
    showRemoteState(data) {
      this.remoteBytes = data.bytes || 0
      this.remoteTotal = data.total || 0
      let message = this.remoteStepMessage(data.step)
      if (data.step === 'downloading' && this.remoteTotal > 0) {
        message += ` ${this.formatFileSize(this.remoteBytes)} / ${this.formatFileSize(this.remoteTotal)}`
      }
      if (data.warning) {
        message += `<br>${this.escapeHtml(data.warning)}`
      }
      if (data.output) {
        message += `<pre>${this.escapeHtml(data.output).split('\n').slice(-5).join('<br>')}</pre>`
      }
      this.setRemoteMessage(message, 'secondary-2')
    },
    setRemoteMessage(message, className) {
      this.remoteMessage = message
      this.remoteMessageClass = { alert: true, [`alert-${className}`]: true }
    },
    endRemoteBackup(message, className) {
      this.remoteRunning = false
      this.remoteCancelling = false
      this.setRemoteMessage(message, className)
    },
    async cancelRemoteBackup() {
      this.remoteCancelling = true
      return await this.postRemoteBackup({ action: 'cancel' }).then(
        () => {
          this.endRemoteBackup(_t('ADMIN_BACKUPS_REMOTE_CANCELLED'), 'warning')
        },
        (pError) => {
          this.endRemoteBackup(this.escapeHtml(pError.message), 'danger')
        },
      )
    },
  },
  mounted() {
    const container = this.$el.parentElement || this.$el
    this.isPreupdate = container.classList.contains(
      'preupdate-backups-container',
    )
    this.csrfToken = container.dataset.csrfToken || wiki.antiCsrfToken || ''
    if (this.isPreupdate) {
      this.packageName = container.dataset.package || ''
      this.startArchive()
    } else {
      this.loadArchives()
      this.resumeRemoteBackup()
    }
  },
})

app.mount('.admin-backups')
