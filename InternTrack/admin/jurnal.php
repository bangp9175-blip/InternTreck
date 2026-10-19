<?php
$page_title = 'Jurnal Kegiatan';
require_once __DIR__ . '/includes/header.php';
$conn = getConnection();

$search = sanitize($_GET['search'] ?? '');
$filter_siswa = (int)($_GET['siswa'] ?? 0);
$filter_pembimbing = (int)($_GET['pembimbing'] ?? 0);

$where = '1=1';
if ($search) {
    $where .= " AND (j.kegiatan LIKE '%$search%' OR s.nama LIKE '%$search%' OR s.nis LIKE '%$search%')";
}
if ($filter_siswa) {
    $where .= " AND j.siswa_id=$filter_siswa";
}
if ($filter_pembimbing) {
    $where .= " AND pe.pembimbing_id=$filter_pembimbing";
}

// Join penempatan & pembimbing agar guru bisa melihat jurnal siswa bimbingannya
$data = $conn->query("SELECT j.*, s.nama AS siswa, s.nis, s.kelas,
    pb.nama AS pembimbing, pe.status AS status_pkl, p.nama_perusahaan
    FROM jurnal j
    JOIN siswa s ON j.siswa_id = s.id
    LEFT JOIN penempatan pe ON pe.siswa_id = s.id
    LEFT JOIN pembimbing pb ON pe.pembimbing_id = pb.id
    LEFT JOIN perusahaan p ON pe.perusahaan_id = p.id
    WHERE $where
    ORDER BY j.tanggal DESC, j.id DESC
    LIMIT 150");

$siswa_list = $conn->query('SELECT id, nis, nama FROM siswa ORDER BY nama');
$pembimbing_list = $conn->query('SELECT id, nama FROM pembimbing ORDER BY nama');
?>

<style>
.jurnal-filter .form-label { font-size: 0.85rem; font-weight: 500; }
.jurnal-card { border-left: 4px solid #3182ce; }
.badge-pembimbing { background: #ebf8ff; color: #2b6cb0; font-size: 0.75rem; padding: 3px 8px; border-radius: 999px; }
.jurnal-detail-row { background: #f8fafc; }
</style>

<div class="card-custom mb-3 jurnal-filter">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-journal-text"></i> Jurnal Kegiatan Siswa</span>
        <small class="text-muted">Guru pembimbing dapat melihat jurnal siswa bimbingannya</small>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Cari</label>
                <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Nama / NIS / kegiatan...">
            </div>
            <div class="col-md-3">
                <label class="form-label">Filter Siswa</label>
                <select name="siswa" class="form-select">
                    <option value="">Semua Siswa</option>
                    <?php while ($s = $siswa_list->fetch_assoc()): ?>
                        <option value="<?= $s['id'] ?>" <?= $filter_siswa == $s['id'] ? 'selected' : '' ?>>
                            <?= $s['nis'] ?> - <?= htmlspecialchars($s['nama']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Filter Guru Pembimbing</label>
                <select name="pembimbing" class="form-select">
                    <option value="">Semua Pembimbing</option>
                    <?php while ($pb = $pembimbing_list->fetch_assoc()): ?>
                        <option value="<?= $pb['id'] ?>" <?= $filter_pembimbing == $pb['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pb['nama']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Tampilkan</button>
                <a href="<?= base_url('admin/jurnal.php') ?>" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<?php if ($filter_pembimbing): ?>
<div class="alert alert-primary py-2">
    <i class="bi bi-person-badge"></i>
    Menampilkan jurnal siswa yang dibimbing oleh pembimbing terpilih.
</div>
<?php endif; ?>

<div class="card-custom">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-custom table-hover mb-0">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>Siswa</th>
                        <th>Pembimbing</th>
                        <th>Perusahaan</th>
                        <th>Kegiatan</th>
                        <th>Hasil</th>
                        <th>Kendala</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody>
                <?php $no = 1; while ($r = $data->fetch_assoc()): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= date('d/m/Y', strtotime($r['tanggal'])) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($r['siswa']) ?></strong><br>
                            <small class="text-muted"><?= htmlspecialchars($r['nis']) ?> · <?= htmlspecialchars($r['kelas']) ?></small>
                        </td>
                        <td>
                            <?php if (!empty($r['pembimbing'])): ?>
                                <span class="badge-pembimbing"><i class="bi bi-person-check"></i> <?= htmlspecialchars($r['pembimbing']) ?></span>
                            <?php else: ?>
                                <span class="text-muted small">Belum ditempatkan</span>
                            <?php endif; ?>
                        </td>
                        <td><small><?= htmlspecialchars($r['nama_perusahaan'] ?? '-') ?></small></td>
                        <td><?= htmlspecialchars(mb_substr($r['kegiatan'], 0, 40)) ?><?= mb_strlen($r['kegiatan']) > 40 ? '...' : '' ?></td>
                        <td><?= htmlspecialchars(mb_substr($r['hasil'] ?? '-', 0, 30)) ?></td>
                        <td><?= htmlspecialchars(mb_substr($r['kendala'] ?? '-', 0, 30)) ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#jurnal<?= $r['id'] ?>">
                                <i class="bi bi-eye"></i>
                            </button>
                        </td>
                    </tr>

                    <!-- Modal Detail Jurnal (untuk Guru Pembimbing) -->
                    <div class="modal fade" id="jurnal<?= $r['id'] ?>" tabindex="-1">
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header" style="background:#1e3a5f;color:#fff;">
                                    <h5 class="modal-title"><i class="bi bi-journal-text"></i> Detail Jurnal Kegiatan</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong>Siswa:</strong> <?= htmlspecialchars($r['siswa']) ?> (<?= htmlspecialchars($r['nis']) ?>)</p>
                                            <p class="mb-1"><strong>Kelas:</strong> <?= htmlspecialchars($r['kelas']) ?></p>
                                            <p class="mb-1"><strong>Tanggal:</strong> <?= date('d F Y', strtotime($r['tanggal'])) ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong>Pembimbing:</strong> <?= htmlspecialchars($r['pembimbing'] ?? '-') ?></p>
                                            <p class="mb-1"><strong>Perusahaan:</strong> <?= htmlspecialchars($r['nama_perusahaan'] ?? '-') ?></p>
                                            <p class="mb-1"><strong>Status PKL:</strong> <?= htmlspecialchars($r['status_pkl'] ?? '-') ?></p>
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="mb-3">
                                        <label class="form-label text-muted small">KEGIATAN</label>
                                        <div class="p-3 bg-light rounded"><?= nl2br(htmlspecialchars($r['kegiatan'])) ?></div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label text-muted small">HASIL</label>
                                        <div class="p-3 bg-light rounded"><?= nl2br(htmlspecialchars($r['hasil'] ?? '-')) ?></div>
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label text-muted small">KENDALA</label>
                                        <div class="p-3 bg-light rounded"><?= nl2br(htmlspecialchars($r['kendala'] ?? '-')) ?></div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
                <?php if ($no === 1): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-5">
                            <i class="bi bi-journal-x" style="font-size:2rem;"></i><br>
                            Tidak ada data jurnal
                            <?php if ($filter_pembimbing || $filter_siswa || $search): ?>
                                <br><small>Coba ubah filter pencarian</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="alert alert-light border mt-3 mb-0">
    <i class="bi bi-lightbulb"></i>
    <strong>Tips untuk Guru Pembimbing:</strong>
    Gunakan filter <em>Guru Pembimbing</em> untuk menampilkan hanya jurnal siswa yang Anda bimbing.
    Klik ikon mata untuk melihat detail lengkap jurnal.
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
