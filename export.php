<?php
ob_start();
session_start();
require_once 'db.php';

/* ── Auth ── */
if (empty($_SESSION['authok'])) {
    header('Location: index.php'); exit;
}

function hv(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

/* ══════════════════════════════════════════
   TÉLÉCHARGEMENT CSV DIRECT si ?type= fourni
══════════════════════════════════════════ */
$type = $_GET['type'] ?? '';

if ($type !== '') {
    /* Nettoyage stricte du paramètre */
    $allowed = ['equipements','movements','logs','reparations','all'];
    if (!in_array($type, $allowed, true)) {
        header('Location: export.php'); exit;
    }

    ob_end_clean(); // On vide le buffer avant d'envoyer le CSV

    $filename = 'sodiaal_' . $type . '_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8 pour Excel

    /* ─── EXPORT EQUIPEMENTS ─── */
    if ($type === 'equipements' || $type === 'all') {
        if ($type === 'all') fputcsv($out, ['=== ÉQUIPEMENTS ==='], ';');

        // Détection colonnes optionnelles
        $cols = ['e.id','e.nom','e.type','e.numero_serie','e.localisation','e.statut','e.qr_code','e.date_attribution'];
        $head = ['ID','Nom','Type','N° Série','Localisation','Statut','QR Code','Date Attribution'];

        foreach ([
            ['etat_reparation',   'État Réparation'],
            ['categorie',         'Catégorie'],
            ['marque',            'Marque'],
            ['modele',            'Modèle'],
            ['criticite',         'Criticité'],
            ['date_achat',        'Date Achat'],
            ['garantie_fin',      'Fin Garantie'],
            ['commentaire_technique','Commentaire Technique'],
        ] as [$col,$label]) {
            try { $pdo->query("SELECT $col FROM equipements LIMIT 0"); $cols[]='e.'.$col; $head[]=$label; }
            catch (PDOException $e) {}
        }

        $head[] = 'Utilisateur (Prénom Nom)';
        $head[] = 'Service';

        fputcsv($out, $head, ';');

        $sql = "SELECT ".implode(',',$cols).", u.prenom AS u_prenom, u.nom AS u_nom, u.service AS u_service
                FROM equipements e LEFT JOIN utilisateurs u ON u.id=e.utilisateur_id
                ORDER BY e.nom ASC";
        try {
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $line = [];
                foreach ($cols as $c) {
                    $key = strpos($c,'.') !== false ? explode('.',$c)[1] : $c;
                    $line[] = $r[$key] ?? '';
                }
                $line[] = trim(($r['u_prenom']??'').' '.($r['u_nom']??''));
                $line[] = $r['u_service'] ?? '';
                fputcsv($out, $line, ';');
            }
        } catch (PDOException $e) { fputcsv($out, ['ERREUR: '.$e->getMessage()], ';'); }

        if ($type === 'all') { fputcsv($out, [], ';'); }
    }

    /* ─── EXPORT MOVEMENTS ─── */
    if ($type === 'movements' || $type === 'all') {
        if ($type === 'all') fputcsv($out, ['=== MOUVEMENTS ==='], ';');

        fputcsv($out, ['ID','Équipement','QR Code','Action','Date Action'], ';');
        try {
            $rows = $pdo->query(
                "SELECT m.id, e.nom, e.qr_code, m.action, m.date_action
                 FROM movements m JOIN equipements e ON m.equipement_id=e.id
                 ORDER BY m.date_action DESC"
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                fputcsv($out, [$r['id'],$r['nom'],$r['qr_code'],$r['action'],$r['date_action']], ';');
            }
        } catch (PDOException $e) { fputcsv($out, ['ERREUR: '.$e->getMessage()], ';'); }

        if ($type === 'all') { fputcsv($out, [], ';'); }
    }

    /* ─── EXPORT LOGS ÉQUIPEMENTS ─── */
    if ($type === 'logs' || $type === 'all') {
        if ($type === 'all') fputcsv($out, ['=== LOGS ÉQUIPEMENTS ==='], ';');

        try {
            $pdo->query("SELECT 1 FROM equipement_logs LIMIT 0");
            fputcsv($out, ['ID','Équipement','QR Code','Type Événement','Titre','Détails','Auteur','Date'], ';');
            $rows = $pdo->query(
                "SELECT l.id, e.nom, e.qr_code, l.type_event, l.titre, l.details, l.auteur, l.created_at
                 FROM equipement_logs l JOIN equipements e ON l.equipement_id=e.id
                 ORDER BY l.created_at DESC"
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                fputcsv($out, [$r['id'],$r['nom'],$r['qr_code'],$r['type_event'],$r['titre'],$r['details']??'',$r['auteur']??'',$r['created_at']], ';');
            }
        } catch (PDOException $e) {
            fputcsv($out, ['Table equipement_logs non disponible (SQL non exécuté)'], ';');
        }

        if ($type === 'all') { fputcsv($out, [], ';'); }
    }

    /* ─── EXPORT RÉPARATIONS ─── */
    if ($type === 'reparations' || $type === 'all') {
        if ($type === 'all') fputcsv($out, ['=== RÉPARATIONS ==='], ';');

        try {
            $pdo->query("SELECT 1 FROM reparations LIMIT 0");
            $repCols = ['r.id','r.statut','r.panne_declaree','r.diagnostic','r.action_prevue'];
            $repHead = ['ID','Statut','Panne Déclarée','Diagnostic','Action Prévue'];
            foreach ([
                ['priorite','Priorité'],['pieces_a_changer','Pièces à changer'],
                ['pieces_changees','Pièces changées'],['technicien','Technicien'],
                ['date_ouverture','Date Ouverture'],['date_mise_a_jour','Dernière MAJ'],
                ['date_cloture','Date Clôture'],['cout_reparation','Coût (€)'],
                ['fournisseur_piece','Fournisseur'],['numero_commande_piece','N° Commande'],
                ['immobilisation_debut','Immob. Début'],['immobilisation_fin','Immob. Fin'],
                ['numero_ticket','N° Ticket'],
            ] as [$col,$label]) {
                try { $pdo->query("SELECT $col FROM reparations LIMIT 0"); $repCols[]='r.'.$col; $repHead[]=$label; }
                catch (PDOException $e) {}
            }
            $repHead[] = 'Équipement'; $repHead[] = 'Site'; $repHead[] = 'QR Code';
            fputcsv($out, $repHead, ';');
            $sql = "SELECT ".implode(',',$repCols).", e.nom AS e_nom, e.localisation AS e_loc, e.qr_code AS e_qr
                    FROM reparations r JOIN equipements e ON e.id=r.equipement_id
                    ORDER BY r.date_ouverture DESC";
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $line = [];
                foreach ($repCols as $c) {
                    $key = strpos($c,'.') !== false ? explode('.',$c)[1] : $c;
                    $line[] = $r[$key] ?? '';
                }
                $line[] = $r['e_nom']; $line[] = $r['e_loc']; $line[] = $r['e_qr'];
                fputcsv($out, $line, ';');
            }
        } catch (PDOException $e) {
            fputcsv($out, ['Table reparations non disponible (SQL non exécuté)'], ';');
        }
    }

    fclose($out);
    exit;
}

