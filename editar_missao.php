<?php
require_once 'includes/header.php';
require_once 'gpx_parser.php';

if (!$isAdmin) {
    header("Location: listar_missoes.php");
    exit();
}

$mensagem_status = "";
$missao_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$missao_data = null;
$pilotos_associados_ids = [];

if ($missao_id <= 0) {
    header("Location: listar_missoes.php");
    exit();
}

// LÓGICA DE ATUALIZAÇÃO DA MISSÃO
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn->begin_transaction();
    try {
        $stmt_old_data = $conn->prepare("SELECT aeronave_id, total_distancia_percorrida, total_tempo_voo, altitude_maxima, data_primeira_decolagem, data_ultimo_pouso FROM missoes WHERE id = ?");
        $stmt_old_data->bind_param("i", $missao_id);
        $stmt_old_data->execute();
        $old_data = $stmt_old_data->get_result()->fetch_assoc();
        $stmt_old_data->close();

        if (!$old_data) {
            throw new Exception("Missão original não encontrada para atualização.");
        }

        $stmt_subtract = $conn->prepare("UPDATE aeronaves_logbook SET distancia_total_acumulada = distancia_total_acumulada - ?, tempo_voo_total_acumulado = tempo_voo_total_acumulado - ? WHERE aeronave_id = ?");
        $stmt_subtract->bind_param("ddi", $old_data['total_distancia_percorrida'], $old_data['total_tempo_voo'], $old_data['aeronave_id']);
        $stmt_subtract->execute();
        $stmt_subtract->close();

        $logData = [];

        if (isset($_FILES['gpx_files']) && count(array_filter($_FILES['gpx_files']['name'])) > 0) {
            $stmt_get_files = $conn->prepare("SELECT file_path FROM missoes_gpx_files WHERE missao_id = ?");
            $stmt_get_files->bind_param("i", $missao_id);
            $stmt_get_files->execute();
            $result_files = $stmt_get_files->get_result();
            while ($file = $result_files->fetch_assoc()) {
                if (file_exists($file['file_path'])) unlink($file['file_path']);
            }
            $stmt_get_files->close();

            $stmt_delete_gpx = $conn->prepare("DELETE FROM missoes_gpx_files WHERE missao_id = ?");
            $stmt_delete_gpx->bind_param("i", $missao_id);
            $stmt_delete_gpx->execute();
            $stmt_delete_gpx->close();

            $gpxProcessor = new GPXProcessor();
            foreach ($_FILES['gpx_files']['tmp_name'] as $key => $tmp_name) {
                if (!empty($tmp_name) && $_FILES['gpx_files']['error'][$key] == UPLOAD_ERR_OK) {
                    $gpxProcessor->load($tmp_name);
                }
            }
            $logData = $gpxProcessor->getAggregatedData();
            if ($logData === null) throw new Exception("Não foi possível processar os novos ficheiros GPX.");
            
            $upload_dir = 'uploads/gpx/';
            $stmt_gpx = $conn->prepare("INSERT INTO missoes_gpx_files (missao_id, file_name, file_path, tempo_voo, distancia_percorrida, altura_maxima, data_decolagem, data_pouso) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $individualFileData = $gpxProcessor->getIndividualFileData();
            foreach ($_FILES['gpx_files']['name'] as $key => $name) {
                if ($_FILES['gpx_files']['error'][$key] == UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES['gpx_files']['tmp_name'][$key];
                    $file_name = $missao_id . '_' . uniqid() . '_' . basename($name);
                    $file_path = $upload_dir . $file_name;
                    if (move_uploaded_file($tmp_name, $file_path)) {
                        $file_log_data = $individualFileData[$key];
                        $decolagem_str = $file_log_data['data_decolagem']->format('Y-m-d H:i:s');
                        $pouso_str = $file_log_data['data_pouso']->format('Y-m-d H:i:s');
                        $stmt_gpx->bind_param("issiddss", $missao_id, $name, $file_path, $file_log_data['tempo_voo'], $file_log_data['distancia_percorrida'], $file_log_data['altura_maxima'], $decolagem_str, $pouso_str);
                        $stmt_gpx->execute();
                    }
                }
            }
            $stmt_gpx->close();
        } else {
            $logData = [
                'altitude_maxima' => $old_data['altitude_maxima'],
                'total_distancia_percorrida' => $old_data['total_distancia_percorrida'],
                'total_tempo_voo' => $old_data['total_tempo_voo'],
                'data_primeira_decolagem' => $old_data['data_primeira_decolagem'],
                'data_ultimo_pouso' => $old_data['data_ultimo_pouso']
            ];
        }

        $aeronave_id = intval($_POST['aeronave_id']);
        $pilotos_selecionados = $_POST['pilotos'];
        $data_ocorrencia = htmlspecialchars($_POST['data_ocorrencia']);
        $tipo_ocorrencia = htmlspecialchars($_POST['tipo_ocorrencia']);
        $rgo_ocorrencia = htmlspecialchars($_POST['rgo_ocorrencia']);
        $dados_vitima = htmlspecialchars($_POST['dados_vitima']);

        $stmt_update = $conn->prepare("UPDATE missoes SET aeronave_id=?, data_ocorrencia=?, tipo_ocorrencia=?, rgo_ocorrencia=?, dados_vitima=?, altitude_maxima=?, total_distancia_percorrida=?, total_tempo_voo=?, data_primeira_decolagem=?, data_ultimo_pouso=? WHERE id=?");
        $stmt_update->bind_param("issssdidssi", $aeronave_id, $data_ocorrencia, $tipo_ocorrencia, $rgo_ocorrencia, $dados_vitima, $logData['altitude_maxima'], $logData['total_distancia_percorrida'], $logData['total_tempo_voo'], $logData['data_primeira_decolagem'], $logData['data_ultimo_pouso'], $missao_id);
        $stmt_update->execute();
        $stmt_update->close();
        
        // Apaga as associações de pilotos antigas e insere as novas
        $stmt_delete_pilots = $conn->prepare("DELETE FROM missoes_pilotos WHERE missao_id = ?");
        $stmt_delete_pilots->bind_param("i", $missao_id);
        $stmt_delete_pilots->execute();
        $stmt_delete_pilots->close();
        
        $stmt_pilotos_assoc = $conn->prepare("INSERT INTO missoes_pilotos (missao_id, piloto_id) VALUES (?, ?)");
        foreach ($pilotos_selecionados as $piloto_id) {
            $stmt_pilotos_assoc->bind_param("ii", $missao_id, $piloto_id);
            $stmt_pilotos_assoc->execute();
        }
        $stmt_pilotos_assoc->close();

        $stmt_add_back = $conn->prepare("UPDATE aeronaves_logbook SET distancia_total_acumulada = distancia_total_acumulada + ?, tempo_voo_total_acumulado = tempo_voo_total_acumulado + ? WHERE aeronave_id = ?");
        $stmt_add_back->bind_param("ddi", $logData['total_distancia_percorrida'], $logData['total_tempo_voo'], $aeronave_id);
        $stmt_add_back->execute();
        $stmt_add_back->close();

        $conn->commit();
        $mensagem_status = "<div class='success-message-box'>Missão atualizada com sucesso! A redirecionar...</div>";

    } catch (Exception $e) {
        $conn->rollback();
        $mensagem_status = "<div class='error-message-box'>Erro ao atualizar a missão: " . $e->getMessage() . "</div>";
    }
}

