const usersTableServiceAddOn = {
  users: {},
  tables: [],
  nameOf(row) {
    return row.cells[1] ? row.cells[1].textContent.trim() : ''
  },
  async getUsers() {
    const url = wiki.url('?api/users')
    const response = await fetch(url)
    if (!response.ok) {
      throw new Error(`error when getting ${url}`)
    }
    const decoded = await response.json()
    const result = {}
    if (Array.isArray(decoded)) {
      decoded.forEach((user) => {
        result[user.name] = user
      })
    }
    return result
  },
  renderButton(name, activatedStatus, isAdmin) {
    let btnType = ''
    let btnText = ''
    let action = ''
    if (activatedStatus) {
      action = 'inactivate'
      if (isAdmin) {
        btnType = 'yw-btn--info'
        btnText = _t('ACCOUNTACTIVATION_BY_EMAIL_ACTIVATION_INACTIVATE_ADMIN')
      } else {
        btnType = 'yw-btn--danger'
        btnText = _t('ACCOUNTACTIVATION_BY_EMAIL_ACTIVATION_INACTIVATE')
      }
    } else if (isAdmin) {
      action = 'activate'
      btnType = 'yw-btn--info'
      btnText = _t('ACCOUNTACTIVATION_BY_EMAIL_ACTIVATION_ACTIVATE_ADMIN')
    } else {
      action = 'activate'
      btnType = 'yw-btn--primary'
      btnText = _t('ACCOUNTACTIVATION_BY_EMAIL_ACTIVATION_ACTIVATE')
    }
    const escapedName = JSON.stringify(name).replace(/(^"|"$)/g, "'")
    return `
      <button
        class="yw-btn yw-btn--sm ${btnType}"
        onClick="usersTableServiceAddOn.manageClick('${action}',${escapedName})">
        ${btnText}
      </button>
    `
  },
  renderCellFor(name) {
    this.tables.forEach((table) => {
      Array.from(table.tBodies[0].rows).forEach((row) => {
        if (this.nameOf(row) === name && name in this.users) {
          const cell = row.cells[row.cells.length - 1]
          cell.innerHTML = this.renderButton(
            name,
            this.users[name].activatedStatus,
            this.users[name].isAdmin,
          )
        }
      })
    })
  },
  appendActivationColumn() {
    this.tables.forEach((table) => {
      const headRow = table.tHead ? table.tHead.rows[0] : null
      if (headRow) {
        const th = document.createElement('th')
        th.setAttribute('data-yw-no-sort', '')
        th.textContent = _t('ACCOUNTACTIVATION_BY_EMAIL_ACTIVATION_STATUS')
        headRow.appendChild(th)
      }
      Array.from(table.tBodies[0].rows).forEach((row) => {
        const name = this.nameOf(row)
        const td = document.createElement('td')
        if (name in this.users) {
          td.innerHTML = this.renderButton(
            name,
            this.users[name].activatedStatus,
            this.users[name].isAdmin,
          )
        }
        row.appendChild(td)
      })
      const footRow = table.tFoot ? table.tFoot.rows[0] : null
      if (footRow) footRow.appendChild(document.createElement('th'))
    })
  },
  manageClick(action, name) {
    if (!(name in this.users)) {
      alert(`user '${String(name)}' is not existing !`)
      return
    }
    let url = ''
    switch (action) {
      case 'activate':
        if (this.users[name].activatedStatus) {
          alert(`user '${String(name)}' is already activated !`)
          return
        }
        url = wiki.url(`?api/emailactivation/${name}/activate`)
        break
      case 'inactivate':
        if (!this.users[name].activatedStatus) {
          alert(`user '${String(name)}' is already inactivated !`)
          return
        }
        url = wiki.url(`?api/emailactivation/${name}/inactivate`)
        break
      default:
        alert(`action '${String(action)}' is not existing !`)
        return
    }
    fetch(url, { method: 'POST' })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`not possible to ${action} user ${name}`)
        }
        return this.getUsers()
      })
      .then((users) => {
        if (!(name in users)) {
          throw new Error(
            `not possible to ${action} user ${name} because user is not anymore a user !`,
          )
        }
        this.users[name] = users[name]
        this.renderCellFor(name)
      })
      .catch((error) => {
        alert(`error '${String(error)}'`)
      })
  },
  init() {
    this.tables = Array.from(
      document.querySelectorAll('#users-table-action table'),
    ).filter((table) => table.tBodies.length > 0)
    if (this.tables.length === 0) return
    this.getUsers()
      .then((users) => {
        this.users = users
        this.appendActivationColumn()
      })
      .catch((error) => console.warn(error))
  },
}

ywInitEach('.users-table, #users-table', () => {
  if (typeof usersTableService !== 'undefined' && usersTableService) {
    usersTableServiceAddOn.init()
  }
})
