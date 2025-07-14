<?php
require_once 'includes/header.php';
require_once 'gpx_parser.php'; 

$mensagem_status = "";

// 1. Busca aeronaves, incluindo o CRBM para o filtro de pilotos
$aeronaves_disponiveis = [];
$sql_aeronaves = "SELECT id, prefixo, modelo, crbm FROM aeronaves WHERE status = 'ativo' ORDER BY prefixo ASC";
$result_aeronaves = $conn->query($sql_aeronaves);
if ($result_aeronaves) {
    while($row = $result_aeronaves->fetch_assoc()) {
        $aeronaves_disponiveis[] = $row;
    }
}

// 2. Busca TODOS os pilotos ativos e agrupa por CRBM para o JavaScript
$pilotos_por_crbm = [];
$sql_pilotos = "SELECT id, posto_graduacao, nome_completo, crbm_piloto FROM pilotos WHERE status_piloto = 'ativo' ORDER BY nome_completo ASC";
$result_pilotos = $conn->query($sql_pilotos);
if ($result_pilotos) {
    while($row = $result_pilotos->fetch_assoc()) {
        $crbm = $row['crbm_piloto'];
        if (!isset($pilotos_por_crbm[$crbm])) {
            $pilotos_por_crbm[$crbm] = [];
        }
        $pilotos_por_crbm[$crbm][] = $row;
    }
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
                throw new Exception("Não foi possível processar os ficheiros GPX.");
            }
            
            $aeronave_id = intval($_POST['aeronave_id']);
            $pilotos_selecionados = array_unique(array_filter($_POST['pilotos']));
            $data_ocorrencia = htmlspecialchars($_POST['data_ocorrencia']);
            $tipo_ocorrencia = htmlspecialchars($_POST['tipo_ocorrencia']);
            $rgo_ocorrencia = htmlspecialchars($_POST['rgo_ocorrencia']);
            $dados_vitima = htmlspecialchars($_POST['dados_vitima']);

            // Atribui valores do log agregado a variáveis
            $altitude_maxima = $logData['altitude_maxima'];
            $total_distancia_percorrida = $logData['total_distancia_percorrida'];
            $total_tempo_voo = $logData['total_tempo_voo'];
            $data_primeira_decolagem = $logData['data_primeira_decolagem'];
            $data_ultimo_pouso = $logData['data_ultimo_pouso'];

            $stmt_missao = $conn->prepare("INSERT INTO missoes (aeronave_id, data_ocorrencia, tipo_ocorrencia, rgo_ocorrencia, dados_vitima, altitude_maxima, total_distancia_percorrida, total_tempo_voo, data_primeira_decolagem, data_ultimo_pouso) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_missao->bind_param("issssdidss", $aeronave_id, $data_ocorrencia, $tipo_ocorrencia, $rgo_ocorrencia, $dados_vitima, $altitude_maxima, $total_distancia_percorrida, $total_tempo_voo, $data_primeira_decolagem, $data_ultimo_pouso);
            $stmt_missao->execute();
            $missao_id = $conn->insert_id;
            $stmt_missao->close();

            $stmt_pilotos_assoc = $conn->prepare("INSERT INTO missoes_pilotos (missao_id, piloto_id) VALUES (?, ?)");
            foreach ($pilotos_selecionados as $piloto_id) {
                $pid = intval($piloto_id);
                $stmt_pilotos_assoc->bind_param("ii", $missao_id, $pid);
                $stmt_pilotos_assoc->execute();
            }
            $stmt_pilotos_assoc->close();

            $upload_dir = 'uploads/gpx/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Prepara a inserção de ficheiros GPX
            $stmt_gpx = $conn->prepare("INSERT INTO missoes_gpx_files (missao_id, file_name, file_path, tempo_voo, distancia_percorrida, altura_maxima, data_decolagem, data_pouso) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            // Prepara a inserção de coordenadas
            $stmt_coords = $conn->prepare("INSERT INTO missao_coordenadas (gpx_file_id, latitude, longitude, altitude, timestamp_ponto) VALUES (?, ?, ?, ?, ?)");

            $individualFileData = $gpxProcessor->getIndividualFileData();
            foreach ($_FILES['gpx_files']['name'] as $key => $name) {
                if ($_FILES['gpx_files']['error'][$key] == UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES['gpx_files']['tmp_name'][$key];
                    $file_name_uniq = $missao_id . '_' . uniqid() . '_' . basename($name);
                    $file_path = $upload_dir . $file_name_uniq;
                    
                    if (move_uploaded_file($tmp_name, $file_path)) {
                        $file_log_data = $individualFileData[$key];
                        
                        $tempo_voo_individual = $file_log_data['tempo_voo'];
                        $distancia_individual = $file_log_data['distancia_percorrida'];
                        $altura_maxima_individual = $file_log_data['altura_maxima'];
                        $decolagem_str = $file_log_data['data_decolagem']->format('Y-m-d H:i:s');
                        $pouso_str = $file_log_data['data_pouso']->format('Y-m-d H:i:s');
                        
                        $stmt_gpx->bind_param("issiddss", $missao_id, $name, $file_path, $tempo_voo_individual, $distancia_individual, $altura_maxima_individual, $decolagem_str, $pouso_str);
                        $stmt_gpx->execute();
                        $gpx_file_id = $conn->insert_id;

                        // Insere todas as coordenadas para este ficheiro
                        foreach ($file_log_data['trackPoints'] as $point) {
                            $lat = $point['lat'];
                            $lon = $point['lon'];
                            $ele = $point['ele'];
                            $time = $point['time']->format('Y-m-d H:i:s');
                            $stmt_coords->bind_param("idds", $gpx_file_id, $lat, $lon, $ele, $time);
                            $stmt_coords->execute();
                        }
                    }
                }
            }
            $stmt_gpx->close();
            $stmt_coords->close();
            
            $stmt_logbook = $conn->prepare("INSERT INTO aeronaves_logbook (aeronave_id, distancia_total_acumulada, tempo_voo_total_acumulado) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE distancia_total_acumulada = distancia_total_acumulada + VALUES(distancia_total_acumulada), tempo_voo_total_acumulado = tempo_voo_total_acumulado + VALUES(tempo_voo_total_acumulado)");
            $stmt_logbook->bind_param("idi", $aeronave_id, $total_distancia_percorrida, $total_tempo_voo);
            $stmt_logbook->execute();
            $stmt_logbook->close();

            $conn->commit();
            $mensagem_status = "<div class='success-message-box'>Missão registrada com sucesso! A redirecionar...</div>";
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
                                <option value="<?php echo $aeronave['id']; ?>" data-crbm="<?php echo htmlspecialchars($aeronave['crbm']); ?>">
                                    <?php echo htmlspecialchars($aeronave['prefixo'] . ' - ' . $aeronave['modelo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="pilotos">Piloto(s):</label>
                        <div id="pilots-container">
                            <small>Selecione uma aeronave para carregar a lista de pilotos.</small>
                        </div>
                        <button type="button" id="add-pilot-btn" class="button-secondary" style="margin-top: 10px;" disabled>Adicionar outro Piloto</button>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="data_ocorrencia">Data da Ocorrência:</label>
                        <input type="date" id="data_ocorrencia" name="data_ocorrencia" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="tipo_ocorrencia">Tipo da Ocorrência:</label>
                        <input type="text" id="tipo_ocorrencia" name="tipo_ocorrencia" placeholder="Ex: Busca e salvamento" required>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
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
                    <small>Pode selecionar vários ficheiros. Eles serão unificados.</small>
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
    .button-secondary { background-color: #6c757d; color: #fff; padding: 8px 12px; border: none; border-radius: 5px; cursor: pointer; font-size: 0.9em; }
    .button-secondary:disabled { background-color: #c8cbcf; cursor: not-allowed; }
    .pilot-selector-wrapper { display: flex; align-items: center; gap: 10px; margin-bottom: 5px; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Passa os dados de PHP para JavaScript
    const pilotosPorCrbm = <?php echo json_encode($pilotos_por_crbm); ?>;
    const isUserAdmin = <?php echo json_encode($isAdmin); ?>;

    const aeronaveSelect = document.getElementById('aeronave_id');
    const pilotsContainer = document.getElementById('pilots-container');
    const addPilotBtn = document.getElementById('add-pilot-btn');

    let availablePilots = [];

    // Função para criar um novo seletor de piloto
    function createPilotSelector(pilots) {
        const wrapper = document.createElement('div');
        wrapper.className = 'pilot-selector-wrapper';

        const select = document.createElement('select');
        select.name = 'pilotos[]';
        select.className = 'form-control';
        select.required = true;

        const placeholder = document.createElement('option');
        placeholder.value = "";
        placeholder.textContent = "Selecione um piloto...";
        select.appendChild(placeholder);
        
        pilots.forEach(piloto => {
            const option = document.createElement('option');
            option.value = piloto.id;
            option.textContent = `${piloto.posto_graduacao} ${piloto.nome_completo}`;
            select.appendChild(option);
        });
        
        wrapper.appendChild(select);
        return wrapper;
    }

    // Função para atualizar a lista de pilotos disponíveis
    function updateAvailablePilots(crbm) {
        if (isUserAdmin) {
            // Admin vê todos os pilotos
            availablePilots = Object.values(pilotosPorCrbm).flat();
        } else {
            // Piloto vê apenas os do CRBM da aeronave
            availablePilots = pilotosPorCrbm[crbm] || [];
        }
    }
    
    // Evento quando a aeronave é selecionada
    aeronaveSelect.addEventListener('change', function() {
        pilotsContainer.innerHTML = '';
        const selectedOption = this.options[this.selectedIndex];
        const crbm = selectedOption.dataset.crbm;

        if (crbm) {
            updateAvailablePilots(crbm);
            if(availablePilots.length > 0) {
                const firstSelectorWrapper = createPilotSelector(availablePilots);
                pilotsContainer.appendChild(firstSelectorWrapper);
                addPilotBtn.disabled = availablePilots.length <= 1;
            } else {
                pilotsContainer.innerHTML = '<small style="color:red;">Nenhum piloto ativo encontrado para o CRBM desta aeronave.</small>';
                addPilotBtn.disabled = true;
            }
        } else {
            pilotsContainer.innerHTML = '<small>Selecione uma aeronave para carregar a lista de pilotos.</small>';
            addPilotBtn.disabled = true;
        }
    });

    // Evento para adicionar um novo piloto
    addPilotBtn.addEventListener('click', function() {
        const currentSelectors = pilotsContainer.querySelectorAll('select');
        const selectedPilotIds = Array.from(currentSelectors).map(s => s.value).filter(Boolean);
        
        const remainingPilots = availablePilots.filter(p => !selectedPilotIds.includes(p.id.toString()));

        if (remainingPilots.length > 0) {
            const newSelectorWrapper = createPilotSelector(remainingPilots);
            
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.textContent = 'Remover';
            removeBtn.className = 'button-secondary';
            removeBtn.style.backgroundColor = '#dc3545';
            removeBtn.onclick = function() {
                newSelectorWrapper.remove();
                addPilotBtn.disabled = false; // Habilita o botão novamente ao remover
            };
            
            newSelectorWrapper.appendChild(removeBtn);
            pilotsContainer.appendChild(newSelectorWrapper);

            if (remainingPilots.length <= 1) {
                addPilotBtn.disabled = true;
            }
        } else {
            addPilotBtn.disabled = true;
        }
    });
    
    // Lógica para mostrar ficheiros GPX selecionados
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
    
    // Redirecionamento após sucesso
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