// --- CARREGAR DADOS DA MISSÃO PARA PREENCHER O FORMULÁRIO ---
$stmt_load = $conn->prepare("SELECT * FROM missoes WHERE id = ?");
$stmt_load->bind_param("i", $missao_id);
$stmt_load->execute();
$result_load = $stmt_load->get_result();
if ($result_load->num_rows === 1) {
    $missao_data = $result_load->fetch_assoc();
    
    // Carrega os IDs dos pilotos já associados a esta missão
    $stmt_pilots = $conn->prepare("SELECT piloto_id FROM missoes_pilotos WHERE missao_id = ?");
    $stmt_pilots->bind_param("i", $missao_id);
    $stmt_pilots->execute();
    $result_pilots = $stmt_pilots->get_result();
    while($row = $result_pilots->fetch_assoc()) {
        $pilotos_associados_ids[] = $row['piloto_id'];
    }
    $stmt_pilots->close();

} else {
    $mensagem_status = "<div class='error-message-box'>Missão não encontrada.</div>";
}
$stmt_load->close();

$aeronaves_disponiveis = $conn->query("SELECT id, prefixo, modelo FROM aeronaves ORDER BY prefixo ASC")->fetch_all(MYSQLI_ASSOC);
$pilotos_disponiveis = $conn->query("SELECT id, posto_graduacao, nome_completo FROM pilotos ORDER BY nome_completo ASC")->fetch_all(MYSQLI_ASSOC);
?>

