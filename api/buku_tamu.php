<?php
/**
 * api/buku_tamu.php
 * Endpoint tunggal untuk operasi CRUD Buku Tamu, dibedakan lewat parameter "aksi".
 * aksi=list   (GET)  -> ambil semua data
 * aksi=tambah (POST) -> tambah data baru, tanggal di-generate server (date())
 * aksi=edit   (POST) -> ubah data berdasarkan id, tanggal awal tidak berubah
 * aksi=hapus  (POST) -> hapus data berdasarkan id
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/lib/json_store.php';

const PATH_DATA = __DIR__ . '/../data/tamu.json';

$aksi = $_GET['aksi'] ?? $_POST['aksi'] ?? '';

switch ($aksi) {
    case 'list':
        aksiList();
        break;
    case 'tambah':
        aksiTambah();
        break;
    case 'edit':
        aksiEdit();
        break;
    case 'hapus':
        aksiHapus();
        break;
    default:
        http_response_code(400);
        kirimRespon(false, 'Aksi tidak dikenali.');
}

/* ---------- Helper ---------- */

function kirimRespon(bool $sukses, string $pesan, $data = null): void
{
    echo json_encode([
        'sukses' => $sukses,
        'pesan'  => $pesan,
        'data'   => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Validasi input dari form. Dijalankan di server -- jangan hanya andalkan
 * validasi "required" di HTML, karena request bisa dikirim langsung tanpa form.
 */
function validasiInput(string $nama, string $email, string $pesan): array
{
    $error = [];
    if (trim($nama) === '') {
        $error[] = 'Nama wajib diisi.';
    }
    if (trim($pesan) === '') {
        $error[] = 'Pesan wajib diisi.';
    }
    if (trim($email) !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error[] = 'Format email tidak valid.';
    }
    return $error;
}

function bersihkanTeks(string $teks): string
{
    // Cegah XSS sederhana: data ini tampil lagi di tabel publik, jadi harus di-escape
    // sebelum disimpan maupun saat ditampilkan (defense in depth).
    return htmlspecialchars(trim($teks), ENT_QUOTES, 'UTF-8');
}

/* ---------- Aksi ---------- */

function aksiList(): void
{
    $data = bacaJson(PATH_DATA);

    usort($data, function ($a, $b) {
        return strtotime($b['tanggal']) <=> strtotime($a['tanggal']);
    });

    kirimRespon(true, 'Berhasil mengambil data.', $data);
}

function aksiTambah(): void
{
    $nama  = (string) ($_POST['nama'] ?? '');
    $email = (string) ($_POST['email'] ?? '');
    $pesan = (string) ($_POST['pesan'] ?? '');

    $error = validasiInput($nama, $email, $pesan);
    if (!empty($error)) {
        kirimRespon(false, implode(' ', $error));
    }

    [$data, $fp] = bukaJsonTerkunci(PATH_DATA);
    if ($fp === null) {
        kirimRespon(false, 'Gagal mengakses penyimpanan data.');
    }

    $idBaru = empty($data) ? 1 : max(array_column($data, 'id')) + 1;

    $entriBaru = [
        'id'      => $idBaru,
        'nama'    => bersihkanTeks($nama),
        'email'   => bersihkanTeks($email),
        'pesan'   => bersihkanTeks($pesan),
        'tanggal' => date('Y-m-d H:i:s'), // auto-generate, waktu server -- BUKAN input user
    ];

    $data[] = $entriBaru;
    tutupJsonTerkunci($fp, $data);

    kirimRespon(true, 'Pesan berhasil dikirim. Terima kasih!', $entriBaru);
}

function aksiEdit(): void
{
    $id    = (int) ($_POST['id'] ?? 0);
    $nama  = (string) ($_POST['nama'] ?? '');
    $email = (string) ($_POST['email'] ?? '');
    $pesan = (string) ($_POST['pesan'] ?? '');

    $error = validasiInput($nama, $email, $pesan);
    if (!empty($error)) {
        kirimRespon(false, implode(' ', $error));
    }

    [$data, $fp] = bukaJsonTerkunci(PATH_DATA);
    if ($fp === null) {
        kirimRespon(false, 'Gagal mengakses penyimpanan data.');
    }

    $ditemukan = false;
    foreach ($data as &$item) {
        if ((int) $item['id'] === $id) {
            $item['nama']  = bersihkanTeks($nama);
            $item['email'] = bersihkanTeks($email);
            $item['pesan'] = bersihkanTeks($pesan);
            // tanggal TIDAK diubah -- tetap tanggal submit awal, bukan tanggal edit
            $ditemukan = true;
            break;
        }
    }
    unset($item);

    tutupJsonTerkunci($fp, $data);

    if (!$ditemukan) {
        kirimRespon(false, 'Data tidak ditemukan.');
    }
    kirimRespon(true, 'Data berhasil diperbarui.');
}

function aksiHapus(): void
{
    $id = (int) ($_POST['id'] ?? 0);

    [$data, $fp] = bukaJsonTerkunci(PATH_DATA);
    if ($fp === null) {
        kirimRespon(false, 'Gagal mengakses penyimpanan data.');
    }

    $jumlahAwal = count($data);

    // array_filter() membuat key array jadi tidak berurutan (gappy), yang kalau
    // langsung di-json_encode() akan jadi objek {} bukan array [] -- array_values()
    // WAJIB dipanggil sesudahnya untuk merapikan kembali index-nya.
    $dataBaru = array_values(array_filter($data, function ($item) use ($id) {
        return (int) $item['id'] !== $id;
    }));

    tutupJsonTerkunci($fp, $dataBaru);

    if (count($dataBaru) === $jumlahAwal) {
        kirimRespon(false, 'Data tidak ditemukan.');
    }
    kirimRespon(true, 'Data berhasil dihapus.');
}
