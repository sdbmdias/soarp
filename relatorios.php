<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';
?>

<style>
.reports-menu {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-top: 20px;
}
.report-button {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 30px;
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,.05);
    text-decoration: none;
    color: #2c3e50;
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    text-align: center;
}
.report-button:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,.08);
}
.report-button i {
    font-size: 3em;
    margin-bottom: 15px;
    color: #3498db;
}
.report-button h2 {
    margin: 0;
    font-size: 1.3em;
}
.report-button p {
    margin: 5px 0 0 0;
    font-size: 0.9em;
    color: #555;
}
</style>

<div class="main-content">
    <h1>Central de Relatórios</h1>
    <p>Selecione um tipo de relatório para visualizar os dados detalhados.</p>

    <div class="reports-menu">
        <a href="relatorio_aeronaves.php" class="report-button">
            <i class="fas fa-plane-departure"></i>
            <h2>Logbook por Aeronave</h2>
            <p>Distância e tempo de voo por cada aeronave.</p>
        </a>
        <a href="relatorio_pilotos.php" class="report-button">
            <i class="fas fa-users-cog"></i>
            <h2>Logbook por Piloto</h2>
            <p>Horas de voo e distância percorrida por cada piloto.</p>
        </a>
        <a href="relatorios_especificos.php" class="report-button">
            <i class="fas fa-file-alt"></i>
            <h2>Relatórios Específicos</h2>
            <p>Gere relatórios personalizados por período e tipo.</p>
        </a>
    </div>
</div>

<?php
// INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>