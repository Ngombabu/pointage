<?php
// superviseur/dashboard.php
session_start();

if (!isset($_SESSION['superviseur_id']) || !isset($_SESSION['shop_id'])) {
    header('Location: /POINTAGE/superviseur/');
    exit;
}

$shopId = $_SESSION['shop_id'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - MobileApp</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .dashboard-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .dashboard-header {
            background: rgba(255,255,255,0.9);
            border-radius: 30px;
            padding: 2rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 600;
            color: #2563eb;
        }

        .btn-scan {
            display: inline-block;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            color: white;
            text-decoration: none;
            border-radius: 30px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-scan:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(37,99,235,0.3);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Tableau de bord</h1>
            <a href="../SCAN/scan.html" class="btn-scan">
                📷 Scanner un QR code
            </a>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div>Présences aujourd'hui</div>
                <div class="stat-number" id="presencesToday">0</div>
            </div>
            <div class="stat-card">
                <div>Retards aujourd'hui</div>
                <div class="stat-number" id="retardsToday">0</div>
            </div>
            <div class="stat-card">
                <div>Agents actifs</div>
                <div class="stat-number" id="agentsActifs">0</div>
            </div>
        </div>
    </div>

    <script>
        // Charger les stats
        fetch('api.php?action=stats')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('presencesToday').textContent = data.presences;
                    document.getElementById('retardsToday').textContent = data.retards;
                    document.getElementById('agentsActifs').textContent = data.agents;
                }
            });
    </script>
</body>
</html>