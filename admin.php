<?php
ob_start();
error_reporting(E_ALL); ini_set('display_errors', 1);
session_start();
require_once 'db.php';

/* ═══════════════════════════════════
   FONCTIONS UTILITAIRES
═══════════════════════════════════ */
function hv(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function goAdmin(string $msg, string $tab = 'equipements'): void {
    $_SESSION['admin_msg'] = $msg;
    header('Location: admin.php?tab=' . $tab);
    exit;
}

function siteBadge(string $site): string {
    $c = ['Paris' => '#3b82f6', 'Boulogne' => '#8b5cf6', 'Lyon' => '#f59e0b', 'Uxbridge' => '#10b981', 'Rennes' => '#ef4444'];
    $col = $c[$site] ?? '#94a3b8';
    return $site
        ? '<span style="background:' . $col . '22;border:1px solid ' . $col . '55;color:' . $col . ';padding:3px 10px;border-radius:999px;font-size:.78rem;font-weight:700">' . hv($site) . '</span>'
        : '<span style="color:#94a3b8">—</span>';
}

function reparBadge(string $etat): string {
    $map = [
        'RAS'                    => ['#22c55e', '#14532d'],
        'A diagnostiquer'        => ['#f59e0b', '#451a03'],
        'En réparation'          => ['#3b82f6', '#1e3a5f'],
        'En attente de pièces'   => ['#f97316', '#431407'],
        'Test après réparation'  => ['#a78bfa', '#2e1065'],
        'Réparé'                 => ['#34d399', '#064e3b'],
        'Restitué'               => ['#22c55e', '#14532d'],
        'Hors service'           => ['#ef4444', '#450a0a'],
    ];
    $e = $etat ?: 'RAS';
    [$fg, $bg] = $map[$e] ?? ['#94a3b8', '#1e293b'];
    return '<span style="background:' . $bg . ';border:1px solid ' . $fg . '55;color:' . $fg . ';padding:3px 8px;border-radius:999px;font-size:.75rem;font-weight:700">' . hv($e) . '</span>';
}

function tableOk(PDO $pdo, string $t): bool {
    static $cache = [];
    if (!isset($cache[$t])) {
        try { $pdo->query("SELECT 1 FROM `$t` LIMIT 0"); $cache[$t] = true; }
        catch (PDOException $e) { $cache[$t] = false; }
    }
    return $cache[$t];
}

function colOk(PDO $pdo, string $t, string $c): bool {
    static $cache = [];
    $k = $t . '.' . $c;
    if (!isset($cache[$k])) {
        try { $pdo->query("SELECT `$c` FROM `$t` LIMIT 0"); $cache[$k] = true; }
        catch (PDOException $e) { $cache[$k] = false; }
    }
    return $cache[$k];
}

/* ═══════════════════════════════════
   AUTH
═══════════════════════════════════ */
if (!isset($_SESSION['admin_ok'])) $_SESSION['admin_ok'] = false;
if (!isset($_SESSION['admin_msg'])) $_SESSION['admin_msg'] = '';

if (isset($_POST['admin_login'], $_POST['admin_pass'])) {
    if ($_POST['admin_login'] === 'admin' && $_POST['admin_pass'] === 'Sodia01') {
        $_SESSION['admin_ok'] = true;
        goAdmin('Accès accordé');
    } else {
        $_SESSION['admin_msg'] = 'Identifiants incorrects';
        header('Location: admin.php');
        exit;
    }
}
if (isset($_POST['logout'])) {
    $_SESSION['admin_ok'] = false;
    header('Location: admin.php');
    exit;
}

/* ── Page de login ── */
if (!$_SESSION['admin_ok']) { ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Administration — Accès sécurisé</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{min-height:100vh;display:grid;place-items:center;color:#e5e7eb;background:radial-gradient(circle at 20% 20%,rgba(239,68,68,.2),transparent 24%),radial-gradient(circle at 80% 30%,rgba(168,85,247,.18),transparent 24%),linear-gradient(135deg,#050816,#0f172a)}
.card{width:min(440px,92vw);background:rgba(15,23,42,.80);backdrop-filter:blur(18px);border:1px solid rgba(255,255,255,.12);border-radius:24px;padding:36px;box-shadow:0 30px 80px rgba(0,0,0,.5)}
.form-control{background:rgba(255,255,255,.08)!important;color:#fff!important;border:1px solid rgba(255,255,255,.14)!important;border-radius:12px;padding:12px 14px;box-shadow:none!important}
.form-control::placeholder{color:#64748b}
.btn-login{border:none;border-radius:12px;padding:13px;font-weight:800;background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;width:100%;cursor:pointer;font-size:1rem}
</style>
</head>
<body>
<div class="card">
  <div class="text-center mb-4">
    <i class="bi bi-shield-lock" style="font-size:3rem;color:#f87171"></i>
    <h1 class="h3 mt-2 fw-bold" style="color:#fca5a5">Administration</h1>
    <p style="color:#fca5a5;font-size:.9rem">Accès réservé aux administrateurs</p>
  </div>
  <?php if (!empty($_SESSION['admin_msg'])): ?>
  <div class="alert alert-danger border-0 rounded-3 mb-3"><?= hv($_SESSION['admin_msg']) ?></div>
  <?php $_SESSION['admin_msg'] = ''; ?>
  <?php endif; ?>
  <form method="post">
    <div class="mb-3">
      <label class="form-label fw-bold" style="color:#f87171">Identifiant</label>
      <input type="text" name="admin_login" class="form-control" placeholder="admin" autocomplete="off" required>
    </div>
    <div class="mb-4">
      <label class="form-label fw-bold" style="color:#f87171">Mot de passe</label>
      <input type="password" name="admin_pass" class="form-control" placeholder="••••••••" required>
    </div>
    <button type="submit" class="btn-login"><i class="bi bi-unlock-fill me-2"></i>Accéder à l'administration</button>
  </form>
  <div class="text-center mt-3">
    <a href="index.php" style="color:#fca5a5;font-size:.85rem"><i class="bi bi-arrow-left me-1"></i>Retour au tableau de bord</a>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php ob_end_flush(); exit; } ?>
<?php
/* ═══════════════════════════════════
   LISTES DE RÉFÉRENCE
═══════════════════════════════════ */
$wfSteps   = ['A diagnostiquer', 'En réparation', 'En attente de pièces', 'Test après réparation', 'Réparé', 'Restitué'];
$priorList = ['Normale', 'Haute', 'Critique'];
$statuts   = ['Disponible', 'En prêt', 'En maintenance', 'Hors service'];
$types     = ['PC', 'Téléphone', 'Tablette', 'Écran', 'Imprimante', 'Serveur', 'Autre'];
$sites     = ['Paris', 'Boulogne', 'Lyon', 'Uxbridge', 'Rennes'];
$etats     = ['RAS', 'A diagnostiquer', 'En réparation', 'En attente de pièces', 'Test après réparation', 'Réparé', 'Restitué', 'Hors service'];

/* ═══════════════════════════════════
   POST HANDLERS
═══════════════════════════════════ */
$action = trim($_POST['action'] ?? '');

/* ── Équipement CRUD ── */
if ($action === 'add_equip') {
    try {
        $cols = 'nom,type,numero_serie,localisation,statut,qr_code';
        $vals = '?,?,?,?,?,?';
        $bind = [
            trim($_POST['nom'] ?? ''),
            trim($_POST['type'] ?? ''),
            trim($_POST['numero_serie'] ?? ''),
            trim($_POST['localisation'] ?? ''),
            trim($_POST['statut'] ?? 'Disponible'),
            trim($_POST['qr_code'] ?? 'QR-' . uniqid()),
        ];
        foreach (['marque', 'modele', 'categorie', 'date_achat', 'garantie_fin', 'commentaire_technique'] as $c)
            if (colOk($pdo, 'equipements', $c) && isset($_POST[$c]) && $_POST[$c] !== '') {
                $cols .= ',' . $c; $vals .= ',?'; $bind[] = trim($_POST[$c]);
            }
        if (colOk($pdo, 'equipements', 'criticite') && isset($_POST['criticite'])) {
            $cols .= ',criticite'; $vals .= ',?'; $bind[] = trim($_POST['criticite']);
        }
        if (colOk($pdo, 'equipements', 'etat_reparation')) {
            $cols .= ',etat_reparation'; $vals .= ',?'; $bind[] = 'RAS';
        }
        $pdo->prepare("INSERT INTO equipements($cols) VALUES($vals)")->execute($bind);
        goAdmin('Équipement ajouté avec succès');
    } catch (Exception $e) { goAdmin('Erreur : ' . $e->getMessage()); }
}

if ($action === 'edit_equip') {
    $id = (int)($_POST['id'] ?? 0);
    try {
        $sets = ['nom=?', 'type=?', 'numero_serie=?', 'localisation=?', 'statut=?'];
        $bind = [
            trim($_POST['nom'] ?? ''), trim($_POST['type'] ?? ''),
            trim($_POST['numero_serie'] ?? ''), trim($_POST['localisation'] ?? ''),
            trim($_POST['statut'] ?? ''),
        ];
        foreach (['marque', 'modele', 'categorie', 'date_achat', 'garantie_fin', 'commentaire_technique'] as $c)
            if (colOk($pdo, 'equipements', $c) && isset($_POST[$c])) {
                $sets[] = $c . '=?'; $bind[] = trim($_POST[$c]);
            }
        if (colOk($pdo, 'equipements', 'criticite') && isset($_POST['criticite'])) {
            $sets[] = 'criticite=?'; $bind[] = trim($_POST['criticite']);
        }
        if (colOk($pdo, 'equipements', 'etat_reparation') && isset($_POST['etat_reparation'])) {
            $sets[] = 'etat_reparation=?'; $bind[] = trim($_POST['etat_reparation']);
        }
        $bind[] = $id;
        $pdo->prepare("UPDATE equipements SET " . implode(',', $sets) . " WHERE id=?")->execute($bind);
        goAdmin('Équipement modifié');
    } catch (Exception $e) { goAdmin('Erreur : ' . $e->getMessage()); }
}

if ($action === 'delete_equip') {
    $id = (int)($_POST['id'] ?? 0);
    try { $pdo->prepare("DELETE FROM equipements WHERE id=?")->execute([$id]); goAdmin('Équipement supprimé'); }
    catch (Exception $e) { goAdmin('Erreur : ' . $e->getMessage()); }
}

/* ── Utilisateur CRUD ── */
if ($action === 'add_user') {
    try {
        $pdo->prepare("INSERT INTO utilisateurs(nom,prenom,service) VALUES(?,?,?)")
            ->execute([trim($_POST['nom'] ?? ''), trim($_POST['prenom'] ?? ''), trim($_POST['service'] ?? '')]);
        goAdmin('Utilisateur ajouté', 'utilisateurs');
    } catch (Exception $e) { goAdmin('Erreur : ' . $e->getMessage(), 'utilisateurs'); }
}

if ($action === 'edit_user') {
    $id = (int)($_POST['id'] ?? 0);
    try {
        $pdo->prepare("UPDATE utilisateurs SET nom=?,prenom=?,service=? WHERE id=?")
            ->execute([trim($_POST['nom'] ?? ''), trim($_POST['prenom'] ?? ''), trim($_POST['service'] ?? ''), $id]);
        goAdmin('Utilisateur modifié', 'utilisateurs');
    } catch (Exception $e) { goAdmin('Erreur : ' . $e->getMessage(), 'utilisateurs'); }
}

if ($action === 'delete_user') {
    $id = (int)($_POST['id'] ?? 0);
    try {
        $pdo->prepare("UPDATE equipements SET utilisateur_id=NULL WHERE utilisateur_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM utilisateurs WHERE id=?")->execute([$id]);
        goAdmin('Utilisateur supprimé', 'utilisateurs');
    } catch (Exception $e) { goAdmin('Erreur : ' . $e->getMessage(), 'utilisateurs'); }
}

/* ── Réparation : OUVRIR ── */
if ($action === 'open_repair') {
    $eid    = (int)($_POST['equipement_id'] ?? 0);
    $panne  = trim($_POST['panne_declaree'] ?? '');
    $tech   = trim($_POST['technicien'] ?? '');
    $prior  = trim($_POST['priorite'] ?? 'Normale');
    $ticket = trim($_POST['numero_ticket'] ?? '');
    try {
        if (!tableOk($pdo, 'reparations')) throw new Exception("Table 'reparations' introuvable.");
        $repCols = 'equipement_id,statut,panne_declaree,technicien,date_ouverture,date_mise_a_jour';
        $repVals = "?,'A diagnostiquer',?,?,NOW(),NOW()";
        $repBind = [$eid, $panne, $tech];
        if (colOk($pdo, 'reparations', 'priorite'))      { $repCols .= ',priorite';      $repVals .= ',?'; $repBind[] = $prior; }
        if (colOk($pdo, 'reparations', 'numero_ticket')) { $repCols .= ',numero_ticket'; $repVals .= ',?'; $repBind[] = $ticket; }
        $pdo->prepare("INSERT INTO reparations($repCols) VALUES($repVals)")->execute($repBind);
        if (colOk($pdo, 'equipements', 'etat_reparation'))
            $pdo->prepare("UPDATE equipements SET etat_reparation='A diagnostiquer' WHERE id=?")->execute([$eid]);
        if (tableOk($pdo, 'equipement_logs'))
            $pdo->prepare("INSERT INTO equipement_logs(equipement_id,type_event,titre,details,auteur) VALUES(?,'reparation','Réparation ouverte',?,?)")
                ->execute([$eid, 'Panne : ' . $panne . ($ticket ? ' | Ticket: ' . $ticket : ''), 'admin']);
        goAdmin('Réparation ouverte' . ($ticket ? ' (ticket ' . $ticket . ')' : ''), 'reparations');
    } catch (Exception $e) { goAdmin('Erreur : ' . $e->getMessage(), 'reparations'); }
}

/* ── Réparation : METTRE À JOUR ── */
if ($action === 'update_repair') {
    $rid     = (int)($_POST['rep_id'] ?? 0);
    $eid     = (int)($_POST['equipement_id'] ?? 0);
    $newStat = trim($_POST['nouveau_statut'] ?? '');
    $comment = trim($_POST['commentaire'] ?? '');
    $piece   = trim($_POST['piece_changee'] ?? '');
    try {
        if (!tableOk($pdo, 'reparations')) throw new Exception("Table 'reparations' introuvable.");
        $sets = ['statut=?', 'date_mise_a_jour=NOW()'];
        $bind = [$newStat];
        foreach (['diagnostic', 'action_prevue', 'pieces_a_changer', 'pieces_changees', 'technicien', 'cout_reparation', 'fournisseur_piece', 'numero_ticket'] as $c)
            if (isset($_POST[$c]) && $_POST[$c] !== '' && colOk($pdo, 'reparations', $c)) {
                $sets[] = "$c=?"; $bind[] = trim($_POST[$c]);
            }
        if (in_array($newStat, ['Réparé', 'Restitué'], true)) $sets[] = 'date_cloture=NOW()';
        $bind[] = $rid;
        $pdo->prepare("UPDATE reparations SET " . implode(',', $sets) . " WHERE id=?")->execute($bind);
        if (colOk($pdo, 'equipements', 'etat_reparation'))
            $pdo->prepare("UPDATE equipements SET etat_reparation=? WHERE id=?")->execute([$newStat, $eid]);
        if (tableOk($pdo, 'reparation_logs'))
            $pdo->prepare("INSERT INTO reparation_logs(reparation_id,statut,commentaire,piece_changee,auteur,created_at) VALUES(?,?,?,?,?,NOW())")
                ->execute([$rid, $newStat, $comment, $piece, 'admin']);
        if (tableOk($pdo, 'equipement_logs'))
            $pdo->prepare("INSERT INTO equipement_logs(equipement_id,type_event,titre,details,auteur) VALUES(?,'reparation',?,?,?)")
                ->execute([$eid, 'Réparation → ' . $newStat, $comment ?: 'Statut mis à jour', 'admin']);
        goAdmin('Réparation mise à jour', 'reparations');
    } catch (Exception $e) { goAdmin('Erreur : ' . $e->getMessage(), 'reparations'); }
}

if ($action === 'close_repair') {
    $repId     = (int)($_POST['rep_id'] ?? 0);
    $eid       = (int)($_POST['equipement_id'] ?? 0);
    $finalStat = in_array($_POST['final_statut'] ?? '', ['Réparé', 'Restitué', 'Hors service'])
                 ? $_POST['final_statut'] : 'Réparé';
    try {
        if (!tableOk($pdo, 'reparations')) throw new Exception("Table 'reparations' introuvable.");
        $sets = ['statut=?', 'date_cloture=NOW()', 'date_mise_a_jour=NOW()'];
        $bind = [$finalStat];
        foreach (['diagnostic', 'pieces_changees', 'cout_reparation', 'technicien'] as $c)
            if (!empty($_POST[$c]) && colOk($pdo, 'reparations', $c)) { $sets[] = $c . '=?'; $bind[] = trim($_POST[$c]); }
        $bind[] = $repId;
        $pdo->prepare('UPDATE reparations SET ' . implode(',', $sets) . ' WHERE id=?')->execute($bind);
        if (colOk($pdo, 'equipements', 'etat_reparation'))
            $pdo->prepare('UPDATE equipements SET etat_reparation=? WHERE id=?')->execute([$finalStat, $eid]);
        if (tableOk($pdo, 'reparation_logs'))
            $pdo->prepare('INSERT INTO reparation_logs(reparation_id,statut,commentaire,auteur,created_at) VALUES(?,?,?,?,NOW())')
                ->execute([$repId, $finalStat, trim($_POST['commentaire_cloture'] ?? 'Clôture réparation'), 'admin']);
        if (tableOk($pdo, 'equipement_logs'))
            $pdo->prepare("INSERT INTO equipement_logs(equipement_id,type_event,titre,details,auteur) VALUES(?,'reparation','Réparation clôturée',?,?)")
                ->execute([$eid, 'Statut final : ' . $finalStat, 'admin']);
        goAdmin('✅ Réparation clôturée avec succès', 'reparations');
    } catch (Exception $e) { goAdmin('Erreur : ' . $e->getMessage(), 'reparations'); }
}

/* ═══════════════════════════════════════════════════════
   IMPORT — SAUVEGARDE + RESET BASE DE DONNÉES
═══════════════════════════════════════════════════════ */
if ($action === 'db_backup_reset') {
    try {
        // 1. Génération du dump SQL (tables principales)
        $tables = ['equipements', 'utilisateurs', 'reparations', 'reparation_logs', 'equipement_logs', 'movements'];
        $sql    = "-- Sauvegarde automatique Traçabilité Sodiaal\n";
        $sql   .= "-- Date : " . date('Y-m-d H:i:s') . "\n\n";
        $sql   .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $tbl) {
            if (!tableOk($pdo, $tbl)) continue;

            // Structure
            $create = $pdo->query("SHOW CREATE TABLE `$tbl`")->fetch(PDO::FETCH_NUM);
            $sql   .= "DROP TABLE IF EXISTS `$tbl`;\n";
            $sql   .= $create[1] . ";\n\n";

            // Données
            $rows = $pdo->query("SELECT * FROM `$tbl`")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $cols  = '`' . implode('`,`', array_keys($rows[0])) . '`';
                $sql  .= "INSERT INTO `$tbl` ($cols) VALUES\n";
                $chunks = [];
                foreach ($rows as $row) {
                    $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), $row);
                    $chunks[] = '(' . implode(',', $vals) . ')';
                }
                $sql .= implode(",\n", $chunks) . ";\n\n";
            }
        }
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        // 2. Enregistrement du fichier de sauvegarde
        $backupDir = __DIR__ . '/backups';
        if (!is_dir($backupDir)) mkdir($backupDir, 0750, true);
        $filename  = 'backup_' . date('Ymd_His') . '.sql';
        $filepath  = $backupDir . '/' . $filename;
        file_put_contents($filepath, $sql);

        // 3. Reset des tables (vidage dans l'ordre pour respecter les FK)
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        $resetTables = ['reparation_logs', 'equipement_logs', 'movements', 'reparations', 'equipements', 'utilisateurs'];
        foreach ($resetTables as $tbl)
            if (tableOk($pdo, $tbl)) $pdo->exec("TRUNCATE TABLE `$tbl`");
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

        goAdmin("✅ Sauvegarde créée ($filename) et base réinitialisée avec succès.", 'import');
    } catch (Exception $e) {
        goAdmin('❌ Erreur lors du reset : ' . $e->getMessage(), 'import');
    }
}

