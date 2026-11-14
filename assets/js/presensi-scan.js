(function () {
  'use strict';

  var html5QrCode = null;
  var isScanning = false;
  var cameras = [];

  var elScanSection = document.getElementById('scanSection');
  var elResultSection = document.getElementById('resultSection');
  var elResultContent = document.getElementById('resultContent');
  var elAlert = document.getElementById('scanAlert');
  var elBtnStart = document.getElementById('btnStartScan');
  var elBtnStop = document.getElementById('btnStopScan');
  var elCameraSelect = document.getElementById('cameraSelect');

  var BASE = window.location.pathname.replace(/presensi\/scan\.php.*$/, '');

  function showAlert(type, message) {
    elAlert.innerHTML = '<div class="alert alert-' + type + ' mt-3">' + escapeHtml(message) + '</div>';
    setTimeout(function () { elAlert.innerHTML = ''; }, 5000);
  }

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str == null ? '' : str;
    return div.innerHTML;
  }

  function postForm(url, data) {
    var formData = new FormData();
    Object.keys(data).forEach(function (key) { formData.append(key, data[key]); });
    return fetch(url, { method: 'POST', body: formData, credentials: 'same-origin' })
      .then(function (response) {
        return response.json().then(function (json) {
          return { status: response.status, body: json };
        });
      });
  }

  function pickBackCameraId(list) {
    var back = (list || []).find(function (c) { return /back|belakang|rear/i.test(c.label || ''); });
    return back ? back.id : null;
  }

  function renderCameraSelect() {
    if (!elCameraSelect || cameras.length <= 1) return;
    elCameraSelect.classList.remove('d-none');
    elCameraSelect.innerHTML = cameras.map(function (c, i) {
      return '<option value="' + c.id + '">' + escapeHtml(c.label || ('Kamera ' + (i + 1))) + '</option>';
    }).join('');
    var backIndex = cameras.findIndex(function (c) { return /back|belakang|rear/i.test(c.label || ''); });
    if (backIndex >= 0) elCameraSelect.selectedIndex = backIndex;
    elCameraSelect.onchange = function () {
      if (!elCameraSelect.value) return;
      stopScanning().then(function () {
        tryStartWithConfigs([{ deviceId: { exact: elCameraSelect.value } }, { facingMode: 'environment' }, {}]);
      });
    };
  }

  // Beberapa browser (terutama Chrome/WebKit di iOS) tidak konsisten
  // menangani facingMode:'environment' untuk memilih kamera belakang, jadi
  // kita coba beberapa cara berurutan: deviceId kamera belakang (by label)
  // -> facingMode environment -> kamera apa pun yang tersedia.
  function tryStartWithConfigs(configs) {
    if (!configs.length) {
      showAlert('danger', 'Tidak dapat mengakses kamera manapun di perangkat ini.');
      return;
    }
    var config = configs[0];
    var rest = configs.slice(1);
    html5QrCode.start(
      config,
      { fps: 10, qrbox: 240 },
      onScanSuccess,
      function () { /* gagal baca per-frame, abaikan */ }
    ).then(function () {
      isScanning = true;
      elBtnStart.classList.add('d-none');
      elBtnStop.classList.remove('d-none');
    }).catch(function (err) {
      if (rest.length) {
        tryStartWithConfigs(rest);
      } else {
        showAlert('danger', 'Tidak dapat mengakses kamera: ' + err);
      }
    });
  }

  function startScanning() {
    if (isScanning) return;
    html5QrCode = new Html5Qrcode('qr-reader', {
      formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
      verbose: false
    });

    Html5Qrcode.getCameras().then(function (devices) {
      cameras = devices || [];
      renderCameraSelect();

      var configs = [];
      if (elCameraSelect && elCameraSelect.value) {
        configs.push({ deviceId: { exact: elCameraSelect.value } });
      } else {
        var backId = pickBackCameraId(cameras);
        if (backId) configs.push({ deviceId: { exact: backId } });
      }
      configs.push({ facingMode: 'environment' });
      configs.push({});
      tryStartWithConfigs(configs);
    }).catch(function () {
      // Enumerasi kamera gagal (izin belum diberikan) -- start() di bawah
      // akan memicu prompt izin kamera dari browser.
      tryStartWithConfigs([{ facingMode: 'environment' }, {}]);
    });
  }

  function stopScanning() {
    if (!html5QrCode || !isScanning) return Promise.resolve();
    return html5QrCode.stop().then(function () {
      isScanning = false;
      elBtnStart.classList.remove('d-none');
      elBtnStop.classList.add('d-none');
    }).catch(function () {});
  }

  function pauseScanning() {
    if (html5QrCode && isScanning) {
      try { html5QrCode.pause(true); } catch (e) {}
    }
  }

  function resumeScanning() {
    if (html5QrCode && isScanning) {
      try { html5QrCode.resume(); } catch (e) {}
    }
  }

  function onScanSuccess(decodedText) {
    pauseScanning();
    submitScan(decodedText.trim());
  }

  function submitScan(content) {
    postForm(BASE + 'presensi/checkin.php', { content: content, csrf_token: window.HADIRIN_CSRF })
      .then(function (res) {
        if (res.body.success) {
          renderSuccess(res.body);
        } else {
          showAlert(res.status === 409 ? 'warning' : 'danger', res.body.message || 'Presensi gagal.');
          setTimeout(resumeScanning, 1500);
        }
      })
      .catch(function () {
        showAlert('danger', 'Gagal menghubungi server. Periksa koneksi internet.');
        setTimeout(resumeScanning, 1500);
      });
  }

  function renderSuccess(result) {
    elScanSection.classList.add('d-none');
    elResultSection.classList.remove('d-none');
    elResultContent.innerHTML = '' +
      '<div class="card text-center">' +
        '<div class="card-body">' +
          '<i class="bi bi-check-circle-fill text-success" style="font-size:3rem;"></i>' +
          '<h4 class="mt-2 text-success">PRESENSI BERHASIL</h4>' +
          '<p class="fw-semibold">' + escapeHtml(result.session_label) + '</p>' +
          '<p class="text-muted small">' + escapeHtml(result.checked_in_at) + '</p>' +
          '<button id="btnScanAgain" class="btn btn-primary btn-lg w-100">Scan Lagi</button>' +
        '</div>' +
      '</div>';

    document.getElementById('btnScanAgain').addEventListener('click', function () {
      elResultSection.classList.add('d-none');
      elScanSection.classList.remove('d-none');
      resumeScanning();
    });
  }

  if (elBtnStart) elBtnStart.addEventListener('click', startScanning);
  if (elBtnStop) elBtnStop.addEventListener('click', stopScanning);
})();
