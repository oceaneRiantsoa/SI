<?php
session_start();
// Connexion à la base de données
$conn = new mysqli("localhost", "root", "", "SI");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Récupération des paramètres
$idDept = $_GET['dept'] ?? 5;
$idPeriode = $_GET['periode'] ?? null;

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['valider_periode'])) {
        $conn->query("INSERT INTO realisation SELECT * FROM temp_realisation WHERE idDept = $idDept AND idPeriode = $idPeriode");
        $conn->query("DELETE FROM temp_realisation WHERE idDept = $idDept AND idPeriode = $idPeriode");
    } 
    elseif (isset($_POST['ajouter'])) {
        $stmt = $conn->prepare("INSERT INTO temp_realisation (idDept, idPeriode, nomRealisation, idType, montant) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisid", $_POST['idDept'], $_POST['idPeriode'], $_POST['nomRealisation'], $_POST['idType'], $_POST['montant']);
        $stmt->execute();
    }
    elseif (isset($_POST['modifier'])) {
        $id = $_POST['id'];
        $table = $_POST['source'] === 'temp' ? 'temp_realisation' : 'realisation';
        $idField = $_POST['source'] === 'temp' ? 'idTempRealisation' : 'idRealisation';
        $montant = $_POST['montant'];
        
        $conn->query("UPDATE $table SET montant = $montant WHERE $idField = $id");
    }
    elseif (isset($_POST['submit_reaction'])) {
        $reactionId = $_POST['reaction_id'] ?? null;
        $idDept = $_POST['idDept'];
        $nomAction = $_POST['nomAction'] ?? '';
        $nomReaction = $_POST['nomReaction'];
        $solution = $_POST['solution'];
        $budgetReaction = $_POST['budgetReaction'];

        if ($reactionId) {
            // Modification
            $stmt = $conn->prepare("UPDATE reaction_temp SET idDept = ?, nomAction = ?, nomReaction = ?, solution = ?, budgetReaction = ? WHERE id = ?");
            $stmt->bind_param("isssdi", 
                $idDept, 
                $nomAction, 
                $nomReaction, 
                $solution, 
                $budgetReaction, 
                $reactionId
            );
        } else {
            // Nouvelle insertion
            $stmt = $conn->prepare("INSERT INTO reaction_temp (idDept, nomAction, nomReaction, solution, budgetReaction) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isssd", 
                $idDept, 
                $nomAction, 
                $nomReaction, 
                $solution, 
                $budgetReaction
            );
        }
        $stmt->execute();
    }
elseif (isset($_POST['validate_reactions'])) {
    if (!empty($_POST['reactions_to_validate'])) {
        // Calculer le coût total des réactions à valider
        $reactionsToValidate = $_POST['reactions_to_validate'];
        $ids = implode(',', array_map('intval', $reactionsToValidate));
        $coutTotal = $conn->query("SELECT SUM(budgetReaction) as total FROM reaction_temp WHERE id IN ($ids)")->fetch_assoc()['total'];

        // Vérifier si le budget est suffisant
        $budgetInitial = $conn->query("SELECT montantActions FROM Departement WHERE idDept = 5")->fetch_assoc()['montantActions'];
        $budgetUtilise = $conn->query("SELECT COALESCE(SUM(budgetReaction), 0) as total FROM reaction")->fetch_assoc()['total'];
        $budgetRestant = $budgetInitial - $budgetUtilise;

        if ($coutTotal <= $budgetRestant) {
            // Procéder à la validation
            foreach ($reactionsToValidate as $reactionId) {
                $stmt = $conn->prepare("SELECT * FROM reaction_temp WHERE id = ?");
                $stmt->bind_param("i", $reactionId);
                $stmt->execute();
                $reaction = $stmt->get_result()->fetch_assoc();

                if ($reaction) {
                    $stmt = $conn->prepare("INSERT INTO reaction (idDept, nomAction, nomReaction, budgetReaction, solution, dateValidation) VALUES (?, ?, ?, ?, ?, CURRENT_DATE)");
                    $stmt->bind_param("issds", 
                        $reaction['idDept'],
                        $reaction['nomAction'],
                        $reaction['nomReaction'],
                        $reaction['budgetReaction'],
                        $reaction['solution']
                    );
                    $stmt->execute();
                    $conn->query("DELETE FROM reaction_temp WHERE id = $reactionId");
                }
            }
            $_SESSION['success'] = "Les réactions ont été validées avec succès.";
        } else {
            $_SESSION['error'] = "Budget insuffisant. Il reste " . number_format($budgetRestant, 2, ',', ' ') . " € disponible.";
        }
    }
    header("Location: realisation-temp.php?dept=$idDept&periode=$idPeriode");
    exit();
}
}
if (isset($_GET['supprimer'])) {
    $table = $_GET['source'] === 'temp' ? 'temp_realisation' : 'realisation';
    $idField = $_GET['source'] === 'temp' ? 'idTempRealisation' : 'idRealisation';
    $conn->query("DELETE FROM $table WHERE $idField = {$_GET['supprimer']}");
    header("Location: realisation-temp.php?dept=$idDept&periode=$idPeriode");
    exit();
}

