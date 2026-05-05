<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'db.php';

function hv(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
// Affichage LCD-safe : remplace accents non rendus par les ecrans ASCII
function dispStatut(string $s): string {
    $s = str_replace(['En prêt','mise en prêt','Mise en prêt','Sortie (mise en prêt)'],
                     ['En pret','mise en pret','Mise en pret','Sortie (mise en pret)'], $s);
    return hv($s);
}
function go(string $msg, string $qr = ''): void {
    $_SESSION['message'] = $msg;
    if ($qr !== '') $_SESSION['lastqr'] = $qr;
    header('Location: index.php'); exit;
}
function siteBadge(string $site): string {
    $c=['Paris'=>'#3b82f6','Boulogne'=>'#8b5cf6','Lyon'=>'#f59e0b','Uxbridge'=>'#10b981','Rennes'=>'#ef4444'];
    $col=$c[$site]??'#94a3b8';
    return $site
        ? '<span style="background:'.$col.'22;border:1px solid '.$col.'55;color:'.$col.';padding:3px 10px;border-radius:999px;font-size:.78rem;font-weight:700">'.hv($site).'</span>'
        : '<span style="color:#94a3b8">—</span>';
}
function reparBadge(string $etat): string {
    $map=[
        'RAS'                   =>['#22c55e','#14532d'],
        'A diagnostiquer'       =>['#f59e0b','#451a03'],
        'En réparation'         =>['#3b82f6','#1e3a5f'],
        'En attente de pièces'  =>['#f97316','#431407'],
        'Test après réparation' =>['#a78bfa','#2e1065'],
        'Réparé'                =>['#34d399','#064e3b'],
        'Restitué'              =>['#22c55e','#14532d'],
        'Hors service'          =>['#ef4444','#450a0a'],
    ];
    $e=$etat?:'RAS'; [$fg,$bg]=$map[$e]??['#94a3b8','#1e293b'];
    return '<span style="background:'.$bg.';border:1px solid '.$fg.'55;color:'.$fg.';padding:3px 10px;border-radius:999px;font-size:.75rem;font-weight:700">'.hv($e).'</span>';
}
function eventIcon(string $t): string {
    $i=['scan'=>'bi-qr-code-scan','mouvement'=>'bi-arrow-left-right','pret'=>'bi-person-fill',
        'retour'=>'bi-box-arrow-in-left','reparation'=>'bi-tools','creation'=>'bi-plus-circle',
        'modification'=>'bi-pencil','piece'=>'bi-cpu'];
    return $i[$t]??'bi-dot';
}

/* ── Cache session : colonnes / tables optionnelles ── */
if (!isset($_SESSION['has_etat_rep'])) {
    try { $pdo->query("SELECT etat_reparation FROM equipements LIMIT 0"); $_SESSION['has_etat_rep']=1; }
    catch (PDOException $ex) { $_SESSION['has_etat_rep']=0; }
}
$hasEtatRep=(bool)$_SESSION['has_etat_rep'];

if (!isset($_SESSION['has_rep_table'])) {
    try { $pdo->query("SELECT 1 FROM reparations LIMIT 0"); $_SESSION['has_rep_table']=1; }
    catch (PDOException $ex) { $_SESSION['has_rep_table']=0; }
}
$hasRepTable=(bool)$_SESSION['has_rep_table'];

if (!isset($_SESSION['has_ticket_col'])) {
    try { $pdo->query("SELECT numero_ticket FROM reparations LIMIT 0"); $_SESSION['has_ticket_col']=1; }
    catch (PDOException $ex) { $_SESSION['has_ticket_col']=0; }
}
$hasTicketCol = $hasRepTable && (bool)$_SESSION['has_ticket_col'];

/* ── Auth ── */
$authUser='admin'; $authPass='bonjour123';
if (!isset($_SESSION['message']))  $_SESSION['message']='';
if (!isset($_SESSION['authok']))   $_SESSION['authok']=false;
if (!isset($_SESSION['lastqr']))   $_SESSION['lastqr']='';

$protocol=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';
$host=$_SERVER['HTTP_HOST'];
$baseUrl=$protocol.'://'.$host.'/tracabilite/index.php';

/* ── LOGIN ── */
if (isset($_POST['loginuser'],$_POST['loginpass'])) {
    $u=trim($_POST['loginuser']); $p=trim($_POST['loginpass']);
    if ($u===$authUser&&$p===$authPass) { $_SESSION['authok']=true; go('Accès autorisé'); }
    else { $_SESSION['authok']=false; go('Identifiants incorrects'); }
}
?>
<?php if (!$_SESSION['authok']): ?>
<!DOCTYPE html><html lang="fr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Accès sécurisé</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{min-height:100vh;display:grid;place-items:center;color:#e5e7eb;
  background:radial-gradient(circle at 20% 20%,rgba(59,130,246,.25),transparent 25%),
             radial-gradient(circle at 80% 30%,rgba(168,85,247,.22),transparent 24%),
             linear-gradient(135deg,#050816,#111827)}
.cardx{width:min(920px,92vw);background:rgba(15,23,42,.78);backdrop-filter:blur(18px);border:1px solid rgba(255,255,255,.12);border-radius:30px;box-shadow:0 30px 80px rgba(0,0,0,.45);overflow:hidden}
.left{padding:42px;background:linear-gradient(160deg,rgba(37,99,235,.18),rgba(168,85,247,.12))}
.right{padding:42px}.title{font-weight:900;letter-spacing:-.04em}.muted{color:#94a3b8}
.lock-wrap{width:170px;height:170px;margin:auto;position:relative;display:grid;place-items:center}
.ring{position:absolute;inset:0;border-radius:50%;border:2px solid rgba(255,255,255,.12);animation:spin 7s linear infinite}
.ring.r2{inset:14px;border-style:dashed;animation-duration:11s}
.ring.r3{inset:28px;border-color:rgba(34,197,94,.18);animation-duration:15s}
@keyframes spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}
.lock-icon{font-size:4.5rem;color:#86efac;text-shadow:0 0 20px rgba(34,197,94,.35);animation:pulse 2s ease-in-out infinite}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.06)}}
.form-control{background:rgba(255,255,255,.08)!important;color:#fff!important;border:1px solid rgba(255,255,255,.14)!important;border-radius:16px;padding:14px 16px;box-shadow:none!important}
.form-control::placeholder{color:#cbd5e1}
.btn-login{border:none;border-radius:16px;padding:14px 18px;font-weight:800;background:linear-gradient(135deg,#2563eb,#7c3aed);color:#fff;width:100%;cursor:pointer}
.badge-soft{display:inline-flex;gap:8px;align-items:center;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.10);padding:8px 12px;border-radius:999px}
.loader-line{height:6px;border-radius:999px;background:rgba(255,255,255,.08);overflow:hidden}
.loader-line span{display:block;height:100%;background:linear-gradient(90deg,#22c55e,#06b6d4,#7c3aed);animation:bar 2.2s ease-in-out infinite}
@keyframes bar{0%{width:0}50%{width:100%}100%{width:0}}
</style></head><body>
<div class="cardx row g-0">
  <div class="col-lg-5 left text-center d-flex flex-column justify-content-center">
    <div class="badge-soft mx-auto mb-3"><i class="bi bi-shield-lock"></i> Interface protégée</div>
    <div class="lock-wrap mb-3">
      <div class="ring"></div><div class="ring r2"></div><div class="ring r3"></div>
      <i class="bi bi-unlock lock-icon"></i>
    </div>
    <h1 class="title display-6 mb-2">Accès sécurisé</h1>
    <p class="muted mb-0">Saisis tes identifiants pour déverrouiller le tableau de bord.</p>
  </div>
  <div class="col-lg-7 right">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div><div class="text-uppercase small muted">Connexion requise</div><h2 class="h3 mb-0 fw-bold">Déverrouillage</h2></div>
      <span class="badge-soft"><i class="bi bi-lightning-charge"></i> Secure mode</span>
    </div>
    <?php if (!empty($_SESSION['message'])): ?>
    <div class="alert <?= $_SESSION['authok']??false?'alert-success':'alert-danger' ?> border-0 rounded-4">
      <?= hv($_SESSION['message']) ?>
    </div>
    <?php $_SESSION['message']=''; ?>
    <?php endif; ?>
    <form method="post" autocomplete="off" class="mt-3">
      <div class="mb-3"><label class="form-label">Utilisateur</label>
        <input type="text" name="loginuser" class="form-control" placeholder="Identifiant"
               autocomplete="off" readonly onfocus="this.removeAttribute('readonly')" required></div>
      <div class="mb-3"><label class="form-label">Mot de passe</label>
        <input type="password" name="loginpass" class="form-control" placeholder="Mot de passe"
               autocomplete="off" readonly onfocus="this.removeAttribute('readonly')" required></div>
      <div class="loader-line mb-3"><span></span></div>
      <button type="submit" class="btn-login"><i class="bi bi-unlock-fill me-2"></i>Déverrouiller</button>
    </form>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
<?php ob_end_flush(); exit; endif; ?>

<?php
/* ══════════════════════════════════════════
   POST : ATTRIBUTION / LIBÉRATION
══════════════════════════════════════════ */
if (isset($_POST['attribuer'],$_POST['equipid'])) {
    $equipId=(int)$_POST['equipid'];
    $userId=isset($_POST['utilisateurid'])&&$_POST['utilisateurid']!==''?(int)$_POST['utilisateurid']:null;
    try {
        if ($userId===null) {
            $pdo->prepare("UPDATE equipements SET utilisateur_id=NULL,date_attribution=NULL,statut='Disponible' WHERE id=?")->execute([$equipId]);
            $pdo->prepare("INSERT INTO movements(equipement_id,action) VALUES(?,'Retour disponible')")->execute([$equipId]);
            try { $pdo->prepare("INSERT INTO equipement_logs(equipement_id,type_event,titre,auteur) VALUES(?,'retour','Retour équipement libéré','admin')")->execute([$equipId]); } catch (PDOException $ex) {}
            go('Équipement libéré avec succès');
        } else {
            $pdo->prepare("UPDATE equipements SET utilisateur_id=?,date_attribution=NOW(),statut='En prêt' WHERE id=?")->execute([$userId,$equipId]);
            $pdo->prepare("INSERT INTO movements(equipement_id,action) VALUES(?,'Sortie (mise en prêt)')")->execute([$equipId]);
            try { $pdo->prepare("INSERT INTO equipement_logs(equipement_id,type_event,titre,auteur) VALUES(?,'pret','Mise en prêt - équipement attribué','admin')")->execute([$equipId]); } catch (PDOException $ex) {}
            go('Équipement attribué avec succès');
        }
    } catch (PDOException $ex) { go('Erreur : '.$ex->getMessage()); }
}

/* ══════════════════════════════════════════
   SCAN QR CODE — double-scan (10 secondes)
══════════════════════════════════════════ */
$delaySeconds=10; $qr='';
if (isset($_GET['qr']))          $qr=trim($_GET['qr']);
elseif (isset($_POST['qrcode'])) $qr=trim($_POST['qrcode']);

if ($qr!=='') {
    try {
        $pdo->beginTransaction();
        $stmt=$pdo->prepare("SELECT id,nom,type,statut,qr_code,utilisateur_id,numero_serie,localisation FROM equipements WHERE qr_code=? LIMIT 1 FOR UPDATE");
        $stmt->execute([$qr]); $equip=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$equip) { $pdo->rollBack(); go('QR code inconnu : '.$qr); }

        $stmt2=$pdo->prepare("SELECT first_scan_at FROM scan_pending WHERE qr_code=? LIMIT 1 FOR UPDATE");
        $stmt2->execute([$qr]); $pending=$stmt2->fetch(PDO::FETCH_ASSOC);

        if (!$pending) {
            $pdo->prepare("INSERT INTO scan_pending(qr_code,first_scan_at) VALUES(?,NOW())")->execute([$qr]);
            $pdo->commit();
            try { $pdo->prepare("INSERT INTO equipement_logs(equipement_id,type_event,titre,details,auteur) VALUES(?,'scan','QR Code scanné','Premier scan – consultation état','système')")->execute([$equip['id']]); } catch (PDOException $ex) {}
            go('État actuel de '.$equip['nom'].' : '.$equip['statut'],$qr);
        } else {
            $elapsed=time()-strtotime($pending['first_scan_at']);
            if ($elapsed<=$delaySeconds) {
                $newStatus=($equip['statut']==='Disponible')?'En prêt':'Disponible';
                if ($newStatus==='Disponible')
                    $pdo->prepare("UPDATE equipements SET statut=?,utilisateur_id=NULL,date_attribution=NULL WHERE id=? AND qr_code=?")->execute([$newStatus,$equip['id'],$qr]);
                else
                    $pdo->prepare("UPDATE equipements SET statut=? WHERE id=? AND qr_code=?")->execute([$newStatus,$equip['id'],$qr]);
                $action=($newStatus==='En prêt')?'Sortie (mise en prêt)':'Retour (disponible)';
                $pdo->prepare("INSERT INTO movements(equipement_id,action) VALUES(?,?)")->execute([$equip['id'],$action]);
                $pdo->prepare("DELETE FROM scan_pending WHERE qr_code=?")->execute([$qr]);
                $pdo->commit();
                try { $pdo->prepare("INSERT INTO equipement_logs(equipement_id,type_event,titre,details,auteur) VALUES(?,'mouvement',?,?,'système')")->execute([$equip['id'],$action,'Double-scan → '.$newStatus]); } catch (PDOException $ex) {}
                go($equip['nom'].' → '.$newStatus,$qr);
            } else {
                $pdo->prepare("UPDATE scan_pending SET first_scan_at=NOW() WHERE qr_code=?")->execute([$qr]);
                $pdo->commit();
                go('État actuel de '.$equip['nom'].' : '.$equip['statut'],$qr);
            }
        }
    } catch (PDOException $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); go('Erreur SQL : '.$ex->getMessage()); }
}

/* ══════════════════════════════════════════
   DONNÉES PRINCIPALES
══════════════════════════════════════════ */
$history=[];
try { $history=$pdo->query("SELECT e.nom,e.qr_code,m.action,m.date_action FROM movements m JOIN equipements e ON m.equipement_id=e.id ORDER BY m.date_action DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC); } catch (PDOException $ex) {}

$utilisateurs=[];
try { $utilisateurs=$pdo->query("SELECT id,nom,prenom,service FROM utilisateurs ORDER BY nom ASC")->fetchAll(PDO::FETCH_ASSOC); } catch (PDOException $ex) {}

$etatCol=$hasEtatRep?"e.etat_reparation,":"'RAS' AS etat_reparation,";
$equipements=[];
$equipementsError='';
try {
    $equipements=$pdo->query(
        "SELECT e.id,e.nom,e.type,e.numero_serie,e.localisation,e.statut,e.qr_code,
                e.utilisateur_id,e.date_attribution,{$etatCol}
                u.nom AS u_nom,u.prenom AS u_prenom,u.service AS u_service
         FROM equipements e LEFT JOIN utilisateurs u ON u.id=e.utilisateur_id
         ORDER BY e.nom ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    // Retry sans la colonne etat_reparation si elle n'existe pas
    $_SESSION['has_etat_rep']=0; $hasEtatRep=false;
    $etatCol="'RAS' AS etat_reparation,";
    try {
        $equipements=$pdo->query(
            "SELECT e.id,e.nom,e.type,e.numero_serie,e.localisation,e.statut,e.qr_code,
                    e.utilisateur_id,e.date_attribution,'RAS' AS etat_reparation,
                    u.nom AS u_nom,u.prenom AS u_prenom,u.service AS u_service
             FROM equipements e LEFT JOIN utilisateurs u ON u.id=e.utilisateur_id
             ORDER BY e.nom ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $ex2) { $equipementsError=$ex2->getMessage(); }
}

/* ── Réparations actives ── */
$activeRepairs=[];
if ($hasRepTable) {
    $ticketSel=$hasTicketCol?'r.numero_ticket,':"'' AS numero_ticket,";
    try {
        $activeRepairs=$pdo->query(
            "SELECT r.id,r.statut,r.panne_declaree,r.technicien,r.date_ouverture,r.priorite,{$ticketSel}
                    e.id AS e_id,e.nom AS e_nom,e.qr_code AS e_qr,e.localisation AS e_loc,e.type AS e_type
             FROM reparations r JOIN equipements e ON e.id=r.equipement_id
             WHERE r.statut NOT IN ('Réparé','Restitué','Clôturé')
             ORDER BY r.date_ouverture ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $ex) {
        try {
            $activeRepairs=$pdo->query(
                "SELECT r.id,r.statut,r.panne_declaree,r.technicien,r.date_ouverture,'' AS priorite,{$ticketSel}
                        e.id AS e_id,e.nom AS e_nom,e.qr_code AS e_qr,e.localisation AS e_loc,e.type AS e_type
                 FROM reparations r JOIN equipements e ON e.id=r.equipement_id
                 WHERE r.statut NOT IN ('Réparé','Restitué','Clôturé') ORDER BY r.date_ouverture ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $ex2) { $activeRepairs=[]; }
    }
}

/* KPIs */
$total=count($equipements); $available=0; $inRepair=count($activeRepairs);
foreach ($equipements as $e) { if (($e['statut']??'')==='Disponible') $available++; }
$busy=max(0,$total-$available);

/* ── Détails du dernier scan ── */
$lastQr=$_SESSION['lastqr']??'';
$equip=null; $lastEvents=[]; $activeRepair=null; $repairHistory=[];

if ($lastQr!=='') {
    try {
        $s=$pdo->prepare("SELECT id,nom,type,statut,qr_code,utilisateur_id,numero_serie,localisation FROM equipements WHERE qr_code=? LIMIT 1");
        $s->execute([$lastQr]); $equip=$s->fetch(PDO::FETCH_ASSOC)?:null;
    } catch (PDOException $ex) {}

    if ($equip) {
        try {
            $s=$pdo->prepare("SELECT type_event,titre,details,created_at FROM equipement_logs WHERE equipement_id=? ORDER BY created_at DESC LIMIT 6");
            $s->execute([$equip['id']]); $lastEvents=$s->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $ex) {}

        if ($hasRepTable) {
            $tcScan=$hasTicketCol?'r.numero_ticket,':"'' AS numero_ticket,";
            try {
                $s=$pdo->prepare("SELECT r.id,r.statut,r.panne_declaree,r.diagnostic,r.pieces_a_changer,r.technicien,r.date_ouverture,{$tcScan}'' AS dummy FROM reparations r WHERE r.equipement_id=? AND r.statut NOT IN ('Réparé','Restitué','Clôturé') ORDER BY r.date_ouverture DESC LIMIT 1");
                $s->execute([$equip['id']]); $activeRepair=$s->fetch(PDO::FETCH_ASSOC)?:null;
            } catch (PDOException $ex) {
                try {
                    $s=$pdo->prepare("SELECT id,statut,panne_declaree,diagnostic,pieces_a_changer,technicien,date_ouverture,'' AS numero_ticket FROM reparations WHERE equipement_id=? AND statut NOT IN ('Réparé','Restitué','Clôturé') ORDER BY date_ouverture DESC LIMIT 1");
                    $s->execute([$equip['id']]); $activeRepair=$s->fetch(PDO::FETCH_ASSOC)?:null;
                } catch (PDOException $ex2) {}
            }
            try {
                $s=$pdo->prepare("SELECT id,statut,panne_declaree,pieces_changees,technicien,date_ouverture,date_cloture,cout_reparation FROM reparations WHERE equipement_id=? AND statut IN ('Réparé','Restitué','Clôturé','Hors service') ORDER BY date_cloture DESC LIMIT 8");
                $s->execute([$equip['id']]); $repairHistory=$s->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $ex) {}
        }
    }
}

$wfSteps=['A diagnostiquer','En réparation','En attente de pièces','Test après réparation','Réparé','Restitué'];
$wfPct=[0=>10,1=>35,2=>55,3=>75,4=>90,5=>100];
function repGaugeColor(string $s): string {
    if (in_array($s,['Réparé','Restitué'],true)) return '#10b981';
    if ($s==='En attente de pièces') return '#f97316';
    return '#6366f1';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Traçabilité — Sodiaal</title>
<link rel="preconnect" href="https://cdn.jsdelivr.net">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--bg1:#070b18;--bg2:#111827;--card:rgba(15,23,42,.72);--line:rgba(255,255,255,.10);--txt:#e5e7eb;--muted:#94a3b8}
html{scroll-behavior:smooth}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{min-height:100vh;color:var(--txt);font-family:system-ui,-apple-system,sans-serif;
  background:radial-gradient(circle at 10% 10%,rgba(59,130,246,.28),transparent 24%),
             radial-gradient(circle at 88% 18%,rgba(168,85,247,.22),transparent 24%),
             radial-gradient(circle at 50% 100%,rgba(6,182,212,.18),transparent 24%),
             linear-gradient(180deg,var(--bg1),var(--bg2))}
.glass{background:var(--card);backdrop-filter:blur(18px);border:1px solid var(--line);box-shadow:0 24px 60px rgba(0,0,0,.35);border-radius:28px}
.hero{padding:28px}
.title{letter-spacing:-.04em;font-weight:900}
.muted{color:var(--muted)}
.chip{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.08);color:var(--txt)}
.scan-panel{background:linear-gradient(135deg,rgba(37,99,235,.25),rgba(124,58,237,.18));border:1px solid rgba(255,255,255,.12);border-radius:26px;padding:22px}
.scan-input{background:rgba(255,255,255,.08)!important;color:#fff!important;border:1px solid rgba(255,255,255,.14)!important;border-radius:16px;padding:16px 18px;font-size:1.05rem;box-shadow:none!important}
.scan-input::placeholder{color:#cbd5e1}
.btn-scan{border-radius:16px;padding:14px 18px;font-weight:800;border:none;background:linear-gradient(135deg,#2563eb,#7c3aed);color:#fff}
.btn-soft{border-radius:16px;padding:14px 18px;font-weight:800;border:1px solid rgba(255,255,255,.10);background:rgba(255,255,255,.08);color:#fff;text-decoration:none;display:inline-block;text-align:center}
.btn-admin{border-radius:16px;padding:14px 18px;font-weight:800;border:1px solid rgba(239,68,68,.40);background:rgba(239,68,68,.12);color:#fca5a5;text-decoration:none;display:inline-block;text-align:center}
.mini-card{background:linear-gradient(135deg,rgba(59,130,246,.16),rgba(168,85,247,.12));border:1px solid rgba(255,255,255,.08);border-radius:22px;padding:18px;height:100%}
.mini-card.repair-active{background:linear-gradient(135deg,rgba(245,158,11,.22),rgba(239,68,68,.18));border-color:rgba(245,158,11,.3);cursor:pointer}
.mini-card.repair-active:hover{border-color:rgba(245,158,11,.6)}
.label{font-size:.82rem;text-transform:uppercase;letter-spacing:.12em;color:var(--muted)}
.value{font-size:1.55rem;font-weight:900}
.status-badge{padding:8px 12px;border-radius:999px;font-weight:800;display:inline-flex;gap:8px;align-items:center}
.ok{background:rgba(34,197,94,.16);color:#86efac}
.busy{background:rgba(245,158,11,.16);color:#fdba74}
.table-wrap{overflow:hidden;border-radius:20px}
.table thead th{background:rgba(255,255,255,.08)!important;color:var(--muted);border-color:rgba(255,255,255,.08);font-size:.78rem;text-transform:uppercase;letter-spacing:.06em}
.table tbody td{background:rgba(255,255,255,.02)!important;color:var(--txt);border-color:rgba(255,255,255,.06)}
/* Ligne cliquable → fiche équipement */
.table tbody tr.equip-row{cursor:pointer;transition:background .15s}
.table tbody tr.equip-row:hover td{background:rgba(99,102,241,.12)!important}
/* Nom cliquable */
.equip-nom-link{color:#a5b4fc;font-weight:800;text-decoration:none;display:flex;align-items:center;gap:5px}
.equip-nom-link:hover{color:#c7d2fe;text-decoration:underline}
.equip-nom-link .bi-arrow-up-right-square{font-size:.75rem;opacity:.6}
/* Hint survol ligne */
.equip-row-hint{font-size:.68rem;color:#6366f1;margin-top:2px;opacity:0;transition:opacity .2s}
.equip-row:hover .equip-row-hint{opacity:1}
.section-title{font-weight:900;margin-bottom:16px;letter-spacing:-.02em}
.qr-card{text-align:center}
.qr-card img{border-radius:18px;background:#fff;padding:10px}
.live-badge{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.18);color:#86efac}
.ip-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:999px;background:rgba(6,182,212,.12);border:1px solid rgba(6,182,212,.25);color:#67e8f9;font-size:.82rem;font-family:monospace}
.user-badge{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;background:rgba(168,85,247,.15);border:1px solid rgba(168,85,247,.25);color:#d8b4fe;font-size:.78rem;font-weight:700}
.btn-attrib{border:none;border-radius:10px;padding:6px 14px;font-weight:700;font-size:.82rem;background:linear-gradient(135deg,#2563eb,#7c3aed);color:#fff;cursor:pointer}
.btn-liberer{border-radius:10px;padding:6px 14px;font-weight:700;font-size:.82rem;background:rgba(239,68,68,.20);border:1px solid rgba(239,68,68,.30);color:#fca5a5;cursor:pointer}
.btn-scanner{border-radius:10px;padding:5px 12px;font-weight:700;font-size:.78rem;background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.30);color:#a5b4fc;cursor:pointer;white-space:nowrap}
.btn-scanner:hover{background:rgba(99,102,241,.3);color:#c7d2fe}
/* Bouton Fiche */
.btn-fiche{border-radius:10px;padding:5px 12px;font-weight:700;font-size:.78rem;background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.28);color:#34d399;cursor:pointer;white-space:nowrap;text-decoration:none;display:inline-block}
.btn-fiche:hover{background:rgba(16,185,129,.25);color:#6ee7b7}
.modal-content{background:#1e293b;border:1px solid rgba(255,255,255,.12);border-radius:24px;color:var(--txt)}
.modal-header,.modal-footer{border-color:rgba(255,255,255,.08)}
.form-select{background:rgba(255,255,255,.08)!important;color:#fff!important;border:1px solid rgba(255,255,255,.14)!important;border-radius:10px;padding:10px 14px}
.form-select option{background:#1e293b;color:#fff}
.search-input{background:rgba(255,255,255,.07)!important;color:#fff!important;border:1px solid rgba(255,255,255,.14)!important;border-radius:14px;padding:10px 16px;box-shadow:none!important}
.search-input::placeholder{color:#94a3b8}
.search-input:focus{background:rgba(255,255,255,.12)!important;border-color:rgba(99,102,241,.5)!important;outline:none}
.event-line{display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.06)}
.event-line:last-child{border-bottom:none}
.event-icon{width:28px;height:28px;border-radius:50%;background:rgba(99,102,241,.20);display:grid;place-items:center;flex-shrink:0;font-size:.85rem;color:#a5b4fc}
.rep-gauge-wrap{background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.25);border-radius:14px;padding:14px}
.rep-gauge-bg{background:rgba(255,255,255,.08);border-radius:999px;height:10px;overflow:hidden;margin:8px 0 4px}
.rep-gauge-fill{height:100%;border-radius:999px;transition:width .6s cubic-bezier(.2,.9,.2,1)}
.wf-step-mini{display:inline-flex;align-items:center;gap:.25rem;padding:.15rem .5rem;border-radius:20px;font-size:.65rem;font-weight:700;white-space:nowrap}
.wf-done-m{background:rgba(16,185,129,.15);color:#34d399;border:1px solid rgba(16,185,129,.3)}
.wf-act-m{background:rgba(99,102,241,.2);color:#a5b4fc;border:1px solid rgba(99,102,241,.4)}
.wf-pend-m{background:rgba(255,255,255,.04);color:rgba(226,232,240,.25);border:1px solid rgba(255,255,255,.06)}
.rep-active-card{background:rgba(245,158,11,.07);border:1px solid rgba(245,158,11,.20);border-radius:14px;padding:12px 14px;display:flex;align-items:center;gap:12px;cursor:pointer;transition:border-color .2s}
.rep-active-card:hover{border-color:rgba(245,158,11,.5)}
.rep-active-dot{width:36px;height:36px;border-radius:50%;background:rgba(245,158,11,.2);display:grid;place-items:center;flex-shrink:0;font-size:1rem;color:#fdba74}
.ticket-badge{display:inline-flex;align-items:center;gap:5px;background:rgba(6,182,212,.10);border:1px solid rgba(6,182,212,.28);color:#67e8f9;padding:3px 9px;border-radius:7px;font-size:.72rem;font-weight:700;font-family:monospace;cursor:pointer;user-select:all;transition:background .15s;text-decoration:none}
.ticket-badge:hover{background:rgba(6,182,212,.2);color:#67e8f9}
.btn-export{border-radius:16px;padding:14px 18px;font-weight:800;border:1px solid rgba(34,197,94,.35);background:rgba(34,197,94,.12);color:#86efac;text-decoration:none;display:inline-block;text-align:center}
.btn-export:hover{background:rgba(34,197,94,.22);color:#a7f3d0}
.btn-analytics{border-radius:16px;padding:14px 18px;font-weight:800;border:1px solid rgba(99,102,241,.35);background:rgba(99,102,241,.12);color:#a5b4fc;text-decoration:none;display:inline-block;text-align:center}
.btn-analytics:hover{background:rgba(99,102,241,.22);color:#c7d2fe}
#searchCount{font-size:.78rem;color:#67e8f9;font-weight:700;min-width:80px;text-align:right}
.footer-note{color:var(--muted);font-size:.9rem}
</style>
</head>
<body>
<div class="container py-4 py-lg-5">

  <!-- ═══ HEADER ═══ -->
  <div class="glass hero mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
      <div>
        <div class="chip rounded-pill px-3 py-2 mb-3 d-inline-flex gap-2"><i class="bi bi-broadcast"></i>Système de traçabilité</div>
        <h1 class="title display-5 mb-2">Gestion des équipements par QR code</h1>
        <p class="muted mb-0">Interface Sodiaal — scan, attribution, réparation.</p>
      </div>
      <div class="text-lg-end">
        <div class="label">Dernière actualisation</div>
        <div class="fw-semibold fs-5"><?= date('d/m/Y H:i') ?></div>
        <div class="live-badge mt-2"><span>●</span>En ligne</div>
        <div class="ip-badge mt-2"><i class="bi bi-hdd-network"></i><?= hv($host) ?></div>
      </div>
    </div>

    <!-- Zone de scan -->
    <div class="scan-panel mt-4">
      <div class="section-title mb-1"><i class="bi bi-qr-code-scan me-2"></i>Zone de scan prioritaire</div>
      <p class="muted mb-3" style="font-size:.88rem">Scanne un QR ou clique sur <strong>Scanner</strong> dans le tableau. <span style="color:#a5b4fc">Clique sur le nom d'un équipement pour ouvrir sa fiche détaillée.</span></p>
      <?php if (!empty($_SESSION['message'])): ?>
      <div class="alert alert-info mt-2 mb-3 rounded-4 border-0"><i class="bi bi-info-circle me-2"></i><?= hv($_SESSION['message']) ?></div>
      <?php $_SESSION['message']=''; ?>
      <?php endif; ?>
      <form method="post" action="index.php" id="scanForm">
        <div class="row g-2 align-items-stretch">
          <div class="col-12 col-lg-9">
            <input type="text" name="qrcode" id="qrInput" class="form-control scan-input"
                   placeholder="Scanner ou saisir un QR code..." autofocus autocomplete="off">
          </div>
          <div class="col-12 col-lg-3 d-grid gap-2">
            <button type="submit" class="btn btn-primary btn-scan"><i class="bi bi-arrow-right-circle me-2"></i>Valider</button>
            <a class="btn-analytics" href="./analytics.php"><i class="bi bi-bar-chart-line me-2"></i>Analytics &amp; Logs</a>
            <a class="btn-admin" href="./admin.php"><i class="bi bi-shield-lock me-2"></i>Administration</a>
            <a class="btn-export" href="./export.php"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Exports &amp; Logs</a>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- ═══ KPIs ═══ -->
  <div class="row g-4 mb-4">
    <div class="col-6 col-md-3">
      <div class="mini-card"><div class="label">Total équipements</div><div class="value"><?= $total ?></div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="mini-card"><div class="label">Disponibles</div><div class="value" style="color:#86efac"><?= $available ?></div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="mini-card"><div class="label">En pret</div><div class="value" style="color:#fdba74"><?= $busy ?></div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="mini-card <?= $inRepair>0?'repair-active':'' ?>"
           onclick="<?= $inRepair>0?"document.getElementById('repairPanel').scrollIntoView({behavior:'smooth'})":'' ?>"
           style="<?= $inRepair===0?'cursor:default':'' ?>">
        <div class="label"><i class="bi bi-tools me-1"></i>En réparation<?= $inRepair>0?' ↓':'' ?></div>
        <div class="value" style="color:<?= $inRepair>0?'#fdba74':'var(--txt)' ?>"><?= $inRepair ?></div>
        <?php if ($inRepair>0): ?><div style="font-size:.72rem;color:#f59e0b;margin-top:4px">Cliquer pour voir ↓</div><?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ═══ PANEL RÉPARATIONS EN COURS ═══ -->
  <?php if (!empty($activeRepairs)): ?>
  <div id="repairPanel" class="glass p-4 mb-4">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
      <div class="section-title mb-0" style="color:#fdba74">
        <i class="bi bi-tools me-2"></i>Réparations en cours — <?= count($activeRepairs) ?> équipement<?= count($activeRepairs)>1?'s':'' ?>
      </div>
      <a href="admin.php?tab=reparations" class="btn btn-sm"
         style="background:rgba(245,158,11,.15);border:1px solid rgba(245,158,11,.3);color:#fcd34d;border-radius:10px;font-weight:700;font-size:.82rem">
        <i class="bi bi-pencil me-1"></i>Gérer dans Admin
      </a>
    </div>
    <div class="row g-3">
      <?php foreach ($activeRepairs as $ar):
        $arIdx=array_search($ar['statut'],$wfSteps); if ($arIdx===false) $arIdx=0;
        $arPct=$wfPct[$arIdx]??0; $arClr=repGaugeColor($ar['statut']??'');
      ?>
      <div class="col-md-6 col-xl-4">
        <div class="rep-active-card" onclick="window.location='equipement.php?id=<?= (int)$ar['e_id'] ?>'">
          <div class="rep-active-dot"><i class="bi bi-tools"></i></div>
          <div class="flex-grow-1 overflow-hidden">
            <div style="font-weight:800;font-size:.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#a5b4fc"><?= hv($ar['e_nom']) ?></div>
            <div style="font-size:.75rem;margin:2px 0"><?= reparBadge($ar['statut']??'') ?></div>
            <?php if (!empty($ar['panne_declaree'])): ?>
            <div style="font-size:.72rem;color:#94a3b8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= hv(mb_substr($ar['panne_declaree'],0,45)) ?></div>
            <?php endif; ?>
            <?php if (!empty($ar['numero_ticket'])): ?>
            <div style="margin-top:4px">
              <span class="ticket-badge" title="Ticket" onclick="event.stopPropagation();copyTicket(this,'<?= hv($ar['numero_ticket']) ?>')">
                <i class="bi bi-ticket-perforated" style="font-size:.75rem"></i><?= hv($ar['numero_ticket']) ?>
                <i class="bi bi-clipboard" style="font-size:.65rem;opacity:.65"></i>
              </span>
            </div>
            <?php endif; ?>
            <div style="background:rgba(255,255,255,.08);border-radius:999px;height:4px;margin-top:6px;overflow:hidden">
              <div style="width:<?= $arPct ?>%;height:100%;background:<?= $arClr ?>;border-radius:999px"></div>
            </div>
            <div style="font-size:.68rem;color:#6366f1;margin-top:3px;font-weight:700">→ Ouvrir la fiche</div>
          </div>
          <div style="text-align:right;flex-shrink:0">
            <?= siteBadge($ar['e_loc']??'') ?>
            <div style="font-size:.68px;color:#64748b;margin-top:4px"><?= date('d/m',strtotime($ar['date_ouverture'])) ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ═══ TABLEAU ÉQUIPEMENTS + QR ═══ -->
  <div class="row g-4 mb-4">
    <div class="col-lg-8">
      <div class="glass p-4 h-100">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
          <div class="section-title mb-0"><i class="bi bi-laptop me-2"></i>Équipements &amp; Attributions</div>
          <div class="d-flex align-items-center gap-2">
            <input type="text" id="searchInput" class="form-control search-input"
                   placeholder="🔍 Nom, série, site, statut…" style="min-width:200px" autocomplete="off">
            <span id="searchCount"><?= $total ?> éléments</span>
          </div>
        </div>
        <div class="table-responsive table-wrap">
          <table class="table table-hover align-middle mb-0" id="equipTable">
            <thead>
              <tr>
                <th>Nom / Fiche</th><th>Type</th><th>N° Série</th><th>Site</th>
                <th>Statut</th><th>Réparation</th><th>Attribué à</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($equipements as $row):
              $etat=$row['etat_reparation']??'RAS';
              $sd=strtolower(implode(' ',[$row['nom']??'',$row['type']??'',$row['numero_serie']??'',$row['localisation']??'',$row['statut']??'',$etat,($row['u_prenom']??'').' '.($row['u_nom']??''),$row['qr_code']??'']));
              $ficheUrl='equipement.php?id='.(int)$row['id'];
            ?>
            <tr class="equip-row" data-search="<?= hv($sd) ?>" data-href="<?= hv($ficheUrl) ?>">
              <td>
                <a href="<?= hv($ficheUrl) ?>" class="equip-nom-link" onclick="event.stopPropagation()">
                  <?= hv($row['nom']??'') ?>
                  <i class="bi bi-arrow-up-right-square"></i>
                </a>
                <div class="equip-row-hint">↗ Ouvrir la fiche</div>
              </td>
              <td class="muted small"><?= hv($row['type']??'') ?></td>
              <td><code style="color:#67e8f9;font-size:.78rem"><?= hv($row['numero_serie']??'') ?></code></td>
              <td><?= siteBadge($row['localisation']??'') ?></td>
              <td><span class="status-badge <?= ($row['statut']??'')==='Disponible'?'ok':'busy' ?>"><?= dispStatut($row['statut']??'') ?></span></td>
              <td><?= reparBadge($etat) ?></td>
              <td>
                <?php if (!empty($row['u_nom'])): ?>
                  <div class="user-badge"><i class="bi bi-person-fill"></i><?= hv(($row['u_prenom']??'').' '.$row['u_nom']) ?></div>
                  <?php if (!empty($row['u_service'])): ?><div class="muted" style="font-size:.72rem;margin-top:2px"><?= hv($row['u_service']) ?></div><?php endif; ?>
                  <?php if (!empty($row['date_attribution'])): ?><div class="muted" style="font-size:.68rem">Depuis le <?= date('d/m/Y',strtotime($row['date_attribution'])) ?></div><?php endif; ?>
                <?php else: ?><span class="muted small">Non attribué</span><?php endif; ?>
              </td>
              <td>
                <div class="d-flex flex-column gap-1">
                  <a href="<?= hv($ficheUrl) ?>" class="btn-fiche" onclick="event.stopPropagation()">
                    <i class="bi bi-card-text me-1"></i>Fiche
                  </a>
                  <button class="btn-attrib" type="button"
                    data-id="<?= (int)$row['id'] ?>"
                    data-nom="<?= hv($row['nom']??'') ?>"
                    data-uid="<?= isset($row['utilisateur_id'])?(int)$row['utilisateur_id']:'' ?>"
                    onclick="event.stopPropagation();openModal(this.dataset)">
                    <i class="bi bi-person-plus me-1"></i>Attribuer
                  </button>
                  <button class="btn-scanner" type="button" data-qr="<?= hv($row['qr_code']??'') ?>" onclick="event.stopPropagation();preFillScan(this.dataset.qr)">
                    <i class="bi bi-qr-code-scan me-1"></i>Scanner
                  </button>
                  <?php if (!empty($row['u_nom'])): ?>
                  <form method="post" style="display:inline" onsubmit="return confirm('Libérer cet équipement ?')" onclick="event.stopPropagation()">
                    <input type="hidden" name="equipid" value="<?= (int)$row['id'] ?>">
                    <input type="hidden" name="utilisateurid" value="">
                    <button type="submit" name="attribuer" value="1" class="btn-liberer w-100"><i class="bi bi-x-circle me-1"></i>Libérer</button>
                  </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($equipements)): ?>
            <tr><td colspan="8" class="text-center muted py-4"><i class="bi bi-inbox me-2"></i>Aucun équipement enregistré</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div id="noSearchResult" class="text-center muted py-3" style="display:none">
          <i class="bi bi-search me-2"></i>Aucun résultat —
          <button onclick="document.getElementById('searchInput').value='';document.getElementById('searchInput').dispatchEvent(new Event('input'))"
                  style="background:none;border:none;color:#6366f1;cursor:pointer;font-weight:700">Effacer</button>
        </div>
      </div>
    </div>

    <!-- QR Code panel -->
    <div class="col-lg-4">
      <div class="glass p-4 h-100 qr-card">
        <div class="section-title"><i class="bi bi-qr-code me-2"></i>QR téléphone</div>
        <?php if (isset($equip)&&$equip): ?>
          <?php $qrUrl=$baseUrl.'?qr='.urlencode($equip['qr_code']); ?>
          <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=<?= urlencode($qrUrl) ?>"
               alt="QR" class="img-fluid mb-3 mx-auto" loading="lazy" width="220" height="220">
          <div class="small muted"><?= hv($equip['nom']) ?> — <?= dispStatut($equip['statut']) ?></div>
          <div class="mt-2"><a href="<?= hv($qrUrl) ?>" class="small" style="color:#67e8f9;word-break:break-all"><?= hv($qrUrl) ?></a></div>
          <div class="mt-2">
            <a href="https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=<?= urlencode($qrUrl) ?>"
               class="btn-soft" style="font-size:.82rem;padding:8px 14px" target="_blank" rel="noopener">
              <i class="bi bi-download me-1"></i>Télécharger HD
            </a>
          </div>
          <div class="mt-3">
            <a href="equipement.php?id=<?= (int)$equip['id'] ?>" class="btn-fiche" style="display:block;text-align:center;padding:8px 14px">
              <i class="bi bi-card-text me-1"></i>Voir la fiche complète
            </a>
          </div>
        <?php else: ?>
          <p class="muted mb-0">Scanne un équipement pour voir son QR code ici.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ═══ DÉTAILS SCAN + HISTORIQUE MOUVEMENTS ═══ -->
  <div class="row g-4 mb-4">
    <div class="col-lg-4">
      <div class="glass p-4 h-100">
        <div class="section-title"><i class="bi bi-card-heading me-2"></i>Détails du scan</div>
        <?php if (isset($equip)&&$equip): ?>
          <p class="mb-1"><strong>QR Code</strong> <code style="color:#67e8f9"><?= hv($equip['qr_code']) ?></code></p>
          <p class="mb-1"><strong>Nom</strong> <?= hv($equip['nom']??'') ?></p>
          <p class="mb-1"><strong>Type</strong> <?= hv($equip['type']??'') ?></p>
          <p class="mb-1"><strong>N° Série</strong> <code style="color:#67e8f9;font-size:.8rem"><?= hv($equip['numero_serie']??'') ?></code></p>
          <p class="mb-1"><strong>Site</strong> <?= siteBadge($equip['localisation']??'') ?></p>
          <p class="mb-2"><strong>Statut</strong> <?= dispStatut($equip['statut']??'') ?></p>
          <div class="mb-3">
            <a href="equipement.php?id=<?= (int)$equip['id'] ?>" class="btn-fiche" style="display:inline-block">
              <i class="bi bi-card-text me-1"></i>Ouvrir la fiche complète
            </a>
          </div>

          <!-- Jauge réparation + ticket -->
          <?php if ($activeRepair):
            $curStat=$activeRepair['statut']??'';
            $curIdx=array_search($curStat,$wfSteps); if ($curIdx===false) $curIdx=0;
            $pct=$wfPct[$curIdx]??0; $gaugeClr=repGaugeColor($curStat);
          ?>
          <hr style="border-color:rgba(255,255,255,.10)">
          <div class="rep-gauge-wrap mb-2">
            <div style="font-size:.8rem;font-weight:800;color:#fdba74;margin-bottom:6px"><i class="bi bi-tools me-1"></i>Réparation en cours</div>
            <div class="d-flex align-items-center justify-content-between mb-1">
              <?= reparBadge($curStat) ?>
              <span style="font-size:.8rem;font-weight:700;color:<?= $gaugeClr ?>"><?= $pct ?>%</span>
            </div>
            <div class="rep-gauge-bg"><div class="rep-gauge-fill" style="width:<?= $pct ?>%;background:<?= $gaugeClr ?>"></div></div>
            <?php if (!empty($activeRepair['numero_ticket'])): ?>
            <div style="margin-top:8px">
              <span class="ticket-badge" title="Cliquer pour copier" onclick="copyTicket(this,'<?= hv($activeRepair['numero_ticket']) ?>')">
                <i class="bi bi-ticket-perforated"></i>
                <span><?= hv($activeRepair['numero_ticket']) ?></span>
                <i class="bi bi-clipboard" style="font-size:.65rem;opacity:.65"></i>
              </span>
            </div>
            <?php endif; ?>
            <div class="d-flex flex-wrap gap-1 mt-2">
              <?php foreach ($wfSteps as $wi=>$ws):
                $cls=$wi<$curIdx?'wf-done-m':($wi===$curIdx?'wf-act-m':'wf-pend-m'); ?>
              <span class="wf-step-mini <?= $cls ?>"><?php if ($wi<$curIdx): ?><i class="bi bi-check2"></i><?php endif; ?><?= hv($ws) ?></span>
              <?php endforeach; ?>
            </div>
            <?php if (!empty($activeRepair['panne_declaree'])): ?>
            <div style="font-size:.78rem;color:#94a3b8;margin-top:8px"><i class="bi bi-exclamation-circle me-1"></i><?= hv(mb_substr($activeRepair['panne_declaree'],0,80)) ?></div>
            <?php endif; ?>
            <?php if (!empty($activeRepair['pieces_a_changer'])): ?>
            <div style="font-size:.75rem;color:#fbbf24;margin-top:4px"><i class="bi bi-cpu me-1"></i><?= hv(mb_substr($activeRepair['pieces_a_changer'],0,60)) ?></div>
            <?php endif; ?>
            <?php if (!empty($activeRepair['technicien'])): ?>
            <div style="font-size:.72rem;color:#94a3b8;margin-top:4px"><i class="bi bi-person me-1"></i><?= hv($activeRepair['technicien']) ?></div>
            <?php endif; ?>
            <div style="font-size:.7rem;color:#64748b;margin-top:6px">
              <i class="bi bi-calendar me-1"></i>Ouvert le <?= date('d/m/Y',strtotime($activeRepair['date_ouverture'])) ?>
              &nbsp;<a href="admin.php?tab=reparations" style="color:#6366f1;font-weight:700">Gérer →</a>
            </div>
          </div>
          <?php endif; ?>

          <!-- Derniers événements -->
          <?php if (!empty($lastEvents)): ?>
          <hr style="border-color:rgba(255,255,255,.10)">
          <div style="font-size:.78rem;font-weight:800;color:var(--muted);margin-bottom:8px;text-transform:uppercase;letter-spacing:.08em">
            <i class="bi bi-clock-history me-1"></i>Derniers événements
          </div>
          <?php foreach ($lastEvents as $ev): ?>
          <div class="event-line">
            <div class="event-icon"><i class="bi <?= hv(eventIcon($ev['type_event'])) ?>"></i></div>
            <div class="flex-grow-1">
              <div style="font-size:.85rem;font-weight:700"><?= hv($ev['titre']) ?></div>
              <?php if (!empty($ev['details'])): ?><div style="font-size:.75rem;color:var(--muted)"><?= hv(mb_substr($ev['details'],0,60)) ?></div><?php endif; ?>
            </div>
            <div style="font-size:.72rem;color:var(--muted);white-space:nowrap"><?= date('d/m H:i',strtotime($ev['created_at'])) ?></div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>

          <!-- Historique réparations clôturées -->
          <?php if (!empty($repairHistory)): ?>
          <hr style="border-color:rgba(255,255,255,.10)">
          <div style="font-size:.78rem;font-weight:800;color:var(--muted);margin-bottom:8px;text-transform:uppercase;letter-spacing:.08em">
            <i class="bi bi-wrench-adjustable me-1" style="color:#86efac"></i>Réparations passées
          </div>
          <?php foreach ($repairHistory as $rh): ?>
          <div style="background:rgba(34,197,94,.06);border:1px solid rgba(34,197,94,.12);border-radius:10px;padding:8px 10px;margin-bottom:6px">
            <div class="d-flex align-items-center justify-content-between gap-1 mb-1">
              <?= reparBadge($rh['statut']??'') ?>
              <span style="font-size:.7rem;color:#94a3b8;white-space:nowrap"><?= !empty($rh['date_cloture'])?date('d/m/Y',strtotime($rh['date_cloture'])):'—' ?></span>
            </div>
            <?php if (!empty($rh['panne_declaree'])): ?>
            <div style="font-size:.75rem;color:#94a3b8;margin-bottom:2px"><i class="bi bi-exclamation-circle me-1"></i><?= hv(mb_substr($rh['panne_declaree'],0,55)) ?></div>
            <?php endif; ?>
            <?php if (!empty($rh['pieces_changees'])): ?>
            <div style="font-size:.72rem;color:#fcd34d;margin-bottom:2px"><i class="bi bi-cpu me-1"></i><?= hv(mb_substr($rh['pieces_changees'],0,50)) ?></div>
            <?php endif; ?>
            <div style="font-size:.7rem;color:#64748b">
              <?php if (!empty($rh['technicien'])): ?><i class="bi bi-person me-1"></i><?= hv($rh['technicien']) ?> &nbsp;<?php endif; ?>
              <?php if (!empty($rh['cout_reparation'])&&$rh['cout_reparation']>0): ?>
              <span style="color:#34d399"><i class="bi bi-currency-euro me-1"></i><?= number_format((float)$rh['cout_reparation'],2,',',' ') ?></span>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>

        <?php else: ?>
          <p class="muted mb-0">Aucun équipement sélectionné.<br>
            <small>Scannez un QR ou cliquez sur <strong>Scanner</strong> dans le tableau.</small></p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Historique mouvements -->
    <div class="col-lg-8">
      <div class="glass p-4 h-100">
        <div class="section-title"><i class="bi bi-clock-history me-2"></i>Historique des mouvements</div>
        <div class="table-responsive table-wrap">
          <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Équipement</th><th>QR Code</th><th>Action</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($history as $row): ?>
            <tr>
              <td style="font-weight:700"><?= hv($row['nom']) ?></td>
              <td><code style="color:#67e8f9;font-size:.78rem"><?= hv($row['qr_code']) ?></code></td>
              <td><?= dispStatut($row['action']) ?></td>
              <td style="font-size:.82rem;color:var(--muted)"><?= hv($row['date_action']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($history)): ?>
            <tr><td colspan="4" class="text-center muted py-3"><i class="bi bi-inbox me-2"></i>Aucun mouvement</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="footer-note text-center pb-2">
    Interface sécurisée — admin only &nbsp;|&nbsp;
    <span style="color:#67e8f9;font-family:monospace;font-size:.85rem"><?= hv($baseUrl) ?></span>
  </div>
</div>

<!-- MODAL ATTRIBUTION -->
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
          <input type="hidden" name="equipid" id="modalEquipId">
          <label class="form-label fw-bold">Sélectionner un utilisateur</label>
          <select name="utilisateurid" class="form-select w-100 mt-1">
            <option value="">— Aucun (libérer) —</option>
            <?php foreach ($utilisateurs as $u): ?>
            <option value="<?= (int)$u['id'] ?>"><?= hv(($u['prenom']??'').' '.($u['nom']??'').' — '.($u['service']??'')) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" name="attribuer" value="1" class="btn btn-primary rounded-3"><i class="bi bi-check2 me-1"></i>Confirmer</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ── Recherche instantanée ── */
(function(){
  const input=document.getElementById('searchInput');
  const counter=document.getElementById('searchCount');
  const noRes=document.getElementById('noSearchResult');
  const rows=Array.from(document.querySelectorAll('#equipTable tbody tr'));
  const total=rows.length;
  let timer;
  input.addEventListener('input',function(){
    clearTimeout(timer);
    timer=setTimeout(function(){
      const val=input.value.toLowerCase().trim();
      let visible=0;
      rows.forEach(function(tr){
        const match=!val||(tr.dataset.search||'').includes(val);
        tr.style.display=match?'':'none';
        if(match) visible++;
      });
      counter.textContent=val?visible+' résultat'+(visible!==1?'s':''):total+' éléments';
      noRes.style.display=(val&&visible===0)?'':'none';
    },150);
  });
  input.addEventListener('keydown',function(e){
    if(e.key==='Escape'){input.value='';input.dispatchEvent(new Event('input'));}
  });
})();

/* ── Navigation par clic sur la ligne → equipement.php?id=X ── */
document.querySelectorAll('#equipTable tbody tr.equip-row').forEach(function(tr){
  tr.addEventListener('click',function(e){
    if(e.target.closest('button,a,form,select,input')) return;
    var href=tr.dataset.href;
    if(href) window.location=href;
  });
});

/* ── Modal attribution ── */
function openModal(ds){
  var id=ds.id||'', nom=ds.nom||'', uid=ds.uid||'';
  document.getElementById('modalEquipId').value=id;
  document.getElementById('modalEquipNom').textContent=nom;
  var sel=document.querySelector('select[name="utilisateurid"]');
  sel.value=(uid&&uid!=='null')?uid:'';
  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAttrib')).show();
}

/* ── preFillScan ── */
function preFillScan(qr){
  document.getElementById('qrInput').value=qr;
  document.getElementById('scanForm').submit();
}

/* ── Auto-submit scan sur Enter (lecteur physique) ── */
document.getElementById('qrInput').addEventListener('keydown',function(e){
  if(e.key==='Enter'){e.preventDefault();document.getElementById('scanForm').submit();}
});

/* ── Copier numéro de ticket ── */
function copyTicket(el,val){
  var span=el.querySelector('span')||el;
  var orig=span.textContent;
  navigator.clipboard.writeText(val).then(function(){
    span.textContent='✓ Copié !';
    el.style.background='rgba(34,197,94,.2)';
    el.style.borderColor='rgba(34,197,94,.5)';
    el.style.color='#86efac';
    setTimeout(function(){
      span.textContent=orig;
      el.style.background='';el.style.borderColor='';el.style.color='';
    },1800);
  }).catch(function(){ prompt('N° de ticket :',val); });
}
</script>
</body>
</html>
