
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
            }
        };
    },
    methods: {
        loadArchives: function() {
            let archiveApp = this;
            archiveApp.message = "Chargement de la liste des archives";
            archiveApp.messageClass = {alert:true,['alert-info']:true};
            $.ajax({
                method: "GET",
                url: wiki.url(`api/archives`),
                success: function(data){
                    archiveApp.archives = {};
                    if (Array.isArray(data)){
                        for (let key of data.keys()) {
                            archiveApp.archives[key] = data[key];
                          }
                    }
                    archiveApp.message = "";
                },
                error: function(xhr,status,error){
                    archiveApp.message = "IMpossible de mettre à jour la liste des archives";
                    archiveApp.messageClass = {alert:true,['alert-danger']:true};
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
                            ,1500,
                            "alert alert-warning");
                    } else {
                        toastMessage(
                            `Suppression réussie de ${archive.filename}`
                            ,1500,
                            "alert alert-success");
                    }
                    
                },
                error: function(xhr,status,error){
                    archiveApp.message = `Suppression impossible de ${archive.filename}`;
                    archiveApp.messageClass = {alert:true,['alert-danger']:true};
                    archiveApp.updating = false;
                }
            });
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