/* ═══════════════════════════════════════════════════════
   IMPORT — FICHIER CSV
═══════════════════════════════════════════════════════ */
if ($action === 'import_csv') {
    // Colonnes obligatoires (mapping flexible nom_csv => nom_bdd)
    $knownCols = [
        'nom'                   => 'nom',
        'type'                  => 'type',
        'numero_serie'          => 'numero_serie',
        'n_serie'               => 'numero_serie',
        'serie'                 => 'numero_serie',
        'localisation'          => 'localisation',
        'site'                  => 'localisation',
        'statut'                => 'statut',
        'qr_code'               => 'qr_code',
        'qr'                    => 'qr_code',
        'marque'                => 'marque',
        'modele'                => 'modele',
        'modèle'                => 'modele',
        'categorie'             => 'categorie',
        'catégorie'             => 'categorie',
        'date_achat'            => 'date_achat',
        'garantie_fin'          => 'garantie_fin',
        'commentaire_technique' => 'commentaire_technique',
        'commentaire'           => 'commentaire_technique',
        'criticite'             => 'criticite',
        'criticité'             => 'criticite',
        'etat_reparation'       => 'etat_reparation',
        'etat'                  => 'etat_reparation',
        'état'                  => 'etat_reparation',
    ];

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        goAdmin('❌ Aucun fichier CSV reçu ou erreur d\'upload.', 'import');
    }

    $tmpPath   = $_FILES['csv_file']['tmp_name'];
    $separator = ($_POST['csv_sep'] ?? ';') ?: ';';
    $handle    = fopen($tmpPath, 'r');

    if (!$handle) goAdmin('❌ Impossible de lire le fichier CSV.', 'import');

    // BOM UTF-8
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($handle);

    $header = fgetcsv($handle, 0, $separator);
    if (!$header) { fclose($handle); goAdmin('❌ Fichier CSV vide ou entête introuvable.', 'import'); }

    // Nettoyage entêtes
    $header = array_map(fn($h) => mb_strtolower(trim(str_replace([' ', '-'], '_', $h))), $header);

    // Résolution colonnes disponibles en BDD
    $colMap = []; // index_csv => nom_bdd
    foreach ($header as $idx => $h) {
        $bddCol = $knownCols[$h] ?? null;
        if ($bddCol && ($bddCol === 'nom' || $bddCol === 'type' || $bddCol === 'numero_serie' || $bddCol === 'localisation' || $bddCol === 'statut' || $bddCol === 'qr_code' || colOk($pdo, 'equipements', $bddCol))) {
            $colMap[$idx] = $bddCol;
        }
    }

    if (!in_array('nom', $colMap)) {
        fclose($handle);
        goAdmin('❌ Colonne "nom" introuvable dans le CSV. Vérifiez les entêtes.', 'import');
    }

    $inserted = 0;
    $skipped  = 0;
    $errors   = [];

    $pdo->beginTransaction();
    try {
        while (($row = fgetcsv($handle, 0, $separator)) !== false) {
            if (count(array_filter($row, fn($v) => trim($v) !== '')) === 0) continue; // ligne vide

            $data = [];
            foreach ($colMap as $csvIdx => $bddCol) {
                $val = trim($row[$csvIdx] ?? '');
                if ($val !== '') $data[$bddCol] = $val;
            }

            if (empty($data['nom'])) { $skipped++; continue; }

            // QR code auto
            if (empty($data['qr_code']))
                $data['qr_code'] = 'QR-' . uniqid();

            // Statut par défaut
            if (empty($data['statut']))
                $data['statut'] = 'Disponible';

            // etat_reparation par défaut
            if (colOk($pdo, 'equipements', 'etat_reparation') && empty($data['etat_reparation']))
                $data['etat_reparation'] = 'RAS';

            // Exclure colonnes inexistantes en BDD
            $finalData = [];
            foreach ($data as $col => $val) {
                if (in_array($col, ['nom', 'type', 'numero_serie', 'localisation', 'statut', 'qr_code']) || colOk($pdo, 'equipements', $col))
                    $finalData[$col] = $val;
            }

            $cols = implode(',', array_keys($finalData));
            $plh  = implode(',', array_fill(0, count($finalData), '?'));
            $pdo->prepare("INSERT INTO equipements($cols) VALUES($plh)")->execute(array_values($finalData));
            $inserted++;
        }
        $pdo->commit();
        fclose($handle);
        goAdmin("✅ Import CSV terminé : $inserted équipement(s) importé(s)" . ($skipped ? ", $skipped ligne(s) ignorée(s)." : '.'), 'import');
    } catch (Exception $e) {
        $pdo->rollBack();
        fclose($handle);
        goAdmin('❌ Erreur lors de l\'import : ' . $e->getMessage(), 'import');
    }
}

