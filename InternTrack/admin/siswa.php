<?php
$page_title = 'Data Siswa';
require_once __DIR__ . '/includes/header.php';
$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'tambah' || $action === 'edit') {
        $nis = sanitize($_POST['nis'] ?? '');
        $nama = sanitize($_POST['nama'] ?? '');
        $kelas = sanitize($_POST['kelas'] ?? '');
        $jurusan = sanitize($_POST['jurusan'] ?? '');
        $no_hp = sanitize($_POST['no_hp'] ?? '');
        $alamat = sanitize($_POST['alamat'] ?? '');
        $id = (int)($_POST['id'] ?? 0);

        if ($nis === '' || $nama === '') {
            flash('error', 'NIS dan Nama wajib diisi.');
        } else {
            $chk = $conn->prepare('SELECT id FROM siswa WHERE nis=? AND id!=?');
            $chk->bind_param('si', $nis, $id);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                flash('error', 'NIS sudah terdaftar (duplikat).');
            } else {
                if ($action === 'tambah') {
                    $st = $conn->prepare('INSERT INTO siswa (nis,nama,kelas,jurusan,no_hp,alamat) VALUES (?,?,?,?,?,?)');
                    $st->bind_param('ssssss', $nis, $nama, $kelas, $jurusan, $no_hp, $alamat);
                    if ($st->execute()) {
                        $newId = $conn->insert_id;
                        $hash = password_hash('password123', PASSWORD_DEFAULT);
                        $role = 'siswa';
                        $u = $conn->prepare('INSERT INTO users (username,password,role,related_id) VALUES (?,?,?,?)');
                        $u->bind_param('sssi', $nis, $hash, $role, $newId);
                        $u->execute();
                        $u->close();
                        flash('success', 'Data siswa berhasil ditambahkan. Login: ' . $nis . ' / password123');
                    } else {
                        flash('error', 'Gagal menambah data.');
                    }
                    $st->close();
                } else {
                    // Ambil NIS lama untuk update username
                    $old = $conn->query("SELECT nis FROM siswa WHERE id=$id")->fetch_assoc();
                    $st = $conn->prepare('UPDATE siswa SET nis=?,nama=?,kelas=?,jurusan=?,no_hp=?,alamat=? WHERE id=?');
                    $st->bind_param('ssssssi', $nis, $nama, $kelas, $jurusan, $no_hp, $alamat, $id);
                    if ($st->execute()) {
                        // Sync username di tabel users agar siswa tetap bisa login
                        if ($old && $old['nis'] !== $nis) {
                            $uu = $conn->prepare("UPDATE users SET username=? WHERE related_id=? AND role='siswa'");
                            $uu->bind_param('si', $nis, $id);
                            $uu->execute();
                            $uu->close();
                        }
                        // Pastikan user login ada (jika belum)
                        $cekUser = $conn->query("SELECT id FROM users WHERE related_id=$id AND role='siswa'");
                        if ($cekUser->num_rows === 0) {
                            $hash = password_hash('password123', PASSWORD_DEFAULT);
                            $role = 'siswa';
                            $u = $conn->prepare('INSERT INTO users (username,password,role,related_id) VALUES (?,?,?,?)');
                            $u->bind_param('sssi', $nis, $hash, $role, $id);
                            $u->execute();
                            $u->close();
                        }
                        flash('success', 'Data berhasil diupdate.');
                    } else {
                        flash('error', 'Gagal update.');
                    }
                    $st->close();
                }
            }
            $chk->close();
        }
        redirect('admin/siswa.php');
    }
    if ($action === 'hapus') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM users WHERE related_id=$id AND role='siswa'");
        $conn->query("DELETE FROM siswa WHERE id=$id");
        flash('success', 'Data siswa dihapus.');
        redirect('admin/siswa.php');
    }
    // Reset password siswa
    if ($action === 'reset_password') {
        $id = (int)$_POST['id'];
        $hash = password_hash('password123', PASSWORD_DEFAULT);
        $st = $conn->prepare("UPDATE users SET password=? WHERE related_id=? AND role='siswa'");
        $st->bind_param('si', $hash, $id);
        $st->execute();
        $st->close();
        flash('success', 'Password direset ke: password123');
        redirect('admin/siswa.php');
    }
}