<div class="main-content">
    <h1>Editar Missão <?php echo htmlspecialchars(!empty($missao_data['rgo_ocorrencia']) ? $missao_data['rgo_ocorrencia'] : '#' . $missao_id); ?></h1>
    <?php echo $mensagem_status; ?>

    <?php if ($missao_data): ?>
    <div class="form-container">
        <form id="editMissaoForm" action="editar_missao.php?id=<?php echo $missao_id; ?>" method="POST" enctype="multipart/form-data">
            <fieldset>
                <legend>1. Detalhes da Missão</legend>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="aeronave_id">Aeronave (HAWK):</label>
                        <select id="aeronave_id" name="aeronave_id" required>
                            <?php foreach ($aeronaves_disponiveis as $aeronave): ?>
                                <option value="<?php echo $aeronave['id']; ?>" <?php echo ($missao_data['aeronave_id'] == $aeronave['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($aeronave['prefixo'] . ' - ' . $aeronave['modelo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="pilotos">Piloto(s):</label>
                        <select id="pilotos" name="pilotos[]" required multiple size="5">
                             <?php foreach ($pilotos_disponiveis as $piloto): ?>
                                <option value="<?php echo $piloto['id']; ?>" <?php echo in_array($piloto['id'], $pilotos_associados_ids) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($piloto['posto_graduacao'] . ' ' . $piloto['nome_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Para selecionar múltiplos, segure Ctrl (ou Cmd no Mac).</small>
                    </div>
                    <div class="form-group">
                        <label for="data_ocorrencia">Data da Ocorrência:</label>
                        <input type="date" id="data_ocorrencia" name="data_ocorrencia" value="<?php echo htmlspecialchars($missao_data['data_ocorrencia']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="tipo_ocorrencia">Tipo da Ocorrência:</label>
                        <input type="text" id="tipo_ocorrencia" name="tipo_ocorrencia" value="<?php echo htmlspecialchars($missao_data['tipo_ocorrencia']); ?>" required>
                    </div>
                     <div class="form-group">
                        <label for="rgo_ocorrencia">Nº do RGO:</label>
                        <input type="text" id="rgo_ocorrencia" name="rgo_ocorrencia" value="<?php echo htmlspecialchars($missao_data['rgo_ocorrencia']); ?>">
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="dados_vitima">Dados da Vítima:</label>
                        <textarea id="dados_vitima" name="dados_vitima" rows="3"><?php echo htmlspecialchars($missao_data['dados_vitima']); ?></textarea>
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>2. Ficheiros de Log de Voo (GPX)</legend>
                <div class="form-group">
                    <label for="gpx_files">Substituir Ficheiros GPX (opcional):</label>
                    <input type="file" id="gpx_files" name="gpx_files[]" accept=".gpx" multiple>
                    <small>Selecione novos ficheiros apenas se desejar substituir os existentes. Isto irá recalcular todos os dados de voo.</small>
                    <div id="file-list" style="margin-top: 10px;"></div>
                </div>
            </fieldset>

            <div class="form-actions">
                <a href="listar_missoes.php" class="button-secondary" style="background-color: #6c757d; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px;">Cancelar</a>
                <button type="submit" id="submitButton" style="background-color: #007bff;">Atualizar Missão</button>
            </div>
        </form>
    </div>
    <?php else: ?>
        <p>Missão não encontrada.</p>
    <?php endif; ?>
</div>

<style>
    fieldset { border: 1px solid #ddd; border-radius: 5px; padding: 20px; margin-bottom: 25px; }
    legend { font-weight: 700; color: #34495e; padding: 0 10px; }
    #file-list { font-size: 0.9em; color: #555; }
    select[multiple] { height: auto; min-height: 120px; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const gpxInput = document.getElementById('gpx_files');
    const fileListDiv = document.getElementById('file-list');
    gpxInput.addEventListener('change', function() {
        fileListDiv.innerHTML = '';
        if (this.files.length > 0) {
            const list = document.createElement('ul');
            for (const file of this.files) {
                const item = document.createElement('li');
                item.textContent = `Novo ficheiro: ${file.name}`;
                list.appendChild(item);
            }
            fileListDiv.appendChild(list);
        }
    });

    const successMessage = document.querySelector('.success-message-box');
    if (successMessage) {
        setTimeout(function() {
            window.location.href = 'listar_missoes.php';
        }, 2000);
    }
});
</script>

<?php
require_once 'includes/footer.php';
?>