if (isset($_GET['delete_reaction'])) {
    $reactionId = $_GET['delete_reaction'];
    $conn->query("DELETE FROM reaction_temp WHERE id = $reactionId");
    header("Location: realisation-temp.php?dept=$idDept&periode=$idPeriode");
    exit();
}

// Récupération des données
$departements = $conn->query("SELECT * FROM Departement ORDER BY nomDept");
$currentDept = $conn->query("SELECT * FROM Departement WHERE idDept = $idDept")->fetch_assoc();
$periodes = $conn->query("SELECT * FROM periode WHERE idDept = $idDept ORDER BY annee DESC, moisDebut DESC");

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi Financier - <?= htmlspecialchars($currentDept['nomDept']) ?></title>
    <style>
        :root {
            --primary: #e83e8c;     /* Rose vif */
            --secondary: #6c3454;    /* Rose foncé */
            --success: #28a745;
            --danger: #dc3545;
            --light: #fdf2f6;       /* Rose très clair */
            --dark: #4a2137;        /* Rose très foncé */
            --warning: #ffc107;
            --pink-100: #fce7f0;    /* Nuances de rose */
            --pink-200: #fad1e3;
            --pink-300: #f8bbd8;
            --pink-400: #f48fb1;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: var(--pink-100);
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            color: white;
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .dept-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .dept-card {
            border: 2px solid var(--pink-200);
            border-radius: 8px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s;
            min-width: 200px;
            background: white;
        }
        
        .dept-card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            background-color: var(--pink-100);
        }
        
        .dept-card.active {
            border-color: var(--primary);
            background-color: var(--pink-100);
        }
        
        .dept-name {
            font-weight: bold;
            color: var(--secondary);
            margin-bottom: 5px;
        }
        
        .dept-id {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(232, 62, 140, 0.1);
            margin-bottom: 2rem;
            overflow: hidden;
            border: 1px solid var(--pink-200);
        }
        
        .card-header {
            background-color: var(--light);
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--pink-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background-color: var(--pink-100);
            font-weight: 600;
            color: var(--dark);
        }
        
        .recette {
            color: var(--success);
            font-weight: bold;
        }
        
        .depense {
            color: var(--danger);
            font-weight: bold;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge-recette {
            background-color: var(--pink-100);
            color: var(--primary);
        }
        
        .badge-depense {
            background-color: #fadbd8;
            color: var(--danger);
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary);
        }
        
        .btn-success {
            background-color: var(--success);
            color: white;
        }
        
        .btn-danger {
            background-color: var(--danger);
            color: white;
        }
        
        .btn-warning {
            background-color: var(--warning);
            color: white;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            transition: all 0.3s ease;
        }
        
        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            align-items: center;
        }
        
        select, input {
            padding: 10px;
            border-radius: 5px;
            border: 1px solid var(--pink-200);
            font-family: inherit;
        }
        
        select:focus, input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px var(--pink-100);
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #7f8c8d;
        }
        
        .totals {
            display: flex;
            justify-content: space-around;
            background-color: var(--light);
            padding: 1rem;
            border-radius: 5px;
            margin-top: 1rem;
            border: 1px solid var(--pink-200);
        }
        
        .total-box {
            text-align: center;
            padding: 0 1rem;
        }
        
        .montant {
            font-family: 'Courier New', monospace;
            font-weight: bold;
        }
        
        .period-selector {
            min-width: 250px;
        }
        
        .actions {
            display: flex;
            gap: 5px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 1rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            max-width: 500px;
            border: 2px solid var(--pink-200);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            background-color: var(--light);
            border-bottom: 1px solid var(--pink-200);
        }
        
        .modal-title {
            margin: 0;
            font-size: 1.5rem;
        }
        
        .modal-close {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .modal-close:hover {
            color: var(--danger);
        }
        
        .budget-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background-color: var(--light);
            border-radius: 5px;
            border: 1px solid var(--pink-200);
        }
        
        .budget-restant {
            font-weight: bold;
            color: var(--primary);
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .alert-success {
            background-color: var(--pink-100);
            color: var(--secondary);
            border: 1px solid var(--pink-200);
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?= $_SESSION['error'] ?>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?= $_SESSION['success'] ?>
                <?php unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <div class="header">
            <h1>Gestion des Réalisations Financières</h1>
            <p>Suivi multidépartement des prévisions et réalisations</p>
            <!-- <p><a href="tableau.php">voir statistiques</a></p> -->
             <a href="tableau.php" class="btn btn-primary">
                <i class="fas fa-chart-bar"></i> Voir statistiques
            </a>
            <a href="etudeBudgetaireGlobale.php" class="btn btn-primary">
                <i class="fas fa-chart-bar"></i> Voir Étude Budgétaire Globale
            </a>
        </div>

        <?php
        // Calculer le budget restant
        $budgetInitial = $conn->query("SELECT montantActions FROM Departement WHERE idDept = 5")->fetch_assoc()['montantActions'];
        $budgetUtilise = $conn->query("SELECT COALESCE(SUM(budgetReaction), 0) as total FROM reaction")->fetch_assoc()['total'];
        $budgetRestant = $budgetInitial - $budgetUtilise;
        ?>
        <div class="card">
            <div class="card-header">
                <h3>Budget Actions</h3>
            </div>
            <div class="card-body">
                <div class="budget-info">
                    <div>Budget Initial : <?= number_format($budgetInitial, 2, ',', ' ') ?> €</div>
                    <div>Budget Utilisé : <?= number_format($budgetUtilise, 2, ',', ' ') ?> €</div>
                    <div class="budget-restant">Budget Restant : <?= number_format($budgetRestant, 2, ',', ' ') ?> €</div>
                </div>
            </div>
        </div>
        
        <!-- Sélecteur de département -->
        <div class="dept-selector">
            <?php while($dept = $departements->fetch_assoc()): ?>
                <?php if($dept['idDept']!=5) { ?>
            <div class="dept-card <?= $idDept == $dept['idDept'] ? 'active' : '' ?>" 
                 onclick="window.location='realisation-temp.php?dept=<?= $dept['idDept'] ?>'">
               
                <div class="dept-name"><?= htmlspecialchars($dept['nomDept']) ?></div>
            
            </div>
            <?php  } ?>

            <?php endwhile; ?>
        </div>
        
        <!-- Carte du département sélectionné -->
        <div class="card">
            <div class="card-header">
                <h2 style="margin: 0;"><?= htmlspecialchars($currentDept['nomDept']) ?></h2>
                <div class="period-selector">
                    <select onchange="window.location.href='realisation-temp.php?dept=<?= $idDept ?>&periode='+this.value">
                        <option value="">Toutes périodes</option>
                        <?php 
                        $periodes->data_seek(0);
                        while($periode = $periodes->fetch_assoc()): 
                            $debut = DateTime::createFromFormat('!m', $periode['moisDebut'])->format('F');
                            $fin = DateTime::createFromFormat('!m', $periode['moisFin'])->format('F');
                            $selected = $periode['idPeriode'] == $idPeriode ? 'selected' : '';
                        ?>
                        <option value="<?= $periode['idPeriode'] ?>" <?= $selected ?>>
                            <?= "$debut-$fin {$periode['annee']}" ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            
            <!-- Carte des réalisations temporaires -->
            <div class="card">
                <div class="card-header">
                    <h3 style="margin: 0;">Saisie temporaire</h3>
                    <?php if($idPeriode): ?>
                    <form method="POST" style="margin:0">
                        <input type="hidden" name="idDept" value="<?= $idDept ?>">
                        <input type="hidden" name="idPeriode" value="<?= $idPeriode ?>">
                        <button type="submit" name="valider_periode" class="btn btn-success">
                            Valider cette période
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php
                    $sql = "SELECT t.*, p.moisDebut, p.moisFin, p.annee 
                            FROM temp_realisation t
                            JOIN periode p ON t.idPeriode = p.idPeriode
                            WHERE t.idDept = $idDept";
                    
                    if($idPeriode) $sql .= " AND t.idPeriode = $idPeriode";
                    
                    $temp = $conn->query($sql);
                    ?>
                    
                    <?php if($temp->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Période</th>
                                <th>Libellé</th>
                                <th>Type</th>
                                <th>Montant</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalTempRecettes = 0;
                            $totalTempDepenses = 0;
                            
                            while($row = $temp->fetch_assoc()): 
                                $debut = DateTime::createFromFormat('!m', $row['moisDebut'])->format('M');
                                $fin = DateTime::createFromFormat('!m', $row['moisFin'])->format('M');
                                $isRecette = $row['idType'] == 1;
                                
                                if($isRecette) $totalTempRecettes += $row['montant'];
                                else $totalTempDepenses += $row['montant'];
                            ?>
                            <tr>
                                <td><?= "$debut-$fin {$row['annee']}" ?></td>
                                <td><?= htmlspecialchars($row['nomRealisation']) ?></td>
                                <td>
                                    <span class="badge <?= $isRecette ? 'badge-recette' : 'badge-depense' ?>">
                                        <?= $isRecette ? 'Recette' : 'Dépense' ?>
                                    </span>
                                </td>
                                <td class="montant <?= $isRecette ? 'recette' : 'depense' ?>">
                                    <?= number_format($row['montant'], 2, ',', ' ') ?> €
                                </td>
                                <td class="actions">
                                    <button onclick="openEditModal(
                                        <?= $row['idTempRealisation'] ?>, 
                                        'temp', 
                                        <?= $row['montant'] ?>
                                    )" class="btn btn-warning btn-sm">Modifier</button>
                                    
                                    <a href="realisation-temp.php?supprimer=<?= $row['idTempRealisation'] ?>&source=temp&dept=<?= $idDept ?>&periode=<?= $idPeriode ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Supprimer cette réalisation temporaire ?')">Supprimer</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    
                    <div class="totals">
                        <div class="total-box">
                            <div>Total Recettes</div>
                            <div class="montant recette"><?= number_format($totalTempRecettes, 2, ',', ' ') ?> €</div>
                        </div>
                        <div class="total-box">
                            <div>Total Dépenses</div>
                            <div class="montant depense"><?= number_format($totalTempDepenses, 2, ',', ' ') ?> €</div>
                        </div>
                        <div class="total-box">
                            <div>Solde</div>
                            <div class="montant" style="color: <?= ($totalTempRecettes - $totalTempDepenses) >= 0 ? 'var(--success)' : 'var(--danger)' ?>">
                                <?= number_format($totalTempRecettes - $totalTempDepenses, 2, ',', ' ') ?> €
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        Aucune saisie temporaire pour cette sélection
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Carte des réalisations validées -->
            <div class="card">
                <div class="card-header">
                    <h3 style="margin: 0;">Réalisations validées</h3>
                </div>
                <div class="card-body">
                    <?php
                    $sql = "SELECT r.*, p.moisDebut, p.moisFin, p.annee 
                            FROM realisation r
                            JOIN periode p ON r.idPeriode = p.idPeriode
                            WHERE r.idDept = $idDept";
                    
                    if($idPeriode) $sql .= " AND r.idPeriode = $idPeriode";
                    
                    $validees = $conn->query($sql);
                    ?>
                    
                    <?php if($validees->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Période</th>
                                <th>Libellé</th>
                                <th>Type</th>
                                <th>Montant</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalValidRecettes = 0;
                            $totalValidDepenses = 0;
                            
                            while($row = $validees->fetch_assoc()): 
                                $debut = DateTime::createFromFormat('!m', $row['moisDebut'])->format('M');
                                $fin = DateTime::createFromFormat('!m', $row['moisFin'])->format('M');
                                $isRecette = $row['idType'] == 1;
                                
                                if($isRecette) $totalValidRecettes += $row['montant'];
                                else $totalValidDepenses += $row['montant'];
                            ?>
                            <tr>
                                <td><?= "$debut-$fin {$row['annee']}" ?></td>
                                <td><?= htmlspecialchars($row['nomRealisation']) ?></td>
                                <td>
                                    <span class="badge <?= $isRecette ? 'badge-recette' : 'badge-depense' ?>">
                                        <?= $isRecette ? 'Recette' : 'Dépense' ?>
                                    </span>
                                </td>
                                <td class="montant <?= $isRecette ? 'recette' : 'depense' ?>">
                                    <?= number_format($row['montant'], 2, ',', ' ') ?> €
                                </td>
                                <td class="actions">
                                    <button onclick="openEditModal(
                                        <?= $row['idRealisation'] ?>, 
                                        'valid', 
                                        <?= $row['montant'] ?>
                                    )" class="btn btn-warning btn-sm">Modifier</button>
                                    
                                    <a href="realisation-temp.php?supprimer=<?= $row['idRealisation'] ?>&source=valid&dept=<?= $idDept ?>&periode=<?= $idPeriode ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Supprimer définitivement cette réalisation ?')">Supprimer</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    
                    <div class="totals">
                        <div class="total-box">
                            <div>Total Recettes</div>
                            <div class="montant recette"><?= number_format($totalValidRecettes, 2, ',', ' ') ?> €</div>
                        </div>
                        <div class="total-box">
                            <div>Total Dépenses</div>
                            <div class="montant depense"><?= number_format($totalValidDepenses, 2, ',', ' ') ?> €</div>
                        </div>
                        <div class="total-box">
                            <div>Solde</div>
                            <div class="montant" style="color: <?= ($totalValidRecettes - $totalValidDepenses) >= 0 ? 'var(--success)' : 'var(--danger)' ?>">
                                <?= number_format($totalValidRecettes - $totalValidDepenses, 2, ',', ' ') ?> €
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        Aucune réalisation validée pour cette sélection
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Carte des réactions temporaires -->
            <div class="card">
                <div class="card-header">
                    <h3>Gestion des Réactions</h3>
                    <button class="btn btn-primary" onclick="openReactionModal()">Ajouter une Réaction</button>
                </div>
                <div class="card-body">
                    <?php
                    $sql = "SELECT rt.*, d.nomDept 
                            FROM reaction_temp rt 
                            JOIN departement d ON rt.idDept = d.idDept 
                            ORDER BY rt.id DESC";
                    $reactions = $conn->query($sql);
                    
                    if($reactions->num_rows > 0):
                    ?>
                    <form method="POST" id="validationForm">
                        <table>
                            <thead>
                                <tr>
                                    <th>Département</th>
                                    <th>Description</th>
                                    <th>Solution</th>
                                    <th>Coût</th>
                                    <!-- <th>Actions</th> -->
                                    <th>Valider</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $totalCost = 0;
                                while($reaction = $reactions->fetch_assoc()): 
                                    $totalCost += $reaction['budgetReaction'];
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($reaction['nomDept']) ?></td>
                                    <td><?= htmlspecialchars($reaction['nomAction']) ?></td>

                                    <td><?= htmlspecialchars($reaction['nomReaction']) ?></td>
                                    
                                    <td class="montant"><?= number_format($reaction['budgetReaction'], 2, ',', ' ') ?> €</td>
                                    <td class="actions">

<button type="button" class="btn btn-warning btn-sm" 
    onclick="openReactionEditModal(
        <?= $reaction['id'] ?>, 
        '<?= addslashes($reaction['nomAction']) ?>', 
        '<?= addslashes($reaction['nomReaction']) ?>', 
        '<?= addslashes($reaction['nomReaction'] ?? '') ?>', 
        <?= $reaction['budgetReaction'] ?>
    )">
    Modifier
</button>
                                        <a href="?delete_reaction=<?= $reaction['id'] ?>" 
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('Supprimer cette réaction ?')">
                                            Supprimer
                                        </a>
                                    </td>
                                    <td>
                                        <input type="checkbox" name="reactions_to_validate[]" value="<?= $reaction['id'] ?>">
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                        <div style="margin-top: 20px; text-align: right;">
                            <button type="submit" name="validate_reactions" class="btn btn-success">
                                Valider les réactions sélectionnées
                            </button>
                        </div>
                    </form>
                    <?php else: ?>
                        <div class="empty-state">Aucune réaction proposée</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Formulaire d'ajout -->
            <div class="card">
                <div class="card-header">
                    <h3 style="margin: 0;">Ajouter une réalisation</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="idDept" value="<?= $idDept ?>">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Période :</label>
                                <select name="idPeriode" required>
                                    <?php 
                                    $periodes->data_seek(0);
                                    while($periode = $periodes->fetch_assoc()): 
                                        $debut = DateTime::createFromFormat('!m', $periode['moisDebut'])->format('F');
                                        $fin = DateTime::createFromFormat('!m', $periode['moisFin'])->format('F');
                                    ?>
                                    <option value="<?= $periode['idPeriode'] ?>" <?= $periode['idPeriode'] == $idPeriode ? 'selected' : '' ?>>
                                        <?= "$debut-$fin {$periode['annee']}" ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Libellé :</label>
                                <input type="text" name="nomRealisation" value="" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Type :</label>
                                <select name="idType" required>
                                    <option value="1">Recette</option>
                                    <option value="2">Dépense</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Montant (€) :</label>
                                <input type="number" step="0.01" min="0" name="montant" required>
                            </div>
                        </div>
                        
                        <button type="submit" name="ajouter" class="btn btn-primary">
                            Enregistrer la réalisation
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modale de modification -->
    <div id="editModal" class="modal" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Modifier le montant</h3>
                <span class="modal-close" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST" id="editForm">
                <input type="hidden" name="id" id="editId">
                <input type="hidden" name="source" id="editSource">
                
                <div class="form-group">
                    <label>Nouveau montant (€) :</label>
                    <input type="number" step="0.01" min="0" name="montant" id="editMontant" required style="width:100%">
                </div>
                
                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                    <button type="button" onclick="closeEditModal()" class="btn btn-danger">Annuler</button>
                    <button type="submit" name="modifier" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal d'ajout/modification de réaction -->
    <div id="reactionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Ajouter une Réaction</h3>
                <span class="modal-close" onclick="closeReactionModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="reaction_id" id="editReactionId">
                <input type="hidden" name="nomAction" id="editReactionAction">
                <div class="form-group">
                    <label>Département :</label>
                    <select name="idDept" required>
                        <?php 
                        $departements->data_seek(0);
                        while($dept = $departements->fetch_assoc()): 
                            if($dept['idDept'] != 5):
                        ?>
                            <option value="<?= $dept['idDept'] ?>"><?= htmlspecialchars($dept['nomDept']) ?></option>
                        <?php 
                            endif;
                        endwhile; 
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Description :</label>
                    <input type="text" name="nomReaction" id="editReactionNom" required>
                </div>
                <div class="form-group">
                    <label>Solution proposée :</label>
                    <input type="text" name="solution" id="editReactionSolution" required>
                </div>
                <div class="form-group">
                    <label>Coût :</label>
                    <input type="number" step="0.01" name="budgetReaction" id="editReactionBudget" required>
                </div>
                <div class="form-actions">
                    <button type="button" onclick="closeReactionModal()" class="btn btn-danger">Annuler</button>
                    <button type="submit" name="submit_reaction" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Fonctions pour gérer la modale
        function openEditModal(id, source, montant) {
            document.getElementById('editId').value = id;
            document.getElementById('editSource').value = source;
            document.getElementById('editMontant').value = montant;
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Fermer la modale si on clique en dehors
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeEditModal();
            }
        }

        function openReactionModal() {
            document.getElementById('reactionModal').style.display = 'block';
        }

        function closeReactionModal() {
            document.getElementById('reactionModal').style.display = 'none';
        }

        function openReactionEditModal(id, action, reaction, solution, budget) {
            document.getElementById('editReactionId').value = id;
            document.getElementById('editReactionNom').value = reaction;
            document.getElementById('editReactionAction').value = action;
            document.getElementById('editReactionSolution').value = solution;
            document.getElementById('editReactionBudget').value = budget;
            openReactionModal();
        }
    </script>
</body>
</html>