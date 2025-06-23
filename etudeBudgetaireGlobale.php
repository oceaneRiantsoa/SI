<?php
session_start();
include 'fonction_prev.php';
include 'fonction_periode.php';

$conn = new mysqli("localhost", "root", "", "SI");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Récupérer toutes les périodes uniques
$periodes = $conn->query("SELECT DISTINCT p.*, d.nomDept 
                         FROM periode p 
                         JOIN departement d ON p.idDept = d.idDept 
                         ORDER BY p.annee, p.moisDebut");

function getDetailedBudgetData($conn, $idDept, $idPeriode) {
    $data = array();
    
    $query = "SELECT 
                p.idType, 
                t.nomType,
                p.nomPrevision,
                p.montant as prevision,
                r.nomRealisation,
                r.montant as realisation
              FROM prevision p
              JOIN type t ON p.idType = t.idType
              LEFT JOIN realisation r ON 
                r.idDept = p.idDept AND 
                r.idPeriode = p.idPeriode AND 
                r.idType = p.idType
              WHERE p.idDept = ? AND p.idPeriode = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $idDept, $idPeriode);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $typeId = $row['idType'];
        if (!isset($data[$typeId])) {
            $data[$typeId] = array(
                'total_prevision' => 0,
                'total_realisation' => 0
            );
        }
        $data[$typeId]['total_prevision'] += $row['prevision'];
        $data[$typeId]['total_realisation'] += $row['realisation'] ?? 0;
    }
    
    return $data;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Étude Budgétaire Globale</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #3498db;
            --secondary: #2c3e50;
            --success: #2ecc71;
            --danger: #e74c3c;
            --light: #ecf0f1;
            --dark: #34495e;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f6fa;
        }

        .header-container {
            text-align: center;
            margin-bottom: 30px;
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            padding: 20px;
            border-radius: 8px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-export {
            background-color: white;
            color: var(--primary);
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .btn-export:hover {
            background-color: var(--light);
            transform: translateY(-2px);
        }

        .table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background-color: var(--light);
            font-weight: bold;
            color: var(--dark);
        }

        .period-title {
            background: var(--secondary);
            color: white;
            font-size: 1.2em;
            padding: 15px;
        }

        .text-right {
            text-align: right;
        }

        .text-left {
            text-align: left;
        }

        .positive {
            color: var(--success);
            font-weight: bold;
        }

        .negative {
            color: var(--danger);
            font-weight: bold;
        }

        .dept-info {
            font-style: italic;
            color: var(--dark);
        }

        .period-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            background-color: var(--light);
        }

        .period-info {
            font-weight: bold;
            color: var(--secondary);
        }

        .total-row {
            background-color: #f8f9fa;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div id="content-to-export">
        <div class="header-container">
            <h1>Étude Budgétaire Globale</h1>
            <button class="btn-export" onclick="exportToPDF()">
                <i class="fas fa-file-pdf"></i> Exporter en PDF
            </button>
        </div>

        <?php while($periode = $periodes->fetch_assoc()): ?>
        <div class="table-container">
            <div class="period-header">
                <div class="period-info">
                    <?php 
                    $debut = DateTime::createFromFormat('!m', $periode['moisDebut'])->format('F');
                    $fin = DateTime::createFromFormat('!m', $periode['moisFin'])->format('F');
                    echo "$debut - $fin {$periode['annee']}";
                    ?>
                </div>
                <div class="dept-info">
                    Département : <?php echo htmlspecialchars($periode['nomDept']); ?>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th class="text-left">Type</th>
                        <th class="text-right">Prévision</th>
                        <th class="text-right">Réalisation</th>
                        <th class="text-right">Écart</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $data = getDetailedBudgetData($conn, $periode['idDept'], $periode['idPeriode']);
                    $total_prev = 0;
                    $total_real = 0;
                    
                    foreach([1 => 'Recettes', 2 => 'Dépenses'] as $typeId => $typeNom):
                        $montant_prev = $data[$typeId]['total_prevision'] ?? 0;
                        $montant_real = $data[$typeId]['total_realisation'] ?? 0;
                        
                        // Calcul de l'écart selon le type
                        if ($typeId == 1) { // Recettes
                            $ecart = $montant_real - $montant_prev; // réalisation - prévision
                        } else { // Dépenses
                            $ecart = $montant_prev - $montant_real; // prévision - réalisation
                        }
                        
                        $total_prev += $typeId == 1 ? $montant_prev : -$montant_prev;
                        $total_real += $typeId == 1 ? $montant_real : -$montant_real;
                    ?>
                    <tr>
                        <td class="text-left"><?php echo $typeNom; ?></td>
                        <td class="text-right"><?php echo number_format($montant_prev, 0, ',', ' '); ?></td>
                        <td class="text-right"><?php echo number_format($montant_real, 0, ',', ' '); ?></td>
                        <td class="text-right <?php echo $ecart >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo number_format($ecart, 0, ',', ' '); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <tr class="total-row">
                        <td class="text-left">Solde</td>
                        <td class="text-right"><?php echo number_format($total_prev, 0, ',', ' '); ?></td>
                        <td class="text-right"><?php echo number_format($total_real, 0, ',', ' '); ?></td>
                        <td class="text-right <?php echo ($total_real - $total_prev) >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo number_format($total_real - $total_prev, 0, ',', ' '); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endwhile; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        function exportToPDF() {
            const element = document.getElementById('content-to-export');
            const opt = {
                margin: 1,
                filename: 'etude_budgetaire_globale.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            const button = document.querySelector('.btn-export');
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Génération du PDF...';
            button.disabled = true;

            html2pdf().set(opt).from(element).save()
                .then(() => {
                    button.innerHTML = originalText;
                    button.disabled = false;
                });
        }
    </script>
</body>
</html>