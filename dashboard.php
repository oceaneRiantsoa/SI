<?php
session_start();
if (!isset($_SESSION["idDept"])) {
    header("Location: login.php");
    exit();
}
?>

<h2>Bienvenue !</h2>
<a href="export_pdf.php">Exporter en PDF</a>
<a href="logout.php">Déconnexion</a>
