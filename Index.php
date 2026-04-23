<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'db.php';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$baseUrl  = $protocol . '://' . $host . '/tracabilite/index.php';

$authUser = 'admin';
$authPass = 'bonjour123';

if (!isset($_SESSION['message'])) $_SESSION['message'] = '';
if (!isset($_SESSION['auth_ok'])) $_SESSION['auth_ok'] = false;
if (!isset($_SESSION['last_qr'])) $_SESSION['last_qr'] = '';

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function go(string $msg, string $qr = ''): void {
    $_SESSION['message'] = $msg;
    if ($qr !== '') $_SESSION['last_qr'] = $qr;
    header('Location: index.php');
    exit;
}

// --- Login ---
if (isset($_POST['login_user'], $_POST['login_pass'])) {
    $u = trim($_POST['login_user']);
    $p = trim($_POST['login_pass']);
    if ($u === $authUser && $p === $authPass) {
        $_SESSION['auth_ok'] = true;
        $_SESSION['message'] = 'Accès autorisé';
        header('Location: index.php');
        exit;
    }
    $_SESSION['auth_ok'] = false;
    $_SESSION['message'] = 'Identifiants incorrects';
}

// --- Attribution utilisateur ---
if (isset($_POST['attribuer'], $_POST['equip_id'])) {
    $equipId = (int)$_POST['equip_id'];
    $userId  = (isset($_POST['utilisateur_id']) && $_POST['utilisateur_id'] !== '') ? (int)$_POST['utilisateur_id'] : null;
    try {
        if ($userId === null) {
            $pdo->prepare("UPDATE equipements SET utilisateur_id = NULL, date_attribution = NULL, statut = 'Disponible' WHERE id = ?")->execute([$equipId]);
            $pdo->prepare("INSERT INTO movements (equipement_id, action) VALUES (?, 'Retour (disponible)')")->execute([$equipId]);
            $_SESSION['message'] = '✅ Équipement libéré avec succès';
        } else {
            $pdo->prepare("UPDATE equipements SET utilisateur_id = ?, date_attribution = NOW(), statut = 'En prêt' WHERE id = ?")->execute([$userId, $equipId]);
            $pdo->prepare("INSERT INTO movements (equipement_id, action) VALUES (?, 'Sortie (mise en prêt)')")->execute([$equipId]);
            $_SESSION['message'] = '✅ Équipement attribué avec succès';
        }
    } catch (PDOException $e) {
        $_SESSION['message'] = '❌ Erreur : ' . $e->getMessage();
    }
    header('Location: index.php');
    exit;
}

