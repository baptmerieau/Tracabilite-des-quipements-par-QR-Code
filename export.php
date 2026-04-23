<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['auth_ok']) || !$_SESSION['auth_ok']) {
    header('Location: index.php');
    exit;
}

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// --- Export CSV brut ---
if (isset($_GET['type'])) {
    $type = $_GET['type'];
    $filename = $type . '_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

    if ($type === 'equipements') {
        fputcsv($out, ['ID', 'Nom', 'Type', 'Statut', 'QR Code'], ';');
        $rows = $pdo->query("SELECT id, nom, type, statut, qr_code FROM equipements ORDER BY nom ASC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) fputcsv($out, $r, ';');
    } elseif ($type === 'logs') {
        fputcsv($out, ['ID', 'Equipement', 'QR Code', 'Action', 'Date'], ';');
        $rows = $pdo->query("SELECT m.id, e.nom, e.qr_code, m.action, m.date_action FROM movements m JOIN equipements e ON m.equipement_id = e.id ORDER BY m.date_action DESC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) fputcsv($out, $r, ';');
    } elseif ($type === 'stats') {
        fputcsv($out, ['Equipement', 'QR Code', 'Nb Sorties', 'Nb Retours', 'Total Mouvements'], ';');
        $rows = $pdo->query("
            SELECT e.nom, e.qr_code,
                SUM(CASE WHEN m.action LIKE '%prÃªt%' THEN 1 ELSE 0 END) AS sorties,
                SUM(CASE WHEN m.action LIKE '%Retour%' THEN 1 ELSE 0 END) AS retours,
                COUNT(*) AS total
            FROM movements m JOIN equipements e ON m.equipement_id = e.id
            GROUP BY e.id ORDER BY total DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) fputcsv($out, $r, ';');
    }
    fclose($out);
    exit;
}

// --- DonnÃ©es pour l'analyse ---
// 1. Mouvements par jour (30 derniers jours)
$mouvParJour = $pdo->query("
    SELECT DATE(date_action) as jour, COUNT(*) as nb
    FROM movements
    WHERE date_action >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(date_action) ORDER BY jour ASC
")->fetchAll(PDO::FETCH_ASSOC);

// 2. Top Ã©quipements les plus utilisÃ©s
$topEquip = $pdo->query("
    SELECT e.nom, COUNT(*) as nb
    FROM movements m JOIN equipements e ON m.equipement_id = e.id
    GROUP BY e.id ORDER BY nb DESC LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

// 3. RÃ©partition des actions
$repartition = $pdo->query("
    SELECT action, COUNT(*) as nb FROM movements GROUP BY action ORDER BY nb DESC
")->fetchAll(PDO::FETCH_ASSOC);

// 4. ActivitÃ© par heure (heatmap)
$parHeure = $pdo->query("
    SELECT HOUR(date_action) as heure, COUNT(*) as nb
    FROM movements GROUP BY HOUR(date_action) ORDER BY heure ASC
")->fetchAll(PDO::FETCH_ASSOC);
$heureMap = array_fill(0, 24, 0);
foreach ($parHeure as $r) $heureMap[(int)$r['heure']] = (int)$r['nb'];

// 5. DurÃ©es moyennes de prÃªt par Ã©quipement
$durees = $pdo->query("
    SELECT e.nom,
        ROUND(AVG(TIMESTAMPDIFF(MINUTE, s.date_action, r.date_action)), 1) AS duree_moy_min
    FROM movements s
    JOIN movements r ON s.equipement_id = r.equipement_id
        AND r.date_action > s.date_action
        AND r.action LIKE '%Retour%'
    JOIN equipements e ON e.id = s.equipement_id
    WHERE s.action LIKE '%prÃªt%'
    GROUP BY e.id
    ORDER BY duree_moy_min DESC
")->fetchAll(PDO::FETCH_ASSOC);

// 6. Derniers logs
$logs = $pdo->query("
    SELECT m.id, e.nom, e.qr_code, m.action, m.date_action
    FROM movements m JOIN equipements e ON m.equipement_id = e.id
    ORDER BY m.date_action DESC LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);
// 7. KPIs globaux
$kpis = $pdo->query("
    SELECT
        COUNT(*) AS total_mouvements,
        COUNT(DISTINCT equipement_id) AS equip_actifs,
        SUM(CASE WHEN action LIKE '%prÃªt%' THEN 1 ELSE 0 END) AS total_sorties,
        SUM(CASE WHEN action LIKE '%Retour%' THEN 1 ELSE 0 END) AS total_retours
    FROM movements
")->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analyse & Export</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <style>
        :root{--bg1:#070b18;--bg2:#111827;--card:rgba(15,23,42,.78);--line:rgba(255,255,255,.10);--txt:#e5e7eb;--muted:#94a3b8;}
        body{min-height:100vh;color:var(--txt);background:radial-gradient(circle at 10% 10%,rgba(59,130,246,.28),transparent 24%),radial-gradient(circle at 88% 18%,rgba(168,85,247,.22),transparent 24%),linear-gradient(135deg,var(--bg1),v>
        .glass{background:var(--card);backdrop-filter:blur(18px);border:1px solid var(--line);border-radius:28px;box-shadow:0 24px 60px rgba(0,0,0,.35);}
        .section-title{font-weight:900;letter-spacing:-.02em;}
        .muted{color:var(--muted);}
        .kpi-card{background:linear-gradient(135deg,rgba(59,130,246,.18),rgba(124,58,237,.14));border:1px solid rgba(255,255,255,.10);border-radius:22px;padding:20px;}
        .kpi-val{font-size:2rem;font-weight:900;}
        .kpi-label{font-size:.8rem;text-transform:uppercase;letter-spacing:.12em;color:var(--muted);}
        .btn-export{border-radius:14px;padding:10px 18px;font-weight:700;border:none;}
        .table thead th{background:rgba(255,255,255,.08)!important;color:var(--txt);border-color:rgba(255,255,255,.08);}
        .table tbody td{background:rgba(255,255,255,.02)!important;color:var(--txt);border-color:rgba(255,255,255,.06);}
        .status-badge{padding:6px 12px;border-radius:999px;font-weight:700;font-size:.82rem;}
        .ok{background:rgba(34,197,94,.16);color:#86efac;}
        .busy{background:rgba(245,158,11,.16);color:#fdba74;}
        .heatmap-cell{display:inline-block;width:36px;height:36px;border-radius:8px;font-size:.72rem;font-weight:700;text-align:center;line-height:36px;margin:2px;}
        .heat0{background:rgba(255,255,255,.05);color:var(--muted);}
        .heat1{background:rgba(59,130,246,.25);color:#93c5fd;}
        .heat2{background:rgba(59,130,246,.45);color:#bfdbfe;}
        .heat3{background:rgba(59,130,246,.70);color:#fff;}
        .heat4{background:rgba(99,102,241,.90);color:#fff;}
        .back-btn{border-radius:14px;padding:10px 20px;font-weight:700;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.10);color:#fff;text-decoration:none;display:inline-flex;align-items:center;gap:8px;}
        canvas{max-height:260px;}
    </style>
</head>
<body>
<div class="container py-4 py-lg-5">
    <!-- Header -->
    <div class="glass p-4 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <a href="index.php" class="back-btn mb-3"><i class="bi bi-arrow-left"></i> Retour</a>
            <h1 class="section-title display-6 mb-1"><i class="bi bi-bar-chart-line me-2"></i>Analyse & Export avancÃ©</h1>
            <div class="muted">Tableau de bord analytique complet de la traÃ§abilitÃ©</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="?type=logs" class="btn btn-primary btn-export"><i class="bi bi-download me-2"></i>CSV Logs</a>
            <a href="?type=equipements" class="btn btn-success btn-export"><i class="bi bi-download me-2"></i>CSV Ã‰quipements</a>
            <a href="?type=stats" class="btn btn-warning btn-export text-dark"><i class="bi bi-download me-2"></i>CSV Stats</a>
        </div>
    </div>

    <!-- KPIs -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><div class="kpi-card"><div class="kpi-label">Total mouvements</div><div class="kpi-val"><?php echo (int)($kpis['total_mouvements']??0); ?></div></div></div>
        <div class="col-6 col-lg-3"><div class="kpi-card"><div class="kpi-label">Ã‰quipements actifs</div><div class="kpi-val"><?php echo (int)($kpis['equip_actifs']??0); ?></div></div></div>
        <div class="col-6 col-lg-3"><div class="kpi-card"><div class="kpi-label">Total sorties</div><div class="kpi-val"><?php echo (int)($kpis['total_sorties']??0); ?></div></div></div>
        <div class="col-6 col-lg-3"><div class="kpi-card"><div class="kpi-label">Total retours</div><div class="kpi-val"><?php echo (int)($kpis['total_retours']??0); ?></div></div></div>
    </div>

    <!-- Graphiques ligne 1 -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="glass p-4 h-100">
                <div class="section-title mb-3"><i class="bi bi-graph-up me-2"></i>Mouvements â€” 30 derniers jours</div>
                <canvas id="chartJours"></canvas>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="glass p-4 h-100">
                <div class="section-title mb-3"><i class="bi bi-pie-chart me-2"></i>RÃ©partition des actions</div>
                <canvas id="chartRep"></canvas>
            </div>
        </div>
    </div>

    <!-- Graphiques ligne 2 -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="glass p-4 h-100">
                <div class="section-title mb-3"><i class="bi bi-trophy me-2"></i>Top équipements utilisés</div>
                <canvas id="chartTop"></canvas>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="glass p-4 h-100">
                <div class="section-title mb-3"><i class="bi bi-stopwatch me-2"></i>Durée moyenne de prêt (min)</div>
                <?php if (!empty($durees)): ?>
<canvas id="chartDuree"></canvas>
                <?php else: ?>
                <p class="muted">Pas encore assez de données (nécessite des cycles complets sortie â†’ retour).</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Heatmap horaire -->
    <div class="glass p-4 mb-4">
        <div class="section-title mb-3"><i class="bi bi-clock me-2"></i>Heatmap activitÃ© par heure</div>
        <div class="d-flex flex-wrap">
            <?php
            $maxH = max(array_values($heureMap)) ?: 1;
            for ($h = 0; $h < 24; $h++):
                $v = $heureMap[$h];
                $ratio = $v / $maxH;
                $cls = $ratio == 0 ? 'heat0' : ($ratio < .25 ? 'heat1' : ($ratio < .55 ? 'heat2' : ($ratio < .85 ? 'heat3' : 'heat4')));
            ?>
            <div class="heatmap-cell <?php echo $cls; ?>" title="<?php echo sprintf('%02d:00 â€” %d mvt', $h, $v); ?>">
                <?php echo sprintf('%02d', $h); ?>
            </div>
            <?php endfor; ?>
        </div>
        <div class="muted small mt-2">Chaque case = une heure de la journée. Plus c'est foncé = plus d'activité.</div>
    </div>

    <!-- Logs dÃ©taillÃ©s -->
    <div class="glass p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div class="section-title"><i class="bi bi-list-ul me-2"></i>Logs détaillés (50 derniers)</div>
            <a href="?type=logs" class="btn btn-primary btn-export btn-sm"><i class="bi bi-download me-1"></i>Export complet</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>#</th><th>Ã‰quipement</th><th>QR Code</th><th>Action</th><th>Date & heure</th></tr></thead>
                <tbody>
                    <?php foreach ($logs as $row): ?>
                    <tr>
                        <td class="muted small"><?php echo h($row['id']); ?></td>
                        <td><?php echo h($row['nom']); ?></td>
                        <td><code><?php echo h($row['qr_code']); ?></code></td>
                        <td><span class="status-badge <?php echo str_contains($row['action'], 'prÃªt') ? 'busy' : 'ok'; ?>"><?php echo h($row['action']); ?></span></td>
                        <td class="muted"><?php echo h($row['date_action']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<div class="text-center muted pb-2 small">Export généré le <?php echo date('d/m/Y Ã  H:i'); ?> â€” Interface sÃ©curisÃ©e admin only.</div>
</div>

<script>
const cfg = (type, labels, data, color, label) => ({
    type,
    data: {
        labels,
        datasets: [{
            label,
            data,
            backgroundColor: Array.isArray(color) ? color : color + '99',
            borderColor: Array.isArray(color) ? color : color,
            borderWidth: 2,
            borderRadius: 10,
            tension: 0.4,
            fill: type === 'line',
            pointRadius: 4,
            pointHoverRadius: 7
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { labels: { color: '#e5e7eb' } } },
        scales: type !== 'pie' && type !== 'doughnut' ? {
            x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,.05)' } },
            y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,.05)' } }
        } : {}
    }
});

// Mouvements par jour
new Chart(document.getElementById('chartJours'), cfg(
    'line',
    <?php echo json_encode(array_column($mouvParJour, 'jour')); ?>,
    <?php echo json_encode(array_map('intval', array_column($mouvParJour, 'nb'))); ?>,
    '#3b82f6', 'Mouvements'
));
// Répartition
const repColors = ['#6366f1','#22c55e','#f59e0b','#ef4444','#06b6d4','#a855f7'];
new Chart(document.getElementById('chartRep'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($repartition, 'action')); ?>,
        datasets: [{ data: <?php echo json_encode(array_map('intval', array_column($repartition, 'nb'))); ?>, backgroundColor: repColors, borderWidth: 2, borderColor: '#111827' }]
    },
    options: { responsive: true, plugins: { legend: { labels: { color: '#e5e7eb', boxWidth: 14 } } } }
});

// Top équipements
new Chart(document.getElementById('chartTop'), cfg(
    'bar',
    <?php echo json_encode(array_column($topEquip, 'nom')); ?>,
    <?php echo json_encode(array_map('intval', array_column($topEquip, 'nb'))); ?>,
    '#a855f7', 'Utilisations'
));

<?php if (!empty($durees)): ?>
// Durées moyennes
new Chart(document.getElementById('chartDuree'), cfg(
    'bar',
    <?php echo json_encode(array_column($durees, 'nom')); ?>,
    <?php echo json_encode(array_map('floatval', array_column($durees, 'duree_moy_min'))); ?>,
    '#22c55e', 'Minutes moyennes'
));
<?php endif; ?>
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

