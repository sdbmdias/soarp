<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

// 2. LÓGICA ESPECÍFICA DA PÁGINA
$pilotos = [];

// Atualiza a consulta para ordenar por graduação
$sql_pilotos = "SELECT id, posto_graduacao, nome_completo, rg, crbm_piloto, obm_piloto, status_piloto 
                FROM pilotos 
                ORDER BY
                    CASE posto_graduacao
                        WHEN 'Cel. QOBM' THEN 1
                        WHEN 'Ten. Cel. QOBM' THEN 2
                        WHEN 'Maj. QOBM' THEN 3
                        WHEN 'Cap. QOBM' THEN 4
                        WHEN '1º Ten. QOBM' THEN 5
                        WHEN '2º Ten. QOBM' THEN 6
                        WHEN 'Asp. Oficial' THEN 7
                        WHEN 'Sub. Ten. QPBM' THEN 8
                        WHEN '1º Sgt. QPBM' THEN 9
                        WHEN '2º Sgt. QPBM' THEN 10
                        WHEN '3º Sgt. QPBM' THEN 11
                        WHEN 'Cb. QPBM' THEN 12
                        WHEN 'Sd. QPBM' THEN 13
                        ELSE 14
                    END, nome_completo ASC";

$result_pilotos = $conn->query($sql_pilotos);
if ($result_pilotos) {
    while($row = $result_pilotos->fetch_assoc()) {
        $pilotos[] = $row;
    }
}
?>

<div class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1>Pilotos Cadastrados</h1>
        <?php if ($isAdmin): ?>
            <a href="cadastro_pilotos.php" class="form-actions button" style="text-decoration: none; display: inline-block; padding: 10px 20px; background-color: #28a745; color: #fff;">
                <i class="fas fa-plus"></i> Adicionar Novo Piloto
            </a>
        <?php endif; ?>
    </div>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nome do Piloto</th>
                    <th>RG</th>
                    <th>CRBM</th>
                    <th>OBM/Seção</th>
                    <th>Status</th>
                    <?php if ($isAdmin): ?>
                    <th>Ações</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($pilotos)): ?>
                    <?php foreach ($pilotos as $piloto): ?>
                        <tr>
                            <td style="text-align: center;">
                                <strong><?php echo htmlspecialchars($piloto['posto_graduacao']); ?></strong> <?php echo htmlspecialchars($piloto['nome_completo']); ?>
                            </td>
                            <td style="text-align: center;"><?php echo htmlspecialchars($piloto['rg']); ?></td>
                            <td style="text-align: center;"><?php echo htmlspecialchars(preg_replace('/(\d)(CRBM)/', '$1º $2', $piloto['crbm_piloto'])); ?></td>
                            <td style="text-align: center;"><?php echo htmlspecialchars($piloto['obm_piloto']); ?></td>
                            <td style="text-align: center;">
                                <span class="status-<?php echo str_replace(' ', '_', strtolower($piloto['status_piloto'])); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $piloto['status_piloto'])); ?>
                                </span>
                            </td>
                            <?php if ($isAdmin): ?>
                            <td class="action-buttons">
                                <a href="editar_pilotos.php?id=<?php echo $piloto['id']; ?>" class="edit-btn">Editar</a>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo $isAdmin ? '6' : '5'; ?>">Nenhum piloto cadastrado.</td>
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