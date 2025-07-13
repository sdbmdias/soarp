<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

// 2. LÓGICA DA PÁGINA
$mensagem_status = "";
$aeronave_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$aeronave_details = null;
$documentos = [];

if ($aeronave_id <= 0) {
    // Redireciona de volta se nenhum ID for fornecido
    header("Location: checklist.php");
    exit();
}

// --- Lógica de UPLOAD de arquivo (Apenas para Admin) ---
if ($isAdmin && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['documento'])) {
    $nome_documento = htmlspecialchars($_POST['nome_documento']);
    
    // Validação básica
    if (empty($nome_documento) || $_FILES['documento']['error'] != UPLOAD_ERR_OK) {
        $mensagem_status = "<div class='error-message-box'>Erro: Nome do documento é obrigatório e o arquivo deve ser enviado sem erros.</div>";
    } else {
        $target_dir = "uploads/";
        $nome_original = basename($_FILES["documento"]["name"]);
        $tipo_arquivo = $_FILES["documento"]["type"];
        // Cria um nome de arquivo único para evitar sobreposições
        $nome_arquivo_servidor = uniqid() . '-' . $nome_original;
        $caminho_arquivo = $target_dir . $nome_arquivo_servidor;

        if (move_uploaded_file($_FILES["documento"]["tmp_name"], $caminho_arquivo)) {
            $stmt = $conn->prepare("INSERT INTO documentos_aeronave (aeronave_id, nome_documento, nome_arquivo_servidor, caminho_arquivo, tipo_arquivo) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $aeronave_id, $nome_documento, $nome_arquivo_servidor, $caminho_arquivo, $tipo_arquivo);
            if ($stmt->execute()) {
                $mensagem_status = "<div class='success-message-box'>Documento enviado com sucesso!</div>";
            } else {
                $mensagem_status = "<div class='error-message-box'>Erro ao salvar informações no banco de dados.</div>";
            }
            $stmt->close();
        } else {
            $mensagem_status = "<div class='error-message-box'>Erro ao mover o arquivo enviado. Verifique as permissões da pasta 'uploads'.</div>";
        }
    }
}

// --- Busca os detalhes da aeronave e a lista de documentos ---
$stmt_aeronave = $conn->prepare("SELECT prefixo, modelo FROM aeronaves WHERE id = ?");
$stmt_aeronave->bind_param("i", $aeronave_id);
$stmt_aeronave->execute();
$result_aeronave = $stmt_aeronave->get_result();
if ($result_aeronave->num_rows > 0) {
    $aeronave_details = $result_aeronave->fetch_assoc();
}
$stmt_aeronave->close();

$stmt_docs = $conn->prepare("SELECT id, nome_documento, caminho_arquivo FROM documentos_aeronave WHERE aeronave_id = ? ORDER BY nome_documento ASC");
$stmt_docs->bind_param("i", $aeronave_id);
$stmt_docs->execute();
$result_docs = $stmt_docs->get_result();
while($row = $result_docs->fetch_assoc()) {
    $documentos[] = $row;
}
$stmt_docs->close();

?>

<div class="main-content">
    <?php if ($aeronave_details): ?>
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h1>Documentos de: <?php echo htmlspecialchars($aeronave_details['prefixo'] . ' - ' . $aeronave_details['modelo']); ?></h1>
            <a href="checklist.php" style="text-decoration: none; color: #555;"><i class="fas fa-arrow-left"></i> Voltar para a Seleção</a>
        </div>
        
        <?php echo $mensagem_status; ?>

        <?php if ($isAdmin): ?>
        <div class="form-container" style="margin-bottom: 30px;">
            <h2>Adicionar Novo Documento</h2>
            <form action="documentos_aeronave.php?id=<?php echo $aeronave_id; ?>" method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nome_documento">Nome do Documento:</label>
                        <input type="text" id="nome_documento" name="nome_documento" placeholder="Ex: Manual de Voo, Apólice de Seguro" required>
                    </div>
                    <div class="form-group">
                        <label for="documento">Arquivo (PDF, Excel, etc.):</label>
                        <input type="file" id="documento" name="documento" required>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit"><i class="fas fa-upload"></i> Enviar Documento</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="text-align: left;">Nome do Documento</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($documentos)): ?>
                        <?php foreach ($documentos as $doc): ?>
                            <tr>
                                <td style="text-align: left;"><?php echo htmlspecialchars($doc['nome_documento']); ?></td>
                                <td class="action-buttons">
                                    <a href="<?php echo htmlspecialchars($doc['caminho_arquivo']); ?>" class="edit-btn" download>
                                        <i class="fas fa-download"></i> Baixar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="2">Nenhum documento encontrado para esta aeronave.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php else: ?>
        <div class="error-message-box">Aeronave não encontrada.</div>
    <?php endif; ?>
</div>

<?php
// 4. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>