if (!$_SESSION['auth_ok']) { ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accès sécurisé</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root{--bg1:#050816;--bg2:#111827;--card:rgba(15,23,42,.78);--line:rgba(255,255,255,.12);--txt:#e5e7eb;--muted:#94a3b8;}
        body{min-height:100vh;margin:0;color:var(--txt);background:radial-gradient(circle at 20% 20%,rgba(59,130,246,.25),transparent 25%),radial-gradient(circle at 80% 30%,rgba(168,85,247,.22),transparent 24%),linear-gradient(135deg,var(--bg1),var(--bg2));display:flex;align-items:center;justify-content:center;font-family:system-ui,sans-serif;}
        .cardx{width:min(920px,92vw);background:var(--card);backdrop-filter:blur(18px);border:1px solid var(--line);border-radius:30px;box-shadow:0 30px 80px rgba(0,0,0,.45);overflow:hidden;}
        .left{padding:42px;background:linear-gradient(160deg,rgba(37,99,235,.18),rgba(168,85,247,.12));}
        .right{padding:42px;}
        .title{font-weight:900;letter-spacing:-.04em;}
        .muted{color:var(--muted)}
        .lock-wrap{width:170px;height:170px;margin:auto;position:relative;display:grid;place-items:center;}
        .ring{position:absolute;inset:0;border-radius:50%;border:2px solid rgba(255,255,255,.12);animation:spin 7s linear infinite;}
        .ring.r2{inset:14px;border-style:dashed;animation-duration:11s;}
        .ring.r3{inset:28px;border-color:rgba(34,197,94,.18);animation-duration:15s;}
        @keyframes spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}
        .lock-icon{font-size:4.5rem;color:#86efac;text-shadow:0 0 20px rgba(34,197,94,.35);animation:pulse 2s ease-in-out infinite;}
        @keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.06)}}
        .unlock-anim{animation:unlock .9s cubic-bezier(.2,.9,.2,1) both;}
        @keyframes unlock{0%{transform:translateY(16px) scale(.94);opacity:0}60%{transform:translateY(-4px) scale(1.03);opacity:1}100%{transform:translateY(0) scale(1)}}
        .form-control{background:rgba(255,255,255,.08)!important;color:#fff!important;border:1px solid rgba(255,255,255,.14)!important;border-radius:16px;padding:14px 16px;box-shadow:none!important;}
        .form-control::placeholder{color:#cbd5e1}
        input:-webkit-autofill,input:-webkit-autofill:hover,input:-webkit-autofill:focus{-webkit-box-shadow:0 0 0 1000px rgba(255,255,255,.08) inset !important;-webkit-text-fill-color:#fff !important;transition:background-color 9999s ease-out 0s;}
        .btn-login{border:none;border-radius:16px;padding:14px 18px;font-weight:800;background:linear-gradient(135deg,#2563eb,#7c3aed);}
        .badge-soft{display:inline-flex;gap:8px;align-items:center;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.10);padding:8px 12px;border-radius:999px;color:var(--txt)}
        .loader-line{height:6px;border-radius:999px;background:rgba(255,255,255,.08);overflow:hidden}
        .loader-line span{display:block;height:100%;background:linear-gradient(90deg,#22c55e,#06b6d4,#7c3aed);animation:bar 2.2s ease-in-out infinite}
        @keyframes bar{0%{width:0%}50%{width:100%}100%{width:0%}}
    </style>
</head>
<body>
<div class="cardx row g-0">
    <div class="col-lg-5 left text-center d-flex flex-column justify-content-center">
        <div class="badge-soft mx-auto mb-3"><i class="bi bi-shield-lock"></i> Interface protégée</div>
        <div class="lock-wrap unlock-anim mb-3">
            <div class="ring"></div><div class="ring r2"></div><div class="ring r3"></div>
            <i class="bi bi-unlock lock-icon"></i>
        </div>
        <h1 class="title display-6 mb-2">Accès sécurisé</h1>
        <p class="muted mb-0">Saisis tes identifiants pour déverrouiller le tableau de bord.</p>
    </div>
    <div class="col-lg-7 right">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <div class="text-uppercase small muted">Connexion requise</div>
                <h2 class="h3 mb-0 fw-bold">Déverrouillage</h2>
            </div>
            <span class="badge-soft"><i class="bi bi-lightning-charge"></i> Secure mode</span>
        </div>
        <?php if (!empty($_SESSION['message'])): ?>
            <div class="alert <?php echo ($_SESSION['auth_ok'] ?? false) ? 'alert-success' : 'alert-danger'; ?> border-0 rounded-4">
                <?php echo h($_SESSION['message']); ?>
            </div>
            <?php $_SESSION['message'] = ''; ?>
        <?php endif; ?>
        <form method="post" autocomplete="off" class="mt-3">
            <div class="mb-3">
                <label class="form-label">Utilisateur</label>
                <input type="text" name="login_user" class="form-control" placeholder="Identifiant" autocomplete="off" readonly onfocus="this.removeAttribute('readonly');" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Mot de passe</label>
                <input type="password" name="login_pass" class="form-control" placeholder="Mot de passe" autocomplete="off" readonly onfocus="this.removeAttribute('readonly');" required>
            </div>
            <div class="loader-line mb-3"><span></span></div>
            <button type="submit" class="btn btn-primary btn-login w-100"><i class="bi bi-unlock-fill me-2"></i>Déverrouiller</button>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php exit; }

$history = []; $equipements = []; $equip = null; $utilisateurs = []; $delaySeconds = 10;

try {
    $history = $pdo->query("SELECT e.nom, e.qr_code, m.action, m.date_action FROM movements m JOIN equipements e ON m.equipement_id = e.id ORDER BY m.date_action DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $history = []; }

try {
    $utilisateurs = $pdo->query("SELECT id, nom, prenom, service FROM utilisateurs ORDER BY nom ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $utilisateurs = []; }

$qr = '';
if (isset($_GET['qr'])) $qr = trim($_GET['qr']);
elseif (isset($_POST['qr_code'])) $qr = trim($_POST['qr_code']);

if ($qr !== '') {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT id, nom, type, statut, qr_code, utilisateur_id FROM equipements WHERE qr_code = ? LIMIT 1 FOR UPDATE");
        $stmt->execute([$qr]);
        $equip = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$equip) { $pdo->rollBack(); go('❌ QR code inconnu : ' . $qr); }

        $stmt = $pdo->prepare("SELECT first_scan_at FROM scan_pending WHERE qr_code = ? LIMIT 1 FOR UPDATE");
        $stmt->execute([$qr]);
        $pending = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pending) {
            $pdo->prepare("INSERT INTO scan_pending (qr_code, first_scan_at) VALUES (?, NOW())")->execute([$qr]);
            $pdo->commit();
            go('🔍 Etat actuel de ' . $equip['nom'] . ' : ' . $equip['statut'], $qr);
        }

        $elapsed = time() - strtotime($pending['first_scan_at']);

        if ($elapsed <= $delaySeconds) {
            $newStatus = ($equip['statut'] === 'Disponible') ? 'En prêt' : 'Disponible';
            if ($newStatus === 'Disponible') {
                $pdo->prepare("UPDATE equipements SET statut = ?, utilisateur_id = NULL, date_attribution = NULL WHERE id = ? AND qr_code = ?")
                    ->execute([$newStatus, $equip['id'], $qr]);
            } else {
                $pdo->prepare("UPDATE equipements SET statut = ? WHERE id = ? AND qr_code = ?")
                    ->execute([$newStatus, $equip['id'], $qr]);
            }
            $action = ($newStatus === 'En prêt') ? 'Sortie (mise en prêt)' : 'Retour (disponible)';
            $pdo->prepare("INSERT INTO movements (equipement_id, action) VALUES (?, ?)")->execute([$equip['id'], $action]);
            $pdo->prepare("DELETE FROM scan_pending WHERE qr_code = ?")->execute([$qr]);
            $pdo->commit();
            go('✅ ' . $equip['nom'] . ' → ' . $newStatus, $qr);
        } else {
            $pdo->prepare("UPDATE scan_pending SET first_scan_at = NOW() WHERE qr_code = ?")->execute([$qr]);
            $pdo->commit();
            go('🔍 Etat actuel de ' . $equip['nom'] . ' : ' . $equip['statut'], $qr);
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        go('Erreur SQL : ' . $e->getMessage());
    }
}

$lastQr = $_SESSION['last_qr'] ?? '';
if ($lastQr !== '' && $equip === null) {
    try {
        $stmt = $pdo->prepare("SELECT id, nom, type, statut, qr_code, utilisateur_id FROM equipements WHERE qr_code = ? LIMIT 1");
        $stmt->execute([$lastQr]);
        $equip = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) { $equip = null; }
}

try {
    $equipements = $pdo->query("
        SELECT e.id, e.nom, e.type, e.statut, e.qr_code, e.utilisateur_id, e.date_attribution,
               u.nom AS u_nom, u.prenom AS u_prenom, u.service AS u_service
        FROM equipements e
        LEFT JOIN utilisateurs u ON u.id = e.utilisateur_id
        ORDER BY e.nom ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $equipements = []; }

$total = count($equipements); $available = 0;
foreach ($equipements as $e) if (($e['statut'] ?? '') === 'Disponible') $available++;
$busy = max(0, $total - $available);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Système de traçabilité</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root{--bg1:#070b18;--bg2:#111827;--card:rgba(15,23,42,.72);--line:rgba(255,255,255,.10);--txt:#e5e7eb;--muted:#94a3b8;}
        html{scroll-behavior:smooth;}
        body{min-height:100vh;color:var(--txt);background:radial-gradient(circle at 10% 10%,rgba(59,130,246,.28),transparent 24%),radial-gradient(circle at 88% 18%,rgba(168,85,247,.22),transparent 24%),radial-gradient(circle at 50% 100%,rgba(16,185,129,.18),transparent 30%),linear-gradient(135deg,var(--bg1),var(--bg2));overflow-x:hidden;}
        .glass{background:var(--card);backdrop-filter:blur(18px);border:1px solid var(--line);box-shadow:0 24px 60px rgba(0,0,0,.35);border-radius:28px;}
        .hero{padding:28px;}
        .title{letter-spacing:-.04em;font-weight:900;}
        .muted{color:var(--muted)}
        .chip,.badge-soft{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.08);color:var(--txt);}
        .scan-panel{background:linear-gradient(135deg,rgba(37,99,235,.25),rgba(124,58,237,.18));border:1px solid rgba(255,255,255,.12);border-radius:26px;padding:22px;}
        .scan-input{background:rgba(255,255,255,.08)!important;color:#fff!important;border:1px solid rgba(255,255,255,.14)!important;border-radius:16px;padding:16px 18px;font-size:1.05rem;}
        .scan-input::placeholder{color:#cbd5e1}
        .btn-scan{border-radius:16px;padding:14px 18px;font-weight:800;border:none;background:linear-gradient(135deg,#2563eb,#7c3aed)}
        .btn-soft{border-radius:16px;padding:14px 18px;font-weight:800;border:1px solid rgba(255,255,255,.10);background:rgba(255,255,255,.08);color:#fff;text-decoration:none;display:inline-block;text-align:center;}
        .btn-analyse{border-radius:16px;padding:14px 18px;font-weight:800;border:1px solid rgba(168,85,247,.40);background:linear-gradient(135deg,rgba(124,58,237,.25),rgba(168,85,247,.15));color:#e9d5ff;text-decoration:none;display:inline-block;text-align:center;}
        .mini-card{background:linear-gradient(135deg,rgba(59,130,246,.16),rgba(168,85,247,.12));border:1px solid rgba(255,255,255,.08);border-radius:22px;padding:18px;height:100%;}
        .label{font-size:.82rem;text-transform:uppercase;letter-spacing:.12em;color:var(--muted)}
        .value{font-size:1.55rem;font-weight:900}
        .status-badge{padding:8px 12px;border-radius:999px;font-weight:800;display:inline-flex;gap:8px;align-items:center}
        .ok{background:rgba(34,197,94,.16);color:#86efac}
        .busy{background:rgba(245,158,11,.16);color:#fdba74}
        .table-wrap{overflow:hidden;border-radius:24px}
        .table thead th{background:rgba(255,255,255,.08)!important;color:var(--txt);border-color:rgba(255,255,255,.08)}
        .table tbody td{background:rgba(255,255,255,.02)!important;color:var(--txt);border-color:rgba(255,255,255,.06)}
        .section-title{font-weight:900;margin-bottom:16px;letter-spacing:-.02em}
        .qr-card{text-align:center}
        .qr-card img{border-radius:18px;background:#fff;padding:10px;}
        .footer-note{color:var(--muted);font-size:.9rem}
        .live-badge{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.18);color:#86efac}
        .ip-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:999px;background:rgba(6,182,212,.12);border:1px solid rgba(6,182,212,.25);color:#67e8f9;font-size:.82rem;font-family:monospace;}
        .user-badge{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;background:rgba(168,85,247,.15);border:1px solid rgba(168,85,247,.25);color:#d8b4fe;font-size:.78rem;font-weight:700;}
        .select-user{background:rgba(255,255,255,.08)!important;color:#fff!important;border:1px solid rgba(255,255,255,.14)!important;border-radius:10px;padding:6px 10px;font-size:.85rem;}
        .select-user option{background:#1e293b;color:#fff;}
        .btn-attrib{border:none;border-radius:10px;padding:6px 14px;font-weight:700;font-size:.82rem;background:linear-gradient(135deg,#2563eb,#7c3aed);color:#fff;cursor:pointer;}
        .btn-liberer{border-radius:10px;padding:6px 14px;font-weight:700;font-size:.82rem;background:rgba(239,68,68,.20);border:1px solid rgba(239,68,68,.30);color:#fca5a5;cursor:pointer;}
        .modal-content{background:#1e293b;border:1px solid rgba(255,255,255,.12);border-radius:24px;color:var(--txt);}
        .modal-header{border-bottom:1px solid rgba(255,255,255,.08);}
        .modal-footer{border-top:1px solid rgba(255,255,255,.08);}
    </style>
</head>
<body>
<div class="container py-4 py-lg-5">

    <!-- Header -->
    <div class="glass hero mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
                <div class="chip rounded-pill px-3 py-2 mb-3"><span class="me-2">●</span>Système de traçabilité</div>
                <h1 class="title display-5 mb-2">Gestion des équipements par QR code</h1>
                <p class="muted mb-0">Interface moderne, rapide et pensée pour impressionner au lancement.</p>
            </div>
            <div class="text-lg-end">
                <div class="label">Dernière actualisation</div>
                <div class="fw-semibold fs-5"><?php echo date('d/m/Y H:i'); ?></div>
                <div class="live-badge mt-2"><span>●</span>En ligne</div>
                <div class="ip-badge mt-2"><i class="bi bi-hdd-network"></i><?php echo h($host); ?></div>
            </div>
        </div>

        <!-- Zone scan -->
        <div class="scan-panel mt-4">
            <div class="section-title mb-1"><i class="bi bi-qr-code-scan me-2"></i>Zone de scan prioritaire</div>
            <div class="muted mb-3">Scanne ici pour déclencher l'action et mettre à jour les états.</div>
            <?php if (!empty($_SESSION['message'])): ?>
                <div class="alert alert-info mt-2 mb-3 rounded-4 border-0">
                    <i class="bi bi-info-circle me-2"></i><?php echo h($_SESSION['message']); ?>
                </div>
                <?php $_SESSION['message'] = ''; ?>
            <?php endif; ?>
            <form method="get" action="index.php">
                <div class="row g-2 align-items-stretch">
                    <div class="col-12 col-lg-9">
                        <input type="text" name="qr" class="form-control scan-input" placeholder="Scanner ou saisir un QR code..." autofocus>
                    </div>
                    <div class="col-12 col-lg-3 d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-scan"><i class="bi bi-arrow-right-circle me-2"></i>Valider</button>
                        <a class="btn-soft" href="./export.php?type=equipements"><i class="bi bi-download me-2"></i>Export équipements</a>
                        <a class="btn-analyse" href="./export.php"><i class="bi bi-bar-chart-line me-2"></i>Analyse & Logs</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- KPIs -->
    <div class="row g-4 mb-4">
        <div class="col-md-4"><div class="mini-card"><div class="label">Total équipements</div><div class="value"><?php echo $total; ?></div></div></div>
        <div class="col-md-4"><div class="mini-card"><div class="label">Disponibles</div><div class="value"><?php echo $available; ?></div></div></div>
        <div class="col-md-4"><div class="mini-card"><div class="label">En prêt</div><div class="value"><?php echo $busy; ?></div></div></div>
    </div>

    <!-- Tableau équipements + QR -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="glass p-4 h-100">
                <div class="section-title"><i class="bi bi-laptop me-2"></i>Équipements & Attributions</div>
                <div class="table-responsive table-wrap">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Type</th>
                                <th>Statut</th>
                                <th>Attribué à</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($equipements as $row): ?>
                            <tr>
                                <td><?php echo h($row['nom']); ?></td>
                                <td><?php echo h($row['type'] ?? ''); ?></td>
                                <td>
                                    <span class="status-badge <?php echo (($row['statut'] ?? '') === 'Disponible') ? 'ok' : 'busy'; ?>">
                                        <?php echo h($row['statut']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($row['u_nom'])): ?>
                                        <div class="user-badge">
                                            <i class="bi bi-person-fill"></i>
                                            <?php echo h(($row['u_prenom'] ?? '') . ' ' . $row['u_nom']); ?>
                                        </div>
                                        <?php if (!empty($row['u_service'])): ?>
                                            <div class="muted small mt-1"><?php echo h($row['u_service']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($row['date_attribution'])): ?>
                                            <div class="muted" style="font-size:.72rem">
                                                Depuis le <?php echo date('d/m/Y H:i', strtotime($row['date_attribution'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="muted small">— Non attribué</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn-attrib mb-1"
                                        onclick="openModal(<?php echo (int)$row['id']; ?>, '<?php echo h($row['nom']); ?>', <?php echo isset($row['utilisateur_id']) && $row['utilisateur_id'] ? (int)$row['utilisateur_id'] : 'null'; ?>)">
                                        <i class="bi bi-person-plus me-1"></i>Attribuer
                                    </button>
                                    <?php if (!empty($row['u_nom'])): ?>
                                    <form method="post" style="display:inline" onsubmit="return confirm('Libérer cet équipement ?')">
                                        <input type="hidden" name="equip_id" value="<?php echo (int)$row['id']; ?>">
                                        <input type="hidden" name="utilisateur_id" value="">
                                        <button type="submit" name="attribuer" value="1" class="btn-liberer">
                                            <i class="bi bi-x-circle me-1"></i>Libérer
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- QR Code -->
        <div class="col-lg-4">
            <div class="glass p-4 h-100 qr-card">
                <div class="section-title"><i class="bi bi-qr-code me-2"></i>QR téléphone</div>
                <?php if (isset($equip) && $equip):
                    $qrUrl = $baseUrl . '?qr=' . urlencode($equip['qr_code']); ?>
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=<?php echo urlencode($qrUrl); ?>" alt="QR" class="img-fluid mb-3">
                    <div class="small text-break muted"><?php echo h($equip['nom']); ?></div>
                    <div class="small text-break muted"><?php echo h($equip['statut']); ?></div>
                    <div class="mt-2">
                        <a href="<?php echo h($qrUrl); ?>" class="small text-break" style="color:#67e8f9"><?php echo h($qrUrl); ?></a>
                    </div>
                <?php else: ?>
                    <p class="muted mb-0">Scanne un équipement pour voir son QR code ici.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Détails scan + Historique -->
    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="glass p-4 h-100">
                <div class="section-title"><i class="bi bi-card-heading me-2"></i>Détails du scan</div>
                <?php if (isset($equip) && $equip): ?>
                    <p class="mb-2"><strong>QR Code :</strong> <?php echo h($equip['qr_code']); ?></p>
                    <p class="mb-2"><strong>Nom :</strong> <?php echo h($equip['nom'] ?? ''); ?></p>
                    <p class="mb-2"><strong>Type :</strong> <?php echo h($equip['type'] ?? ''); ?></p>
                    <p class="mb-0"><strong>Statut :</strong> <?php echo h($equip['statut'] ?? ''); ?></p>
                <?php else: ?>
                    <p class="muted mb-0">Aucun équipement sélectionné.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="glass p-4 h-100">
                <div class="section-title"><i class="bi bi-clock-history me-2"></i>Historique des mouvements</div>
                <div class="table-responsive table-wrap">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Équipement</th><th>QR Code</th><th>Action</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php foreach ($history as $row): ?>
                            <tr>
                                <td><?php echo h($row['nom']); ?></td>
                                <td><?php echo h($row['qr_code']); ?></td>
                                <td><?php echo h($row['action']); ?></td>
                                <td><?php echo h($row['date_action']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="footer-note text-center pb-2">
        Interface sécurisée — admin only — Serveur : <span style="color:#67e8f9;font-family:monospace"><?php echo h($baseUrl); ?></span>
    </div>
</div>

<!-- Modal attribution -->
<div class="modal fade" id="modalAttrib" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Attribuer l'équipement</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="index.php">
                <div class="modal-body">
                    <p class="muted mb-3">Équipement : <strong id="modalEquipNom"></strong></p>
                    <input type="hidden" name="equip_id" id="modalEquipId">
                    <label class="form-label fw-bold">Sélectionner un utilisateur</label>
                    <select name="utilisateur_id" class="form-select select-user w-100 mt-1">
                        <option value="">— Aucun (libérer) —</option>
                        <?php foreach ($utilisateurs as $u): ?>
                        <option value="<?php echo (int)$u['id']; ?>">
                            <?php echo h(($u['prenom'] ?? '') . ' ' . $u['nom'] . ' — ' . ($u['service'] ?? '')); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="attribuer" value="1" class="btn btn-primary rounded-3">
                        <i class="bi bi-check2 me-1"></i>Confirmer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openModal(id, nom, currentUserId) {
    document.getElementById('modalEquipId').value = id;
    document.getElementById('modalEquipNom').textContent = nom;
    const sel = document.querySelector('select[name="utilisateur_id"]');
    if (currentUserId !== null) {
        sel.value = currentUserId;
    } else {
        sel.value = '';
    }
    new bootstrap.Modal(document.getElementById('modalAttrib')).show();
}
</script>
</body>
</html>