/* ═══════════════════════════════════
   CHARGEMENT DES DONNÉES PAR ONGLET
═══════════════════════════════════ */
$tab      = $_GET['tab'] ?? 'equipements';
$flashMsg = $_SESSION['admin_msg'];
$_SESSION['admin_msg'] = '';

$equipements = [];
try {
    $eCols  = colOk($pdo, 'equipements', 'etat_reparation') ? 'e.etat_reparation,' : "'RAS' AS etat_reparation,";
    $eCols2 = colOk($pdo, 'equipements', 'marque') ? 'e.marque,e.modele,e.criticite,' : "'' AS marque,'' AS modele,'' AS criticite,";
    $equipements = $pdo->query(
        "SELECT e.id,e.nom,e.type,e.numero_serie,e.localisation,e.statut,e.qr_code,
                e.utilisateur_id,e.date_attribution,{$eCols}{$eCols2}
                u.nom AS u_nom,u.prenom AS u_prenom
         FROM equipements e LEFT JOIN utilisateurs u ON u.id=e.utilisateur_id
         ORDER BY e.nom ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $equipements = []; }

$utilisateurs = [];
try {
    $utilisateurs = $pdo->query(
        "SELECT u.id,u.nom,u.prenom,u.service, COUNT(eq.id) AS nb
         FROM utilisateurs u LEFT JOIN equipements eq ON eq.utilisateur_id=u.id
         GROUP BY u.id ORDER BY u.nom ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

if (!isset($_SESSION['has_ticket_col'])) {
    try { $pdo->query("SELECT numero_ticket FROM reparations LIMIT 0"); $_SESSION['has_ticket_col'] = 1; }
    catch (PDOException $ex) { $_SESSION['has_ticket_col'] = 0; }
}
$reparations = [];
$repTableOk  = tableOk($pdo, 'reparations');
$hasTicketCol = $repTableOk && (bool)($_SESSION['has_ticket_col'] ?? 0);

if ($repTableOk) {
    try {
        $reparations = $pdo->query(
            "SELECT r.*,e.nom AS e_nom,e.type AS e_type,e.qr_code AS e_qr,e.numero_serie AS e_serie
             FROM reparations r
             JOIN equipements e ON e.id=r.equipement_id
             WHERE r.statut NOT IN ('Réparé','Restitué','Clôturé')
             ORDER BY FIELD(r.statut,'A diagnostiquer','En réparation','En attente de pièces','Test après réparation') DESC,r.date_ouverture ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { $reparations = []; }
}

$closedRepairs = [];
if ($repTableOk) {
    try {
        $tcSel        = $hasTicketCol ? "r.numero_ticket," : "'' AS numero_ticket,";
        $closedRepairs = $pdo->query(
            "SELECT r.id,r.statut,r.panne_declaree,r.diagnostic,r.pieces_changees,
                    r.technicien,r.date_ouverture,r.date_cloture,r.cout_reparation,{$tcSel}
                    e.nom AS e_nom,e.type AS e_type,e.localisation AS e_loc,e.qr_code AS e_qr
             FROM reparations r
             JOIN equipements e ON e.id=r.equipement_id
             WHERE r.statut IN ('Réparé','Restitué','Clôturé','Hors service')
             ORDER BY r.date_cloture DESC
             LIMIT 80"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { $closedRepairs = []; }
}

/* Historique QR */
$histSearch = trim($_GET['qr_search'] ?? '');
$histEquip  = null; $histLogs = []; $histMovs = []; $histReps = [];
if ($histSearch !== '') {
    try {
        $eHist = $pdo->prepare("SELECT e.*,u.nom AS u_nom,u.prenom AS u_prenom,u.service AS u_service FROM equipements e LEFT JOIN utilisateurs u ON u.id=e.utilisateur_id WHERE e.qr_code=? OR e.numero_serie=? OR e.nom LIKE ? LIMIT 1");
        $eHist->execute([$histSearch, $histSearch, '%' . $histSearch . '%']);
        $histEquip = $eHist->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {}
    if ($histEquip) {
        try { $s = $pdo->prepare("SELECT * FROM equipement_logs WHERE equipement_id=? ORDER BY created_at DESC LIMIT 30"); $s->execute([$histEquip['id']]); $histLogs = $s->fetchAll(); } catch (PDOException $e) {}
        try { $s = $pdo->prepare("SELECT * FROM movements WHERE equipement_id=? ORDER BY date_action DESC LIMIT 20"); $s->execute([$histEquip['id']]); $histMovs = $s->fetchAll(); } catch (PDOException $e) {}
        if ($repTableOk) { try { $s = $pdo->prepare("SELECT * FROM reparations WHERE equipement_id=? ORDER BY date_ouverture DESC"); $s->execute([$histEquip['id']]); $histReps = $s->fetchAll(); } catch (PDOException $e) {} }
    }
}

/* Stats pour l'onglet import */
$dbStats = [];
try {
    $dbStats['equipements']  = (int)$pdo->query("SELECT COUNT(*) FROM equipements")->fetchColumn();
    $dbStats['utilisateurs'] = (int)$pdo->query("SELECT COUNT(*) FROM utilisateurs")->fetchColumn();
    $dbStats['reparations']  = $repTableOk ? (int)$pdo->query("SELECT COUNT(*) FROM reparations")->fetchColumn() : 0;
} catch (PDOException $e) {}

/* Sauvegardes existantes */
$backupFiles = [];
$backupDir   = __DIR__ . '/backups';
if (is_dir($backupDir)) {
    $files = glob($backupDir . '/backup_*.sql');
    if ($files) {
        rsort($files);
        foreach (array_slice($files, 0, 10) as $f)
            $backupFiles[] = ['name' => basename($f), 'size' => round(filesize($f) / 1024, 1), 'date' => date('d/m/Y H:i', filemtime($f))];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Administration — Traçabilité Sodiaal</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--bg1:#070b18;--bg2:#111827;--card:rgba(15,23,42,.78);--line:rgba(255,255,255,.10);--txt:#e5e7eb;--muted:#94a3b8}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{min-height:100vh;color:var(--txt);font-family:system-ui,-apple-system,sans-serif;font-size:.95rem;
     background:radial-gradient(circle at 12% 12%,rgba(239,68,68,.18),transparent 24%),
                radial-gradient(circle at 88% 18%,rgba(168,85,247,.18),transparent 24%),
                linear-gradient(180deg,var(--bg1),var(--bg2))}
.glass{background:var(--card);backdrop-filter:blur(18px);border:1px solid var(--line);border-radius:24px;box-shadow:0 20px 50px rgba(0,0,0,.35)}
.section-title{font-weight:900;letter-spacing:-.02em;margin-bottom:14px}
.muted{color:var(--muted)}
/* Nav tabs */
.admin-nav{display:flex;gap:4px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:6px;flex-wrap:wrap}
.admin-nav a{padding:10px 18px;border-radius:12px;color:var(--muted);text-decoration:none;font-weight:700;font-size:.88rem;transition:all .18s}
.admin-nav a.active{background:linear-gradient(135deg,rgba(99,102,241,.35),rgba(168,85,247,.25));color:#fff;border:1px solid rgba(99,102,241,.4)}
.admin-nav a:hover:not(.active){background:rgba(255,255,255,.07);color:#fff}
/* Tables */
.table-wrap{overflow:hidden;border-radius:18px}
.table thead th{background:rgba(255,255,255,.07)!important;color:var(--muted);border-color:rgba(255,255,255,.08);font-size:.8rem;text-transform:uppercase;letter-spacing:.06em}
.table tbody td{background:rgba(255,255,255,.02)!important;color:var(--txt);border-color:rgba(255,255,255,.06)}
.table tbody tr:hover td{background:rgba(255,255,255,.05)!important}
/* Forms */
.form-control,.form-select{background:rgba(255,255,255,.07)!important;color:#fff!important;border:1px solid rgba(255,255,255,.12)!important;border-radius:10px!important;padding:9px 12px!important;box-shadow:none!important}
.form-control::placeholder{color:#64748b}
.form-control:focus,.form-select:focus{border-color:rgba(99,102,241,.5)!important}
.form-select option{background:#1e293b;color:#fff}
.form-label{font-weight:700;font-size:.82rem;color:var(--muted);margin-bottom:4px}
.btn-primary{background:linear-gradient(135deg,#4f46e5,#7c3aed)!important;border:none!important;border-radius:10px;font-weight:700}
.btn-danger{border-radius:10px;font-weight:700}
.btn-success{border-radius:10px;font-weight:700}
.btn-warning{border-radius:10px;font-weight:700;color:#000}
.btn-secondary{border-radius:10px;font-weight:700}
.btn-sm{font-size:.78rem;padding:5px 12px}
.btn-outline-light{border-color:rgba(255,255,255,.15)!important;color:#fff!important;border-radius:10px;font-weight:700}
/* Réparation cards */
.rep-card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:14px;margin-bottom:10px;transition:border-color .2s}
.rep-card:hover{border-color:rgba(99,102,241,.35)}
.rep-card.haute{border-left:3px solid #f97316}
.rep-card.critique{border-left:3px solid #ef4444}
.wf-bar{display:flex;gap:3px;margin-top:10px}
.wf-bar div{flex:1;height:6px;border-radius:3px}
/* Timeline */
.tl-line{display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.06)}
.tl-line:last-child{border-bottom:none}
.tl-dot{width:30px;height:30px;border-radius:50%;display:grid;place-items:center;flex-shrink:0;font-size:.8rem}
/* Ticket badge */
.ticket-badge{display:inline-flex;align-items:center;gap:5px;background:rgba(6,182,212,.10);border:1px solid rgba(6,182,212,.28);color:#67e8f9;padding:3px 10px;border-radius:8px;font-size:.75rem;font-weight:700;font-family:monospace;cursor:pointer;user-select:all;transition:background .15s}
.ticket-badge:hover{background:rgba(6,182,212,.2)}
.flash{border-radius:12px;border:none;font-weight:600}
.search-bar{background:rgba(255,255,255,.06)!important;color:#fff!important;border:1px solid rgba(255,255,255,.12)!important;border-radius:12px;padding:9px 14px;box-shadow:none!important}
.search-bar::placeholder{color:#64748b}
/* ── Import ── */
.import-zone{border:2px dashed rgba(99,102,241,.4);border-radius:16px;padding:32px;text-align:center;transition:border-color .2s,background .2s;cursor:pointer}
.import-zone:hover,.import-zone.dragover{border-color:rgba(99,102,241,.9);background:rgba(99,102,241,.07)}
.import-zone input[type=file]{display:none}
.stat-pill{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.10);border-radius:14px;padding:16px 20px;text-align:center}
.stat-pill .val{font-size:1.8rem;font-weight:900;color:#a5b4fc}
.stat-pill .lbl{font-size:.75rem;color:var(--muted);text-transform:uppercase;letter-spacing:.08em}
.danger-zone{border:1px solid rgba(239,68,68,.3);border-radius:16px;padding:20px;background:rgba(239,68,68,.05)}
.backup-row{display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:rgba(255,255,255,.04);border-radius:10px;margin-bottom:6px;font-size:.85rem}
</style>
</head>
<body>
<div class="container-fluid py-4 px-3 px-md-4">

  <!-- Header -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <div style="font-size:.78rem;text-transform:uppercase;letter-spacing:.1em;color:#64748b;margin-bottom:4px">Sodiaal — Interface de traçabilité</div>
      <h1 class="h3 fw-black mb-0"><i class="bi bi-shield-lock me-2" style="color:#a5b4fc"></i>Administration</h1>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="index.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left me-1"></i>Tableau de bord</a>
      <form method="post" class="d-inline">
        <button type="submit" name="logout" class="btn btn-sm" style="background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#fca5a5;border-radius:10px">
          <i class="bi bi-box-arrow-right me-1"></i>Déconnexion
        </button>
      </form>
    </div>
  </div>

  <!-- Flash message -->
  <?php if ($flashMsg): ?>
  <div class="alert flash <?= str_contains($flashMsg, '❌') || str_contains($flashMsg, 'Erreur') ? 'alert-danger' : 'alert-success' ?> mb-4"><?= hv($flashMsg) ?></div>
  <?php endif; ?>

  <!-- Navigation onglets -->
  <nav class="admin-nav mb-4">
    <a href="admin.php?tab=equipements"  class="<?= $tab === 'equipements'  ? 'active' : '' ?>"><i class="bi bi-laptop me-1"></i>Équipements</a>
    <a href="admin.php?tab=utilisateurs" class="<?= $tab === 'utilisateurs' ? 'active' : '' ?>"><i class="bi bi-people me-1"></i>Utilisateurs</a>
    <a href="admin.php?tab=reparations"  class="<?= $tab === 'reparations'  ? 'active' : '' ?>">
      <i class="bi bi-tools me-1"></i>Réparations
      <?php if (!empty($reparations)): ?><span style="background:#ef4444;color:#fff;padding:1px 7px;border-radius:999px;font-size:.7rem;margin-left:4px"><?= count($reparations) ?></span><?php endif; ?>
    </a>
    <a href="admin.php?tab=historique"   class="<?= $tab === 'historique'   ? 'active' : '' ?>"><i class="bi bi-clock-history me-1"></i>Historique QR</a>
    <a href="admin.php?tab=import"       class="<?= $tab === 'import'       ? 'active' : '' ?>" style="<?= $tab === 'import' ? '' : 'color:#f59e0b' ?>">
      <i class="bi bi-database-up me-1"></i>Import de base de données
    </a>
  </nav>

  <!-- ════════════════════════════════════
       ONGLET ÉQUIPEMENTS
  ════════════════════════════════════ -->
  <?php if ($tab === 'equipements'): ?>
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="glass p-4">
        <div class="section-title"><i class="bi bi-plus-circle me-2" style="color:#4ade80"></i>Ajouter un équipement</div>
        <form method="post" action="admin.php?tab=equipements">
          <input type="hidden" name="action" value="add_equip">
          <div class="row g-2">
            <div class="col-12"><label class="form-label">Nom *</label><input type="text" name="nom" class="form-control" required placeholder="Dell Latitude 5520"></div>
            <div class="col-6"><label class="form-label">Type</label>
              <select name="type" class="form-select"><?php foreach ($types as $t): ?><option><?= hv($t) ?></option><?php endforeach; ?></select></div>
            <div class="col-6"><label class="form-label">Statut</label>
              <select name="statut" class="form-select"><?php foreach ($statuts as $s): ?><option><?= hv($s) ?></option><?php endforeach; ?></select></div>
            <div class="col-12"><label class="form-label">N° Série</label><input type="text" name="numero_serie" class="form-control" placeholder="SN-XXXXXXX"></div>
            <div class="col-6"><label class="form-label">Marque</label><input type="text" name="marque" class="form-control" placeholder="Dell"></div>
            <div class="col-6"><label class="form-label">Modèle</label><input type="text" name="modele" class="form-control" placeholder="Latitude 5520"></div>
            <div class="col-12"><label class="form-label">Site</label>
              <select name="localisation" class="form-select"><option value="">— Sélectionner —</option><?php foreach ($sites as $s): ?><option><?= hv($s) ?></option><?php endforeach; ?></select></div>
            <div class="col-6"><label class="form-label">Date achat</label><input type="date" name="date_achat" class="form-control"></div>
            <div class="col-6"><label class="form-label">Fin garantie</label><input type="date" name="garantie_fin" class="form-control"></div>
            <div class="col-12"><label class="form-label">QR Code (auto si vide)</label><input type="text" name="qr_code" class="form-control" placeholder="QR-…"></div>
            <div class="col-12 mt-2"><button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-circle me-1"></i>Ajouter</button></div>
          </div>
        </form>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="glass p-4">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
          <div class="section-title mb-0"><i class="bi bi-laptop me-2"></i>Équipements (<?= count($equipements) ?>)</div>
          <input type="text" id="equipSearch" class="form-control search-bar" style="max-width:220px" placeholder="Filtrer…" autocomplete="off">
        </div>
        <?php if (empty($equipements)): ?>
        <p class="muted text-center py-4"><i class="bi bi-inbox me-2"></i>Aucun équipement</p>
        <?php else: ?>
        <div class="table-wrap">
          <table class="table align-middle mb-0" id="equipTable">
            <thead><tr><th>Nom</th><th>Type</th><th>Site</th><th>Statut</th><th>Réparation</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($equipements as $e):
                $sd = strtolower(implode(' ', [$e['nom'] ?? '', $e['type'] ?? '', $e['localisation'] ?? '', $e['statut'] ?? '', $e['numero_serie'] ?? ''])); ?>
            <tr data-search="<?= hv($sd) ?>">
              <td>
                <div class="fw-bold"><?= hv($e['nom'] ?? '') ?></div>
                <div class="muted" style="font-size:.72rem"><?= hv(trim(($e['marque'] ?? '') . ' ' . ($e['modele'] ?? ''))) ?></div>
                <code style="color:#67e8f9;font-size:.72rem"><?= hv($e['numero_serie'] ?? '') ?></code>
              </td>
              <td class="muted small"><?= hv($e['type'] ?? '') ?></td>
              <td><?= siteBadge($e['localisation'] ?? '') ?></td>
              <td><span class="badge <?= ($e['statut'] ?? '') === 'Disponible' ? 'bg-success' : 'bg-warning text-dark' ?>"><?= hv($e['statut'] ?? '') ?></span></td>
              <td><?= reparBadge($e['etat_reparation'] ?? 'RAS') ?></td>
              <td>
                <div class="d-flex gap-1 flex-wrap">
                  <a href="equipement.php?id=<?= (int)$e['id'] ?>" class="btn btn-sm btn-success" title="Fiche"><i class="bi bi-card-text"></i></a>
                  <button type="button" class="btn btn-sm btn-primary" data-json="<?= htmlspecialchars(json_encode($e), ENT_QUOTES, 'UTF-8') ?>" onclick="openEditEquip(JSON.parse(this.dataset.json))"><i class="bi bi-pencil"></i></button>
                  <form method="post" onsubmit="return confirm('Supprimer cet équipement ?')">
                    <input type="hidden" name="action" value="delete_equip"><input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                  </form>
                  <a href="admin.php?tab=reparations" onclick="return openRepairFromEquip(<?= (int)$e['id'] ?>,<?= json_encode($e['nom'] ?? '', JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)" class="btn btn-sm btn-warning" title="Ouvrir réparation"><i class="bi bi-tools"></i></a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ════════════════════════════════════
       ONGLET UTILISATEURS
  ════════════════════════════════════ -->
  <?php elseif ($tab === 'utilisateurs'): ?>
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="glass p-4">
        <div class="section-title"><i class="bi bi-person-plus me-2" style="color:#4ade80"></i>Ajouter un utilisateur</div>
        <form method="post" action="admin.php?tab=utilisateurs">
          <input type="hidden" name="action" value="add_user">
          <div class="mb-2"><label class="form-label">Prénom *</label><input type="text" name="prenom" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Nom *</label><input type="text" name="nom" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">Service</label><input type="text" name="service" class="form-control" placeholder="DSI, RH, Finance…"></div>
          <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-circle me-1"></i>Ajouter</button>
        </form>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="glass p-4">
        <div class="section-title mb-3"><i class="bi bi-people me-2"></i>Utilisateurs (<?= count($utilisateurs) ?>)</div>
        <?php if (empty($utilisateurs)): ?>
        <p class="muted text-center py-4"><i class="bi bi-inbox me-2"></i>Aucun utilisateur</p>
        <?php else: ?>
        <div class="table-wrap">
          <table class="table align-middle mb-0">
            <thead><tr><th>Prénom</th><th>Nom</th><th>Service</th><th>Appareils</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($utilisateurs as $u): ?>
            <tr>
              <td><?= hv($u['prenom'] ?? '') ?></td>
              <td class="fw-bold"><?= hv($u['nom'] ?? '') ?></td>
              <td class="muted"><?= hv($u['service'] ?? '—') ?></td>
              <td><span class="badge bg-primary"><?= (int)$u['nb'] ?></span></td>
              <td>
                <div class="d-flex gap-1">
                  <button type="button" class="btn btn-sm btn-primary" data-json="<?= htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8') ?>" onclick="openEditUser(JSON.parse(this.dataset.json))"><i class="bi bi-pencil"></i></button>
                  <form method="post" onsubmit="return confirm('Supprimer cet utilisateur ?')">
                    <input type="hidden" name="action" value="delete_user"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ════════════════════════════════════
       ONGLET RÉPARATIONS
  ════════════════════════════════════ -->
  <?php elseif ($tab === 'reparations'): ?>
  <?php if (!$repTableOk): ?>
  <div class="alert alert-warning">La table <code>reparations</code> est introuvable. Exécutez votre script SQL de mise à jour.</div>
  <?php else: ?>
  <div class="row g-4">
    <!-- Formulaire ouverture -->
    <div class="col-lg-4">
      <div class="glass p-4">
        <div class="section-title"><i class="bi bi-plus-circle me-2" style="color:#fbbf24"></i>Ouvrir une réparation</div>
        <form method="post" action="admin.php?tab=reparations">
          <input type="hidden" name="action" value="open_repair">
          <div class="mb-2"><label class="form-label">Équipement *</label>
            <select name="equipement_id" id="repEquipSelect" class="form-select" required>
              <option value="">— Choisir —</option>
              <?php foreach ($equipements as $eq): ?>
              <option value="<?= (int)$eq['id'] ?>"><?= hv($eq['nom']) ?><?= $eq['numero_serie'] ? ' — ' . hv($eq['numero_serie']) : '' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-2"><label class="form-label">Panne déclarée *</label><textarea name="panne_declaree" class="form-control" rows="2" required></textarea></div>
          <div class="mb-2"><label class="form-label">Technicien</label><input type="text" name="technicien" class="form-control" placeholder="Nom du tech…"></div>
          <?php if ($hasTicketCol): ?>
          <div class="mb-2"><label class="form-label">N° Ticket</label><input type="text" name="numero_ticket" class="form-control" placeholder="INC-XXXXXXX"></div>
          <?php endif; ?>
          <div class="mb-3"><label class="form-label">Priorité</label>
            <select name="priorite" class="form-select"><?php foreach ($priorList as $p): ?><option><?= hv($p) ?></option><?php endforeach; ?></select>
          </div>
          <button type="submit" class="btn btn-warning w-100 fw-bold"><i class="bi bi-tools me-1"></i>Ouvrir la réparation</button>
        </form>
      </div>
    </div>
    <!-- Réparations en cours -->
    <div class="col-lg-8">
      <div class="glass p-4">
        <div class="section-title"><i class="bi bi-wrench me-2" style="color:#f59e0b"></i>En cours (<?= count($reparations) ?>)</div>
        <?php if (empty($reparations)): ?>
        <p class="muted text-center py-4"><i class="bi bi-check-circle me-2"></i>Aucune réparation active</p>
        <?php else: ?>
        <?php
        $wfIdx = array_flip($wfSteps);
        foreach ($reparations as $r):
            $prior = $r['priorite'] ?? 'Normale';
            $cardClass = $prior === 'Critique' ? 'critique' : ($prior === 'Haute' ? 'haute' : '');
            $wfPos = ($wfIdx[$r['statut']] ?? -1) + 1;
        ?>
        <div class="rep-card <?= $cardClass ?>">
          <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap">
            <div>
              <span class="fw-bold"><?= hv($r['e_nom'] ?? '') ?></span>
              <span class="muted ms-2 small"><?= hv($r['e_type'] ?? '') ?></span>
              <?php if ($r['e_serie'] ?? ''): ?><code style="color:#67e8f9;font-size:.72rem;margin-left:6px"><?= hv($r['e_serie']) ?></code><?php endif; ?>
              <?php if (!empty($r['numero_ticket'])): ?>
              <span class="ticket-badge ms-2" onclick="copyTicket(this,'<?= hv($r['numero_ticket']) ?>')" title="Cliquer pour copier">
                <i class="bi bi-ticket-perforated"></i><span><?= hv($r['numero_ticket']) ?></span>
              </span>
              <?php endif; ?>
            </div>
            <div class="d-flex gap-1 flex-wrap">
              <?= reparBadge($r['statut'] ?? '') ?>
              <?php if ($prior !== 'Normale'): ?>
              <span style="background:<?= $prior === 'Critique' ? 'rgba(239,68,68,.2)' : 'rgba(249,115,22,.2)' ?>;border:1px solid <?= $prior === 'Critique' ? 'rgba(239,68,68,.4)' : 'rgba(249,115,22,.4)' ?>;color:<?= $prior === 'Critique' ? '#fca5a5' : '#fed7aa' ?>;padding:2px 8px;border-radius:999px;font-size:.72rem;font-weight:700"><?= hv($prior) ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div class="muted small mt-1"><?= hv(mb_substr($r['panne_declaree'] ?? '', 0, 120)) ?></div>
          <?php if ($r['technicien'] ?? ''): ?><div class="muted" style="font-size:.75rem;margin-top:2px"><i class="bi bi-person me-1"></i><?= hv($r['technicien']) ?></div><?php endif; ?>
          <!-- Barre workflow -->
          <div class="wf-bar">
            <?php foreach ($wfSteps as $i => $step):
                $done = ($i + 1) <= $wfPos;
                $active = ($i + 1) === $wfPos;
            ?>
            <div style="background:<?= $active ? '#f59e0b' : ($done ? '#22c55e' : 'rgba(255,255,255,.10)') ?>;flex:1;height:6px;border-radius:3px" title="<?= hv($step) ?>"></div>
            <?php endforeach; ?>
          </div>
          <!-- Actions -->
          <div class="d-flex gap-2 mt-3 flex-wrap">
            <form method="post" action="admin.php?tab=reparations" class="d-flex gap-2 align-items-center flex-wrap">
              <input type="hidden" name="action" value="update_repair">
              <input type="hidden" name="rep_id" value="<?= (int)$r['id'] ?>">
              <input type="hidden" name="equipement_id" value="<?= (int)$r['equipement_id'] ?>">
              <select name="nouveau_statut" class="form-select form-select-sm" style="width:auto">
                <?php foreach ($wfSteps as $s): ?><option<?= $r['statut'] === $s ? ' selected' : '' ?>><?= hv($s) ?></option><?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-sm btn-primary">Mettre à jour</button>
            </form>
            <button type="button" class="btn btn-sm btn-success"
              onclick="openCloseRepair({rid:<?= (int)$r['id'] ?>,eid:<?= (int)$r['equipement_id'] ?>,nom:<?= json_encode($r['e_nom'] ?? '', JSON_HEX_QUOT | JSON_HEX_TAG) ?>,diag:<?= json_encode($r['diagnostic'] ?? '', JSON_HEX_QUOT | JSON_HEX_TAG) ?>,pieces:<?= json_encode($r['pieces_changees'] ?? '', JSON_HEX_QUOT | JSON_HEX_TAG) ?>,tech:<?= json_encode($r['technicien'] ?? '', JSON_HEX_QUOT | JSON_HEX_TAG) ?>,cout:<?= json_encode($r['cout_reparation'] ?? '', JSON_HEX_QUOT | JSON_HEX_TAG) ?>})">
              <i class="bi bi-check2-circle me-1"></i>Clôturer
            </button>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <!-- Réparations clôturées -->
      <?php if (!empty($closedRepairs)): ?>
      <div class="glass p-4 mt-4">
        <div class="section-title"><i class="bi bi-archive me-2" style="color:#22c55e"></i>Clôturées (<?= count($closedRepairs) ?>)</div>
        <div class="table-wrap">
          <table class="table align-middle mb-0">
            <thead><tr><th>Équipement</th><th>Panne</th><th>Statut</th><th>Technicien</th><th>Clôture</th></tr></thead>
            <tbody>
            <?php foreach ($closedRepairs as $r): ?>
            <tr>
              <td class="fw-bold"><?= hv($r['e_nom'] ?? '') ?></td>
              <td class="muted small"><?= hv(mb_substr($r['panne_declaree'] ?? '', 0, 60)) ?></td>
              <td><?= reparBadge($r['statut'] ?? '') ?></td>
              <td class="muted small"><?= hv($r['technicien'] ?? '—') ?></td>
              <td class="muted small"><?= $r['date_cloture'] ? date('d/m/Y', strtotime($r['date_cloture'])) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ════════════════════════════════════
       ONGLET HISTORIQUE QR
  ════════════════════════════════════ -->
  <?php elseif ($tab === 'historique'): ?>
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="glass p-4">
        <div class="section-title"><i class="bi bi-qr-code me-2" style="color:#67e8f9"></i>Recherche QR / Série</div>
        <form method="get" action="admin.php">
          <input type="hidden" name="tab" value="historique">
          <div class="mb-3"><input type="text" name="qr_search" class="form-control" placeholder="QR-XXX ou N° série ou nom…" value="<?= hv($histSearch) ?>"></div>
          <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Rechercher</button>
        </form>
      </div>
    </div>
    <div class="col-lg-8">
      <?php if ($histSearch !== '' && $histEquip === null): ?>
      <div class="glass p-4"><p class="muted text-center py-4"><i class="bi bi-search me-2"></i>Aucun équipement trouvé pour « <?= hv($histSearch) ?> »</p></div>
      <?php elseif ($histEquip): ?>
      <div class="glass p-4">
        <div class="section-title"><i class="bi bi-card-text me-2"></i><?= hv($histEquip['nom'] ?? '') ?></div>
        <div class="row g-2 mb-3">
          <div class="col-auto"><code style="color:#67e8f9"><?= hv($histEquip['qr_code'] ?? '') ?></code></div>
          <div class="col-auto"><?= siteBadge($histEquip['localisation'] ?? '') ?></div>
          <div class="col-auto"><?= reparBadge($histEquip['etat_reparation'] ?? 'RAS') ?></div>
        </div>
        <?php if (!empty($histLogs)): ?>
        <div class="section-title mt-3" style="font-size:.85rem"><i class="bi bi-list-ul me-1"></i>Logs</div>
        <?php foreach ($histLogs as $l): ?>
        <div class="tl-line">
          <div class="tl-dot" style="background:rgba(99,102,241,.2)"><i class="bi bi-dot" style="color:#a5b4fc"></i></div>
          <div>
            <div class="fw-bold" style="font-size:.85rem"><?= hv($l['titre'] ?? $l['type_event'] ?? '') ?></div>
            <div class="muted" style="font-size:.75rem"><?= hv($l['details'] ?? '') ?></div>
            <div class="muted" style="font-size:.72rem"><?= $l['created_at'] ?? '' ?></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
        <?php if (!empty($histReps)): ?>
        <div class="section-title mt-4" style="font-size:.85rem"><i class="bi bi-tools me-1"></i>Réparations</div>
        <?php foreach ($histReps as $r): ?>
        <div class="rep-card" style="margin-bottom:8px">
          <div class="d-flex justify-content-between flex-wrap gap-1">
            <span class="muted small"><?= $r['date_ouverture'] ? date('d/m/Y', strtotime($r['date_ouverture'])) : '' ?></span>
            <?= reparBadge($r['statut'] ?? '') ?>
          </div>
          <div class="muted small"><?= hv(mb_substr($r['panne_declaree'] ?? '', 0, 100)) ?></div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div class="glass p-4"><p class="muted text-center py-4"><i class="bi bi-search me-2"></i>Entrez un QR code, un numéro de série ou un nom d'équipement.</p></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ════════════════════════════════════
       ONGLET IMPORT DE BASE DE DONNÉES
  ════════════════════════════════════ -->
  <?php elseif ($tab === 'import'): ?>

  <!-- Statistiques base -->
  <div class="row g-3 mb-4">
    <div class="col-sm-4">
      <div class="stat-pill">
        <div class="val"><?= $dbStats['equipements'] ?? 0 ?></div>
        <div class="lbl"><i class="bi bi-laptop me-1"></i>Équipements</div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="stat-pill">
        <div class="val"><?= $dbStats['utilisateurs'] ?? 0 ?></div>
        <div class="lbl"><i class="bi bi-people me-1"></i>Utilisateurs</div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="stat-pill">
        <div class="val"><?= $dbStats['reparations'] ?? 0 ?></div>
        <div class="lbl"><i class="bi bi-tools me-1"></i>Réparations</div>
      </div>
    </div>
  </div>

  <div class="row g-4">

    <!-- ── Colonne gauche : Import CSV ── -->
    <div class="col-lg-6">
      <div class="glass p-4">
        <div class="section-title"><i class="bi bi-file-earmark-spreadsheet me-2" style="color:#4ade80"></i>Import CSV — Équipements</div>
        <p class="muted small mb-3">Importez un grand nombre d'équipements en une seule fois à partir d'un fichier CSV. Les colonnes reconnues sont détectées automatiquement.</p>

        <form method="post" action="admin.php?tab=import" enctype="multipart/form-data" id="csvForm">
          <input type="hidden" name="action" value="import_csv">

          <!-- Zone de dépôt -->
          <label class="import-zone mb-3" id="dropZone" for="csvFileInput">
            <input type="file" name="csv_file" id="csvFileInput" accept=".csv,text/csv">
            <i class="bi bi-upload" style="font-size:2.5rem;color:#6366f1;display:block;margin-bottom:10px"></i>
            <div class="fw-bold">Cliquez ou déposez votre fichier CSV ici</div>
            <div class="muted small mt-1" id="fileNameDisplay">Aucun fichier sélectionné</div>
          </label>

          <div class="mb-3">
            <label class="form-label">Séparateur de colonnes</label>
            <select name="csv_sep" class="form-select">
              <option value=";">Point-virgule ( ; ) — par défaut Excel FR</option>
              <option value=",">Virgule ( , ) — CSV standard</option>
              <option value="&#9;">Tabulation ( TAB )</option>
            </select>
          </div>

          <button type="submit" class="btn btn-success w-100 fw-bold" id="importBtn">
            <i class="bi bi-cloud-upload me-2"></i>Lancer l'import CSV
          </button>
        </form>

        <!-- Aide colonnes -->
        <div class="mt-4">
          <div class="section-title" style="font-size:.82rem;color:var(--muted)"><i class="bi bi-info-circle me-1"></i>Colonnes reconnues</div>
          <div class="row g-1">
            <?php
            $colsDoc = [
                ['nom',                   'Obligatoire', '#22c55e'],
                ['type',                  'Obligatoire', '#22c55e'],
                ['numero_serie',          'Obligatoire', '#22c55e'],
                ['localisation / site',   'Obligatoire', '#22c55e'],
                ['statut',                'Optionnel',   '#f59e0b'],
                ['qr_code / qr',          'Optionnel',   '#f59e0b'],
                ['marque',                'Optionnel',   '#f59e0b'],
                ['modele',                'Optionnel',   '#f59e0b'],
                ['categorie',             'Optionnel',   '#f59e0b'],
                ['date_achat',            'Optionnel',   '#f59e0b'],
                ['garantie_fin',          'Optionnel',   '#f59e0b'],
                ['commentaire_technique', 'Optionnel',   '#f59e0b'],
                ['criticite',             'Optionnel',   '#f59e0b'],
                ['etat_reparation',       'Optionnel',   '#f59e0b'],
            ];
            foreach ($colsDoc as [$col, $req, $color]): ?>
            <div class="col-6">
              <div style="background:rgba(255,255,255,.04);border:1px solid <?= $color ?>33;border-radius:8px;padding:5px 9px;font-size:.75rem;margin-bottom:4px">
                <code style="color:<?= $color ?>"><?= $col ?></code>
                <span style="color:var(--muted);font-size:.68rem;display:block"><?= $req ?></span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <p class="muted mt-2" style="font-size:.75rem"><i class="bi bi-lightbulb me-1"></i>Les colonnes absentes dans votre base sont ignorées automatiquement. QR code généré automatiquement si absent.</p>
        </div>
      </div>
    </div>

    <!-- ── Colonne droite : Sauvegarde + Reset ── -->
    <div class="col-lg-6">
      <div class="glass p-4">
        <div class="section-title"><i class="bi bi-database me-2" style="color:#f59e0b"></i>Sauvegarde &amp; Réinitialisation</div>
        <p class="muted small mb-4">Créez une sauvegarde complète de la base de données au format <code>.sql</code>, puis videz toutes les tables principales.</p>

        <div class="danger-zone">
          <div class="d-flex align-items-start gap-3 mb-3">
            <div style="width:42px;height:42px;background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);border-radius:12px;display:grid;place-items:center;flex-shrink:0">
              <i class="bi bi-exclamation-triangle-fill" style="color:#fca5a5;font-size:1.1rem"></i>
            </div>
            <div>
              <div class="fw-bold" style="color:#fca5a5">Zone de danger</div>
              <div class="muted small">Cette opération va vider toutes les tables. Un fichier <code>.sql</code> de sauvegarde sera automatiquement créé <strong>avant</strong> la suppression.</div>
            </div>
          </div>

          <div class="mb-3" style="background:rgba(255,255,255,.04);border-radius:10px;padding:12px">
            <div class="fw-bold small mb-2"><i class="bi bi-list-check me-1"></i>Tables qui seront vidées :</div>
            <?php
            $resetTablesList = ['equipements', 'utilisateurs', 'reparations', 'reparation_logs', 'equipement_logs', 'movements'];
            foreach ($resetTablesList as $tbl):
                $exists = tableOk($pdo, $tbl); ?>
            <span style="background:<?= $exists ? 'rgba(239,68,68,.15)' : 'rgba(255,255,255,.05)' ?>;border:1px solid <?= $exists ? 'rgba(239,68,68,.3)' : 'rgba(255,255,255,.10)' ?>;color:<?= $exists ? '#fca5a5' : '#64748b' ?>;padding:3px 9px;border-radius:8px;font-size:.75rem;font-family:monospace;margin:2px;display:inline-block">
              <?= $exists ? '🗑️' : '—' ?> <?= $tbl ?>
            </span>
            <?php endforeach; ?>
          </div>

          <form method="post" action="admin.php?tab=import" onsubmit="return confirmReset()">
            <input type="hidden" name="action" value="db_backup_reset">
            <button type="submit" class="btn btn-danger w-100 fw-bold">
              <i class="bi bi-arrow-counterclockwise me-2"></i>Sauvegarder la base et réinitialiser
            </button>
          </form>
        </div>

        <!-- Sauvegardes existantes -->
        <div class="mt-4">
          <div class="section-title" style="font-size:.85rem"><i class="bi bi-archive me-2" style="color:#a5b4fc"></i>Sauvegardes récentes
            <span style="font-weight:400;color:var(--muted);font-size:.75rem">(dossier <code>backups/</code>)</span>
          </div>
          <?php if (empty($backupFiles)): ?>
          <p class="muted small"><i class="bi bi-inbox me-1"></i>Aucune sauvegarde pour l'instant.</p>
          <?php else: ?>
          <?php foreach ($backupFiles as $bf): ?>
          <div class="backup-row">
            <div>
              <i class="bi bi-file-earmark-code me-2" style="color:#a5b4fc"></i>
              <code style="font-size:.8rem;color:#e2e8f0"><?= hv($bf['name']) ?></code>
            </div>
            <div class="d-flex align-items-center gap-3">
              <span class="muted" style="font-size:.75rem"><?= hv($bf['date']) ?></span>
              <span style="background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.25);color:#a5b4fc;padding:2px 8px;border-radius:6px;font-size:.72rem"><?= $bf['size'] ?> Ko</span>
              <a href="backups/<?= urlencode($bf['name']) ?>" class="btn btn-sm btn-outline-light" style="padding:3px 10px;font-size:.75rem" title="Télécharger" download>
                <i class="bi bi-download"></i>
              </a>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <?php endif; ?>
</div>

<!-- MODAL CLÔTURE RÉPARATION -->
<div class="modal fade" id="modalCloseRepair" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background:#1e293b;border:1px solid rgba(255,255,255,.12);border-radius:20px;color:var(--txt)">
      <div class="modal-header" style="border-bottom:1px solid rgba(255,255,255,.08)">
        <h5 class="modal-title fw-bold"><i class="bi bi-check2-circle me-2" style="color:#4ade80"></i>Clôturer — <span id="closeEquipNom"></span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="admin.php?tab=reparations">
        <input type="hidden" name="action" value="close_repair">
        <input type="hidden" name="rep_id" id="closeRepId">
        <input type="hidden" name="equipement_id" id="closeEquipId">
        <div class="modal-body">
          <div class="row g-2">
            <div class="col-12"><label class="form-label">Statut final</label>
              <select name="final_statut" class="form-select">
                <option>Réparé</option><option>Restitué</option><option>Hors service</option>
              </select></div>
            <div class="col-12"><label class="form-label">Diagnostic</label><textarea name="diagnostic" id="closeRepDiag" class="form-control" rows="2"></textarea></div>
            <div class="col-12"><label class="form-label">Pièces changées</label><input type="text" name="pieces_changees" id="closeRepPieces" class="form-control"></div>
            <div class="col-6"><label class="form-label">Technicien</label><input type="text" name="technicien" id="closeRepTech" class="form-control"></div>
            <div class="col-6"><label class="form-label">Coût (€)</label><input type="number" name="cout_reparation" id="closeRepCout" class="form-control" step="0.01" min="0"></div>
            <div class="col-12"><label class="form-label">Commentaire clôture</label><textarea name="commentaire_cloture" class="form-control" rows="2"></textarea></div>
          </div>
        </div>
        <div class="modal-footer" style="border-top:1px solid rgba(255,255,255,.08)">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn btn-success"><i class="bi bi-check2 me-1"></i>Clôturer</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL ÉDITION ÉQUIPEMENT -->
<div class="modal fade" id="modalEditEquip" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="background:#1e293b;border:1px solid rgba(255,255,255,.12);border-radius:20px;color:var(--txt)">
      <div class="modal-header" style="border-bottom:1px solid rgba(255,255,255,.08)">
        <h5 class="modal-title fw-bold"><i class="bi bi-pencil me-2"></i>Modifier l'équipement</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="admin.php?tab=equipements">
        <input type="hidden" name="action" value="edit_equip">
        <input type="hidden" name="id" id="editEquipId">
        <div class="modal-body">
          <div class="row g-2">
            <div class="col-12"><label class="form-label">Nom *</label><input type="text" name="nom" id="editEquipNom" class="form-control" required></div>
            <div class="col-6"><label class="form-label">Type</label>
              <select name="type" id="editEquipType" class="form-select"><?php foreach ($types as $t): ?><option><?= hv($t) ?></option><?php endforeach; ?></select></div>
            <div class="col-6"><label class="form-label">Statut</label>
              <select name="statut" id="editEquipStatut" class="form-select"><?php foreach ($statuts as $s): ?><option><?= hv($s) ?></option><?php endforeach; ?></select></div>
            <div class="col-12"><label class="form-label">N° Série</label><input type="text" name="numero_serie" id="editEquipSerie" class="form-control"></div>
            <div class="col-6"><label class="form-label">Marque</label><input type="text" name="marque" id="editEquipMarque" class="form-control"></div>
            <div class="col-6"><label class="form-label">Modèle</label><input type="text" name="modele" id="editEquipModele" class="form-control"></div>
            <div class="col-12"><label class="form-label">Site</label>
              <select name="localisation" id="editEquipLoc" class="form-select"><?php foreach ($sites as $s): ?><option><?= hv($s) ?></option><?php endforeach; ?></select></div>
            <div class="col-6"><label class="form-label">État réparation</label>
              <select name="etat_reparation" id="editEquipEtat" class="form-select"><?php foreach ($etats as $et): ?><option><?= hv($et) ?></option><?php endforeach; ?></select></div>
            <div class="col-6"><label class="form-label">Criticité</label>
              <select name="criticite" id="editEquipCrit" class="form-select"><option>Normale</option><option>Haute</option><option>Critique</option></select></div>
          </div>
        </div>
        <div class="modal-footer" style="border-top:1px solid rgba(255,255,255,.08)">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Enregistrer</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL ÉDITION UTILISATEUR -->
<div class="modal fade" id="modalEditUser" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background:#1e293b;border:1px solid rgba(255,255,255,.12);border-radius:20px;color:var(--txt)">
      <div class="modal-header" style="border-bottom:1px solid rgba(255,255,255,.08)">
        <h5 class="modal-title fw-bold"><i class="bi bi-pencil me-2"></i>Modifier l'utilisateur</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="admin.php?tab=utilisateurs">
        <input type="hidden" name="action" value="edit_user">
        <input type="hidden" name="id" id="editUserId">
        <div class="modal-body">
          <div class="mb-2"><label class="form-label">Prénom</label><input type="text" name="prenom" id="editUserPrenom" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Nom</label><input type="text" name="nom" id="editUserNom" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Service</label><input type="text" name="service" id="editUserService" class="form-control"></div>
        </div>
        <div class="modal-footer" style="border-top:1px solid rgba(255,255,255,.08)">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Enregistrer</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ── Filtrage équipements ── */
(function(){
  const inp = document.getElementById('equipSearch');
  if (!inp) return;
  const rows = Array.from(document.querySelectorAll('#equipTable tbody tr'));
  inp.addEventListener('input', function(){
    const v = inp.value.toLowerCase();
    rows.forEach(r => r.style.display = (!v || (r.dataset.search||'').includes(v)) ? '' : 'none');
  });
})();

/* ── Modal clôture réparation ── */
function openCloseRepair(ds) {
  document.getElementById('closeRepId').value    = ds.rid  || '';
  document.getElementById('closeEquipId').value  = ds.eid  || '';
  document.getElementById('closeEquipNom').textContent = ds.nom || '';
  document.getElementById('closeRepDiag').value   = ds.diag   || '';
  document.getElementById('closeRepPieces').value = ds.pieces || '';
  document.getElementById('closeRepTech').value   = ds.tech   || '';
  document.getElementById('closeRepCout').value   = ds.cout   || '';
  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCloseRepair')).show();
}

/* ── Modal édition équipement ── */
function openEditEquip(e) {
  document.getElementById('editEquipId').value    = e.id;
  document.getElementById('editEquipNom').value   = e.nom          || '';
  document.getElementById('editEquipSerie').value = e.numero_serie || '';
  document.getElementById('editEquipMarque').value= e.marque       || '';
  document.getElementById('editEquipModele').value= e.modele       || '';
  setSelect('editEquipType',   e.type);
  setSelect('editEquipStatut', e.statut);
  setSelect('editEquipLoc',    e.localisation);
  setSelect('editEquipEtat',   e.etat_reparation || 'RAS');
  setSelect('editEquipCrit',   e.criticite || 'Normale');
  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditEquip')).show();
}
function setSelect(id, val) {
  const s = document.getElementById(id);
  if (s && val) for (let o of s.options) if (o.value === val || o.text === val) { o.selected = true; break; }
}

/* ── Modal édition utilisateur ── */
function openEditUser(u) {
  document.getElementById('editUserId').value      = u.id;
  document.getElementById('editUserPrenom').value  = u.prenom  || '';
  document.getElementById('editUserNom').value     = u.nom     || '';
  document.getElementById('editUserService').value = u.service || '';
  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditUser')).show();
}

/* ── Ouvrir réparation depuis équipements ── */
function openRepairFromEquip(id, nom) {
  const sel = document.getElementById('repEquipSelect');
  if (sel) { sel.value = id; setTimeout(() => sel.scrollIntoView({behavior:'smooth',block:'center'}), 300); }
  return true;
}

/* ── Copier ticket ── */
function copyTicket(el, val) {
  const span = el.querySelector('span') || el;
  const orig = span.textContent;
  navigator.clipboard.writeText(val).then(() => {
    span.textContent = '✓ Copié !';
    el.style.background = 'rgba(34,197,94,.2)'; el.style.borderColor = 'rgba(34,197,94,.5)'; el.style.color = '#86efac';
    setTimeout(() => { span.textContent = orig; el.style.background = ''; el.style.borderColor = ''; el.style.color = ''; }, 1800);
  }).catch(() => prompt('N° de ticket :', val));
}

/* ── Import CSV : drag & drop + preview nom fichier ── */
(function(){
  const zone  = document.getElementById('dropZone');
  const input = document.getElementById('csvFileInput');
  const label = document.getElementById('fileNameDisplay');
  if (!zone || !input) return;

  input.addEventListener('change', function(){
    label.textContent = input.files[0] ? input.files[0].name : 'Aucun fichier sélectionné';
  });

  zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
  zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
  zone.addEventListener('drop', e => {
    e.preventDefault(); zone.classList.remove('dragover');
    if (e.dataTransfer.files[0]) {
      const dt = new DataTransfer();
      dt.items.add(e.dataTransfer.files[0]);
      input.files = dt.files;
      label.textContent = e.dataTransfer.files[0].name;
    }
  });
})();

/* ── Confirmation reset base ── */
function confirmReset() {
  return confirm(
    '⚠️ ATTENTION — Réinitialisation de la base\n\n' +
    'Cette action va :\n' +
    '1. Créer une sauvegarde .sql complète\n' +
    '2. Vider toutes les tables (équipements, utilisateurs, réparations, logs…)\n\n' +
    'Cette opération est IRRÉVERSIBLE sans la sauvegarde.\n\n' +
    'Confirmez-vous la réinitialisation ?'
  );
}
</script>
</body>
</html>
<?php ob_end_flush(); ?>
