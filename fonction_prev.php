<?php
include 'connexion.php';
$conn = $bdd;

function createPrevision($idDept, $idPeriode, $nomPrevision, $idType, $montant)
{
    global $conn;
    $stmt = $conn->prepare("INSERT INTO prevision (idDept, idPeriode, nomPrevision, idType, montant) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisid", $idDept, $idPeriode, $nomPrevision, $idType, $montant);
    $stmt->execute();
    $stmt->close();
}

function readPrevision($idPrevision)
{
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM prevision WHERE idPrevision = ?");
    $stmt->bind_param("i", $idPrevision);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    return $result->fetch_assoc();
}

function readAllPrevisions()
{
    global $conn;
    $result = $conn->query("SELECT * FROM prevision");
    return $result->fetch_all(MYSQLI_ASSOC);
}

function updatePrevision($idPrevision, $idDept, $idPeriode, $nomPrevision, $idType, $montant)
{
    global $conn;
    $stmt = $conn->prepare("UPDATE prevision SET idDept=?, idPeriode=?, nomPrevision=?, idType=?, montant=? WHERE idPrevision=?");
    $stmt->bind_param("iisidi", $idDept, $idPeriode, $nomPrevision, $idType, $montant, $idPrevision);
    $stmt->execute();
    $stmt->close();
}

function deletePrevision($idPrevision)
{
    global $conn;
    $stmt = $conn->prepare("DELETE FROM prevision WHERE idPrevision = ?");
    $stmt->bind_param("i", $idPrevision);
    $stmt->execute();
    $stmt->close();
}

// Fonction pour récupérer une prévision par son ID (alias de readPrevision)
function getPrevisionById($idPrevision)
{
    return readPrevision($idPrevision);
}

// Fonction pour récupérer les prévisions par département
function getPrevisionsByDept($idDept)
{
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM prevision WHERE idDept = ?");
    $stmt->bind_param("i", $idDept);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Fonction pour récupérer les prévisions par période
function getPrevisionsByPeriod($idPeriode)
{
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM prevision WHERE idPeriode = ?");
    $stmt->bind_param("i", $idPeriode);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Fonction pour récupérer les prévisions par type (recette/dépense)
function getPrevisionsByType($idType)
{
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM prevision WHERE idType = ?");
    $stmt->bind_param("i", $idType);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Fonction pour calculer le total des prévisions par type et période
function getTotalByTypeAndPeriod($idType, $idPeriode)
{
    global $conn;
    $stmt = $conn->prepare("SELECT SUM(montant) as total FROM prevision WHERE idType = ? AND idPeriode = ?");
    $stmt->bind_param("ii", $idType, $idPeriode);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['total'] ?? 0;
}

// Fonction pour vérifier si une prévision existe déjà
function previsionExists($idDept, $idPeriode, $nomPrevision)
{
    global $conn;
    $stmt = $conn->prepare("SELECT idPrevision FROM prevision WHERE idDept = ? AND idPeriode = ? AND nomPrevision = ?");
    $stmt->bind_param("iis", $idDept, $idPeriode, $nomPrevision);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    return $exists;
}
?>