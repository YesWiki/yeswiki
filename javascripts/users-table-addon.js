const usersTableServiceAddOn = {
    data: function(){
        return {
            users: {},
            usersDataTables: {},
        };
    },
    methods: {
        appendActivationColumn: function(){
            for (const dataTableApiIdx in this.usersDataTables) {
                let settings = this.usersDataTables[dataTableApiIdx].init();
                let node = this.usersDataTables[dataTableApiIdx].table().node();
                this.usersDataTables[dataTableApiIdx].destroy(false);
                node.querySelector('thead > tr').appendChild(document.createElement('th'))
                node.querySelectorAll('tbody > tr').forEach((row)=>{
                    let name = row.children[1].innerText;
                    let td = document.createElement('td');
                    if (name in this.users){
                        td.innerText = JSON.stringify({
                            activatedStatus:this.users[name].activatedStatus,
                            isAdmin:this.users[name].isAdmin,
                        });
                    }
                    row.appendChild(td)
                });
                node.querySelector('tfoot > tr').appendChild(document.createElement('th'))
                settings.columnDefs = [
                    ...('columnDefs' in settings ? settings.columnDefs : []),
                    ...[
                        {
                            targets: -1,
                            title: _t('ACCOUNTACTIVATION_BY_EMAIL_ACTIVATION_STATUS'),
                            render: (data,type,row)=>{
                                let name = row[1];
                                if (!(name in this.users)) return '';
                                let cellData = '';
                                try {
                                    cellData = JSON.parse(data);
                                } catch (error) {
                                    console.warn({data,row});
                                    throw error;
                                }
                                let activatedStatus = cellData.activatedStatus || false;
                                let isAdmin = cellData.isAdmin || false;
                                return this.renderButton(name,activatedStatus,isAdmin);
                            }
                        }
                    ]
                ];
                this.usersDataTables[dataTableApiIdx] = new DataTable(node,settings);
            }
        },
        getUsers: async function(){
            let url = wiki.url('?api/users');
            return await fetch(url)
                .then((response)=>{
                    if (!response.ok){
                        throw `error when getting ${url}`
                    }
                    return response.json().then((decoded)=>{
                        let result = {};
                        if (Array.isArray(decoded)){
                            decoded.forEach((user)=>{
                                result[user.name] = user;
                            })
                        }
                        return result;
                    });
                });
        },
        init: function(){
            this.searchAndAddTables();
            this.getUsers()
              .then((users)=>{
                this.users=users;
                this.appendActivationColumn();
            })
              .catch((error)=>console.warn(error))
        },
        manageClick: function(action,name){
            if (!(name in this.users)){
                alert(`user '${String(name)}' is not existing !`);
                return false;
            }
            let url = '';
            switch (action) {
                case 'activate':
                    if (this.users[name].activatedStatus){
                        alert(`user '${String(name)}' is already activated !`);
                        return false;
                    }
                    url = wiki.url(`?api/emailactivation/${name}/activate`);
                    break;
                case 'inactivate':
                    if (!this.users[name].activatedStatus){
                        alert(`user '${String(name)}' is already inactivated !`);
                        return false;
                    }
                    url = wiki.url(`?api/emailactivation/${name}/inactivate`);
                    break;
                default:
                    alert(`action '${String(action)}' is not existing !`);
                    return false;
            }
            fetch(url,{method: 'POST'})
              .then((response)=>{
                if (response.ok){
                    this.getUsers()
                      .then((users)=>{
                        if (name in users){
                            this.users[name] = users[name];
                            let newValue = JSON.stringify(
                                {
                                    activatedStatus:users[name].activatedStatus,
                                    isAdmin:users[name].isAdmin,
                                }
                            );
                            let baseObj = this;
                            for (const dataTableApiIdx in this.usersDataTables) {
                                this.usersDataTables[dataTableApiIdx].rows(function(idx,data){
                                    return (data.length > 0 && data[1] == name);
                                })
                                  .every(function(rowIdx){
                                    let d = this.data();
                                    let cell = this.cell(rowIdx,d.length-1)
                                    cell.data(newValue)
                                    cell.node().innerHTML = baseObj.renderButton(name,users[name].activatedStatus,users[name].isAdmin);
                                  })
                            }
                        } else {
                            throw `not possible to ${action} user ${name} because user is not anymore a user !`
                        }
                      })
                } else {
                    throw `not possible to ${action} user ${name}`;
                }
              })
              .catch((error)=>{
                alert(`error '${String(error)}'`);
              })
        },
        renderButton: function(name,activatedStatus,isAdmin){
            let btnType = '';
            let btnText = '';
            let action = '';
            if (activatedStatus){
                action = 'inactivate';
                if (isAdmin){
                    btnType = 'btn-info';
                    btnText = _t('ACCOUNTACTIVATION_BY_EMAIL_ACTIVATION_INACTIVATE_ADMIN');
                } else {
                    btnType = 'btn-danger';
                    btnText = _t('ACCOUNTACTIVATION_BY_EMAIL_ACTIVATION_INACTIVATE');
                }
            } else if (isAdmin){
                action = 'activate';
                btnType = 'btn-info';
                btnText = _t('ACCOUNTACTIVATION_BY_EMAIL_ACTIVATION_ACTIVATE_ADMIN');
            } else {
                action = 'activate';
                btnType = 'btn-primary';
                btnText = _t('ACCOUNTACTIVATION_BY_EMAIL_ACTIVATION_ACTIVATE');
            }
            return `
              <button 
                class="btn btn-sm ${btnType}" 
                onClick="usersTableServiceAddOn.methods.manageClick('${action}',${JSON.stringify(name).replace(/(^"|"$)/g,'\'')})">
                ${btnText}
              </button>
            `;
        },
        searchAndAddTables: function(){
            document.querySelectorAll('#users-table-action').forEach((item)=>{
                let table = null;
                let tables = item.children
                let len = tables.length
                for (let i = 0; i < len && !table; i++) {
                    if (tables[i].classList.contains('dataTables_wrapper')){
                        table = tables[i];
                    }
                }
                if (table){
                    let tableId = table.id.slice(0,-"_wrapper".length);
                    let datatable = table.querySelector(`#${tableId}`);
                    if (datatable && DataTable.isDataTable(datatable)){
                        this.usersDataTables[tableId] = new DataTable(datatable);
                    }
                }
            });
        }
    },
    initData: function(){
        this.methods.parent = this;
        // init data -- not needed with VueJs
        let data = this.data();
        for(const key in data){
            this.methods[key] = data[key];
        }
    },
    mounted: function(){
        this.initData(); // not needed with VueJs
        this.methods.init(); // replace by this.init() in VueJs
    }

};
document.addEventListener('DOMContentLoaded',()=>{
    if (usersTableService){
        usersTableServiceAddOn.mounted();
    }
});