<?php
include 'fonction_prev.php';
include 'fonction_periode.php';
$idDept = 1;

// Connexion à la base de données
$conn = new mysqli("localhost", "root", "", "SI");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission for add
if (isset($_POST['ajouter'])) {
    $idDept = $_POST['idDept'];
    $idPeriode = $_POST['idPeriode'];
    $nomPrevision = $_POST['nomPrevision'];
    $idType = $_POST['idType'];
    $montant = $_POST['montant'];

    createPrevision($idDept, $idPeriode, $nomPrevision, $idType, $montant);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_POST['addPeriode'])) {
    $idDept = $_POST['idDept'];
    $moisDebut = $_POST['moisDebut'];
    $moisFin = $_POST['moisFin'];
    $annee = $_POST['annee'];

    createPeriode($idDept, $moisDebut, $moisFin, $annee);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle form submission for edit
if (isset($_POST['modifier'])) {
    $idPrevision = $_POST['idPrevision'];
    $idDept = $_POST['idDept'];
    $idPeriode = $_POST['idPeriode'];
    $nomPrevision = $_POST['nomPrevision'];
    $idType = $_POST['idType'];
    $montant = $_POST['montant'];

    updatePrevision($idPrevision, $idDept, $idPeriode, $nomPrevision, $idType, $montant);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle deletion
if (isset($_GET['delete'])) {
    deletePrevision($_GET['delete']);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle edit form population
$editData = null;
if (isset($_GET['edit'])) {
    $editData = getPrevisionById($_GET['edit']);
}

// Handle CSV import
if (isset($_POST['importCSV'])) {
    $idDept = $_POST['idDept'];
    
    if (isset($_FILES['csvFile']) && $_FILES['csvFile']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['csvFile']['tmp_name'];
        
        // Check file extension
        $fileExt = pathinfo($_FILES['csvFile']['name'], PATHINFO_EXTENSION);
        if (strtolower($fileExt) !== 'csv') {
            die("Le fichier doit être au format CSV.");
        }
        
        if (($handle = fopen($file, "r")) !== FALSE) {
            // Skip header row
            fgetcsv($handle, 1000, ",");
            
            // Database connection
            $conn = new mysqli("localhost", "root", "", "SI");
            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }
            
            // Prepare statements
            $stmtPeriode = $conn->prepare("INSERT INTO periode (idDept, moisDebut, moisFin, annee) VALUES (?, ?, ?, ?)");
            $stmtPrevision = $conn->prepare("INSERT INTO prevision (idDept, idPeriode, nomPrevision, idType, montant) VALUES (?, ?, ?, ?, ?)");
            
            // Read file line by line
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // Process period
                $moisDebut = $data[0];
                $moisFin = $data[1];
                $annee = $data[2];
                
                // Check if period exists
                $checkPeriode = $conn->prepare("SELECT idPeriode FROM periode WHERE idDept = ? AND moisDebut = ? AND moisFin = ? AND annee = ?");
                $checkPeriode->bind_param("iiii", $idDept, $moisDebut, $moisFin, $annee);
                $checkPeriode->execute();
                $result = $checkPeriode->get_result();
                
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $idPeriode = $row['idPeriode'];
                } else {
                    // Insert new period
                    $stmtPeriode->bind_param("iiii", $idDept, $moisDebut, $moisFin, $annee);
                    $stmtPeriode->execute();
                    $idPeriode = $conn->insert_id;
                }
                
                // Process prevision
                $nomPrevision = $data[3];
                $type = $data[4];
                $montant = $data[5];
                
                // Determine idType
                $idType = (strtolower(trim($type)) == "recette") ? 1 : 2;
                
                // Insert prevision
                $stmtPrevision->bind_param("iisid", $idDept, $idPeriode, $nomPrevision, $idType, $montant);
                $stmtPrevision->execute();
            }
            
            // Close connections
            fclose($handle);
            $conn->close();
            
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    } else {
        die("Erreur lors du téléchargement du fichier.");
    }
}

// Récupérer les prévisions avec les détails des périodes
$previsions = array();
$result = $conn->query("
    SELECT p.*, per.moisDebut, per.moisFin, per.annee 
    FROM prevision p
    JOIN periode per ON p.idPeriode = per.idPeriode
    ORDER BY per.annee DESC, per.moisDebut DESC
");
if ($result) {
    $previsions = $result->fetch_all(MYSQLI_ASSOC);
}

// Créer un tableau associatif pour regrouper les périodes avec leurs infos
$periodesDetails = array();
foreach ($previsions as $prev) {
    $periodId = $prev['idPeriode'];
    if (!isset($periodesDetails[$periodId])) {
        $periodesDetails[$periodId] = array(
            'moisDebut' => $prev['moisDebut'],
            'moisFin' => $prev['moisFin'],
            'annee' => $prev['annee']
        );
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Prévisions</title>
    <style>
        :root {
            --primary: #3498db;
            --secondary: #2c3e50;
            --success: #2ecc71;
            --danger: #e74c3c;
            --light: #ecf0f1;
            --dark: #34495e;
            --warning: #f39c12;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f9f9f9;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            color: white;
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        h1 {
            margin: 0;
            color: var(--secondary);
        }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .card-header {
            background-color: var(--light);
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #eee;
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
            background-color: var(--light);
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
            background-color: #d5f5e3;
            color: var(--success);
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
            text-decoration: none;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
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
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        select, input, textarea {
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ddd;
            font-family: inherit;
            width: 100%;
            box-sizing: border-box;
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
        }
        
        .total-box {
            text-align: center;
            padding: 0 1rem;
        }
        
        .montant {
            font-family: 'Courier New', monospace;
            font-weight: bold;
        }
        
        .period-section {
            margin-top: 2rem;
        }
        
        .period-title {
            color: var(--secondary);
            border-bottom: 2px solid var(--secondary);
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .tables-container {
            display: flex;
            gap: 20px;
            margin-bottom: 1rem;
        }
        
        .table-section {
            flex: 1;
            background: white;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .table-title {
            color: var(--dark);
            margin-top: 0;
        }
        
        .add-btn-container {
            display: flex;
            justify-content: flex-end;
            margin-top: 1rem;
        }
        
        /* Styles pour les modales */
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
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .modal-title {
            margin: 0;
            font-size: 1.5rem;
            color: var(--secondary);
        }
        
        .modal-close {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
        }
        
        .modal-close:hover {
            color: var(--danger);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .tables-container {
                flex-direction: column;
            }
            
            .modal-content {
                width: 90%;
                margin: 10% auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Gestion des Prévisions</h1>
        </div>
        
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="openPeriodeModal()">Ajouter Periode</button>
            <button class="btn btn-primary" onclick="openModal()">Ajouter Prevision</button>
            <button class="btn btn-warning" onclick="openImportModal()">Importer CSV</button>
        </div>

        <?php
        foreach ($periodesDetails as $periodId => $details) {
            $moisDebut = DateTime::createFromFormat('!m', $details['moisDebut'])->format('F');
            $moisFin = DateTime::createFromFormat('!m', $details['moisFin'])->format('F');
            $annee = $details['annee'];
            
            echo "<div class='period-section'>";
            echo "<h2 class='period-title'>Période $moisDebut - $moisFin $annee</h2>";

            echo "<div class='tables-container'>";

            // Section Recettes
            echo "<div class='table-section'>";
            echo "<h3 class='table-title'>Recettes</h3>";
            echo "<table>
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Montant</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>";

            foreach ($previsions as $prevision) {
                if ($prevision['idPeriode'] == $periodId && $prevision['idType'] == 1) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($prevision['nomPrevision']) . "</td>";
                    echo "<td class='montant recette'>" . number_format($prevision['montant'], 2, ',', ' ') . " €</td>";
                    echo "<td class='actions'>
                            <button class='btn btn-warning btn-sm' onclick=\"openEditModal(" . $prevision['idPrevision'] . ")\">Modifier</button>
                            <button class='btn btn-danger btn-sm' onclick=\"deletePrevision(" . $prevision['idPrevision'] . ")\">Supprimer</button>
                          </td>";
                    echo "</tr>";
                }
            }
            echo "</tbody></table>";
            echo "</div>";

            // Section Dépenses
            echo "<div class='table-section'>";
            echo "<h3 class='table-title'>Dépenses</h3>";
            echo "<table>
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Montant</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>";

            foreach ($previsions as $prevision) {
                if ($prevision['idPeriode'] == $periodId && $prevision['idType'] == 2) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($prevision['nomPrevision']) . "</td>";
                    echo "<td class='montant depense'>" . number_format($prevision['montant'], 2, ',', ' ') . " €</td>";
                    echo "<td class='actions'>
                            <button class='btn btn-warning btn-sm' onclick=\"openEditModal(" . $prevision['idPrevision'] . ")\">Modifier</button>
                            <button class='btn btn-danger btn-sm' onclick=\"deletePrevision(" . $prevision['idPrevision'] . ")\">Supprimer</button>
                          </td>";
                    echo "</tr>";
                }
            }
            echo "</tbody></table>";
            echo "</div>";

            echo "</div>"; // Fin tables-container

            echo "<div class='add-btn-container'>";
            echo "<button class='btn btn-primary' onclick='openModal($periodId)'>Ajouter Prévision</button>";
            echo "</div>";

            echo "</div>"; // Fin period-section
        }
        ?>
    </div>

    <!-- Modal pour les prévisions -->
    <div id="modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><?= isset($editData) ? 'Modifier' : 'Ajouter' ?> une Prévision</h3>
                <span class="modal-close" onclick="closeModal()">&times;</span>
            </div>
            <form action="" method="POST">
                <input type="hidden" id="idPrevision" name="idPrevision" value="<?= isset($editData) ? $editData['idPrevision'] : '' ?>">

                <div class="form-group">
                    <label>Nom :</label>
                    <input type="text" id="nomPrevision" name="nomPrevision" value="<?= isset($editData) ? htmlspecialchars($editData['nomPrevision']) : '' ?>" required>
                </div>

                <input type="hidden" id="idDept" name="idDept" value="<?= $idDept ?>">

                <div class="form-group">
                    <label>Période :</label>
                    <select name="idPeriode" id="idPeriode" required>
                        <?php
                        $periodes = $conn->query("SELECT * FROM periode ORDER BY annee DESC, moisDebut DESC");
                        while($periode = $periodes->fetch_assoc()): 
                            $debut = DateTime::createFromFormat('!m', $periode['moisDebut'])->format('F');
                            $fin = DateTime::createFromFormat('!m', $periode['moisFin'])->format('F');
                            $selected = (isset($editData) && $editData['idPeriode'] == $periode['idPeriode']) ? 'selected' : '';
                        ?>
                        <option value="<?= $periode['idPeriode'] ?>" <?= $selected ?>>
                            <?= "$debut - $fin {$periode['annee']}" ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Type :</label>
                    <select name="idType" id="idType" required>
                        <option value="1" <?= (isset($editData) && $editData['idType'] == 1) ? 'selected' : '' ?>>Recette</option>
                        <option value="2" <?= (isset($editData) && $editData['idType'] == 2) ? 'selected' : '' ?>>Dépense</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Montant :</label>
                    <input type="number" id="montant" step="0.01" name="montant" value="<?= isset($editData) ? $editData['montant'] : '' ?>" required>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-danger" onclick="closeModal()">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="<?= isset($editData) ? 'modifier' : 'ajouter' ?>"><?= isset($editData) ? 'Modifier' : 'Ajouter' ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal pour les périodes -->
    <div id="periodeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Ajouter une Période</h3>
                <span class="modal-close" onclick="closePeriodeModal()">&times;</span>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="idDept" value="<?= $idDept ?>">

                <div class="form-group">
                    <label>Début Mois :</label>
                    <select name="moisDebut" required>
                        <?php
                        $mois = [
                            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
                            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
                            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
                        ];
                        foreach ($mois as $num => $nom): ?>
                        <option value="<?= $num ?>"><?= $nom ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Fin Mois :</label>
                    <select name="moisFin" required>
                        <?php foreach ($mois as $num => $nom): ?>
                        <option value="<?= $num ?>" <?= $num == 12 ? 'selected' : '' ?>><?= $nom ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Année :</label>
                    <select name="annee" required>
                        <?php
                        $currentYear = date('Y');
                        for ($i = $currentYear; $i <= $currentYear + 5; $i++): ?>
                        <option value="<?= $i ?>"><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-danger" onclick="closePeriodeModal()">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="addPeriode">Ajouter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal pour l'import CSV -->
    <div id="importModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Importer des données depuis CSV</h3>
                <span class="modal-close" onclick="closeImportModal()">&times;</span>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="idDept" value="<?= $idDept ?>">
                
                <div class="form-group">
                    <label>Sélectionner un fichier CSV :</label>
                    <input type="file" name="csvFile" accept=".csv" required>
                </div>
                
                <div class="form-group">
                    <p>Format attendu : moisDebut,moisFin,annee,nomPrevision,type,montant</p>
                    <p>Exemple : <code>1,3,2023,Salaire,Recette,5000</code></p>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-danger" onclick="closeImportModal()">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="importCSV">Importer</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Au chargement de la page, ouvrir le modal si on est en mode édition
        window.onload = function() {
            <?php if (isset($editData)): ?>
                document.getElementById('modal').style.display = 'block';
            <?php endif; ?>
        };

        function openEditModal(idPrevision) {
            window.location.href = '?edit=' + idPrevision;
        }

        function openModal(idPeriode = '') {
            document.getElementById('modal').style.display = 'block';
            if (idPeriode) {
                document.getElementById('idPeriode').value = idPeriode;
            }
            document.getElementById('nomPrevision').value = '';
            document.getElementById('montant').value = '';
            document.getElementById('idType').value = '1';
            document.getElementById('modalTitle').innerText = 'Ajouter Prévision';
            document.getElementById('idPrevision').value = '';
        }

        function closeModal() {
            document.getElementById('modal').style.display = 'none';
            // Supprimer le paramètre edit de l'URL si présent
            if (window.location.href.includes('edit=')) {
                window.location.href = window.location.pathname;
            }
        }

        function openPeriodeModal() {
            document.getElementById('periodeModal').style.display = 'block';
        }

        function closePeriodeModal() {
            document.getElementById('periodeModal').style.display = 'none';
        }

        function openImportModal() {
            document.getElementById('importModal').style.display = 'block';
        }

        function closeImportModal() {
            document.getElementById('importModal').style.display = 'none';
        }

        function deletePrevision(idPrevision) {
            if (confirm("Êtes-vous sûr de vouloir supprimer cette prévision ?")) {
                window.location.href = '?delete=' + idPrevision;
            }
        }

        // Fermer la modale si on clique en dehors
        window.onclick = function(event) {
            const modals = ['modal', 'periodeModal', 'importModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target == modal) {
                    if (modalId === 'modal') closeModal();
                    if (modalId === 'periodeModal') closePeriodeModal();
                    if (modalId === 'importModal') closeImportModal();
                }
            });
        }
    </script>
</body>
</html>