import {
  readConf,
  writeconf,
  semanticConf,
  defaultMapping
} from './commons/attributes.js'
import renderHelper from './commons/render-helper.js'

const findVideoOptions = (base) => $(base).find(
  'select[name=ratio],'
    + 'select[name=class],'
    + 'input[type=text][name=maxwidth],'
    + 'input[type=text][name=maxheight]'
)
const findVideoOptionsNotInitialized = (base) => $(base).find(
  'select[name=ratio]:not(.initialized),'
    + 'input[type=text][name=maxwidth]:not(.initialized),'
    + 'input[type=text][name=maxheight]:not(.initialized)'
)

const setFormGroupVisible = function() {
  $(this).closest('.form-group').show()
}
const setFormGroupHidden = function() {
  $(this).closest('.form-group').hide()
}

const prepareData = function() {
  const baseLocal = $(this).closest('.url-field.form-field')
  const textOptions = $(baseLocal).find('.form-group.options-wrap input[type=text][name=options]')?.val() ?? ''
  const options = textOptions?.split('|') ?? []
  const ratio = options?.[0] ?? ''
  const maxwidth = options?.[1] ?? ''
  const maxheight = options?.[2] ?? ''

  $(this).val(
    $(this).prop('type') === 'text'
      ? (
        $(this).prop('name') === 'maxwidth'
          ? maxwidth
          : maxheight
      )
      : ratio
  )
  $(this).addClass('initialized')
}

const updateVideoOptionsVisibility = (event) => {
  const { target } = event
  const baseLocal = $(target).closest('.url-field.form-field')
  const selectDisplayVideo = $(baseLocal).find('.form-group.displayvideo-wrap select[name=displayvideo]')

  if (selectDisplayVideo.val() === 'displayvideo') {
    $(findVideoOptions(baseLocal)).each(setFormGroupVisible)
  } else {
    $(findVideoOptions(baseLocal)).each(setFormGroupHidden)
  }
}

const updateVideoOptions = (event) => {
  const { target } = event
  const baseLocal = $(target).closest('.url-field.form-field')
  const selectDisplayVideo = $(baseLocal).find('.form-group.displayvideo-wrap select[name=displayvideo]')?.val() ?? ''
  const selectRatio = $(baseLocal).find('.form-group.ratio-wrap select[name=ratio]')?.val() ?? ''
  const textMaxwidth = $(baseLocal).find('.form-group.maxwidth-wrap input[type=text][name=maxwidth]')?.val() ?? ''
  const textMaxheight = $(baseLocal).find('.form-group.maxheight-wrap input[type=text][name=maxheight]')?.val() ?? ''
  const textOptions = $(baseLocal).find('.form-group.options-wrap input[type=text][name=options]')

  textOptions.val(
    (
      selectDisplayVideo !== 'displayvideo'
      && selectRatio.length === 0
      && textMaxwidth.length === 0
      && textMaxheight.length === 0
    )
      ? ''
      : `${selectRatio}|${textMaxwidth}|${textMaxheight}`
  )
}

const initOptions = () => {
  const base = $('.url-field')
  const selectDisplayVideo = base.find('select[name=displayvideo]:not(.initialized)')
  const videooptions = $(findVideoOptionsNotInitialized(base))

  base.find('input[type=text][name=options]:not(.initialized),select[name=class]:not(.initialized)')
    .addClass('initialized')
    .each(setFormGroupHidden)

  selectDisplayVideo.on('change', updateVideoOptionsVisibility)
  selectDisplayVideo.on('blur', updateVideoOptionsVisibility)
  selectDisplayVideo.each(function() {
    $(this).addClass('initialized')
  })
  selectDisplayVideo.on('change', updateVideoOptions)
  videooptions.on('change', updateVideoOptions)
  videooptions.on('blur', updateVideoOptions)
  videooptions.on('focusout', updateVideoOptions)
  videooptions.each(prepareData)

  selectDisplayVideo.trigger('change')
  videooptions.trigger('change')
}

export default {
  field: {
    label: _t('BAZ_FORM_EDIT_URL_LABEL'),
    name: 'url',
    attrs: { type: 'url' },
    icon: '<i class="fas fa-link"></i>'
  },
  attributes: {
    displayvideo: {
      label: _t('BAZAR_URL_DISPLAY_VIDEO'),
      options: { ' ': _t('NO'), displayvideo: _t('YES') }
    },
    ratio: {
      label: _t('BAZAR_VIDEO_RATIO_LABEL'),
      options: { '': '16/9', '4par3': '4/3' }
    },
    maxwidth: {
      label: _t('BAZAR_VIDEO_MAXWIDTH_LABEL'),
      value: ''
    },
    maxheight: {
      label: _t('BAZAR_VIDEO_MAXHEIGHT_LABEL'),
      value: ''
    },
    options: {
      label: 'options',
      value: ''
    },
    class: {
      label: _t('BAZAR_VIDEO_POSITION_LABEL'),
      options: {
        '': 'standard',
        'pull-left': _t('BAZAR_VIDEO_POSITION_LEFT'),
        'pull-right': _t('BAZAR_VIDEO_POSITION_RIGHT')
      }
    },
    read: readConf,
    write: writeconf,
    semantic: semanticConf
  },
  attributesMapping: {
    ...defaultMapping,
    ...{
      3: 'displayvideo',
      6: 'options',
      7: 'class'
    }
  },
  advancedAttributes: ['read', 'write', 'semantic', 'hint', 'ratio', 'maxwidth', 'maxheight', 'options', 'class'],
  // disabledAttributes: [],
  renderInput(field) {
    return {
      field: field.displayvideo === 'displayvideo'
        ? '<input type="text" disabled value="https://framatube.org/w/pAQiVCgv2CsLg79KKXUoMw"/>'
        : `<input type="url" placeholder="${field.value || ''}"/>`,
      onRender() {
        initOptions()
        renderHelper.defineLabelHintForGroup(field, 'maxwidth', _t('BAZAR_VIDEO_MAX_HINT'))
        renderHelper.defineLabelHintForGroup(field, 'maxheight', _t('BAZAR_VIDEO_MAX_HINT'))
      }
    }
  }
}
