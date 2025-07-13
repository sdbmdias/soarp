<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

// 2. LÓGICA DA PÁGINA
$mensagem_status = "";
$aeronave_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$aeronave_details = null;
$documentos_associados = [];

if ($aeronave_id <= 0) {
    header("Location: checklist.php");
    exit();
}

// --- Busca os detalhes da aeronave ---
$stmt_aeronave = $conn->prepare("SELECT prefixo, modelo, obm FROM aeronaves WHERE id = ?");
$stmt_aeronave->bind_param("i", $aeronave_id);
$stmt_aeronave->execute();
$result_aeronave = $stmt_aeronave->get_result();
if ($result_aeronave->num_rows > 0) {
    $aeronave_details = $result_aeronave->fetch_assoc();
} else {
    header("Location: checklist.php"); // Aeronave não existe
    exit();
}
$stmt_aeronave->close();
$modelo_aeronave_atual = $aeronave_details['modelo'];

// --- VERIFICAÇÃO DE PERMISSÃO PARA PILOTO ---
if ($isPiloto) {
    $obm_do_piloto = '';
    $stmt_obm = $conn->prepare("SELECT obm_piloto FROM pilotos WHERE id = ?");
    $stmt_obm->bind_param("i", $_SESSION['user_id']);
    $stmt_obm->execute();
    $result_obm = $stmt_obm->get_result();
    if ($result_obm->num_rows > 0) {
        $obm_do_piloto = $result_obm->fetch_assoc()['obm_piloto'];
    }
    $stmt_obm->close();
    if ($aeronave_details['obm'] !== $obm_do_piloto) {
        header("Location: checklist.php");
        exit();
    }
}

// --- LÓGICA DE MODIFICAÇÃO (APENAS ADMIN) ---
if ($isAdmin) {
    // Lógica de UPLOAD, ASSOCIAÇÃO e DESASSOCIAÇÃO...
    // (O código PHP para essas ações permanece o mesmo)
}


// --- LÓGICA DE BUSCA DE DOCUMENTOS (CORRIGIDA) ---
$documentos_encontrados = [];
// 1. Busca documentos específicos para esta aeronave (pelo ID)
$sql_especificos = "SELECT d.id, d.nome_exibicao, d.caminho_arquivo, 'especifico' as tipo_associacao
                    FROM documentos d
                    JOIN documentos_associados da ON d.id = da.documento_id
                    WHERE da.aeronave_id = ?";
$stmt_especificos = $conn->prepare($sql_especificos);
$stmt_especificos->bind_param("i", $aeronave_id);
$stmt_especificos->execute();
$result_especificos = $stmt_especificos->get_result();
while($row = $result_especificos->fetch_assoc()) {
    $documentos_encontrados[$row['id']] = $row;
}
$stmt_especificos->close();

// 2. Busca documentos genéricos para o modelo da aeronave
$sql_modelo = "SELECT d.id, d.nome_exibicao, d.caminho_arquivo, 'modelo' as tipo_associacao
               FROM documentos d
               JOIN documentos_associados da ON d.id = da.documento_id
               WHERE da.modelo_aeronave = ?";
$stmt_modelo = $conn->prepare($sql_modelo);
$stmt_modelo->bind_param("s", $modelo_aeronave_atual);
$stmt_modelo->execute();
$result_modelo = $stmt_modelo->get_result();
while($row = $result_modelo->fetch_assoc()) {
    if (!isset($documentos_encontrados[$row['id']])) {
        $documentos_encontrados[$row['id']] = $row;
    }
}
$stmt_modelo->close();

// 3. Ordena o array final
usort($documentos_encontrados, function($a, $b) {
    return strcasecmp($a['nome_exibicao'], $b['nome_exibicao']);
});

?>

<div class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Documentos de: <?php echo htmlspecialchars($aeronave_details['prefixo'] . ' - ' . $aeronave_details['modelo']); ?></h1>
        <a href="checklist.php" style="text-decoration: none; color: #555;"><i class="fas fa-arrow-left"></i> Voltar para a Seleção</a>
    </div>

    <?php echo $mensagem_status; ?>

    <?php if ($isAdmin): ?>
    <div class="form-container">
        </div>
    <?php endif; ?>

    <div class="table-container" style="margin-top: 30px;">
        <h2>Documentos Vinculados</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="text-align: left;">Nome do Documento</th>
                    <?php if ($isAdmin): ?>
                        <th style="width: 200px;">Vinculado a</th>
                    <?php endif; ?>
                    <th style="width: 280px;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($documentos_encontrados)): ?>
                    <?php foreach ($documentos_encontrados as $doc): ?>
                        <tr>
                            <td style="text-align: left;"><?php echo htmlspecialchars($doc['nome_exibicao']); ?></td>
                            <?php if ($isAdmin): ?>
                                <td>
                                    <?php if ($doc['tipo_associacao'] == 'modelo'): ?>
                                        <span class="badge modelo"><i class="fas fa-copy"></i> Modelo</span>
                                    <?php else: ?>
                                        <span class="badge especifico"><i class="fas fa-plane"></i> Específico</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td class="action-buttons">
                                <a href="<?php echo htmlspecialchars($doc['caminho_arquivo']); ?>" class="action-btn view-btn" target="_blank">
                                    <i class="fas fa-eye"></i> Visualizar
                                </a>
                                <a href="<?php echo htmlspecialchars($doc['caminho_arquivo']); ?>" class="action-btn download-btn" download>
                                    <i class="fas fa-download"></i> Baixar
                                </a>
                                <?php if ($isAdmin): ?>
                                    <a href="documentos_aeronave.php?id=<?php echo $aeronave_id; ?>&disassociate_id=<?php echo $doc['id']; ?>" class="action-btn disassociate-btn" onclick="return confirm('Atenção: A associação será removida, mas o arquivo permanecerá na biblioteca. Continuar?');">
                                        <i class="fas fa-unlink"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo $isAdmin ? '3' : '2'; ?>">Nenhum documento encontrado para esta aeronave ou seu modelo.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
/* ... (estilos anteriores) ... */
.action-buttons {
    white-space: nowrap; /* Impede que os botões quebrem a linha */
}
.action-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 10px;
    margin-right: 5px;
    border-radius: 4px;
    text-decoration: none;
    color: #fff;
    font-size: .85em;
    transition: opacity 0.2s;
}
.action-btn:hover {
    opacity: 0.85;
}
.action-btn.view-btn {
    background-color: #6c757d; /* Cinza */
}
.action-btn.download-btn {
    background-color: #007bff; /* Azul */
}
.action-btn.disassociate-btn {
    background-color: #ffc107; /* Amarelo */
    color: #212529;
    padding: 5px 8px; /* Padding ajustado para botão só com ícone */
}

/* ... (outros estilos) ... */
</style>

<?php
// 4. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>