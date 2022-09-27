
import SpinnerLoader from '../../tools/bazar/presentation/javascripts/components/SpinnerLoader.js'

let rootsElements = ['.admin-backups-container','.preupdate-backups-container'];
let isVueJS3 = (typeof Vue.createApp == "function");

let appParams = {
    components: { SpinnerLoader },
    data: function() {
        return {
            canForceUpdate: false,
            isPreupdate: false,
            archives: {},
            ready: false,
            updating: false,
            archiving: false,
            message: "",
            messageClass: {
                alert: true,
                ['alert-info']: true
            },
            selectedArchivesToDelete: [],
            savefiles: true,
            savedatabase: true,
            excludedfiles: [],
            extrafiles: [],
            showAdvancedParams: false,
            currentArchiveUid: "",
            archiveMessage: "",
            archiveMessageClass: {
                alert: true,
                ['alert-info']: true
            },
            stoppingArchive: false,
            canForceDelete: false,
            askConfirmationToDelete: false,
            upgradeName: "",
            showReturn: true,
            warnIfNotStarted: true,
            callAsync: true,
        };
    },
    methods: {
        loadArchives: function() {
            let archiveApp = this;
            archiveApp.updating = true;
            archiveApp.message = _t('ADMIN_BACKUPS_LOADING_LIST');
            archiveApp.messageClass = {alert:true,['alert-info']:true};
            $.ajax({
                method: "GET",
                url: wiki.url(`api/archives`),
                success: function(data){
                    archiveApp.archives = {};
                    let archiveNames = [];
                    if (Array.isArray(data)){
                        for (let key of data.keys()) {
                            archiveApp.archives[key] = data[key];
                            archiveNames.push(data.filename);
                          }
                    }
                    archiveApp.message = "";
                    archiveApp.selectedArchivesToDelete = archiveApp.selectedArchivesToDelete.filter(e => archiveNames.includes(e));
                },
                error: function(xhr,status,error){
                    archiveApp.message = _t('ADMIN_BACKUPS_NOT_POSSIBLE_TO_LOAD_LIST');
                    archiveApp.messageClass = {alert:true,['alert-danger']:true};
                    archiveApp.selectedArchivesToDelete = [];
                },
                complete: function(){
                    archiveApp.ready = true;
                    archiveApp.updating = false;
                }
            });
        },
        deleteArchive: function(archive) {
            let archiveApp = this;
            archiveApp.updating = true;
            archiveApp.message = _t('ADMIN_BACKUPS_DELETE_ARCHIVE',{filename:archive.filename});
            archiveApp.messageClass = {alert:true,['alert-info']:true};
            $.ajax({
                method: "POST",
                url: wiki.url(`api/archives/${archive.filename}`),
                data: {
                    action: 'delete'
                },
                success: function(data){
                    archiveApp.message = "";
                    archiveApp.loadArchives();
                    if (Array.isArray(data) 
                        || !data.main){
                        toastMessage(
                            _t('ADMIN_BACKUPS_DELETE_ARCHIVE_POSSIBLE_ERROR',{filename:archive.filename})
                            ,3000,
                            "alert alert-warning");
                    } else {
                        toastMessage(
                            _t('ADMIN_BACKUPS_DELETE_ARCHIVE_SUCCESS',{filename:archive.filename})
                            ,3000,
                            "alert alert-success");
                    }
                    
                },
                error: function(xhr,status,error){
                    archiveApp.message = _t('ADMIN_BACKUPS_DELETE_ARCHIVE_ERROR',{filename:archive.filename});
                    archiveApp.messageClass = {alert:true,['alert-danger']:true};
                    archiveApp.updating = false;
                },
                complete: function(){
                    archiveApp.selectedArchivesToDelete = [];
                }
            });
        },
        deleteSelectedArchives: function (){
            if (this.selectedArchivesToDelete.length == 0){
                toastMessage(_t('ADMIN_BACKUPS_NO_ARCHIVE_TO_DELETE'), 2000, 'alert alert-info')
            } else {
                let archiveApp = this;
                archiveApp.updating = true;
                archiveApp.message = _t('ADMIN_BACKUPS_DELETE_SELECTED_ARCHIVES');
                archiveApp.messageClass = {alert:true,['alert-info']:true};
                $.ajax({
                    method: "POST",
                    url: wiki.url(`api/archives`),
                    data: {
                        action: 'delete',
                        filesnames: archiveApp.selectedArchivesToDelete
                    },
                    success: function(data){
                        archiveApp.message = "";
                        archiveApp.loadArchives();
                        if (Array.isArray(data) 
                            || !data.main){
                            toastMessage(
                                _t('ADMIN_BACKUPS_DELETE_ARCHIVE_POSSIBLE_ERROR',{filename:archiveApp.selectedArchivesToDelete.join(",\n")})
                                ,3000,
                                "alert alert-warning");
                        } else {
                            toastMessage(
                                _t('ADMIN_BACKUPS_DELETE_ARCHIVE_SUCCESS',{filename:archiveApp.selectedArchivesToDelete.join(",\n")})
                                ,3000,
                                "alert alert-success");
                        }
                        
                    },
                    error: function(xhr,status,error){
                        toastMessage(
                            _t('ADMIN_BACKUPS_DELETE_ARCHIVE_ERROR',{filename:archiveApp.selectedArchivesToDelete.join(',')})
                            ,3000,
                            "alert alert-danger");
                        archiveApp.message = _t('ADMIN_BACKUPS_DELETE_ARCHIVE_ERROR',{filename:archiveApp.selectedArchivesToDelete.join(',')});
                        archiveApp.messageClass = {alert:true,['alert-danger']:true};
                        archiveApp.updating = false;
                        archiveApp.loadArchives();
                    }
                });
            }
        },
        toggleSelectedArchive: function (filename){
            if(this.selectedArchivesToDelete.includes(filename)){
                this.selectedArchivesToDelete = this.selectedArchivesToDelete.filter(e => (e != filename));
            } else {
                this.selectedArchivesToDelete.push(filename);
            }
        },
        restoreArchive: function (archive){
            // TODO update this part then restore will work
            let archiveApp = this;
            archiveApp.updating = true;
            archiveApp.message = _t('ADMIN_BACKUPS_RESTORE_ARCHIVE',{filename:archive.filename});
            archiveApp.messageClass = {alert:true,['alert-info']:true};
            $.ajax({
                method: "POST",
                url: wiki.url(`api/archives/${archive.filename}`),
                data: {
                    action: 'restore'
                },
                success: function(data){
                    archiveApp.message = "";
                    archiveApp.loadArchives();
                },
                error: function(xhr,status,error){
                    archiveApp.message = _t('ADMIN_BACKUPS_RESTORE_ARCHIVE_ERROR',{filename:archive.filename});
                    archiveApp.messageClass = {alert:true,['alert-danger']:true};
                    archiveApp.updating = false;
                },
                complete: function(){
                }
            });
        },
        startArchive: function (){
            let archiveApp = this;
            archiveApp.updating = true;
            archiveApp.archiving = true;
            archiveApp.message = "";
            archiveApp.archiveMessage = _t('ADMIN_BACKUPS_START_BACKUP');
            archiveApp.archiveMessageClass = {alert:true,['alert-info']:true};
            $.ajax({
                method: "GET",
                url: wiki.url(`api/archives/archivingStatus/`),
                cache: false,
                success: function(data){
                    if (typeof data != "object" || !data.hasOwnProperty('canArchive')){
                        archiveApp.endStartingUpdateError();
                        return;
                    } else if (data.canArchive){
                        archiveApp.callAsync = (!data.hasOwnProperty('callAsync') || data.callAsync);
                        archiveApp.startArchiveNextStep();
                        return;
                    } else if (data.hasOwnProperty('archiving') && data.archiving) {
                        archiveApp.endStartingUpdateError(_t('ADMIN_BACKUPS_START_BACKUP_ERROR_ARCHIVING').replace(/\n/g,'<br>'));
                        return ;
                    } else if (data.hasOwnProperty('hibernated') && data.hibernated) {
                        archiveApp.endStartingUpdateError(_t('ADMIN_BACKUPS_START_BACKUP_ERROR_HIBERNATE').replace(/\n/g,'<br>'));
                        return ;
                    } else if (data.hasOwnProperty('privatePathWritable') && !data.privatePathWritable) {
                        archiveApp.endStartingUpdateError(_t('ADMIN_BACKUPS_START_BACKUP_PATH_NOT_WRITABLE').replace(/\n/g,'<br>'));
                        return ;
                    } else if (data.hasOwnProperty('canExec') && !data.canExec) {
                        archiveApp.endStartingUpdateError(_t('ADMIN_BACKUPS_START_BACKUP_CANNOT_EXEC').replace(/\n/g,'<br>'));
                        return ;
                    } else if (data.hasOwnProperty('notAvailableOnTheInternet') && !data.notAvailableOnTheInternet) {
                        archiveApp.endStartingUpdateError(_t('ADMIN_BACKUPS_START_BACKUP_FOLDER_AVAILABLE').replace(/\n/g,'<br>'));
                        return ;
                    } else if (data.hasOwnProperty('enoughSpace') && !data.enoughSpace) {
                        archiveApp.endStartingUpdateError(_t('ADMIN_BACKUPS_START_BACKUP_NOT_ENOUGH_SPACE').replace(/\n/g,'<br>'));
                        return ;
                    }
                    archiveApp.endStartingUpdateError();
                },
                error: function(){
                    archiveApp.endStartingUpdateError();
                }
            });
        },
        startArchiveNextStep: function(){
            let archiveApp = this;
            archiveApp.updating = true;
            archiveApp.archiving = true;
            if (!archiveApp.isPreupdate){
                if (!archiveApp.canForceDelete){
                    archiveApp.checkFilesToDelete();
                    return ;
                }
                archiveApp.canForceDelete = false;
                archiveApp.askConfirmationToDelete = false;
            }
            if (!archiveApp.callAsync){
                archiveApp.archiveMessage = _t('ADMIN_BACKUPS_START_BACKUP_SYNC').replace(/\n/g,'<br>');
                archiveApp.archiveMessageClass = {alert:true,['alert-warning']:true};
            }
            let ajaxOptions = {
                method: "POST",
                url: wiki.url(`api/archives`),
                data: {
                    action: 'startArchive',
                    params: {
                        savefiles: archiveApp.savefiles,
                        savedatabase: archiveApp.savedatabase,
                        extrafiles: archiveApp.extrafiles,
                        excludedfiles: archiveApp.excludedfiles
                    },
                    callAsync: archiveApp.callAsync
                },
                success: function(data){
                    if (archiveApp.callAsync){
                        archiveApp.archiveMessage = _t('ADMIN_BACKUPS_STARTED');
                        archiveApp.archiveMessageClass = {alert:true,['alert-info']:true};
                    }
                    archiveApp.currentArchiveUid = data.uid;
                    setTimeout(archiveApp.updateStatus, 2000);
                },
                error: function(xhr,status,error){
                    archiveApp.endStartingUpdateError(`${_t('ADMIN_BACKUPS_START_BACKUP_ERROR')} (${error})`);
                }
            };
            if (!archiveApp.callAsync){
                ajaxOptions.timeout = 6000000 ; // timeout 10 minutes if sync
            }
            $.ajax(ajaxOptions);
        },
        endStartingUpdateError: function(message = ""){
            let archiveApp = this;
            archiveApp.archiveMessage = message.length == 0 ? _t('ADMIN_BACKUPS_START_BACKUP_ERROR') : message;
            archiveApp.archiveMessageClass = {alert:true,['alert-danger']:true};
            archiveApp.updating = false;
            archiveApp.archiving = false;
            if (archiveApp.isPreupdate){
                archiveApp.canForceUpdate = true;
            }
        },
        checkFilesToDelete: function (){
            let archiveApp = this;
            $.ajax({
                method: "POST",
                url: wiki.url(`api/archives`),
                data: {
                    action: 'futureDeletedArchives',
                },
                success: function(data){
                    if (data.files.length == 0){
                        archiveApp.canForceDelete = true;
                        archiveApp.askConfirmationToDelete = false;
                        archiveApp.startArchiveNextStep();
                    } else {
                        archiveApp.updating = false;
                        archiveApp.archiving = false;
                        archiveApp.canForceDelete = false;
                        archiveApp.askConfirmationToDelete = true;
                        archiveApp.archiveMessage = _t('ADMIN_BACKUPS_CONFIRMATION_TO_DELETE',{
                            'files': data.files.join('<br>')
                        }).replace("\n",'<br>');
                        archiveApp.archiveMessageClass = {alert:true,['alert-warning']:true};
                    }
                },
                error: function(xhr,status,error){
                    archiveApp.archiveMessage = _t('ADMIN_BACKUPS_START_BACKUP_ERROR');
                    archiveApp.archiveMessageClass = {alert:true,['alert-danger']:true};
                    archiveApp.updating = false;
                    archiveApp.archiving = false;
                }
            });
        },
        toggleconfimationToDeleteFiles: function (){
            this.canForceDelete = !this.canForceDelete;
        },
        stopArchive: function (){
            if (this.archiving && this.currentArchiveUid.length == 0){
                setTimeout(() => {
                    this.stopArchive()
                }, 300);
                return;
            }
            this.stoppingArchive = true;
            let archiveApp = this;
            $.ajax({
                method: "POST",
                url: wiki.url(`api/archives`),
                cache: false,
                data: {
                    action: 'stopArchive',
                    uid: archiveApp.currentArchiveUid
                },
                success: function(data){
                    archiveApp.archiveMessage = _t('ADMIN_BACKUPS_STOPPING_ARCHIVE');
                    archiveApp.archiveMessageClass = {alert:true,['alert-warning']:true};
                    setTimeout(archiveApp.checkStopped,500);
                },
                error: function(xhr,status,error){
                    archiveApp.archiveMessage = _t('ADMIN_BACKUPS_STOP_BACKUP_ERROR');
                    archiveApp.archiveMessageClass = {alert:true,['alert-danger']:true};
                    archiveApp.stoppingArchive = false;
                }
            });
        },
        checkStopped: function(){
            let archiveApp= this;
            if (archiveApp.archiving && archiveApp.currentArchiveUid > 0){
                let getData = {};
                if (!archiveApp.callAsync){
                    getData.forceStarted = true;
                }
                $.ajax({
                    method: "GET",
                    url: wiki.url(`api/archives/uidstatus/${archiveApp.currentArchiveUid}`),
                    cache: false,
                    data: getData,
                    success: function(data){
                        if (data.stopped){
                            return true;
                        } else if (!data.started){
                            setTimeout(archiveApp.checkStopped, 1000);
                            return false;
                        } else if (data.finished){
                            return true;
                        } else if (!data.running) {
                            setTimeout(archiveApp.checkStopped, 1000);
                            return false;
                        } else {
                            setTimeout(archiveApp.stopArchive, 1000);
                            return false;
                        }
                    },
                    error: function(xhr,status,error){
                        setTimeout(archiveApp.checkStopped, 1000);
                    }
                });
            }
        },
        updateStatus: function(){
            if (this.currentArchiveUid.length > 0){
                let archiveApp= this;
                archiveApp.warnIfNotStarted = false;
                setTimeout(() => {
                    archiveApp.warnIfNotStarted = true ;
                }, 5000);
                let getData = {};
                if (!archiveApp.callAsync){
                    getData.forceStarted = true;
                }
                $.ajax({
                    method: "GET",
                    url: wiki.url(`api/archives/uidstatus/${archiveApp.currentArchiveUid}`),
                    cache: false,
                    data: getData,
                    success: function(data){
                        if (data.stopped){
                            archiveApp.endUpdatingStatus(_t('ADMIN_BACKUPS_UID_STATUS_STOP'),'success');
                        } else if (!data.started){
                            archiveApp.endUpdatingStatus(_t('ADMIN_BACKUPS_UID_STATUS_NOT_FOUND'),'warning');
                            setTimeout(archiveApp.loadArchives, 3000);
                        } else if (data.finished){
                            if (archiveApp.isPreupdate){
                                archiveApp.startForcedUpdate(_t('ADMIN_BACKUPS_UID_STATUS_FINISHED')+'<br/>'+_t('ADMIN_BACKUPS_UID_STATUS_FINISHED_THEN_UPDATING'));
                            } else {
                                archiveApp.endUpdatingStatus(_t('ADMIN_BACKUPS_UID_STATUS_FINISHED'),'success');
                            }
                        } else if (!data.running) {
                            if (archiveApp.warnIfNotStarted) {
                                archiveApp.endUpdatingStatus(_t('ADMIN_BACKUPS_UID_STATUS_NOT_FINISHED'),'danger');
                            } else {
                                setTimeout(archiveApp.updateStatus, 1000);
                            }
                        } else if (archiveApp.stoppingArchive) {
                            archiveApp.archiveMessage = _t('ADMIN_BACKUPS_STOPPING_ARCHIVE');
                            archiveApp.archiveMessage += "<pre>"+data.output.split("\n").slice(-5).join("<br>")+"</pre>";
                            setTimeout(archiveApp.updateStatus, 1000);
                        } else {
                            archiveApp.archiveMessage = _t('ADMIN_BACKUPS_UID_STATUS_RUNNING');
                            archiveApp.archiveMessage += "<pre>"+data.output.split("\n").slice(-5).join("<br>")+"</pre>";
                            archiveApp.archiveMessageClass = {alert:true,['alert-secondary-2']:true};
                            setTimeout(archiveApp.updateStatus, 1000);
                        }
                    },
                    error: function(xhr,status,error){
                        archiveApp.endUpdatingStatus(_t('ADMIN_BACKUPS_UPDATE_UID_STATUS_ERROR'),'danger');
                        setTimeout(archiveApp.loadArchives, 3000);
                    }
                });
            } else {
                this.endUpdatingStatus();
            }
        },
        endUpdatingStatus: function (message = "", className = "info"){
            this.archiveMessage = message;
            this.archiveMessageClass = {alert:true,[`alert-${className}`]:true};
            this.updating = false;
            this.archiving = false;
            this.stoppingArchive = false;
            this.currentArchiveUid = "";
            if (!this.isPreupdate){
                this.loadArchives();
            }
        },
        formatFileSize: function (bytes,decimalPoint) {
            if(bytes == 0) return '0';
            var k = 1024,
                dm = decimalPoint || 0,
                sizes = ['', 'K', 'M', 'G', 'T', 'P', 'E', 'Z', 'Y'],
                i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        },
        downloadUrl: function(archive){
            return wiki.url(`api/archives/${archive.filename}`);
        },
        updateType: function (){
            if (this.$refs.adminBackupsTypeFull.checked){
                this.savefiles = true;
                this.savedatabase = true;
            } else if(this.$refs.adminBackupsTypeOnlyFiles.checked) {
                this.savefiles = true;
                this.savedatabase = false;
            } else if(this.$refs.adminBackupsTypeOnlyDb.checked) {
                this.savefiles = false;
                this.savedatabase = true;
            } else {
                this.savefiles = false;
                this.savedatabase = false;
            }
        },
        removeExtraFile: function (file){
            this.extrafiles = this.extrafiles.filter(e => e != file);
        },
        updateExtraFiles: function (){
            let newVal = this.$refs.newExtraFile.value;
            let newFiles = newVal.split(',');
            if (newFiles.length > 0){
                for (let index = 0; index < newFiles.length; index++) {
                    if (!this.extrafiles.includes(newFiles[index])){
                        this.extrafiles.push(newFiles[index]);
                    }
                }
            }
            this.$refs.newExtraFile.value = "";
        },
        removeExcludedFile: function (file){
            this.excludedfiles = this.excludedfiles.filter(e => e != file);
        },
        updateExcludedFiles: function (){
            let newVal = this.$refs.newExcludedFile.value;
            let newFiles = newVal.split(',');
            if (newFiles.length > 0){
                for (let index = 0; index < newFiles.length; index++) {
                    if (!this.excludedfiles.includes(newFiles[index])){
                        this.excludedfiles.push(newFiles[index]);
                    }
                }
            }
            this.$refs.newExcludedFile.value = "";
        },
        forceUpdate: function() {
            let archiveApp = this;
            $.ajax({
                method: "GET",
                url: wiki.url(`api/archives/forcedUpdateToken/`),
                cache: false,
                success: function(data){
                    if (
                        typeof archiveApp.upgradeName != "string" ||
                        archiveApp.upgradeName.length == 0 ||
                        typeof data != "object" || 
                        !data.hasOwnProperty('token') || 
                        typeof data.token != "string" || 
                        data.token.length == 0){
                        archiveApp.endStartingUpdateError(_t('ADMIN_BACKUPS_FORCED_UPDATE_NOT_POSSIBLE'));
                        archiveApp.canForceUpdate = false;
                    } else {
                        window.location = wiki.url(wiki.pageTag,{upgrade:archiveApp.upgradeName,forcedUpdateToken:data.token});
                    }
                },
                error: function(){
                    archiveApp.endStartingUpdateError(_t('ADMIN_BACKUPS_FORCED_UPDATE_NOT_POSSIBLE'));
                    archiveApp.canForceUpdate = false;
                }
            });
        },
        bypassArchive: function(){
            let archiveApp = this;
            if (archiveApp.archiving){
                archiveApp.stopArchive();
                setTimeout(() => {
                    archiveApp.bypassArchive();
                }, 1000);
            } else {
                archiveApp.startForcedUpdate(_t('ADMIN_BACKUPS_UID_STATUS_FINISHED_THEN_UPDATING'));
            }
        },
        startForcedUpdate: function(message){
            this.endUpdatingStatus(message,'success');
            this.showReturn = false;
            this.forceUpdate();
        }
    },
    mounted (){
        let nodeElement = isVueJS3 ? this.$el.parentNode : this.$el;
        this.isPreupdate = $(nodeElement).hasClass('preupdate-backups-container');
        if (this.isPreupdate){
            this.upgradeName = $(nodeElement).data("upgrade");
        }
        if (isVueJS3){
            $(this.$el.parentNode).on(
                "dblclick",
                function (e) {
                  return false;
                }
              );
        } else {
            $(this.$el).on(
                "dblclick",
                function (e) {
                  return false;
                }
              );
        }
        if (this.isPreupdate){
            this.startArchive();
        } else {
            this.loadArchives();   
        }
    }
};

if (isVueJS3){
    let app = Vue.createApp(appParams);
    rootsElements.forEach(elem => {
        if ($(elem).length > 0){
            app.mount(elem);
        }
    });
} else {
    rootsElements.forEach(elem => {
        if ($(elem).length > 0){
            new Vue({
                ...{el:elem},
                ...appParams
            });
        }
    });
}