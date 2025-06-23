<?php
session_start();
include 'fonction_prev.php';
include 'fonction_periode.php';

if (!isset($_SESSION['idDept']) || !isset($_SESSION['nomDept'])) {
    header("Location: login.php");
    exit();
}

$idDept = $_SESSION['idDept'];
$nomDept = $_SESSION['nomDept'];

// Connexion à la base de données
$conn = new mysqli("localhost", "root", "", "SI");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Récupérer les catégories de recettes et dépenses
$categories = array();
$query = "SELECT idType, nomType FROM type";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $categories[$row['idType']] = $row['nomType'];
}

// Récupérer toutes les périodes avec moisDebut, moisFin et année
$periodes = array();
$query = "SELECT idPeriode, moisDebut, moisFin, annee FROM periode ORDER BY annee, moisDebut";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $periodes[] = $row;
}

// Fonction pour obtenir les données détaillées
function getDetailedBudgetData($conn, $idDept, $idPeriode) {
    $data = array();
    
    // Modifier la requête pour récupérer toutes les prévisions et réalisations
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
              WHERE p.idDept = ? AND p.idPeriode = ?
              UNION
              SELECT 
                r.idType,
                t.nomType,
                NULL as nomPrevision,
                0 as prevision,
                r.nomRealisation,
                r.montant as realisation
              FROM realisation r
              JOIN type t ON r.idType = t.idType
              WHERE r.idDept = ? AND r.idPeriode = ?
              AND NOT EXISTS (
                SELECT 1 FROM prevision p 
                WHERE p.idDept = r.idDept 
                AND p.idPeriode = r.idPeriode 
                AND p.idType = r.idType
              )
              ORDER BY idType, nomPrevision, nomRealisation";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiii", $idDept, $idPeriode, $idDept, $idPeriode);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $typeId = $row['idType'];
        if (!isset($data[$typeId])) {
            $data[$typeId] = array(
                'nom' => $row['nomType'],
                'details' => array(),
                'total_prevision' => 0,
                'total_realisation' => 0
            );
        }
        
        // Ajouter les détails
        $data[$typeId]['details'][] = array(
            'nom_prevision' => $row['nomPrevision'],
            'montant_prevision' => $row['prevision'],
            'nom_realisation' => $row['nomRealisation'],
            'montant_realisation' => $row['realisation']
        );
        
        // Mettre à jour les totaux
        $data[$typeId]['total_prevision'] += $row['prevision'];
        $data[$typeId]['total_realisation'] += $row['realisation'];
        $data[$typeId]['ecart'] = $typeId == 1 ? 
            ($data[$typeId]['total_realisation'] - $data[$typeId]['total_prevision']) : // Pour les recettes
            ($data[$typeId]['total_prevision'] - $data[$typeId]['total_realisation']);  // Pour les dépenses
    }
    
    return $data;
}
// Calculer les données pour chaque période
$detailedData = array();
$soldeDebut = 0;

