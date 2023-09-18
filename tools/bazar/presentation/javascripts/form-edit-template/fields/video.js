import {
  defaultMapping,
  readConf,
  writeconf,
  semanticConf
} from './commons/attributes.js'
import renderHelper from './commons/render-helper.js'

export default {
    field: {
      label: _t('BAZAR_VIDEO_LABEL'),
      name: "video",
      attrs: { type: "video" },
      icon: '<i class="fas fa-video"></i>',
    },
    attributes: {
      ratio:{
        label: _t('BAZAR_VIDEO_RATIO_LABEL'),
        options: {'':'16/9','4par3':'4/3'},
      },
      maxwidth:{
        label: _t('BAZAR_VIDEO_MAXWIDTH_LABEL'),
        value: ''
      },
      maxheight:{
        label: _t('BAZAR_VIDEO_MAXHEIGHT_LABEL'),
        value: ''
      },
      class:{
        label: _t('BAZAR_VIDEO_POSITION_LABEL'),
        options: {
          '': 'standard',
          'pull-left': _t('BAZAR_VIDEO_POSITION_LEFT'),
          'pull-right': _t('BAZAR_VIDEO_POSITION_RIGHT'),
        }
      },
      read: readConf,
      write: writeconf,
      semantic: semanticConf
    },
    attributesMapping: {
      ...defaultMapping,
      ...{
        3:'ratio',
        4:'maxwidth',
        6:'maxheight',
        7:'class'
      }
    },
    advancedAttributes: ['read', 'write', 'semantic', 'hint', 'value','ratio','maxwidth','maxheight','class'],
    renderInput(field) {
      return {
    
          field: '<input type="text" disabled value="https://framatube.org/w/pAQiVCgv2CsLg79KKXUoMw"/>', 

          onRender() {
            renderHelper.defineLabelHintForGroup(field, 'maxwidth', _t('BAZAR_VIDEO_MAX_HINT'))
            renderHelper.defineLabelHintForGroup(field, 'maxheight', _t('BAZAR_VIDEO_MAX_HINT'))
          }
      }
    }
}