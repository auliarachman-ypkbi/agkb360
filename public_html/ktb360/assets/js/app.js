// ============================================================
// KTB 360° Evaluation Platform — Main JavaScript
// ============================================================

// ── CSRF helper ───────────────────────────────────────────────
const csrf = document.querySelector('meta[name=csrf]')?.content || '';

// ── AJAX helper ───────────────────────────────────────────────
async function apiPost(url, data) {
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
    body: JSON.stringify(data)
  });
  return res.json();
}

async function apiGet(url) {
  const res = await fetch(url, { headers: { 'X-CSRF-Token': csrf } });
  return res.json();
}

// ── DataTables default init ────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  if (typeof $.fn.DataTable !== 'undefined') {
    $.extend(true, $.fn.dataTable.defaults, {
      language: {
        url: '//cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json'
      },
      pageLength: 25,
      responsive: true,
      pagingType: 'full_numbers',
      dom: "<'row align-items-center mb-3'<'col-sm-6'l><'col-sm-6 text-end'f>>" +
           "<'row'<'col-12'tr>>" +
           "<'row align-items-center mt-3'<'col-sm-5 text-muted small'i><'col-sm-7 d-flex justify-content-end'p>>",
    });
    $('.data-table').DataTable();
  }

  // Auto-dismiss alerts after 4s
  document.querySelectorAll('.alert.auto-dismiss').forEach(el => {
    setTimeout(() => {
      const bs = bootstrap.Alert.getOrCreateInstance(el);
      bs?.close();
    }, 4000);
  });

  // Confirm delete buttons
  document.querySelectorAll('[data-confirm]').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      e.preventDefault();
      const msg = btn.dataset.confirm || 'Yakin ingin menghapus?';
      const result = await Swal.fire({
        title: 'Konfirmasi', text: msg, icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#0D2D5E', cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, lanjutkan', cancelButtonText: 'Batal'
      });
      if (result.isConfirmed) {
        if (btn.href) window.location = btn.href;
        else btn.closest('form')?.submit();
      }
    });
  });
});

// ── Score color helper ────────────────────────────────────────
function scoreColor(score) {
  if (score < 1.75) return '#dc3545';
  if (score < 2.50) return '#fd7e14';
  if (score < 3.25) return '#0d6efd';
  return '#198754';
}

// ── Render radar chart ────────────────────────────────────────
function renderRadarChart(canvasId, labels, data, label = 'Skor') {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;
  new Chart(ctx, {
    type: 'radar',
    data: {
      labels,
      datasets: [{
        label,
        data,
        backgroundColor: 'rgba(13,45,94,0.15)',
        borderColor: '#0D2D5E',
        pointBackgroundColor: '#C9A227',
        pointBorderColor: '#0D2D5E',
        pointRadius: 5
      }]
    },
    options: {
      responsive: true,
      scales: { r: { min: 0, max: 4, ticks: { stepSize: 1 } } },
      plugins: { legend: { display: false } }
    }
  });
}

// ── Render bar chart ──────────────────────────────────────────
function renderBarChart(canvasId, labels, data, label = 'Skor Rata-rata') {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label,
        data,
        backgroundColor: data.map(v => scoreColor(v) + '99'),
        borderColor: data.map(v => scoreColor(v)),
        borderWidth: 2,
        borderRadius: 6
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        y: { min: 0, max: 4, ticks: { stepSize: 1 } }
      }
    }
  });
}

// ── Export to JSON ────────────────────────────────────────────
async function exportData(type) {
  const res = await fetch(`/api/data.php?action=export&type=${type}`);
  const blob = await res.blob();
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `ktb360_${type}_${new Date().toISOString().slice(0,10)}.json`;
  a.click();
  URL.revokeObjectURL(url);
}

// ── Import from JSON ──────────────────────────────────────────
async function importData(file, type) {
  const text = await file.text();
  const data = JSON.parse(text);
  const result = await apiPost('/api/data.php', { action: 'import', type, data });
  return result;
}

// ── AI Suggestion ─────────────────────────────────────────────
async function generateAISuggestion(evaluateeId, periodId) {
  const btn = document.getElementById('btn-generate-ai');
  if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generating...'; }

  try {
    const result = await apiPost('/api/ai.php', { evaluatee_id: evaluateeId, period_id: periodId });
    if (result.suggestion) {
      document.getElementById('ai-text').value = result.suggestion;
      document.getElementById('ai-container').style.display = 'block';
    } else {
      Swal.fire('Error', result.error || 'Gagal generate saran', 'error');
    }
  } catch(e) {
    Swal.fire('Error', 'Koneksi gagal', 'error');
  } finally {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-stars me-2"></i>Generate AI Saran'; }
  }
}

// ── Hard Reset (admin) ────────────────────────────────────────
async function hardReset() {
  const result = await Swal.fire({
    title: '⚠️ HARD RESET',
    html: 'Ini akan <strong>menghapus semua data</strong> (responses, assignments, AI suggestions) dan mereset ke kondisi awal.<br><br>Ketik <code>RESET</code> untuk konfirmasi.',
    input: 'text', icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc3545', cancelButtonColor: '#6c757d',
    confirmButtonText: 'Reset', cancelButtonText: 'Batal',
    preConfirm: (val) => {
      if (val !== 'RESET') { Swal.showValidationMessage('Ketik RESET untuk konfirmasi'); return false; }
      return true;
    }
  });
  if (result.isConfirmed) {
    const res = await apiPost('/api/data.php', { action: 'hard_reset' });
    if (res.success) {
      Swal.fire('Berhasil', 'Data telah direset ke kondisi awal.', 'success').then(() => location.reload());
    } else {
      Swal.fire('Error', res.error || 'Reset gagal', 'error');
    }
  }
}