/* ══════════════════════════════════════════
   PAGE D'INTERFACE si pas de ?type=
══════════════════════════════════════════ */

/* Comptage pour les badges */
$counts = [];
$queries = [
    'equipements' => "SELECT COUNT(*) FROM equipements",
    'movements'   => "SELECT COUNT(*) FROM movements",
    'logs'        => "SELECT COUNT(*) FROM equipement_logs",
    'reparations' => "SELECT COUNT(*) FROM reparations",
];
foreach ($queries as $k => $sql) {
    try { $counts[$k] = $pdo->query($sql)->fetchColumn(); }
    catch (PDOException $e) { $counts[$k] = '—'; }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Export — Sodiaal Traçabilité</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--bg1:#070b18;--bg2:#111827;--card:rgba(15,23,42,.72);--line:rgba(255,255,255,.10);--txt:#e5e7eb;--muted:#94a3b8}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{min-height:100vh;color:var(--txt);font-family:system-ui,-apple-system,sans-serif;
  background:radial-gradient(circle at 10% 10%,rgba(59,130,246,.28),transparent 24%),
             radial-gradient(circle at 88% 18%,rgba(168,85,247,.22),transparent 24%),
             linear-gradient(180deg,var(--bg1),var(--bg2))}
.glass{background:var(--card);backdrop-filter:blur(18px);border:1px solid var(--line);box-shadow:0 24px 60px rgba(0,0,0,.35);border-radius:28px}
.muted{color:var(--muted)}
.title{letter-spacing:-.04em;font-weight:900}
.chip{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.08);color:var(--txt)}
.btn-back{border-radius:14px;padding:10px 18px;font-weight:700;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.06);color:#e5e7eb;text-decoration:none;display:inline-flex;align-items:center;gap:8px;transition:background .15s}
.btn-back:hover{background:rgba(255,255,255,.12);color:#fff}

/* Carte export */
.export-card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:22px;padding:24px;transition:border-color .2s,background .2s;height:100%}
.export-card:hover{border-color:rgba(99,102,241,.4);background:rgba(99,102,241,.06)}
.export-icon{width:52px;height:52px;border-radius:16px;display:grid;place-items:center;font-size:1.5rem;margin-bottom:14px}
.export-title{font-weight:800;font-size:1.05rem;margin-bottom:4px}
.export-desc{font-size:.82rem;color:var(--muted);line-height:1.5;margin-bottom:12px}
.export-count{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:999px;font-size:.78rem;font-weight:700}
.btn-dl{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:14px;font-weight:800;font-size:.88rem;border:none;cursor:pointer;text-decoration:none;transition:filter .15s}
.btn-dl:hover{filter:brightness(1.15)}

