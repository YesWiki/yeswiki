const geolocationHelper = (function() {
  // private objects
  const eventDispatcher = {
    // data
    eventsListeners: {},
    loadingCache: {},
    // methods
    addEvent(eventName, listener, once = false) {
      if (typeof eventName == 'string') {
        if (!(eventName in this.eventsListeners)) {
          this.eventsListeners[eventName] = []
        }
        this.eventsListeners[eventName] = [
          ...this.eventsListeners[eventName],
          ...[{ listener, once, triggered: false }]
        ]
      }
    },
    addEventOnce(eventName, listener) {
      this.addEvent(eventName, listener, true)
    },
    cleanEvent(eventName) {
      this.eventsListeners[eventName] = this.eventsListeners[eventName].filter((eventData) => !eventData.once || !eventData.triggered)
    },
    dispatchEvent(eventName, param = undefined) {
      if (typeof eventName == 'string') {
        if (eventName in this.eventsListeners) {
          this.eventsListeners[eventName].forEach((eventData, idx) => {
            if (!eventData.once || !eventData.triggered) {
              this.eventsListeners[eventName][idx].triggered = true
              if (param != undefined) {
                if (Array.isArray(param)) {
                  eventData.listener(...param)
                } else {
                  eventData.listener(param)
                }
              } else {
                eventData.listener()
              }
            }
          })
          this.cleanEvent(eventName)
        }
      }
    },
    async runOnceOrWait(eventId, eventPrefix, asynFunc) {
      if (!(eventPrefix in this.loadingCache) || !Array.isArray(this.loadingCache[eventPrefix])) {
        this.loadingCache[eventPrefix] = []
      }
      const errorEventName = `${eventPrefix}.${eventId}.error`
      const readyEventName = `${eventPrefix}.${eventId}.ready`
      const p = new Promise((resolve, reject) => {
        this.addEventOnce(readyEventName, (...args) => resolve(...args))
        this.addEventOnce(errorEventName, (e) => reject(e))
        if (!this.loadingCache[eventPrefix].includes(eventId)) {
          this.loadingCache[eventPrefix] = [...this.loadingCache[eventPrefix], eventId]
          const resettingLoading = () => {
            this.loadingCache[eventPrefix] = this.loadingCache[eventPrefix].filter((idToCheck) => idToCheck != eventId)
          }
          this.addEventOnce(errorEventName, resettingLoading)
          this.addEventOnce(readyEventName, resettingLoading)
          this.addEventOnce(readyEventName, () => {
            this.setEventTriggered(errorEventName)
          })
          asynFunc()
            .then((...args) => {
              this.dispatchEvent(readyEventName, args)
              return Promise.resolve(...args)
            })
            .catch((e) => { this.dispatchEvent(errorEventName, e) })
        }
      })
      return await p.then((...args) => Promise.resolve(...args))
    },
    setEventTriggered(eventName) {
      if (typeof eventName == 'string' && (eventName in this.eventsListeners)) {
        this.eventsListeners[eventName] = this.eventsListeners[eventName].map((evenData) => {
          evenData.triggered = true
          return evenData
        })
      }
    }
  }
  const geolocationHelperInternal = {
    data() {
      return {
        cache: {
          fromAddress: {},
          fromPostalCode: {},
          fromTown: {}
        },
        countries: // World's countries with country code, name and bounding box
                [
                  { id: 'AF', name: 'Afghanistan', box: [60.53, 29.32, 75.16, 38.49] },
                  { id: 'AO', name: 'Angola', box: [11.64, -17.93, 24.08, -4.44] },
                  { id: 'AL', name: 'Albania', box: [19.3, 39.62, 21.02, 42.69] },
                  { id: 'AE', name: 'United Arab Emirates', box: [51.58, 22.5, 56.4, 26.06] },
                  { id: 'AR', name: 'Argentina', box: [-73.42, -55.25, -53.63, -21.83] },
                  { id: 'AM', name: 'Armenia', box: [43.58, 38.74, 46.51, 41.25] },
                  { id: 'AQ', name: 'Antarctica', box: [-180.0, -90.0, 180.0, -63.27] },
                  { id: 'TF', name: 'French Southern Territories', box: [68.72, -49.78, 70.56, -48.63] },
                  { id: 'AU', name: 'Australia', box: [113.34, -43.63, 153.57, -10.67] },
                  { id: 'AT', name: 'Austria', box: [9.48, 46.43, 16.98, 49.04] },
                  { id: 'AZ', name: 'Azerbaijan', box: [44.79, 38.27, 50.39, 41.86] },
                  { id: 'BI', name: 'Burundi', box: [29.02, -4.5, 30.75, -2.35] },
                  { id: 'BE', name: 'Belgium', box: [2.51, 49.53, 6.16, 51.48] },
                  { id: 'BJ', name: 'Benin', box: [0.77, 6.14, 3.8, 12.24] },
                  { id: 'BF', name: 'Burkina Faso', box: [-5.47, 9.61, 2.18, 15.12] },
                  { id: 'BD', name: 'Bangladesh', box: [88.08, 20.67, 92.67, 26.45] },
                  { id: 'BG', name: 'Bulgaria', box: [22.38, 41.23, 28.56, 44.23] },
                  { id: 'BS', name: 'Bahamas', box: [-78.98, 23.71, -77.0, 27.04] },
                  { id: 'BA', name: 'Bosnia and Herzegovina', box: [15.75, 42.65, 19.6, 45.23] },
                  { id: 'BY', name: 'Belarus', box: [23.2, 51.32, 32.69, 56.17] },
                  { id: 'BZ', name: 'Belize', box: [-89.23, 15.89, -88.11, 18.5] },
                  { id: 'BO', name: 'Bolivia', box: [-69.59, -22.87, -57.5, -9.76] },
                  { id: 'BR', name: 'Brazil', box: [-73.99, -33.77, -34.73, 5.24] },
                  { id: 'BN', name: 'Brunei', box: [114.2, 4.01, 115.45, 5.45] },
                  { id: 'BT', name: 'Bhutan', box: [88.81, 26.72, 92.1, 28.3] },
                  { id: 'BW', name: 'Botswana', box: [19.9, -26.83, 29.43, -17.66] },
                  { id: 'CF', name: 'Central African Republic', box: [14.46, 2.27, 27.37, 11.14] },
                  { id: 'CA', name: 'Canada', box: [-141.0, 41.68, -52.65, 73.23] },
                  { id: 'CH', name: 'Switzerland', box: [6.02, 45.78, 10.44, 47.83] },
                  { id: 'CL', name: 'Chile', box: [-75.64, -55.61, -66.96, -17.58] },
                  { id: 'CN', name: 'China', box: [73.68, 18.2, 135.03, 53.46] },
                  { id: 'CI', name: 'Ivory Coast', box: [-8.6, 4.34, -2.56, 10.52] },
                  { id: 'CM', name: 'Cameroon', box: [8.49, 1.73, 16.01, 12.86] },
                  { id: 'CD', name: 'Congo (Kinshasa)', box: [12.18, -13.26, 31.17, 5.26] },
                  { id: 'CG', name: 'Congo (Brazzaville)', box: [11.09, -5.04, 18.45, 3.73] },
                  { id: 'CO', name: 'Colombia', box: [-78.99, -4.3, -66.88, 12.44] },
                  { id: 'CR', name: 'Costa Rica', box: [-85.94, 8.23, -82.55, 11.22] },
                  { id: 'CU', name: 'Cuba', box: [-84.97, 19.86, -74.18, 23.19] },
                  { id: 'CY', name: 'Cyprus', box: [32.26, 34.57, 34.0, 35.17] },
                  { id: 'CZ', name: 'Czech Republic', box: [12.24, 48.56, 18.85, 51.12] },
                  { id: 'DE', name: 'Germany', box: [5.99, 47.3, 15.02, 54.98] },
                  { id: 'DJ', name: 'Djibouti', box: [41.66, 10.93, 43.32, 12.7] },
                  { id: 'DK', name: 'Denmark', box: [8.09, 54.8, 12.69, 57.73] },
                  { id: 'DO', name: 'Dominican Republic', box: [-71.95, 17.6, -68.32, 19.88] },
                  { id: 'DZ', name: 'Algeria', box: [-8.68, 19.06, 12.0, 37.12] },
                  { id: 'EC', name: 'Ecuador', box: [-80.97, -4.96, -75.23, 1.38] },
                  { id: 'EG', name: 'Egypt', box: [24.7, 22.0, 36.87, 31.59] },
                  { id: 'ER', name: 'Eritrea', box: [36.32, 12.46, 43.08, 18.0] },
                  { id: 'ES', name: 'Spain', box: [-9.39, 35.95, 3.04, 43.75] },
                  { id: 'EE', name: 'Estonia', box: [23.34, 57.47, 28.13, 59.61] },
                  { id: 'ET', name: 'Ethiopia', box: [32.95, 3.42, 47.79, 14.96] },
                  { id: 'FI', name: 'Finland', box: [20.65, 59.85, 31.52, 70.16] },
                  { id: 'FJ', name: 'Fiji', box: [-180.0, -18.29, 180.0, -16.02] },
                  { id: 'FK', name: 'Falkland Islands', box: [-61.2, -52.3, -57.75, -51.1] },
                  { id: 'FR', name: 'France', box: [-5.0, 42.5, 9.56, 51.15] },
                  { id: 'GA', name: 'Gabon', box: [8.8, -3.98, 14.43, 2.33] },
                  { id: 'GB', name: 'United Kingdom', box: [-7.57, 49.96, 1.68, 58.64] },
                  { id: 'GE', name: 'Georgia', box: [39.96, 41.06, 46.64, 43.55] },
                  { id: 'GH', name: 'Ghana', box: [-3.24, 4.71, 1.06, 11.1] },
                  { id: 'GN', name: 'Guinea', box: [-15.13, 7.31, -7.83, 12.59] },
                  { id: 'GM', name: 'Gambia', box: [-16.84, 13.13, -13.84, 13.88] },
                  { id: 'GW', name: 'Guinea Bissau', box: [-16.68, 11.04, -13.7, 12.63] },
                  { id: 'GQ', name: 'Equatorial Guinea', box: [9.31, 1.01, 11.29, 2.28] },
                  { id: 'GR', name: 'Greece', box: [20.15, 34.92, 26.6, 41.83] },
                  { id: 'GL', name: 'Greenland', box: [-73.3, 60.04, -12.21, 83.65] },
                  { id: 'GT', name: 'Guatemala', box: [-92.23, 13.74, -88.23, 17.82] },
                  { id: 'GY', name: 'Guyana', box: [-61.41, 1.27, -56.54, 8.37] },
                  { id: 'HN', name: 'Honduras', box: [-89.35, 12.98, -83.15, 16.01] },
                  { id: 'HR', name: 'Croatia', box: [13.66, 42.48, 19.39, 46.5] },
                  { id: 'HT', name: 'Haiti', box: [-74.46, 18.03, -71.62, 19.92] },
                  { id: 'HU', name: 'Hungary', box: [16.2, 45.76, 22.71, 48.62] },
                  { id: 'ID', name: 'Indonesia', box: [95.29, -10.36, 141.03, 5.48] },
                  { id: 'IN', name: 'India', box: [68.18, 7.97, 97.4, 35.49] },
                  { id: 'IE', name: 'Ireland', box: [-9.98, 51.67, -6.03, 55.13] },
                  { id: 'IR', name: 'Iran', box: [44.11, 25.08, 63.32, 39.71] },
                  { id: 'IQ', name: 'Iraq', box: [38.79, 29.1, 48.57, 37.39] },
                  { id: 'IS', name: 'Iceland', box: [-24.33, 63.5, -13.61, 66.53] },
                  { id: 'IL', name: 'Israel', box: [34.27, 29.5, 35.84, 33.28] },
                  { id: 'IT', name: 'Italy', box: [6.75, 36.62, 18.48, 47.12] },
                  { id: 'JM', name: 'Jamaica', box: [-78.34, 17.7, -76.2, 18.52] },
                  { id: 'JO', name: 'Jordan', box: [34.92, 29.2, 39.2, 33.38] },
                  { id: 'JP', name: 'Japan', box: [129.41, 31.03, 145.54, 45.55] },
                  { id: 'KZ', name: 'Kazakhstan', box: [46.47, 40.66, 87.36, 55.39] },
                  { id: 'KE', name: 'Kenya', box: [33.89, -4.68, 41.86, 5.51] },
                  { id: 'KG', name: 'Kyrgyzstan', box: [69.46, 39.28, 80.26, 43.3] },
                  { id: 'KH', name: 'Cambodia', box: [102.35, 10.49, 107.61, 14.57] },
                  { id: 'KR', name: 'South Korea', box: [126.12, 34.39, 129.47, 38.61] },
                  { id: 'KW', name: 'Kuwait', box: [46.57, 28.53, 48.42, 30.06] },
                  { id: 'LA', name: 'Laos', box: [100.12, 13.88, 107.56, 22.46] },
                  { id: 'LB', name: 'Lebanon', box: [35.13, 33.09, 36.61, 34.64] },
                  { id: 'LR', name: 'Liberia', box: [-11.44, 4.36, -7.54, 8.54] },
                  { id: 'LY', name: 'Libya', box: [9.32, 19.58, 25.16, 33.14] },
                  { id: 'LK', name: 'Sri Lanka', box: [79.7, 5.97, 81.79, 9.82] },
                  { id: 'LS', name: 'Lesotho', box: [27.0, -30.65, 29.33, -28.65] },
                  { id: 'LT', name: 'Lithuania', box: [21.06, 53.91, 26.59, 56.37] },
                  { id: 'LU', name: 'Luxembourg', box: [5.67, 49.44, 6.24, 50.13] },
                  { id: 'LV', name: 'Latvia', box: [21.06, 55.62, 28.18, 57.97] },
                  { id: 'MA', name: 'Morocco', box: [-17.02, 21.42, -1.12, 35.76] },
                  { id: 'MD', name: 'Moldova', box: [26.62, 45.49, 30.02, 48.47] },
                  { id: 'MG', name: 'Madagascar', box: [43.25, -25.6, 50.48, -12.04] },
                  { id: 'MX', name: 'Mexico', box: [-117.13, 14.54, -86.81, 32.72] },
                  { id: 'MK', name: 'Macedonia', box: [20.46, 40.84, 22.95, 42.32] },
                  { id: 'ML', name: 'Mali', box: [-12.17, 10.1, 4.27, 24.97] },
                  { id: 'MM', name: 'Myanmar', box: [92.3, 9.93, 101.18, 28.34] },
                  { id: 'ME', name: 'Montenegro', box: [18.45, 41.88, 20.34, 43.52] },
                  { id: 'MN', name: 'Mongolia', box: [87.75, 41.6, 119.77, 52.05] },
                  { id: 'MZ', name: 'Mozambique', box: [30.18, -26.74, 40.78, -10.32] },
                  { id: 'MR', name: 'Mauritania', box: [-17.06, 14.62, -4.92, 27.4] },
                  { id: 'MW', name: 'Malawi', box: [32.69, -16.8, 35.77, -9.23] },
                  { id: 'MY', name: 'Malaysia', box: [100.09, 0.77, 119.18, 6.93] },
                  { id: 'NA', name: 'Namibia', box: [11.73, -29.05, 25.08, -16.94] },
                  { id: 'NC', name: 'New Caledonia', box: [164.03, -22.4, 167.12, -20.11] },
                  { id: 'NE', name: 'Niger', box: [0.3, 11.66, 15.9, 23.47] },
                  { id: 'NG', name: 'Nigeria', box: [2.69, 4.24, 14.58, 13.87] },
                  { id: 'NI', name: 'Nicaragua', box: [-87.67, 10.73, -83.15, 15.02] },
                  { id: 'NL', name: 'Netherlands', box: [3.31, 50.8, 7.09, 53.51] },
                  { id: 'NO', name: 'Norway', box: [4.99, 58.08, 31.29, 70.92] },
                  { id: 'NP', name: 'Nepal', box: [80.09, 26.4, 88.17, 30.42] },
                  { id: 'NZ', name: 'New Zealand', box: [166.51, -46.64, 178.52, -34.45] },
                  { id: 'OM', name: 'Oman', box: [52.0, 16.65, 59.81, 26.4] },
                  { id: 'PK', name: 'Pakistan', box: [60.87, 23.69, 77.84, 37.13] },
                  { id: 'PA', name: 'Panama', box: [-82.97, 7.22, -77.24, 9.61] },
                  { id: 'PE', name: 'Peru', box: [-81.41, -18.35, -68.67, -0.06] },
                  { id: 'PH', name: 'Philippines', box: [117.17, 5.58, 126.54, 18.51] },
                  { id: 'PG', name: 'Papua New Guinea', box: [141.0, -10.65, 156.02, -2.5] },
                  { id: 'PL', name: 'Poland', box: [14.07, 49.03, 24.03, 54.85] },
                  { id: 'PR', name: 'Puerto Rico', box: [-67.24, 17.95, -65.59, 18.52] },
                  { id: 'KP', name: 'North Korea', box: [124.27, 37.67, 130.78, 42.99] },
                  { id: 'PT', name: 'Portugal', box: [-9.53, 36.84, -6.39, 42.28] },
                  { id: 'PY', name: 'Paraguay', box: [-62.69, -27.55, -54.29, -19.34] },
                  { id: 'QA', name: 'Qatar', box: [50.74, 24.56, 51.61, 26.11] },
                  { id: 'RO', name: 'Romania', box: [20.22, 43.69, 29.63, 48.22] },
                  { id: 'RU', name: 'Russia', box: [-180.0, 41.15, 180.0, 81.25] },
                  { id: 'RW', name: 'Rwanda', box: [29.02, -2.92, 30.82, -1.13] },
                  { id: 'SA', name: 'Saudi Arabia', box: [34.63, 16.35, 55.67, 32.16] },
                  { id: 'SD', name: 'Sudan', box: [21.94, 8.62, 38.41, 22.0] },
                  { id: 'SS', name: 'South Sudan', box: [23.89, 3.51, 35.3, 12.25] },
                  { id: 'SN', name: 'Senegal', box: [-17.63, 12.33, -11.47, 16.6] },
                  { id: 'SB', name: 'Solomon Islands', box: [156.49, -10.83, 162.4, -6.6] },
                  { id: 'SL', name: 'Sierra Leone', box: [-13.25, 6.79, -10.23, 10.05] },
                  { id: 'SV', name: 'El Salvador', box: [-90.1, 13.15, -87.72, 14.42] },
                  { id: 'SO', name: 'Somalia', box: [40.98, -1.68, 51.13, 12.02] },
                  { id: 'RS', name: 'Serbia', box: [18.83, 42.25, 22.99, 46.17] },
                  { id: 'SR', name: 'Suriname', box: [-58.04, 1.82, -53.96, 6.03] },
                  { id: 'SK', name: 'Slovakia', box: [16.88, 47.76, 22.56, 49.57] },
                  { id: 'SI', name: 'Slovenia', box: [13.7, 45.45, 16.56, 46.85] },
                  { id: 'SE', name: 'Sweden', box: [11.03, 55.36, 23.9, 69.11] },
                  { id: 'SZ', name: 'Swaziland', box: [30.68, -27.29, 32.07, -25.66] },
                  { id: 'SY', name: 'Syria', box: [35.7, 32.31, 42.35, 37.23] },
                  { id: 'TD', name: 'Chad', box: [13.54, 7.42, 23.89, 23.41] },
                  { id: 'TG', name: 'Togo', box: [-0.05, 5.93, 1.87, 11.02] },
                  { id: 'TH', name: 'Thailand', box: [97.38, 5.69, 105.59, 20.42] },
                  { id: 'TJ', name: 'Tajikistan', box: [67.44, 36.74, 74.98, 40.96] },
                  { id: 'TM', name: 'Turkmenistan', box: [52.5, 35.27, 66.55, 42.75] },
                  { id: 'TL', name: 'East Timor', box: [124.97, -9.39, 127.34, -8.27] },
                  { id: 'TT', name: 'Trinidad and Tobago', box: [-61.95, 10.0, -60.9, 10.89] },
                  { id: 'TN', name: 'Tunisia', box: [7.52, 30.31, 11.49, 37.35] },
                  { id: 'TR', name: 'Turkey', box: [26.04, 35.82, 44.79, 42.14] },
                  { id: 'TW', name: 'Taiwan', box: [120.11, 21.97, 121.95, 25.3] },
                  { id: 'TZ', name: 'Tanzania', box: [29.34, -11.72, 40.32, -0.95] },
                  { id: 'UG', name: 'Uganda', box: [29.58, -1.44, 35.04, 4.25] },
                  { id: 'UA', name: 'Ukraine', box: [22.09, 44.36, 40.08, 52.34] },
                  { id: 'UY', name: 'Uruguay', box: [-58.43, -34.95, -53.21, -30.11] },
                  { id: 'US', name: 'United States', box: [-125.0, 25.0, -66.96, 49.5] },
                  { id: 'UZ', name: 'Uzbekistan', box: [55.93, 37.14, 73.06, 45.59] },
                  { id: 'VE', name: 'Venezuela', box: [-73.3, 0.72, -59.76, 12.16] },
                  { id: 'VN', name: 'Vietnam', box: [102.17, 8.6, 109.34, 23.35] },
                  { id: 'VU', name: 'Vanuatu', box: [166.63, -16.6, 167.84, -14.63] },
                  { id: 'PS', name: 'West Bank', box: [34.93, 31.35, 35.55, 32.53] },
                  { id: 'YE', name: 'Yemen', box: [42.6, 12.59, 53.11, 19.0] },
                  { id: 'ZA', name: 'South Africa', box: [16.34, -34.82, 32.83, -22.09] },
                  { id: 'ZM', name: 'Zambia', box: [21.89, -17.96, 33.49, -8.24] },
                  { id: 'ZW', name: 'Zimbabwe', box: [25.26, -22.27, 32.85, -15.51] }
                ],
        eventDispatcher,
        isInit: false,
        isDebugMode: false,
        loadingUrl: {}
      }
    },
    methods: {
      // calculate geodesic distance
      calculateGeodesicDistance({ point1 = { latitude: '', longitude: '' }, point2 = { latitude: '', longitude: '' } }) {
        // sanitize
        ['point1', 'point2'].forEach((name) => {
          const val = eval(name)
          if (typeof val !== 'object' || val === null) {
            throw new Error(`${name} should be an object`)
          }
          ['latitude', 'longitude'].forEach((key) => {
            if (!(key in val)) {
              throw new Error(`${name} should contain key '${key}'`)
            }
            if (Number.isNaN(val[key])) {
              throw new Error(`${name}[${key}] should be a number`)
            }
          })
        })
        // Radius in Km
        const radiusEarthKm = 6371.07103

        // Convert degrees to radians
        const radiusLatFrom = Number(point1.latitude) * (Math.PI / 180)
        const radiusLatTo = Number(point2.latitude) * (Math.PI / 180)

        // Radian difference (latitudes)
        const latDiff = radiusLatTo - radiusLatFrom

        // Radian difference (longitudes)
        const lngDiff = (Number(point1.longitude) - Number(point1.longitude)) * (pi() / 180)

        return 2 * radiusEarthKm * Math.sin(
          Math.sqrt(
            Math.sin(latDiff / 2) * Math.sin(latDiff / 2)
                            + Math.cos(radiusLatFrom) * Math.cos(radiusLatTo) * Math.sin(lngDiff / 2) * Math.sin(lngDiff / 2)
          )
        )
      },
      convertDataFromGouvApi(data, countryCode, country) {
        return data
          .filter((entry) => typeof entry === 'object'
                            && 'codesPostaux' in entry
                            && 'departement' in entry
                            && 'region' in entry
                            && 'nom' in entry)
          .map((entry) => this.toGeolocationData({
            postalCodes: entry.codesPostaux,
            town: entry.nom,
            county: entry.departement.nom,
            countyCode: entry.departement.code,
            state: entry.region.nom,
            stateCode: entry.region.code,
            countryCode,
            country
          }))
      },
      extractGeolocData(data) {
        if (!Array.isArray(data)) {
          throw new Error('waited array when getting address')
        }
        return data
          .filter((entry) => typeof entry === 'object'
                            && 'lat' in entry
                            && 'lon' in entry
                            && 'display_name' in entry
                            && 'type' in entry)
          .map((entry) => {
            const latitude = entry.lat
            const longitude = entry.lon
            const infos = entry.display_name.split(',').map((e) => e.trim())
            const postalCodes = (infos.length > 2 && typeof Number(infos[infos.length - 2]) === 'number')
              ? { postalCodes: [infos[infos.length - 2]] } : {}
            switch (entry.type) {
              case 'administrative':
                return this.toGeolocationData({
                  ...{
                    town: infos[0],
                    country: infos[infos.length - 1],
                    latitude,
                    longitude
                  },
                  ...postalCodes
                })
              case 'residential':
                return this.toGeolocationData({
                  ...{
                    street: infos[0],
                    state: infos[4] || '',
                    county: infos[3] || '',
                    town: infos[2] || '',
                    country: infos[infos.length - 1],
                    latitude,
                    longitude
                  },
                  ...postalCodes
                })
              case 'hamlet':
              case 'village':
                return this.toGeolocationData({
                  ...{
                    street: infos[0],
                    state: infos[4] || '',
                    county: infos[3] || '',
                    town: infos[1] || '',
                    country: infos[infos.length - 1],
                    latitude,
                    longitude
                  },
                  ...postalCodes
                })
              case 'postal_code':
                return this.toGeolocationData({
                  ...{
                    state: infos[0],
                    country: infos[infos.length - 1],
                    latitude,
                    longitude
                  },
                  ...postalCodes
                })
              case 'postcode':
                return this.toGeolocationData({
                  ...{
                    street: infos[0],
                    town: infos[1] || '',
                    county: infos[2] || '',
                    state: infos[3] || '',
                    country: infos[infos.length - 1],
                    latitude,
                    longitude
                  },
                  ...postalCodes
                })
              case 'unclassified':
                if ('class' in entry && entry.class === 'highway') {
                  return this.toGeolocationData({
                    ...{
                      street: infos[0],
                      street1: infos[1],
                      state: infos[5] || '',
                      county: infos[4] || '',
                      town: infos[2] || '',
                      country: infos[infos.length - 1],
                      latitude,
                      longitude
                    },
                    ...postalCodes,
                    ...(
                      infos.length === 11
                        ? {
                          street2: infos[2],
                          town: infos[4] || '',
                          county: infos[6] || '',
                          state: infos[7] || ''
                        } : {}
                    )
                  })
                }
              default:
                return this.toGeolocationData({
                  country: infos[infos.length - 1],
                  latitude,
                  longitude
                })
            }
          })
      },
      async geolocate(address) {
        await this.waitInit()
        let cacheKey = ''
        let url = ''
        if (typeof address === 'object' && address !== null) {
          cacheKey = JSON.stringify(address)
          const params = []
          const associations = {
            street: 'street',
            postalCode: 'postalcode',
            town: 'city',
            county: 'county',
            state: 'state'
          }
          Object.keys(associations).forEach((dataKey) => {
            const paramKey = associations[dataKey]
            if (dataKey in address && typeof address[dataKey] === 'string' && address[dataKey].length > 0) {
              params[paramKey] = address[dataKey]
            }
          })
          if (Object.keys(params).length > 0) {
            if ('street' in params && (!('postalcode' in params) || !('city' in params))) {
              url = `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(Object.keys(params).map((k) => params[k]).join(' '))}&format=json`
            } else {
              url = `https://nominatim.openstreetmap.org/search?${Object.keys(params).map((k) => `${k}=${encodeURIComponent(params[k])}`).join('&')}&format=json`
            }
          } else {
            // throw new Error('address shoud be an object with at least one key from \'street\',\'postalCode\',\'town\',\'county\',\'state\'')
            return ''
          }
        }
        if (url.length == 0) {
          if (typeof address === 'string') {
            const sanitizedAddress = this.sanitizeAddress(address)
            if (sanitizedAddress.length === 0) {
              throw new Error('empty sanitized address')
            }
            cacheKey = sanitizedAddress
            url = `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(sanitizedAddress)}&format=json`
          } else {
            throw new Error('address shoud be a string')
          }
        }
        if (cacheKey in this.cache.fromAddress) {
          return this.cache.fromAddress[cacheKey]
        }
        this.log(`geolocate '${cacheKey}'`)
        return await this.eventDispatcher.runOnceOrWait(cacheKey, 'fromAddress', async() => this.getJsonWithTimeLimitThenWait(
          url,
          'nominatim.openstreetmap.org',
          1000
        )
          .then((data) => {
            this.cache.fromAddress[cacheKey] = this.extractGeolocData(data)
            return this.cache.fromAddress[cacheKey]
          })
          .catch((error) => {
            this.log(error, 'error')
            return [this.toGeolocationData({})]
          }))
      },
      getCountry(code) {
        const foundData = this.countries.find((countryData) => countryData.id.toLowerCase() === code.toLowerCase())
        if (foundData) {
          return foundData.name
        }
        throw new Error(`Country '${code}' not found`)
      },
      getCountryBox(country) {
        return this.getCountryData(country).box
      },
      getCountryCode(country) {
        return this.getCountryData(country).id
      },
      // Return the country giving its name
      getCountryData(country) {
        const normalizedCountry = this.normalizeNFDDiacritic(country)

        const foundData = this.countries.find((countryData) => this.normalizeNFDDiacritic(countryData.name) === normalizedCountry)
        if (foundData) {
          return foundData
        }
        throw new Error(`Country '${country}' not found`)
      },
      async getGelocationDataFromGouv(country, id, fromCache, intoUrl) {
        await this.waitInit()
        if (typeof country !== 'string') {
          throw new Error('country shoud be a string')
        }
        if (typeof id !== 'string') {
          throw new Error('id shoud be a string')
        }
        let countryCode = ''
        try {
          countryCode = this.getCountryCode(country)
        } catch (error) {
          // unknown country
          return []
        }
        if (country.toLowerCase() === 'france') {
          if (id in this.cache[fromCache]) {
            return this.cache[fromCache][id]
          }
          return await this.eventDispatcher.runOnceOrWait(`${country}-${id}`, fromCache, async() => this.getJsonWithTimeLimitThenWait(
            `https://geo.api.gouv.fr/communes?${intoUrl}&fields=codesPostaux,departement,region`,
            'geo.api.gouv.fr',
            100
          )
            .then((data) => {
              if (!Array.isArray(data)) {
                throw new Error(`waited array when getting ${id}`)
              }
              this.cache[fromCache][id] = this.convertDataFromGouvApi(data, countryCode, country)
              return this.cache[fromCache][id]
            })
            .catch((error) => {
              this.log(error, 'error')
              return [this.toGeolocationData({ countryCode, country })]
            }))
        }
        return [this.toGeolocationData({ countryCode, country })]
      },
      async getGelocationDataFromPostalCode(country, postalCode) {
        if (!['string', 'number'].includes(typeof postalCode)) {
          throw new Error('postalCode shoud be a string or number')
        }
        return this.getGelocationDataFromGouv(country, postalCode, 'fromPostalCode', `codePostal=${postalCode}`)
      },
      async getGelocationDataFromTown(country, town) {
        if (typeof town !== 'string') {
          throw new Error('town shoud be a string')
        }
        return this.getGelocationDataFromGouv(country, town, 'fromTown', `nom=${town}`)
      },
      async getJsonWithTimeLimitThenWait(url, id, minimumTimeToWait = 100, timeLimit = 5000) {
        if (!(id in this.loadingUrl) || (typeof this.loadingUrl[id] !== 'object')) {
          this.loadingUrl[id] = {
            running: false,
            next: []
          }
        }
        const canStartDirectly = !this.loadingUrl[id].running

        const processNext = () => {
          if (this.loadingUrl[id].next.length == 0) {
            this.loadingUrl[id].running = false
          } else {
            this.loadingUrl[id].running = true
            const firstToresolve = this.loadingUrl[id].next[0]
            this.loadingUrl[id].next = (this.loadingUrl[id].next.length > 1)
              ? this.loadingUrl[id].next.slice(1)
              : []
            firstToresolve()
          }
        }

        const p = new Promise((resolve) => {
          this.loadingUrl[id].next = [
            ...this.loadingUrl[id].next,
            resolve
          ]
          if (canStartDirectly) {
            processNext()
          }
        })
        return await p.then(() => this.getJsonWithTimeLimit(url, timeLimit))
          .finally(() => {
            setTimeout(() => processNext(), minimumTimeToWait)
          })
      },
      async getJsonWithTimeLimit(url, timeLimit = 5000) {
        const abortController = new AbortController()
        const resetTimeoutId = setTimeout(() => abortController.abort(), timeLimit)
        return fetch(url, { signal: abortController.signal })
          .then((response) => {
            if (!response.ok) {
              throw new Error('response not ok when getting data from postal code')
            }
            return response.json()
          })
          .finally(() => {
            clearTimeout(resetTimeoutId)
          })
      },
      log(object, type = 'log') {
        if (!['log', 'error', 'warn'].includes(type)) {
          type = 'log'
        }
        if (this.isDebugMode) {
          console[type](object)
        }
      },
      init() {
        this.isDebugMode = wiki.isDebugEnabled
        this.isInit = true
        // do nothing
        this.eventDispatcher.dispatchEvent('init.init.ready')
      },
      normalizeNFDDiacritic(text) {
        return text.normalize('NFD').replace(/\p{Diacritic}/gu, '').replace(/[-]/g, ' ').toLowerCase()
      },
      sanitizeAddress(address) {
        return address.replace(/\\("|\'|\\)/g, ' ').trim()
      },
      toGeolocationData(data) {
        const sanitizedData = (typeof data === 'object') ? data : {}
        const exportData = {}
        const tab = [
          'street', 'street1', 'street2', 'town', 'county',
          'countyCode', 'state', 'stateCode', 'country', 'countryCode', 'latitude', 'longitude'
        ]
        tab.forEach((key) => {
          exportData[key] = (key in sanitizedData && ['string', 'number'].includes(typeof sanitizedData[key]))
            ? String(sanitizedData[key])
            : ''
        })
        exportData.postalCodes = ('postalCodes' in sanitizedData && Array.isArray(sanitizedData.postalCodes))
          ? sanitizedData.postalCodes.map((e) => (['string', 'number'].includes(typeof e) ? e : '')).filter((e) => String(e).length > 0)
          : []
        return exportData
      },
      async waitInit() {
        if (this.isInit) {
          return true
        }
        return await this.eventDispatcher.runOnceOrWait('init', 'init', async() => {})
      }
    },
    initData() {
      this.methods.parent = this
      // init data -- not needed with VueJs
      const data = this.data()
      for (const key in data) {
        this.methods[key] = data[key]
      }
    },
    mounted() {
      this.methods.init() // replace by this.init() in VueJs
    }
  }
  // initData
  geolocationHelperInternal.initData() // not needed with VueJs

  // When document is ready
  window.addEventListener('load', () => geolocationHelperInternal.mounted())

  // public methods
  return {
    // calculate geodesic distance
    calculateGeodesicDistance({ point1 = { latitude: '', longitude: '' }, point2 = { latitude: '', longitude: '' } }) {
      return geolocationHelperInternal.methods.calculateGeodesicDistance({ point1, point2 })
    },
    // Return the country name giving its country code
    getCountry(code) {
      return geolocationHelperInternal.methods.getCountry(code)
    },
    // Return the country's bounding box giving its name
    getCountryBox(country) {
      return geolocationHelperInternal.methods.getCountryBox(country)
    },
    // Return the country code giving its name
    getCountryCode(country) {
      return geolocationHelperInternal.methods.getCountryCode(country)
    },
    async getGelocationDataFromPostalCode(country, postalCode) {
      return geolocationHelperInternal.methods.getGelocationDataFromPostalCode(country, postalCode)
    },
    async getGelocationDataFromTown(country, town) {
      return geolocationHelperInternal.methods.getGelocationDataFromTown(country, town)
    },
    async geolocate(address) {
      return geolocationHelperInternal.methods.geolocate(address)
    },
    async geolocateRetryWithoutNumberAtBeginningIfNeeded(address) {
      return await geolocationHelperInternal.methods.geolocate(address).then((results) => {
        if (results.length == 0) {
          const matches = geolocationHelperInternal.methods.sanitizeAddress(address).match(/^\d+(.*)/i)
          if (matches) {
            return geolocationHelperInternal.methods.geolocate(matches[1])
          }
        }
        return results
      })
    }
  }
}())
