<?php
/**
 * json_store.php
 * Pustaka terpusat untuk baca/tulis file JSON secara aman.
 * Menggunakan file locking (LOCK_EX) untuk mencegah race condition
 * saat dua request menulis ke file yang sama secara bersamaan.
 */

/**
 * Baca isi file JSON dan kembalikan sebagai array PHP.
 * Jika file belum ada / kosong / rusak, kembalikan array kosong.
 */
function bacaJson(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }
    $isi  = file_get_contents($path);
    $data = json_decode($isi, true);
    return is_array($data) ? $data : [];
}

/**
 * Tulis array PHP ke file JSON (operasi tunggal, dengan lock).
 * Dipakai untuk kasus simpel yang tidak butuh read-modify-write terkunci penuh.
 */
function tulisJson(string $path, array $data): bool
{
    $fp = fopen($path, 'c+');
    if ($fp === false) {
        return false;
    }
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }
    fclose($fp);
    return false;
}

/**
 * Buka + kunci file untuk operasi read-modify-write yang aman dari race condition.
 * Lock ini sengaja dibiarkan terbuka (blocking) sampai tutupJsonTerkunci() dipanggil,
 * supaya tidak ada request lain yang bisa menulis di antara proses baca dan tulis.
 *
 * @return array{0: array|null, 1: resource|null} [data, file_pointer]
 */
function bukaJsonTerkunci(string $path): array
{
    $fp = fopen($path, 'c+');
    if ($fp === false) {
        return [null, null];
    }
    flock($fp, LOCK_EX); // blocking lock -- tunggu sampai file bebas dari proses lain
    $isi  = stream_get_contents($fp);
    $data = json_decode($isi, true);
    if (!is_array($data)) {
        $data = [];
    }
    return [$data, $fp];
}

/**
 * Tulis data final dan lepas lock yang dibuka oleh bukaJsonTerkunci().
 * WAJIB dipanggil setelah bukaJsonTerkunci(), bahkan jika tidak ada perubahan,
 * agar lock tidak menggantung dan memblokir request lain.
 *
 * @param resource $fp
 */
function tutupJsonTerkunci($fp, array $data): void
{
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}
