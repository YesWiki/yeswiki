function getOrientation(file, callback) {
  var reader = new FileReader();
  reader.onload = function(e) {

    var view = new DataView(e.target.result);
    if (view.getUint16(0, false) != 0xFFD8) return callback(-2);
    var length = view.byteLength, offset = 2;
    while (offset < length) {
      var marker = view.getUint16(offset, false);
      offset += 2;
      if (marker == 0xFFE1) {
        if (view.getUint32(offset += 2, false) != 0x45786966) return callback(-1);
        var little = view.getUint16(offset += 6, false) == 0x4949;
        offset += view.getUint32(offset + 4, little);
        var tags = view.getUint16(offset, little);
        offset += 2;
        for (var i = 0; i < tags; i++)
          if (view.getUint16(offset + (i * 12), little) == 0x0112)
            return callback(view.getUint16(offset + (i * 12) + 8, little));
      }
      else if ((marker & 0xFF00) != 0xFF00) break;
      else offset += view.getUint16(offset, false);
    }
    return callback(-1);
  };
  reader.readAsArrayBuffer(file.slice(0, 64 * 1024));
}


function handleFileSelect(evt) {
  var target = evt.target || evt.srcElement;
  var id = target.id;
  var files = target.files; // FileList object

  // Loop through the FileList and render image files as thumbnails.
  for (var i = 0, f; f = files[i]; i++) {

    // Only process image files.
    if (!f.type.match('image.*')) {
      continue;
    }
    if (typeof imageMaxSize !== "undefined"){
      if (f.size>imageMaxSize) {
        alert(_t('IMAGEFIELD_TOO_LARGE_IMAGE',{imageMaxSize:imageMaxSize}));
        document.getElementById(id).type = '';
        document.getElementById(id).type = 'file';
        continue ;
      }
    }
    var reader = new FileReader();
    // Closure to capture the file information.
    reader.onload = (function(theFile) {
      return function(e) {
        getOrientation(theFile, function(orientation) {
          var css = '';
          if (orientation === 6) {
            css = 'transform:rotate(90deg);';
          } else if (orientation === 8) {
            css = 'transform:rotate(270deg);';
          } else if (orientation === 3) {
            css = 'transform:rotate(180deg);';
          } else {
            css = '';
          }
          // TODO: rotate image
          css = '';
          // Render thumbnail.
          var span = document.createElement('span');
          span.innerHTML = `<img class="img-responsive" style="${css}" src="${e.target.result}" title="${escape(theFile.name)}"/>`;
          document.getElementById('img-'+id).innerHTML = span.innerHTML;
          document.getElementById('data-'+id).value = e.target.result;
          document.getElementById('filename-'+id).value = theFile.name;
        });

      };
    })(f);

    // Read in the image file as a data URL.
    reader.readAsDataURL(f);
  }
}

var imageinputs = document.getElementsByClassName('yw-image-upload');
for (var i = 0; i < imageinputs.length; i++)
{
    imageinputs.item(i).addEventListener('change', handleFileSelect, false);
}