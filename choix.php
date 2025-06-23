<?php
session_start();

// Vérification de la connexion
if (!isset($_SESSION['idDept']) || !isset($_SESSION['nomDept'])) {
    header("Location: login.php");
    exit();
}

// Récupération des infos de session
$idDept = $_SESSION['idDept'];
$nomDept = $_SESSION['nomDept'];

// Si c'est le département Finance (id 5), redirection directe
if ($idDept == 5) {
    header("Location: realisation-temp.php");
    exit();
}

// Connexion à la base de données
$conn = new mysqli("localhost", "root", "", "SI");

// Traitement du formulaire d'action
if(isset($_POST['submit_action'])) {
    $nomAction = $_POST['nom_action'];
    $budgetAction = $_POST['budget_action'];
    
    $stmt = $conn->prepare("INSERT INTO action_temp (idDept, nomAction, budgetAction) VALUES (?, ?, ?)");
    $stmt->bind_param("isd", $idDept, $nomAction, $budgetAction);
    $stmt->execute();
}

// Traitement du formulaire de réaction
if(isset($_POST['submit_reaction'])) {
    $nomAction = $_POST['action_concernee'];
    $nomReaction = $_POST['nom_reaction'];
    $budgetReaction = $_POST['budget_reaction'];
    // $solution = $_POST['solution'];
    
    $stmt = $conn->prepare("INSERT INTO reaction_temp (idDept, nomAction, nomReaction, budgetReaction) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("issd", $idDept, $nomAction, $nomReaction, $budgetReaction);
    $stmt->execute();
}

// Ajouter ce nouveau traitement pour le deuxième formulaire
if(isset($_POST['submit_reaction_existing'])) {
    $actionId = $_POST['action_id'];
    $nomReaction = $_POST['nom_reaction'];
    $budgetReaction = $_POST['budget_reaction'];
    
    // Récupérer le nom de l'action existante
    $actionQuery = $conn->prepare("SELECT nomAction FROM action_temp WHERE id = ?");
    $actionQuery->bind_param("i", $actionId);
    $actionQuery->execute();
    $result = $actionQuery->get_result();
    $action = $result->fetch_assoc();
    
    $stmt = $conn->prepare("INSERT INTO reaction_temp (idDept, nomAction, nomReaction, budgetReaction) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("issd", $idDept, $action['nomAction'], $nomReaction, $budgetReaction);
    $stmt->execute();
}

// Récupérer les actions temporaires
$actions_temp = $conn->query("SELECT * FROM action_temp WHERE idDept = $idDept");
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Principal - <?php echo htmlspecialchars($nomDept); ?></title>
    <style>
        :root {
            --primary: #e83e8c;     /* Rose vif */
            --secondary: #6c3454;    /* Rose foncé */
            --success: #28a745;
            --danger: #dc3545;
            --light: #fdf2f6;       /* Rose très clair */
            --background: #fce7f0;   /* Fond rose pâle */
            --warning: #ffc107;
            --pink-100: #fce7f0;    /* Nuances de rose */
            --pink-200: #fad1e3;
            --pink-300: #f8bbd8;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--background);
            margin: 0;
            padding: 0;
            color: #333;
        }
        
        .bank-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
        }
        
        .bank-header {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            color: white;
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(232, 62, 140, 0.1);
            text-align: center;
        }
        
        .bank-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .bank-card-header {
            background-color: var(--light);
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #eee;
        }
        
        .bank-card-body {
            padding: 2rem;
        }
        
        .welcome-message {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .dept-name {
            font-weight: bold;
            color: var(--primary);
        }
        
        .btn-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .btn-container-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
        }
        
        .bank-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            border-radius: 8px;
            background: white;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--dark);
            border: 1px solid var(--pink-200);
            height: 150px;
        }
        
        .bank-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(232, 62, 140, 0.15);
            border-color: var(--primary);
        }
        
        .bank-btn i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--primary);
        }
        
        .bank-btn-prevision {
            border-top: 4px solid var(--primary);
        }
        
        .bank-btn-prevision:hover {
            background-color: var(--pink-100);
        }
        
        .bank-btn-realisation {
            border-top: 4px solid var(--secondary);
        }
        
        .bank-btn-realisation:hover {
            background-color: var(--pink-100);
        }
        
        .bank-btn-etude {
            border-top: 4px solid var(--pink-300);
        }
        
        .bank-btn-etude:hover {
            background-color: var(--pink-100);
        }
        
        .btn-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .btn-desc {
            font-size: 0.9rem;
            color: #7f8c8d;
            text-align: center;
        }
        
        .bank-footer {
            text-align: center;
            margin-top: 2rem;
        }
        
        .logout-btn {
            color: var(--danger);
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
        }
        
        .logout-btn:hover {
            text-decoration: underline;
        }

        /* New styles for action/reaction modules */
        .module-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        .action-form, .reaction-form {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(232, 62, 140, 0.1);
            border: 1px solid var(--pink-200);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid var(--pink-200);
            border-radius: 4px;
        }
        
        .form-group input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--pink-100);
            outline: none;
        }
        
        .reaction-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--pink-200);
        }
        
        select.action-select {
            width: 100%;
            padding: 8px;
            border: 1px solid var(--pink-200);
            border-radius: 4px;
            margin-bottom: 15px;
        }
        
        select.action-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--pink-100);
            outline: none;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="bank-container">
        <div class="bank-header">
            <h1>Tableau de Bord Financier</h1>
        </div>
        
        <div class="bank-card">
            <div class="bank-card-body">
                <div class="welcome-message">
                    Bienvenue, <span class="dept-name"><?php echo htmlspecialchars($nomDept); ?></span>
                    <a href="tableau.php">statistiques</a>
                </div>
                
                <div class="btn-container-3">
                    <a href="prevision.php" class="bank-btn bank-btn-prevision">
                        <i class="fas fa-chart-line"></i>
                        <div class="btn-title">Prévision</div>
                        <div class="btn-desc">Gestion des prévisions budgétaires</div>
                    </a>
                    
                    <a href="realisation.php" class="bank-btn bank-btn-realisation">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <div class="btn-title">Réalisation</div>
                        <div class="btn-desc">Suivi des réalisations financières</div>
                    </a>
                    
                    <a href="etudeBudgetaire.php" class="bank-btn bank-btn-etude">
                        <i class="fas fa-search-dollar"></i>
                        <div class="btn-title">Étude Budgétaire</div>
                        <div class="btn-desc">Analyse des données budgétaires</div>
                    </a>
                </div>

                <!-- Nouveaux modules Action/Réaction -->
                <div class="module-container">
                    <!-- Module Action -->
                    <div class="action-form">
                        <h3><i class="fas fa-tasks"></i> Ajouter une Action</h3>
                        <form method="POST">
                            <div class="form-group">
                                <label>Description de l'action :</label>
                                <input type="text" name="nom_action" required>
                            </div>
                            <div class="form-group">
                                <!-- <label>Coût de l'action :</label> -->
                                <input type="hidden" name="budget_action" step="0.01" values=1>
                            </div>
                            <button type="submit" name="submit_action" class="bank-btn">Ajouter l'action</button>
                        </form>
                    </div>

                   <!-- Module Réaction -->
