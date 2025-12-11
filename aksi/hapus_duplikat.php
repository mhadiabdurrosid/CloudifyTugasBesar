<?php

if (!isset($conn)) {
    // Jika file ini dipanggil langsung, hentikan proses
    return;
}

/*
 * Hapus entri duplikat pada tabel `files`
 * Duplikat ditentukan berdasarkan: name + path
 * Yang disimpan hanya yang paling terbaru (created_at paling besar)
 */

try {
    // Ambil semua file yang berpotensi duplikat
    $sql = "
        SELECT id, name, path, size, mime, extension, uploaded_by, created_at
        FROM files
        WHERE is_deleted IS NULL OR is_deleted = 0
        ORDER BY name, path, created_at DESC
    ";

    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $seen = [];    // untuk menandai file yang sudah dipertahankan
        $deleteIds = [];

        while ($row = $result->fetch_assoc()) {
            $key = strtolower($row['name'] . '|' . $row['path']);

            // Jika belum ada key → simpan versi pertama (yang paling baru)
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                continue;
            }

            // Jika sudah ada → masukkan ke daftar delete
            $deleteIds[] = $row['id'];
        }

        // Jika ada data yang harus dihapus
        if (!empty($deleteIds)) {
            $ids = implode(',', array_map('intval', $deleteIds));

            $conn->query("UPDATE files SET is_deleted = 1 WHERE id IN ($ids)");
        }
    }

} catch (Throwable $e) {
    // Abaikan error untuk keamanan tampilan
    // Tetapi boleh di-log jika ingin
    // file_put_contents('log_duplikat.txt', $e->getMessage(), FILE_APPEND);
}

?>
