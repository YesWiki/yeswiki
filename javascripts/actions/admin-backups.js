
import SpinnerLoader from '../../tools/bazar/presentation/javascripts/components/SpinnerLoader.js'

let rootsElements = ['.admin-backups-container'];
let isVueJS3 = (typeof Vue.createApp == "function");

let appParams = {
    components: { SpinnerLoader },
    data: function() {
        return {
            archives: {},
            ready: false,
            updating: false,
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
            showAdvancedParams: false
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
                                _t('ADMIN_BACKUPS_DELETE_ARCHIVE_POSSIBLE_ERROR',{filename:archiveApp.selectedArchivesToDelete.join(',')})
                                ,3000,
                                "alert alert-warning");
                        } else {
                            toastMessage(
                                _t('ADMIN_BACKUPS_DELETE_ARCHIVE_SUCCESS',{filename:archiveApp.selectedArchivesToDelete.join(',')})
                                ,3000,
                                "alert alert-success");
                        }
                        
                    },
                    error: function(xhr,status,error){
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
            archiveApp.message = _t('ADMIN_BACKUPS_START_BACKUP');
            archiveApp.messageClass = {alert:true,['alert-info']:true};
            $.ajax({
                method: "POST",
                url: wiki.url(`api/archives`),
                data: {
                    action: 'startArchive',
                    params: {
                        savefiles: archiveApp.savefiles,
                        savedatabase: archiveApp.savedatabase,
                        extrafiles: archiveApp.extrafiles,
                        excludedfiles: archiveApp.excludedfiles
                    }
                },
                success: function(data){
                    archiveApp.message = "";
                    archiveApp.loadArchives();
                },
                error: function(xhr,status,error){
                    archiveApp.message = _t('ADMIN_BACKUPS_START_BACKUP_ERROR');
                    archiveApp.messageClass = {alert:true,['alert-danger']:true};
                },
                complete: function(){
                    archiveApp.updating = false;
                }
            });
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
        }
    },
    mounted (){
        this.loadArchives();
    }
};

if (isVueJS3){
    let app = Vue.createApp(appParams);
    rootsElements.forEach(elem => {
        app.mount(elem);
    });
} else {
    rootsElements.forEach(elem => {
        new Vue({
            ...{el:elem},
            ...appParams
        });
    });
}