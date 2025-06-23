<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion par Département</title>
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--pink-100);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .login-container {
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 8px 16px rgba(232, 62, 140, 0.1);
            width: 320px;
            border: 1px solid var(--pink-200);
        }

        h2 {
            text-align: center;
            color: var(--secondary);
            margin-bottom: 25px;
            font-size: 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--secondary);
        }

        select, input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 2px solid var(--pink-200);
            border-radius: 6px;
            box-sizing: border-box;
            transition: all 0.3s ease;
        }

        select:focus, input[type="password"]:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--pink-100);
        }

        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        button:hover {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(232, 62, 140, 0.2);
        }

        .error {
            color: var(--danger);
            text-align: center;
            margin-top: 15px;
            padding: 10px;
            background-color: #fff5f6;
            border: 1px solid var(--danger);
            border-radius: 4px;
        }

        a {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: var(--primary);
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        a:hover {
            color: var(--secondary);
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Connexion Département</h2>
        
        <?php
        // Vérification si le formulaire a été soumis
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            // Connexion à la base de données
            $conn = new mysqli('localhost', 'root', '', 'SI');
            
            // Vérification des erreurs de connexion
            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }
            
            // Récupération des données du formulaire
            $idDept = $_POST['departement'];
            $mdp = $_POST['password'];
            
            // Requête pour vérifier les identifiants
            $sql = "SELECT * FROM departement WHERE idDept = ? AND mdp = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $idDept, $mdp);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Authentification réussie
                session_start();
                $row = $result->fetch_assoc();
                $_SESSION['idDept'] = $row['idDept'];
                $_SESSION['nomDept'] = $row['nomDept'];
                
                // Redirection vers la page d'accueil du département
                header("Location: choix.php");
                exit();
            } else {
                // Authentification échouée
                echo '<p class="error">Département ou mot de passe incorrect</p>';
            }
            
            $stmt->close();
            $conn->close();
        }
        ?>
        
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="form-group">
                <label for="departement">Département:</label>
                <select id="departement" name="departement" required>
                    <option value="">-- Sélectionnez un département --</option>
                    <?php
                    // Connexion à la base de données
                    $conn = new mysqli('localhost', 'root', '', 'SI');
                    
                    // Vérification des erreurs de connexion
                    if ($conn->connect_error) {
                        die("Connection failed: " . $conn->connect_error);
                    }
                    
                    // Requête pour récupérer tous les départements
                    $sql = "SELECT idDept, nomDept FROM departement ORDER BY nomDept";
                    $result = $conn->query($sql);
                    
                    // Affichage des options
                    if ($result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            echo '<option value="' . htmlspecialchars($row['idDept']) . '">' 
                                 . htmlspecialchars($row['nomDept']) . '</option>';
                        }
                    }
                    
                    $conn->close();
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label for="password">Mot de passe:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit">Se connecter</button>
            <a href="import.php">Importer</a>
        </form>
    </div>
</body>
</html>