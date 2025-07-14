<?php
require_once 'includes/header.php';
require_once 'gpx_parser.php'; 

$mensagem_status = "";

// Lógica para buscar aeronaves
$aeronaves_disponiveis = [];
if ($isAdmin) {
    $sql_aeronaves = "SELECT id, prefixo, modelo FROM aeronaves WHERE status = 'ativo' ORDER BY prefixo ASC";
    $result_aeronaves = $conn->query($sql_aeronaves);
} else {
    $obm_do_piloto = '';
    $stmt_obm = $conn->prepare("SELECT obm_piloto FROM pilotos WHERE id = ?");
    $stmt_obm->bind_param("i", $_SESSION['user_id']);
    $stmt_obm->execute();
    $result_obm = $stmt_obm->get_result();
    if ($result_obm->num_rows > 0) $obm_do_piloto = $result_obm->fetch_assoc()['obm_piloto'];
    $stmt_obm->close();

    if (!empty($obm_do_piloto)) {
        $stmt_aeronaves = $conn->prepare("SELECT id, prefixo, modelo FROM aeronaves WHERE status = 'ativo' AND obm = ? ORDER BY prefixo ASC");
        $stmt_aeronaves->bind_param("s", $obm_do_piloto);
        $stmt_aeronaves->execute();
        $result_aeronaves = $stmt_aeronaves->get_result();
    }
}
if (isset($result_aeronaves) && $result_aeronaves->num_rows > 0) {
    while($row = $result_aeronaves->fetch_assoc()) $aeronaves_disponiveis[] = $row;
}

// Busca pilotos com posto/graduação
$pilotos_disponiveis = [];
$sql_pilotos = "SELECT id, posto_graduacao, nome_completo FROM pilotos WHERE status_piloto = 'ativo' ORDER BY nome_completo ASC";
$result_pilotos = $conn->query($sql_pilotos);
if (isset($result_pilotos) && $result_pilotos->num_rows > 0) {
    while($row = $result_pilotos->fetch_assoc()) $pilotos_disponiveis[] = $row;
}


// LÓGICA DE SUBMISSÃO DO FORMULÁRIO
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    if (isset($_FILES['gpx_files']) && count(array_filter($_FILES['gpx_files']['name'])) > 0 && isset($_POST['pilotos']) && !empty($_POST['pilotos'])) {
        
        $conn->begin_transaction();
        try {
            $gpxProcessor = new GPXProcessor();
            foreach ($_FILES['gpx_files']['tmp_name'] as $key => $tmp_name) {
                if (!empty($tmp_name) && $_FILES['gpx_files']['error'][$key] == UPLOAD_ERR_OK) {
                    $gpxProcessor->load($tmp_name);
                }
            }
            
            $logData = $gpxProcessor->getAggregatedData();

            if ($logData === null) {
                throw new Exception("Não foi possível processar os ficheiros GPX. Verifique se são válidos e contêm trilhas.");
            }
            
            $aeronave_id = intval($_POST['aeronave_id']);
            $pilotos_selecionados = $_POST['pilotos']; // Agora é um array
            $data_ocorrencia = htmlspecialchars($_POST['data_ocorrencia']);
            $tipo_ocorrencia = htmlspecialchars($_POST['tipo_ocorrencia']);
            $rgo_ocorrencia = htmlspecialchars($_POST['rgo_ocorrencia']);
            $dados_vitima = htmlspecialchars($_POST['dados_vitima']);

            // Insere a missão principal SEM o piloto_id
            $stmt_missao = $conn->prepare("INSERT INTO missoes (aeronave_id, data_ocorrencia, tipo_ocorrencia, rgo_ocorrencia, dados_vitima, altitude_maxima, total_distancia_percorrida, total_tempo_voo, data_primeira_decolagem, data_ultimo_pouso) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_missao->bind_param("issssdidss", $aeronave_id, $data_ocorrencia, $tipo_ocorrencia, $rgo_ocorrencia, $dados_vitima, $logData['altitude_maxima'], $logData['total_distancia_percorrida'], $logData['total_tempo_voo'], $logData['data_primeira_decolagem'], $logData['data_ultimo_pouso']);
            $stmt_missao->execute();
            $missao_id = $conn->insert_id;

            // Associa os pilotos selecionados à missão
            $stmt_pilotos_assoc = $conn->prepare("INSERT INTO missoes_pilotos (missao_id, piloto_id) VALUES (?, ?)");
            foreach ($pilotos_selecionados as $piloto_id) {
                $stmt_pilotos_assoc->bind_param("ii", $missao_id, $piloto_id);
                $stmt_pilotos_assoc->execute();
            }
            $stmt_pilotos_assoc->close();

            // Lógica para guardar os ficheiros e atualizar o logbook (sem alterações)
            $upload_dir = 'uploads/gpx/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
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
            $stmt_logbook = $conn->prepare("INSERT INTO aeronaves_logbook (aeronave_id, distancia_total_acumulada, tempo_voo_total_acumulado) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE distancia_total_acumulada = distancia_total_acumulada + VALUES(distancia_total_acumulada), tempo_voo_total_acumulado = tempo_voo_total_acumulado + VALUES(tempo_voo_total_acumulado)");
            $stmt_logbook->bind_param("idi", $aeronave_id, $logData['total_distancia_percorrida'], $logData['total_tempo_voo']);
            $stmt_logbook->execute();

            $conn->commit();
            $mensagem_status = "<div class='success-message-box'>Missão registrada com sucesso!</div>";
        } catch (Exception $e) {
            $conn->rollback();
            $mensagem_status = "<div class='error-message-box'>Erro ao registrar a missão: " . $e->getMessage() . "</div>";
        }

    } else {
        $mensagem_status = "<div class='error-message-box'>Erro: Preencha todos os campos obrigatórios, incluindo a seleção de pelo menos um piloto e um ficheiro GPX.</div>";
    }
}
?>