$search = sanitize($_GET['search'] ?? '');
$filter_kelas = sanitize($_GET['kelas'] ?? '');
$where = '1=1';
if ($search) $where .= " AND (s.nama LIKE '%$search%' OR s.nis LIKE '%$search%')";
if ($filter_kelas) $where .= " AND s.kelas='$filter_kelas'";

$siswa = $conn->query("SELECT s.*, u.username as login_user
    FROM siswa s
    LEFT JOIN users u ON u.related_id=s.id AND u.role='siswa'
    WHERE $where ORDER BY s.nama");
$kelas_list = $conn->query('SELECT DISTINCT kelas FROM siswa ORDER BY kelas');
?>

<style>
/* Perbaikan CSS halaman Data Siswa */
.siswa-table th { white-space: nowrap; }
.siswa-table td { vertical-align: middle !important; }
.siswa-actions { display: flex; flex-wrap: wrap; gap: 4px; align-items: center; }
.siswa-actions .btn { padding: 0.25rem 0.5rem; font-size: 0.8rem; line-height: 1.2; }
.siswa-actions form { display: inline; margin: 0; }
.login-hint {
    font-size: 0.75rem; color: #718096; background: #edf2f7;
    padding: 2px 8px; border-radius: 4px; display: inline-block; margin-top: 2px;
}
.modal .form-label { font-weight: 500; }
.modal .form-control, .modal .form-select { border-radius: 8px; }
.filter-card { border-radius: 12px; }
.table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
@media (max-width: 768px) {
    .siswa-actions { flex-direction: column; align-items: flex-start; }
}
</style>

<div class="card-custom filter-card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Cari (Nama / NIS)</label>
                <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Ketik nama atau NIS...">
            </div>
            <div class="col-md-3">
                <label class="form-label">Filter Kelas</label>
                <select name="kelas" class="form-select">
                    <option value="">Semua Kelas</option>
                    <?php while ($k = $kelas_list->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($k['kelas']) ?>" <?= $filter_kelas===$k['kelas']?'selected':'' ?>><?= htmlspecialchars($k['kelas']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Cari</button>
                <a href="<?= base_url('admin/siswa.php') ?>" class="btn btn-outline-secondary">Reset</a>
            </div>
            <div class="col-md-2 text-end">
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalTambah">
                    <i class="bi bi-plus-lg"></i> Tambah
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card-custom">
    <div class="card-body p-0">
        <div class="table-wrap">
            <table class="table table-custom table-hover mb-0 siswa-table">
                <thead>
                    <tr>
                        <th style="width:50px">No</th>
                        <th>NIS</th>
                        <th>Nama</th>
                        <th>Kelas</th>
                        <th>Jurusan</th>
                        <th>No. HP</th>
                        <th>Akun Login</th>
                        <th style="min-width:160px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php $no=1; while ($r = $siswa->fetch_assoc()): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><strong><?= htmlspecialchars($r['nis']) ?></strong></td>
                        <td><?= htmlspecialchars($r['nama']) ?></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($r['kelas']) ?></span></td>
                        <td><?= htmlspecialchars($r['jurusan']) ?></td>
                        <td><?= htmlspecialchars($r['no_hp'] ?? '-') ?></td>
                        <td>
                            <?php if ($r['login_user']): ?>
                                <span class="login-hint"><i class="bi bi-key"></i> <?= htmlspecialchars($r['login_user']) ?></span>
                            <?php else: ?>
                                <span class="badge bg-danger">Belum ada akun</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="siswa-actions">
                                <button class="btn btn-sm btn-info text-white" data-bs-toggle="modal" data-bs-target="#detail<?= $r['id'] ?>" title="Detail"><i class="bi bi-eye"></i></button>
                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#edit<?= $r['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></button>
                                <form method="POST" onsubmit="return confirm('Reset password ke password123?')">
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Reset Password"><i class="bi bi-key"></i></button>
                                </form>
                                <form method="POST" onsubmit="return confirm('Yakin hapus data ini?')">
                                    <input type="hidden" name="action" value="hapus">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>

                    <!-- Modal Detail -->
                    <div class="modal fade" id="detail<?= $r['id'] ?>" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title"><i class="bi bi-person-badge"></i> Detail Siswa</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <table class="table table-borderless mb-0">
                                        <tr><th width="130">NIS</th><td><?= htmlspecialchars($r['nis']) ?></td></tr>
                                        <tr><th>Nama</th><td><?= htmlspecialchars($r['nama']) ?></td></tr>
                                        <tr><th>Kelas</th><td><?= htmlspecialchars($r['kelas']) ?></td></tr>
                                        <tr><th>Jurusan</th><td><?= htmlspecialchars($r['jurusan']) ?></td></tr>
                                        <tr><th>No. HP</th><td><?= htmlspecialchars($r['no_hp'] ?? '-') ?></td></tr>
                                        <tr><th>Alamat</th><td><?= htmlspecialchars($r['alamat'] ?? '-') ?></td></tr>
                                        <tr><th>Username Login</th><td><code><?= htmlspecialchars($r['login_user'] ?? $r['nis']) ?></code></td></tr>
                                        <tr><th>Password</th><td><code>password123</code> (default)</td></tr>
                                        <?php $p=getPersentaseKehadiran($r['id']); $st=hitungStatusKehadiran($p); ?>
                                        <tr><th>Kehadiran</th><td><strong><?= $p ?>%</strong> — <?= $st ?></td></tr>
                                    </table>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Edit -->
                    <div class="modal fade" id="edit<?= $r['id'] ?>" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <form method="POST">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Siswa</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                        <div class="mb-3">
                                            <label class="form-label">NIS *</label>
                                            <input type="text" name="nis" class="form-control" value="<?= htmlspecialchars($r['nis']) ?>" required>
                                            <div class="form-text">NIS juga dipakai sebagai username login</div>
                                        </div>
                                        <div class="mb-3"><label class="form-label">Nama *</label><input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($r['nama']) ?>" required></div>
                                        <div class="mb-3"><label class="form-label">Kelas</label><input type="text" name="kelas" class="form-control" value="<?= htmlspecialchars($r['kelas']) ?>"></div>
                                        <div class="mb-3"><label class="form-label">Jurusan</label><input type="text" name="jurusan" class="form-control" value="<?= htmlspecialchars($r['jurusan']) ?>"></div>
                                        <div class="mb-3"><label class="form-label">No. HP</label><input type="text" name="no_hp" class="form-control" value="<?= htmlspecialchars($r['no_hp'] ?? '') ?>"></div>
                                        <div class="mb-3"><label class="form-label">Alamat</label><textarea name="alamat" class="form-control" rows="2"><?= htmlspecialchars($r['alamat'] ?? '') ?></textarea></div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                        <button type="submit" class="btn btn-primary">Simpan</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
                <?php if ($no===1): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5">Tidak ada data siswa</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="alert alert-info mt-3 mb-0">
    <i class="bi bi-info-circle"></i>
    <strong>Login Siswa:</strong> Username = <strong>NIS</strong>, Password default = <strong>password123</strong>.
    Semua siswa yang ada di data sudah punya akun login. Gunakan tombol <i class="bi bi-key"></i> untuk reset password.
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-person-plus"></i> Tambah Siswa</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="tambah">
                    <div class="mb-3">
                        <label class="form-label">NIS *</label>
                        <input type="text" name="nis" class="form-control" required placeholder="Contoh: 2024026">
                        <div class="form-text">NIS otomatis jadi username login (password: password123)</div>
                    </div>
                    <div class="mb-3"><label class="form-label">Nama *</label><input type="text" name="nama" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Kelas</label><input type="text" name="kelas" class="form-control" value="XII RPL 1"></div>
                    <div class="mb-3"><label class="form-label">Jurusan</label><input type="text" name="jurusan" class="form-control" value="Rekayasa Perangkat Lunak"></div>
                    <div class="mb-3"><label class="form-label">No. HP</label><input type="text" name="no_hp" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Alamat</label><textarea name="alamat" class="form-control" rows="2"></textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
