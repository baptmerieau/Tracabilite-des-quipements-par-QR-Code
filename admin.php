<?php
ob_start();
error_reporting(E_ALL); ini_set('display_errors',1);
session_start();
require_once 'db.php';

function hv(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function goAdmin(string $msg, string $tab='equipements'): void {
    $_SESSION['admin_msg']=$msg;
    header('Location: admin.php?tab='.$tab); exit;
}
function siteBadge(string $site): string {
    $c=['Paris'=>'#3b82f6','Boulogne'=>'#8b5cf6','Lyon'=>'#f59e0b','Uxbridge'=>'#10b981','Rennes'=>'#ef4444'];
    $col=$c[$site]??'#94a3b8';
    return $site?'<span style="background:'.$col.'22;border:1px solid '.$col.'55;color:'.$col.';padding:3px 10px;border-radius:999px;font-size:.78rem;font-weight:700">'.hv($site).'</span>':'<span style="color:#94a3b8">—</span>';
}
function reparBadge(string $etat): string {
    $map=['RAS'=>['#22c55e','#14532d'],'A diagnostiquer'=>['#f59e0b','#451a03'],'En réparation'=>['#3b82f6','#1e3a5f'],
          'En attente de pièces'=>['#f97316','#431407'],'Test après réparation'=>['#a78bfa','#2e1065'],
          'Réparé'=>['#34d399','#064e3b'],'Restitué'=>['#22c55e','#14532d'],'Hors service'=>['#ef4444','#450a0a']];
    $e=$etat?:'RAS'; [$fg,$bg]=$map[$e]??['#94a3b8','#1e293b'];
    return '<span style="background:'.$bg.';border:1px solid '.$fg.'55;color:'.$fg.';padding:3px 8px;border-radius:999px;font-size:.75rem;font-weight:700">'.hv($e).'</span>';
}
function tableOk(PDO $pdo, string $t): bool {
    static $cache=[];
    if (!isset($cache[$t])) { try { $pdo->query("SELECT 1 FROM `$t` LIMIT 0"); $cache[$t]=true; } catch (PDOException $e) { $cache[$t]=false; } }
    return $cache[$t];
}
function colOk(PDO $pdo, string $t, string $c): bool {
    static $cache=[];
    $k=$t.'.'.$c;
    if (!isset($cache[$k])) { try { $pdo->query("SELECT `$c` FROM `$t` LIMIT 0"); $cache[$k]=true; } catch (PDOException $e) { $cache[$k]=false; } }
    return $cache[$k];
}

/* ── Auth ── */
if (!isset($_SESSION['admin_ok'])) $_SESSION['admin_ok']=false;
if (!isset($_SESSION['admin_msg'])) $_SESSION['admin_msg']='';
if (isset($_POST['admin_login'],$_POST['admin_pass'])) {
    if ($_POST['admin_login']==='admin' && $_POST['admin_pass']==='Sodia01') {
        $_SESSION['admin_ok']=true; goAdmin('Accès accordé');
    } else { $_SESSION['admin_msg']='Identifiants incorrects'; header('Location: admin.php'); exit; }
}
if (isset($_POST['logout'])) { $_SESSION['admin_ok']=false; header('Location: admin.php'); exit; }

$wfSteps=['A diagnostiquer','En réparation','En attente de pièces','Test après réparation','Réparé','Restitué'];
$priorList=['Normale','Haute','Critique'];
$statuts=['Disponible','En prêt','En maintenance','Hors service'];
$types=['PC','Téléphone','Tablette','Écran','Imprimante','Serveur','Autre'];
$sites=['Paris','Boulogne','Lyon','Uxbridge','Rennes'];
$etats=['RAS','A diagnostiquer','En réparation','En attente de pièces','Test après réparation','Réparé','Restitué','Hors service'];

if (!$_SESSION['admin_ok']) {
?>
<!DOCTYPE html><html lang="fr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Administration — Accès sécurisé</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{min-height:100vh;display:grid;place-items:center;color:#e5e7eb;background:radial-gradient(circle at 20% 20%,rgba(239,68,68,.2),transparent 24%),radial-gradient(circle at 80% 30%,rgba(168,85,247,.18),transparent 24%),linear-gradient(135deg,#050816,#0f172a)}
.card{width:min(440px,92vw);background:rgba(15,23,42,.80);backdrop-filter:blur(18px);border:1px solid rgba(255,255,255,.12);border-radius:24px;padding:36px;box-shadow:0 30px 80px rgba(0,0,0,.5)}
.form-control{background:rgba(255,255,255,.08)!important;color:#fff!important;border:1px solid rgba(255,255,255,.14)!important;border-radius:12px;padding:12px 14px;box-shadow:none!important}
.form-control::placeholder{color:#fca5a5}
.btn-login{border:none;border-radius:12px;padding:13px;font-weight:800;background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;width:100%;cursor:pointer;font-size:1rem}
</style></head><body>
<div class="card">
  <div class="text-center mb-4"><i class="bi bi-shield-lock" style="font-size:3rem;color:#f87171"></i>
    <h1 class="h3 mt-2 fw-bold" style="color:#fca5a5">Administration</h1><p style="color:#fca5a5;font-size:.9rem">Accès réservé aux administrateurs</p></div>
  <?php if (!empty($_SESSION['admin_msg'])): ?>
  <div class="alert alert-danger border-0 rounded-3 mb-3"><?= hv($_SESSION['admin_msg']) ?></div>
  <?php $_SESSION['admin_msg']=''; ?>
  <?php endif; ?>
  <form method="post">
    <div class="mb-3"><label class="form-label fw-bold" style="color:#f87171">Identifiant</label>
      <input type="text" name="admin_login" class="form-control" placeholder="admin" autocomplete="off" required></div>
    <div class="mb-4"><label class="form-label fw-bold" style="color:#f87171">Mot de passe</label>
      <input type="password" name="admin_pass" class="form-control" placeholder="••••••••" required></div>
    <button type="submit" class="btn-login"><i class="bi bi-unlock-fill me-2"></i>Accéder à l'administration</button>
  </form>
  <div class="text-center mt-3"><a href="index.php" style="color:#fca5a5;font-size:.85rem"><i class="bi bi-arrow-left me-1"></i>Retour au tableau de bord</a></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
<?php ob_end_flush(); exit; ?>
<?php } ?>
<?php
/* ═══════════════════════════════════
   POST HANDLERS
═══════════════════════════════════ */

$action = trim($_POST['action'] ?? '');

/* ── Équipement : CRUD ── */
if ($action === 'add_equip') {
    try {
        $cols='nom,type,numero_serie,localisation,statut,qr_code';
        $vals='?,?,?,?,?,?';
        $bind=[trim($_POST['nom']??''),trim($_POST['type']??''),trim($_POST['numero_serie']??''),trim($_POST['localisation']??''),trim($_POST['statut']??'Disponible'),trim($_POST['qr_code']??'QR-'.uniqid())];
        foreach (['marque','modele','categorie','date_achat','garantie_fin','commentaire_technique'] as $c)
            if (colOk($pdo,'equipements',$c) && isset($_POST[$c]) && $_POST[$c]!=='') { $cols.=','.$c; $vals.=',?'; $bind[]=trim($_POST[$c]); }
        if (colOk($pdo,'equipements','criticite') && isset($_POST['criticite'])) { $cols.=',criticite'; $vals.=',?'; $bind[]=trim($_POST['criticite']); }
        if (colOk($pdo,'equipements','etat_reparation')) { $cols.=',etat_reparation'; $vals.=",?"; $bind[]='RAS'; }
        $pdo->prepare("INSERT INTO equipements($cols) VALUES($vals)")->execute($bind);
        goAdmin('Équipement ajouté avec succès');
    } catch (Exception $e) { goAdmin('Erreur : '.$e->getMessage()); }
}
if ($action === 'edit_equip') {
    $id=(int)($_POST['id']??0);
    try {
        $sets=['nom=?','type=?','numero_serie=?','localisation=?','statut=?'];
        $bind=[trim($_POST['nom']??''),trim($_POST['type']??''),trim($_POST['numero_serie']??''),trim($_POST['localisation']??''),trim($_POST['statut']??'')];
        foreach (['marque','modele','categorie','date_achat','garantie_fin','commentaire_technique'] as $c)
            if (colOk($pdo,'equipements',$c) && isset($_POST[$c])) { $sets[]=$c.'=?'; $bind[]=trim($_POST[$c]); }
        if (colOk($pdo,'equipements','criticite') && isset($_POST['criticite'])) { $sets[]='criticite=?'; $bind[]=trim($_POST['criticite']); }
        if (colOk($pdo,'equipements','etat_reparation') && isset($_POST['etat_reparation'])) { $sets[]='etat_reparation=?'; $bind[]=trim($_POST['etat_reparation']); }
        $bind[]=$id;
        $pdo->prepare("UPDATE equipements SET ".implode(',',$sets)." WHERE id=?")->execute($bind);
        goAdmin('Équipement modifié');
    } catch (Exception $e) { goAdmin('Erreur : '.$e->getMessage()); }
}
if ($action === 'delete_equip') {
    $id=(int)($_POST['id']??0);
    try { $pdo->prepare("DELETE FROM equipements WHERE id=?")->execute([$id]); goAdmin('Équipement supprimé'); }
    catch (Exception $e) { goAdmin('Erreur : '.$e->getMessage()); }
}

/* ── Utilisateur : CRUD ── */
if ($action === 'add_user') {
    try {
        $pdo->prepare("INSERT INTO utilisateurs(nom,prenom,service) VALUES(?,?,?)")
            ->execute([trim($_POST['nom']??''),trim($_POST['prenom']??''),trim($_POST['service']??'')]);
        goAdmin('Utilisateur ajouté','utilisateurs');
    } catch (Exception $e) { goAdmin('Erreur : '.$e->getMessage(),'utilisateurs'); }
}
if ($action === 'edit_user') {
    $id=(int)($_POST['id']??0);
    try {
        $pdo->prepare("UPDATE utilisateurs SET nom=?,prenom=?,service=? WHERE id=?")
            ->execute([trim($_POST['nom']??''),trim($_POST['prenom']??''),trim($_POST['service']??''),$id]);
        goAdmin('Utilisateur modifié','utilisateurs');
    } catch (Exception $e) { goAdmin('Erreur : '.$e->getMessage(),'utilisateurs'); }
}
if ($action === 'delete_user') {
    $id=(int)($_POST['id']??0);
    try {
        $pdo->prepare("UPDATE equipements SET utilisateur_id=NULL WHERE utilisateur_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM utilisateurs WHERE id=?")->execute([$id]);
        goAdmin('Utilisateur supprimé','utilisateurs');
    } catch (Exception $e) { goAdmin('Erreur : '.$e->getMessage(),'utilisateurs'); }
}

/* ── Réparation : OUVRIR ── */
if ($action === 'open_repair') {
    $eid   = (int)($_POST['equipement_id'] ?? 0);
    $panne = trim($_POST['panne_declaree'] ?? '');
    $tech  = trim($_POST['technicien'] ?? '');
    $prior = trim($_POST['priorite'] ?? 'Normale');
    $ticket= trim($_POST['numero_ticket'] ?? '');
    try {
        if (!tableOk($pdo,'reparations')) throw new Exception("Table 'reparations' introuvable. Exécutez database_update.sql.");
        /* Construction dynamique — colonnes optionnelles selon version BDD */
        $repCols = 'equipement_id,statut,panne_declaree,technicien,date_ouverture,date_mise_a_jour';
        $repVals = "?,'A diagnostiquer',?,?,NOW(),NOW()";
        $repBind = [$eid,$panne,$tech];
        if (colOk($pdo,'reparations','priorite'))      { $repCols.=',priorite';      $repVals.=',?'; $repBind[]=$prior; }
        if (colOk($pdo,'reparations','numero_ticket')) { $repCols.=',numero_ticket'; $repVals.=',?'; $repBind[]=$ticket; }
        $pdo->prepare("INSERT INTO reparations($repCols) VALUES($repVals)")->execute($repBind);
        if (colOk($pdo,'equipements','etat_reparation'))
            $pdo->prepare("UPDATE equipements SET etat_reparation='A diagnostiquer' WHERE id=?")->execute([$eid]);
        if (tableOk($pdo,'equipement_logs'))
            $pdo->prepare("INSERT INTO equipement_logs(equipement_id,type_event,titre,details,auteur) VALUES(?,'reparation','Réparation ouverte',?,?)")
                ->execute([$eid,'Panne : '.$panne.($ticket?' | Ticket: '.$ticket:''),'admin']);
        goAdmin('Réparation ouverte'.($ticket?' (ticket '.$ticket.')':''),'reparations');
    } catch (Exception $e) { goAdmin('Erreur : '.$e->getMessage(),'reparations'); }
}

/* ── Réparation : METTRE À JOUR ── */
if ($action === 'update_repair') {
    $rid     = (int)($_POST['rep_id'] ?? 0);
    $eid     = (int)($_POST['equipement_id'] ?? 0);
    $newStat = trim($_POST['nouveau_statut'] ?? '');
    $comment = trim($_POST['commentaire'] ?? '');
    $piece   = trim($_POST['piece_changee'] ?? '');
    try {
        if (!tableOk($pdo,'reparations')) throw new Exception("Table 'reparations' introuvable.");
        $sets = ['statut=?','date_mise_a_jour=NOW()'];
        $bind = [$newStat];
        foreach (['diagnostic','action_prevue','pieces_a_changer','pieces_changees','technicien','cout_reparation','fournisseur_piece','numero_ticket'] as $c) {
            if (isset($_POST[$c]) && $_POST[$c]!=='' && colOk($pdo,'reparations',$c)) { $sets[]="$c=?"; $bind[]=trim($_POST[$c]); }
        }
        if (in_array($newStat,['Réparé','Restitué'],true)) $sets[]='date_cloture=NOW()';
        $bind[]=$rid;
        $pdo->prepare("UPDATE reparations SET ".implode(',',$sets)." WHERE id=?")->execute($bind);
        if (colOk($pdo,'equipements','etat_reparation'))
            $pdo->prepare("UPDATE equipements SET etat_reparation=? WHERE id=?")->execute([$newStat,$eid]);
        if (tableOk($pdo,'reparation_logs'))
            $pdo->prepare("INSERT INTO reparation_logs(reparation_id,statut,commentaire,piece_changee,auteur,created_at) VALUES(?,?,?,?,?,NOW())")
                ->execute([$rid,$newStat,$comment,$piece,'admin']);
        if (tableOk($pdo,'equipement_logs'))
            $pdo->prepare("INSERT INTO equipement_logs(equipement_id,type_event,titre,details,auteur) VALUES(?,'reparation',?,?,?)")
                ->execute([$eid,'Réparation → '.$newStat,$comment?:'Statut mis à jour','admin']);
        goAdmin('Réparation mise à jour','reparations');
    } catch (Exception $e) { goAdmin('Erreur : '.$e->getMessage(),'reparations'); }
}

if ($action === 'close_repair') {
    $repId = (int)($_POST['rep_id'] ?? 0);
    $eid   = (int)($_POST['equipement_id'] ?? 0);
    $finalStat = in_array($_POST['final_statut']??'',['Réparé','Restitué','Hors service'])
                 ? $_POST['final_statut'] : 'Réparé';
    try {
        if (!tableOk($pdo,'reparations')) throw new Exception("Table 'reparations' introuvable.");
        $sets=['statut=?','date_cloture=NOW()','date_mise_a_jour=NOW()'];
        $bind=[$finalStat];
        foreach (['diagnostic','pieces_changees','cout_reparation','technicien'] as $c)
            if (!empty($_POST[$c]) && colOk($pdo,'reparations',$c)) { $sets[]=$c.'=?'; $bind[]=trim($_POST[$c]); }
        $bind[]=$repId;
        $pdo->prepare('UPDATE reparations SET '.implode(',',$sets).' WHERE id=?')->execute($bind);
        if (colOk($pdo,'equipements','etat_reparation'))
            $pdo->prepare('UPDATE equipements SET etat_reparation=? WHERE id=?')->execute([$finalStat,$eid]);
        if (tableOk($pdo,'reparation_logs'))
            $pdo->prepare('INSERT INTO reparation_logs(reparation_id,statut,commentaire,auteur,created_at) VALUES(?,?,?,?,NOW())')
                ->execute([$repId,$finalStat,trim($_POST['commentaire_cloture']??'Clôture réparation'),'admin']);
        if (tableOk($pdo,'equipement_logs'))
            $pdo->prepare("INSERT INTO equipement_logs(equipement_id,type_event,titre,details,auteur) VALUES(?,'reparation','Réparation clôturée',?,?)")
                ->execute([$eid,'Statut final : '.$finalStat,'admin']);
        goAdmin('✅ Réparation clôturée avec succès','reparations');
    } catch (Exception $e) { goAdmin('Erreur : '.$e->getMessage(),'reparations'); }
}

/* ═══════════════════════════════════
   CHARGEMENT DES DONNÉES PAR ONGLET
═══════════════════════════════════ */
$tab = $_GET['tab'] ?? 'equipements';
$flashMsg = $_SESSION['admin_msg']; $_SESSION['admin_msg']='';

$equipements=[];
try {
    $eCols = colOk($pdo,'equipements','etat_reparation') ? 'e.etat_reparation,' : "'RAS' AS etat_reparation,";
    $eCols2= colOk($pdo,'equipements','marque') ? 'e.marque,e.modele,e.criticite,' : "'' AS marque,'' AS modele,'' AS criticite,";
    $equipements=$pdo->query(
        "SELECT e.id,e.nom,e.type,e.numero_serie,e.localisation,e.statut,e.qr_code,
                e.utilisateur_id,e.date_attribution,{$eCols}{$eCols2}
                u.nom AS u_nom,u.prenom AS u_prenom
         FROM equipements e LEFT JOIN utilisateurs u ON u.id=e.utilisateur_id
         ORDER BY e.nom ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $equipements=[]; }

$utilisateurs=[];
try { $utilisateurs=$pdo->query("SELECT u.id,u.nom,u.prenom,u.service, COUNT(eq.id) AS nb FROM utilisateurs u LEFT JOIN equipements eq ON eq.utilisateur_id=u.id GROUP BY u.id ORDER BY u.nom ASC")->fetchAll(PDO::FETCH_ASSOC); } catch (PDOException $e) {}

// Vérification colonne numero_ticket
if (!isset($_SESSION['has_ticket_col'])) {
    try { $pdo->query("SELECT numero_ticket FROM reparations LIMIT 0"); $_SESSION['has_ticket_col']=1; }
    catch (PDOException $ex) { $_SESSION['has_ticket_col']=0; }
}
$reparations=[]; $repTableOk = tableOk($pdo,'reparations');
$hasTicketCol = $repTableOk && (bool)($_SESSION['has_ticket_col']??0);
if ($repTableOk) {
    try {
        $reparations=$pdo->query(
            "SELECT r.*,e.nom AS e_nom,e.type AS e_type,e.qr_code AS e_qr,e.numero_serie AS e_serie
             FROM reparations r
             JOIN equipements e ON e.id=r.equipement_id
             WHERE r.statut NOT IN ('Réparé','Restitué','Clôturé')
             ORDER BY FIELD(r.statut,'A diagnostiquer','En réparation','En attente de pièces','Test après réparation') DESC,r.date_ouverture ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

/* Réparations clôturées */
$closedRepairs=[];
if ($repTableOk) {
    try {
        $tcSel=$hasTicketCol?"r.numero_ticket,":"'' AS numero_ticket,";
        $closedRepairs=$pdo->query(
            "SELECT r.id,r.statut,r.panne_declaree,r.diagnostic,r.pieces_changees,
                    r.technicien,r.date_ouverture,r.date_cloture,r.cout_reparation,{$tcSel}
                    e.nom AS e_nom,e.type AS e_type,e.localisation AS e_loc,e.qr_code AS e_qr
             FROM reparations r
             JOIN equipements e ON e.id=r.equipement_id
             WHERE r.statut IN ('Réparé','Restitué','Clôturé','Hors service')
             ORDER BY r.date_cloture DESC
             LIMIT 80"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { $closedRepairs=[]; }
}

    } catch (PDOException $e) { $reparations=[]; }
}

/* Historique QR */
$histSearch=trim($_GET['qr_search']??'');
$histEquip=null; $histLogs=[]; $histMovs=[]; $histReps=[];
if ($histSearch!=='') {
    try {
        $eHist=$pdo->prepare("SELECT e.*,u.nom AS u_nom,u.prenom AS u_prenom,u.service AS u_service FROM equipements e LEFT JOIN utilisateurs u ON u.id=e.utilisateur_id WHERE e.qr_code=? OR e.numero_serie=? OR e.nom LIKE ? LIMIT 1");
        $eHist->execute([$histSearch,$histSearch,'%'.$histSearch.'%']);
        $histEquip=$eHist->fetch(PDO::FETCH_ASSOC)?:null;
    } catch (PDOException $e) {}
    if ($histEquip) {
        try { $s=$pdo->prepare("SELECT * FROM equipement_logs WHERE equipement_id=? ORDER BY created_at DESC LIMIT 30"); $s->execute([$histEquip['id']]); $histLogs=$s->fetchAll(PDO::FETCH_ASSOC); } catch (PDOException $e) {}
        try { $s=$pdo->prepare("SELECT * FROM movements WHERE equipement_id=? ORDER BY date_action DESC LIMIT 20"); $s->execute([$histEquip['id']]); $histMovs=$s->fetchAll(PDO::FETCH_ASSOC); } catch (PDOException $e) {}
        if ($repTableOk) { try { $s=$pdo->prepare("SELECT * FROM reparations WHERE equipement_id=? ORDER BY date_ouverture DESC"); $s->execute([$histEquip['id']]); $histReps=$s->fetchAll(PDO::FETCH_ASSOC); } catch (PDOException $e) {} }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Administration — Traçabilité</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--bg1:#070b18;--bg2:#111827;--card:rgba(15,23,42,.78);--line:rgba(255,255,255,.10);--txt:#e5e7eb;--muted:#94a3b8}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{min-height:100vh;color:var(--txt);font-family:system-ui,-apple-system,sans-serif;font-size:.95rem;background:radial-gradient(circle at 12% 12%,rgba(239,68,68,.18),transparent 24%),radial-gradient(circle at 88% 18%,rgba(168,85,247,.18),transparent 24%),linear-gradient(180deg,var(--bg1),var(--bg2))}
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
/* Flux d'alerte */
.flash{border-radius:12px;border:none;font-weight:600}
/* Barre recherche */
.search-bar{background:rgba(255,255,255,.06)!important;color:#fff!important;border:1px solid rgba(255,255,255,.12)!important;border-radius:12px;padding:9px 14px;box-shadow:none!important}
.search-bar::placeholder{color:#64748b}
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
      <form method="post" class="d-inline"><button type="submit" name="logout" class="btn btn-sm" style="background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#fca5a5;border-radius:10px"><i class="bi bi-box-arrow-right me-1"></i>Déconnexion</button></form>
    </div>
  </div>

  <!-- Flash message -->
  <?php if ($flashMsg): ?>
  <div class="alert flash <?= str_contains($flashMsg,'Erreur')||str_contains($flashMsg,'erreur')?'alert-danger':'alert-success' ?> mb-4"><?= hv($flashMsg) ?></div>
  <?php endif; ?>

  <!-- Onglets -->
  <nav class="admin-nav mb-4">
    <a href="admin.php?tab=equipements"  class="<?= $tab==='equipements' ?'active':'' ?>"><i class="bi bi-laptop me-1"></i>Équipements</a>
    <a href="admin.php?tab=utilisateurs" class="<?= $tab==='utilisateurs'?'active':'' ?>"><i class="bi bi-people me-1"></i>Utilisateurs</a>
    <a href="admin.php?tab=reparations"  class="<?= $tab==='reparations' ?'active':'' ?>"><i class="bi bi-tools me-1"></i>Réparations <?php if (!empty($reparations)): ?><span style="background:#ef4444;color:#fff;padding:1px 7px;border-radius:999px;font-size:.7rem;margin-left:4px"><?= count($reparations) ?></span><?php endif; ?></a>
    <a href="admin.php?tab=historique"   class="<?= $tab==='historique'  ?'active':'' ?>"><i class="bi bi-clock-history me-1"></i>Historique QR</a>
  </nav>

  <!-- ════════════════════════════════════
       ONGLET ÉQUIPEMENTS
  ════════════════════════════════════ -->
  <?php if ($tab==='equipements'): ?>
  <div class="row g-4">
    <!-- Formulaire ajout -->
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
    <!-- Liste équipements -->
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
            <?php foreach ($equipements as $e): $sd=strtolower(implode(' ',[$e['nom']??'',$e['type']??'',$e['localisation']??'',$e['statut']??'',$e['numero_serie']??''])); ?>
            <tr data-search="<?= hv($sd) ?>">
              <td>
                <div class="fw-bold"><?= hv($e['nom']??'') ?></div>
                <div class="muted" style="font-size:.72rem"><?= hv(trim(($e['marque']??'').' '.($e['modele']??''))) ?></div>
                <code style="color:#67e8f9;font-size:.72rem"><?= hv($e['numero_serie']??'') ?></code>
              </td>
              <td class="muted small"><?= hv($e['type']??'') ?></td>
              <td><?= siteBadge($e['localisation']??'') ?></td>
              <td><span class="badge <?= ($e['statut']??'')==='Disponible'?'bg-success':'bg-warning text-dark' ?>"><?= hv($e['statut']??'') ?></span></td>
              <td><?= reparBadge($e['etat_reparation']??'RAS') ?></td>
              <td>
                <div class="d-flex gap-1 flex-wrap">
                  <a href="equipement.php?id=<?= (int)$e['id'] ?>" class="btn btn-sm btn-success" title="Fiche équipement"><i class="bi bi-card-text"></i></a>
                  <button type="button" class="btn btn-sm btn-primary" data-json="<?= htmlspecialchars(json_encode($e), ENT_QUOTES, 'UTF-8') ?>" onclick="openEditEquip(JSON.parse(this.dataset.json))"><i class="bi bi-pencil"></i></button>
                  <form method="post" onsubmit="return confirm('Supprimer cet équipement ?')">
                    <input type="hidden" name="action" value="delete_equip"><input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                  </form>
                  <a href="admin.php?tab=reparations" onclick="return openRepairFromEquip(<?= (int)$e['id'] ?>,<?= json_encode($e['nom']??'', JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP) ?>)" class="btn btn-sm btn-warning" title="Ouvrir réparation"><i class="bi bi-tools"></i></a>
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
  <?php elseif ($tab==='utilisateurs'): ?>
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
              <td><?= hv($u['prenom']??'') ?></td>
              <td class="fw-bold"><?= hv($u['nom']??'') ?></td>
              <td class="muted small"><?= hv($u['service']??'') ?></td>
              <td><span class="badge bg-secondary"><?= (int)($u['nb']??0) ?></span></td>
              <td>
                <div class="d-flex gap-1">
                  <button type="button" class="btn btn-sm btn-primary" data-json="<?= htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8') ?>" onclick="openEditUser(JSON.parse(this.dataset.json))"><i class="bi bi-pencil"></i></button>
                  <form method="post" onsubmit="return confirm('Supprimer cet utilisateur ? Ses équipements seront libérés.')">
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
  <?php elseif ($tab==='reparations'): ?>
  <?php if (!$repTableOk): ?>
  <div class="alert alert-danger glass"><i class="bi bi-exclamation-triangle me-2"></i>Table <code>reparations</code> introuvable. Veuillez exécuter <code>database_update.sql</code> dans phpMyAdmin.</div>
  <?php else: ?>
  <div class="row g-4">
    <!-- Ouvrir une réparation -->
    <div class="col-lg-4">
      <div class="glass p-4" id="formOpenRepair">
        <div class="section-title"><i class="bi bi-tools me-2" style="color:#f59e0b"></i>Ouvrir une réparation</div>
        <form method="post" action="admin.php?tab=reparations">
          <input type="hidden" name="action" value="open_repair">
          <div class="mb-2"><label class="form-label">Équipement *</label>
            <select name="equipement_id" id="repEquipSelect" class="form-select" required>
              <option value="">— Sélectionner —</option>
              <?php foreach ($equipements as $e): ?>
              <option value="<?= (int)$e['id'] ?>"><?= hv($e['nom']??'') ?> — <?= hv($e['localisation']??'') ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="mb-2"><label class="form-label">Panne déclarée *</label>
            <textarea name="panne_declaree" class="form-control" rows="3" placeholder="Description du problème…" required></textarea></div>

          <!-- ▶ Champ Numéro de ticket ◀ -->
          <div class="mb-2">
            <label class="form-label"><i class="bi bi-ticket-perforated me-1" style="color:#67e8f9"></i>N° Ticket <small class="text-muted">(GLPI, ServiceNow, Jira…)</small></label>
            <input type="text" name="numero_ticket" id="repTicketInput" class="form-control" placeholder="ex: GLPI-4821 / INC0001234">
          </div>

          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label">Technicien</label>
              <input type="text" name="technicien" class="form-control" placeholder="Nom"></div>
            <div class="col-6"><label class="form-label">Priorité</label>
              <select name="priorite" class="form-select"><?php foreach ($priorList as $p): ?><option><?= hv($p) ?></option><?php endforeach; ?></select></div>
          </div>
          <button type="submit" class="btn btn-warning w-100"><i class="bi bi-tools me-2"></i>Ouvrir la réparation</button>
        </form>
      </div>
    </div>
    <!-- Réparations en cours -->
    <div class="col-lg-8">
      <div class="glass p-4">
        <div class="section-title"><i class="bi bi-list-check me-2" style="color:#f59e0b"></i>En cours (<?= count($reparations) ?>)</div>
        <?php if (empty($reparations)): ?>
        <div class="text-center muted py-5"><i class="bi bi-check-circle" style="font-size:2.5rem;color:#22c55e;display:block;margin-bottom:8px"></i>Aucune réparation en cours</div>
        <?php else: ?>
        <?php foreach ($reparations as $r):
          $cIdx=array_search($r['statut']??'',$wfSteps); if ($cIdx===false) $cIdx=0;
          $isDone=in_array($r['statut']??'',['Réparé','Restitué'],true);
          $cardClass='rep-card'.(strtolower($r['priorite']??'')==='haute'?' haute':(strtolower($r['priorite']??'')==='critique'?' critique':''));
        ?>
        <div class="<?= $cardClass ?>">
          <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-1">
            <div>
              <div style="font-weight:800;font-size:.95rem"><?= hv($r['e_nom']??'') ?></div>
              <div style="font-size:.75rem;color:#94a3b8"><?= hv($r['e_type']??'') ?> — <?= siteBadge($r['localisation']??$r['e_loc']??'') ?></div>
            </div>
            <div class="text-end">
              <?= reparBadge($r['statut']??'') ?>
              <?php if (!empty($r['priorite']) && $r['priorite']!=='Normale'): ?>
              <span style="display:block;font-size:.7rem;color:#f97316;margin-top:3px">⚠ <?= hv($r['priorite']) ?></span>
              <?php endif; ?>
            </div>
          </div>

          <!-- Badge ticket numéro -->
          <?php if (!empty($r['numero_ticket'])): ?>
          <div style="margin-bottom:6px">
            <span class="ticket-badge" title="Cliquer pour copier" onclick="copyTicket(this,'<?= hv($r['numero_ticket']) ?>')">
              <i class="bi bi-ticket-perforated"></i>
              <span><?= hv($r['numero_ticket']) ?></span>
              <i class="bi bi-clipboard" style="font-size:.7rem;opacity:.7"></i>
            </span>
          </div>
          <?php endif; ?>

          <?php if (!empty($r['panne_declaree'])): ?>
          <div style="font-size:.8rem;color:#94a3b8;margin-bottom:4px"><i class="bi bi-exclamation-circle me-1"></i><?= hv(mb_substr($r['panne_declaree'],0,100)) ?></div>
          <?php endif; ?>
          <?php if (!empty($r['technicien'])): ?>
          <div style="font-size:.78rem;color:#94a3b8"><i class="bi bi-person me-1"></i><?= hv($r['technicien']) ?></div>
          <?php endif; ?>
          <div style="font-size:.72rem;color:#64748b;margin-top:4px"><i class="bi bi-calendar me-1"></i>Ouvert le <?= date('d/m/Y',strtotime($r['date_ouverture'])) ?></div>

          <!-- Barre de workflow -->
          <div class="wf-bar">
            <?php foreach ($wfSteps as $wi=>$ws): ?>
            <div style="background:<?= $wi<=$cIdx?($isDone?'#22c55e':'#6366f1'):'rgba(255,255,255,.08)' ?>" title="<?= hv($ws) ?>"></div>
            <?php endforeach; ?>
          </div>

          <!-- Formulaire mise à jour -->
          <details class="mt-3">
            <summary style="cursor:pointer;color:#6366f1;font-size:.82rem;font-weight:700"><i class="bi bi-pencil-square me-1"></i>Mettre à jour / Modifier</summary>
            <form method="post" action="admin.php?tab=reparations" class="mt-2">
              <input type="hidden" name="action" value="update_repair">
              <input type="hidden" name="rep_id" value="<?= (int)$r['id'] ?>">
              <input type="hidden" name="equipement_id" value="<?= (int)($r['equipement_id']??0) ?>">
              <div class="row g-2">
                <div class="col-12"><label class="form-label">Statut</label>
                  <select name="nouveau_statut" class="form-select">
                    <?php foreach ($wfSteps as $ws): ?><option <?= $ws===$r['statut']?'selected':'' ?>><?= hv($ws) ?></option><?php endforeach; ?>
                  </select></div>
                <div class="col-12"><label class="form-label">Diagnostic / Commentaire</label>
                  <textarea name="commentaire" class="form-control" rows="2" placeholder="Observations…"><?= hv($r['diagnostic']??'') ?></textarea></div>
                <div class="col-12"><label class="form-label">Pièce changée</label>
                  <input type="text" name="piece_changee" class="form-control" placeholder="Réf. pièce, description…"></div>
                <div class="col-12">
                  <!-- ▶ Numéro de ticket dans la mise à jour ◀ -->
                  <label class="form-label"><i class="bi bi-ticket-perforated me-1" style="color:#67e8f9"></i>N° Ticket</label>
                  <input type="text" name="numero_ticket" class="form-control"
                         value="<?= hv($r['numero_ticket']??'') ?>"
                         placeholder="GLPI-4821 / INC0001234…">
                </div>
                <div class="col-6"><label class="form-label">Pièces à changer</label>
                  <input type="text" name="pieces_a_changer" class="form-control" value="<?= hv($r['pieces_a_changer']??'') ?>"></div>
                <div class="col-6"><label class="form-label">Coût (€)</label>
                  <input type="number" step="0.01" name="cout_reparation" class="form-control" value="<?= hv($r['cout_reparation']??'') ?>"></div>
                <div class="col-12">
                  <button type="submit" class="btn btn-primary w-100 btn-sm"><i class="bi bi-check2-circle me-1"></i>Enregistrer</button>
                </div>
              </div>
            </form>
          </details>
          <!-- ▶ BOUTON CLÔTURER ◀ -->
          <div class="mt-2">
            <button type="button" class="btn btn-sm w-100"
                    style="background:rgba(34,197,94,.15);border:1px solid rgba(34,197,94,.35);color:#86efac;font-weight:700"
                    data-rid="<?= (int)$r['id'] ?>"
                    data-eid="<?= (int)($r['equipement_id']??0) ?>"
                    data-nom="<?= hv($r['e_nom']??'') ?>"
                    data-diag="<?= hv($r['diagnostic']??'') ?>"
                    data-pieces="<?= hv($r['pieces_changees']??'') ?>"
                    data-tech="<?= hv($r['technicien']??'') ?>"
                    data-cout="<?= hv($r['cout_reparation']??'') ?>"
                    onclick="openCloseRepair(this.dataset)">
              <i class="bi bi-check2-all me-1"></i>Clôturer cette réparation
            </button>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ════ HISTORIQUE RÉPARATIONS CLÔTURÉES ════ -->
  <?php if (!empty($closedRepairs)): ?>
  <div class="glass p-4 mt-4">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
      <div class="section-title mb-0" style="color:#86efac">
        <i class="bi bi-check-circle me-2"></i>Réparations clôturées (<?= count($closedRepairs) ?>)
      </div>
      <div style="font-size:.78rem;color:#94a3b8">Les 80 plus récentes</div>
    </div>
    <div class="table-wrap">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Équipement</th><th>Site</th><th>Panne</th>
            <th>Pièces changées</th><th>Technicien</th>
            <th>Ouvert</th><th>Clôturé</th><th>Coût</th><th>Statut</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($closedRepairs as $cr): ?>
        <tr>
          <td>
            <div style="font-weight:700"><?= hv($cr['e_nom']??'') ?></div>
            <code style="font-size:.7rem;color:#67e8f9"><?= hv($cr['e_qr']??'') ?></code>
          </td>
          <td><?= siteBadge($cr['e_loc']??'') ?></td>
          <td style="font-size:.78rem;max-width:150px">
            <?= hv(mb_substr($cr['panne_declaree']??'',0,60)) ?>
            <?php if (!empty($cr['diagnostic'])): ?>
            <div style="color:#6366f1;font-size:.72rem;margin-top:2px"><?= hv(mb_substr($cr['diagnostic'],0,50)) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!empty($cr['pieces_changees'])): ?>
            <span style="background:rgba(251,191,36,.15);border:1px solid rgba(251,191,36,.25);color:#fcd34d;padding:2px 8px;border-radius:6px;font-size:.72rem">
              <i class="bi bi-cpu me-1"></i><?= hv(mb_substr($cr['pieces_changees'],0,40)) ?>
            </span>
            <?php else: ?><span class="muted" style="font-size:.75rem">—</span>
            <?php endif; ?>
          </td>
          <td style="font-size:.78rem"><?= hv($cr['technicien']??'—') ?></td>
          <td style="font-size:.75rem;color:#94a3b8;white-space:nowrap">
            <?= !empty($cr['date_ouverture'])?date('d/m/Y',strtotime($cr['date_ouverture'])):'—' ?>
          </td>
          <td style="font-size:.75rem;color:#86efac;white-space:nowrap">
            <?= !empty($cr['date_cloture'])?date('d/m/Y',strtotime($cr['date_cloture'])):'—' ?>
          </td>
          <td style="font-size:.82rem;font-weight:700;color:#34d399;white-space:nowrap">
            <?= !empty($cr['cout_reparation'])&&$cr['cout_reparation']>0?number_format((float)$cr['cout_reparation'],2,',',' ').' €':'—' ?>
          </td>
          <td><?= reparBadge($cr['statut']??'') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
  <?php endif; ?>

  <!-- ════════════════════════════════════
       ONGLET HISTORIQUE QR
  ════════════════════════════════════ -->
  <?php elseif ($tab==='historique'): ?>
  <div class="glass p-4 mb-4">
    <div class="section-title"><i class="bi bi-search me-2" style="color:#67e8f9"></i>Recherche par QR / N° Série / Nom</div>
    <form method="get" action="admin.php" class="row g-2 align-items-end">
      <input type="hidden" name="tab" value="historique">
      <div class="col-12 col-md-8">
        <input type="text" name="qr_search" class="form-control" value="<?= hv($histSearch) ?>"
               placeholder="QR code, numéro de série ou nom de l'équipement…" autofocus>
      </div>
      <div class="col-12 col-md-4 d-grid">
        <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Rechercher</button>
      </div>
    </form>
  </div>

  <?php if ($histSearch && !$histEquip): ?>
  <div class="alert alert-warning glass"><i class="bi bi-exclamation-triangle me-2"></i>Aucun équipement trouvé pour <strong><?= hv($histSearch) ?></strong></div>
  <?php endif; ?>

  <?php if ($histEquip): ?>
  <div class="row g-4">
    <!-- ── Fiche équipement redesignée ── -->
    <div class="col-lg-4">
      <div class="glass overflow-hidden">
        <!-- En-tête gradient -->
        <?php
        $typeIcon=['PC'=>'bi-pc-display','Téléphone'=>'bi-phone','Tablette'=>'bi-tablet','Écran'=>'bi-display','Imprimante'=>'bi-printer'];
        $tIcon=$typeIcon[$histEquip['type']??'']??'bi-laptop';
        $statOk=($histEquip['statut']??'')==='Disponible';
        ?>
        <div style="background:linear-gradient(135deg,rgba(99,102,241,.28),rgba(168,85,247,.18));padding:26px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,.08)">
          <div style="width:60px;height:60px;border-radius:50%;background:rgba(99,102,241,.25);border:2px solid rgba(99,102,241,.4);display:grid;place-items:center;margin:0 auto 10px;font-size:1.7rem;color:#a5b4fc">
            <i class="bi <?= hv($tIcon) ?>"></i>
          </div>
          <h5 style="font-weight:900;margin-bottom:6px;color:#f1f5f9"><?= hv($histEquip['nom']??'') ?></h5>
          <span style="background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);color:#e2e8f0;padding:3px 12px;border-radius:999px;font-size:.78rem;font-weight:700"><?= hv($histEquip['type']??'') ?></span>
          <?php if (!empty($histEquip['marque']) || !empty($histEquip['modele'])): ?>
          <div style="font-size:.78rem;color:#94a3b8;margin-top:5px"><?= hv(trim(($histEquip['marque']??'').' '.($histEquip['modele']??''))) ?></div>
          <?php endif; ?>
        </div>

        <!-- Infos clés -->
        <div style="padding:18px 20px">
          <div style="display:grid;grid-template-columns:auto 1fr;gap:7px 12px;align-items:center">
            <span style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#64748b">N° Série</span>
            <code style="color:#67e8f9;font-size:.78rem"><?= hv($histEquip['numero_serie']??'—') ?></code>
            <span style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#64748b">QR Code</span>
            <code style="color:#a5b4fc;font-size:.76rem"><?= hv($histEquip['qr_code']??'—') ?></code>
            <span style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#64748b">Site</span>
            <div><?= siteBadge($histEquip['localisation']??'') ?></div>
            <span style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#64748b">Statut</span>
            <span style="background:<?= $statOk?'rgba(34,197,94,.15)':'rgba(245,158,11,.15)' ?>;color:<?= $statOk?'#86efac':'#fdba74' ?>;padding:2px 10px;border-radius:999px;font-size:.75rem;font-weight:700"><?= hv($histEquip['statut']??'') ?></span>
            <?php if (!empty($histEquip['etat_reparation'])): ?>
            <span style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#64748b">Réparation</span>
            <div><?= reparBadge($histEquip['etat_reparation']??'RAS') ?></div>
            <?php endif; ?>
            <?php if (!empty($histEquip['u_nom'])): ?>
            <span style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#64748b">Attribué à</span>
            <span style="font-size:.82rem;color:#d8b4fe"><?= hv(($histEquip['u_prenom']??'').' '.($histEquip['u_nom']??'')) ?></span>
            <?php endif; ?>
          </div>
        </div>

        <!-- QR Code -->
        <div style="padding:0 20px 18px;text-align:center">
          <div style="display:inline-block;background:#fff;border-radius:14px;padding:10px;box-shadow:0 4px 16px rgba(0,0,0,.4)">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?= urlencode($histEquip['qr_code']??'') ?>"
                 alt="QR" width="150" height="150" loading="lazy" style="display:block;border-radius:6px">
          </div>
          <div style="margin-top:8px">
            <a href="https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=<?= urlencode($histEquip['qr_code']??'') ?>"
               target="_blank" rel="noopener" class="btn btn-sm btn-info" style="font-size:.78rem">
              <i class="bi bi-download me-1"></i>HD 400px
            </a>
          </div>
        </div>

        <!-- Stats rapides -->
        <div style="border-top:1px solid rgba(255,255,255,.08);padding:12px 20px;display:grid;grid-template-columns:1fr 1fr 1fr;text-align:center;gap:6px">
          <div>
            <div style="font-size:1.25rem;font-weight:900;color:#a5b4fc"><?= count($histLogs)+count($histMovs) ?></div>
            <div style="font-size:.62rem;text-transform:uppercase;letter-spacing:.06em;color:#64748b">Événements</div>
          </div>
          <div>
            <div style="font-size:1.25rem;font-weight:900;color:#f59e0b"><?= count($histReps) ?></div>
            <div style="font-size:.62rem;text-transform:uppercase;letter-spacing:.06em;color:#64748b">Réparations</div>
          </div>
          <div>
            <?php $totalCout=array_sum(array_column($histReps,'cout_reparation')); ?>
            <div style="font-size:1.25rem;font-weight:900;color:#34d399"><?= $totalCout>0?number_format($totalCout,0).'€':'—' ?></div>
            <div style="font-size:.62rem;text-transform:uppercase;letter-spacing:.06em;color:#64748b">Coût total</div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Droite : timeline + réparations ── -->
    <div class="col-lg-8">
      <!-- Timeline -->
      <?php if (!empty($histLogs) || !empty($histMovs)): ?>
      <div class="glass p-4 mb-4">
        <div class="section-title"><i class="bi bi-clock-history me-2" style="color:#6366f1"></i>Timeline (<?= count($histLogs)+count($histMovs) ?>)</div>
        <?php
        $all=[];
        foreach ($histLogs as $l) $all[]=['date'=>$l['created_at'],'titre'=>$l['titre']??'','detail'=>$l['details']??'','type'=>$l['type_event']??''];
        foreach ($histMovs as $m) $all[]=['date'=>$m['date_action'],'titre'=>$m['action']??'','detail'=>'','type'=>'mouvement'];
        usort($all,fn($a,$b)=>strtotime($b['date'])-strtotime($a['date']));
        $iconMap=['scan'=>'bi-qr-code-scan','mouvement'=>'bi-arrow-left-right','pret'=>'bi-person-fill','retour'=>'bi-box-arrow-in-left','reparation'=>'bi-tools','creation'=>'bi-plus-circle','modification'=>'bi-pencil'];
        $bgMap=['scan'=>'rgba(6,182,212,.2)','mouvement'=>'rgba(99,102,241,.2)','pret'=>'rgba(168,85,247,.2)','retour'=>'rgba(34,197,94,.2)','reparation'=>'rgba(245,158,11,.2)','creation'=>'rgba(16,185,129,.2)','modification'=>'rgba(245,158,11,.2)'];
        $tcMap=['scan'=>'#67e8f9','mouvement'=>'#a5b4fc','pret'=>'#d8b4fe','retour'=>'#86efac','reparation'=>'#fdba74','creation'=>'#6ee7b7','modification'=>'#fcd34d'];
        ?>
        <?php foreach ($all as $ev):
          $ic=$iconMap[$ev['type']]??'bi-dot'; $bg=$bgMap[$ev['type']]??'rgba(99,102,241,.15)'; $tc=$tcMap[$ev['type']]??'#a5b4fc';
        ?>
        <div class="tl-line">
          <div class="tl-dot" style="background:<?= $bg ?>"><i class="bi <?= hv($ic) ?>" style="color:<?= $tc ?>"></i></div>
          <div class="flex-grow-1">
            <div style="font-size:.85rem;font-weight:700;color:#f1f5f9"><?= hv($ev['titre']) ?></div>
            <?php if (!empty($ev['detail'])): ?><div style="font-size:.75rem;color:#94a3b8"><?= hv(mb_substr($ev['detail'],0,90)) ?></div><?php endif; ?>
          </div>
          <div style="font-size:.72rem;color:#64748b;white-space:nowrap;margin-left:8px"><?= date('d/m/Y H:i',strtotime($ev['date'])) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Réparations passées -->
      <?php if (!empty($histReps)): ?>
      <div class="glass p-4">
        <div class="section-title"><i class="bi bi-tools me-2" style="color:#f59e0b"></i>Historique des réparations (<?= count($histReps) ?>)</div>
        <div class="accordion accordion-flush" id="accRep">
        <?php foreach ($histReps as $ri=>$hr):
          $cIdx=array_search($hr['statut']??'',$wfSteps); if ($cIdx===false) $cIdx=0;
          $isDone=in_array($hr['statut']??'',['Réparé','Restitué'],true);
        ?>
        <div class="accordion-item" style="background:transparent;border:1px solid rgba(255,255,255,.08);border-radius:12px;margin-bottom:8px;overflow:hidden">
          <h2 class="accordion-header">
            <button type="button" class="accordion-button <?= $ri>0?'collapsed':'' ?>" type="button"
                    data-bs-toggle="collapse" data-bs-target="#rep<?= $ri ?>"
                    style="background:rgba(255,255,255,.04);color:#f1f5f9;font-size:.85rem;border-radius:12px;box-shadow:none">
              <span class="me-2"><?= reparBadge($hr['statut']??'') ?></span>
              <span style="font-weight:700"><?= date('d/m/Y',strtotime($hr['date_ouverture'])) ?></span>
              <?php if (!empty($hr['numero_ticket'])): ?>
              <span class="ticket-badge ms-2" onclick="event.stopPropagation();copyTicket(this,'<?= hv($hr['numero_ticket']) ?>')" title="Copier le ticket">
                <i class="bi bi-ticket-perforated"></i><?= hv($hr['numero_ticket']) ?>
              </span>
              <?php endif; ?>
              <?php if (!empty($hr['panne_declaree'])): ?><span style="color:#94a3b8;font-size:.78rem;margin-left:8px">— <?= hv(mb_substr($hr['panne_declaree'],0,40)) ?></span><?php endif; ?>
              <?php if ($isDone): ?><i class="bi bi-check-circle-fill ms-auto" style="color:#22c55e"></i><?php endif; ?>
            </button>
          </h2>
          <div id="rep<?= $ri ?>" class="accordion-collapse collapse <?= $ri===0?'show':'' ?>">
            <div style="padding:14px 16px;background:rgba(0,0,0,.2)">
              <?php if (!empty($hr['panne_declaree'])): ?><div style="margin-bottom:8px"><span style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b">Panne</span><br><span style="font-size:.85rem;color:#f1f5f9"><?= hv($hr['panne_declaree']) ?></span></div><?php endif; ?>
              <?php if (!empty($hr['diagnostic'])): ?><div style="margin-bottom:8px"><span style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b">Diagnostic</span><br><span style="font-size:.82rem;color:#e2e8f0"><?= hv($hr['diagnostic']) ?></span></div><?php endif; ?>
              <?php if (!empty($hr['pieces_changees'])): ?><div style="margin-bottom:8px;padding:8px 12px;background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.2);border-radius:8px"><i class="bi bi-cpu me-2" style="color:#fbbf24"></i><span style="font-size:.82rem;color:#fcd34d;font-weight:700"><?= hv($hr['pieces_changees']) ?></span></div><?php endif; ?>
              <div style="display:flex;flex-wrap:wrap;gap:10px;font-size:.78rem;color:#94a3b8;margin-top:6px">
                <?php if (!empty($hr['technicien'])): ?><span><i class="bi bi-person me-1"></i><?= hv($hr['technicien']) ?></span><?php endif; ?>
                <?php if (!empty($hr['cout_reparation']) && $hr['cout_reparation']>0): ?><span style="color:#34d399"><i class="bi bi-currency-euro me-1"></i><?= number_format((float)$hr['cout_reparation'],2) ?> €</span><?php endif; ?>
                <?php if (!empty($hr['date_cloture'])): ?><span><i class="bi bi-calendar-check me-1"></i>Clôturé le <?= date('d/m/Y',strtotime($hr['date_cloture'])) ?></span><?php endif; ?>
              </div>
              <div style="display:flex;gap:3px;margin-top:10px">
                <?php foreach ($wfSteps as $wi2=>$ws2): ?><div style="flex:1;height:5px;border-radius:3px;background:<?= $wi2<=$cIdx?($isDone?'#22c55e':'#6366f1'):'rgba(255,255,255,.08)' ?>" title="<?= hv($ws2) ?>"></div><?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if (empty($histLogs) && empty($histMovs) && empty($histReps)): ?>
      <div class="glass p-5 text-center"><i class="bi bi-inbox" style="font-size:3rem;color:#334155;display:block;margin-bottom:12px"></i><p class="muted">Aucun historique.</p></div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
  <?php endif; ?>

</div><!-- /container -->


<!-- MODAL CLÔTURE RÉPARATION -->
<div class="modal fade" id="modalCloseRepair" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background:#1e293b;border:1px solid rgba(255,255,255,.12);border-radius:20px;color:var(--txt)">
      <div class="modal-header" style="border-bottom:1px solid rgba(255,255,255,.08)">
        <h5 class="modal-title fw-bold"><i class="bi bi-check2-all me-2" style="color:#86efac"></i>Clôturer la réparation</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="admin.php?tab=reparations">
        <input type="hidden" name="action" value="close_repair">
        <input type="hidden" name="rep_id" id="closeRepId">
        <input type="hidden" name="equipement_id" id="closeEquipId">
        <div class="modal-body">
          <div class="alert mb-3" style="background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.25);border-radius:12px;color:#86efac;font-weight:700">
            <i class="bi bi-info-circle me-2"></i>Équipement : <span id="closeEquipNom"></span>
          </div>
          <div class="mb-2">
            <label class="form-label fw-bold">Statut final *</label>
            <select name="final_statut" class="form-select">
              <option value="Réparé">✅ Réparé — équipement fonctionnel</option>
              <option value="Restitué">📦 Restitué — remis à l'utilisateur</option>
              <option value="Hors service">❌ Hors service — irréparable</option>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label">Diagnostic / Action réalisée</label>
            <textarea name="diagnostic" id="closeRepDiag" class="form-control" rows="3" placeholder="Ce qui a été fait, cause identifiée…"></textarea>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-12">
              <label class="form-label">Pièces changées</label>
              <input type="text" name="pieces_changees" id="closeRepPieces" class="form-control" placeholder="Réf. pièces remplacées…">
            </div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label">Technicien</label>
              <input type="text" name="technicien" id="closeRepTech" class="form-control">
            </div>
            <div class="col-6">
              <label class="form-label">Coût total (€)</label>
              <input type="number" step="0.01" name="cout_reparation" id="closeRepCout" class="form-control" placeholder="0.00">
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label">Commentaire de clôture</label>
            <textarea name="commentaire_cloture" class="form-control" rows="2" placeholder="Notes finales…"></textarea>
          </div>
        </div>
        <div class="modal-footer" style="border-top:1px solid rgba(255,255,255,.08)">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn" style="background:linear-gradient(135deg,#16a34a,#15803d);color:#fff;font-weight:700;border:none">
            <i class="bi bi-check2-all me-1"></i>Confirmer la clôture
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL ÉDITION ÉQUIPEMENT -->
<div class="modal fade" id="modalEditEquip" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
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
            <div class="col-6"><label class="form-label">Type</label><select name="type" id="editEquipType" class="form-select"><?php foreach ($types as $t): ?><option><?= hv($t) ?></option><?php endforeach; ?></select></div>
            <div class="col-6"><label class="form-label">Statut</label><select name="statut" id="editEquipStatut" class="form-select"><?php foreach ($statuts as $s): ?><option><?= hv($s) ?></option><?php endforeach; ?></select></div>
            <div class="col-12"><label class="form-label">N° Série</label><input type="text" name="numero_serie" id="editEquipSerie" class="form-control"></div>
            <div class="col-6"><label class="form-label">Marque</label><input type="text" name="marque" id="editEquipMarque" class="form-control"></div>
            <div class="col-6"><label class="form-label">Modèle</label><input type="text" name="modele" id="editEquipModele" class="form-control"></div>
            <div class="col-12"><label class="form-label">Site</label><select name="localisation" id="editEquipLoc" class="form-select"><?php foreach ($sites as $s): ?><option><?= hv($s) ?></option><?php endforeach; ?></select></div>
            <div class="col-6"><label class="form-label">État réparation</label><select name="etat_reparation" id="editEquipEtat" class="form-select"><?php foreach ($etats as $et): ?><option><?= hv($et) ?></option><?php endforeach; ?></select></div>
            <div class="col-6"><label class="form-label">Criticité</label><select name="criticite" id="editEquipCrit" class="form-select"><option>Normale</option><option>Haute</option><option>Critique</option></select></div>
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
/* Filtrage équipements */
(function(){
  const inp=document.getElementById('equipSearch');
  if (!inp) return;
  const rows=Array.from(document.querySelectorAll('#equipTable tbody tr'));
  inp.addEventListener('input',function(){
    const v=inp.value.toLowerCase();
    rows.forEach(r=>r.style.display=(!v||(r.dataset.search||'').includes(v))?'':'none');
  });
})();


/* Modal clôture réparation */
function openCloseRepair(ds) {
  document.getElementById('closeRepId').value    = ds.rid||'';
  document.getElementById('closeEquipId').value  = ds.eid||'';
  document.getElementById('closeEquipNom').textContent = ds.nom||'';
  document.getElementById('closeRepDiag').value   = ds.diag||'';
  document.getElementById('closeRepPieces').value = ds.pieces||'';
  document.getElementById('closeRepTech').value   = ds.tech||'';
  document.getElementById('closeRepCout').value   = ds.cout||'';
  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCloseRepair')).show();
}
/* Modal édition équipement */
function openEditEquip(e) {
  document.getElementById('editEquipId').value=e.id;
  document.getElementById('editEquipNom').value=e.nom||'';
  document.getElementById('editEquipSerie').value=e.numero_serie||'';
  document.getElementById('editEquipMarque').value=e.marque||'';
  document.getElementById('editEquipModele').value=e.modele||'';
  setSelect('editEquipType',e.type);
  setSelect('editEquipStatut',e.statut);
  setSelect('editEquipLoc',e.localisation);
  setSelect('editEquipEtat',e.etat_reparation||'RAS');
  setSelect('editEquipCrit',e.criticite||'Normale');
  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditEquip')).show();
}
function setSelect(id,val){const s=document.getElementById(id);if(s&&val){for(let o of s.options){if(o.value===val||o.text===val){o.selected=true;break;}}}}

/* Modal édition utilisateur */
function openEditUser(u) {
  document.getElementById('editUserId').value=u.id;
  document.getElementById('editUserPrenom').value=u.prenom||'';
  document.getElementById('editUserNom').value=u.nom||'';
  document.getElementById('editUserService').value=u.service||'';
  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditUser')).show();
}

/* Ouvrir réparation depuis équipements (pré-remplit le formulaire) */
function openRepairFromEquip(id, nom) {
  const sel=document.getElementById('repEquipSelect');
  if (sel) {
    sel.value=id;
    setTimeout(function(){ sel.scrollIntoView({behavior:'smooth',block:'center'}); },300);
  }
  return true;
}

/* ── Copier le numéro de ticket ── */
function copyTicket(el, val) {
  const span = el.querySelector('span') || el;
  const orig = span.textContent;
  navigator.clipboard.writeText(val).then(function(){
    span.textContent = '✓ Copié !';
    el.style.background = 'rgba(34,197,94,.2)';
    el.style.borderColor = 'rgba(34,197,94,.5)';
    el.style.color = '#86efac';
    setTimeout(function(){
      span.textContent = orig;
      el.style.background = '';
      el.style.borderColor = '';
      el.style.color = '';
    }, 1800);
  }).catch(function(){ prompt('N° de ticket :', val); });
}
</script>
</body>
</html>
<?php ob_end_flush(); ?>