<div class="main-content">
    <h1>Registrar Nova Missão</h1>
    <?php echo $mensagem_status; ?>
    <div class="form-container">
        <form id="missaoForm" action="cadastro_missao.php" method="POST" enctype="multipart/form-data">
            <fieldset>
                <legend>1. Detalhes da Missão</legend>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="aeronave_id">Aeronave (HAWK):</label>
                        <select id="aeronave_id" name="aeronave_id" required>
                            <option value="">Selecione a Aeronave</option>
                            <?php foreach ($aeronaves_disponiveis as $aeronave): ?>
                                <option value="<?php echo $aeronave['id']; ?>"><?php echo htmlspecialchars($aeronave['prefixo'] . ' - ' . $aeronave['modelo']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="pilotos">Piloto(s):</label>
                        <select id="pilotos" name="pilotos[]" required multiple size="5">
                            <?php foreach ($pilotos_disponiveis as $piloto): ?>
                                <option value="<?php echo $piloto['id']; ?>">
                                    <?php echo htmlspecialchars($piloto['posto_graduacao'] . ' ' . $piloto['nome_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Para selecionar múltiplos pilotos, segure a tecla Ctrl (ou Cmd no Mac) e clique nos nomes.</small>
                    </div>
                    <div class="form-group">
                        <label for="data_ocorrencia">Data da Ocorrência:</label>
                        <input type="date" id="data_ocorrencia" name="data_ocorrencia" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="tipo_ocorrencia">Tipo da Ocorrência:</label>
                        <input type="text" id="tipo_ocorrencia" name="tipo_ocorrencia" placeholder="Ex: Busca e salvamento, Incêndio florestal" required>
                    </div>
                    <div class="form-group">
                        <label for="rgo_ocorrencia">Nº do RGO (se aplicável):</label>
                        <input type="text" id="rgo_ocorrencia" name="rgo_ocorrencia" placeholder="Ex: 123456/2025">
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="dados_vitima">Dados da Vítima (se aplicável):</label>
                        <textarea id="dados_vitima" name="dados_vitima" rows="3" placeholder="Informações relevantes sobre a vítima ou alvo da busca."></textarea>
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>2. Ficheiros de Log de Voo (GPX)</legend>
                <div class="form-group">
                    <label for="gpx_files">Selecione os ficheiros GPX da missão:</label>
                    <input type="file" id="gpx_files" name="gpx_files[]" accept=".gpx" multiple required>
                    <small>Pode selecionar vários ficheiros. Eles serão unificados para gerar o logbook.</small>
                    <div id="file-list" style="margin-top: 10px;"></div>
                </div>
            </fieldset>

            <div class="form-actions">
                <button type="submit" id="submitButton">Registrar Missão</button>
            </div>
        </form>
    </div>
</div>

<style>
    fieldset { border: 1px solid #ddd; border-radius: 5px; padding: 20px; margin-bottom: 25px; }
    legend { font-weight: 700; color: #34495e; padding: 0 10px; }
    #file-list { font-size: 0.9em; color: #555; }
    select[multiple] {
        height: auto;
        min-height: 120px;
    }
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
                item.textContent = file.name;
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