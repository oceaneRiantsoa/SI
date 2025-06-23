<?php
include 'connexion.php';
$conn = $bdd;

function createRealisation($idDept, $idPeriode, $nomPrevision, $idType, $montant)
{
    global $conn;
    $stmt = $conn->prepare("INSERT INTO temp_realisation (idDept, idPeriode, nomRealisation, idType, montant) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisid", $idDept, $idPeriode, $nomPrevision, $idType, $montant);
    $stmt->execute();
    $stmt->close();
}

// Fonction pour lire une prévision par ID
function readPrevision($idPrevision)
{
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM realisation WHERE idRealisation = ?");
    $stmt->bind_param("i", $idPrevision);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    return $result->fetch_assoc();
}

// Fonction pour récupérer toutes les prévisions
function readAllPrevisions()
{
    global $conn;
    $result = $conn->query("SELECT * FROM realisation");
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Fonction pour mettre à jour une prévision
function updatePrevision($idPrevision, $idDept, $idPeriode, $nomPrevision, $idType, $montant)
{
    global $conn;
    $stmt = $conn->prepare("UPDATE realisation SET idDept=?, idPeriode=?, nomPrevision=?, idType=?, montant=? WHERE idRealisation=?");
    $stmt->bind_param("iisidi", $idDept, $idPeriode, $nomPrevision, $idType, $montant, $idPrevision);
    $stmt->execute();
    $stmt->close();
}

// Fonction pour supprimer une prévision
function deletePrevision($idPrevision)
{
    global $conn;
    $stmt = $conn->prepare("DELETE FROM realisation WHERE idRealisation = ?");
    $stmt->bind_param("i", $idPrevision);
    $stmt->execute();
    $stmt->close();
}

// Fermer la connexion à la fin du script
// $conn->close();
?>