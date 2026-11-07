// assets/js/app.js
// Frontend vanilla ES6 untuk aplikasi Buku Tamu.
// Semua operasi data (list/tambah/edit/hapus) lewat fetch() ke api/buku_tamu.php.

const API_URL = 'api/buku_tamu.php';

const form       = document.getElementById('form-tamu');
const inputId    = document.getElementById('tamu-id');
const inputNama  = document.getElementById('nama');
const inputEmail = document.getElementById('email');
const inputPesan = document.getElementById('pesan');
const btnSubmit  = document.getElementById('btn-submit');
const btnBatal   = document.getElementById('btn-batal');
const formTitle  = document.getElementById('form-title');
const formStatus = document.getElementById('form-status');
const tabelBody  = document.getElementById('tabel-body');
const emptyMsg   = document.getElementById('empty-msg');
const jumlahTamu = document.getElementById('jumlah-tamu');

// Tahun footer -- satu kali panggil saja (hindari bug duplikat getElementById
// yang sama seperti ditemukan di proyek sebelumnya).
document.getElementById('tahun').textContent = new Date().getFullYear();

/* ---------- Util ---------- */

const tampilkanStatus = (pesan, jenis = 'info') => {
  formStatus.textContent = pesan;
  formStatus.className = `status-msg status-${jenis}`;
};

const formatTanggal = (tanggalString) => {
  // "YYYY-MM-DD HH:MM:SS" dari PHP -> ganti spasi jadi "T" supaya bisa di-parse Date()
  const d = new Date(tanggalString.replace(' ', 'T'));
  if (isNaN(d)) return tanggalString;
  return d.toLocaleString('id-ID', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
};

// Data sudah di-escape di server (htmlspecialchars), tapi tetap escape lagi
// di sisi klien sebagai lapisan pertahanan kedua sebelum dimasukkan ke innerHTML.
const escapeHTML = (str = '') =>
  str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

/* ---------- Ambil & tampilkan data ---------- */

async function muatDaftarTamu() {
  try {
    const res = await fetch(`${API_URL}?aksi=list`);
    const hasil = await res.json();

    if (!hasil.sukses) {
      tampilkanStatus(hasil.pesan, 'error');
      return;
    }
    renderTabel(hasil.data);
  } catch (err) {
    tampilkanStatus('Gagal memuat data dari server.', 'error');
    console.error(err);
  }
}

function renderTabel(daftar) {
  tabelBody.innerHTML = '';
  jumlahTamu.textContent = daftar.length;

  if (daftar.length === 0) {
    emptyMsg.hidden = false;
    return;
  }
  emptyMsg.hidden = true;

  for (const tamu of daftar) {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>
      <h3>${escapeHTML(tamu.nama)}</h3><br>
      <small>${escapeHTML(tamu.email || '-')}</small><br>
      <small>${formatTanggal(tamu.tanggal)}</small>
      </td>
      <td>${escapeHTML(tamu.pesan)}</td>
      <td class="aksi-cell">
        <button type="button" class="btn-edit" data-id="${tamu.id}">Edit</button>
        <button type="button" class="btn-hapus" data-id="${tamu.id}">Hapus</button>
      </td>
    `;
    tabelBody.appendChild(tr);
  }
}

/* ---------- Simpan (tambah / edit) ---------- */

async function kirimForm(event) {
  event.preventDefault();
  btnSubmit.disabled = true;

  const aksi = inputId.value ? 'edit' : 'tambah';
  const formData = new FormData();
  formData.append('aksi', aksi);
  if (inputId.value) formData.append('id', inputId.value);
  formData.append('nama', inputNama.value.trim());
  formData.append('email', inputEmail.value.trim());
  formData.append('pesan', inputPesan.value.trim());

  try {
    const res = await fetch(API_URL, { method: 'POST', body: formData });
    const hasil = await res.json();

    if (!hasil.sukses) {
      tampilkanStatus(hasil.pesan, 'error');
      return;
    }

    tampilkanStatus(hasil.pesan, 'sukses');
    resetForm();
    await muatDaftarTamu();
  } catch (err) {
    tampilkanStatus('Terjadi kesalahan saat mengirim data.', 'error');
    console.error(err);
  } finally {
    btnSubmit.disabled = false;
  }
}

function resetForm() {
  form.reset();
  inputId.value = '';
  formTitle.textContent = 'Tulis Pesan';
  btnSubmit.textContent = 'Kirim';
  btnBatal.hidden = true;
}

function isiFormUntukEdit(tamu) {
  inputId.value = tamu.id;
  inputNama.value = tamu.nama;
  inputEmail.value = tamu.email;
  inputPesan.value = tamu.pesan;
  formTitle.textContent = 'Edit Pesan';
  btnSubmit.textContent = 'Simpan Perubahan';
  btnBatal.hidden = false;
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

/* ---------- Hapus ---------- */

async function hapusTamu(id) {
  if (!confirm('Yakin ingin menghapus data ini?')) return;

  const formData = new FormData();
  formData.append('aksi', 'hapus');
  formData.append('id', id);

  try {
    const res = await fetch(API_URL, { method: 'POST', body: formData });
    const hasil = await res.json();
    tampilkanStatus(hasil.pesan, hasil.sukses ? 'sukses' : 'error');
    if (hasil.sukses) await muatDaftarTamu();
  } catch (err) {
    tampilkanStatus('Gagal menghapus data.', 'error');
    console.error(err);
  }
}

/* ---------- Event listener ---------- */

tabelBody.addEventListener('click', async (event) => {
  const target = event.target;
  const id = target.dataset.id;
  if (!id) return;

  if (target.classList.contains('btn-hapus')) {
    await hapusTamu(id);
  } else if (target.classList.contains('btn-edit')) {
    // Ambil ulang daftar terbaru supaya data yang masuk ke form selalu akurat
    const res = await fetch(`${API_URL}?aksi=list`);
    const hasil = await res.json();
    const tamu = hasil.data.find((t) => String(t.id) === id);
    if (tamu) isiFormUntukEdit(tamu);
  }
});

btnBatal.addEventListener('click', resetForm);
form.addEventListener('submit', kirimForm);

muatDaftarTamu();
