document.addEventListener('DOMContentLoaded', function () {
  var copyBtn = document.querySelector('.voucher-card__copy[data-copy-code]');
  if (!copyBtn) {
    return;
  }

  var defaultLabel = copyBtn.textContent.trim();

  copyBtn.addEventListener('click', function () {
    var code = copyBtn.getAttribute('data-copy-code') || '';
    if (!code) {
      return;
    }

    function onSuccess() {
      copyBtn.classList.add('is-copied');
      copyBtn.textContent = 'Đã sao chép';
      window.setTimeout(function () {
        copyBtn.classList.remove('is-copied');
        copyBtn.textContent = defaultLabel;
      }, 2000);
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(code).then(onSuccess).catch(fallbackCopy);
      return;
    }

    fallbackCopy();

    function fallbackCopy() {
      var area = document.createElement('textarea');
      area.value = code;
      area.setAttribute('readonly', '');
      area.style.position = 'fixed';
      area.style.left = '-9999px';
      document.body.appendChild(area);
      area.select();
      try {
        document.execCommand('copy');
        onSuccess();
      } catch (err) {
        /* ignore */
      }
      document.body.removeChild(area);
    }
  });
});
