<?php
require_once '../config.php';
check_login_admin();

$id_jadwal = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id_jadwal) {
    alert('ID Jadwal tidak valid!', 'danger');
    redirect('admin/jadwal_tes.php');
}

// Get jadwal info
$jadwal = mysqli_fetch_assoc(mysqli_query($conn, "SELECT jt.*, ks.nama_kategori FROM jadwal_tes jt LEFT JOIN kategori_soal ks ON jt.id_kategori = ks.id_kategori WHERE jt.id_jadwal = $id_jadwal"));

if (!$jadwal) {
    alert('Jadwal tidak ditemukan!', 'danger');
    redirect('admin/jadwal_tes.php');
}

// Handle POST request - Save soal
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add_soal') {
            $id_soal = (int)$_POST['id_soal'];
            $nomor_urut = (int)$_POST['nomor_urut'];
            
            // Check if soal already exists in this jadwal
            $check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM soal_tes WHERE id_jadwal = $id_jadwal AND id_soal = $id_soal"));
            if ($check['total'] > 0) {
                alert('Soal sudah ada di jadwal ini!', 'warning');
            } else {
                $query = "INSERT INTO soal_tes (id_jadwal, id_soal, nomor_urut) VALUES ($id_jadwal, $id_soal, $nomor_urut)";
                if (mysqli_query($conn, $query)) {
                    alert('Soal berhasil ditambahkan!', 'success');
                } else {
                    alert('Gagal menambahkan soal: ' . mysqli_error($conn), 'danger');
                }
            }
        } elseif ($_POST['action'] == 'add_random') {
            $jumlah = (int)$_POST['jumlah'];
            $id_kategori = $jadwal['id_kategori'];
            
            // Get random soal from bank_soal
            $existing = mysqli_query($conn, "SELECT id_soal FROM soal_tes WHERE id_jadwal = $id_jadwal");
            $exclude_ids = [];
            while ($row = mysqli_fetch_assoc($existing)) {
                $exclude_ids[] = $row['id_soal'];
            }
            $exclude_clause = count($exclude_ids) > 0 ? "AND id_soal NOT IN (" . implode(',', $exclude_ids) . ")" : "";
            
            $random_soal = mysqli_query($conn, "SELECT id_soal FROM bank_soal WHERE id_kategori = $id_kategori $exclude_clause ORDER BY RAND() LIMIT $jumlah");
            
            // Get current max nomor_urut
            $max_urut = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(MAX(nomor_urut), 0) as max_urut FROM soal_tes WHERE id_jadwal = $id_jadwal"));
            $nomor_urut = $max_urut['max_urut'] + 1;
            
            $added = 0;
            while ($soal = mysqli_fetch_assoc($random_soal)) {
                $query = "INSERT INTO soal_tes (id_jadwal, id_soal, nomor_urut) VALUES ($id_jadwal, {$soal['id_soal']}, $nomor_urut)";
                if (mysqli_query($conn, $query)) {
                    $added++;
                    $nomor_urut++;
                }
            }
            alert("$added soal berhasil ditambahkan secara acak!", 'success');
        }
    }
    redirect("admin/jadwal_tes_soal.php?id=$id_jadwal");
}

// Handle delete soal from jadwal
if (isset($_GET['delete_soal'])) {
    $id_soal_tes = (int)$_GET['delete_soal'];
    if (mysqli_query($conn, "DELETE FROM soal_tes WHERE id_soal_tes = $id_soal_tes AND id_jadwal = $id_jadwal")) {
        alert('Soal berhasil dihapus dari jadwal!', 'success');
    } else {
        alert('Gagal menghapus soal: ' . mysqli_error($conn), 'danger');
    }
    redirect("admin/jadwal_tes_soal.php?id=$id_jadwal");
}

