(function () {
  'use strict';

  var currentToken = null;

  var elScanSection = document.getElementById('scanSection');
  var elResultSection = document.getElementById('resultSection');
  var elResultContent = document.getElementById('resultContent');
  var elAlert = document.getElementById('scanAlert');
  var elManualToken = document.getElementById('manualToken');
  var elBtnManualCheck = document.getElementById('btnManualCheck');
  var elEventSelect = document.getElementById('eventSelect');
  var elEventInfo = document.getElementById('eventInfo');
  var elEventBanner = document.getElementById('activeEventBanner');
  var elNoEventNotice = document.getElementById('noEventNotice');

  var BASE = window.location.pathname.replace(/scanner\/index\.php.*$/, '');

  /* ---------- Pemilihan event aktif (Langkah 1) ---------- */

  function currentEventId() {
    return elEventSelect && elEventSelect.value ? elEventSelect.value : '';
  }

  function selectedOption() {
    if (!elEventSelect || elEventSelect.selectedIndex < 0) return null;
    return elEventSelect.options[elEventSelect.selectedIndex] || null;
  }

  function currentEventName() {
    var opt = selectedOption();
    if (!opt) return '';
    return opt.getAttribute('data-name') || opt.textContent.trim();
  }

  function syncEventUI() {
    if (!elScanSection) return;
    var eventId = currentEventId();
    var opt = selectedOption();

    if (eventId) {
      elScanSection.classList.remove('d-none');
      if (elNoEventNotice) elNoEventNotice.classList.add('d-none');
      if (elEventBanner) elEventBanner.textContent = currentEventName();
      if (elManualToken) elManualToken.focus();

      if (elEventInfo) {
        var bits = [];
        var date = opt ? opt.getAttribute('data-date') : '';
        var location = opt ? opt.getAttribute('data-location') : '';
        if (date) bits.push('Tanggal: ' + date);
        if (location) bits.push('Lokasi: ' + location);
        elEventInfo.textContent = bits.join(' · ');
        elEventInfo.classList.toggle('d-none', bits.length === 0);
      }
    } else {
      elScanSection.classList.add('d-none');
      if (elResultSection) elResultSection.classList.add('d-none');
      if (elNoEventNotice) elNoEventNotice.classList.remove('d-none');
    }
  }

  /* ---------- Utilitas ---------- */

  function showAlert(type, message) {
    if (!elAlert) return;
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
    Object.keys(data).forEach(function (key) {
      var value = data[key];
      if (Array.isArray(value)) {
        value.forEach(function (v) { formData.append(key + '[]', v); });
      } else {
        formData.append(key, value);
      }
    });
    return fetch(url, { method: 'POST', body: formData, credentials: 'same-origin' })
      .then(function (response) {
        return response.json().then(function (json) {
          return { status: response.status, body: json };
        });
      });
  }

  /* ---------- Validasi & check-in ---------- */

  function validateToken(token) {
    if (!token) return;
    if (!currentEventId()) {
      showAlert('warning', 'Pilih event aktif terlebih dahulu.');
      return;
    }
    postForm(BASE + 'scanner/validate.php', {
      token: token,
      event_id: currentEventId(),
      csrf_token: window.HADIRIN_CSRF
    })
      .then(function (res) {
        if (res.body.success) {
          currentToken = token;
          renderGroupResult(res.body.group, res.body.guests);
        } else {
          showAlert('danger', res.body.message || 'Barcode tidak valid.');
        }
      })
      .catch(function () {
        showAlert('danger', 'Gagal menghubungi server. Periksa koneksi internet.');
      });
  }

  function renderGroupResult(group, guests) {
    elScanSection.classList.add('d-none');
    elResultSection.classList.remove('d-none');

    var rows = guests.map(function (g) {
      var checkedInClass = g.checked_in ? 'checked-in' : '';
      var disabled = g.checked_in ? 'disabled checked' : '';
      var statusText = g.checked_in ? 'Sudah Masuk' : 'Belum Masuk';
      return '' +
        '<div class="guest-check-row ' + checkedInClass + '">' +
          '<input type="checkbox" class="guest-checkbox" value="' + g.id + '" ' + disabled + '>' +
          '<label>' + escapeHtml(g.full_name) + ' <span class="text-muted small">&mdash; ' + statusText + '</span></label>' +
        '</div>';
    }).join('');

    elResultContent.innerHTML = '' +
      '<div class="alert alert-success text-center fw-semibold">Barcode Valid</div>' +
      '<div class="alert alert-primary py-2 small">Event: <strong>' + escapeHtml(currentEventName()) + '</strong></div>' +
      '<div class="card mb-3">' +
        '<div class="card-body">' +
          '<h5 class="mb-1">' + escapeHtml(group.group_name) + '</h5>' +
          '<p class="text-muted mb-2">Kuota: ' + group.quota + ' &middot; Hadir: ' + group.present + ' &middot; Belum Hadir: ' + group.absent + '</p>' +
          '<div id="guestList">' + rows + '</div>' +
        '</div>' +
      '</div>' +
      '<button id="btnConfirmCheckin" class="btn btn-success btn-lg w-100 mb-2" disabled>KONFIRMASI CHECK-IN (0 TAMU)</button>' +
      '<button id="btnNextScan" class="btn btn-outline-primary btn-lg w-100">Scan Berikutnya</button>';

    document.querySelectorAll('.guest-checkbox:not([disabled])').forEach(function (cb) {
      cb.addEventListener('change', updateConfirmButton);
    });
    document.getElementById('btnConfirmCheckin').addEventListener('click', confirmCheckin);
    document.getElementById('btnNextScan').addEventListener('click', backToScan);
  }

  function updateConfirmButton() {
    var checked = document.querySelectorAll('.guest-checkbox:checked:not([disabled])');
    var btn = document.getElementById('btnConfirmCheckin');
    btn.textContent = 'KONFIRMASI CHECK-IN (' + checked.length + ' TAMU)';
    btn.disabled = checked.length === 0;
  }

  function confirmCheckin() {
    var checked = document.querySelectorAll('.guest-checkbox:checked:not([disabled])');
    var guestIds = Array.prototype.map.call(checked, function (cb) { return cb.value; });
    if (guestIds.length === 0) return;

    var btn = document.getElementById('btnConfirmCheckin');
    btn.disabled = true;
    btn.textContent = 'Memproses...';

    postForm(BASE + 'scanner/checkin.php', {
      token: currentToken,
      guest_ids: guestIds,
      event_id: currentEventId(),
      csrf_token: window.HADIRIN_CSRF
    })
      .then(function (res) {
        if (res.body.success) {
          renderCheckinSuccess(res.body);
        } else if (res.status === 409) {
          showAlert('warning', res.body.message);
          validateToken(currentToken);
        } else {
          showAlert('danger', res.body.message || 'Check-in gagal.');
          btn.disabled = false;
          updateConfirmButton();
        }
      })
      .catch(function () {
        showAlert('danger', 'Gagal menghubungi server. Periksa koneksi internet.');
        btn.disabled = false;
        updateConfirmButton();
      });
  }

  function renderCheckinSuccess(result) {
    var names = result.guests.map(function (g) {
      return '<li>' + escapeHtml(g.full_name) + '</li>';
    }).join('');
    var timestamp = result.guests.length ? result.guests[0].checkin_at : '';

    elResultContent.innerHTML = '' +
      '<div class="card text-center">' +
        '<div class="card-body">' +
          '<i class="bi bi-check-circle-fill text-success" style="font-size:3rem;"></i>' +
          '<h4 class="mt-2 text-success">CHECK-IN BERHASIL</h4>' +
          '<p class="fw-semibold">' + result.checked_in_count + ' Tamu Diizinkan Masuk</p>' +
          '<ul class="list-unstyled">' + names + '</ul>' +
          '<p class="text-muted small">' + escapeHtml(timestamp) + '</p>' +
          '<button id="btnNextScan2" class="btn btn-primary btn-lg w-100 mb-2">Scan Berikutnya</button>' +
          '<button id="btnViewGroup" class="btn btn-outline-secondary btn-lg w-100">Lihat Detail Grup</button>' +
        '</div>' +
      '</div>';

    document.getElementById('btnNextScan2').addEventListener('click', backToScan);
    document.getElementById('btnViewGroup').addEventListener('click', function () {
      validateToken(currentToken);
    });
  }

  function backToScan() {
    elResultSection.classList.add('d-none');
    elScanSection.classList.remove('d-none');
    currentToken = null;
    if (elManualToken) elManualToken.focus();
  }

  /* ---------- Input barcode manual / scanner genggam ---------- */

  function submitManualToken() {
    var token = elManualToken.value.trim();
    if (!token) {
      showAlert('warning', 'Masukkan kode barcode terlebih dahulu.');
      return;
    }
    validateToken(token);
    elManualToken.value = '';
  }

  if (elBtnManualCheck) {
    elBtnManualCheck.addEventListener('click', submitManualToken);
  }
  if (elManualToken) {
    // Barcode scanner genggam (USB/Bluetooth) umumnya bertindak sebagai
    // keyboard: mengetik kode lalu menekan Enter secara otomatis.
    elManualToken.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        submitManualToken();
      }
    });
  }

  /* ---------- Inisialisasi ---------- */

  if (elEventSelect) {
    elEventSelect.addEventListener('change', function () {
      if (elResultSection) elResultSection.classList.add('d-none');
      currentToken = null;
      syncEventUI();
    });
    syncEventUI();
  }
})();
