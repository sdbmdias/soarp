<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

// 2. LÓGICA ESPECÍFICA DA PÁGINA
$controles = [];

$sql_base = "SELECT c.id, c.fabricante, c.modelo, c.numero_serie, c.crbm, c.obm, c.status, c.homologacao_anatel, a.prefixo AS prefixo_aeronave 
             FROM controles c 
             LEFT JOIN aeronaves a ON c.aeronave_id = a.id";

if ($isPiloto) {
    $crbm_do_usuario_logado = '';
    $stmt_crbm = $conn->prepare("SELECT crbm_piloto FROM pilotos WHERE id = ?");
    $stmt_crbm->bind_param("i", $_SESSION['user_id']);
    $stmt_crbm->execute();
    $result_crbm = $stmt_crbm->get_result();
    if ($result_crbm->num_rows > 0) {
        $crbm_do_usuario_logado = $result_crbm->fetch_assoc()['crbm_piloto'];
    }
    $stmt_crbm->close();

    if (!empty($crbm_do_usuario_logado)) {
        $sql_controles = $sql_base . " WHERE c.crbm = ? ORDER BY c.id DESC";
        $stmt_controles = $conn->prepare($sql_controles);
        $stmt_controles->bind_param("s", $crbm_do_usuario_logado);
        $stmt_controles->execute();
        $result_controles = $stmt_controles->get_result();
        $stmt_controles->close();
    }
} else {
    $sql_controles = $sql_base . " ORDER BY c.id DESC";
    $result_controles = $conn->query($sql_controles);
}

if (isset($result_controles) && $result_controles->num_rows > 0) {
    while ($row = $result_controles->fetch_assoc()) {
        $controles[] = $row;
    }
}
?>

<style>
/* Adiciona uma dica visual para rolagem em telas pequenas */
@media (max-width: 768px) {
    .table-container::after {
        content: '◄ Arraste para ver mais ►';
        display: block;
        text-align: center;
        font-size: 0.8em;
        color: #999;
        margin-top: 10px;
    }
}
</style>

<div class="main-content">
    <h1>Lista de Controles (Rádios)</h1>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Fabricante/Modelo</th>
                    <th>Nº Série</th>
                    <th>Vinculado ao</th>
                    <th>Lotação (CRBM/OBM)</th>
                    <th>Status</th>
                    <th>ANATEL</th>
                    <?php if ($isAdmin): ?>
                    <th>Ações</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($controles)): ?>
                    <?php foreach ($controles as $controle): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($controle['id'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(($controle['fabricante'] ?? 'N/A') . ' / ' . ($controle['modelo'] ?? 'N/A')); ?></td>
                            <td><?php echo htmlspecialchars($controle['numero_serie'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($controle['prefixo_aeronave'] ?? 'Nenhum'); ?></td>
                            <td>
                                <?php 
                                    $crbm_formatado = preg_replace('/(\d)(CRBM)/', '$1º $2', $controle['crbm'] ?? 'N/A');
                                    echo htmlspecialchars($crbm_formatado . ' / ' . ($controle['obm'] ?? 'N/A'));
                                ?>
                            </td>
                            <td>
                                <?php
                                $status_map = ['ativo' => 'Ativo', 'em_manutencao' => 'Em Manutenção', 'baixado' => 'Baixado'];
                                $status = $controle['status'] ?? 'desconhecido';
                                $status_texto = $status_map[$status] ?? ucfirst($status);
                                ?>
                                <span class="status-<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($status_texto); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($controle['homologacao_anatel'] ?? 'Não'); ?></td>
                            <?php if ($isAdmin): ?>
                            <td class="action-buttons">
                                <a href="editar_controles.php?id=<?php echo $controle['id']; ?>" class="edit-btn">Editar</a>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo $isAdmin ? '8' : '7'; ?>">Nenhum controle cadastrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// 4. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>