/* Carte "Tout exporter" */
.export-all-card{background:linear-gradient(135deg,rgba(37,99,235,.22),rgba(124,58,237,.18));border:1px solid rgba(99,102,241,.28);border-radius:22px;padding:28px}

/* Table aperçu */
.preview-table thead th{background:rgba(255,255,255,.07)!important;color:var(--muted);border-color:rgba(255,255,255,.07);font-size:.75rem;text-transform:uppercase;letter-spacing:.06em}
.preview-table tbody td{background:rgba(255,255,255,.02)!important;color:var(--txt);border-color:rgba(255,255,255,.05);font-size:.82rem}
.preview-table tbody tr:hover td{background:rgba(255,255,255,.05)!important}
.table-wrap{overflow:hidden;border-radius:16px}
.section-title{font-weight:900;letter-spacing:-.02em;margin-bottom:16px}
</style>
</head>
<body>
<div class="container py-4 py-lg-5" style="max-width:1100px">

  <!-- HEADER -->
  <div class="glass p-4 mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
      <div>
        <div class="chip rounded-pill px-3 py-2 mb-2 d-inline-flex gap-2"><i class="bi bi-download"></i>Module Export</div>
        <h1 class="title fs-3 mb-1">Exports CSV — Traçabilité Sodiaal</h1>
        <p class="muted mb-0" style="font-size:.9rem">Télécharge les données en CSV (compatible Excel / LibreOffice). Séparateur : <code style="color:#67e8f9">;</code> — Encodage : UTF-8 BOM.</p>
      </div>
      <a href="index.php" class="btn-back"><i class="bi bi-arrow-left"></i>Retour tableau de bord</a>
    </div>
  </div>

  <!-- CARTES EXPORT INDIVIDUELS -->
  <div class="row g-4 mb-4">

    <!-- Équipements -->
    <div class="col-md-6 col-xl-3">
      <div class="export-card">
        <div class="export-icon" style="background:rgba(59,130,246,.18);color:#60a5fa"><i class="bi bi-laptop"></i></div>
        <div class="export-title">Équipements</div>
        <div class="export-desc">Tous les champs : nom, type, N° série, site, statut, état réparation, marque, modèle, dates, utilisateur attribué.</div>
        <div class="mb-3">
          <span class="export-count" style="background:rgba(59,130,246,.15);color:#60a5fa">
            <i class="bi bi-table"></i><?= hv((string)$counts['equipements']) ?> enregistrements
          </span>
        </div>
        <a href="export.php?type=equipements" class="btn-dl" style="background:rgba(59,130,246,.25);color:#93c5fd;border:1px solid rgba(59,130,246,.3)">
          <i class="bi bi-file-earmark-spreadsheet"></i>Télécharger
        </a>
      </div>
    </div>

    <!-- Mouvements -->
    <div class="col-md-6 col-xl-3">
      <div class="export-card">
        <div class="export-icon" style="background:rgba(245,158,11,.18);color:#fbbf24"><i class="bi bi-arrow-left-right"></i></div>
        <div class="export-title">Mouvements</div>
        <div class="export-desc">Historique complet des sorties et retours d'équipements (table <code>movements</code>).</div>
        <div class="mb-3">
          <span class="export-count" style="background:rgba(245,158,11,.15);color:#fbbf24">
            <i class="bi bi-table"></i><?= hv((string)$counts['movements']) ?> enregistrements
          </span>
        </div>
        <a href="export.php?type=movements" class="btn-dl" style="background:rgba(245,158,11,.2);color:#fde68a;border:1px solid rgba(245,158,11,.3)">
          <i class="bi bi-file-earmark-spreadsheet"></i>Télécharger
        </a>
      </div>
    </div>

    <!-- Logs équipements -->
    <div class="col-md-6 col-xl-3">
      <div class="export-card">
        <div class="export-icon" style="background:rgba(99,102,241,.18);color:#a5b4fc"><i class="bi bi-clock-history"></i></div>
        <div class="export-title">Logs Équipements</div>
        <div class="export-desc">Journal détaillé de tous les événements : scans, prêts, retours, réparations, modifications (table <code>equipement_logs</code>).</div>
        <div class="mb-3">
          <span class="export-count" style="background:rgba(99,102,241,.15);color:#a5b4fc">
            <i class="bi bi-table"></i><?= hv((string)$counts['logs']) ?> enregistrements
          </span>
        </div>
        <a href="export.php?type=logs" class="btn-dl" style="background:rgba(99,102,241,.2);color:#c7d2fe;border:1px solid rgba(99,102,241,.3)">
          <i class="bi bi-file-earmark-spreadsheet"></i>Télécharger
        </a>
      </div>
    </div>

    <!-- Réparations -->
    <div class="col-md-6 col-xl-3">
      <div class="export-card">
        <div class="export-icon" style="background:rgba(239,68,68,.18);color:#f87171"><i class="bi bi-tools"></i></div>
        <div class="export-title">Réparations</div>
        <div class="export-desc">Toutes les réparations : panne, diagnostic, pièces, technicien, coûts, dates, numéro de ticket (table <code>reparations</code>).</div>
        <div class="mb-3">
          <span class="export-count" style="background:rgba(239,68,68,.15);color:#f87171">
            <i class="bi bi-table"></i><?= hv((string)$counts['reparations']) ?> enregistrements
          </span>
        </div>
        <a href="export.php?type=reparations" class="btn-dl" style="background:rgba(239,68,68,.2);color:#fca5a5;border:1px solid rgba(239,68,68,.3)">
          <i class="bi bi-file-earmark-spreadsheet"></i>Télécharger
        </a>
      </div>
    </div>
  </div>

  <!-- TOUT EXPORTER EN UN FICHIER -->
  <div class="export-all-card mb-4">
    <div class="row align-items-center g-3">
      <div class="col-lg-8">
        <div style="font-size:1.15rem;font-weight:900;margin-bottom:6px"><i class="bi bi-archive me-2" style="color:#a5b4fc"></i>Tout exporter en un seul fichier</div>
        <p class="muted mb-0" style="font-size:.88rem">
          Un seul fichier CSV contenant les 4 tableaux à la suite : Équipements + Mouvements + Logs + Réparations. Idéal pour une archive complète ou un audit.
        </p>
      </div>
      <div class="col-lg-4 text-lg-end">
        <a href="export.php?type=all" class="btn-dl" style="background:linear-gradient(135deg,#2563eb,#7c3aed);color:#fff;font-size:1rem;padding:14px 28px">
          <i class="bi bi-download"></i>Export complet (.csv)
        </a>
      </div>
    </div>
  </div>

  <!-- APERÇU DERNIERS MOUVEMENTS -->
  <div class="glass p-4 mb-4">
    <div class="section-title"><i class="bi bi-eye me-2"></i>Aperçu — 10 derniers mouvements</div>
    <div class="table-responsive table-wrap">
      <table class="table table-hover align-middle mb-0 preview-table">
        <thead>
          <tr><th>#</th><th>Équipement</th><th>QR Code</th><th>Action</th><th>Date</th></tr>
        </thead>
        <tbody>
        <?php
        try {
            $rows=$pdo->query("SELECT m.id,e.nom,e.qr_code,m.action,m.date_action FROM movements m JOIN equipements e ON m.equipement_id=e.id ORDER BY m.date_action DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r): ?>
            <tr>
              <td class="muted"><?= hv((string)$r['id']) ?></td>
              <td style="font-weight:700"><?= hv($r['nom']) ?></td>
              <td><code style="color:#67e8f9;font-size:.78rem"><?= hv($r['qr_code']) ?></code></td>
              <td><?= hv($r['action']) ?></td>
              <td class="muted"><?= hv($r['date_action']) ?></td>
            </tr>
            <?php endforeach;
        } catch (PDOException $e) { echo '<tr><td colspan="5" class="text-center muted py-3">Aucune donnée disponible</td></tr>'; }
        ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- APERÇU DERNIERS LOGS -->
  <?php
  try {
      $pdo->query("SELECT 1 FROM equipement_logs LIMIT 0");
      $hasLogs = true;
  } catch (PDOException $e) { $hasLogs = false; }
  if ($hasLogs): ?>
  <div class="glass p-4 mb-4">
    <div class="section-title"><i class="bi bi-clock-history me-2"></i>Aperçu — 10 derniers logs équipements</div>
    <div class="table-responsive table-wrap">
      <table class="table table-hover align-middle mb-0 preview-table">
        <thead>
          <tr><th>#</th><th>Équipement</th><th>Type</th><th>Titre</th><th>Auteur</th><th>Date</th></tr>
        </thead>
        <tbody>
        <?php
        try {
            $rows=$pdo->query("SELECT l.id,e.nom,l.type_event,l.titre,l.auteur,l.created_at FROM equipement_logs l JOIN equipements e ON l.equipement_id=e.id ORDER BY l.created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r): ?>
            <tr>
              <td class="muted"><?= hv((string)$r['id']) ?></td>
              <td style="font-weight:700"><?= hv($r['nom']) ?></td>
              <td><code style="color:#a5b4fc;font-size:.78rem"><?= hv($r['type_event']) ?></code></td>
              <td><?= hv($r['titre']) ?></td>
              <td class="muted"><?= hv($r['auteur']??'—') ?></td>
              <td class="muted"><?= hv($r['created_at']) ?></td>
            </tr>
            <?php endforeach;
        } catch (PDOException $e) { echo '<tr><td colspan="6" class="text-center muted py-3">Erreur requête</td></tr>'; }
        ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <div class="text-center muted pb-2" style="font-size:.82rem">
    <i class="bi bi-info-circle me-1"></i>
    Les fichiers CSV utilisent le séparateur <code>;</code> et l'encodage UTF-8 BOM pour une compatibilité maximale avec Excel et LibreOffice Calc.
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
