<?php
ob_start();
session_start();
require_once 'db.php';
if (empty($_SESSION['authok'])) { header('Location: index.php'); exit; }

function hv(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

/* ══════════════════════════════════════════
   DONNÉES LIVE POUR LES GRAPHIQUES
══════════════════════════════════════════ */

/* KPIs */
$kpi = ['total'=>0,'dispo'=>0,'pret'=>0,'maintenance'=>0,'hs'=>0,'repairs'=>0,'scans_week'=>0,'mvt_month'=>0];
try {
    $rows = $pdo->query("SELECT statut, COUNT(*) AS n FROM equipements GROUP BY statut")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $kpi['total'] += $r['n'];
        if ($r['statut']==='Disponible')    $kpi['dispo']       = $r['n'];
        if ($r['statut']==='En prêt')       $kpi['pret']        = $r['n'];
        if ($r['statut']==='En maintenance')$kpi['maintenance'] = $r['n'];
        if ($r['statut']==='Hors service')  $kpi['hs']          = $r['n'];
    }
} catch (PDOException $e) {}
try { $kpi['repairs'] = $pdo->query("SELECT COUNT(*) FROM reparations WHERE statut NOT IN ('Réparé','Restitué','Clôturé')")->fetchColumn(); } catch (PDOException $e) {}
try { $kpi['scans_week'] = $pdo->query("SELECT COUNT(*) FROM movements WHERE date_action >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(); } catch (PDOException $e) {}
try { $kpi['mvt_month'] = $pdo->query("SELECT COUNT(*) FROM movements WHERE date_action >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn(); } catch (PDOException $e) {}

/* Mouvements par jour — 30 derniers jours */
$mvtDays = []; $mvtCounts = [];
try {
    $rows = $pdo->query("SELECT DATE(date_action) AS d, COUNT(*) AS n FROM movements WHERE date_action >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(date_action) ORDER BY d ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) { $mvtDays[] = $r['d']; $mvtCounts[] = (int)$r['n']; }
} catch (PDOException $e) {}

/* Statut équipements */
$statLabels = []; $statData = []; $statColors = [];
$statColorMap = ['Disponible'=>'#22c55e','En prêt'=>'#f59e0b','En maintenance'=>'#3b82f6','Hors service'=>'#ef4444'];
try {
    $rows = $pdo->query("SELECT statut, COUNT(*) AS n FROM equipements GROUP BY statut ORDER BY n DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $statLabels[] = $r['statut']?:'Inconnu';
        $statData[]   = (int)$r['n'];
        $statColors[] = $statColorMap[$r['statut']]??'#6366f1';
    }
} catch (PDOException $e) {}

/* Par site */
$siteLabels = []; $siteData = []; $siteColors2 = [];
$siteMap = ['Paris'=>'#3b82f6','Boulogne'=>'#8b5cf6','Lyon'=>'#f59e0b','Uxbridge'=>'#10b981','Rennes'=>'#ef4444'];
try {
    $rows = $pdo->query("SELECT localisation, COUNT(*) AS n FROM equipements WHERE localisation!='' GROUP BY localisation ORDER BY n DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $siteLabels[] = $r['localisation'];
        $siteData[]   = (int)$r['n'];
        $siteColors2[]= $siteMap[$r['localisation']]??'#94a3b8';
    }
} catch (PDOException $e) {}

/* Par type */
$typeLabels = []; $typeData = [];
try {
    $rows = $pdo->query("SELECT type, COUNT(*) AS n FROM equipements WHERE type!='' GROUP BY type ORDER BY n DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) { $typeLabels[]=$r['type']; $typeData[]=(int)$r['n']; }
} catch (PDOException $e) {}

/* Events par type (equipement_logs) */
$evtLabels=[]; $evtData=[]; $evtColors=[];
$evtColorMap=['scan'=>'#6366f1','mouvement'=>'#f59e0b','pret'=>'#22c55e','retour'=>'#3b82f6','reparation'=>'#ef4444','creation'=>'#10b981','modification'=>'#a78bfa','piece'=>'#f97316'];
try {
    $rows = $pdo->query("SELECT type_event, COUNT(*) AS n FROM equipement_logs GROUP BY type_event ORDER BY n DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $evtLabels[]=$r['type_event']; $evtData[]=(int)$r['n'];
        $evtColors[]=$evtColorMap[$r['type_event']]??'#94a3b8';
    }
} catch (PDOException $e) {}
$hasLogs = !empty($evtLabels);

/* Réparations par statut */
$repLabels=[]; $repData=[]; $repColors=[];
$repColorMap=['A diagnostiquer'=>'#f59e0b','En réparation'=>'#3b82f6','En attente de pièces'=>'#f97316','Test après réparation'=>'#a78bfa','Réparé'=>'#22c55e','Restitué'=>'#10b981','Hors service'=>'#ef4444'];
try {
    $rows = $pdo->query("SELECT statut, COUNT(*) AS n FROM reparations GROUP BY statut ORDER BY n DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $repLabels[]=$r['statut']; $repData[]=(int)$r['n'];
        $repColors[]=$repColorMap[$r['statut']]??'#94a3b8';
    }
} catch (PDOException $e) {}
$hasRep = !empty($repLabels);

/* Top 8 équipements les plus actifs (movements) */
$topEqLabels=[]; $topEqData=[];
try {
    $rows=$pdo->query("SELECT e.nom, COUNT(m.id) AS n FROM movements m JOIN equipements e ON m.equipement_id=e.id GROUP BY e.id,e.nom ORDER BY n DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) { $topEqLabels[]=$r['nom']; $topEqData[]=(int)$r['n']; }
} catch (PDOException $e) {}

/* Logs des 7 derniers jours par jour (equipement_logs) */
$logDays=[]; $logDayCounts=[];
try {
    $rows=$pdo->query("SELECT DATE(created_at) AS d, COUNT(*) AS n FROM equipement_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY d ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) { $logDays[]=$r['d']; $logDayCounts[]=(int)$r['n']; }
} catch (PDOException $e) {}

/* ══════════════════════════════════════════
   IMPORT CSV
══════════════════════════════════════════ */
$csvData = []; $csvHeaders = []; $csvError = ''; $csvName = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_FILES['csvfile']) && $_FILES['csvfile']['error']===0) {
    $csvName = hv($_FILES['csvfile']['name']);
    $tmp = $_FILES['csvfile']['tmp_name'];
    $content = file_get_contents($tmp);
    // Supprime BOM UTF-8
    $content = ltrim($content, "\xEF\xBB\xBF");
    $lines = preg_split('/\r\n|\r|\n/', trim($content));
    if (count($lines) < 2) {
        $csvError = 'Fichier vide ou non reconnu.';
    } else {
        // Détection séparateur (;  ou ,)
        $firstLine = $lines[0];
        $sep = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
        foreach ($lines as $i => $line) {
            if (trim($line)==='') continue;
            $row = str_getcsv($line, $sep);
            if ($i===0) { $csvHeaders = $row; }
            else        { $csvData[]  = $row; }
            if (count($csvData) >= 500) { $csvError = 'Affichage limité aux 500 premières lignes.'; break; }
        }
    }
}

/* JSON pour Chart.js */
$j = fn($v) => json_encode($v, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Analytics & Logs — Sodiaal</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<style>
:root{--bg1:#070b18;--bg2:#111827;--card:rgba(15,23,42,.72);--line:rgba(255,255,255,.10);--txt:#e5e7eb;--muted:#94a3b8}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{min-height:100vh;color:var(--txt);font-family:system-ui,-apple-system,sans-serif;
  background:radial-gradient(circle at 10% 10%,rgba(59,130,246,.28),transparent 24%),
             radial-gradient(circle at 88% 18%,rgba(168,85,247,.22),transparent 24%),
             radial-gradient(circle at 50% 100%,rgba(6,182,212,.18),transparent 24%),
             linear-gradient(180deg,var(--bg1),var(--bg2))}
.glass{background:var(--card);backdrop-filter:blur(18px);border:1px solid var(--line);box-shadow:0 24px 60px rgba(0,0,0,.35);border-radius:28px}
.muted{color:var(--muted)}
.title{letter-spacing:-.04em;font-weight:900}
.chip{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.08);color:var(--txt)}
.section-title{font-weight:900;letter-spacing:-.02em;margin-bottom:16px}
/* Nav */
.btn-back{border-radius:14px;padding:10px 18px;font-weight:700;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.06);color:#e5e7eb;text-decoration:none;display:inline-flex;align-items:center;gap:8px;transition:background .15s}
.btn-back:hover{background:rgba(255,255,255,.12);color:#fff}
.btn-export-link{border-radius:14px;padding:10px 18px;font-weight:700;border:1px solid rgba(34,197,94,.25);background:rgba(34,197,94,.1);color:#86efac;text-decoration:none;display:inline-flex;align-items:center;gap:8px;transition:background .15s}
.btn-export-link:hover{background:rgba(34,197,94,.2);color:#a7f3d0}
/* KPI cards */
.kpi-card{background:linear-gradient(135deg,rgba(59,130,246,.16),rgba(168,85,247,.12));border:1px solid rgba(255,255,255,.08);border-radius:22px;padding:20px;text-align:center}
.kpi-value{font-size:2rem;font-weight:900;line-height:1}
.kpi-label{font-size:.78rem;text-transform:uppercase;letter-spacing:.12em;color:var(--muted);margin-top:6px}
/* Chart card */
.chart-card{background:rgba(15,23,42,.6);border:1px solid rgba(255,255,255,.08);border-radius:22px;padding:22px;height:100%}
.chart-title{font-weight:800;font-size:.95rem;color:var(--muted);text-transform:uppercase;letter-spacing:.1em;margin-bottom:16px}
/* CSV */
.drop-zone{border:2px dashed rgba(99,102,241,.4);border-radius:20px;padding:36px;text-align:center;cursor:pointer;transition:border-color .2s,background .2s}
.drop-zone:hover,.drop-zone.drag-over{border-color:rgba(99,102,241,.8);background:rgba(99,102,241,.08)}
.drop-zone input[type=file]{display:none}
.csv-table thead th{background:rgba(255,255,255,.07)!important;color:var(--muted);border-color:rgba(255,255,255,.07);font-size:.75rem;text-transform:uppercase;letter-spacing:.06em;white-space:nowrap}
.csv-table tbody td{background:rgba(255,255,255,.02)!important;color:var(--txt);border-color:rgba(255,255,255,.05);font-size:.8rem;white-space:nowrap;max-width:200px;overflow:hidden;text-overflow:ellipsis}
.csv-table tbody tr:hover td{background:rgba(255,255,255,.05)!important}
.table-scroll{overflow-x:auto;overflow-y:auto;max-height:420px;border-radius:14px}
/* Stat pill */
.stat-pill{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:999px;font-size:.75rem;font-weight:700}
</style>
</head>
<body>
<div class="container py-4 py-lg-5" style="max-width:1280px">

  <!-- HEADER -->
  <div class="glass p-4 mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
      <div>
        <div class="chip rounded-pill px-3 py-2 mb-2 d-inline-flex gap-2"><i class="bi bi-bar-chart-line"></i>Analytics & Logs</div>
        <h1 class="title fs-3 mb-1">Tableau de bord analytique</h1>
        <p class="muted mb-0" style="font-size:.88rem">Graphiques temps réel depuis la base de données · Import &amp; analyse de fichiers CSV</p>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <a href="export.php" class="btn-export-link"><i class="bi bi-download"></i>Exporter</a>
        <a href="index.php" class="btn-back"><i class="bi bi-arrow-left"></i>Tableau de bord</a>
      </div>
    </div>
  </div>

  <!-- KPIs -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg-2">
      <div class="kpi-card"><div class="kpi-value"><?= (int)$kpi['total'] ?></div><div class="kpi-label">Équipements</div></div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="kpi-card"><div class="kpi-value" style="color:#86efac"><?= (int)$kpi['dispo'] ?></div><div class="kpi-label">Disponibles</div></div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="kpi-card"><div class="kpi-value" style="color:#fdba74"><?= (int)$kpi['pret'] ?></div><div class="kpi-label">En prêt</div></div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="kpi-card"><div class="kpi-value" style="color:#f87171"><?= (int)$kpi['repairs'] ?></div><div class="kpi-label">En réparation</div></div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="kpi-card"><div class="kpi-value" style="color:#67e8f9"><?= (int)$kpi['scans_week'] ?></div><div class="kpi-label">Scans 7 j</div></div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="kpi-card"><div class="kpi-value" style="color:#a5b4fc"><?= (int)$kpi['mvt_month'] ?></div><div class="kpi-label">Mvt 30 j</div></div>
    </div>
  </div>

  <!-- GRAPHIQUES LIGNE 1 -->
  <div class="row g-4 mb-4">
    <!-- Mouvements 30 jours -->
    <div class="col-lg-8">
      <div class="chart-card">
        <div class="chart-title"><i class="bi bi-graph-up me-2"></i>Mouvements par jour — 30 derniers jours</div>
        <?php if (empty($mvtDays)): ?>
        <div class="text-center muted py-4"><i class="bi bi-inbox me-2"></i>Aucun mouvement enregistré</div>
        <?php else: ?>
        <canvas id="chartMvt" height="80"></canvas>
        <?php endif; ?>
      </div>
    </div>
    <!-- Statut équipements -->
    <div class="col-lg-4">
      <div class="chart-card">
        <div class="chart-title"><i class="bi bi-pie-chart me-2"></i>Statut des équipements</div>
        <?php if (empty($statData)): ?>
        <div class="text-center muted py-4"><i class="bi bi-inbox me-2"></i>Aucune donnée</div>
        <?php else: ?>
        <canvas id="chartStat" height="160"></canvas>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- GRAPHIQUES LIGNE 2 -->
  <div class="row g-4 mb-4">
    <!-- Par site -->
    <div class="col-md-6 col-lg-4">
      <div class="chart-card">
        <div class="chart-title"><i class="bi bi-geo-alt me-2"></i>Équipements par site</div>
        <?php if (empty($siteData)): ?>
        <div class="text-center muted py-4"><i class="bi bi-inbox me-2"></i>Aucune donnée</div>
        <?php else: ?>
        <canvas id="chartSite" height="180"></canvas>
        <?php endif; ?>
      </div>
    </div>
    <!-- Par type -->
    <div class="col-md-6 col-lg-4">
      <div class="chart-card">
        <div class="chart-title"><i class="bi bi-laptop me-2"></i>Équipements par type</div>
        <?php if (empty($typeData)): ?>
        <div class="text-center muted py-4"><i class="bi bi-inbox me-2"></i>Aucune donnée</div>
        <?php else: ?>
        <canvas id="chartType" height="180"></canvas>
        <?php endif; ?>
      </div>
    </div>
    <!-- Top équipements actifs -->
    <div class="col-md-12 col-lg-4">
      <div class="chart-card">
        <div class="chart-title"><i class="bi bi-trophy me-2"></i>Top équipements — mouvements</div>
        <?php if (empty($topEqData)): ?>
        <div class="text-center muted py-4"><i class="bi bi-inbox me-2"></i>Aucune donnée</div>
        <?php else: ?>
        <canvas id="chartTopEq" height="180"></canvas>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- GRAPHIQUES LIGNE 3 : LOGS + RÉPARATIONS -->
  <?php if ($hasLogs || $hasRep): ?>
  <div class="row g-4 mb-4">
    <?php if ($hasLogs): ?>
    <!-- Events par type -->
    <div class="col-md-6 <?= $hasRep?'col-lg-4':'col-lg-6' ?>">
      <div class="chart-card">
        <div class="chart-title"><i class="bi bi-clock-history me-2"></i>Événements par type (logs)</div>
        <canvas id="chartEvt" height="200"></canvas>
      </div>
    </div>
    <!-- Logs 7 derniers jours -->
    <?php if (!empty($logDays)): ?>
    <div class="col-md-6 <?= $hasRep?'col-lg-4':'col-lg-6' ?>">
      <div class="chart-card">
        <div class="chart-title"><i class="bi bi-activity me-2"></i>Activité logs — 7 derniers jours</div>
        <canvas id="chartLogDays" height="200"></canvas>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
    <?php if ($hasRep): ?>
    <!-- Réparations par statut -->
    <div class="col-md-12 col-lg-4">
      <div class="chart-card">
        <div class="chart-title"><i class="bi bi-tools me-2"></i>Réparations par statut</div>
        <canvas id="chartRep" height="200"></canvas>
      </div>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- IMPORT CSV -->
  <div class="glass p-4 mb-4">
    <div class="section-title"><i class="bi bi-file-earmark-arrow-up me-2"></i>Importer &amp; analyser un fichier CSV</div>
    <p class="muted mb-4" style="font-size:.88rem">Glisse-dépose ou sélectionne un fichier CSV exporté depuis ce système ou tout autre outil. Séparateur <code>;</code> ou <code>,</code> détecté automatiquement. Max 500 lignes affichées.</p>

    <form method="post" enctype="multipart/form-data" id="csvForm">
      <div class="drop-zone" id="dropZone" onclick="document.getElementById('csvInput').click()">
        <input type="file" name="csvfile" id="csvInput" accept=".csv,.txt" onchange="document.getElementById('csvForm').submit()">
        <i class="bi bi-cloud-upload" style="font-size:2.5rem;color:#6366f1;margin-bottom:10px;display:block"></i>
        <div style="font-weight:800;font-size:1.05rem;margin-bottom:4px">Cliquer pour sélectionner un CSV</div>
        <div class="muted" style="font-size:.85rem">ou glisser-déposer ici</div>
      </div>
    </form>

    <?php if ($csvError): ?>
    <div class="alert alert-warning mt-3 border-0 rounded-4"><i class="bi bi-exclamation-triangle me-2"></i><?= hv($csvError) ?></div>
    <?php endif; ?>

    <?php if (!empty($csvHeaders)): ?>
    <div class="mt-4">
      <!-- Infos fichier -->
      <div class="d-flex flex-wrap gap-3 align-items-center mb-3">
        <div style="font-weight:800;font-size:1rem"><i class="bi bi-file-earmark-spreadsheet me-2" style="color:#86efac"></i><?= $csvName ?></div>
        <span class="stat-pill" style="background:rgba(34,197,94,.15);color:#86efac"><i class="bi bi-table"></i><?= count($csvData) ?> lignes</span>
        <span class="stat-pill" style="background:rgba(99,102,241,.15);color:#a5b4fc"><i class="bi bi-columns"></i><?= count($csvHeaders) ?> colonnes</span>
      </div>

      <!-- Graphique automatique si colonne statut ou action détectée -->
      <?php
      $csvStatCol   = null; $csvActionCol = null; $csvDateCol = null; $csvSiteCol = null;
      foreach ($csvHeaders as $ci => $ch) {
          $chl = strtolower(trim($ch));
          if (in_array($chl,['statut','status']))              $csvStatCol   = $ci;
          if (in_array($chl,['action','mouvement']))           $csvActionCol = $ci;
          if (in_array($chl,['date_action','date','created_at','date_attribution'])) $csvDateCol = $ci;
          if (in_array($chl,['localisation','site']))          $csvSiteCol   = $ci;
      }

      /* Comptage sur la colonne statut ou action */
      $autoChartLabels=[]; $autoChartData=[]; $autoChartTitle='';
      $targetCol = $csvStatCol ?? $csvActionCol;
      if ($targetCol !== null) {
          $autoChartTitle = trim($csvHeaders[$targetCol]);
          $counts2 = [];
          foreach ($csvData as $row) { $v=trim($row[$targetCol]??''); if ($v!=='') $counts2[$v] = ($counts2[$v]??0)+1; }
          arsort($counts2);
          foreach ($counts2 as $k=>$v) { $autoChartLabels[]=$k; $autoChartData[]=$v; }
      }

      /* Comptage sur site */
      $siteChartLabels=[]; $siteChartData=[];
      if ($csvSiteCol !== null) {
          $sc=[];
          foreach ($csvData as $row) { $v=trim($row[$csvSiteCol]??''); if ($v!=='') $sc[$v]=($sc[$v]??0)+1; }
          arsort($sc);
          foreach ($sc as $k=>$v) { $siteChartLabels[]=$k; $siteChartData[]=$v; }
      }
      ?>

      <?php if (!empty($autoChartLabels) || !empty($siteChartLabels)): ?>
      <div class="row g-4 mb-4">
        <?php if (!empty($autoChartLabels)): ?>
        <div class="col-md-6">
          <div class="chart-card">
            <div class="chart-title"><i class="bi bi-bar-chart me-2"></i>Répartition — <?= hv($autoChartTitle) ?></div>
            <canvas id="chartCsvAuto" height="200"></canvas>
          </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($siteChartLabels)): ?>
        <div class="col-md-6">
          <div class="chart-card">
            <div class="chart-title"><i class="bi bi-geo-alt me-2"></i>Répartition — Site / Localisation</div>
            <canvas id="chartCsvSite" height="200"></canvas>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Tableau CSV -->
      <div class="table-scroll">
        <table class="table table-hover align-middle mb-0 csv-table">
          <thead>
            <tr>
              <th>#</th>
              <?php foreach ($csvHeaders as $h): ?><th><?= hv($h) ?></th><?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($csvData as $i => $row): ?>
            <tr>
              <td class="muted"><?= $i+1 ?></td>
              <?php foreach ($csvHeaders as $ci => $_): ?>
              <td title="<?= hv($row[$ci]??'') ?>"><?= hv($row[$ci]??'') ?></td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <div class="text-center muted pb-2" style="font-size:.82rem">
    <i class="bi bi-info-circle me-1"></i>Données en temps réel · Actualisez la page pour mettre à jour les graphiques
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ── Configuration Chart.js globale ── */
Chart.defaults.color = '#94a3b8';
Chart.defaults.borderColor = 'rgba(255,255,255,0.07)';
Chart.defaults.font.family = "system-ui, -apple-system, sans-serif";

const gridOpts = { color:'rgba(255,255,255,0.07)', drawBorder:false };
const tickOpts  = { color:'#94a3b8', font:{size:11} };

/* ── Mouvements 30 jours ── */
<?php if (!empty($mvtDays)): ?>
new Chart(document.getElementById('chartMvt'), {
  type:'line',
  data:{ labels:<?= $j($mvtDays) ?>, datasets:[{
    label:'Mouvements', data:<?= $j($mvtCounts) ?>,
    borderColor:'#6366f1', backgroundColor:'rgba(99,102,241,0.12)',
    fill:true, tension:0.4, pointBackgroundColor:'#a5b4fc', pointRadius:4, pointHoverRadius:7
  }]},
  options:{ responsive:true, plugins:{legend:{display:false},tooltip:{callbacks:{title:l=>l[0].label}}},
    scales:{ x:{grid:gridOpts,ticks:tickOpts}, y:{grid:gridOpts,ticks:{...tickOpts,stepSize:1},beginAtZero:true} }}
});
<?php endif; ?>

/* ── Statut équipements (donut) ── */
<?php if (!empty($statData)): ?>
new Chart(document.getElementById('chartStat'), {
  type:'doughnut',
  data:{ labels:<?= $j($statLabels) ?>, datasets:[{data:<?= $j($statData) ?>, backgroundColor:<?= $j($statColors) ?>, borderWidth:2, borderColor:'#111827', hoverOffset:8 }]},
  options:{ responsive:true, cutout:'65%', plugins:{ legend:{ position:'bottom', labels:{color:'#94a3b8',padding:14,font:{size:11}} } } }
});
<?php endif; ?>

/* ── Par site (bar horizontal) ── */
<?php if (!empty($siteData)): ?>
new Chart(document.getElementById('chartSite'), {
  type:'bar',
  data:{ labels:<?= $j($siteLabels) ?>, datasets:[{ label:'Équipements', data:<?= $j($siteData) ?>, backgroundColor:<?= $j($siteColors2) ?>, borderRadius:8, borderSkipped:false }]},
  options:{ indexAxis:'y', responsive:true, plugins:{legend:{display:false}},
    scales:{ x:{grid:gridOpts,ticks:tickOpts,beginAtZero:true}, y:{grid:{display:false},ticks:tickOpts} }}
});
<?php endif; ?>

/* ── Par type (bar) ── */
<?php if (!empty($typeData)): ?>
new Chart(document.getElementById('chartType'), {
  type:'bar',
  data:{ labels:<?= $j($typeLabels) ?>, datasets:[{ label:'Quantité', data:<?= $j($typeData) ?>,
    backgroundColor:['#6366f1','#3b82f6','#22c55e','#f59e0b','#ef4444','#a78bfa','#10b981','#f97316'],
    borderRadius:8, borderSkipped:false }]},
  options:{ responsive:true, plugins:{legend:{display:false}},
    scales:{ x:{grid:{display:false},ticks:tickOpts}, y:{grid:gridOpts,ticks:{...tickOpts,stepSize:1},beginAtZero:true} }}
});
<?php endif; ?>

/* ── Top équipements (bar horizontal) ── */
<?php if (!empty($topEqData)): ?>
new Chart(document.getElementById('chartTopEq'), {
  type:'bar',
  data:{ labels:<?= $j($topEqLabels) ?>, datasets:[{ label:'Mouvements', data:<?= $j($topEqData) ?>,
    backgroundColor:'rgba(99,102,241,0.6)', borderColor:'#6366f1', borderWidth:1, borderRadius:6, borderSkipped:false }]},
  options:{ indexAxis:'y', responsive:true, plugins:{legend:{display:false}},
    scales:{ x:{grid:gridOpts,ticks:tickOpts,beginAtZero:true}, y:{grid:{display:false},ticks:{...tickOpts,font:{size:10}}} }}
});
<?php endif; ?>

/* ── Événements par type ── */
<?php if ($hasLogs && !empty($evtData)): ?>
new Chart(document.getElementById('chartEvt'), {
  type:'pie',
  data:{ labels:<?= $j($evtLabels) ?>, datasets:[{ data:<?= $j($evtData) ?>, backgroundColor:<?= $j($evtColors) ?>, borderWidth:2, borderColor:'#111827', hoverOffset:6 }]},
  options:{ responsive:true, plugins:{ legend:{ position:'bottom', labels:{color:'#94a3b8',padding:10,font:{size:10}} } } }
});
<?php endif; ?>

/* ── Logs 7 jours ── */
<?php if ($hasLogs && !empty($logDays)): ?>
new Chart(document.getElementById('chartLogDays'), {
  type:'bar',
  data:{ labels:<?= $j($logDays) ?>, datasets:[{ label:'Logs', data:<?= $j($logDayCounts) ?>,
    backgroundColor:'rgba(163,122,251,0.5)', borderColor:'#a78bfa', borderWidth:1, borderRadius:6 }]},
  options:{ responsive:true, plugins:{legend:{display:false}},
    scales:{ x:{grid:{display:false},ticks:tickOpts}, y:{grid:gridOpts,ticks:{...tickOpts,stepSize:1},beginAtZero:true} }}
});
<?php endif; ?>

/* ── Réparations par statut ── */
<?php if ($hasRep && !empty($repData)): ?>
new Chart(document.getElementById('chartRep'), {
  type:'bar',
  data:{ labels:<?= $j($repLabels) ?>, datasets:[{ label:'Réparations', data:<?= $j($repData) ?>, backgroundColor:<?= $j($repColors) ?>, borderRadius:8, borderSkipped:false }]},
  options:{ indexAxis:'y', responsive:true, plugins:{legend:{display:false}},
    scales:{ x:{grid:gridOpts,ticks:tickOpts,beginAtZero:true}, y:{grid:{display:false},ticks:{...tickOpts,font:{size:10}}} }}
});
<?php endif; ?>

/* ── CSV auto-charts ── */
<?php if (!empty($autoChartLabels)): ?>
new Chart(document.getElementById('chartCsvAuto'), {
  type:'bar',
  data:{ labels:<?= $j($autoChartLabels) ?>, datasets:[{ label:'Nombre', data:<?= $j($autoChartData) ?>,
    backgroundColor:'rgba(99,102,241,0.55)', borderColor:'#6366f1', borderWidth:1, borderRadius:6 }]},
  options:{ responsive:true, plugins:{legend:{display:false}},
    scales:{ x:{grid:{display:false},ticks:tickOpts}, y:{grid:gridOpts,ticks:{...tickOpts,stepSize:1},beginAtZero:true} }}
});
<?php endif; ?>
<?php if (!empty($siteChartLabels)): ?>
new Chart(document.getElementById('chartCsvSite'), {
  type:'bar',
  data:{ labels:<?= $j($siteChartLabels) ?>, datasets:[{ label:'Nombre', data:<?= $j($siteChartData) ?>,
    backgroundColor:['#3b82f6','#8b5cf6','#f59e0b','#10b981','#ef4444','#94a3b8'],
    borderRadius:6 }]},
  options:{ responsive:true, plugins:{legend:{display:false}},
    scales:{ x:{grid:{display:false},ticks:tickOpts}, y:{grid:gridOpts,ticks:{...tickOpts,stepSize:1},beginAtZero:true} }}
});
<?php endif; ?>

/* ── Drag & drop CSV ── */
const dz = document.getElementById('dropZone');
['dragenter','dragover'].forEach(e=>dz.addEventListener(e,ev=>{ev.preventDefault();dz.classList.add('drag-over');}));
['dragleave','drop'].forEach(e=>dz.addEventListener(e,ev=>{ev.preventDefault();dz.classList.remove('drag-over');}));
dz.addEventListener('drop',ev=>{
  const file = ev.dataTransfer.files[0];
  if (file) {
    const input = document.getElementById('csvInput');
    const dt = new DataTransfer(); dt.items.add(file); input.files = dt.files;
    document.getElementById('csvForm').submit();
  }
});
</script>
</body>
</html>
