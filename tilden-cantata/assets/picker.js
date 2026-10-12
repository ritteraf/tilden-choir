document.addEventListener('click', function (e) {
  var pick = e.target.closest('.tcc-media__pick'), clear = e.target.closest('.tcc-media__clear');
  if (!pick && !clear) return;
  e.preventDefault();
  var box = (pick || clear).closest('.tcc-media'), input = box.querySelector('input[type=hidden]');
  var img = box.querySelector('.tcc-media__preview'), name = box.querySelector('.tcc-media__name');
  if (clear) { input.value = ''; if (img) { img.src = ''; img.style.display = 'none'; } if (name) name.textContent = ''; clear.style.display = 'none'; return; }
  var isImage = box.dataset.type === 'image';
  var frame = wp.media({ title: isImage ? 'Choose cover art' : 'Choose file', multiple: false, library: isImage ? { type: 'image' } : {} });
  frame.on('select', function () {
    var a = frame.state().get('selection').first().toJSON();
    input.value = a.id;
    if (img) { img.src = (a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url); img.style.display = 'block'; }
    if (name) name.textContent = a.filename || a.title;
    box.querySelector('.tcc-media__clear').style.display = '';
  });
  frame.open();
});
document.addEventListener('input', function (e) {
  if (e.target.id === '_tcc_tint') { var s = e.target.parentNode.querySelector('.tcc-swatch'); if (s) s.style.background = e.target.value; }
});