<div class="reaction-form">
    <h3><i class="fas fa-reply"></i> Ajouter une Réaction</h3>
    
    <!-- Partie 1: Nouvelle réaction avec nouvelle action -->
    <form method="POST">
        <div class="form-group">
            <label>Action concernée :</label>
            <input type="text" name="action_concernee" required placeholder="Décrire l'action qui nécessite une réaction">
        </div>
        <div class="form-group">
            <label>Description de la réaction :</label>
            <input type="text" name="nom_reaction" required placeholder="Décrire la réaction proposée">
        </div>
        <div class="form-group">
            <label>Coût de la réaction :</label>
            <input type="number" name="budget_reaction" step="0.01" required>
        </div>
        <button type="submit" name="submit_reaction" class="bank-btn">Ajouter la réaction</button>
    </form>

    <!-- Partie 2: Réaction à une action existante -->
    <div class="reaction-section">
        <h4>Réagir à une action existante</h4>
        <form method="POST">
            <div class="form-group">
                <label>Sélectionner une action existante :</label>
                <select name="action_id" class="action-select" required>
                    <?php 
                    $actions_temp = $conn->query("SELECT * FROM action_temp WHERE idDept = $idDept");
                    while($action = $actions_temp->fetch_assoc()): 
                    ?>
                        <option value="<?= $action['id'] ?>"><?= htmlspecialchars($action['nomAction']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Description de la réaction :</label>
                <input type="text" name="nom_reaction" required placeholder="Décrire la réaction proposée">
            </div>
            <div class="form-group">
                <label>Coût de la réaction :</label>
                <input type="number" name="budget_reaction" step="0.01" required>
            </div>
            <button type="submit" name="submit_reaction_existing" class="bank-btn">Ajouter la réaction</button>
        </form>
    </div>
</div>
                </div>
            </div>
        </div>
        
        <div class="bank-footer">
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Déconnexion
            </a>
        </div>
    </div>
</body>
</html>