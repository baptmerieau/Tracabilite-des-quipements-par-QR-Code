<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['auth_ok']) || !$_SESSION['auth_ok']) {
    header('Location: index.php');
    exit;
}

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$success = '';
$error   = '';

// --- Ajout utilisateur ---
if (isset($_POST['add_user'])) {
    $nom    = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $service = trim($_POST['service'] ?? '');
    if ($nom === '' || $prenom === '') {
        $error = 'Le nom et le prénom sont obligatoires.';
    } else {
        try {
            $pdo->prepare("INSERT INTO utilisateurs (nom, prenom, service) VALUES (?, ?, ?)")->execute([$nom, $prenom, $service]);
            $success = '✅ Utilisateur ' . $prenom . ' ' . $nom . ' ajouté avec succès.';
        } catch (PDOException $e) {
            $error = '❌ Erreur : ' . $e->getMessage();
        }
    }
}

// --- Suppression utilisateur ---
if (isset($_POST['delete_user'])) {
    $uid = (int)$_POST['user_id'];
    try {
        $pdo->prepare("UPDATE equipements SET utilisateur_id = NULL, date_attribution = NULL WHERE utilisateur_id = ?")->execute([$uid]);
        $pdo->prepare("DELETE FROM utilisateurs WHERE id = ?")->execute([$uid]);
        $success = '✅ Utilisateur supprimé.';
    } catch (PDOException $e) {
        $error = '❌ Erreur : ' . $e->getMessage();
    }
}

// --- Ajout équipement ---
if (isset($_POST['add_equip'])) {
    $nom     = trim($_POST['equip_nom'] ?? '');
    $type    = trim($_POST['equip_type'] ?? '');
    $qr_code = trim($_POST['equip_qr'] ?? '');
    $statut  = 'Disponible';
    if ($nom === '' || $qr_code === '') {
        $error = 'Le nom et le QR code sont obligatoires.';
    } else {
        try {
            $check = $pdo->prepare("SELECT id FROM equipements WHERE qr_code = ?");
            $check->execute([$qr_code]);
            if ($check->fetch()) {
                $error = '❌ Ce QR code existe déjà dans la base.';
            } else {
                $pdo->prepare("INSERT INTO equipements (nom, type, qr_code, statut) VALUES (?, ?, ?, ?)")->execute([$nom, $type, $qr_code, $statut]);
                $success = '✅ Équipement "' . $nom . '" ajouté avec le QR code "' . $qr_code . '".';
            }
        } catch (PDOException $e) {
            $error = '❌ Erreur : ' . $e->getMessage();
        }
    }
}

// --- Suppression équipement ---
if (isset($_POST['delete_equip'])) {
    $eid = (int)$_POST['equip_id'];
    try {
        $pdo->prepare("DELETE FROM movements WHERE equipement_id = ?")->execute([$eid]);
        $pdo->prepare("DELETE FROM scan_pending WHERE qr_code = (SELECT qr_code FROM equipements WHERE id = ?)")->execute([$eid]);
        $pdo->prepare("DELETE FROM equipements WHERE id = ?")->execute([$eid]);
        $success = '✅ Équipement supprimé.';
    } catch (PDOException $e) {
        $error = '❌ Erreur : ' . $e->getMessage();
    }
}

