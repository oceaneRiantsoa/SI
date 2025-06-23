<?php
session_start();
include 'connexion.php';
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

// Ajouter après la connexion à la base de données
function getVentesStats($conn, $idProduit = null) {
    $dateCRM = '2025-05-07';
    $where = $idProduit ? "AND idProduit = $idProduit" : "";
    
    // Avant CRM
    $avantCRM = $conn->query("
        SELECT COUNT(*) as nbVentes, SUM(nbAchete) as totalVentes
        FROM venteProduit 
        WHERE dateVente < '$dateCRM' $where
    ")->fetch_assoc();

    // Après CRM
    $apresCRM = $conn->query("
        SELECT COUNT(*) as nbVentes, SUM(nbAchete) as totalVentes
        FROM venteProduit 
        WHERE dateVente >= '$dateCRM' $where
    ")->fetch_assoc();

    return [
        'avant' => $avantCRM['totalVentes'] ?? 0,
        'apres' => $apresCRM['totalVentes'] ?? 0
    ];
}

// Ajouter après la fonction getVentesStats
function getChiffreAffairesStats($conn, $idProduit = null) {
    $dateCRM = '2025-05-07';
    $where = $idProduit ? "AND v.idProduit = $idProduit" : "";
    
    // Avant CRM
    $avantCRM = $conn->query("
        SELECT SUM(v.nbAchete * p.prixUnitaire) as totalCA
        FROM venteProduit v
        JOIN produit p ON v.idProduit = p.idProduit
        WHERE v.dateVente < '$dateCRM' $where
    ")->fetch_assoc();

    // Après CRM
    $apresCRM = $conn->query("
        SELECT SUM(v.nbAchete * p.prixUnitaire) as totalCA
        FROM venteProduit v
        JOIN produit p ON v.idProduit = p.idProduit
        WHERE v.dateVente >= '$dateCRM' $where
    ")->fetch_assoc();

    return [
        'avant' => $avantCRM['totalCA'] ?? 0,
        'apres' => $apresCRM['totalCA'] ?? 0
    ];
}

// Ajouter après les autres fonctions
function getTop3Produits($conn) {
    $result = $conn->query("
        SELECT p.nomProduit, SUM(v.nbAchete) as totalVentes
        FROM venteProduit v
        JOIN produit p ON v.idProduit = p.idProduit
        GROUP BY p.idProduit, p.nomProduit
        ORDER BY totalVentes DESC
        LIMIT 3
    ");
    
    $labels = [];
    $data = [];
    $colors = ['#FF6384', '#36A2EB', '#FFCE56']; // Couleurs pour chaque portion
    
    while($row = $result->fetch_assoc()) {
        $labels[] = $row['nomProduit'];
        $data[] = $row['totalVentes'];
    }
    
    return [
        'labels' => $labels,
        'data' => $data,
        'colors' => $colors
    ];
}

// Modifier la partie du traitement de simulation des ventes
if(isset($_POST['simuler_ventes'])) {
    $periode = $_POST['periode'];  // Corriger cette ligne
    $dateSimulation = ($periode === 'before') ? '2025-05-13' : '2025-05-15';
    
    // Récupérer tous les produits
    $produits = $conn->query("SELECT idProduit, prixUnitaire FROM produit");
    
    // Liste de noms de clients pour la simulation
    $clients = ['Rakoto', 'Rasoa', 'Rabe', 'Randria', 'Razafy', 'Rasoanirina', 'Ramarolahy'];
    
    while($produit = $produits->fetch_assoc()) {
        // Générer un nombre aléatoire de ventes différent selon la période
        $nbVentes = ($periode === 'before') ? rand(1, 3) : rand(3, 7); // Plus de ventes après le 7 mai
        
        for($i = 0; $i < $nbVentes; $i++) {
            // Choisir un client aléatoire
            $client = $clients[array_rand($clients)];
            
            // Générer une quantité aléatoire (entre 1 et 3)
            $quantite = rand(1, 3);
            
            // Insérer la vente
            $stmt = $conn->prepare("INSERT INTO venteProduit (nomClient, idProduit, nbAchete, dateVente) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("siis", $client, $produit['idProduit'], $quantite, $dateSimulation);
            $stmt->execute();
        }
    }
    
    // Message de succès
    $_SESSION['success'] = "Simulation des ventes effectuée avec succès pour la période " . 
                          ($periode === 'before' ? 'avant' : 'après') . " le 14 mai.";
    
    // Redirection
    header("Location: tableau.php");
    exit();
}

// Afficher le message de succès s'il existe
if(isset($_SESSION['success'])) {
    echo "<div class='alert alert-success'>" . $_SESSION['success'] . "</div>";
    unset($_SESSION['success']);
}

// Modifier la requête des statistiques
$stats = $conn->query("
    SELECT s.*, p.nomProduit, a.nomAction, r.nomReaction, r.dateValidation as dateCRM, r.solution
    FROM statistique s
    LEFT JOIN produit p ON s.idProduit = p.idProduit
    LEFT JOIN action a ON s.idAction = a.id
    LEFT JOIN reaction r ON s.idReaction = r.id
    WHERE s.dateStat >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
    ORDER BY s.dateStat DESC
");

// Récupérer les données pour le graphique (nombre de clients distincts)
$dateAction = '2025-03-20'; // Date de la première action
$dateCRM = '2025-04-01';   // Date de mise en place du CRM

// Avant Action
$avantAction = $conn->query("
    SELECT COUNT(DISTINCT nomClient) as nbClients
    FROM venteProduit
    WHERE dateVente < '$dateAction'
")->fetch_assoc()['nbClients'] ?? 0;

// Après Action (mais avant CRM)
$apresAction = $conn->query("
    SELECT COUNT(DISTINCT nomClient) as nbClients
    FROM venteProduit
    WHERE dateVente >= '$dateAction' AND dateVente < '$dateCRM'
")->fetch_assoc()['nbClients'] ?? 0;

// Avant CRM
$avantCRM = $conn->query("
    SELECT COUNT(DISTINCT nomClient) as nbClients
    FROM venteProduit
    WHERE dateVente < '$dateCRM'
")->fetch_assoc()['nbClients'] ?? 0;

// Après CRM
$apresCRM = $conn->query("
    SELECT COUNT(DISTINCT nomClient) as nbClients
    FROM venteProduit
    WHERE dateVente >= '$dateCRM'
")->fetch_assoc()['nbClients'] ?? 0;

// Fonction de simulation des ventes
function simulerVente($conn, $idProduit, $idAction = null, $idReaction = null, $periodeAvant = true) {
    // Vérifier si le produit existe
    $produit = $conn->query("SELECT prixUnitaire FROM produit WHERE idProduit = $idProduit")->fetch_assoc();
    if (!$produit) {
        return false;
    }
    $prix = $produit['prixUnitaire'];

    // Récupérer les CRM validés
    $crms = $conn->query("SELECT dateValidation FROM reaction WHERE solution IS NOT NULL ORDER BY dateValidation")->fetch_all(MYSQLI_ASSOC);
    
    // Facteurs d'impact de base
    $baseVentes = rand(10, 50);
    $multiplicateur = 1;

    // Impact des CRM validés
    foreach($crms as $crm) {
        if (!$periodeAvant && strtotime($dateStat) > strtotime($crm['dateValidation'])) {
            $multiplicateur *= 1.3; // +30% pour chaque CRM validé
        }
    }

    // Impact des actions et réactions
    if ($idAction) $multiplicateur *= 1.2;
    if ($idReaction) $multiplicateur *= 1.25;

    // Calculer les ventes finales
    $nbVentes = round($baseVentes * $multiplicateur);
    $totalRevenue = $nbVentes * $prix;

    // Insérer dans la table statistique
    $dateStat = date('Y-m-d');
    $sql = "INSERT INTO statistique (idProduit, idAction, idReaction, periodeAvant, nbVentes, totalRevenue, dateStat)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiibids", $idProduit, $idAction, $idReaction, $periodeAvant, $nbVentes, $totalRevenue, $dateStat);
    return $stmt->execute();
}

// Gestion de la simulation
if (isset($_POST['simuler'])) {
    $idProduit = $_POST['idProduit'];
    $idAction = !empty($_POST['idAction']) ? $_POST['idAction'] : null;
    $idReaction = !empty($_POST['idReaction']) ? $_POST['idReaction'] : null;
    $periodeAvant = isset($_POST['periodeAvant']) ? 1 : 0;
    if (simulerVente($conn, $idProduit, $idAction, $idReaction, $periodeAvant)) {
        header("Location: tableau.php"); // Rafraîchir la page
    } else {
        echo "<p style='color: red;'>Erreur lors de la simulation : produit invalide.</p>";
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau - <?php echo htmlspecialchars($nomDept); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #e83e8c;     /* Rose vif */
            --secondary: #6c3454;    /* Rose foncé */
            --success: #28a745;
            --danger: #dc3545;
            --light: #fdf2f6;       /* Rose très clair */
            --pink-100: #fce7f0;    /* Nuances de rose */
            --pink-200: #fad1e3;
            --pink-300: #f8bbd8;
        }

        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background: var(--pink-100); 
        }

        .container { 
            max-width: 1200px; 
            margin: auto; 
            background: white; 
            padding: 20px; 
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(232, 62, 140, 0.1);
        }

        .header { 
            text-align: center; 
            margin-bottom: 20px;
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            color: white;
            padding: 20px;
            border-radius: 8px;
        }

        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px; 
        }

        th, td { 
            padding: 12px; 
            border: 1px solid var(--pink-200); 
            text-align: left; 
        }

        th { 
            background: var(--primary); 
            color: white; 
        }

        .btn { 
            padding: 10px 20px; 
            background: var(--primary); 
            color: white; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
        }

        .btn:hover { 
            background: var(--secondary); 
        }

        .chart-container { 
            margin-top: 40px; 
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(232, 62, 140, 0.1);
            border: 1px solid var(--pink-200);
        }

        /* Réduire la taille du diagramme top 3 */
        .chart-container:last-child {
            max-width: 400px;
            margin: 40px auto;
        }

        .simulation-container { 
            margin-top: 20px;
            background: var(--light);
            padding: 20px;
            border-radius: 8px;
            border: 1px solid var(--pink-200);
        }

        .bank-btn { 
            padding: 10px 20px; 
            background: var(--primary); 
            color: white; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
        }

        .bank-btn:hover { 
            background: var(--secondary); 
        }

        select, input {
            padding: 8px;
            border: 1px solid var(--pink-200);
            border-radius: 4px;
            margin: 5px;
        }

        select:focus, input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--pink-100);
        }

        .positive { color: var(--success); }
        .negative { color: var(--danger); }

        .total-row {
            background: var(--light);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Tableau - <?php echo htmlspecialchars($nomDept); ?></h1>
            <p>Statistiques des ventes et simulation</p>
        </div>

        <!-- Formulaire de simulation -->
        <div class="form-container">
            <form method="POST">
                <label for="idProduit">Produit :</label>
                <select name="idProduit" required>
                    <?php
                    $produits = $conn->query("SELECT * FROM produit");
                    while ($p = $produits->fetch_assoc()) {
                        echo "<option value='{$p['idProduit']}'>{$p['nomProduit']}</option>";
                    }
                    ?>
                </select>

                <label for="idAction">Action (optionnel) :</label>
                <select name="idAction">
                    <option value="">Aucune</option>
                    <?php
                    $actions = $conn->query("SELECT * FROM action WHERE idDept = $idDept");
                    while ($a = $actions->fetch_assoc()) {
                        echo "<option value='{$a['id']}'>{$a['nomAction']}</option>";
                    }
                    ?>
                </select>

                <label for="idReaction">Réaction (optionnel) :</label>
                <select name="idReaction">
                    <option value="">Aucune</option>
                    <?php
                    $reactions = $conn->query("SELECT * FROM reaction WHERE idDept = $idDept");
                    while ($r = $reactions->fetch_assoc()) {
                        echo "<option value='{$r['id']}'>{$r['nomReaction']}</option>";
                    }
                    ?>
                </select>

                <label><input type="checkbox" name="periodeAvant" checked> Période avant</label>
                <button type="submit" name="simuler" class="btn">Simuler Vente</button>
            </form>
        </div>

        <!-- Simulation des ventes -->
        <div class="simulation-container">
            <h3><i class="fas fa-shopping-cart"></i> Simulation des Ventes</h3>
            <form method="POST" class="simulation-form">
                <div class="form-group">
                    <label>Date de simulation :</label>
                    <select name="periode" required>
                        <option value="before">Avant CRM</option>
                        <option value="after">Après CRM</option>
                    </select>
                </div>
                <button type="submit" name="simuler_ventes" class="bank-btn">Simuler les Ventes</button>
            </form>
        </div>

        <!-- Sélection du produit pour les statistiques -->
        <div class="stats-container">
            <h3>Statistiques des ventes par produit</h3>
            <form method="GET" class="stats-form">
                <select name="produit_stat" onchange="this.form.submit()">
                    <option value="">Tous les produits</option>
                    <?php
                    $produits = $conn->query("SELECT * FROM produit");
                    while ($p = $produits->fetch_assoc()) {
                        $selected = (isset($_GET['produit_stat']) && $_GET['produit_stat'] == $p['idProduit']) ? 'selected' : '';
                        echo "<option value='{$p['idProduit']}' $selected>{$p['nomProduit']}</option>";
                    }
                    ?>
                </select>
            </form>

            <!-- Graphique Chart.js -->
            <div class="chart-container">
                <canvas id="ventesChart"></canvas>
            </div>

            <!-- Ajouter après la div chart-container du premier graphique -->
            <div class="chart-container">
                <h3>Évolution du Chiffre d'Affaires</h3>
                <canvas id="caChart"></canvas>
            </div>

            <?php
            // $statsCA = getChiffreAffairesStats($conn, $idProduitStat);
            ?>

            <script>
            const ctxCA = document.getElementById('caChart').getContext('2d');
            new Chart(ctxCA, {
                type: 'bar',
                data: {
                    labels: ['Avant CRM', 'Après CRM'],
                    datasets: [{
                        label: 'Chiffre d\'affaires (Ar)',
                        data: [
                            <?php echo $statsCA['avant']; ?>,
                            <?php echo $statsCA['apres']; ?>
                        ],
                        backgroundColor: [
                            '#FFB366',
                            '#66B2FF'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Chiffre d\'affaires (Ar)'
                            }
                        }
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: <?php 
                                if ($idProduitStat) {
                                    $nomProduit = $conn->query("SELECT nomProduit FROM produit WHERE idProduit = $idProduitStat")->fetch_assoc()['nomProduit'];
                                    echo "'Chiffre d\'affaires - $nomProduit'";
                                } else {
                                    echo "'Chiffre d\'affaires - Tous les produits'";
                                }
                            ?>
                        }
                    }
                }
            });
            </script>
        </div>

        <?php
        // Récupérer les statistiques pour le produit sélectionné ou tous les produits
        $idProduitStat = isset($_GET['produit_stat']) ? $_GET['produit_stat'] : null;
        $statsVentes = getVentesStats($conn, $idProduitStat);
        ?>

        <script>
            const ctxVentes = document.getElementById('ventesChart').getContext('2d');
            new Chart(ctxVentes, {
                type: 'bar',
                data: {
                    labels: ['Avant CRM', 'Après CRM'],
                    datasets: [{
                        label: 'Nombre de ventes',
                        data: [
                            <?php echo $statsVentes['avant']; ?>,
                            <?php echo $statsVentes['apres']; ?>
                        ],
                        backgroundColor: [
                            '#FF9999',
                            '#99FF99'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Nombre de ventes'
                            }
                        }
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: <?php 
                                if ($idProduitStat) {
                                    $nomProduit = $conn->query("SELECT nomProduit FROM produit WHERE idProduit = $idProduitStat")->fetch_assoc()['nomProduit'];
                                    echo "'Statistiques des ventes - $nomProduit'";
                                } else {
                                    echo "'Statistiques des ventes - Tous les produits'";
                                }
                            ?>
                        }
                    }
                }
            });
        </script>

        <!-- Tableau des statistiques -->
        <table>
            <tr>
                <th>Produit</th>
                <th>Action</th>
                <th>CRM (Réaction)</th>
                <th>Date CRM</th>
                <th>Solution CRM</th>
                <th>Période</th>
                <th>Nombre de ventes</th>
                <th>Chiffre d'affaires</th>
                <th>Date</th>
            </tr>
            <?php while ($row = $stats->fetch_assoc()) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['nomProduit'] ?: 'Produit inconnu'); ?></td>
                    <td><?php echo htmlspecialchars($row['nomAction'] ?: 'Aucune'); ?></td>
                    <td><?php echo htmlspecialchars($row['nomReaction'] ?: 'Aucun'); ?></td>
                    <td><?php echo $row['dateCRM'] ?: '-'; ?></td>
                    <td><?php echo htmlspecialchars($row['solution'] ?: '-'); ?></td>
                    <td><?php echo $row['periodeAvant'] ? 'Avant' : 'Après'; ?></td>
                    <td><?php echo $row['nbVentes']; ?></td>
                    <td><?php echo number_format($row['totalRevenue'], 2); ?> Ar</td>
                    <td><?php echo $row['dateStat']; ?></td>
                </tr>
            <?php } ?>
        </table>

        <!-- Ajouter après le tableau des statistiques existant -->
        <h3>Récapitulatif du Chiffre d'Affaires par Produit</h3>
        <table>
            <tr>
                <th>Produit</th>
                <th>CA Avant CRM</th>
                <th>CA Après CRM</th>
                <th>Évolution</th>
            </tr>
            <?php 
            $produits = $conn->query("SELECT * FROM produit");
            while($produit = $produits->fetch_assoc()) {
                $statsCA = getChiffreAffairesStats($conn, $produit['idProduit']);
                $evolution = $statsCA['apres'] - $statsCA['avant'];
                $evolutionPourcent = $statsCA['avant'] > 0 ? ($evolution / $statsCA['avant'] * 100) : 0;
            ?>
            <tr>
                <td><?= htmlspecialchars($produit['nomProduit']) ?></td>
                <td><?= number_format($statsCA['avant'], 2) ?> Ar</td>
                <td><?= number_format($statsCA['apres'], 2) ?> Ar</td>
                <td class="<?= $evolution >= 0 ? 'positive' : 'negative' ?>">
                    <?= number_format($evolutionPourcent, 1) ?>%
                    (<?= number_format($evolution, 2) ?> Ar)
                </td>
            </tr>
            <?php } ?>
            <?php 
            // Ligne pour le total
            $statsTotalCA = getChiffreAffairesStats($conn);
            $evolutionTotal = $statsTotalCA['apres'] - $statsTotalCA['avant'];
            $evolutionPourcentTotal = $statsTotalCA['avant'] > 0 ? ($evolutionTotal / $statsTotalCA['avant'] * 100) : 0;
            ?>
            <tr class="total-row">
                <td><strong>Total</strong></td>
                <td><strong><?= number_format($statsTotalCA['avant'], 2) ?> Ar</strong></td>
                <td><strong><?= number_format($statsTotalCA['apres'], 2) ?> Ar</strong></td>
                <td class="<?= $evolutionTotal >= 0 ? 'positive' : 'negative' ?>">
                    <strong><?= number_format($evolutionPourcentTotal, 1) ?>%
                    (<?= number_format($evolutionTotal, 2) ?> Ar)</strong>
                </td>
            </tr>
        </table>

        <!-- Graphique Chart.js -->
        <!-- <div class="chart-container">
            <canvas id="clientsChart"></canvas>
        </div> -->
        <script>
            const ctx = document.getElementById('clientsChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Avant CRM', 'Après CRM'],
                    datasets: [{
                        label: 'Nombre de Ventes',
                        data: [
                            <?php echo getVentesStats($conn)['avant']; ?>,
                            <?php echo getVentesStats($conn)['apres']; ?>
                        ],
                        backgroundColor: [
                            '#FF9999',
                            '#66B2FF'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Nombre de Ventes'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Périodes'
                            }
                        }
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Évolution des Ventes Avant et Après CRM'
                        }
                    }
                }
            });
        </script>

        <!-- Ajouter le graphique combiné -->
        <div class="chart-container">
            <canvas id="combinedChart"></canvas>
        </div>

        <script>
        const ctxCombined = document.getElementById('combinedChart').getContext('2d');
        new Chart(ctxCombined, {
            type: 'bar',
            data: {
                labels: ['Avant CRM', 'Après CRM'],
                datasets: [{
                    label: 'Nombre de ventes',
                    data: [
                        <?php echo $statsVentes['avant']; ?>,
                        <?php echo $statsVentes['apres']; ?>
                    ],
                    backgroundColor: '#FF9999',
                    borderWidth: 1,
                    yAxisID: 'y1'
                }, {
                    label: 'Chiffre d\'affaires (Ar)',
                    data: [
                        <?php echo $statsCA['avant']; ?>,
                        <?php echo $statsCA['apres']; ?>
                    ],
                    backgroundColor: '#66B2FF',
                    borderWidth: 1,
                    yAxisID: 'y2'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Nombre de ventes'
                        }
                    },
                    y2: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Chiffre d\'affaires (Ar)'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Évolution des ventes et du chiffre d\'affaires'
                    }
                }
            }
        });
        </script>

        <!-- Ajouter après les autres graphiques, avant la fermeture de la div stats-container -->
        <div class="chart-container">
            <h3>Top 3 des Produits les Plus Vendus</h3>
            <canvas id="top3Chart"></canvas>
        </div>

        <?php
        $top3Data = getTop3Produits($conn);
        ?>

        <script>
        const ctxTop3 = document.getElementById('top3Chart').getContext('2d');
        new Chart(ctxTop3, {
            type: 'bar', // Changement pour un graphique en barres horizontales
            data: {
                labels: <?php echo json_encode($top3Data['labels']); ?>,
                datasets: [{
                    label: 'Nombre de ventes',
                    data: <?php echo json_encode($top3Data['data']); ?>,
                    backgroundColor: [
                        'rgba(232, 62, 140, 0.8)',  // Rose vif
                        'rgba(108, 52, 84, 0.8)',   // Rose foncé
                        'rgba(232, 189, 208, 0.8)'  // Rose clair
                    ],
                    borderColor: [
                        'rgba(232, 62, 140, 1)',
                        'rgba(108, 52, 84, 1)',
                        'rgba(252, 231, 240, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y', // Pour avoir des barres horizontales
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    x: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Nombre de ventes'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false // Cache la légende car non nécessaire
                    },
                    title: {
                        display: true,
                        text: 'Top 3 des Produits les Plus Vendus',
                        font: {
                            size: 16
                        }
                    }
                },
                // Ajouter des étiquettes de données sur les barres
                plugins: [{
                    afterDraw: function(chart) {
                        var ctx = chart.ctx;
                        chart.data.datasets.forEach(function(dataset, datasetIndex) {
                            var meta = chart.getDatasetMeta(datasetIndex);
                            if (!meta.hidden) {
                                meta.data.forEach(function(element, index) {
                                    var dataValue = dataset.data[index];
                                    var xPosition = element.x + 10;
                                    var yPosition = element.y;
                                    
                                    ctx.fillStyle = '#333';
                                    ctx.textAlign = 'left';
                                    ctx.textBaseline = 'middle';
                                    ctx.fillText(dataValue, xPosition, yPosition);
                                });
                            }
                        });
                    }
                }]
            }
        });
        </script>
    </div>
</body>
</html>