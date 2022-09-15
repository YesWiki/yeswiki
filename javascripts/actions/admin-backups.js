
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
            selectedArchivesToDelete: []
        };
    },
    methods: {
        loadArchives: function() {
            let archiveApp = this;
            archiveApp.updating = true;
            archiveApp.message = "Chargement de la liste des archives";
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
                    archiveApp.message = "Impossible de mettre à jour la liste des archives";
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
            archiveApp.message = `Suppression de ${archive.filename}`;
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
                            `Une erreur pourrait avoir eu lieu en supprimant ${archive.filename}`
                            ,3000,
                            "alert alert-warning");
                    } else {
                        toastMessage(
                            `Suppression réussie de ${archive.filename}`
                            ,3000,
                            "alert alert-success");
                    }
                    
                },
                error: function(xhr,status,error){
                    archiveApp.message = `Suppression impossible de ${archive.filename}`;
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
                toastMessage('Aucune archive à supprimer', 2000, 'alert alert-info')
            } else {
                let archiveApp = this;
                archiveApp.updating = true;
                archiveApp.message = `Suppression des archives sélectionnées`;
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
                                `Une erreur pourrait avoir eu lieu en supprimant ${archiveApp.selectedArchivesToDelete.join(',')}`
                                ,3000,
                                "alert alert-warning");
                        } else {
                            toastMessage(
                                `Suppression réussie de ${archiveApp.selectedArchivesToDelete.join(',')}`
                                ,3000,
                                "alert alert-success");
                        }
                        
                    },
                    error: function(xhr,status,error){
                        archiveApp.message = `Suppression impossible de ${archiveApp.selectedArchivesToDelete.join(',')}`;
                        archiveApp.messageClass = {alert:true,['alert-danger']:true};
                        archiveApp.updating = false;
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
            archiveApp.message = `Restauration de ${archive.filename}`;
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
                    archiveApp.message = `Restauration impossible de ${archive.filename}`;
                    archiveApp.messageClass = {alert:true,['alert-danger']:true};
                    archiveApp.updating = false;
                },
                complete: function(){
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