// --- Chargement données ---
$utilisateurs = $pdo->query("SELECT * FROM utilisateurs ORDER BY nom ASC")->fetchAll(PDO::FETCH_ASSOC);
$equipements  = $pdo->query("SELECT e.*, u.nom AS u_nom, u.prenom AS u_prenom FROM equipements e LEFT JOIN utilisateurs u ON u.id = e.utilisateur_id ORDER BY e.nom ASC")->fetchAll(PDO::FETCH_ASSOC);

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$baseUrl  = $protocol . '://' . $host . '/tracabilite/index.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root{--bg1:#070b18;--bg2:#111827;--card:rgba(15,23,42,.78);--line:rgba(255,255,255,.10);--txt:#e5e7eb;--muted:#94a3b8;}
        body{min-height:100vh;color:var(--txt);background:radial-gradient(circle at 10% 10%,rgba(59,130,246,.28),transparent 24%),radial-gradient(circle at 88% 18%,rgba(168,85,247,.22),transparent 24%),linear-gradient(135deg,var(--bg1),var(--bg2));font-family:system-ui,sans-serif;}
        .glass{background:var(--card);backdrop-filter:blur(18px);border:1px solid var(--line);border-radius:28px;box-shadow:0 24px 60px rgba(0,0,0,.35);}
        .section-title{font-weight:900;letter-spacing:-.02em;}
        .muted{color:var(--muted);}
        .form-control,.form-select{background:rgba(255,255,255,.08)!important;color:#fff!important;border:1px solid rgba(255,255,255,.14)!important;border-radius:14px;padding:12px 16px;}
        .form-control::placeholder{color:#cbd5e1}
        .form-control:focus,.form-select:focus{box-shadow:0 0 0 3px rgba(99,102,241,.35)!important;border-color:#6366f1!important;}
        .form-select option{background:#1e293b;color:#fff;}
        .btn-add{border:none;border-radius:14px;padding:12px 20px;font-weight:800;background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff;}
        .btn-del{border:none;border-radius:10px;padding:5px 12px;font-weight:700;font-size:.8rem;background:rgba(239,68,68,.2);border:1px solid rgba(239,68,68,.3);color:#fca5a5;cursor:pointer;}
        .label{font-size:.8rem;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);}
        .table thead th{background:rgba(255,255,255,.08)!important;color:var(--txt);border-color:rgba(255,255,255,.08);}
        .table tbody td{background:rgba(255,255,255,.02)!important;color:var(--txt);border-color:rgba(255,255,255,.06);}
        .status-ok{background:rgba(34,197,94,.16);color:#86efac;padding:4px 10px;border-radius:999px;font-weight:700;font-size:.8rem;}
        .status-busy{background:rgba(245,158,11,.16);color:#fdba74;padding:4px 10px;border-radius:999px;font-weight:700;font-size:.8rem;}
        .back-btn{border-radius:14px;padding:10px 20px;font-weight:700;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.10);color:#fff;text-decoration:none;display:inline-flex;align-items:center;gap:8px;}
        .nav-pills .nav-link{color:var(--muted);border-radius:14px;padding:10px 20px;font-weight:700;}
        .nav-pills .nav-link.active{background:linear-gradient(135deg,#2563eb,#7c3aed);color:#fff;}
        .qr-preview{border-radius:14px;background:#fff;padding:8px;}
        .badge-admin{background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#fca5a5;padding:6px 12px;border-radius:999px;font-size:.8rem;font-weight:700;}
    </style>
</head>
<body>
<div class="container py-4 py-lg-5">

    <!-- Header -->
    <div class="glass p-4 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <a href="index.php" class="back-btn mb-3 d-inline-flex"><i class="bi bi-arrow-left"></i>Retour au tableau de bord</a>
            <h1 class="section-title display-6 mb-1"><i class="bi bi-shield-lock me-2"></i>Panneau d'administration</h1>
            <div class="muted">Gestion des utilisateurs et des équipements — accès admin uniquement</div>
        </div>
        <span class="badge-admin"><i class="bi bi-lock-fill me-1"></i>Admin only</span>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success border-0 rounded-4 mb-4"><?php echo h($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger border-0 rounded-4 mb-4"><?php echo h($error); ?></div>
    <?php endif; ?>

    <!-- Tabs -->
    <ul class="nav nav-pills mb-4 gap-2" id="adminTabs">
        <li class="nav-item"><a class="nav-link active" href="#" onclick="showTab('users')"><i class="bi bi-people me-2"></i>Utilisateurs</a></li>
        <li class="nav-item"><a class="nav-link" href="#" onclick="showTab('equips')"><i class="bi bi-laptop me-2"></i>Équipements</a></li>
    </ul>

    <!-- TAB UTILISATEURS -->
    <div id="tab-users">
        <div class="row g-4">
            <!-- Formulaire ajout utilisateur -->
            <div class="col-lg-5">
                <div class="glass p-4 h-100">
                    <div class="section-title mb-3"><i class="bi bi-person-plus me-2"></i>Ajouter un utilisateur</div>
                    <form method="post">
                        <div class="mb-3">
                            <div class="label mb-1">Prénom *</div>
                            <input type="text" name="prenom" class="form-control" placeholder="ex: Jean" required>
                        </div>
                        <div class="mb-3">
                            <div class="label mb-1">Nom *</div>
                            <input type="text" name="nom" class="form-control" placeholder="ex: Dupont" required>
                        </div>
                        <div class="mb-4">
                            <div class="label mb-1">Service</div>
                            <input type="text" name="service" class="form-control" placeholder="ex: Informatique, Production...">
                        </div>
                        <button type="submit" name="add_user" value="1" class="btn btn-add w-100">
                            <i class="bi bi-plus-circle me-2"></i>Ajouter l'utilisateur
                        </button>
                    </form>
                </div>
            </div>

            <!-- Liste utilisateurs -->
            <div class="col-lg-7">
                <div class="glass p-4 h-100">
                    <div class="section-title mb-3"><i class="bi bi-people me-2"></i>Utilisateurs enregistrés (<?php echo count($utilisateurs); ?>)</div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead><tr><th>#</th><th>Prénom Nom</th><th>Service</th><th>Créé le</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($utilisateurs as $u): ?>
                                <tr>
                                    <td class="muted small"><?php echo $u['id']; ?></td>
                                    <td><strong><?php echo h($u['prenom'] . ' ' . $u['nom']); ?></strong></td>
                                    <td class="muted"><?php echo h($u['service'] ?? '—'); ?></td>
                                    <td class="muted small"><?php echo isset($u['created_at']) ? date('d/m/Y', strtotime($u['created_at'])) : '—'; ?></td>
                                    <td>
                                        <form method="post" onsubmit="return confirm('Supprimer cet utilisateur ?')">
                                            <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                            <button type="submit" name="delete_user" value="1" class="btn-del">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($utilisateurs)): ?>
                                <tr><td colspan="5" class="text-center muted py-3">Aucun utilisateur enregistré.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB ÉQUIPEMENTS -->
    <div id="tab-equips" style="display:none">
        <div class="row g-4">
            <!-- Formulaire ajout équipement -->
            <div class="col-lg-5">
                <div class="glass p-4 h-100">
                    <div class="section-title mb-3"><i class="bi bi-plus-square me-2"></i>Ajouter un équipement</div>
                    <form method="post" id="formEquip">
                        <div class="mb-3">
                            <div class="label mb-1">Nom de l'équipement *</div>
                            <input type="text" name="equip_nom" id="equipNom" class="form-control" placeholder="ex: Laptop Dell N°12" required oninput="updateQrPreview()">
                        </div>
                        <div class="mb-3">
                            <div class="label mb-1">Type</div>
                            <select name="equip_type" class="form-select">
                                <option value="">— Sélectionner —</option>
                                <option value="Laptop">Laptop</option>
                                <option value="Tablette">Tablette</option>
                                <option value="Téléphone">Téléphone</option>
                                <option value="Casque">Casque</option>
                                <option value="Câble">Câble</option>
                                <option value="Autre">Autre</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <div class="label mb-1">QR Code (identifiant unique) *</div>
                            <input type="text" name="equip_qr" id="equipQr" class="form-control" placeholder="ex: QR001, QR002..." required oninput="updateQrPreview()">
                            <div class="muted small mt-1">Ce texte sera encodé dans le QR code. Doit être unique.</div>
                        </div>

                        <!-- Prévisualisation QR -->
                        <div class="mb-4 text-center" id="qrPreviewBox" style="display:none">
                            <div class="label mb-2">Prévisualisation du QR code</div>
                            <img id="qrPreviewImg" src="" alt="QR" class="qr-preview img-fluid" style="max-width:160px;">
                            <div class="muted small mt-2" id="qrPreviewUrl"></div>
                        </div>

                        <button type="submit" name="add_equip" value="1" class="btn btn-add w-100">
                            <i class="bi bi-plus-circle me-2"></i>Ajouter l'équipement
                        </button>
                    </form>
                </div>
            </div>

            <!-- Liste équipements -->
            <div class="col-lg-7">
                <div class="glass p-4 h-100">
                    <div class="section-title mb-3"><i class="bi bi-laptop me-2"></i>Équipements enregistrés (<?php echo count($equipements); ?>)</div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead><tr><th>Nom</th><th>Type</th><th>QR Code</th><th>Statut</th><th>Attribué à</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($equipements as $row): ?>
                                <tr>
                                    <td><strong><?php echo h($row['nom']); ?></strong></td>
                                    <td class="muted small"><?php echo h($row['type'] ?? '—'); ?></td>
                                    <td><code style="color:#67e8f9"><?php echo h($row['qr_code']); ?></code></td>
                                    <td>
                                        <span class="<?php echo ($row['statut'] === 'Disponible') ? 'status-ok' : 'status-busy'; ?>">
                                            <?php echo h($row['statut']); ?>
                                        </span>
                                    </td>
                                    <td class="muted small">
                                        <?php echo !empty($row['u_nom']) ? h(($row['u_prenom'] ?? '') . ' ' . $row['u_nom']) : '—'; ?>
                                    </td>
                                    <td>
                                        <form method="post" onsubmit="return confirm('Supprimer cet équipement et tout son historique ?')">
                                            <input type="hidden" name="equip_id" value="<?php echo (int)$row['id']; ?>">
                                            <button type="submit" name="delete_equip" value="1" class="btn-del">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($equipements)): ?>
                                <tr><td colspan="6" class="text-center muted py-3">Aucun équipement enregistré.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center muted small pb-2 mt-4">Panneau admin — accès restreint</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const BASE_URL = "<?php echo addslashes($baseUrl); ?>";

function showTab(tab) {
    document.getElementById('tab-users').style.display  = tab === 'users'  ? '' : 'none';
    document.getElementById('tab-equips').style.display = tab === 'equips' ? '' : 'none';
    document.querySelectorAll('.nav-link').forEach((el, i) => {
        el.classList.toggle('active', (i === 0 && tab === 'users') || (i === 1 && tab === 'equips'));
    });
}

function updateQrPreview() {
    const qr  = document.getElementById('equipQr').value.trim();
    const nom = document.getElementById('equipNom').value.trim();
    const box = document.getElementById('qrPreviewBox');
    if (qr.length < 1) { box.style.display = 'none'; return; }
    const url = BASE_URL + '?qr=' + encodeURIComponent(qr);
    document.getElementById('qrPreviewImg').src = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' + encodeURIComponent(url);
    document.getElementById('qrPreviewUrl').textContent = url;
    box.style.display = '';
}
</script>
</body>
</html>
