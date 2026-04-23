<?php
session_start();
require_once 'db.php';

// Redirige si pas connecté au dashboard
if (!isset($_SESSION['auth_ok']) || !$_SESSION['auth_ok']) {
    header('Location: index.php');
    exit;
}

// Mot de passe admin secondaire
$adminSecret = 'Sodiaal01';

if (!isset($_SESSION['admin_ok'])) $_SESSION['admin_ok'] = false;

if (isset($_POST['admin_pass'])) {
    if (trim($_POST['admin_pass']) === $adminSecret) {
        $_SESSION['admin_ok'] = true;
    } else {
        $_SESSION['admin_error'] = '❌ Mot de passe administrateur incorrect.';
    }
    header('Location: admin.php');
    exit;
}

if (isset($_GET['lock'])) {
    $_SESSION['admin_ok'] = false;
    header('Location: admin.php');
    exit;
}

if (!$_SESSION['admin_ok']) { ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accès Administration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root{--bg1:#050816;--bg2:#111827;--card:rgba(15,23,42,.78);--line:rgba(255,255,255,.12);--txt:#e5e7eb;--muted:#94a3b8;}
        body{min-height:100vh;margin:0;color:var(--txt);background:radial-gradient(circle at 20% 20%,rgba(239,68,68,.18),transparent 25%),radial-gradient(circle at 80% 30%,rgba(168,85,247,.22),transparent 24%),linear-gradient(135deg,var(--bg1),var(--bg2));display:flex;align-items:center;justify-content:center;font-family:system-ui,sans-serif;}
        .cardx{width:min(500px,92vw);background:var(--card);backdrop-filter:blur(18px);border:1px solid var(--line);border-radius:30px;box-shadow:0 30px 80px rgba(0,0,0,.45);padding:42px;}
        .title{font-weight:900;letter-spacing:-.04em;}
        .muted{color:var(--muted)}
        .form-control{background:rgba(255,255,255,.08)!important;color:#fff!important;border:1px solid rgba(255,255,255,.14)!important;border-radius:16px;padding:14px 16px;box-shadow:none!important;}
        .form-control::placeholder{color:#cbd5e1}
        input:-webkit-autofill,input:-webkit-autofill:hover,input:-webkit-autofill:focus{-webkit-box-shadow:0 0 0 1000px rgba(255,255,255,.08) inset !important;-webkit-text-fill-color:#fff !important;}
        .btn-unlock{border:none;border-radius:16px;padding:14px 18px;font-weight:800;background:linear-gradient(135deg,#dc2626,#9333ea);width:100%;}
        .shield{font-size:4rem;color:#fca5a5;text-align:center;display:block;margin-bottom:16px;animation:pulse 2s ease-in-out infinite;}
        @keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.08)}}
        .badge-soft{display:inline-flex;gap:8px;align-items:center;background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.25);padding:8px 14px;border-radius:999px;color:#fca5a5;font-weight:700;font-size:.85rem;}
        .loader-line{height:6px;border-radius:999px;background:rgba(255,255,255,.08);overflow:hidden;margin-bottom:20px;}
        .loader-line span{display:block;height:100%;background:linear-gradient(90deg,#dc2626,#9333ea,#dc2626);animation:bar 2.2s ease-in-out infinite;}
        @keyframes bar{0%{width:0%}50%{width:100%}100%{width:0%}}
    </style>
</head>
<body>
<div class="cardx text-center">
    <span class="shield"><i class="bi bi-shield-lock-fill"></i></span>
    <div class="badge-soft mx-auto mb-3 d-inline-flex"><i class="bi bi-lock-fill"></i>Zone restreinte</div>
    <h1 class="title h3 mb-2">Accès Administration</h1>
    <p class="muted mb-4">Ce panneau nécessite un second mot de passe administrateur.</p>

    <?php if (!empty($_SESSION['admin_error'])): ?>
        <div class="alert alert-danger border-0 rounded-4 mb-3">
            <?php echo htmlspecialchars($_SESSION['admin_error']); $_SESSION['admin_error'] = ''; ?>
        </div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
        <div class="mb-3 text-start">
            <label class="form-label fw-bold">Mot de passe admin</label>
            <input type="password" name="admin_pass" class="form-control" placeholder="Mot de passe administrateur" required autofocus readonly onfocus="this.removeAttribute('readonly');">
        </div>
        <div class="loader-line"><span></span></div>
        <button type="submit" class="btn btn-danger btn-unlock">
            <i class="bi bi-unlock-fill me-2"></i>Accéder au panneau admin
        </button>
        <div class="mt-3">
            <a href="index.php" class="text-decoration-none small" style="color:var(--muted)">
                <i class="bi bi-arrow-left me-1"></i>Retour au tableau de bord
            </a>
        </div>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php exit; }

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$success = '';
$error   = '';

// --- Ajout utilisateur ---
if (isset($_POST['add_user'])) {
    $nom     = trim($_POST['nom'] ?? '');
    $prenom  = trim($_POST['prenom'] ?? '');
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
    if ($nom === '' || $qr_code === '') {
        $error = 'Le nom et le QR code sont obligatoires.';
    } else {
        try {
            $check = $pdo->prepare("SELECT id FROM equipements WHERE qr_code = ?");
            $check->execute([$qr_code]);
            if ($check->fetch()) {
                $error = '❌ Ce QR code existe déjà dans la base.';
            } else {
                $pdo->prepare("INSERT INTO equipements (nom, type, qr_code, statut) VALUES (?, ?, ?, 'Disponible')")->execute([$nom, $type, $qr_code]);
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
        $qrStmt = $pdo->prepare("SELECT qr_code FROM equipements WHERE id = ?");
        $qrStmt->execute([$eid]);
        $qrRow = $qrStmt->fetch(PDO::FETCH_ASSOC);
        if ($qrRow) {
            $pdo->prepare("DELETE FROM movements WHERE equipement_id = ?")->execute([$eid]);
            $pdo->prepare("DELETE FROM scan_pending WHERE qr_code = ?")->execute([$qrRow['qr_code']]);
            $pdo->prepare("DELETE FROM equipements WHERE id = ?")->execute([$eid]);
        }
        $success = '✅ Équipement supprimé.';
    } catch (PDOException $e) {
        $error = '❌ Erreur : ' . $e->getMessage();
    }
}

// --- Chargement données ---
$utilisateurs = $pdo->query("SELECT * FROM utilisateurs ORDER BY nom ASC")->fetchAll(PDO::FETCH_ASSOC);
$equipements  = $pdo->query("
    SELECT e.*, u.nom AS u_nom, u.prenom AS u_prenom
    FROM equipements e
    LEFT JOIN utilisateurs u ON u.id = e.utilisateur_id
    ORDER BY e.nom ASC
")->fetchAll(PDO::FETCH_ASSOC);

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
        body{min-height:100vh;color:var(--txt);background:radial-gradient(circle at 10% 10%,rgba(239,68,68,.20),transparent 24%),radial-gradient(circle at 88% 18%,rgba(168,85,247,.22),transparent 24%),linear-gradient(135deg,var(--bg1),var(--bg2));font-family:system-ui,sans-serif;}
        .glass{background:var(--card);backdrop-filter:blur(18px);border:1px solid var(--line);border-radius:28px;box-shadow:0 24px 60px rgba(0,0,0,.35);}
        .section-title{font-weight:900;letter-spacing:-.02em;}
        .muted{color:var(--muted);}
        .form-control,.form-select{background:rgba(255,255,255,.08)!important;color:#fff!important;border:1px solid rgba(255,255,255,.14)!important;border-radius:14px;padding:12px 16px;}
        .form-control::placeholder{color:#cbd5e1}
        .form-control:focus,.form-select:focus{box-shadow:0 0 0 3px rgba(239,68,68,.25)!important;border-color:#dc2626!important;}
        .form-select option{background:#1e293b;color:#fff;}
        .btn-add{border:none;border-radius:14px;padding:12px 20px;font-weight:800;background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff;width:100%;}
        .btn-del{border:none;border-radius:10px;padding:5px 12px;font-weight:700;font-size:.8rem;background:rgba(239,68,68,.2);border:1px solid rgba(239,68,68,.3);color:#fca5a5;cursor:pointer;}
        .label{font-size:.8rem;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);}
        .table thead th{background:rgba(255,255,255,.08)!important;color:var(--txt);border-color:rgba(255,255,255,.08);}
        .table tbody td{background:rgba(255,255,255,.02)!important;color:var(--txt);border-color:rgba(255,255,255,.06);}
        .status-ok{background:rgba(34,197,94,.16);color:#86efac;padding:4px 10px;border-radius:999px;font-weight:700;font-size:.8rem;display:inline-block;}
        .status-busy{background:rgba(245,158,11,.16);color:#fdba74;padding:4px 10px;border-radius:999px;font-weight:700;font-size:.8rem;display:inline-block;}
        .back-btn{border-radius:14px;padding:10px 20px;font-weight:700;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.10);color:#fff;text-decoration:none;display:inline-flex;align-items:center;gap:8px;}
        .lock-btn{border-radius:14px;padding:10px 20px;font-weight:700;background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.35);color:#fca5a5;text-decoration:none;display:inline-flex;align-items:center;gap:8px;}
        .nav-pills .nav-link{color:var(--muted);border-radius:14px;padding:10px 20px;font-weight:700;}
        .nav-pills .nav-link.active{background:linear-gradient(135deg,#dc2626,#9333ea);color:#fff;}
        .badge-admin{background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#fca5a5;padding:6px 14px;border-radius:999px;font-size:.82rem;font-weight:700;display:inline-flex;align-items:center;gap:6px;}
        .qr-preview{border-radius:14px;background:#fff;padding:8px;max-width:160px;}
    </style>
</head>
<body>
<div class="container py-4 py-lg-5">

    <!-- Header -->
    <div class="glass p-4 mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <div class="d-flex gap-2 mb-3 flex-wrap">
                    <a href="index.php" class="back-btn"><i class="bi bi-arrow-left"></i>Tableau de bord</a>
                    <a href="?lock=1" class="lock-btn" onclick="return confirm('Verrouiller le panneau admin ?')">
                        <i class="bi bi-lock-fill"></i>Verrouiller
                    </a>
                </div>
                <h1 class="section-title display-6 mb-1"><i class="bi bi-shield-lock me-2"></i>Panneau d'administration</h1>
                <div class="muted">Gestion des utilisateurs et des équipements — accès restreint</div>
            </div>
            <span class="badge-admin"><i class="bi bi-lock-fill"></i>Admin connecté</span>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success border-0 rounded-4 mb-4"><?php echo h($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger border-0 rounded-4 mb-4"><?php echo h($error); ?></div>
    <?php endif; ?>

    <!-- Tabs -->
    <ul class="nav nav-pills mb-4 gap-2" id="adminTabs">
        <li class="nav-item">
            <a class="nav-link active" href="#" onclick="showTab('users'); return false;">
                <i class="bi bi-people me-2"></i>Utilisateurs
                <span class="ms-2 badge bg-white text-dark"><?php echo count($utilisateurs); ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#" onclick="showTab('equips'); return false;">
                <i class="bi bi-laptop me-2"></i>Équipements
                <span class="ms-2 badge bg-white text-dark"><?php echo count($equipements); ?></span>
            </a>
        </li>
    </ul>

    <!-- ===================== TAB UTILISATEURS ===================== -->
    <div id="tab-users">
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="glass p-4 h-100">
                    <div class="section-title mb-3"><i class="bi bi-person-plus me-2"></i>Ajouter un utilisateur</div>
                    <form method="post" autocomplete="off">
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
                        <button type="submit" name="add_user" value="1" class="btn-add btn">
                            <i class="bi bi-plus-circle me-2"></i>Ajouter l'utilisateur
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="glass p-4 h-100">
                    <div class="section-title mb-3"><i class="bi bi-people me-2"></i>Utilisateurs enregistrés</div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead><tr><th>#</th><th>Prénom Nom</th><th>Service</th><th>Créé le</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($utilisateurs as $u): ?>
                                <tr>
                                    <td class="muted small"><?php echo (int)$u['id']; ?></td>
                                    <td><strong><?php echo h(($u['prenom'] ?? '') . ' ' . $u['nom']); ?></strong></td>
                                    <td class="muted"><?php echo h($u['service'] ?? '—'); ?></td>
                                    <td class="muted small"><?php echo !empty($u['created_at']) ? date('d/m/Y', strtotime($u['created_at'])) : '—'; ?></td>
                                    <td>
                                        <form method="post" onsubmit="return confirm('Supprimer cet utilisateur ? Ses équipements seront libérés.')">
                                            <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                            <button type="submit" name="delete_user" value="1" class="btn-del">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($utilisateurs)): ?>
                                <tr><td colspan="5" class="text-center muted py-4">Aucun utilisateur enregistré.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== TAB ÉQUIPEMENTS ===================== -->
    <div id="tab-equips" style="display:none">
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="glass p-4 h-100">
                    <div class="section-title mb-3"><i class="bi bi-plus-square me-2"></i>Ajouter un équipement</div>
                    <form method="post" autocomplete="off">
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
                                <option value="Imprimante">Imprimante</option>
                                <option value="Autre">Autre</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <div class="label mb-1">QR Code (identifiant unique) *</div>
                            <input type="text" name="equip_qr" id="equipQr" class="form-control" placeholder="ex: QR001, QR002..." required oninput="updateQrPreview()">
                            <div class="muted small mt-1"><i class="bi bi-info-circle me-1"></i>Ce texte sera encodé dans le QR code. Doit être unique.</div>
                        </div>

                        <!-- Prévisualisation QR -->
                        <div class="mb-4 text-center" id="qrPreviewBox" style="display:none">
                            <div class="label mb-2">Prévisualisation</div>
                            <img id="qrPreviewImg" src="" alt="QR" class="qr-preview img-fluid mx-auto d-block">
                            <div class="muted small mt-2 text-break" id="qrPreviewUrl"></div>
                        </div>

                        <button type="submit" name="add_equip" value="1" class="btn-add btn">
                            <i class="bi bi-plus-circle me-2"></i>Ajouter l'équipement
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="glass p-4 h-100">
                    <div class="section-title mb-3"><i class="bi bi-laptop me-2"></i>Équipements enregistrés</div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead><tr><th>Nom</th><th>Type</th><th>QR Code</th><th>Statut</th><th>Attribué à</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($equipements as $row): ?>
                                <tr>
                                    <td><strong><?php echo h($row['nom']); ?></strong></td>
                                    <td class="muted small"><?php echo h($row['type'] ?? '—'); ?></td>
                                    <td><code style="color:#67e8f9;font-size:.82rem"><?php echo h($row['qr_code']); ?></code></td>
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
                                <tr><td colspan="6" class="text-center muted py-4">Aucun équipement enregistré.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center muted small pb-2 mt-4">
        Panneau admin — accès restreint — <a href="?lock=1" style="color:#fca5a5;text-decoration:none" onclick="return confirm('Verrouiller ?')"><i class="bi bi-lock me-1"></i>Verrouiller</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const BASE_URL = "<?php echo addslashes($baseUrl); ?>";

function showTab(tab) {
    document.getElementById('tab-users').style.display  = (tab === 'users')  ? '' : 'none';
    document.getElementById('tab-equips').style.display = (tab === 'equips') ? '' : 'none';
    document.querySelectorAll('.nav-link').forEach((el, i) => {
        el.classList.toggle('active', (i === 0 && tab === 'users') || (i === 1 && tab === 'equips'));
    });
}

function updateQrPreview() {
    const qr  = document.getElementById('equipQr').value.trim();
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