// Get soal yang sudah ada di jadwal
$soal_jadwal = mysqli_query($conn, "SELECT st.*, bs.pertanyaan, bs.pilihan_a, bs.pilihan_b, bs.pilihan_c, bs.pilihan_d, bs.pilihan_e 
    FROM soal_tes st 
    JOIN bank_soal bs ON st.id_soal = bs.id_soal 
    WHERE st.id_jadwal = $id_jadwal 
    ORDER BY st.nomor_urut ASC");

// Get soal yang belum ada di jadwal (untuk dropdown)
$soal_available = mysqli_query($conn, "SELECT bs.* FROM bank_soal bs 
    WHERE bs.id_kategori = {$jadwal['id_kategori']} 
    AND bs.id_soal NOT IN (SELECT id_soal FROM soal_tes WHERE id_jadwal = $id_jadwal)
    ORDER BY bs.id_soal DESC");

// Count available soal
$count_available = mysqli_num_rows($soal_available);

// Get current max nomor_urut
$max_urut = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(MAX(nomor_urut), 0) as max_urut FROM soal_tes WHERE id_jadwal = $id_jadwal"));

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-file-text"></i> Kelola Soal - <?php echo $jadwal['nama_tes']; ?></h2>
    <a href="jadwal_tes.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<?php show_alert(); ?>

<div class="row">
    <div class="col-md-4">
        <!-- Info Jadwal -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Info Jadwal</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr><th>Nama Tes:</th><td><?php echo $jadwal['nama_tes']; ?></td></tr>
                    <tr><th>Kategori:</th><td><?php echo $jadwal['nama_kategori']; ?></td></tr>
                    <tr><th>Jumlah Soal (Target):</th><td><?php echo $jadwal['jumlah_soal']; ?> soal</td></tr>
                    <tr><th>Soal Terpasang:</th><td><strong class="text-primary"><?php echo mysqli_num_rows($soal_jadwal); ?></strong> soal</td></tr>
                    <tr><th>Durasi:</th><td><?php echo $jadwal['durasi']; ?> menit</td></tr>
                    <tr><th>Status:</th><td>
                        <?php if($jadwal['status'] == 'aktif'): ?>
                            <span class="badge bg-success">Aktif</span>
                        <?php else: ?>
                            <span class="badge bg-warning"><?php echo $jadwal['status']; ?></span>
                        <?php endif; ?>
                    </td></tr>
                </table>
            </div>
        </div>

        <!-- Tambah Soal Manual -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Tambah Soal Manual</h5>
            </div>
            <div class="card-body">
                <?php if ($count_available > 0): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="add_soal">
                    <div class="mb-3">
                        <label class="form-label">Pilih Soal</label>
                        <select name="id_soal" class="form-select" required>
                            <option value="">-- Pilih Soal --</option>
                            <?php 
                            mysqli_data_seek($soal_available, 0);
                            while($soal = mysqli_fetch_assoc($soal_available)): 
                            ?>
                                <option value="<?php echo $soal['id_soal']; ?>">
                                    #<?php echo $soal['id_soal']; ?> - <?php echo substr(strip_tags($soal['pertanyaan']), 0, 50); ?>...
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nomor Urut</label>
                        <input type="number" name="nomor_urut" class="form-control" value="<?php echo $max_urut['max_urut'] + 1; ?>" min="1" required>
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-plus"></i> Tambah Soal
                    </button>
                </form>
                <?php else: ?>
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-exclamation-triangle"></i> Tidak ada soal tersedia di kategori ini.
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tambah Soal Random -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-shuffle"></i> Tambah Soal Acak</h5>
            </div>
            <div class="card-body">
                <?php if ($count_available > 0): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="add_random">
                    <div class="mb-3">
                        <label class="form-label">Jumlah Soal</label>
                        <input type="number" name="jumlah" class="form-control" 
                               value="<?php echo min($count_available, $jadwal['jumlah_soal'] - mysqli_num_rows($soal_jadwal)); ?>" 
                               min="1" max="<?php echo $count_available; ?>" required>
                        <small class="text-muted">Tersedia: <?php echo $count_available; ?> soal</small>
                    </div>
                    <button type="submit" class="btn btn-info w-100">
                        <i class="bi bi-shuffle"></i> Tambah Acak
                    </button>
                </form>
                <?php else: ?>
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-exclamation-triangle"></i> Tidak ada soal tersedia di kategori ini.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <!-- Daftar Soal di Jadwal -->
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-list-ol"></i> Daftar Soal Terpasang (<?php echo mysqli_num_rows($soal_jadwal); ?> soal)</h5>
            </div>
            <div class="card-body">
                <?php if (mysqli_num_rows($soal_jadwal) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="60">No</th>
                                <th>Pertanyaan</th>
                                <th width="100">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            mysqli_data_seek($soal_jadwal, 0);
                            while($row = mysqli_fetch_assoc($soal_jadwal)): 
                            ?>
                            <tr>
                                <td><span class="badge bg-primary"><?php echo $row['nomor_urut']; ?></span></td>
                                <td>
                                    <strong><?php echo substr(strip_tags($row['pertanyaan']), 0, 100); ?>...</strong>
                                    <div class="mt-1">
                                        <small class="text-muted">
                                            A: <?php echo substr(strip_tags($row['pilihan_a']), 0, 30); ?>... |
                                            B: <?php echo substr(strip_tags($row['pilihan_b']), 0, 30); ?>...
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <a href="jadwal_tes_soal.php?id=<?php echo $id_jadwal; ?>&delete_soal=<?php echo $row['id_soal_tes']; ?>" 
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Hapus soal ini dari jadwal?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info mb-0">
                    <i class="bi bi-info-circle"></i> Belum ada soal yang ditambahkan ke jadwal ini. 
                    Gunakan form di samping untuk menambahkan soal.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