foreach ($periodes as $periode) {
    $periodeData = getDetailedBudgetData($conn, $idDept, $periode['idPeriode']);
    
    // Initialisation des totaux
    $totalRecettesPrevision = 0;
    $totalRecettesReel = 0;
    $totalDepensesPrevision = 0;
    $totalDepensesReel = 0;
    
    foreach ($periodeData as $idType => $item) {
        if ($idType == 1) { // Recettes
            $totalRecettesPrevision += isset($item['total_prevision']) ? $item['total_prevision'] : 0;
            $totalRecettesReel += isset($item['total_realisation']) ? $item['total_realisation'] : 0;
        } else { // Dépenses
            $totalDepensesPrevision += isset($item['total_prevision']) ? $item['total_prevision'] : 0;
            $totalDepensesReel += isset($item['total_realisation']) ? $item['total_realisation'] : 0;
        }
    }
    
    // Calcul du solde fin avec vérification des valeurs nulles
    $soldeFin = $soldeDebut + ($totalRecettesReel - $totalDepensesReel);
    
    $detailedData[] = array(
        'moisDebut' => $periode['moisDebut'],
        'moisFin' => $periode['moisFin'],
        'annee' => $periode['annee'],
        'solde_debut' => $soldeDebut,
        'details' => $periodeData,
        'total_recettes_prev' => $totalRecettesPrevision,
        'total_recettes_real' => $totalRecettesReel,
        'total_depenses_prev' => $totalDepensesPrevision,
        'total_depenses_real' => $totalDepensesReel,
        'solde_fin' => $soldeFin
    );
    
    $soldeDebut = $soldeFin;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Étude Budgétaire Détail - <?php echo htmlspecialchars($nomDept); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        :root {
            --primary-color: #005baa;
            --secondary-color: #003366;
            --accent-color: #4CAF50;
            --danger-color: #e74c3c;
            --light-gray: #f5f7fa;
            --dark-gray: #333;
            --white: #ffffff;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: var(--light-gray);
            color: var(--dark-gray);
        }
        
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        h1 {
            color: var(--secondary-color);
            margin: 0;
            font-size: 24px;
        }
        
        .btn-export {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.3s;
        }
        
        .btn-export:hover {
            background-color: var(--secondary-color);
        }
        
        .table-container {
            background-color: var(--white);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        thead {
            background-color: var(--primary-color);
            color: var(--white);
        }
        
        th {
            padding: 12px 15px;
            text-align: center;
            font-weight: 600;
            position: sticky;
            top: 0;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .text-left {
            text-align: left;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .month-header {
            background-color: #e6f2ff;
            font-weight: 600;
        }
        
        .period-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--secondary-color);
        }
        
        .category-row {
            background-color: var(--light-gray);
        }
        
        .total-row {
            background-color: #d4e6f7;
            font-weight: 600;
        }
        
        .positive {
            color: var(--accent-color);
        }
        
        .negative {
            color: var(--danger-color);
        }
        
        .section-title {
            font-weight: 600;
            color: var(--secondary-color);
        }
        
        @media print {
            .btn-export {
                display: none;
            }
            
            body {
                padding: 0;
                background-color: white;
            }
            
            .table-container {
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div id="content-to-export">
        <div class="header-container">
            <h1>Étude Budgétaire Détail - <?php echo htmlspecialchars($nomDept); ?></h1>
            <button class="btn-export" onclick="exportToPDF()">
                <i class="fas fa-file-pdf"></i> Exporter en PDF
            </button>
        </div>
        
        <?php foreach ($detailedData as $periode): ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th colspan="5" class="period-title">
                            <?php 
                            echo htmlspecialchars($periode['moisDebut']);
                            if ($periode['moisDebut'] != $periode['moisFin']) {
                                echo " - " . htmlspecialchars($periode['moisFin']);
                            }
                            echo " (" . htmlspecialchars($periode['annee']) . ")";
                            ?>
                        </th>
                    </tr>
                    <tr>
                        <th class="text-left">Catégorie</th>
                        <th class="text-right">Prévision</th>
                        <th class="text-right">Réalisation</th>
                        <th class="text-right">Écart</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Recettes -->
                    <tr class="month-header">
                        <td class="text-left section-title" colspan="4">Recettes</td>
                    </tr>
                    
                    <?php 
                    $hasRecettes = false;
                    foreach ($periode['details'] as $idType => $categorie): 
                        if ($idType == 1): // Recettes
                            $hasRecettes = true;
                            foreach ($categorie['details'] as $detail): ?>
                            <tr class="category-row">
                                <td class="text-left">
                                    <?php 
                                    echo $detail['nom_prevision'] ? 
                                        htmlspecialchars($detail['nom_prevision']) : 
                                        htmlspecialchars($detail['nom_realisation']); 
                                    ?>
                                </td>
                                <td class="text-right"><?php echo number_format($detail['montant_prevision'], 0, ',', ' '); ?></td>
                                <td class="text-right"><?php echo number_format($detail['montant_realisation'], 0, ',', ' '); ?></td>
                                <td class="text-right <?php echo ($detail['montant_realisation'] - $detail['montant_prevision'] >= 0) ? 'positive' : 'negative'; ?>">
                                    <?php echo number_format($detail['montant_realisation'] - $detail['montant_prevision'], 0, ',', ' '); ?>
                                </td>
                            </tr>
                            <?php endforeach;
                        endif;
                    endforeach; 
                    
                    if (!$hasRecettes): ?>
                    <tr class="category-row">
                        <td class="text-left" colspan="4">Aucune recette enregistrée</td>
                    </tr>
                    <?php endif; ?>
                    
                    <!-- Total Recettes -->
                    <tr class="total-row">
                        <td class="text-left">Total Recettes</td>
                        <td class="text-right"><?php echo number_format($periode['total_recettes_prev'], 0, ',', ' '); ?></td>
                        <td class="text-right"><?php echo number_format($periode['total_recettes_real'], 0, ',', ' '); ?></td>
                        <td class="text-right <?php echo (($periode['total_recettes_real'] - $periode['total_recettes_prev']) >= 0) ? 'positive' : 'negative'; ?>">
                            <?php echo number_format($periode['total_recettes_real'] - $periode['total_recettes_prev'], 0, ',', ' '); ?>
                        </td>
                    </tr>
                    
                    <!-- Dépenses -->
                    <tr class="month-header">
                        <td class="text-left section-title" colspan="4">Dépenses</td>
                    </tr>
                    
                    <?php 
                    $hasDepenses = false;
                    foreach ($periode['details'] as $idType => $categorie): 
                        if ($idType == 2): // Dépenses
                            $hasDepenses = true;
                            foreach ($categorie['details'] as $detail): ?>
                            <tr class="category-row">
                                <td class="text-left">
                                    <?php 
                                    echo $detail['nom_prevision'] ? 
                                        htmlspecialchars($detail['nom_prevision']) : 
                                        htmlspecialchars($detail['nom_realisation']); 
                                    ?>
                                </td>
                                <td class="text-right"><?php echo number_format($detail['montant_prevision'], 0, ',', ' '); ?></td>
                                <td class="text-right"><?php echo number_format($detail['montant_realisation'], 0, ',', ' '); ?></td>
                                <td class="text-right <?php echo ($detail['montant_realisation'] - $detail['montant_prevision'] >= 0) ? 'positive' : 'negative'; ?>">
                                    <?php echo number_format($detail['montant_realisation'] - $detail['montant_prevision'], 0, ',', ' '); ?>
                                </td>
                            </tr>
                            <?php endforeach;
                        endif;
                    endforeach; 
                    
                    if (!$hasDepenses): ?>
                    <tr class="category-row">
                        <td class="text-left" colspan="4">Aucune dépense enregistrée</td>
                    </tr>
                    <?php endif; ?>
                    
                    <!-- Total Dépenses -->
                    <tr class="total-row">
                        <td class="text-left">Total Dépenses</td>
                        <td class="text-right"><?php echo number_format($periode['total_depenses_prev'], 0, ',', ' '); ?></td>
                        <td class="text-right"><?php echo number_format($periode['total_depenses_real'], 0, ',', ' '); ?></td>
                        <td class="text-right <?php echo (($periode['total_depenses_prev'] - $periode['total_depenses_real']) >= 0) ? 'positive' : 'negative'; ?>">
                            <?php echo number_format($periode['total_depenses_prev'] - $periode['total_depenses_real'], 0, ',', ' '); ?>
                        </td>
                    </tr>
                    
                    <!-- Solde -->
                    <tr class="month-header">
                        <td class="text-left section-title" colspan="4">Solde</td>
                    </tr>
                    <tr>
                        <td class="text-left">Solde Début</td>
                        <td class="text-right" colspan="3"><?php echo number_format($periode['solde_debut'], 0, ',', ' '); ?></td>
                    </tr>
                    <tr>
                        <td class="text-left">Solde Fin</td>
                        <td class="text-right <?php echo ($periode['solde_fin'] >= 0) ? 'positive' : 'negative'; ?>" colspan="3">
                            <?php echo number_format($periode['solde_fin'], 0, ',', ' '); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
    </div>

    <script>
        function exportToPDF() {
            const element = document.getElementById('content-to-export');
            const opt = {
                margin: 10,
                filename: 'etude_budgetaire_detail_<?php echo $nomDept; ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            const button = document.querySelector('.btn-export');
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Génération...';
            button.disabled = true;

            html2pdf().set(opt).from(element).save().then(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            });
        }
    </script>
</body>
</html>