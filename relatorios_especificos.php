<?php
require_once 'includes/header.php';
?>

<style>
.placeholder-container {
    text-align: center;
    padding: 50px 20px;
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,.05);
}
.placeholder-container i {
    font-size: 4em;
    color: #_PLACEHOLDER_6c757d;
    opacity: 0.5;
}
.placeholder-container h2 {
    margin-top: 20px;
    color: #2c3e50;
}
.placeholder-container p {
    color: #555;
    font-size: 1.1em;
}
</style>

<div class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1>Relatórios Específicos</h1>
        <a href="relatorios.php" style="text-decoration: none; color: #555;"><i class="fas fa-arrow-left"></i> Voltar</a>
    </div>

    <div class="placeholder-container" style="margin-top: 30px;">
        <i class="fas fa-tools"></i>
        <h2>Em Construção</h2>
        <p>Esta área será destinada à geração de relatórios personalizados no futuro.</p>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>