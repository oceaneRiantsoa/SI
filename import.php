<?php
if(isset($_POST['import'])) {
    $conn = new mysqli('localhost', 'root', '', 'SI');
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Modifier le tableau pour n'inclure que les fichiers souhaités
    $csvFiles = [
        'departement' => 'dep.csv',
        'periode' => 'peri.csv',
        'prevision' => 'prev.csv',
        'temp_realisation' => 'tempreal.csv'
    ];

    $messages = [];

    foreach($csvFiles as $table => $file) {
        if(file_exists($file)) {
            $handle = fopen($file, "r");
            $firstLine = true;
            
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if($firstLine) {
                    $firstLine = false;
                    continue;
                }
                
                // Construire la requête INSERT en fonction de la table
                switch($table) {
                    case 'departement':
                        $query = "INSERT INTO departement (idDept, nomDept, mdp) VALUES (?, ?, ?)";
                        break;
                    case 'periode':
                        $query = "INSERT INTO periode (idDept, moisDebut, moisFin, annee) VALUES (?, ?, ?, ?)";
                        break;
                    case 'prevision':
                        $query = "INSERT INTO prevision (idDept, idPeriode, nomPrevision, idType, montant) VALUES (?, ?, ?, ?, ?)";
                        break;
                    case 'temp_realisation':
                        $query = "INSERT INTO temp_realisation (idDept, idPeriode, nomRealisation, idType, montant) VALUES (?, ?, ?, ?, ?)";
                        break;
                }

                $stmt = $conn->prepare($query);
                if($stmt) {
                    switch($table) {
                        case 'departement':
                            $stmt->bind_param("iss", $data[0], $data[1], $data[2]);
                            break;
                        case 'periode':
                            $stmt->bind_param("iiii", $data[0], $data[1], $data[2], $data[3]);
                            break;
                        case 'prevision':
                        case 'temp_realisation':
                            $stmt->bind_param("iisid", $data[0], $data[1], $data[2], $data[3], $data[4]);
                            break;
                    }
                    
                    if($stmt->execute()) {
                        if(!isset($messages[$table])) {
                            $messages[$table] = "Table $table : Importation réussie";
                        }
                    } else {
                        $messages[$table] = "Table $table : Erreur lors de l'importation - " . $stmt->error;
                    }
                    $stmt->close();
                }
            }
            fclose($handle);
        } else {
            $messages[$table] = "Le fichier $file n'existe pas";
        }
    }
    
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import CSV</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
        }
        .import-btn {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .import-btn:hover {
            background-color: #45a049;
        }
        .message {
            margin-top: 20px;
            padding: 10px;
            border-radius: 4px;
        }
        .success {
            background-color: #dff0d8;
            color: #3c763d;
        }
        .error {
            background-color: #f2dede;
            color: #a94442;
        }
    </style>
</head>
<body>
    <h1>Importation des données CSV</h1>
    
    <form method="post">
        <button type="submit" name="import" class="import-btn">Importer tous les fichiers CSV</button>
    </form>

    <?php if(isset($messages)): ?>
        <div class="message">
            <?php foreach($messages as $msg): ?>
                <p class="<?php echo strpos($msg, 'réussie') ? 'success' : 'error'; ?>">
                    <?php echo htmlspecialchars($msg); ?>
                </p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</body>
</html>